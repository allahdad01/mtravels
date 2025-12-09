<?php
// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

require_once '../../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
header('Content-Type: application/json');

// Get the JSON payload
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['family_id'])) {
    echo json_encode(["success" => false, "message" => "Family ID is required"]);
    exit();
}

$family_id = intval($data['family_id']);

$pdo->beginTransaction();

try {
    // Check if the family exists
    $stmt_check = $pdo->prepare("SELECT family_id FROM families WHERE family_id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt_check->bindParam(1, $family_id, PDO::PARAM_INT);
    $stmt_check->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_check->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $result = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        echo json_encode(["success" => false, "message" => "Family not found"]);
        exit();
    }

    // Get all family members (umrah bookings)
    $stmt_get_members = $pdo->prepare("
        SELECT ub.*, c.client_type
        FROM umrah_bookings ub
        JOIN clients c ON ub.sold_to = c.id
        WHERE ub.family_id = ? AND ub.tenant_id = ? AND ub.branch_id = ? AND c.tenant_id = ? AND c.branch_id = ?
    ");
    $stmt_get_members->bindParam(1, $family_id, PDO::PARAM_INT);
    $stmt_get_members->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_get_members->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt_get_members->bindParam(4, $tenant_id, PDO::PARAM_INT);
    $stmt_get_members->bindParam(5, $branch_id, PDO::PARAM_INT);
    $stmt_get_members->execute();
    $members_result = $stmt_get_members->fetchAll(PDO::FETCH_ASSOC);

    foreach ($members_result as $member) {
        $booking_id = $member['booking_id'];
        $client_id = $member['sold_to'];
        $supplier_id = $member['supplier'];
        $currency = $member['currency'];
        $client_type = $member['client_type'];
        $mainAccountId = $member['paid_to'];

        // Handle client transactions
        $clientTransactions = "SELECT id, amount, type, created_at FROM client_transactions
                               WHERE client_id = ? AND transaction_of = 'umrah'
                               AND reference_id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($clientTransactions);
        $stmt->bindParam(1, $client_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $booking_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $clientResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($clientResults as $row) {
            $amount = abs($row['amount']);
            $transaction_date = $row['created_at'];
            $transaction_id = $row['id'];
            $transaction_type = $row['type'];

            // Only adjust balance for regular clients
            if ($client_type === 'regular') {
                // Adjust Client Balance
                $clientBalanceField = ($currency == 'USD') ? 'usd_balance' : 'afs_balance';

                // Get current client balance before update
                $getCurrentBalanceQuery = "SELECT $clientBalanceField FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmtGetCurrentBalance = $pdo->prepare($getCurrentBalanceQuery);
                $stmtGetCurrentBalance->bindParam(1, $client_id, PDO::PARAM_INT);
                $stmtGetCurrentBalance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmtGetCurrentBalance->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmtGetCurrentBalance->execute();
                $currentBalanceResult = $stmtGetCurrentBalance->fetch(PDO::FETCH_ASSOC);
                $currentBalance = $currentBalanceResult[$clientBalanceField];

                if ($transaction_type == 'debit') {
                    $adjustClientBalance = "UPDATE clients
                                           SET $clientBalanceField = $clientBalanceField + ?
                                           WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                } else {
                    $adjustClientBalance = "UPDATE clients
                                           SET $clientBalanceField = $clientBalanceField - ?
                                           WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                }

                $stmt = $pdo->prepare($adjustClientBalance);
                $stmt->bindParam(1, $amount, PDO::PARAM_STR);
                $stmt->bindParam(2, $client_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                $stmt->execute();

                // Update subsequent transactions' running balances
                if ($transaction_type == 'debit') {
                    $updateSubsequentBalances = "UPDATE client_transactions
                                                SET balance = balance + ?
                                                WHERE client_id = ?
                                                AND id > ?
                                                AND currency = ?
                                                AND tenant_id = ? AND branch_id = ?";
                } else {
                    $updateSubsequentBalances = "UPDATE client_transactions
                                                SET balance = balance - ?
                                                WHERE client_id = ?
                                                AND id > ?
                                                AND currency = ?
                                                AND tenant_id = ? AND branch_id = ?";
                }

                $stmtUpdate = $pdo->prepare($updateSubsequentBalances);
                $stmtUpdate->bindParam(1, $amount, PDO::PARAM_STR);
                $stmtUpdate->bindParam(2, $client_id, PDO::PARAM_INT);
                $stmtUpdate->bindParam(3, $transaction_id, PDO::PARAM_INT);
                $stmtUpdate->bindParam(4, $currency, PDO::PARAM_STR);
                $stmtUpdate->bindParam(5, $tenant_id, PDO::PARAM_INT);
                $stmtUpdate->bindParam(6, $branch_id, PDO::PARAM_INT);
                $stmtUpdate->execute();
            }

            // Delete Client Transaction
            $deleteClientTransaction = "DELETE FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($deleteClientTransaction);
            $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        // Handle supplier transactions
        $supplierTypeQuery = "SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($supplierTypeQuery);
        $stmt->bindParam(1, $supplier_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $supplierTypeRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $supplier_type = $supplierTypeRow['supplier_type'];

        $supplierTransactions = "SELECT id, amount, transaction_type, transaction_date FROM supplier_transactions
                                 WHERE supplier_id = ? AND transaction_of = 'umrah'
                                 AND reference_id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($supplierTransactions);
        $stmt->bindParam(1, $supplier_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $booking_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $supplierResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($supplierResults as $row) {
            $amount = $row['amount'];
            $transaction_date = $row['transaction_date'];
            $transaction_id = $row['id'];

            // Only adjust balance for external suppliers
            if ($supplier_type === 'External') {
                // Adjust Supplier Balance
                $adjustSupplierBalance = "UPDATE suppliers
                                          SET balance = balance " . ($row['transaction_type'] == 'Credit' ? '-' : '+') . " ?
                                          WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $pdo->prepare($adjustSupplierBalance);
                $stmt->bindParam(1, $amount, PDO::PARAM_STR);
                $stmt->bindParam(2, $supplier_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                $stmt->execute();

                // Update subsequent transactions' running balances
                $updateSubsequentSupplierBalances = "UPDATE supplier_transactions
                                                    SET balance = balance " . ($row['transaction_type'] == 'Credit' ? '-' : '+') . " ?
                                                    WHERE supplier_id = ?
                                                    AND id > ?
                                                    AND tenant_id = ? AND branch_id = ?";
                $stmtUpdate = $pdo->prepare($updateSubsequentSupplierBalances);
                $stmtUpdate->bindParam(1, $amount, PDO::PARAM_STR);
                $stmtUpdate->bindParam(2, $supplier_id, PDO::PARAM_INT);
                $stmtUpdate->bindParam(3, $transaction_id, PDO::PARAM_INT);
                $stmtUpdate->bindParam(4, $tenant_id, PDO::PARAM_INT);
                $stmtUpdate->bindParam(5, $branch_id, PDO::PARAM_INT);
                $stmtUpdate->execute();
            }

            // Delete Supplier Transaction
            $deleteSupplierTransaction = "DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($deleteSupplierTransaction);
            $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        // Handle main account transactions
        if ($mainAccountId && $mainAccountId > 0) {
            // First fetch all transactions to calculate balances
            $stmt_fetch_main_transactions = $pdo->prepare("
                SELECT mat.id, mat.amount, mat.type, mat.currency, mat.created_at
                FROM main_account_transactions mat
                JOIN umrah_transactions ut ON mat.reference_id = ut.id
                WHERE ut.umrah_booking_id = ? AND mat.transaction_of = 'umrah'
                AND mat.tenant_id = ? AND mat.branch_id = ?
            ");
            $stmt_fetch_main_transactions->bindParam(1, $booking_id, PDO::PARAM_INT);
            $stmt_fetch_main_transactions->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt_fetch_main_transactions->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt_fetch_main_transactions->execute();
            $result_main_transactions = $stmt_fetch_main_transactions->fetchAll(PDO::FETCH_ASSOC);

            // Process each transaction for balance adjustments
            foreach ($result_main_transactions as $main_transaction) {
                $main_amount = $main_transaction['amount'];
                $main_type = $main_transaction['type'];
                $main_currency = $main_transaction['currency'];
                $transaction_date = $main_transaction['created_at'];
                $transaction_id = $main_transaction['id'];

                // Update main account balance
                if ($main_type === 'credit') {
                    if ($main_currency === 'USD') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET usd_balance = usd_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } elseif ($main_currency === 'AFS') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } elseif ($main_currency === 'EUR') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET euro_balance = euro_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } elseif ($main_currency === 'DARHAM') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET darham_balance = darham_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } else {
                        throw new PDOException("Unsupported currency type for main account balance update.");
                    }

                    $update_subsequent_main = $pdo->prepare("
                        UPDATE main_account_transactions
                        SET balance = balance - ?
                        WHERE main_account_id = ? AND created_at > ?
                        AND currency = ?
                        AND tenant_id = ? AND branch_id = ?
                        ORDER BY created_at ASC
                    ");
                } else {
                    if ($main_currency === 'USD') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET usd_balance = usd_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } elseif ($main_currency === 'AFS') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET afs_balance = afs_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } elseif ($main_currency === 'EUR') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET euro_balance = euro_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } elseif ($main_currency === 'DARHAM') {
                        $stmt_update_main = $pdo->prepare("UPDATE main_account SET darham_balance = darham_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } else {
                        throw new PDOException("Unsupported currency type for main account balance update.");
                    }

                    $update_subsequent_main = $pdo->prepare("
                        UPDATE main_account_transactions
                        SET balance = balance + ?
                        WHERE main_account_id = ?
                        AND id > ?
                        AND currency = ?
                        AND tenant_id = ? AND branch_id = ?
                    ");
                }

                $stmt_update_main->bindParam(1, $main_amount, PDO::PARAM_STR);
                $stmt_update_main->bindParam(2, $mainAccountId, PDO::PARAM_INT);
                $stmt_update_main->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_update_main->bindParam(4, $branch_id, PDO::PARAM_INT);
                $stmt_update_main->execute();

                $update_subsequent_main->bindParam(1, $main_amount, PDO::PARAM_STR);
                $update_subsequent_main->bindParam(2, $mainAccountId, PDO::PARAM_INT);
                $update_subsequent_main->bindParam(3, $transaction_id, PDO::PARAM_INT);
                $update_subsequent_main->bindParam(4, $main_currency, PDO::PARAM_STR);
                $update_subsequent_main->bindParam(5, $tenant_id, PDO::PARAM_INT);
                $update_subsequent_main->bindParam(6, $branch_id, PDO::PARAM_INT);
                $update_subsequent_main->execute();
            }

            // Now delete all main account transactions
            $stmt_delete_main_transactions = $pdo->prepare("
                DELETE mat FROM main_account_transactions mat
                JOIN umrah_transactions ut ON mat.reference_id = ut.id
                WHERE ut.umrah_booking_id = ? AND mat.transaction_of = 'umrah'
                AND mat.tenant_id = ? AND mat.branch_id = ?
            ");
            $stmt_delete_main_transactions->bindParam(1, $booking_id, PDO::PARAM_INT);
            $stmt_delete_main_transactions->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt_delete_main_transactions->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt_delete_main_transactions->execute();
        }

        // Delete all umrah transactions
        $stmt_delete_transactions = $pdo->prepare("DELETE FROM umrah_transactions WHERE umrah_booking_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_delete_transactions->bindParam(1, $booking_id, PDO::PARAM_INT);
        $stmt_delete_transactions->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_delete_transactions->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_delete_transactions->execute();

        // Delete all notifications
        $stmt_delete_notifications = $pdo->prepare("DELETE FROM notifications WHERE transaction_id IN (SELECT id FROM umrah_transactions WHERE umrah_booking_id = ?) AND tenant_id = ? AND branch_id = ?");
        $stmt_delete_notifications->bindParam(1, $booking_id, PDO::PARAM_INT);
        $stmt_delete_notifications->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_delete_notifications->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_delete_notifications->execute();
    }

    // Delete all family members (umrah bookings)
    $stmt_delete_members = $pdo->prepare("DELETE FROM umrah_bookings WHERE family_id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt_delete_members->bindParam(1, $family_id, PDO::PARAM_INT);
    $stmt_delete_members->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_delete_members->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt_delete_members->execute();

    // Delete the family
    $stmt_delete_family = $pdo->prepare("DELETE FROM families WHERE family_id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt_delete_family->bindParam(1, $family_id, PDO::PARAM_INT);
    $stmt_delete_family->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_delete_family->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt_delete_family->execute();

    // Commit the transaction
    $pdo->commit();

    // Log the activity
    $old_values = json_encode([
        'family_id' => $family_id
    ]);
    $new_values = json_encode([]);

    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmt_log = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, 'delete', 'families', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt_log->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt_log->bindParam(2, $family_id, PDO::PARAM_INT);
    $stmt_log->bindParam(3, $old_values, PDO::PARAM_STR);
    $stmt_log->bindParam(4, $new_values, PDO::PARAM_STR);
    $stmt_log->bindParam(5, $ip_address, PDO::PARAM_STR);
    $stmt_log->bindParam(6, $user_agent, PDO::PARAM_STR);
    $stmt_log->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $stmt_log->bindParam(8, $branch_id, PDO::PARAM_INT);
    $stmt_log->execute();

    echo json_encode(["success" => true, "message" => "Family and all associated records deleted successfully"]);
} catch (PDOException $e) {
    $pdo->rollback();
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
