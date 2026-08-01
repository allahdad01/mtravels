<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
require_once '../../includes/db.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $transactionId = $_POST['transaction_id'] ?? 0;
    $umrahId = $_POST['umrah_id'] ?? 0;
    $originalAmount = floatval($_POST['original_amount'] ?? 0);
    $newAmount = floatval($_POST['payment_amount'] ?? 0);
    $newDescription = $_POST['payment_description'] ?? '';

    // Validate required fields

// Validate payment_description
$payment_description = isset($_POST['payment_description']) ? DbSecurity::validateInput($_POST['payment_description'], 'string', ['maxlength' => 255]) : null;

// Validate receipt
$receipt = isset($_POST['receipt']) ? DbSecurity::validateInput($_POST['receipt'], 'string', ['maxlength' => 255]) : '';

// Validate payment_date
$payment_date = isset($_POST['payment_date']) ? DbSecurity::validateInput($_POST['payment_date'], 'date') : null;

// Validate payment_amount
$payment_amount = isset($_POST['payment_amount']) ? DbSecurity::validateInput($_POST['payment_amount'], 'float', ['min' => 0]) : null;

// Validate original_amount
$original_amount = isset($_POST['original_amount']) ? DbSecurity::validateInput($_POST['original_amount'], 'float', ['min' => 0]) : null;

// Validate umrah_id
$umrah_id = isset($_POST['umrah_id']) ? DbSecurity::validateInput($_POST['umrah_id'], 'int', ['min' => 0]) : null;

