<?php
// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once '../../includes/db.php';

$data = json_decode(file_get_contents("php://input"), true);
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($data['id'])) {
    $refundId = intval($data['id']);

    try {
        // Step 1: Fetch Visa Refund Details
        $query = "SELECT vr.*, v.sold_to, v.supplier, v.sold, v.base, c.client_type
                  FROM visa_refunds vr
                  JOIN visa_applications v ON vr.visa_id = v.id
                  LEFT JOIN clients c ON v.sold_to = c.id
                  WHERE vr.id = ? AND vr.tenant_id = ? AND vr.branch_id = ? AND v.tenant_id = ? AND v.branch_id = ? AND (c.tenant_id = ? AND c.branch_id = ? OR c.id IS NULL)";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(1, $refundId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $refund = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$refund) {
            echo json_encode(['success' => false, 'message' => 'Refund not found.']);
            exit();
        }

        $clientId = $refund['sold_to'];
        $supplierId = $refund['supplier'];
        $visaId = $refund['visa_id'];
        $currency = $refund['currency'];
        $clientType = $refund['client_type'];
        $sold = $refund['sold'];
        $base = $refund['base'];
        $profit = $sold - $base;

        // Start Transaction
        $pdo->beginTransaction();

        // Step 2: Reverse Client Transactions (Only If Client is Regular)
        if ($clientType === 'regular') {
            $clientTransactions = "SELECT id, amount, type, created_at FROM client_transactions
                                  WHERE client_id = ? AND transaction_of = 'visa_refund'
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

                // Update subsequent transactions' running balances
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
        } else {
            $clientTransactions = "SELECT id, amount, type, created_at FROM client_transactions
                                  WHERE client_id = ? AND transaction_of = 'visa_refund'
                                  AND reference_id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($clientTransactions);
            $stmt->bindParam(1, $clientId, PDO::PARAM_INT);
            $stmt->bindParam(2, $refundId, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $clientResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($clientResults as $row) {
                $transaction_id = $row['id'];

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
        if ($supplierId) {
            $supplierTransactions = "SELECT id, amount, transaction_type, transaction_date FROM supplier_transactions
                                   WHERE supplier_id = ? AND transaction_of = 'visa_refund'
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
                $supplierTypeResult = $stmt->fetch(PDO::FETCH_ASSOC);
                $supplierType = $supplierTypeResult['supplier_type'];

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

                    // Update subsequent transactions' running balances
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
                }

                // Delete Supplier Transaction (always)
                $deleteSupplierTransaction = "DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $pdo->prepare($deleteSupplierTransaction);
                $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
                $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmt->execute();
            }
        }

        // Step 4: Handle Main Account Transactions
        $mainTransactions = "SELECT id, amount, type, currency, created_at, main_account_id
                           FROM main_account_transactions
                           WHERE reference_id = ? AND transaction_of = 'visa_refund' AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($mainTransactions);
        $stmt->bindParam(1, $refundId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $mainResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($mainResults as $row) {
            $amount = $row['amount'];
            $type = $row['type'];
            $mainCurrency = $row['currency'];
            $transaction_date = $row['created_at'];
            $mainAccountId = $row['main_account_id'];
            $transaction_id = $row['id'];

            // Update main account balance
            switch ($mainCurrency) {
                case 'USD': $balanceField = 'usd_balance'; break;
                case 'AFS': $balanceField = 'afs_balance'; break;
                case 'EUR': $balanceField = 'euro_balance'; break;
                case 'DARHAM': $balanceField = 'darham_balance'; break;
                default: $balanceField = 'afs_balance'; break;
            }
            $adjustMainBalance = "UPDATE main_account
                                SET $balanceField = $balanceField " . ($type == 'credit' ? '-' : '+') . " ?
                                WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($adjustMainBalance);
            $stmt->bindParam(1, $amount, PDO::PARAM_STR);
            $stmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt->execute();

            // Update subsequent transactions' running balances
            $updateSubsequentMainBalances = "UPDATE main_account_transactions
                                           SET balance = balance " . ($type == 'credit' ? '-' : '+') . " ?
                                           WHERE main_account_id = ?
                                           AND id > ?
                                           AND currency = ?
                                           AND tenant_id = ? AND branch_id = ?";
            $stmtUpdate = $pdo->prepare($updateSubsequentMainBalances);
            $stmtUpdate->bindParam(1, $amount, PDO::PARAM_STR);
            $stmtUpdate->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $stmtUpdate->bindParam(3, $transaction_id, PDO::PARAM_INT);
            $stmtUpdate->bindParam(4, $mainCurrency, PDO::PARAM_STR);
            $stmtUpdate->bindParam(5, $tenant_id, PDO::PARAM_INT);
            $stmtUpdate->bindParam(6, $branch_id, PDO::PARAM_INT);
            $stmtUpdate->execute();

            // Delete Main Account Transaction
            $deleteMainTransaction = "DELETE FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($deleteMainTransaction);
            $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        // Step 5: Update Visa Application Profit
        if ($refund['refund_type'] === 'full') {
            // For full refund, restore the original profit
            $updateVisaQuery = "UPDATE visa_applications SET profit = ?, status = 'Approved' WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($updateVisaQuery);
            $stmt->bindParam(1, $profit, PDO::PARAM_STR);
            $stmt->bindParam(2, $visaId, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            // For partial refund, add back the refunded amount to profit
            $updateVisaQuery = "UPDATE visa_applications SET profit = ?, status = 'Approved' WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($updateVisaQuery);
            $stmt->bindParam(1, $profit, PDO::PARAM_STR);
            $stmt->bindParam(2, $visaId, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        // Step 6: Delete the Refund Record
        $deleteRefund = "DELETE FROM visa_refunds WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($deleteRefund);
        $stmt->bindParam(1, $refundId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        // Commit transaction
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Refund deleted successfully']);

    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error deleting refund: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>