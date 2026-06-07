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

    // Step 1: Fetch Hotel Refund Details
    $query = "SELECT hr.*, h.sold_to, h.supplier_id, h.sold_amount, h.base_amount, c.client_type
              FROM hotel_refunds hr
              JOIN hotel_bookings h ON hr.booking_id = h.id
              LEFT JOIN clients c ON h.sold_to = c.id
              WHERE hr.id = ? AND hr.tenant_id = ? AND hr.branch_id = ? AND h.tenant_id = ? AND h.branch_id = ? AND c.tenant_id = ? AND c.branch_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$refundId, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($result) === 0) {
        echo json_encode(['success' => false, 'message' => 'Refund not found.']);
        exit();
    }

    $refund = $result[0];
    $clientId = $refund['sold_to'];
    $supplierId = $refund['supplier_id'];
    $hotelId = $refund['booking_id'];
    $currency = $refund['currency'];
    $clientType = $refund['client_type'];
    $sold = $refund['sold_amount'];
    $base = $refund['base_amount'];
    $profit = $sold - $base;

    // Check if refund has any associated main account transactions
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'hotel_refund' AND tenant_id = ? AND branch_id = ?");
    $stmt_check->execute([$refundId, $tenant_id, $branch_id]);
    if ($stmt_check->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'This hotel refund has associated main account transactions. Please delete the transactions first before deleting the refund.']);
        exit();
    }

    // Start Transaction
    $pdo->beginTransaction();

    try {
        // Step 2: Reverse Client Transactions (Only If Client is Regular)
         if ($clientType === 'regular') {
             $clientTransactions = "SELECT id, amount, type, created_at FROM client_transactions
                                  WHERE client_id = ? AND transaction_of = 'hotel_refund'
                                  AND reference_id = ? AND tenant_id = ? AND branch_id = ?";
             $stmt = $pdo->prepare($clientTransactions);
             $stmt->execute([$clientId, $refundId, $tenant_id, $branch_id]);
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
                $stmt->execute([$amount, $clientId, $tenant_id, $branch_id]);

                // Update subsequent transactions' running balances
                $updateSubsequentBalances = "UPDATE client_transactions
                                           SET balance = balance " . ($row['type'] == 'credit' ? '-' : '+') . " ?
                                           WHERE client_id = ?
                                           AND id > ?
                                           AND currency = ?
                                           AND tenant_id = ? AND branch_id = ?
                                           ORDER BY created_at ASC";
                $stmtUpdate = $pdo->prepare($updateSubsequentBalances);
                $stmtUpdate->execute([$amount, $clientId, $transaction_id, $currency, $tenant_id, $branch_id]);

                // Delete Client Transaction
                $deleteClientTransaction = "DELETE FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $pdo->prepare($deleteClientTransaction);
                $stmt->execute([$transaction_id, $tenant_id, $branch_id]);
                }
        } else {
            $clientTransactions = "SELECT id, amount, type, created_at FROM client_transactions
                                 WHERE client_id = ? AND transaction_of = 'hotel_refund'
                                 AND reference_id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($clientTransactions);
            $stmt->execute([$clientId, $refundId, $tenant_id, $branch_id]);
            $clientResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($clientResults as $row) {
                $transaction_id = $row['id'];

                // Delete Client Transaction
                $deleteClientTransaction = "DELETE FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $pdo->prepare($deleteClientTransaction);
                $stmt->execute([$transaction_id, $tenant_id, $branch_id]);
            }
        }

        // Step 3: Reverse Supplier Transactions
         if ($supplierId) {
             $supplierTransactions = "SELECT id, amount, transaction_type, transaction_date FROM supplier_transactions
                                    WHERE supplier_id = ? AND transaction_of = 'hotel_refund'
                                    AND reference_id = ? AND tenant_id = ? AND branch_id = ?";
             $stmt = $pdo->prepare($supplierTransactions);
             $stmt->execute([$supplierId, $refundId, $tenant_id, $branch_id]);
             $supplierResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

             foreach ($supplierResults as $row) {
                $amount = $row['amount'];
                $transaction_date = $row['transaction_date'];
                $transaction_id = $row['id'];
                
                // Check Supplier Type
                $supplierTypeQuery = "SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $pdo->prepare($supplierTypeQuery);
                $stmt->execute([$supplierId, $tenant_id, $branch_id]);
                $supplierTypeRow = $stmt->fetch(PDO::FETCH_ASSOC);
                $supplierType = $supplierTypeRow['supplier_type'] ?? null;

                if ($supplierType === 'External') {
                    // Adjust Supplier Balance
                    $adjustSupplierBalance = "UPDATE suppliers
                                            SET balance = balance " . ($row['transaction_type'] == 'Credit' ? '-' : '+') . " ?
                                            WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                    $stmt = $pdo->prepare($adjustSupplierBalance);
                    $stmt->execute([$amount, $supplierId, $tenant_id, $branch_id]);
                    
                    // Update subsequent transactions' running balances
                    $updateSubsequentSupplierBalances = "UPDATE supplier_transactions
                                                       SET balance = balance " . ($row['transaction_type'] == 'Credit' ? '-' : '+') . " ?
                                                       WHERE supplier_id = ?
                                                       AND id > ?
                                                       AND tenant_id = ? AND branch_id = ?
                                                       ORDER BY transaction_date ASC";
                    $stmtUpdate = $pdo->prepare($updateSubsequentSupplierBalances);
                    $stmtUpdate->execute([$amount, $supplierId, $transaction_id, $tenant_id, $branch_id]);
                
                }

                // Delete Supplier Transaction
                $deleteSupplierTransaction = "DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmt = $pdo->prepare($deleteSupplierTransaction);
                $stmt->execute([$transaction_id, $tenant_id, $branch_id]);
                }
                }

        // Step 4: Handle Main Account Transactions
         $mainTransactions = "SELECT id, amount, type, currency, created_at, main_account_id
                            FROM main_account_transactions
                            WHERE reference_id = ? AND transaction_of = 'hotel_refund' AND tenant_id = ? AND branch_id = ?";
         $stmt = $pdo->prepare($mainTransactions);
         $stmt->execute([$refundId, $tenant_id, $branch_id]);
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
            $stmt->execute([$amount, $mainAccountId, $tenant_id, $branch_id]);

            // Update subsequent transactions' running balances
            $updateSubsequentMainBalances = "UPDATE main_account_transactions
                                           SET balance = balance " . ($type == 'credit' ? '-' : '+') . " ?
                                           WHERE main_account_id = ?
                                           AND id > ?
                                           AND currency = ?
                                           AND tenant_id = ? AND branch_id = ?";
            $stmtUpdate = $pdo->prepare($updateSubsequentMainBalances);
            $stmtUpdate->execute([$amount, $mainAccountId, $transaction_id, $mainCurrency, $tenant_id, $branch_id]);

            // Delete Main Account Transaction
            $deleteMainTransaction = "DELETE FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($deleteMainTransaction);
            $stmt->execute([$transaction_id, $tenant_id, $branch_id]);
            }

        // Step 5: Update Hotel Booking Profit
        if ($refund['refund_type'] === 'full') {
            // For full refund, restore the original profit
            $updateHotelQuery = "UPDATE hotel_bookings SET profit = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($updateHotelQuery);
            $stmt->execute([$profit, $hotelId, $tenant_id, $branch_id]);
        } else {
            // For partial refund, add back the refunded amount to profit
            $updateHotelQuery = "UPDATE hotel_bookings SET profit = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($updateHotelQuery);
            $stmt->execute([$profit, $hotelId, $tenant_id, $branch_id]);
        }

        // Step 6: Delete the Refund Record
        $deleteRefund = "DELETE FROM hotel_refunds WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($deleteRefund);
        $stmt->execute([$refundId, $tenant_id, $branch_id]);

        // Commit transaction
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Refund deleted successfully']);

        } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error deleting refund: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
} 