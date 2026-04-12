<?php
// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

require_once '../../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$data = json_decode(file_get_contents("php://input"), true);
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($data['id'])) {
    $refundId = intval($data['id']);

    try {
        // Step 1: Fetch Refund Details
        $query = "SELECT umr.*, um.sold_to, um.booking_id, um.price, um.sold_price, c.client_type
                  FROM umrah_refunds umr
                  JOIN umrah_bookings um ON umr.booking_id = um.booking_id
                  LEFT JOIN clients c ON um.sold_to = c.id
                  WHERE umr.id = ? AND umr.tenant_id = ? AND umr.branch_id = ? AND um.tenant_id = ? AND um.branch_id = ? AND (c.tenant_id = ? AND c.branch_id = ? OR c.id IS NULL)";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(1, $refundId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Refund not found.']);
            exit();
        }

        $refund = $result;
        $clientId = $refund['sold_to'];
        $umrahId = $refund['booking_id'];
        $currency = $refund['currency'];
        $clientType = $refund['client_type'];
        $refundType = $refund['refund_type'];
        $refundAmount = $refund['refund_amount'];
        $profit = $refund['sold_price'] - $refund['price'];

        // Get all services for this booking (multi-supplier support)
        $servicesQuery = "SELECT supplier_id, base_price, sold_price, profit FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($servicesQuery);
        $stmt->bindParam(1, $umrahId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $servicesResult = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($servicesResult)) {
            // Fallback if no services found - use booking totals
            $services = array(array(
                'supplier_id' => null,
                'base_price' => floatval($refund['price'] ?? 0),
                'sold_price' => floatval($refund['sold_price'] ?? 0),
                'profit' => floatval($refund['sold_price'] ?? 0) - floatval($refund['price'] ?? 0)
            ));
        } else {
            $services = $servicesResult;
        }

        // Calculate totals from services
        $totalBasePrice = array_sum(array_column($services, 'base_price'));
        $totalSoldPrice = array_sum(array_column($services, 'sold_price'));
        $totalProfit = array_sum(array_column($services, 'profit'));

        // Start Transaction
        $pdo->beginTransaction();

        // Step 2: Reverse Client Transactions (Only If Client is Regular)
        if ($clientType === 'regular') {
            $clientTransactions = "SELECT id, amount, type, created_at FROM client_transactions
                                  WHERE client_id = ? AND transaction_of = 'umrah_refund'
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
                                  WHERE client_id = ? AND transaction_of = 'umrah_refund'
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

        // Step 3: Reverse Supplier Transactions for all suppliers involved in the refund
        $supplierTransactions = "SELECT st.id, st.amount, st.transaction_type, st.transaction_date, st.supplier_id
                                FROM supplier_transactions st
                                WHERE st.transaction_of = 'umrah_refund'
                                AND st.reference_id = ? AND st.tenant_id = ? AND st.branch_id = ?";
        $stmt = $pdo->prepare($supplierTransactions);
        $stmt->bindParam(1, $refundId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $supplierResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($supplierResults as $row) {
            $amount = $row['amount'];
            $transaction_date = $row['transaction_date'];
            $transaction_id = $row['id'];
            $supplierId = $row['supplier_id'];

            // Check Supplier Type
            $supplierTypeQuery = "SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmtType = $pdo->prepare($supplierTypeQuery);
            $stmtType->bindParam(1, $supplierId, PDO::PARAM_INT);
            $stmtType->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmtType->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmtType->execute();
            $supplierTypeResult = $stmtType->fetch(PDO::FETCH_ASSOC);
            $supplierType = $supplierTypeResult['supplier_type'];

            if ($supplierType === 'External') {
                // Adjust Supplier Balance
                $adjustSupplierBalance = "UPDATE suppliers
                                        SET balance = balance " . ($row['transaction_type'] == 'Credit' ? '-' : '+') . " ?
                                        WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmtBalance = $pdo->prepare($adjustSupplierBalance);
                $stmtBalance->bindParam(1, $amount, PDO::PARAM_STR);
                $stmtBalance->bindParam(2, $supplierId, PDO::PARAM_INT);
                $stmtBalance->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmtBalance->bindParam(4, $branch_id, PDO::PARAM_INT);
                $stmtBalance->execute();

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

            // Delete Supplier Transaction
            $deleteSupplierTransaction = "DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmtDelete = $pdo->prepare($deleteSupplierTransaction);
            $stmtDelete->bindParam(1, $transaction_id, PDO::PARAM_INT);
            $stmtDelete->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmtDelete->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmtDelete->execute();
        }

        // Step 4: Handle Main Account Transactions
        $mainTransactions = "SELECT id, amount, type, currency, created_at, main_account_id
                           FROM main_account_transactions
                           WHERE reference_id = ? AND transaction_of = 'umrah_refund' AND tenant_id = ? AND branch_id = ?";
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

        // Step 5: Restore Booking Profit
        if ($refundType === 'full') {
            $updateBookingQuery = "UPDATE umrah_bookings SET profit = ?, status = 'active' WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($updateBookingQuery);
            $stmt->bindParam(1, $profit, PDO::PARAM_STR);
            $stmt->bindParam(2, $umrahId, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
        } else {

            $updateBookingQuery = "UPDATE umrah_bookings SET profit = ? WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($updateBookingQuery);
            $stmt->bindParam(1, $profit, PDO::PARAM_STR);
            $stmt->bindParam(2, $umrahId, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        // Step 6: Delete the Refund Record
        $deleteRefund = "DELETE FROM umrah_refunds WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($deleteRefund);
        $stmt->bindParam(1, $refundId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        // Step 7: Update family totals after deleting refund (restoring booking)
        $bookingQuery = "SELECT family_id, status FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($bookingQuery);
        $stmt->bindParam(1, $umrahId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $bookingResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bookingResult && $bookingResult['status'] === 'confirmed') {
            // If booking is now confirmed/active, update family totals
            $familyId = $bookingResult['family_id'];
            $updateFamilyStmt = $pdo->prepare("
                UPDATE families f
                SET
                    f.total_members = (SELECT COUNT(*) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ?),
                    f.total_price = (SELECT SUM(COALESCE(sold_price, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ?),
                    f.total_paid = (SELECT SUM(COALESCE(paid, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ?),
                    f.total_paid_to_bank = (SELECT SUM(COALESCE(received_bank_payment, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ?),
                    f.total_due = (SELECT SUM(COALESCE(due, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ?)
                WHERE f.family_id = ? AND f.tenant_id = ? AND f.branch_id = ?
            ");
            $updateFamilyStmt->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $familyId, $tenant_id, $branch_id]);
        }

        // Commit transaction
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Refund deleted successfully']);

    } catch (PDOException $e) {
        // Rollback transaction on error (only if transaction is active)
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Error deleting refund: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>