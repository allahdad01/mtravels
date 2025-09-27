<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

require_once '../includes/conn.php';
$tenant_id = $_SESSION['tenant_id'];

$data = json_decode(file_get_contents("php://input"), true);
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($data['id'])) {
    $refundId = intval($data['id']);

    // Step 1: Fetch Date Change Transaction Details (Including Client Type)
    $query = "SELECT rf.*, c.client_type FROM refunded_tickets rf 
              JOIN clients c ON rf.sold_to = c.id WHERE rf.id = ? AND rf.tenant_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $refundId, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found.']);
        exit();
    }

    $transaction = $result->fetch_assoc();
    $clientId = $transaction['sold_to'];
    $supplierId = $transaction['supplier'];
    $pnr = $transaction['pnr'];
    $mainAccountId = $transaction['paid_to'];
    $currency = $transaction['currency'];
    $clientType = $transaction['client_type']; // Get client type

    // Start Transaction
    $conn->begin_transaction();

    try {
        // Step 2: Reverse Client Transactions (Only If Client is Regular)
        if ($clientType === 'regular') {
            $clientTransactions = "SELECT id, amount, type, created_at FROM client_transactions 
                                   WHERE client_id = ? AND transaction_of = 'ticket_refund' 
                                   AND reference_id = ? AND tenant_id = ?";
            $stmt = $conn->prepare($clientTransactions);
            $stmt->bind_param("iii", $clientId, $refundId, $tenant_id);
            $stmt->execute();
            $clientResults = $stmt->get_result();

            while ($row = $clientResults->fetch_assoc()) {
                $amount = $row['amount'];
                $transaction_date = $row['created_at'];
                $transaction_id = $row['id'];

                // Adjust Client Balance with Correct Reversal Logic
                $clientBalanceField = ($currency == 'USD') ? 'usd_balance' : 'afs_balance';
                
                // Reverse logic: If original was 'credit', subtract; if 'debit', add.
                $adjustClientBalance = "UPDATE clients 
                                        SET $clientBalanceField = $clientBalanceField " . ($row['type'] == 'credit' ? '-' : '+') . " ? 
                                        WHERE id = ?";
                $stmt = $conn->prepare($adjustClientBalance);
                $stmt->bind_param("di", $amount, $clientId);
                $stmt->execute();

                // Update all subsequent transactions' running balances
                // If the deleted transaction was a credit, we need to subtract that amount from all later transactions
                // If it was a debit, we need to add that amount to all later transactions
                $updateSubsequentBalances = "UPDATE client_transactions 
                                            SET balance = balance " . ($row['type'] == 'credit' ? '-' : '+') . " ? 
                                            WHERE client_id = ? AND created_at > ? 
                                            AND currency = ?
                                            AND tenant_id = ?
                                            ORDER BY created_at ASC";
                $stmtUpdate = $conn->prepare($updateSubsequentBalances);
                $stmtUpdate->bind_param("dissi", $amount, $clientId, $transaction_date, $currency, $tenant_id);
                $stmtUpdate->execute();

                // Delete Client Transaction
                $deleteClientTransaction = "DELETE FROM client_transactions WHERE id = ? AND tenant_id = ?";
                $stmt = $conn->prepare($deleteClientTransaction);
                $stmt->bind_param("ii", $transaction_id, $tenant_id);
                $stmt->execute();
            }
        }

        // Step 3: Reverse Supplier Transactions
        $supplierTransactions = "SELECT id, amount, transaction_type, transaction_date FROM supplier_transactions 
                                 WHERE supplier_id = ? AND transaction_of = 'ticket_refund' 
                                 AND reference_id = ? AND tenant_id = ?";
        $stmt = $conn->prepare($supplierTransactions);
        $stmt->bind_param("iii", $supplierId, $refundId, $tenant_id);
        $stmt->execute();
        $supplierResults = $stmt->get_result();

        while ($row = $supplierResults->fetch_assoc()) {
            $amount = $row['amount'];
            $transaction_date = $row['transaction_date'];
            $transaction_id = $row['id'];
            
            // Check Supplier Type
            $supplierTypeQuery = "SELECT supplier_type FROM suppliers WHERE id = ?";
            $stmt = $conn->prepare($supplierTypeQuery);
            $stmt->bind_param("i", $supplierId);
            $stmt->execute();
            $supplierTypeResult = $stmt->get_result();
            $supplierTypeRow = $supplierTypeResult->fetch_assoc();
            $supplierType = $supplierTypeRow['supplier_type'];

            if ($supplierType === 'External') {
                // Adjust Supplier Balance
                $adjustSupplierBalance = "UPDATE suppliers 
                                          SET balance = balance " . ($row['transaction_type'] == 'Credit' ? '-' : '+') . " ? 
                                          WHERE id = ?";
                $stmt = $conn->prepare($adjustSupplierBalance);
                $stmt->bind_param("di", $amount, $supplierId);
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
                $stmtUpdate->bind_param("disi", $amount, $supplierId, $transaction_date, $tenant_id);
                $stmtUpdate->execute();

                // Delete Supplier Transaction
                $deleteSupplierTransaction = "DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ?";
                $stmt = $conn->prepare($deleteSupplierTransaction);
                $stmt->bind_param("ii", $transaction_id, $tenant_id);
                $stmt->execute();
            }
            
            // No need to delete supplier transactions here as it's done outside the loop
        }

        // Handle main account transactions and balance updates
        if ($mainAccountId && $mainAccountId > 0) {
            // Fetch main account transactions for this ticket
            $stmt_fetch_main_transactions = $conn->prepare("
                SELECT id, amount, type, currency, created_at
                FROM main_account_transactions 
                WHERE reference_id = ? AND transaction_of = 'ticket_refund' AND tenant_id = ?
            ");
            $stmt_fetch_main_transactions->bind_param("ii", $refundId, $tenant_id);
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
                    // For a credit transaction being deleted, subtract the amount from all later transactions
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
                    // For a debit transaction being deleted, add the amount to all later transactions
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
                
                $stmt_update_main->bind_param("di", $main_amount, $mainAccountId, $tenant_id);
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

         // Delete main account transactions associated with this ticket
    $stmt_delete_main_transactions = $conn->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'ticket_refund' AND tenant_id = ?");
    $stmt_delete_main_transactions->bind_param("ii", $refundId, $tenant_id);
    if (!$stmt_delete_main_transactions->execute()) {
        throw new Exception("Failed to delete main account transactions associated with ticket ID $refundId.");
    }
    $stmt_delete_main_transactions->close();


        // Step 5: Delete the Refund Record
        $deleteTransaction = "DELETE FROM refunded_tickets WHERE id = ? AND tenant_id = ?";

        $stmt = $conn->prepare($deleteTransaction);
        $stmt->bind_param("ii", $refundId, $tenant_id);
        $stmt->execute();

       // Commit Transaction
       $conn->commit(); 
       
        // Log the activity
        $old_values = json_encode([
            'refund_id' => $refundId,
            'client_id' => $clientId,
            'supplier_id' => $supplierId,
            'main_account_id' => $mainAccountId,
            'currency' => $currency,
            'client_type' => $clientType,
            'pnr' => $pnr
        ]);
        $new_values = json_encode([]);
        
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt_log = $conn->prepare("
            INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
            VALUES (?, 'delete', 'refunded_tickets', ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt_log->bind_param("iissssi", $user_id, $refundId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
        $stmt_log->execute();
        $stmt_log->close();
        
echo json_encode(['success' => true, 'message' => 'Ticket deleted successfully!']);
exit();
} catch (Exception $e) {
    $conn->rollback(); // Roll back the transaction in case of errors
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit();
}

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}
?>
