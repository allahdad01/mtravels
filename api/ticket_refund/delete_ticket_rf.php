<?php
// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// Database connection
require_once '../../includes/db.php';

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

$data = json_decode(file_get_contents("php://input"), true);
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($data['id'])) {
    $refundId = intval($data['id']);

    // Step 1: Fetch Date Change Transaction Details (Including Client Type)
    $query = "SELECT rf.*, c.client_type FROM refunded_tickets rf
              JOIN clients c ON rf.sold_to = c.id WHERE rf.id = ? AND rf.tenant_id = ? AND rf.branch_id = ? AND c.tenant_id = ? AND c.branch_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $refundId, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found.']);
        exit();
    }

    $clientId = $transaction['sold_to'];
    $supplierId = $transaction['supplier'];
    $pnr = $transaction['pnr'];
    $mainAccountId = $transaction['paid_to'];
    $currency = $transaction['currency'];
    $clientType = $transaction['client_type']; // Get client type

    // Start Transaction
    $pdo->beginTransaction();

    try {
        // Step 2: Reverse Client Transactions (Only If Client is Regular)
        if ($clientType === 'regular') {
            $clientTransactions = "SELECT id, amount, type, created_at FROM client_transactions
                                   WHERE client_id = ? AND transaction_of = 'ticket_refund'
                                   AND reference_id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($clientTransactions);
            $stmt->bindParam(1, $clientId, PDO::PARAM_INT);
            $stmt->bindParam(2, $refundId, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $clientResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($clientResults as $row) {
                $amount = $row['amount'];
                $transaction_date = $row['created_at'];
                $transaction_id = $row['id'];

                // Adjust Client Balance with Correct Reversal Logic
                $clientBalanceField = ($currency == 'USD') ? 'usd_balance' : 'afs_balance';

                // Reverse logic: If original was 'credit', subtract; if 'debit', add.
                $adjustClientBalance = "UPDATE clients
                                        SET $clientBalanceField = $clientBalanceField " . ($row['type'] == 'credit' ? '-' : '+') . " ?
                                        WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $pdo->prepare($adjustClientBalance);
                $stmt->bindParam(1, $amount, PDO::PARAM_STR);
                $stmt->bindParam(2, $clientId, PDO::PARAM_INT);
                $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                $stmt->execute();

                // Update all subsequent transactions' running balances
                // If the deleted transaction was a credit, we need to subtract that amount from all later transactions
                // If it was a debit, we need to add that amount to all later transactions
                $updateSubsequentBalances = "UPDATE client_transactions
                                            SET balance = balance " . ($row['type'] == 'credit' ? '-' : '+') . " ?
                                            WHERE client_id = ?
                                            AND id > ?
                                            AND currency = ?
                                            AND tenant_id = ? AND branch_id = ?";
                $stmtUpdate = $pdo->prepare($updateSubsequentBalances);
                $stmtUpdate->bindParam(1, $amount, PDO::PARAM_STR);
                $stmtUpdate->bindParam(2, $clientId, PDO::PARAM_INT);
                $stmtUpdate->bindParam(3, $transaction_id, PDO::PARAM_INT);
                $stmtUpdate->bindParam(4, $currency, PDO::PARAM_STR);
                $stmtUpdate->bindParam(5, $tenant_id, PDO::PARAM_INT);
                $stmtUpdate->bindParam(6, $branch_id, PDO::PARAM_INT);
                $stmtUpdate->execute();

                // Delete Client Transaction
                $deleteClientTransaction = "DELETE FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $pdo->prepare($deleteClientTransaction);
                $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
                $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmt->execute();
            }
        }

        // Step 3: Reverse Supplier Transactions
        $supplierTransactions = "SELECT id, amount, transaction_type, transaction_date FROM supplier_transactions
                                 WHERE supplier_id = ? AND transaction_of = 'ticket_refund'
                                 AND reference_id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($supplierTransactions);
        $stmt->bindParam(1, $supplierId, PDO::PARAM_INT);
        $stmt->bindParam(2, $refundId, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $supplierResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($supplierResults as $row) {
            $amount = $row['amount'];
            $transaction_date = $row['transaction_date'];
            $transaction_id = $row['id'];

            // Check Supplier Type
            $supplierTypeQuery = "SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($supplierTypeQuery);
            $stmt->bindParam(1, $supplierId, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $supplierTypeRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $supplierType = $supplierTypeRow['supplier_type'];

            if ($supplierType === 'External') {
                // Adjust Supplier Balance
                $adjustSupplierBalance = "UPDATE suppliers
                                          SET balance = balance " . ($row['transaction_type'] == 'Credit' ? '-' : '+') . " ?
                                          WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $pdo->prepare($adjustSupplierBalance);
                $stmt->bindParam(1, $amount, PDO::PARAM_STR);
                $stmt->bindParam(2, $supplierId, PDO::PARAM_INT);
                $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                $stmt->execute();

                // Update all subsequent transactions' running balances
                // If the deleted transaction was a Credit, we need to subtract that amount from all later transactions
                // If it was a Debit, we need to add that amount to all later transactions
                $updateSubsequentSupplierBalances = "UPDATE supplier_transactions
                                                    SET balance = balance " . ($row['transaction_type'] == 'Credit' ? '-' : '+') . " ?
                                                    WHERE supplier_id = ?
                                                    AND id > ?
                                                    AND tenant_id = ? AND branch_id = ?";
                $stmtUpdate = $pdo->prepare($updateSubsequentSupplierBalances);
                $stmtUpdate->bindParam(1, $amount, PDO::PARAM_STR);
                $stmtUpdate->bindParam(2, $supplierId, PDO::PARAM_INT);
                $stmtUpdate->bindParam(3, $transaction_id, PDO::PARAM_INT);
                $stmtUpdate->bindParam(4, $tenant_id, PDO::PARAM_INT);
                $stmtUpdate->bindParam(5, $branch_id, PDO::PARAM_INT);
                $stmtUpdate->execute();

                // Delete Supplier Transaction
                $deleteSupplierTransaction = "DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $pdo->prepare($deleteSupplierTransaction);
                $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
                $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmt->execute();
            }

            // No need to delete supplier transactions here as it's done outside the loop
        }

        // Handle main account transactions and balance updates
        if ($mainAccountId && $mainAccountId > 0) {
            // Fetch main account transactions for this ticket
            $stmt_fetch_main_transactions = $pdo->prepare("
                SELECT id, amount, type, currency, created_at
                FROM main_account_transactions
                WHERE reference_id = ? AND transaction_of = 'ticket_refund' AND tenant_id = ? AND branch_id = ?
            ");
            $stmt_fetch_main_transactions->bindParam(1, $refundId, PDO::PARAM_INT);
            $stmt_fetch_main_transactions->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt_fetch_main_transactions->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt_fetch_main_transactions->execute();
            $result_main_transactions = $stmt_fetch_main_transactions->fetchAll(PDO::FETCH_ASSOC);

            foreach ($result_main_transactions as $main_transaction) {
                $main_amount = $main_transaction['amount'];
                $main_type = $main_transaction['type'];
                $main_currency = $main_transaction['currency'];
                $transaction_date = $main_transaction['created_at'];
                $transaction_id = $main_transaction['id'];

                // Update main account balance based on transaction type
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

                    // Update running balances for all subsequent main account transactions
                    // For a credit transaction being deleted, subtract the amount from all later transactions
                    $update_subsequent_main = $pdo->prepare("
                        UPDATE main_account_transactions
                        SET balance = balance - ?
                        WHERE main_account_id = ?
                        AND id > ?
                        AND currency = ?
                        AND tenant_id = ? AND branch_id = ?
                    ");
                } elseif ($main_type === 'debit') {
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

                    // Update running balances for all subsequent main account transactions
                    // For a debit transaction being deleted, add the amount to all later transactions
                    $update_subsequent_main = $pdo->prepare("
                        UPDATE main_account_transactions
                        SET balance = balance + ?
                        WHERE main_account_id = ?
                        AND id > ?
                        AND currency = ?
                        AND tenant_id = ? AND branch_id = ?
                    ");
                } else {
                    throw new PDOException("Invalid transaction type for main account transaction.");
                }

                $stmt_update_main->bindParam(1, $main_amount, PDO::PARAM_STR);
                $stmt_update_main->bindParam(2, $mainAccountId, PDO::PARAM_INT);
                $stmt_update_main->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt_update_main->bindParam(4, $branch_id, PDO::PARAM_INT);
                $stmt_update_main->execute();

                // Execute the update for subsequent transactions
                $update_subsequent_main->bindParam(1, $main_amount, PDO::PARAM_STR);
                $update_subsequent_main->bindParam(2, $mainAccountId, PDO::PARAM_INT);
                $update_subsequent_main->bindParam(3, $transaction_id, PDO::PARAM_INT);
                $update_subsequent_main->bindParam(4, $main_currency, PDO::PARAM_STR);
                $update_subsequent_main->bindParam(5, $tenant_id, PDO::PARAM_INT);
                $update_subsequent_main->bindParam(6, $branch_id, PDO::PARAM_INT);
                $update_subsequent_main->execute();
            }
        }

        // Delete main account transactions associated with this ticket
        $stmt_delete_main_transactions = $pdo->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'ticket_refund' AND tenant_id = ? AND branch_id = ?");
        $stmt_delete_main_transactions->bindParam(1, $refundId, PDO::PARAM_INT);
        $stmt_delete_main_transactions->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_delete_main_transactions->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_delete_main_transactions->execute();

        // Step 5: Delete the Refund Record
        $deleteTransaction = "DELETE FROM refunded_tickets WHERE id = ? AND tenant_id = ? AND branch_id = ?";

        $stmt = $pdo->prepare($deleteTransaction);
        $stmt->bindParam(1, $refundId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        // Commit Transaction
        $pdo->commit();

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

        $stmt_log = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'delete', 'refunded_tickets', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $stmt_log->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt_log->bindParam(2, $refundId, PDO::PARAM_INT);
        $stmt_log->bindParam(3, $old_values, PDO::PARAM_STR);
        $stmt_log->bindParam(4, $new_values, PDO::PARAM_STR);
        $stmt_log->bindParam(5, $ip_address, PDO::PARAM_STR);
        $stmt_log->bindParam(6, $user_agent, PDO::PARAM_STR);
        $stmt_log->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $stmt_log->bindParam(8, $branch_id, PDO::PARAM_INT);
        $stmt_log->execute();

        echo json_encode(['success' => true, 'message' => 'Ticket deleted successfully!']);
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack(); // Roll back the transaction in case of errors
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit();
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}
?>
