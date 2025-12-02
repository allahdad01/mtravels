<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once '../includes/conn.php';

$data = json_decode(file_get_contents("php://input"), true);
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($data['id'])) {
    $refundId = intval($data['id']);

    // Step 1: Fetch Visa Refund Details
    $query = "SELECT vr.*, v.sold_to, v.supplier, v.sold, v.base, c.client_type
              FROM visa_refunds vr
              JOIN visa_applications v ON vr.visa_id = v.id
              LEFT JOIN clients c ON v.sold_to = c.id
              WHERE vr.id = ? AND vr.tenant_id = ? AND vr.branch_id = ? AND v.tenant_id = ? AND v.branch_id = ? AND (c.tenant_id = ? AND c.branch_id = ? OR c.id IS NULL)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiiiii", $refundId, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Refund not found.']);
        exit();
    }

    $refund = $result->fetch_assoc();
    $clientId = $refund['sold_to'];
    $supplierId = $refund['supplier'];
    $visaId = $refund['visa_id'];
    $currency = $refund['currency'];
    $clientType = $refund['client_type'];
    $sold = $refund['sold'];
    $base = $refund['base'];
    $profit = $sold - $base;

    // Start Transaction
    $conn->begin_transaction();

    try {
        // Step 2: Reverse Client Transactions (Only If Client is Regular)
        if ($clientType === 'regular') {
            $clientTransactions = "SELECT id, amount, type, created_at FROM client_transactions
                                 WHERE client_id = ? AND transaction_of = 'visa_refund'
                                 AND reference_id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $conn->prepare($clientTransactions);
            $stmt->bind_param("iiii", $clientId, $refundId, $tenant_id, $branch_id);
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
                                      WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $conn->prepare($adjustClientBalance);
                $stmt->bind_param("diii", $amount, $clientId, $tenant_id, $branch_id);
                $stmt->execute();

                // Update subsequent transactions' running balances
                $updateSubsequentBalances = "UPDATE client_transactions
                                           SET balance = balance " . ($row['type'] == 'credit' ? '-' : '+') . " ?
                                           WHERE client_id = ?
                                           AND id > ?
                                           AND currency = ?
                                           AND tenant_id = ? AND branch_id = ?";
                $stmtUpdate = $conn->prepare($updateSubsequentBalances);
                $stmtUpdate->bind_param("dissis", $amount, $clientId, $transaction_id, $currency, $tenant_id, $branch_id);
                $stmtUpdate->execute();

                // Delete Client Transaction
                $deleteClientTransaction = "DELETE FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $conn->prepare($deleteClientTransaction);
                $stmt->bind_param("iii", $transaction_id, $tenant_id, $branch_id);
                $stmt->execute();
            }
        } else {
            $clientTransactions = "SELECT id, amount, type, created_at FROM client_transactions
                                 WHERE client_id = ? AND transaction_of = 'visa_refund'
                                 AND reference_id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $conn->prepare($clientTransactions);
            $stmt->bind_param("iiii", $clientId, $refundId, $tenant_id, $branch_id);
            $stmt->execute();
            $clientResults = $stmt->get_result();

            while ($row = $clientResults->fetch_assoc()) {
                $transaction_id = $row['id'];

                // Delete Client Transaction
                $deleteClientTransaction = "DELETE FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $conn->prepare($deleteClientTransaction);
                $stmt->bind_param("iii", $transaction_id, $tenant_id, $branch_id);
                $stmt->execute();
            }
        }

        // Step 3: Reverse Supplier Transactions
        if ($supplierId) {
            $supplierTransactions = "SELECT id, amount, transaction_type, transaction_date FROM supplier_transactions
                                   WHERE supplier_id = ? AND transaction_of = 'visa_refund'
                                   AND reference_id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $conn->prepare($supplierTransactions);
            $stmt->bind_param("iiii", $supplierId, $refundId, $tenant_id, $branch_id);
            $stmt->execute();
            $supplierResults = $stmt->get_result();

            while ($row = $supplierResults->fetch_assoc()) {
                $amount = $row['amount'];
                $transaction_date = $row['transaction_date'];
                $transaction_id = $row['id'];
                
                // Check Supplier Type
                $supplierTypeQuery = "SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $conn->prepare($supplierTypeQuery);
                $stmt->bind_param("iii", $supplierId, $tenant_id, $branch_id);
                $stmt->execute();
                $supplierTypeResult = $stmt->get_result();
                $supplierTypeRow = $supplierTypeResult->fetch_assoc();
                $supplierType = $supplierTypeRow['supplier_type'];

                if ($supplierType === 'External') {
                    // Adjust Supplier Balance
                    $adjustSupplierBalance = "UPDATE suppliers
                                            SET balance = balance " . ($row['transaction_type'] == 'Credit' ? '-' : '+') . " ?
                                            WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                    $stmt = $conn->prepare($adjustSupplierBalance);
                    $stmt->bind_param("diii", $amount, $supplierId, $tenant_id, $branch_id);
                    $stmt->execute();

                    // Update subsequent transactions' running balances
                    $updateSubsequentSupplierBalances = "UPDATE supplier_transactions
                                                        SET balance = balance " . ($row['transaction_type'] == 'Credit' ? '-' : '+') . " ?
                                                        WHERE supplier_id = ?
                                                        AND id > ?
                                                        AND tenant_id = ? AND branch_id = ?";
                    $stmtUpdate = $conn->prepare($updateSubsequentSupplierBalances);
                    $stmtUpdate->bind_param("disis", $amount, $supplierId, $transaction_id, $tenant_id, $branch_id);
                    $stmtUpdate->execute();
                }

                // Delete Supplier Transaction (always)
                $deleteSupplierTransaction = "DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $conn->prepare($deleteSupplierTransaction);
                $stmt->bind_param("iii", $transaction_id, $tenant_id, $branch_id);
                $stmt->execute();
            }
        }

        // Step 4: Handle Main Account Transactions
        $mainTransactions = "SELECT id, amount, type, currency, created_at, main_account_id
                           FROM main_account_transactions
                           WHERE reference_id = ? AND transaction_of = 'visa_refund' AND tenant_id = ? AND branch_id = ?";
        $stmt = $conn->prepare($mainTransactions);
        $stmt->bind_param("iii", $refundId, $tenant_id, $branch_id);
        $stmt->execute();
        $mainResults = $stmt->get_result();

        while ($row = $mainResults->fetch_assoc()) {
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
            $stmt = $conn->prepare($adjustMainBalance);
            $stmt->bind_param("diii", $amount, $mainAccountId, $tenant_id, $branch_id);
            $stmt->execute();

            // Update subsequent transactions' running balances
            $updateSubsequentMainBalances = "UPDATE main_account_transactions
                                           SET balance = balance " . ($type == 'credit' ? '-' : '+') . " ?
                                           WHERE main_account_id = ?
                                           AND id > ?
                                           AND currency = ?
                                           AND tenant_id = ? AND branch_id = ?";
            $stmtUpdate = $conn->prepare($updateSubsequentMainBalances);
            $stmtUpdate->bind_param("dissis", $amount, $mainAccountId, $transaction_id, $mainCurrency, $tenant_id, $branch_id);
            $stmtUpdate->execute();

            // Delete Main Account Transaction
            $deleteMainTransaction = "DELETE FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $conn->prepare($deleteMainTransaction);
            $stmt->bind_param("iii", $transaction_id, $tenant_id, $branch_id);
            $stmt->execute();
        }

        // Step 5: Update Visa Application Profit
        if ($refund['refund_type'] === 'full') {
            // For full refund, restore the original profit
            $updateVisaQuery = "UPDATE visa_applications SET profit = ?, status = 'Pending' WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $conn->prepare($updateVisaQuery);
            $stmt->bind_param("diii", $profit, $visaId, $tenant_id, $branch_id);
            $stmt->execute();
        } else {
            // For partial refund, add back the refunded amount to profit
            $updateVisaQuery = "UPDATE visa_applications SET profit = ?, status = 'Pending' WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $conn->prepare($updateVisaQuery);
            $stmt->bind_param("diii", $profit, $visaId, $tenant_id, $branch_id);
            $stmt->execute();
        }

        // Step 6: Delete the Refund Record
        $deleteRefund = "DELETE FROM visa_refunds WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $conn->prepare($deleteRefund);
        $stmt->bind_param("iii", $refundId, $tenant_id, $branch_id);
        $stmt->execute();

        // Commit transaction
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Refund deleted successfully']);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error deleting visa refund: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error deleting refund: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
} 