// Validate transaction_id
$transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;
$exchange_rate = isset($_POST['exchange_rate']) ? DbSecurity::validateInput($_POST['exchange_rate'], 'float', ['min' => 0]) : null;

    if (!$transactionId || !$umrahId) {
        echo json_encode(['success' => false, 'message' => 'Missing transaction or umrah ID']);
        exit;
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Get umrah transaction details before update
        $stmt = $pdo->prepare("SELECT payment_amount, payment_date, transaction_to, exchange_rate, receipt FROM umrah_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $transactionId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            throw new Exception("Transaction not found");
        }

        $transactionTo = $transaction['transaction_to'] ?? 'Internal Account';

        // Get umrah booking details
         $stmt = $pdo->prepare("SELECT currency as booking_currency, sold_price, sold_to FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
         $stmt->bindParam(1, $umrahId, PDO::PARAM_INT);
         $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
         $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
         $stmt->execute();
         $booking = $stmt->fetch(PDO::FETCH_ASSOC);

         if (!$booking) {
             throw new Exception("Umrah booking not found");
         }

         $booking_currency = $booking['booking_currency'];
         $sold_price = $booking['sold_price'];
         $client_id = $booking['sold_to'];

        // Store old values for activity log
        $oldValues = json_encode([
            'transaction_id' => $transactionId,
            'umrah_id' => $umrahId,
            'payment_amount' => $transaction['payment_amount'],
            'payment_date' => $transaction['payment_date'],
            'transaction_to' => $transaction['transaction_to'],
            'exchange_rate' => $transaction['exchange_rate'],
            'receipt' => $transaction['receipt']
        ]);

        // Update the umrah transaction
        $stmt = $pdo->prepare("UPDATE umrah_transactions SET payment_amount = ?, payment_description = ?, exchange_rate = ?, receipt = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $newAmount, PDO::PARAM_STR);
        $stmt->bindParam(2, $newDescription, PDO::PARAM_STR);
        $stmt->bindParam(3, $exchange_rate, PDO::PARAM_STR);
        $stmt->bindParam(4, $receipt, PDO::PARAM_STR);
        $stmt->bindParam(5, $transactionId, PDO::PARAM_INT);
        $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new PDOException("Failed to update transaction");
        }

        // Recalculate the total paid amount in the booking's base currency
        $stmt_get_transactions = $pdo->prepare("
            SELECT payment_amount, currency, exchange_rate
            FROM umrah_transactions
            WHERE umrah_booking_id = ? AND transaction_type = 'Credit' AND tenant_id = ? AND branch_id = ?
        ");
        $stmt_get_transactions->bindParam(1, $umrahId, PDO::PARAM_INT);
        $stmt_get_transactions->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt_get_transactions->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt_get_transactions->execute();
        $transactions_result = $stmt_get_transactions->fetchAll(PDO::FETCH_ASSOC);

        $total_paid_in_base_currency = 0;

        foreach ($transactions_result as $txn) {
            $txn_amount = floatval($txn['payment_amount']);
            $txn_currency = $txn['currency'];
            $txn_exchange_rate = floatval($txn['exchange_rate']) ?: 1;

            // Convert to booking's base currency
            if ($txn_currency === $booking_currency) {
                $total_paid_in_base_currency += $txn_amount;
            } elseif ($txn_currency === 'USD' && $booking_currency === 'AFS') {
                $total_paid_in_base_currency += ($txn_amount * $txn_exchange_rate);
            } elseif ($txn_currency === 'AFS' && $booking_currency === 'USD') {
                $total_paid_in_base_currency += ($txn_amount / $txn_exchange_rate);
            } elseif ($txn_currency === 'EUR' && $booking_currency === 'USD') {
                $total_paid_in_base_currency += ($txn_amount / $txn_exchange_rate);
            } elseif (($txn_currency === 'DARHAM' || $txn_currency === 'DAR') && $booking_currency === 'USD') {
                $total_paid_in_base_currency += ($txn_amount / $txn_exchange_rate);
            } elseif ($txn_currency === 'USD' && $booking_currency === 'EUR') {
                $total_paid_in_base_currency += ($txn_amount / $txn_exchange_rate);
            } elseif ($txn_currency === 'AFS' && $booking_currency === 'EUR') {
                $total_paid_in_base_currency += (($txn_amount / $txn_exchange_rate) / $txn_exchange_rate);
            } elseif (($txn_currency === 'DARHAM' || $txn_currency === 'DAR') && $booking_currency === 'AFS') {
                $total_paid_in_base_currency += ($txn_amount / $txn_exchange_rate * $txn_exchange_rate);
            } else {
                $total_paid_in_base_currency += $txn_amount;
            }
        }

        // Update paid amount in umrah_bookings with the converted total
        $due_amount = $sold_price - $total_paid_in_base_currency;
        $stmt_update_paid = $pdo->prepare("UPDATE umrah_bookings SET paid = ?, due = ? WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt_update_paid->bindParam(1, $total_paid_in_base_currency, PDO::PARAM_STR);
        $stmt_update_paid->bindParam(2, $due_amount, PDO::PARAM_STR);
        $stmt_update_paid->bindParam(3, $umrahId, PDO::PARAM_INT);
        $stmt_update_paid->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt_update_paid->bindParam(5, $branch_id, PDO::PARAM_INT);
        if (!$stmt_update_paid->execute()) {
            throw new PDOException('Failed to update paid amount in umrah_bookings');
        }

        // Update the booking's paid amount if needed (for balance adjustments)
        $amountDifference = $newAmount - $originalAmount;
        if ($amountDifference != 0) {

            // Check if transaction is to internal account or bank/supplier
            if (strtolower($transactionTo) === 'internal account' || empty($transactionTo)) {
                // Handle internal account transaction
                $mainTxStmt = $pdo->prepare("SELECT id, amount, type, currency, main_account_id, balance FROM main_account_transactions
                                             WHERE reference_id = ? AND transaction_of = 'umrah_transaction' AND tenant_id = ? AND branch_id = ?");
                $mainTxStmt->bindParam(1, $transactionId, PDO::PARAM_INT);
                $mainTxStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $mainTxStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                $mainTxStmt->execute();
                $mainTx = $mainTxStmt->fetch(PDO::FETCH_ASSOC);

                if ($mainTx) {
                    $mainTxId = $mainTx['id'];
                    $mainTxType = $mainTx['type'];
                    $currency = $mainTx['currency'];
                    $mainAccountId = $mainTx['main_account_id'];

                    // Map currency codes to the correct database field names
                    $currencyFieldMap = [
                        'USD' => 'usd_balance',
                        'AFS' => 'afs_balance',
                        'EUR' => 'euro_balance',
                        'DARHAM' => 'darham_balance'
                    ];

                    // Check if the currency is in our map
                    if (!isset($currencyFieldMap[$currency])) {
                        throw new Exception("Unknown currency: " . $currency);
                    }

                    // Get the correct field name
                    $balanceField = $currencyFieldMap[$currency];

                    // Calculate the adjustment for main account transaction
                    // For credit transactions, increase amount when payment increases
                    // For debit transactions, decrease amount when payment increases
                    $mainTxAdjustment = ($mainTxType == 'credit') ? $amountDifference : -$amountDifference;

                    // Update the main account transaction
                    $updateMainTxStmt = $pdo->prepare("UPDATE main_account_transactions
                                                      SET amount = amount + ?, created_at = NOW(), description = ?, exchange_rate = ?, receipt = ?
                                                      WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    $updateMainTxStmt->bindParam(1, $mainTxAdjustment, PDO::PARAM_STR);
                    $updateMainTxStmt->bindParam(2, $newDescription, PDO::PARAM_STR);
                    $updateMainTxStmt->bindParam(3, $exchange_rate, PDO::PARAM_STR);
                    $updateMainTxStmt->bindParam(4, $receipt, PDO::PARAM_STR);
                    $updateMainTxStmt->bindParam(5, $mainTxId, PDO::PARAM_INT);
                    $updateMainTxStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
                    $updateMainTxStmt->bindParam(7, $branch_id, PDO::PARAM_INT);

                    if (!$updateMainTxStmt->execute()) {
                        throw new PDOException("Failed to update main account transaction");
                    }

                    // Update subsequent transactions' balances
                    $updateSubsequentQuery = "UPDATE main_account_transactions
                                             SET balance = balance + ?
                                             WHERE main_account_id = ?
                                             AND currency = ?
                                             AND id > ?
                                             AND id != ? AND tenant_id = ? AND branch_id = ?";
                    $updateSubsequentStmt = $pdo->prepare($updateSubsequentQuery);
                    $updateSubsequentStmt->bindParam(1, $mainTxAdjustment, PDO::PARAM_STR);
                    $updateSubsequentStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
                    $updateSubsequentStmt->bindParam(3, $currency, PDO::PARAM_STR);
                    $updateSubsequentStmt->bindParam(4, $mainTxId, PDO::PARAM_INT);
                    $updateSubsequentStmt->bindParam(5, $mainTxId, PDO::PARAM_INT);
                    $updateSubsequentStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
                    $updateSubsequentStmt->bindParam(7, $branch_id, PDO::PARAM_INT);

                    if (!$updateSubsequentStmt->execute()) {
                        throw new PDOException("Failed to update subsequent transactions");
                    }

                    // Update the balance of the current transaction
                    $newBalance = $mainTx['balance'] + $mainTxAdjustment;
                    $updateCurrentBalanceQuery = "UPDATE main_account_transactions SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                    $updateCurrentBalanceStmt = $pdo->prepare($updateCurrentBalanceQuery);
                    $updateCurrentBalanceStmt->bindParam(1, $newBalance, PDO::PARAM_STR);
                    $updateCurrentBalanceStmt->bindParam(2, $mainTxId, PDO::PARAM_INT);
                    $updateCurrentBalanceStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $updateCurrentBalanceStmt->bindParam(4, $branch_id, PDO::PARAM_INT);

                    if (!$updateCurrentBalanceStmt->execute()) {
                        throw new PDOException("Failed to update current transaction balance");
                    }

                    // Update main account balance
                    $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    $stmt->bindParam(1, $mainTxAdjustment, PDO::PARAM_STR);
                    $stmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
                    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);

                    if (!$stmt->execute()) {
                        throw new PDOException("Failed to update main account balance");
                    }
                }
            } else {
                 // Handle bank/supplier transaction
                 // Get supplier ID from umrah_booking_services
                 $bookingStmt = $pdo->prepare("SELECT supplier_id FROM umrah_booking_services WHERE booking_id = ? AND (service_type = 'all' OR FIND_IN_SET('visa', REPLACE(service_type, '+', ',')) > 0) AND tenant_id = ? AND branch_id = ? LIMIT 1");
                 $bookingStmt->bindParam(1, $umrahId, PDO::PARAM_INT);
                 $bookingStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                 $bookingStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                 $bookingStmt->execute();
                 $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);

                 if ($booking) {
                     $supplierId = $booking['supplier_id'];

                    // Check for existing supplier transaction
                    $supplierTxStmt = $pdo->prepare("SELECT id, amount, balance FROM supplier_transactions
                                                    WHERE reference_id = ? AND transaction_of = 'umrah_transaction' AND tenant_id = ? AND branch_id = ?");
                    $supplierTxStmt->bindParam(1, $transactionId, PDO::PARAM_INT);
                    $supplierTxStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                    $supplierTxStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                    $supplierTxStmt->execute();
                    $supplierTx = $supplierTxStmt->fetch(PDO::FETCH_ASSOC);

                    if ($supplierTx) {
                        $supplierTxId = $supplierTx['id'];
                        $originalSupplierAmount = $supplierTx['amount'];
                        $currentBalance = $supplierTx['balance'];

                        // Calculate the adjustment
                        $supplierTxAdjustment = $newAmount - $originalSupplierAmount;

                        // Update the supplier transaction
                        $updateSupplierTxStmt = $pdo->prepare("UPDATE supplier_transactions
                                                              SET amount = ?, transaction_date = NOW(), receipt = ?
                                                              WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                        $updateSupplierTxStmt->bindParam(1, $newAmount, PDO::PARAM_STR);
                        $updateSupplierTxStmt->bindParam(2, $receipt, PDO::PARAM_STR);
                        $updateSupplierTxStmt->bindParam(3, $supplierTxId, PDO::PARAM_INT);
                        $updateSupplierTxStmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
                        $updateSupplierTxStmt->bindParam(5, $branch_id, PDO::PARAM_INT);

                        if (!$updateSupplierTxStmt->execute()) {
                            throw new PDOException("Failed to update supplier transaction");
                        }

                        // Get current bank_payment value
                        $getBankPaymentStmt = $pdo->prepare("SELECT received_bank_payment FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
                        $getBankPaymentStmt->bindParam(1, $umrahId, PDO::PARAM_INT);
                        $getBankPaymentStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                        $getBankPaymentStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                        $getBankPaymentStmt->execute();
                        $bankPaymentResult = $getBankPaymentStmt->fetch(PDO::FETCH_ASSOC);

                        if ($bankPaymentResult) {
                            $currentBankPayment = $bankPaymentResult['received_bank_payment'];
                            $newBankPayment = $currentBankPayment + $supplierTxAdjustment;

                            // Update the umrah booking's bank_payment field
                            $updateBookingBankPaymentStmt = $pdo->prepare("UPDATE umrah_bookings SET received_bank_payment = ? WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
                            $updateBookingBankPaymentStmt->bindParam(1, $newBankPayment, PDO::PARAM_STR);
                            $updateBookingBankPaymentStmt->bindParam(2, $umrahId, PDO::PARAM_INT);
                            $updateBookingBankPaymentStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                            $updateBookingBankPaymentStmt->bindParam(4, $branch_id, PDO::PARAM_INT);

                            if (!$updateBookingBankPaymentStmt->execute()) {
                                throw new PDOException("Failed to update booking bank payment");
                            }
                        }

                        // Update the balance of the current transaction
                        $newSupplierBalance = $currentBalance + $supplierTxAdjustment;
                        $updateSupplierBalanceQuery = "UPDATE supplier_transactions SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                        $updateSupplierBalanceStmt = $pdo->prepare($updateSupplierBalanceQuery);
                        $updateSupplierBalanceStmt->bindParam(1, $newSupplierBalance, PDO::PARAM_STR);
                        $updateSupplierBalanceStmt->bindParam(2, $supplierTxId, PDO::PARAM_INT);
                        $updateSupplierBalanceStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                        $updateSupplierBalanceStmt->bindParam(4, $branch_id, PDO::PARAM_INT);

                        if (!$updateSupplierBalanceStmt->execute()) {
                            throw new PDOException("Failed to update supplier transaction balance");
                        }

                        // Update subsequent supplier transactions' balances
                        $updateSubsequentSupplierQuery = "UPDATE supplier_transactions
                                                        SET balance = balance + ?
                                                        WHERE supplier_id = ?
                                                        AND id > ?
                                                        AND id != ? AND tenant_id = ? AND branch_id = ?";
                        $updateSubsequentSupplierStmt = $pdo->prepare($updateSubsequentSupplierQuery);
                        $updateSubsequentSupplierStmt->bindParam(1, $supplierTxAdjustment, PDO::PARAM_STR);
                        $updateSubsequentSupplierStmt->bindParam(2, $supplierId, PDO::PARAM_INT);
                        $updateSubsequentSupplierStmt->bindParam(3, $supplierTxId, PDO::PARAM_INT);
                        $updateSubsequentSupplierStmt->bindParam(4, $supplierTxId, PDO::PARAM_INT);
                        $updateSubsequentSupplierStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
                        $updateSubsequentSupplierStmt->bindParam(6, $branch_id, PDO::PARAM_INT);

                        if (!$updateSubsequentSupplierStmt->execute()) {
                            throw new PDOException("Failed to update subsequent supplier transactions");
                        }

                        // Update supplier balance
                         $stmt = $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                         $stmt->bindParam(1, $supplierTxAdjustment, PDO::PARAM_STR);
                         $stmt->bindParam(2, $supplierId, PDO::PARAM_INT);
                         $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                         $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);

                         if (!$stmt->execute()) {
                             throw new PDOException("Failed to update supplier balance");
                         }

                         // Update client balance if client type is regular
                         if ($client_id > 0) {
                             // Check client type
                             $stmt_check_client_type = $pdo->prepare("SELECT client_type FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                             $stmt_check_client_type->bindParam(1, $client_id, PDO::PARAM_INT);
                             $stmt_check_client_type->bindParam(2, $tenant_id, PDO::PARAM_INT);
                             $stmt_check_client_type->bindParam(3, $branch_id, PDO::PARAM_INT);
                             $stmt_check_client_type->execute();
                             $client_type_result = $stmt_check_client_type->fetch(PDO::FETCH_ASSOC);
                             
                             if ($client_type_result && $client_type_result['client_type'] === 'regular') {
                                 // Get client transaction details
                                 $stmt_get_client_tx = $pdo->prepare("SELECT id, amount, currency, balance FROM client_transactions WHERE reference_id = ? AND transaction_of = 'umrah_transaction' AND tenant_id = ? AND branch_id = ?");
                                 $stmt_get_client_tx->bindParam(1, $transactionId, PDO::PARAM_INT);
                                 $stmt_get_client_tx->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                 $stmt_get_client_tx->bindParam(3, $branch_id, PDO::PARAM_INT);
                                 $stmt_get_client_tx->execute();
                                 $client_tx = $stmt_get_client_tx->fetch(PDO::FETCH_ASSOC);
                                 
                                 if ($client_tx) {
                                     $originalClientAmount = $client_tx['amount'];
                                     $tx_currency = $client_tx['currency'] ?? 'USD';

                                     // Calculate client adjustment same way as supplier
                                     $clientAdjustment = $newAmount - $originalClientAmount;

                                 // Get balance field based on currency
                                 $balance_column = ($tx_currency === 'USD') ? 'usd_balance' : 'afs_balance';

                                 // Update client balance
                                 $update_query = ($tx_currency === 'USD')
                                     ? "UPDATE clients SET usd_balance = usd_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?"
                                     : "UPDATE clients SET afs_balance = afs_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                                 $stmt_update_client = $pdo->prepare($update_query);
                                 $stmt_update_client->bindParam(1, $clientAdjustment, PDO::PARAM_STR);
                                 $stmt_update_client->bindParam(2, $client_id, PDO::PARAM_INT);
                                 $stmt_update_client->bindParam(3, $tenant_id, PDO::PARAM_INT);
                                 $stmt_update_client->bindParam(4, $branch_id, PDO::PARAM_INT);
                                 if (!$stmt_update_client->execute()) {
                                     throw new PDOException('Failed to update client balance');
                                 }

                                 // Update client transaction
                                  $stmt_update_client_tx = $pdo->prepare("UPDATE client_transactions
                                      SET amount = ?, balance = balance + ?
                                      WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                                  $stmt_update_client_tx->bindParam(1, $newAmount, PDO::PARAM_STR);
                                  $stmt_update_client_tx->bindParam(2, $clientAdjustment, PDO::PARAM_STR);
                                  $stmt_update_client_tx->bindParam(3, $client_tx['id'], PDO::PARAM_INT);
                                  $stmt_update_client_tx->bindParam(4, $tenant_id, PDO::PARAM_INT);
                                  $stmt_update_client_tx->bindParam(5, $branch_id, PDO::PARAM_INT);
                                 if (!$stmt_update_client_tx->execute()) {
                                     throw new PDOException("Failed to update client transaction");
                                 }

                                 // Update subsequent client transactions' balances
                                 $stmt_update_subsequent_client = $pdo->prepare("
                                     UPDATE client_transactions
                                     SET balance = balance + ?
                                     WHERE client_id = ? AND currency = ?
                                     AND id > ?
                                     AND tenant_id = ? AND branch_id = ?
                                 ");
                                 $stmt_update_subsequent_client->bindParam(1, $clientAdjustment, PDO::PARAM_STR);
                                 $stmt_update_subsequent_client->bindParam(2, $client_id, PDO::PARAM_INT);
                                 $stmt_update_subsequent_client->bindParam(3, $tx_currency, PDO::PARAM_STR);
                                 $stmt_update_subsequent_client->bindParam(4, $client_tx['id'], PDO::PARAM_INT);
                                 $stmt_update_subsequent_client->bindParam(5, $tenant_id, PDO::PARAM_INT);
                                 $stmt_update_subsequent_client->bindParam(6, $branch_id, PDO::PARAM_INT);
                                 if (!$stmt_update_subsequent_client->execute()) {
                                     throw new PDOException("Failed to update subsequent client transaction balances");
                                 }
                                 }
                                 }
                                 }
                                 } else {
                         // If no supplier transaction exists, inform admin to approve notification
                         throw new Exception("No supplier transaction found. Please approve the related notification first.");
                        }
                        } else {
                        throw new Exception("Umrah booking not found");
                        }
                        }

            // Update family totals
            $updateFamilyQuery = "UPDATE families f
                                 SET f.total_paid = (
                                     SELECT SUM(paid) FROM umrah_bookings
                                     WHERE family_id = (
                                         SELECT family_id FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?
                                     )
                                 ),
                                 f.total_paid_to_bank = (
                                     SELECT SUM(received_bank_payment) FROM umrah_bookings
                                     WHERE family_id = (
                                         SELECT family_id FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?
                                     )
                                 ),
                                 f.total_due = (
                                     SELECT SUM(due) FROM umrah_bookings
                                     WHERE family_id = (
                                         SELECT family_id FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?
                                     )
                                 )
                                 WHERE f.family_id = (
                                     SELECT family_id FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?
                                 )";
            $updateFamilyStmt = $pdo->prepare($updateFamilyQuery);
            $updateFamilyStmt->bindParam(1, $umrahId,    PDO::PARAM_INT);
            $updateFamilyStmt->bindParam(2, $tenant_id,  PDO::PARAM_INT);
            $updateFamilyStmt->bindParam(3, $branch_id,  PDO::PARAM_INT);
            $updateFamilyStmt->bindParam(4, $umrahId,    PDO::PARAM_INT);
            $updateFamilyStmt->bindParam(5, $tenant_id,  PDO::PARAM_INT);
            $updateFamilyStmt->bindParam(6, $branch_id,  PDO::PARAM_INT);
            $updateFamilyStmt->bindParam(7, $umrahId,    PDO::PARAM_INT);
            $updateFamilyStmt->bindParam(8, $tenant_id,  PDO::PARAM_INT);
            $updateFamilyStmt->bindParam(9, $branch_id,  PDO::PARAM_INT);
            $updateFamilyStmt->bindParam(10, $umrahId,    PDO::PARAM_INT);
            $updateFamilyStmt->bindParam(11, $tenant_id,  PDO::PARAM_INT);
            $updateFamilyStmt->bindParam(12, $branch_id,  PDO::PARAM_INT);

            if (!$updateFamilyStmt->execute()) {
                throw new PDOException("Failed to update family totals");
            }
        }

        // Sync receipt to related tables
        if (strtolower($transactionTo) === 'internal account' || empty($transactionTo)) {
            $stmt = $pdo->prepare("UPDATE main_account_transactions SET receipt = ? WHERE reference_id = ? AND transaction_of = 'umrah_transaction' AND tenant_id = ? AND branch_id = ?");
            $stmt->bindParam(1, $receipt, PDO::PARAM_STR);
            $stmt->bindParam(2, $transactionId, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            if (!$stmt->execute()) {
                throw new PDOException("Failed to sync receipt to main account transaction");
            }
        } else {
            $stmt = $pdo->prepare("UPDATE supplier_transactions SET receipt = ? WHERE reference_id = ? AND transaction_of = 'umrah_transaction' AND tenant_id = ? AND branch_id = ?");
            $stmt->bindParam(1, $receipt, PDO::PARAM_STR);
            $stmt->bindParam(2, $transactionId, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            if (!$stmt->execute()) {
                throw new PDOException("Failed to sync receipt to supplier transaction");
            }
        }

        // Create new values for activity log
        $newValues = json_encode([
            'transaction_id' => $transactionId,
            'umrah_id' => $umrahId,
            'payment_amount' => $newAmount,
            'payment_date' => $transaction['payment_date'],
            'payment_description' => $newDescription,
            'transaction_to' => $transactionTo,
            'exchange_rate' => $exchange_rate,
            'receipt' => $receipt
        ]);

        // Get user information for activity log
        $userId = $_SESSION['user_id'];
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        // Insert activity log
        $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, ip_address, user_agent, action,table_name, record_id, old_values, new_values, created_at, tenant_id, branch_id)
                                  VALUES (?, ?, ?, 'update', 'umrah_transactions', ?, ?, ?, NOW(), ?, ?)");
        $logStmt->bindParam(1, $userId, PDO::PARAM_INT);
        $logStmt->bindParam(2, $ipAddress, PDO::PARAM_STR);
        $logStmt->bindParam(3, $userAgent, PDO::PARAM_STR);
        $logStmt->bindParam(4, $transactionId, PDO::PARAM_INT);
        $logStmt->bindParam(5, $oldValues, PDO::PARAM_STR);
        $logStmt->bindParam(6, $newValues, PDO::PARAM_STR);
        $logStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $logStmt->bindParam(8, $branch_id, PDO::PARAM_INT);

        if (!$logStmt->execute()) {
            // Just log the error, don't throw exception to allow transaction to complete
            error_log("Failed to insert activity log: " . $logStmt->error);
        }

        // Commit transaction
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Transaction updated successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>