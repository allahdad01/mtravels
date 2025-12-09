<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

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
include '../includes/db.php';

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
    $pdo->beginTransaction();
    
    try {
        // Get payment details before update
        $stmt = $pdo->prepare("SELECT amount, currency, payment_date, description, payment_type, receipt FROM salary_payments WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $paymentId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            throw new Exception("Payment not found");
        }

        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
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
        $transactionStmt = $pdo->prepare("SELECT id, balance, created_at FROM main_account_transactions
                                          WHERE reference_id = ? AND transaction_of = 'salary_payment' AND tenant_id = ? AND branch_id = ?");
        $transactionStmt->bindParam(1, $paymentId, PDO::PARAM_INT);
        $transactionStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $transactionStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $transactionStmt->execute();

        if ($transactionStmt->rowCount() === 0) {
            throw new Exception("Transaction record not found");
        }

        $transaction = $transactionStmt->fetch(PDO::FETCH_ASSOC);
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
                                     AND id > ?
                                     AND id != ?
                                     AND tenant_id = ? AND branch_id = ?";
            $updateSubsequentStmt = $pdo->prepare($updateSubsequentQuery);
            $updateSubsequentStmt->bindParam(1, $balanceAdjustment, PDO::PARAM_STR);
            $updateSubsequentStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(3, $currency, PDO::PARAM_STR);
            $updateSubsequentStmt->bindParam(4, $transactionId, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(5, $transactionId, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(7, $branch_id, PDO::PARAM_INT);

            if (!$updateSubsequentStmt->execute()) {
                throw new Exception("Failed to update subsequent transactions: " . $updateSubsequentStmt->errorInfo()[2]);
            }
            
            // Calculate new balance for this transaction
            $newBalance = $currentBalance - $amountDifference;
            
            // Update the balance of the current transaction
            $updateCurrentBalanceQuery = "UPDATE main_account_transactions SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $updateCurrentBalanceStmt = $pdo->prepare($updateCurrentBalanceQuery);
            $updateCurrentBalanceStmt->bindParam(1, $newBalance, PDO::PARAM_STR);
            $updateCurrentBalanceStmt->bindParam(2, $transactionId, PDO::PARAM_INT);
            $updateCurrentBalanceStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $updateCurrentBalanceStmt->bindParam(4, $branch_id, PDO::PARAM_INT);

            if (!$updateCurrentBalanceStmt->execute()) {
                throw new Exception("Failed to update current transaction balance: " . $updateCurrentBalanceStmt->errorInfo()[2]);
            }
        }
        
        // Update the transaction
        $updateTransactionSql = "UPDATE main_account_transactions
                               SET amount = ?, description = ?, created_at = ?
                               WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $updateTransactionStmt = $pdo->prepare($updateTransactionSql);
        $updateTransactionStmt->bindParam(1, $newAmount, PDO::PARAM_STR);
        $updateTransactionStmt->bindParam(2, $newDescription, PDO::PARAM_STR);
        $updateTransactionStmt->bindParam(3, $newDate, PDO::PARAM_STR);
        $updateTransactionStmt->bindParam(4, $transactionId, PDO::PARAM_INT);
        $updateTransactionStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
        $updateTransactionStmt->bindParam(6, $branch_id, PDO::PARAM_INT);

        if (!$updateTransactionStmt->execute()) {
            throw new Exception("Failed to update transaction: " . $updateTransactionStmt->errorInfo()[2]);
        }
        
        // Update the salary payment
        $updatePaymentSql = "UPDATE salary_payments
                           SET amount = ?, payment_date = ?, description = ?, payment_type = ?
                           WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $updatePaymentStmt = $pdo->prepare($updatePaymentSql);
        $updatePaymentStmt->bindParam(1, $newAmount, PDO::PARAM_STR);
        $updatePaymentStmt->bindParam(2, $newDate, PDO::PARAM_STR);
        $updatePaymentStmt->bindParam(3, $newDescription, PDO::PARAM_STR);
        $updatePaymentStmt->bindParam(4, $paymentType, PDO::PARAM_STR);
        $updatePaymentStmt->bindParam(5, $paymentId, PDO::PARAM_INT);
        $updatePaymentStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $updatePaymentStmt->bindParam(7, $branch_id, PDO::PARAM_INT);

        if (!$updatePaymentStmt->execute()) {
            throw new Exception("Failed to update payment: " . $updatePaymentStmt->errorInfo()[2]);
        }
        
        // Update main account balance if amount changed
        if ($amountDifference != 0 && $mainAccountId) {
            // For salary payments, we're taking money from the account, so it's a debit
            // Increase balance if amount decreases, decrease if amount increases
            $balanceAdjustment = -$amountDifference;
            
            $updateAccountSql = "UPDATE main_account SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $updateAccountStmt = $pdo->prepare($updateAccountSql);
            $updateAccountStmt->bindParam(1, $balanceAdjustment, PDO::PARAM_STR);
            $updateAccountStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $updateAccountStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $updateAccountStmt->bindParam(4, $branch_id, PDO::PARAM_INT);

            if (!$updateAccountStmt->execute()) {
                throw new Exception("Failed to update main account balance: " . $updateAccountStmt->errorInfo()[2]);
            }
        }
        
        // Update salary advance record if this is an advance payment
        if ($paymentType === 'advance') {
            // Check if there's an existing advance record by receipt number
            $advanceCheckStmt = $pdo->prepare("SELECT id FROM salary_advances WHERE receipt = ? AND tenant_id = ? AND branch_id = ?");
            $advanceCheckStmt->bindParam(1, $receipt, PDO::PARAM_STR);
            $advanceCheckStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $advanceCheckStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $advanceCheckStmt->execute();

            if ($advanceCheckStmt->rowCount() > 0) {
                // Update existing advance record
                $advanceRecord = $advanceCheckStmt->fetch(PDO::FETCH_ASSOC);
                $advanceId = $advanceRecord['id'];
                
                $updateAdvanceSql = "UPDATE salary_advances
                                   SET amount = ?, description = ?
                                   WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $updateAdvanceStmt = $pdo->prepare($updateAdvanceSql);
                $updateAdvanceStmt->bindParam(1, $newAmount, PDO::PARAM_STR);
                $updateAdvanceStmt->bindParam(2, $newDescription, PDO::PARAM_STR);
                $updateAdvanceStmt->bindParam(3, $advanceId, PDO::PARAM_INT);
                $updateAdvanceStmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
                $updateAdvanceStmt->bindParam(5, $branch_id, PDO::PARAM_INT);

                if (!$updateAdvanceStmt->execute()) {
                    throw new Exception("Failed to update salary advance record: " . $updateAdvanceStmt->errorInfo()[2]);
                }
            } else if ($originalPaymentType !== 'advance') {
                // Create new advance record if payment type changed to 'advance'
                $insertAdvanceSql = "INSERT INTO salary_advances
                                   (user_id, payment_id, amount, advance_date, description, currency, receipt, tenant_id, branch_id)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $insertAdvanceStmt = $pdo->prepare($insertAdvanceSql);
                $insertAdvanceStmt->bindParam(1, $userId, PDO::PARAM_INT);
                $insertAdvanceStmt->bindParam(2, $paymentId, PDO::PARAM_INT);
                $insertAdvanceStmt->bindParam(3, $newAmount, PDO::PARAM_STR);
                $insertAdvanceStmt->bindParam(4, $newDate, PDO::PARAM_STR);
                $insertAdvanceStmt->bindParam(5, $newDescription, PDO::PARAM_STR);
                $insertAdvanceStmt->bindParam(6, $currency, PDO::PARAM_STR);
                $insertAdvanceStmt->bindParam(7, $receipt, PDO::PARAM_STR);
                $insertAdvanceStmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
                $insertAdvanceStmt->bindParam(9, $branch_id, PDO::PARAM_INT);

                if (!$insertAdvanceStmt->execute()) {
                    throw new Exception("Failed to create salary advance record: " . $insertAdvanceStmt->errorInfo()[2]);
                }
            }
        } else if ($originalPaymentType === 'advance' && $paymentType !== 'advance') {
            // If payment type was 'advance' but is no longer, delete the advance record
            $deleteAdvanceSql = "DELETE FROM salary_advances WHERE receipt = ? AND tenant_id = ? AND branch_id = ?";
            $deleteAdvanceStmt = $pdo->prepare($deleteAdvanceSql);
            $deleteAdvanceStmt->bindParam(1, $receipt, PDO::PARAM_STR);
            $deleteAdvanceStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $deleteAdvanceStmt->bindParam(3, $branch_id, PDO::PARAM_INT);

            if (!$deleteAdvanceStmt->execute()) {
                throw new Exception("Failed to delete salary advance record: " . $deleteAdvanceStmt->errorInfo()[2]);
            }
        }
        
        // If date changed, we need to reorder transactions and recalculate all balances
        if ($newDate != $originalDate) {
            // Get all transactions for this account and currency, ordered by date
            $stmt = $pdo->prepare("SELECT id, amount, type, created_at
                                   FROM main_account_transactions
                                   WHERE main_account_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?
                                   ORDER BY created_at ASC, id ASC");
            $stmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
            $stmt->bindParam(2, $currency, PDO::PARAM_STR);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);

            if (!$stmt->execute()) {
                throw new Exception("Failed to retrieve transactions for reordering: " . $stmt->errorInfo()[2]);
            }

            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
                $updateStmt = $pdo->prepare("UPDATE main_account_transactions SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $updateStmt->bindParam(1, $runningBalance, PDO::PARAM_STR);
                $updateStmt->bindParam(2, $tx['id'], PDO::PARAM_INT);
                $updateStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $updateStmt->bindParam(4, $branch_id, PDO::PARAM_INT);

                if (!$updateStmt->execute()) {
                    throw new Exception("Failed to update transaction balance during reordering: " . $updateStmt->errorInfo()[2]);
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
        $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id, branch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $activity_log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(2, $action, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(3, $table_name, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(4, $record_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(5, $old_values, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(6, $new_values, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(7, $ip_address, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(8, $user_agent, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(10, $branch_id, PDO::PARAM_INT);
        $activity_log_stmt->execute();
        
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Payment updated successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 