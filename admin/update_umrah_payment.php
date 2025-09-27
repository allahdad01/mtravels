<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
include '../includes/conn.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $transactionId = $_POST['transaction_id'] ?? 0;
    $umrahId = $_POST['umrah_id'] ?? 0;
    $originalAmount = floatval($_POST['original_amount'] ?? 0);
    $newAmount = floatval($_POST['payment_amount'] ?? 0);
    $newDate = $_POST['payment_date'] ?? '';
    $newTime = $_POST['payment_time'] ?? '00:00:00';
    $newDescription = $_POST['payment_description'] ?? '';
    
    // Combine date and time
    $newDateTime = $newDate . ' ' . $newTime;
    
    // Validate required fields

// Validate payment_description
$payment_description = isset($_POST['payment_description']) ? DbSecurity::validateInput($_POST['payment_description'], 'string', ['maxlength' => 255]) : null;

// Validate payment_time
$payment_time = isset($_POST['payment_time']) ? DbSecurity::validateInput($_POST['payment_time'], 'string', ['maxlength' => 255]) : null;

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

// Validate exchange_rate
$exchange_rate = isset($_POST['exchange_rate']) ? DbSecurity::validateInput($_POST['exchange_rate'], 'float', ['min' => 0]) : null;

    if (!$transactionId || !$umrahId) {
        echo json_encode(['success' => false, 'message' => 'Missing transaction or umrah ID']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get umrah transaction details before update
        $stmt = $conn->prepare("SELECT payment_amount, payment_date, transaction_to, exchange_rate FROM umrah_transactions WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $transactionId, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Transaction not found");
        }
        
        $transaction = $result->fetch_assoc();
        $originalDate = $transaction['payment_date'];
        $transactionTo = $transaction['transaction_to'] ?? 'Internal Account';

        // Get umrah booking details
        $stmt = $conn->prepare("SELECT currency as booking_currency, sold_price FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $umrahId, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Umrah booking not found");
        }

        $booking = $result->fetch_assoc();
        $booking_currency = $booking['booking_currency'];
        $sold_price = $booking['sold_price'];

        // Store old values for activity log
        $oldValues = json_encode([
            'transaction_id' => $transactionId,
            'umrah_id' => $umrahId,
            'payment_amount' => $transaction['payment_amount'],
            'payment_date' => $transaction['payment_date'],
            'transaction_to' => $transaction['transaction_to'],
            'exchange_rate' => $transaction['exchange_rate']
        ]);
        
        // Update the umrah transaction
        $stmt = $conn->prepare("UPDATE umrah_transactions SET payment_amount = ?, payment_description = ?, payment_date = ?, exchange_rate = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("dssdii", $newAmount, $newDescription, $newDateTime, $exchange_rate, $transactionId, $tenant_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update transaction: " . $stmt->error);
        }

        // Recalculate the total paid amount in the booking's base currency
        $stmt_get_transactions = $conn->prepare("
            SELECT payment_amount, currency, exchange_rate
            FROM umrah_transactions
            WHERE umrah_booking_id = ? AND transaction_type = 'Credit' AND tenant_id = ?
        ");
        $stmt_get_transactions->bind_param("ii", $umrahId, $tenant_id);
        $stmt_get_transactions->execute();
        $transactions_result = $stmt_get_transactions->get_result();

        $total_paid_in_base_currency = 0;

        while ($txn = $transactions_result->fetch_assoc()) {
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
            } elseif ($txn_currency === 'EUR' && $booking_currency === 'AFS') {
                $total_paid_in_base_currency += ($txn_amount / $txn_exchange_rate * $txn_exchange_rate);
            } elseif (($txn_currency === 'DARHAM' || $txn_currency === 'DAR') && $booking_currency === 'AFS') {
                $total_paid_in_base_currency += ($txn_amount / $txn_exchange_rate * $txn_exchange_rate);
            } else {
                $total_paid_in_base_currency += $txn_amount;
            }
        }
        $stmt_get_transactions->close();

        // Update paid amount in umrah_bookings with the converted total
        $due_amount = $sold_price - $total_paid_in_base_currency;
        $stmt_update_paid = $conn->prepare("UPDATE umrah_bookings SET paid = ?, due = ? WHERE booking_id = ? AND tenant_id = ?");
        $stmt_update_paid->bind_param("ddii", $total_paid_in_base_currency, $due_amount, $umrahId, $tenant_id);
        if (!$stmt_update_paid->execute()) {
            throw new Exception('Failed to update paid amount in umrah_bookings: ' . $stmt_update_paid->error);
        }
        $stmt_update_paid->close();

        // Update the booking's paid amount if needed (for balance adjustments)
        $amountDifference = $newAmount - $originalAmount;
        if ($amountDifference != 0) {

            // Check if transaction is to internal account or bank/supplier
            if (strtolower($transactionTo) === 'internal account' || empty($transactionTo)) {
                // Handle internal account transaction
                $mainTxStmt = $conn->prepare("SELECT id, amount, type, currency, main_account_id, balance FROM main_account_transactions 
                                             WHERE reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ?");
                $mainTxStmt->bind_param("ii", $transactionId, $tenant_id);
                $mainTxStmt->execute();
                $mainTxResult = $mainTxStmt->get_result();
                
                if ($mainTxResult->num_rows > 0) {
                    $mainTx = $mainTxResult->fetch_assoc();
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
                    $updateMainTxStmt = $conn->prepare("UPDATE main_account_transactions
                                                      SET amount = amount + ?, created_at = ?, description = ?, exchange_rate = ?
                                                      WHERE id = ? AND tenant_id = ?");
                    $updateMainTxStmt->bind_param("dssdii", $mainTxAdjustment, $newDateTime, $newDescription, $exchange_rate, $mainTxId, $tenant_id);
                    
                    if (!$updateMainTxStmt->execute()) {
                        throw new Exception("Failed to update main account transaction: " . $updateMainTxStmt->error);
                    }
                    
                    // Update subsequent transactions' balances
                    $updateSubsequentQuery = "UPDATE main_account_transactions 
                                             SET balance = balance + ? 
                                             WHERE main_account_id = ? 
                                             AND currency = ? 
                                             AND id > ? 
                                             AND id != ? AND tenant_id = ?";
                    $updateSubsequentStmt = $conn->prepare($updateSubsequentQuery);
                    $updateSubsequentStmt->bind_param("dissii", $mainTxAdjustment, $mainAccountId, $currency, $mainTxId, $mainTxId, $tenant_id);
                    
                    if (!$updateSubsequentStmt->execute()) {
                        throw new Exception("Failed to update subsequent transactions: " . $updateSubsequentStmt->error);
                    }
                    
                    // Update the balance of the current transaction
                    $newBalance = $mainTx['balance'] + $mainTxAdjustment;
                    $updateCurrentBalanceQuery = "UPDATE main_account_transactions SET balance = ? WHERE id = ? AND tenant_id = ?";
                    $updateCurrentBalanceStmt = $conn->prepare($updateCurrentBalanceQuery);
                    $updateCurrentBalanceStmt->bind_param("dii", $newBalance, $mainTxId, $tenant_id);
                    
                    if (!$updateCurrentBalanceStmt->execute()) {
                        throw new Exception("Failed to update current transaction balance: " . $updateCurrentBalanceStmt->error);
                    }
                    
                    // Update main account balance
                    $stmt = $conn->prepare("UPDATE main_account SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ?");
                    $stmt->bind_param("dii", $mainTxAdjustment, $mainAccountId, $tenant_id);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update main account balance: " . $stmt->error);
                    }
                }
            } else {
                // Handle bank/supplier transaction
                // Get supplier ID from umrah booking's supplier_id field
                $bookingStmt = $conn->prepare("SELECT supplier FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
                $bookingStmt->bind_param("ii", $umrahId, $tenant_id);
                $bookingStmt->execute();
                $bookingResult = $bookingStmt->get_result();
                
                if ($bookingResult->num_rows > 0) {
                    $booking = $bookingResult->fetch_assoc();
                    $supplierId = $booking['supplier'];
                    
                    // Check for existing supplier transaction
                    $supplierTxStmt = $conn->prepare("SELECT id, amount, balance FROM supplier_transactions 
                                                    WHERE reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ?");
                    $supplierTxStmt->bind_param("ii", $transactionId, $tenant_id);
                    $supplierTxStmt->execute();
                    $supplierTxResult = $supplierTxStmt->get_result();
                    
                    if ($supplierTxResult->num_rows > 0) {
                        $supplierTx = $supplierTxResult->fetch_assoc();
                        $supplierTxId = $supplierTx['id'];
                        $originalSupplierAmount = $supplierTx['amount'];
                        $currentBalance = $supplierTx['balance'];
                        
                        // Calculate the adjustment
                        $supplierTxAdjustment = $newAmount - $originalSupplierAmount;
                        
                        // Update the supplier transaction
                        $updateSupplierTxStmt = $conn->prepare("UPDATE supplier_transactions 
                                                              SET amount = ?, transaction_date = ? 
                                                              WHERE id = ? AND tenant_id = ?");
                        $updateSupplierTxStmt->bind_param("dsii", $newAmount, $newDateTime, $supplierTxId, $tenant_id);
                        
                        if (!$updateSupplierTxStmt->execute()) {
                            throw new Exception("Failed to update supplier transaction: " . $updateSupplierTxStmt->error);
                        }
                        
                        // Get current bank_payment value
                        $getBankPaymentStmt = $conn->prepare("SELECT received_bank_payment FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
                        $getBankPaymentStmt->bind_param("ii", $umrahId, $tenant_id);
                        $getBankPaymentStmt->execute();
                        $bankPaymentResult = $getBankPaymentStmt->get_result();
                        
                        if ($bankPaymentResult->num_rows > 0) {
                            $currentBankPayment = $bankPaymentResult->fetch_assoc()['received_bank_payment'];
                            $newBankPayment = $currentBankPayment + $supplierTxAdjustment;
                            
                            // Update the umrah booking's bank_payment field
                            $updateBookingBankPaymentStmt = $conn->prepare("UPDATE umrah_bookings SET received_bank_payment = ? WHERE booking_id = ? AND tenant_id = ?");
                            $updateBookingBankPaymentStmt->bind_param("dii", $newBankPayment, $umrahId, $tenant_id);
                            
                            if (!$updateBookingBankPaymentStmt->execute()) {
                                throw new Exception("Failed to update booking bank payment: " . $updateBookingBankPaymentStmt->error);
                            }
                        }
                        
                        // Update the balance of the current transaction
                        $newSupplierBalance = $currentBalance + $supplierTxAdjustment;
                        $updateSupplierBalanceQuery = "UPDATE supplier_transactions SET balance = ? WHERE id = ? AND tenant_id = ?";
                        $updateSupplierBalanceStmt = $conn->prepare($updateSupplierBalanceQuery);
                        $updateSupplierBalanceStmt->bind_param("dii", $newSupplierBalance, $supplierTxId, $tenant_id);
                        
                        if (!$updateSupplierBalanceStmt->execute()) {
                            throw new Exception("Failed to update supplier transaction balance: " . $updateSupplierBalanceStmt->error);
                        }
                        
                        // Update subsequent supplier transactions' balances
                        $updateSubsequentSupplierQuery = "UPDATE supplier_transactions 
                                                        SET balance = balance + ? 
                                                        WHERE supplier_id = ? 
                                                        AND transaction_date > ? 
                                                        AND id != ? AND tenant_id = ?";
                        $updateSubsequentSupplierStmt = $conn->prepare($updateSubsequentSupplierQuery);
                        $updateSubsequentSupplierStmt->bind_param("disii", $supplierTxAdjustment, $supplierId, $originalDate, $supplierTxId, $tenant_id);
                        
                        if (!$updateSubsequentSupplierStmt->execute()) {
                            throw new Exception("Failed to update subsequent supplier transactions: " . $updateSubsequentSupplierStmt->error);
                        }
                        
                        // Update supplier balance
                        $stmt = $conn->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ?");
                        $stmt->bind_param("di", $supplierTxAdjustment, $supplierId, $tenant_id);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to update supplier balance: " . $stmt->error);
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
                                         SELECT family_id FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?
                                     )
                                 ),
                                 f.total_due = (
                                     SELECT SUM(due) FROM umrah_bookings 
                                     WHERE family_id = (
                                         SELECT family_id FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?
                                     )
                                 )
                                 WHERE f.family_id = (
                                     SELECT family_id FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?
                                 )";
            $updateFamilyStmt = $conn->prepare($updateFamilyQuery);
            $updateFamilyStmt->bind_param("iiiiii", $umrahId, $tenant_id, $umrahId, $tenant_id, $umrahId, $tenant_id);
            
            if (!$updateFamilyStmt->execute()) {
                throw new Exception("Failed to update family totals: " . $updateFamilyStmt->error);
            }
        }
        
        // If date changed, handle reordering based on transaction destination
        if ($newDateTime != $originalDate) {
            if (strtolower($transactionTo) === 'internal account' || empty($transactionTo)) {
                // Handle internal account date change
                $mainTxStmt = $conn->prepare("SELECT id, main_account_id, currency FROM main_account_transactions 
                                             WHERE reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ?");
                $mainTxStmt->bind_param("ii", $umrahId, $tenant_id);
                $mainTxStmt->execute();
                $mainTxResult = $mainTxStmt->get_result();
                
                if ($mainTxResult->num_rows > 0) {
                    $mainTx = $mainTxResult->fetch_assoc();
                    $mainTxId = $mainTx['id'];
                    $mainAccountId = $mainTx['main_account_id'];
                    $currency = $mainTx['currency'];
                    
                    // Update the main account transaction date
                    $updateMainTxDateStmt = $conn->prepare("UPDATE main_account_transactions SET created_at = ? WHERE id = ? AND tenant_id = ?");
                    $updateMainTxDateStmt->bind_param("si", $newDateTime, $mainTxId, $tenant_id);
                    
                    if (!$updateMainTxDateStmt->execute()) {
                        throw new Exception("Failed to update main account transaction date: " . $updateMainTxDateStmt->error);
                    }
                    
                    // Get all transactions for this account and currency, ordered by date
                    $stmt = $conn->prepare("SELECT id, amount, type, created_at 
                                           FROM main_account_transactions 
                                           WHERE main_account_id = ? AND currency = ? AND tenant_id = ?
                                           ORDER BY created_at ASC, id ASC");
                    $stmt->bind_param("is", $mainAccountId, $currency, $tenant_id);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to retrieve transactions for reordering: " . $stmt->error);
                    }
                    
                    $result = $stmt->get_result();
                    $transactions = $result->fetch_all(MYSQLI_ASSOC);
                    
                    // Recalculate running balance for all transactions
                    $runningBalance = 0;
                    foreach ($transactions as $tx) {
                        $txAmount = floatval($tx['amount']);
                        if ($tx['type'] == 'credit') {
                            $runningBalance += $txAmount;
                        } else {
                            $runningBalance -= $txAmount;
                        }
                        
                        // Update the balance for this transaction
                        $updateStmt = $conn->prepare("UPDATE main_account_transactions SET balance = ? WHERE id = ? AND tenant_id = ?");
                        $updateStmt->bind_param("di", $runningBalance, $tx['id'], $tenant_id);
                        
                        if (!$updateStmt->execute()) {
                            throw new Exception("Failed to update transaction balance during reordering: " . $updateStmt->error);
                        }
                    }
                }
            } else {
                // Handle supplier transaction date change
                $supplierStmt = $conn->prepare("SELECT id FROM suppliers WHERE name = ? AND tenant_id = ?");
                $supplierStmt->bind_param("si", $transactionTo, $tenant_id);
                $supplierStmt->execute();
                $supplierResult = $supplierStmt->get_result();
                
                if ($supplierResult->num_rows > 0) {
                    $supplier = $supplierResult->fetch_assoc();
                    $supplierId = $supplier['id'];
                    
                    // Check for existing supplier transaction
                    $supplierTxStmt = $conn->prepare("SELECT id FROM supplier_transactions 
                                                    WHERE reference_id = ? AND transaction_type = 'umrah' AND tenant_id = ?");
                    $supplierTxStmt->bind_param("ii", $umrahId, $tenant_id);
                    $supplierTxStmt->execute();
                    $supplierTxResult = $supplierTxStmt->get_result();
                    
                    if ($supplierTxResult->num_rows > 0) {
                        $supplierTx = $supplierTxResult->fetch_assoc();
                        $supplierTxId = $supplierTx['id'];
                        
                        // Update the supplier transaction date
                        $updateSupplierTxDateStmt = $conn->prepare("UPDATE supplier_transactions SET transaction_date = ? WHERE id = ? AND tenant_id = ?");
                        $updateSupplierTxDateStmt->bind_param("sii", $newDateTime, $supplierTxId, $tenant_id);
                        
                        if (!$updateSupplierTxDateStmt->execute()) {
                            throw new Exception("Failed to update supplier transaction date: " . $updateSupplierTxDateStmt->error);
                        }
                        
                        // Get all transactions for this supplier, ordered by date
                        $stmt = $conn->prepare("SELECT id, amount, transaction_date 
                                               FROM supplier_transactions 
                                               WHERE supplier_id = ? AND tenant_id = ?
                                               ORDER BY transaction_date ASC, id ASC");
                        $stmt->bind_param("ii", $supplierId, $tenant_id);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to retrieve supplier transactions for reordering: " . $stmt->error);
                        }
                        
                        $result = $stmt->get_result();
                        $transactions = $result->fetch_all(MYSQLI_ASSOC);
                        
                        // Recalculate running balance for all supplier transactions
                        $runningBalance = 0;
                        foreach ($transactions as $tx) {
                            $txAmount = floatval($tx['amount']);
                            $runningBalance += $txAmount;
                            
                            // Update the balance for this transaction
                            $updateStmt = $conn->prepare("UPDATE supplier_transactions SET balance = ? WHERE id = ? AND tenant_id = ?");
                            $updateStmt->bind_param("dii", $runningBalance, $tx['id'], $tenant_id);
                            
                            if (!$updateStmt->execute()) {
                                throw new Exception("Failed to update supplier transaction balance during reordering: " . $updateStmt->error);
                            }
                        }
                    }
                }
            }
        }
        
        // Create new values for activity log
        $newValues = json_encode([
            'transaction_id' => $transactionId,
            'umrah_id' => $umrahId,
            'payment_amount' => $newAmount,
            'payment_date' => $newDateTime,
            'payment_description' => $newDescription,
            'transaction_to' => $transactionTo,
            'exchange_rate' => $exchange_rate
        ]);
        
        // Get user information for activity log
        $userId = $_SESSION['user_id'];
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        
        // Insert activity log
        $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, ip_address, user_agent, action,table_name, record_id, old_values, new_values, created_at, tenant_id) 
                                  VALUES (?, ?, ?, 'update', 'umrah_transactions', ?, ?, ?, NOW(), ?)");
        $logStmt->bind_param("ssiissi", $userId, $ipAddress, $userAgent, $transactionId, $oldValues, $newValues, $tenant_id);
        
        if (!$logStmt->execute()) {
            // Just log the error, don't throw exception to allow transaction to complete
            error_log("Failed to insert activity log: " . $logStmt->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Transaction updated successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 