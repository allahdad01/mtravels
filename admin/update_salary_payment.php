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
    // Get form data with proper validation
    $paymentId = isset($_POST['payment_id']) ? DbSecurity::validateInput($_POST['payment_id'], 'int', ['min' => 1]) : 0;
    $userId = isset($_POST['user_id']) ? DbSecurity::validateInput($_POST['user_id'], 'int', ['min' => 1]) : 0;
    $originalAmount = isset($_POST['original_amount']) ? DbSecurity::validateInput($_POST['original_amount'], 'float', ['min' => 0]) : 0;
    $newAmount = isset($_POST['payment_amount']) ? DbSecurity::validateInput($_POST['payment_amount'], 'float', ['min' => 0]) : 0;
    $currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'string', ['maxlength' => 10]) : '';
    $newDate = isset($_POST['payment_date']) ? DbSecurity::validateInput($_POST['payment_date'], 'date') : '';
    $newDescription = isset($_POST['payment_description']) ? DbSecurity::validateInput($_POST['payment_description'], 'string', ['maxlength' => 255]) : '';
    $paymentType = isset($_POST['payment_type']) ? DbSecurity::validateInput($_POST['payment_type'], 'string', ['maxlength' => 50]) : '';
    $mainAccountId = isset($_POST['main_account_id']) ? DbSecurity::validateInput($_POST['main_account_id'], 'int', ['min' => 1]) : 0;
    
    // Additional security validation for payment_type
    $validPaymentTypes = ['regular', 'bonus', 'advance', 'other'];
    if (!in_array($paymentType, $validPaymentTypes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment type']);
        exit;
    }
    
    // Additional validation for currency
    $validCurrencies = ['USD', 'AFS', 'EUR', 'DARHAM'];
    if (!in_array($currency, $validCurrencies)) {
        echo json_encode(['success' => false, 'message' => 'Invalid currency']);
        exit;
    }
    
    // Validate required fields
    if (!$paymentId || !$userId || !$mainAccountId) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get payment details before update
        $stmt = $conn->prepare("SELECT amount, currency, payment_date, description, payment_type, receipt FROM salary_payments WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $paymentId, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Payment not found");
        }
        
        $payment = $result->fetch_assoc();
        $originalDate = $payment['payment_date'];
        $receipt = $payment['receipt'];
        $originalPaymentType = $payment['payment_type'];
        
        // Calculate the difference between original and new amount
        $amountDifference = $newAmount - $originalAmount;
        
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
        
        // Get transaction details
        $transactionStmt = $conn->prepare("SELECT id, balance, created_at FROM main_account_transactions 
                                          WHERE reference_id = ? AND transaction_of = 'salary_payment' AND tenant_id = ?");
        $transactionStmt->bind_param("ii", $paymentId, $tenant_id);
        $transactionStmt->execute();
        $transactionResult = $transactionStmt->get_result();
        
        if ($transactionResult->num_rows === 0) {
            throw new Exception("Transaction record not found");
        }
        
        $transaction = $transactionResult->fetch_assoc();
        $transactionId = $transaction['id'];
        $transactionDate = $transaction['created_at'];
        $currentBalance = $transaction['balance'];
        
        // Update subsequent transactions' balances if amount changed
        if ($amountDifference != 0) {
            // For salary payments, we're taking money from the account, so it's a debit
            // If we increase the amount, all subsequent balances should decrease
            $balanceAdjustment = -$amountDifference;
            
            $updateSubsequentQuery = "UPDATE main_account_transactions 
                                     SET balance = balance + ? 
                                     WHERE main_account_id = ? 
                                     AND currency = ? 
                                     AND created_at > ? 
                                     AND id != ?
                                     AND tenant_id = ?";
            $updateSubsequentStmt = $conn->prepare($updateSubsequentQuery);
            $updateSubsequentStmt->bind_param("dissi", $balanceAdjustment, $mainAccountId, $currency, $transactionDate, $transactionId, $tenant_id);
            
            if (!$updateSubsequentStmt->execute()) {
                throw new Exception("Failed to update subsequent transactions: " . $updateSubsequentStmt->error);
            }
            
            // Calculate new balance for this transaction
            $newBalance = $currentBalance - $amountDifference;
            
            // Update the balance of the current transaction
            $updateCurrentBalanceQuery = "UPDATE main_account_transactions SET balance = ? WHERE id = ? AND tenant_id = ?";
            $updateCurrentBalanceStmt = $conn->prepare($updateCurrentBalanceQuery);
            $updateCurrentBalanceStmt->bind_param("dii", $newBalance, $transactionId, $tenant_id);
            
            if (!$updateCurrentBalanceStmt->execute()) {
                throw new Exception("Failed to update current transaction balance: " . $updateCurrentBalanceStmt->error);
            }
        }
        
        // Update the transaction
        $updateTransactionSql = "UPDATE main_account_transactions 
                               SET amount = ?, description = ?, created_at = ? 
                               WHERE id = ? AND tenant_id = ?";
        $updateTransactionStmt = $conn->prepare($updateTransactionSql);
        $updateTransactionStmt->bind_param("dssii", $newAmount, $newDescription, $newDate, $transactionId, $tenant_id);
        
        if (!$updateTransactionStmt->execute()) {
            throw new Exception("Failed to update transaction: " . $updateTransactionStmt->error);
        }
        
        // Update the salary payment
        $updatePaymentSql = "UPDATE salary_payments 
                           SET amount = ?, payment_date = ?, description = ?, payment_type = ? 
                           WHERE id = ? AND tenant_id = ?";
        $updatePaymentStmt = $conn->prepare($updatePaymentSql);
        $updatePaymentStmt->bind_param("dsssii", $newAmount, $newDate, $newDescription, $paymentType, $paymentId, $tenant_id);
        
        if (!$updatePaymentStmt->execute()) {
            throw new Exception("Failed to update payment: " . $updatePaymentStmt->error);
        }
        
        // Update main account balance if amount changed
        if ($amountDifference != 0 && $mainAccountId) {
            // For salary payments, we're taking money from the account, so it's a debit
            // Increase balance if amount decreases, decrease if amount increases
            $balanceAdjustment = -$amountDifference;
            
            $updateAccountSql = "UPDATE main_account SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ?";
            $updateAccountStmt = $conn->prepare($updateAccountSql);
            $updateAccountStmt->bind_param("dii", $balanceAdjustment, $mainAccountId, $tenant_id);
            
            if (!$updateAccountStmt->execute()) {
                throw new Exception("Failed to update main account balance: " . $updateAccountStmt->error);
            }
        }
        
        // Update salary advance record if this is an advance payment
        if ($paymentType === 'advance') {
            // Check if there's an existing advance record by receipt number
            $advanceCheckStmt = $conn->prepare("SELECT id FROM salary_advances WHERE receipt = ? AND tenant_id = ?");
            $advanceCheckStmt->bind_param("si", $receipt, $tenant_id);
            $advanceCheckStmt->execute();
            $advanceResult = $advanceCheckStmt->get_result();
            
            if ($advanceResult->num_rows > 0) {
                // Update existing advance record
                $advanceRecord = $advanceResult->fetch_assoc();
                $advanceId = $advanceRecord['id'];
                
                $updateAdvanceSql = "UPDATE salary_advances 
                                   SET amount = ?, description = ? 
                                   WHERE id = ? AND tenant_id = ?";
                $updateAdvanceStmt = $conn->prepare($updateAdvanceSql);
                $updateAdvanceStmt->bind_param("dsii", $newAmount, $newDescription, $advanceId, $tenant_id);
                
                if (!$updateAdvanceStmt->execute()) {
                    throw new Exception("Failed to update salary advance record: " . $updateAdvanceStmt->error);
                }
            } else if ($originalPaymentType !== 'advance') {
                // Create new advance record if payment type changed to 'advance'
                $insertAdvanceSql = "INSERT INTO salary_advances 
                                   (user_id, payment_id, amount, advance_date, description, currency, receipt, tenant_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $insertAdvanceStmt = $conn->prepare($insertAdvanceSql);
                $insertAdvanceStmt->bind_param("iidssssi", $userId, $paymentId, $newAmount, $newDate, $newDescription, $currency, $receipt, $tenant_id);
                
                if (!$insertAdvanceStmt->execute()) {
                    throw new Exception("Failed to create salary advance record: " . $insertAdvanceStmt->error);
                }
            }
        } else if ($originalPaymentType === 'advance' && $paymentType !== 'advance') {
            // If payment type was 'advance' but is no longer, delete the advance record
            $deleteAdvanceSql = "DELETE FROM salary_advances WHERE receipt = ? AND tenant_id = ?";
            $deleteAdvanceStmt = $conn->prepare($deleteAdvanceSql);
            $deleteAdvanceStmt->bind_param("si", $receipt, $tenant_id);
            
            if (!$deleteAdvanceStmt->execute()) {
                throw new Exception("Failed to delete salary advance record: " . $deleteAdvanceStmt->error);
            }
        }
        
        // If date changed, we need to reorder transactions and recalculate all balances
        if ($newDate != $originalDate) {
            // Get all transactions for this account and currency, ordered by date
            $stmt = $conn->prepare("SELECT id, amount, type, created_at 
                                   FROM main_account_transactions 
                                   WHERE main_account_id = ? AND currency = ? AND tenant_id = ?
                                   ORDER BY created_at ASC, id ASC");
            $stmt->bind_param("isi", $mainAccountId, $currency, $tenant_id);
            
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
                $updateStmt->bind_param("dii", $runningBalance, $tx['id'], $tenant_id);
                
                if (!$updateStmt->execute()) {
                    throw new Exception("Failed to update transaction balance during reordering: " . $updateStmt->error);
                }
            }
        }
        
        // Add activity logging
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Prepare old values
        $old_values = [
            'payment_id' => $paymentId,
            'user_id' => $userId,
            'amount' => $originalAmount,
            'description' => $payment['description'] ?? '',
            'payment_date' => $originalDate,
            'currency' => $currency,
            'payment_type' => $payment['payment_type']
        ];
        
        // Prepare new values
        $new_values = [
            'amount' => $newAmount,
            'description' => $newDescription,
            'payment_date' => $newDate,
            'payment_type' => $paymentType
        ];
        
        $action = 'update';
        $table_name = 'salary_payments';
        $record_id = $paymentId;
        $old_values = json_encode($old_values);
        $new_values = json_encode($new_values);
        
        // Insert activity log
        $activity_log_stmt = $conn->prepare("INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $activity_log_stmt->bind_param("isisssssi", 
            $user_id, 
            $action, 
            $table_name, 
            $record_id, 
            $old_values, 
            $new_values, 
            $ip_address, 
            $user_agent,
            $tenant_id
        );
        $activity_log_stmt->execute();
        
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Payment updated successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 