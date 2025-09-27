<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();



// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}
$tenant_id = $_SESSION['tenant_id'];
// Include database connection
include '../includes/conn.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $transactionId = $_POST['transaction_id'] ?? 0;
    $dateChangeId = $_POST['booking_id'] ?? 0;
    $originalAmount = floatval($_POST['original_amount'] ?? 0);
    $newAmount = floatval($_POST['payment_amount'] ?? 0);
    $newDate = $_POST['payment_date'] ?? '';
    $newDescription = $_POST['payment_description'] ?? '';
    $exchange_rate = floatval($_POST['exchange_rate'] ?? 0);
    // Validate required fields

// Validate payment_description
$payment_description = isset($_POST['payment_description']) ? DbSecurity::validateInput($_POST['payment_description'], 'string', ['maxlength' => 255]) : null;

// Validate payment_date
$payment_date = isset($_POST['payment_date']) ? DbSecurity::validateInput($_POST['payment_date'], 'date') : null;

// Validate payment_amount
$payment_amount = isset($_POST['payment_amount']) ? DbSecurity::validateInput($_POST['payment_amount'], 'float', ['min' => 0]) : null;

// Validate original_amount
$original_amount = isset($_POST['original_amount']) ? DbSecurity::validateInput($_POST['original_amount'], 'float', ['min' => 0]) : null;

// Validate booking_id
$booking_id = isset($_POST['booking_id']) ? DbSecurity::validateInput($_POST['booking_id'], 'int', ['min' => 0]) : null;
$exchange_rate = isset($_POST['exchange_rate']) ? DbSecurity::validateInput($_POST['exchange_rate'], 'float', ['min' => 0]) : null;
// Validate transaction_id
$transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;
    if (!$transactionId || !$dateChangeId) {
        echo json_encode(['success' => false, 'message' => 'Missing transaction or date change ID']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get transaction details before update
        $stmt = $conn->prepare("SELECT amount, type, main_account_id, created_at FROM main_account_transactions WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $transactionId, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Transaction not found");
        }
        
        $transaction = $result->fetch_assoc();
        $type = $transaction['type'];
        $mainAccountId = $transaction['main_account_id'];
        $originalDate = $transaction['created_at'];
        
        // Get currency from date_change_tickets
        $currencyStmt = $conn->prepare("SELECT currency FROM date_change_tickets WHERE id = ? AND tenant_id = ?");
        $currencyStmt->bind_param("ii", $dateChangeId, $tenant_id);
        $currencyStmt->execute();
        $currencyResult = $currencyStmt->get_result();
        
        if ($currencyResult->num_rows === 0) {
            throw new Exception("Date change ticket not found");
        }
        
        $currency = $currencyResult->fetch_assoc()['currency'];
        
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
        
        // Update subsequent transactions' balances if amount changed
        if ($amountDifference != 0) {
            // For credit transactions, subsequent balances increase when amount increases
            // For debit transactions, subsequent balances decrease when amount increases
            $balanceAdjustment = ($type == 'credit') ? $amountDifference : -$amountDifference;
            
            $updateSubsequentQuery = "UPDATE main_account_transactions 
                                     SET balance = balance + ? 
                                     WHERE main_account_id = ? 
                                     AND created_at > ? 
                                     AND id != ?
                                     AND tenant_id = ?";
            $updateSubsequentStmt = $conn->prepare($updateSubsequentQuery);
            $updateSubsequentStmt->bind_param("disii", $balanceAdjustment, $mainAccountId, $originalDate, $transactionId, $tenant_id);
            
            if (!$updateSubsequentStmt->execute()) {
                throw new Exception("Failed to update subsequent transactions: " . $updateSubsequentStmt->error);
            }
            
            // Get the current balance of the transaction
            $getCurrentBalanceQuery = "SELECT balance FROM main_account_transactions WHERE id = ? AND tenant_id = ?";
            $getCurrentBalanceStmt = $conn->prepare($getCurrentBalanceQuery);
            $getCurrentBalanceStmt->bind_param("ii", $transactionId, $tenant_id);
            
            if (!$getCurrentBalanceStmt->execute()) {
                throw new Exception("Failed to get current transaction balance: " . $getCurrentBalanceStmt->error);
            }
            
            $balanceResult = $getCurrentBalanceStmt->get_result();
            $currentBalance = $balanceResult->fetch_assoc()['balance'];
            
            // Calculate new balance for this transaction
            $newBalance = $currentBalance + (($type == 'credit') ? $amountDifference : -$amountDifference);
            
            // Update the balance of the current transaction
            $updateCurrentBalanceQuery = "UPDATE main_account_transactions SET balance = ? WHERE id = ? AND tenant_id = ?";
            $updateCurrentBalanceStmt = $conn->prepare($updateCurrentBalanceQuery);
            $updateCurrentBalanceStmt->bind_param("dii", $newBalance, $transactionId, $tenant_id);
            
            if (!$updateCurrentBalanceStmt->execute()) {
                throw new Exception("Failed to update current transaction balance: " . $updateCurrentBalanceStmt->error);
            }
        }
        
        // Update the transaction
        $stmt = $conn->prepare("UPDATE main_account_transactions SET amount = ?, description = ?, exchange_rate = ?, created_at = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("dsssii", $newAmount, $newDescription, $exchange_rate, $newDate, $transactionId, $tenant_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update transaction: " . $stmt->error);
        }
        
        // Update main account balance if amount changed
        if ($amountDifference != 0 && $mainAccountId) {
            // For credit transactions (received payments), increase balance if amount increases
            // For debit transactions (paid out), decrease balance if amount increases
            $balanceAdjustment = ($type == 'credit') ? $amountDifference : -$amountDifference;
            
            $stmt = $conn->prepare("UPDATE main_account SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ?");
            $stmt->bind_param("dii", $balanceAdjustment, $mainAccountId, $tenant_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update main account balance: " . $stmt->error);
            }
        }
        
        // If date changed, we need to reorder transactions and recalculate all balances
        if ($newDate != $originalDate) {
            // Get all transactions for this account, ordered by date
            $stmt = $conn->prepare("SELECT id, amount, type, created_at 
                                   FROM main_account_transactions 
                                   WHERE main_account_id = ? 
                                   ORDER BY created_at ASC, id ASC
                                   AND tenant_id = ?");
            $stmt->bind_param("ii", $mainAccountId, $tenant_id);
            
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
            'transaction_id' => $transactionId,
            'booking_id' => $dateChangeId,
            'amount' => $originalAmount,
            'description' => $transaction['description'] ?? '',
            'created_at' => $originalDate
        ];
        
        // Prepare new values
        $new_values = [
            'amount' => $newAmount,
            'description' => $newDescription,
            'created_at' => $newDate
        ];
        $action = 'update';
        $table_name = 'main_account_transactions';
        $record_id = $transactionId;
        $old_values = json_encode($old_values);
        $new_values = json_encode($new_values);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Insert activity log
        $activity_log_stmt = $conn->prepare("INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $activity_log_stmt->bind_param("isssssssi", $user_id, $action, $table_name, $record_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
        $activity_log_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 