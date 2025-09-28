<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

require_once('../includes/conn.php');
$tenant_id = $_SESSION['tenant_id'];
// Validate booking_id
$booking_id = isset($_POST['booking_id']) ? DbSecurity::validateInput($_POST['booking_id'], 'int', ['min' => 0]) : null;
// Accept both JSON and form data
$booking_id = null;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check for JSON data first
    $json_data = json_decode(file_get_contents("php://input"), true);
    if (isset($json_data['booking_id'])) {
        $booking_id = intval($json_data['booking_id']);
    }
    // If not found in JSON, check POST data
    else if (isset($_POST['booking_id'])) {
        $booking_id = intval($_POST['booking_id']);
    }
}

if ($booking_id !== null) {
    // Step 1: Fetch Booking Details (Including Client Type)
    $query = "SELECT ub.*, c.client_type FROM umrah_bookings ub 
              JOIN clients c ON ub.sold_to = c.id WHERE ub.booking_id = ? AND ub.tenant_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $booking_id, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Booking not found.']);
        exit();
    }

    $booking = $result->fetch_assoc();
    $client_id = $booking['sold_to'];
    $currency = $booking['currency'];
    $client_type = $booking['client_type'];
    $mainAccountId = $booking['paid_to'];

    // Get all services/suppliers for this booking
    $servicesQuery = "SELECT ubs.id as service_id, ubs.supplier_id, ubs.service_type, ubs.base_price, ubs.sold_price, ubs.profit, ubs.currency, s.supplier_type
                      FROM umrah_booking_services ubs
                      JOIN suppliers s ON ubs.supplier_id = s.id
                      WHERE ubs.booking_id = ? AND ubs.tenant_id = ? AND s.tenant_id = ?";
    $stmtServices = $conn->prepare($servicesQuery);
    $stmtServices->bind_param("iii", $booking_id, $tenant_id, $tenant_id);
    $stmtServices->execute();
    $servicesResult = $stmtServices->get_result();
    $services = [];
    while ($service = $servicesResult->fetch_assoc()) {
        $services[] = $service;
    }
    $stmtServices->close();

    // Start Transaction
    $conn->begin_transaction();

    try {
        // Step 2: Reverse Client Transactions (Only If Client is Regular)
        if ($client_type === 'regular') {
            $clientTransactions = "SELECT id, amount, type, created_at FROM client_transactions 
                                   WHERE client_id = ? AND transaction_of = 'umrah' 
                                   AND reference_id = ? AND tenant_id = ?";
            $stmt = $conn->prepare($clientTransactions);
            $stmt->bind_param("iii", $client_id, $booking_id, $tenant_id);
            $stmt->execute();
            $clientResults = $stmt->get_result();

            while ($row = $clientResults->fetch_assoc()) {
                $amount = abs($row['amount']); // Ensure positive value
                $transaction_date = $row['created_at'];
                $transaction_id = $row['id'];
                $transaction_type = $row['type'];

                // Adjust Client Balance with Correct Reversal Logic
                $clientBalanceField = ($currency == 'USD') ? 'usd_balance' : 'afs_balance';
                
                // Get current client balance before update
                $getCurrentBalanceQuery = "SELECT $clientBalanceField FROM clients WHERE id = ?";
                $stmtGetCurrentBalance = $conn->prepare($getCurrentBalanceQuery);
                $stmtGetCurrentBalance->bind_param('i', $client_id);
                $stmtGetCurrentBalance->execute();
                $stmtGetCurrentBalance->bind_result($currentBalance);
                $stmtGetCurrentBalance->fetch();
                $stmtGetCurrentBalance->close();
                
                // Using the same logic as update_visa.php:
                // If transaction was 'debit' (client owes more), we need to add that amount back (client owes less)
                // If transaction was 'credit' (client owes less), we need to subtract that amount (client owes more)
                if ($transaction_type == 'debit') {
                    // For debit transactions, add the amount back to client balance
                    $adjustClientBalance = "UPDATE clients 
                                           SET $clientBalanceField = $clientBalanceField + ? 
                                           WHERE id = ? AND tenant_id = ?";
                } else { // credit
                    // For credit transactions, subtract the amount from client balance
                    $adjustClientBalance = "UPDATE clients 
                                           SET $clientBalanceField = $clientBalanceField - ? 
                                           WHERE id = ? AND tenant_id = ?";
                }
                
                $stmt = $conn->prepare($adjustClientBalance);
                $stmt->bind_param("dii", $amount, $client_id, $tenant_id);
                $stmt->execute();

                // Update all subsequent transactions' running balances
                // If the deleted transaction was a debit, we need to add that amount to all later transactions
                // If it was a credit, we need to subtract that amount from all later transactions
                if ($transaction_type == 'debit') {
                    $updateSubsequentBalances = "UPDATE client_transactions 
                                                SET balance = balance + ? 
                                                WHERE client_id = ? AND created_at > ? 
                                                AND currency = ?
                                                AND tenant_id = ?
                                                ORDER BY created_at ASC";
                } else { // credit
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

                // Delete Client Transaction
                $deleteClientTransaction = "DELETE FROM client_transactions WHERE id = ? AND tenant_id = ?";
                $stmt = $conn->prepare($deleteClientTransaction);
                $stmt->bind_param("ii", $transaction_id, $tenant_id);
                $stmt->execute();
            }
        } else if ($client_type === 'agency') {
            // For agency clients, just delete the transactions without balance adjustments
            $deleteClientTransactions = "DELETE FROM client_transactions 
                                       WHERE client_id = ? AND transaction_of = 'umrah' 
                                       AND reference_id = ? AND tenant_id = ?";
            $stmt = $conn->prepare($deleteClientTransactions);
            $stmt->bind_param("iii", $client_id, $booking_id, $tenant_id);
            $stmt->execute();
        }

        // Step 3: Reverse Supplier Transactions for all services
        foreach ($services as $service) {
            $supplier_id = $service['supplier_id'];
            $supplier_type = $service['supplier_type'];

            if ($supplier_type === 'External') {
                $supplierTransactions = "SELECT id, amount, transaction_type, transaction_date FROM supplier_transactions
                                          WHERE supplier_id = ? AND transaction_of = 'umrah'
                                          AND reference_id = ? AND tenant_id = ?";
                $stmt = $conn->prepare($supplierTransactions);
                $stmt->bind_param("iii", $supplier_id, $booking_id, $tenant_id);
                $stmt->execute();
                $supplierResults = $stmt->get_result();

                while ($row = $supplierResults->fetch_assoc()) {
                    $amount = $row['amount'];
                    $transaction_date = $row['transaction_date'];
                    $transaction_id = $row['id'];

                    // Adjust Supplier Balance
                    $adjustSupplierBalance = "UPDATE suppliers
                                               SET balance = balance " . ($row['transaction_type'] == 'Credit' ? '-' : '+') . " ?
                                               WHERE id = ? AND tenant_id = ?";
                    $stmt = $conn->prepare($adjustSupplierBalance);
                    $stmt->bind_param("dii", $amount, $supplier_id, $tenant_id);
                    $stmt->execute();

                    // Update all subsequent transactions' running balances
                    // If the deleted transaction was a Credit, we need to subtract that amount from all later transactions
                    // If it was a Debit, we need to add that amount to all later transactions
                    $updateSubsequentSupplierBalances = "UPDATE supplier_transactions
                                                         SET balance = balance " . ($row['transaction_type'] == 'Credit' ? '-' : '+') . " ?
                                                         WHERE supplier_id = ? AND transaction_date > ?
                                                         AND tenant_id = ?
                                                         ORDER BY transaction_date ASC";
                    $stmtUpdate = $conn->prepare($updateSubsequentSupplierBalances);
                    $stmtUpdate->bind_param("disi", $amount, $supplier_id, $transaction_date, $tenant_id);
                    $stmtUpdate->execute();

                    // Delete Supplier Transaction
                    $deleteSupplierTransaction = "DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ?";
                    $stmt = $conn->prepare($deleteSupplierTransaction);
                    $stmt->bind_param("ii", $transaction_id, $tenant_id);
                    $stmt->execute();
                }
            } else if ($supplier_type === 'Internal') {
                // For internal suppliers, just delete the transactions without balance adjustments
                $deleteSupplierTransactions = "DELETE FROM supplier_transactions
                                              WHERE supplier_id = ? AND transaction_of = 'umrah'
                                              AND reference_id = ? AND tenant_id = ?";
                $stmt = $conn->prepare($deleteSupplierTransactions);
                $stmt->bind_param("iii", $supplier_id, $booking_id, $tenant_id);
                $stmt->execute();
            }
        }
        
         // Handle main account transactions and balance updates
         if ($mainAccountId && $mainAccountId > 0) {
            // Fetch main account transactions for this ticket
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
            
            while ($main_transaction = $result_main_transactions->fetch_assoc()) {
                $main_amount = $main_transaction['amount'];
                $main_type = $main_transaction['type'];
                $main_currency = $main_transaction['currency'];
                $transaction_date = $main_transaction['created_at'];
                $transaction_id = $main_transaction['id'];
                
                // Update main account balance based on transaction type
                if ($main_type === 'credit') {
                    if ($main_currency === 'USD') {
                        $stmt_update_main = $conn->prepare("UPDATE main_account SET usd_balance = usd_balance - ? WHERE id = ? AND tenant_id = ?");
                    } elseif ($main_currency === 'AFS') {
                        $stmt_update_main = $conn->prepare("UPDATE main_account SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ?");
                    } else {
                        throw new Exception("Unsupported currency type for main account balance update.");
                    }
                    
                    // Update running balances for all subsequent main account transactions
                    $update_subsequent_main = $conn->prepare("
                        UPDATE main_account_transactions 
                        SET balance = balance - ? 
                        WHERE main_account_id = ? AND created_at > ? 
                        AND currency = ?
                        AND tenant_id = ?
                        ORDER BY created_at ASC
                    ");
                } elseif ($main_type === 'debit') {
                    if ($main_currency === 'USD') {
                        $stmt_update_main = $conn->prepare("UPDATE main_account SET usd_balance = usd_balance + ? WHERE id = ? AND tenant_id = ?");
                    } elseif ($main_currency === 'AFS') {
                        $stmt_update_main = $conn->prepare("UPDATE main_account SET afs_balance = afs_balance + ? WHERE id = ? AND tenant_id = ?");
                    } else {
                        throw new Exception("Unsupported currency type for main account balance update.");
                    }
                    
                    // Update running balances for all subsequent main account transactions
                    $update_subsequent_main = $conn->prepare("
                        UPDATE main_account_transactions 
                        SET balance = balance + ? 
                        WHERE main_account_id = ? AND created_at > ? 
                        AND currency = ?
                        AND tenant_id = ?
                        ORDER BY created_at ASC
                    ");
                } else {
                    throw new Exception("Invalid transaction type for main account transaction.");
                }
                
                $stmt_update_main->bind_param("dii", $main_amount, $mainAccountId, $tenant_id);
                if (!$stmt_update_main->execute()) {
                    throw new Exception("Failed to update main account balance for transaction.");
                }
                $stmt_update_main->close();
                
                // Execute the update for subsequent transactions
                $update_subsequent_main->bind_param("dissi", $main_amount, $mainAccountId, $transaction_date, $main_currency, $tenant_id);
                if (!$update_subsequent_main->execute()) {
                    throw new Exception("Failed to update subsequent main account transaction balances.");
                }
                $update_subsequent_main->close();
            }
            $stmt_fetch_main_transactions->close();
        }

        // Delete main account transactions associated with this booking
        $stmt_delete_main_transactions = $conn->prepare("
            DELETE mat FROM main_account_transactions mat
            JOIN umrah_transactions ut ON mat.reference_id = ut.id
            WHERE ut.umrah_booking_id = ? AND mat.transaction_of = 'umrah'
            AND mat.tenant_id = ?
        ");
        $stmt_delete_main_transactions->bind_param("ii", $booking_id, $tenant_id);
        if (!$stmt_delete_main_transactions->execute()) {
            throw new Exception("Failed to delete main account transactions associated with booking ID $booking_id.");
        }
        $stmt_delete_main_transactions->close();

        // Delete Umrah transactions associated with this booking
        $stmt_delete_umrah_transactions = $conn->prepare("DELETE FROM umrah_transactions WHERE umrah_booking_id = ? AND tenant_id = ?");
        $stmt_delete_umrah_transactions->bind_param("ii", $booking_id, $tenant_id);
        if (!$stmt_delete_umrah_transactions->execute()) {
            throw new Exception("Failed to delete Umrah transactions associated with booking ID $booking_id.");
        }
        $stmt_delete_umrah_transactions->close();

        // Delete booking services associated with this booking
        $stmt_delete_services = $conn->prepare("DELETE FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ?");
        $stmt_delete_services->bind_param("ii", $booking_id, $tenant_id);
        if (!$stmt_delete_services->execute()) {
            throw new Exception("Failed to delete booking services associated with booking ID $booking_id.");
        }
        $stmt_delete_services->close();

        // Step 5: Delete the Booking Record
        $deleteBooking = "DELETE FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?";
        $stmt = $conn->prepare($deleteBooking);
        $stmt->bind_param("ii", $booking_id, $tenant_id);
        $stmt->execute();

        // Commit Transaction
        $conn->commit();
        
        // Log the activity
        $old_values = json_encode([
            'booking_id' => $booking_id,
            'client_id' => $client_id,
            'services' => $services,
            'paid_to' => $mainAccountId,
            'currency' => $currency,
            'client_type' => $client_type,
            'total_base_price' => $booking['price'],
            'total_sold_price' => $booking['sold_price'],
            'total_profit' => $booking['profit']
        ]);
        $new_values = json_encode([]);
        
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt_log = $conn->prepare("
            INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
            VALUES (?, 'delete', 'umrah_bookings', ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt_log->bind_param("iissssi", $user_id, $booking_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
        $stmt_log->execute();
        $stmt_log->close();
        
        echo json_encode(['success' => true, 'message' => 'Booking deleted successfully!']);
        exit();
    } catch (Exception $e) {
        $conn->rollback(); // Roll back the transaction in case of errors
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit();
    }

    $stmt->close();
    $conn->close();
} else {
    // Improved error handling with more specific messages
    if ($_SERVER["REQUEST_METHOD"] != "POST") {
        echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST request.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Missing booking_id parameter.']);
    }
    exit();
}
?>
