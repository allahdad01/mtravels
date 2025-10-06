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

    // Step 1: Fetch Refund Details
    $query = "SELECT umr.*, um.sold_to, um.booking_id, c.client_type
              FROM umrah_refunds umr
              JOIN umrah_bookings um ON umr.booking_id = um.booking_id
              LEFT JOIN clients c ON um.sold_to = c.id
              WHERE umr.id = ? AND umr.tenant_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $refundId, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Refund not found.']);
        exit();
    }

    $refund = $result->fetch_assoc();
    $clientId = $refund['sold_to'];
    $umrahId = $refund['booking_id'];
    $currency = $refund['currency'];
    $clientType = $refund['client_type'];
    $refundType = $refund['refund_type'];
    $refundAmount = $refund['refund_amount'];
    $profit = $refund['sold_price'] - $refund['price'];

    // Get all services for this booking (multi-supplier support)
    $servicesQuery = "SELECT * FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($servicesQuery);
    $stmt->bind_param('ii', $umrahId, $tenant_id);
    $stmt->execute();
    $servicesResult = $stmt->get_result();

    if ($servicesResult->num_rows === 0) {
        // Fallback to old single-supplier logic if no services found
        $services = array(array(
            'supplier_id' => $refund['supplier'] ?? null,
            'base_price' => floatval($refund['price'] ?? 0),
            'sold_price' => floatval($refund['sold_price'] ?? 0),
            'profit' => floatval($refund['sold_price'] ?? 0) - floatval($refund['price'] ?? 0)
        ));
    } else {
        $services = $servicesResult->fetch_all(MYSQLI_ASSOC);
    }

    // Calculate totals from services
    $totalBasePrice = array_sum(array_column($services, 'base_price'));
    $totalSoldPrice = array_sum(array_column($services, 'sold_price'));
    $totalProfit = array_sum(array_column($services, 'profit'));

    // Start Transaction
    $conn->begin_transaction();

    try {
        // Step 2: Reverse Client Transactions (Only If Client is Regular)
        if ($clientType === 'regular') {
            $clientTransactions = "SELECT id, amount, type, created_at FROM client_transactions 
                                 WHERE client_id = ? AND transaction_of = 'umrah_refund' 
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
                                      WHERE id = ? AND tenant_id = ?";
                $stmt = $conn->prepare($adjustClientBalance);
                $stmt->bind_param("dii", $amount, $clientId, $tenant_id);
                $stmt->execute();

                // Update subsequent transactions' running balances
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

        // Step 3: Reverse Supplier Transactions for all suppliers involved in the refund
        $supplierTransactions = "SELECT st.id, st.amount, st.transaction_type, st.transaction_date, st.supplier_id
                                FROM supplier_transactions st
                                WHERE st.transaction_of = 'umrah_refund'
                                AND st.reference_id = ? AND st.tenant_id = ?";
        $stmt = $conn->prepare($supplierTransactions);
        $stmt->bind_param("ii", $refundId, $tenant_id);
        $stmt->execute();
        $supplierResults = $stmt->get_result();

        while ($row = $supplierResults->fetch_assoc()) {
            $amount = $row['amount'];
            $transaction_date = $row['transaction_date'];
            $transaction_id = $row['id'];
            $supplierId = $row['supplier_id'];

            // Check Supplier Type
            $supplierTypeQuery = "SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?";
            $stmtType = $conn->prepare($supplierTypeQuery);
            $stmtType->bind_param("ii", $supplierId, $tenant_id);
            $stmtType->execute();
            $supplierTypeResult = $stmtType->get_result();
            $supplierTypeRow = $supplierTypeResult->fetch_assoc();
            $supplierType = $supplierTypeRow['supplier_type'];

            if ($supplierType === 'External') {
                // Adjust Supplier Balance
                $adjustSupplierBalance = "UPDATE suppliers
                                        SET balance = balance " . ($row['transaction_type'] == 'credit' ? '-' : '+') . " ?
                                        WHERE id = ? AND tenant_id = ?";
                $stmtBalance = $conn->prepare($adjustSupplierBalance);
                $stmtBalance->bind_param("dii", $amount, $supplierId, $tenant_id);
                $stmtBalance->execute();

                // Update subsequent transactions' running balances
                $updateSubsequentSupplierBalances = "UPDATE supplier_transactions
                                                   SET balance = balance " . ($row['transaction_type'] == 'credit' ? '-' : '+') . " ?
                                                   WHERE supplier_id = ? AND transaction_date > ?
                                                   AND tenant_id = ?
                                                   ORDER BY transaction_date ASC";
                $stmtUpdate = $conn->prepare($updateSubsequentSupplierBalances);
                $stmtUpdate->bind_param("disi", $amount, $supplierId, $transaction_date, $tenant_id);
                $stmtUpdate->execute();
            }

            // Delete Supplier Transaction
            $deleteSupplierTransaction = "DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ?";
            $stmtDelete = $conn->prepare($deleteSupplierTransaction);
            $stmtDelete->bind_param("ii", $transaction_id, $tenant_id);
            $stmtDelete->execute();
        }

        // Step 4: Handle Main Account Transactions
        $mainTransactions = "SELECT id, amount, type, currency, created_at, main_account_id 
                           FROM main_account_transactions 
                           WHERE reference_id = ? AND transaction_of = 'umrah_refund' AND tenant_id = ?";
        $stmt = $conn->prepare($mainTransactions);
        $stmt->bind_param("ii", $refundId, $tenant_id);
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
            $balanceField = ($mainCurrency == 'USD') ? 'usd_balance' : 'afs_balance';
            $adjustMainBalance = "UPDATE main_account 
                                SET $balanceField = $balanceField " . ($type == 'credit' ? '-' : '+') . " ? 
                                WHERE id = ? AND tenant_id = ?";
            $stmt = $conn->prepare($adjustMainBalance);
            $stmt->bind_param("dii", $amount, $mainAccountId, $tenant_id);
            $stmt->execute();

            // Update subsequent transactions' running balances
            $updateSubsequentMainBalances = "UPDATE main_account_transactions 
                                           SET balance = balance " . ($type == 'credit' ? '-' : '+') . " ? 
                                           WHERE main_account_id = ? AND created_at > ? 
                                           AND currency = ?
                                           AND tenant_id = ?
                                           ORDER BY created_at ASC";
            $stmtUpdate = $conn->prepare($updateSubsequentMainBalances);
            $stmtUpdate->bind_param("dissi", $amount, $mainAccountId, $transaction_date, $mainCurrency, $tenant_id);
            $stmtUpdate->execute();

            // Delete Main Account Transaction
            $deleteMainTransaction = "DELETE FROM main_account_transactions WHERE id = ? AND tenant_id = ?";
            $stmt = $conn->prepare($deleteMainTransaction);
            $stmt->bind_param("ii", $transaction_id, $tenant_id);
            $stmt->execute();
        }

        // Step 5: Restore Booking Profit
        if ($refundType === 'full') {
            $updateBookingQuery = "UPDATE umrah_bookings SET profit = ?, status = 'confirmed' WHERE booking_id = ? AND tenant_id = ?";
            $stmt = $conn->prepare($updateBookingQuery);
            $stmt->bind_param("dii", $profit, $umrahId, $tenant_id);
            $stmt->execute();
        } else {
           
            $updateBookingQuery = "UPDATE umrah_bookings SET profit = ? WHERE booking_id = ? AND tenant_id = ?";
            $stmt = $conn->prepare($updateBookingQuery);
            $stmt->bind_param("dii", $profit, $umrahId, $tenant_id);
            $stmt->execute();
        }

        // Step 6: Delete the Refund Record
        $deleteRefund = "DELETE FROM umrah_refunds WHERE id = ? AND tenant_id = ?";
        $stmt = $conn->prepare($deleteRefund);
        $stmt->bind_param("ii", $refundId, $tenant_id);
        $stmt->execute();

        // Commit transaction
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Refund deleted successfully']);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error deleting umrah refund: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error deleting refund: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
} 