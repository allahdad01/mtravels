<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

require_once '../includes/conn.php';
$tenant_id = $_SESSION['tenant_id'];
header('Content-Type: application/json');

// Get the JSON payload
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['family_id'])) {
    echo json_encode(["success" => false, "message" => "Family ID is required"]);
    exit();
}

$family_id = intval($data['family_id']);

$conn->begin_transaction();

try {
    // Check if the family exists
    $stmt_check = $conn->prepare("SELECT family_id FROM families WHERE family_id = ? AND tenant_id = ?");
    $stmt_check->bind_param("ii", $family_id, $tenant_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Family not found"]);
        exit();
    }
    $stmt_check->close();

    // Get all family members (umrah bookings)
    $stmt_get_members = $conn->prepare("
        SELECT ub.*, c.client_type 
        FROM umrah_bookings ub 
        JOIN clients c ON ub.sold_to = c.id 
        WHERE ub.family_id = ? AND ub.tenant_id = ?
    ");
    $stmt_get_members->bind_param("ii", $family_id, $tenant_id);
    $stmt_get_members->execute();
    $members_result = $stmt_get_members->get_result();

    while ($member = $members_result->fetch_assoc()) {
        $booking_id = $member['booking_id'];
        $client_id = $member['sold_to'];
        $supplier_id = $member['supplier'];
        $currency = $member['currency'];
        $client_type = $member['client_type'];
        $mainAccountId = $member['paid_to'];

        // Handle client transactions
        $clientTransactions = "SELECT id, amount, type, created_at FROM client_transactions 
                               WHERE client_id = ? AND transaction_of = 'umrah' 
                               AND reference_id = ? AND tenant_id = ?";
        $stmt = $conn->prepare($clientTransactions);
        $stmt->bind_param("iiii", $client_id, $booking_id, $tenant_id);
        $stmt->execute();
        $clientResults = $stmt->get_result();

        while ($row = $clientResults->fetch_assoc()) {
            $amount = abs($row['amount']);
            $transaction_date = $row['created_at'];
            $transaction_id = $row['id'];
            $transaction_type = $row['type'];

            // Only adjust balance for regular clients
            if ($client_type === 'regular') {
                // Adjust Client Balance
                $clientBalanceField = ($currency == 'USD') ? 'usd_balance' : 'afs_balance';
                
                if ($transaction_type == 'debit') {
                    $adjustClientBalance = "UPDATE clients 
                                           SET $clientBalanceField = $clientBalanceField + ? 
                                           WHERE id = ? AND tenant_id = ?";
                } else {
                    $adjustClientBalance = "UPDATE clients 
                                           SET $clientBalanceField = $clientBalanceField - ? 
                                           WHERE id = ? AND tenant_id = ?";
                }
                
                $stmt = $conn->prepare($adjustClientBalance);
                $stmt->bind_param("di", $amount, $client_id, $tenant_id);
                $stmt->execute();

                // Update subsequent transactions' running balances
                if ($transaction_type == 'debit') {
                    $updateSubsequentBalances = "UPDATE client_transactions 
                                                SET balance = balance + ? 
                                                WHERE client_id = ? AND created_at > ? 
                                                AND currency = ?
                                                AND tenant_id = ?
                                                ORDER BY created_at ASC";
                } else {
                    $updateSubsequentBalances = "UPDATE client_transactions 
                                                SET balance = balance - ? 
                                                WHERE client_id = ? AND created_at > ? 
                                                AND currency = ?
                                                AND tenant_id = ?
                                                ORDER BY created_at ASC";
                }
                
                $stmtUpdate = $conn->prepare($updateSubsequentBalances);
                $stmtUpdate->bind_param("dissi", $amount, $client_id, $transaction_date, $currency, $tenant_id);
                $stmtUpdate->execute();
            }

            // Delete Client Transaction
            $deleteClientTransaction = "DELETE FROM client_transactions WHERE id = ? AND tenant_id = ?";
            $stmt = $conn->prepare($deleteClientTransaction);
            $stmt->bind_param("ii", $transaction_id, $tenant_id);
            $stmt->execute();
        }

        // Handle supplier transactions
        $supplierTypeQuery = "SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?";
        $stmt = $conn->prepare($supplierTypeQuery);
        $stmt->bind_param("ii", $supplier_id, $tenant_id);
        $stmt->execute();
        $supplierTypeResult = $stmt->get_result();
        $supplierTypeRow = $supplierTypeResult->fetch_assoc();
        $supplier_type = $supplierTypeRow['supplier_type'];

        $supplierTransactions = "SELECT id, amount, transaction_type, transaction_date FROM supplier_transactions 
                                 WHERE supplier_id = ? AND transaction_of = 'umrah' 
                                 AND reference_id = ? AND tenant_id = ?";
        $stmt = $conn->prepare($supplierTransactions);
        $stmt->bind_param("iiii", $supplier_id, $booking_id, $tenant_id);
        $stmt->execute();
        $supplierResults = $stmt->get_result();

        while ($row = $supplierResults->fetch_assoc()) {
            $amount = $row['amount'];
            $transaction_date = $row['transaction_date'];
            $transaction_id = $row['id'];
            
            // Only adjust balance for external suppliers
            if ($supplier_type === 'External') {
                // Adjust Supplier Balance
                $adjustSupplierBalance = "UPDATE suppliers 
                                          SET balance = balance " . ($row['transaction_type'] == 'Credit' ? '-' : '+') . " ? 
                                          WHERE id = ? AND tenant_id = ?";
                $stmt = $conn->prepare($adjustSupplierBalance);
                $stmt->bind_param("di", $amount, $supplier_id, $tenant_id);
                $stmt->execute();
                
                // Update subsequent transactions' running balances
                $updateSubsequentSupplierBalances = "UPDATE supplier_transactions 
                                                    SET balance = balance " . ($row['transaction_type'] == 'Credit' ? '-' : '+') . " ? 
                                                    WHERE supplier_id = ? AND transaction_date > ?
                                                    AND tenant_id = ?
                                                    ORDER BY transaction_date ASC";
                $stmtUpdate = $conn->prepare($updateSubsequentSupplierBalances);
                $stmtUpdate->bind_param("disi", $amount, $supplier_id, $transaction_date, $tenant_id);
                $stmtUpdate->execute();
            }

            // Delete Supplier Transaction
            $deleteSupplierTransaction = "DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ?";
            $stmt = $conn->prepare($deleteSupplierTransaction);
            $stmt->bind_param("ii", $transaction_id, $tenant_id);
            $stmt->execute();
        }

        // Handle main account transactions
        if ($mainAccountId && $mainAccountId > 0) {
            // First fetch all transactions to calculate balances
            $stmt_fetch_main_transactions = $conn->prepare("
                SELECT mat.id, mat.amount, mat.type, mat.currency, mat.created_at
                FROM main_account_transactions mat
                JOIN umrah_transactions ut ON mat.reference_id = ut.id
                WHERE ut.umrah_booking_id = ? AND mat.transaction_of = 'umrah'
                AND mat.tenant_id = ?
            ");
            $stmt_fetch_main_transactions->bind_param("ii", $booking_id, $tenant_id);
            $stmt_fetch_main_transactions->execute();
            $result_main_transactions = $stmt_fetch_main_transactions->get_result();
            
            // Store transactions in an array for processing
            $transactions = [];
            while ($main_transaction = $result_main_transactions->fetch_assoc()) {
                $transactions[] = $main_transaction;
            }
            $stmt_fetch_main_transactions->close();

            // Process each transaction for balance adjustments
            foreach ($transactions as $main_transaction) {
                $main_amount = $main_transaction['amount'];
                $main_type = $main_transaction['type'];
                $main_currency = $main_transaction['currency'];
                $transaction_date = $main_transaction['created_at'];
                $transaction_id = $main_transaction['id'];
                
                // Update main account balance
                if ($main_type === 'credit') {
                    if ($main_currency === 'USD') {
                        $stmt_update_main = $conn->prepare("UPDATE main_account SET usd_balance = usd_balance - ? WHERE id = ? AND tenant_id = ?");
                    } else {
                        $stmt_update_main = $conn->prepare("UPDATE main_account SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ?");
                    }
                    
                    $update_subsequent_main = $conn->prepare("
                        UPDATE main_account_transactions 
                        SET balance = balance - ? 
                        WHERE main_account_id = ? AND created_at > ? 
                        AND currency = ?
                        AND tenant_id = ?
                        ORDER BY created_at ASC
                    ");
                } else {
                    if ($main_currency === 'USD') {
                        $stmt_update_main = $conn->prepare("UPDATE main_account SET usd_balance = usd_balance + ? WHERE id = ? AND tenant_id = ?");
                    } else {
                        $stmt_update_main = $conn->prepare("UPDATE main_account SET afs_balance = afs_balance + ? WHERE id = ? AND tenant_id = ?");
                    }
                    
                    $update_subsequent_main = $conn->prepare("
                        UPDATE main_account_transactions 
                        SET balance = balance + ? 
                        WHERE main_account_id = ? AND created_at > ? 
                        AND currency = ?
                        AND tenant_id = ?
                        ORDER BY created_at ASC
                    ");
                }
                
                $stmt_update_main->bind_param("di", $main_amount, $mainAccountId, $tenant_id);
                $stmt_update_main->execute();
                $stmt_update_main->close();
                
                $update_subsequent_main->bind_param("dissi", $main_amount, $mainAccountId, $transaction_date, $main_currency, $tenant_id);
                $update_subsequent_main->execute();
                $update_subsequent_main->close();
            }

            // Now delete all main account transactions
            $stmt_delete_main_transactions = $conn->prepare("
                DELETE mat FROM main_account_transactions mat
                JOIN umrah_transactions ut ON mat.reference_id = ut.id
                WHERE ut.umrah_booking_id = ? AND mat.transaction_of = 'umrah'
                AND mat.tenant_id = ?
            ");
            $stmt_delete_main_transactions->bind_param("ii", $booking_id, $tenant_id);
            $stmt_delete_main_transactions->execute();
            $stmt_delete_main_transactions->close();
        }

        // Delete all umrah transactions
        $stmt_delete_transactions = $conn->prepare("DELETE FROM umrah_transactions WHERE umrah_booking_id = ? AND tenant_id = ?");
        $stmt_delete_transactions->bind_param("ii", $booking_id, $tenant_id);
        $stmt_delete_transactions->execute();
        $stmt_delete_transactions->close();

        // Delete all notifications
        $stmt_delete_notifications = $conn->prepare("DELETE FROM notifications WHERE transaction_id IN (SELECT id FROM umrah_transactions WHERE umrah_booking_id = ?)");
        $stmt_delete_notifications->bind_param("ii", $booking_id, $tenant_id);
        $stmt_delete_notifications->execute();
        $stmt_delete_notifications->close();
    }
    $stmt_get_members->close();

    // Delete all family members (umrah bookings)
    $stmt_delete_members = $conn->prepare("DELETE FROM umrah_bookings WHERE family_id = ? AND tenant_id = ?");
    $stmt_delete_members->bind_param("ii", $family_id, $tenant_id);
    $stmt_delete_members->execute();
    $stmt_delete_members->close();

    // Delete the family
    $stmt_delete_family = $conn->prepare("DELETE FROM families WHERE family_id = ? AND tenant_id = ?");
    $stmt_delete_family->bind_param("ii", $family_id, $tenant_id);
    $stmt_delete_family->execute();
    $stmt_delete_family->close();

    // Commit the transaction
    $conn->commit();
    
    // Log the activity
    $old_values = json_encode([
        'family_id' => $family_id
    ]);
    $new_values = json_encode([]);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt_log = $conn->prepare("
        INSERT INTO activity_log 
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
        VALUES (?, 'delete', 'families', ?, ?, ?, ?, ?, NOW(), ?)
    ");
    $stmt_log->bind_param("iissssi", $user_id, $family_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
    $stmt_log->execute();
    $stmt_log->close();
    
    echo json_encode(["success" => true, "message" => "Family and all associated records deleted successfully"]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}

$conn->close();
