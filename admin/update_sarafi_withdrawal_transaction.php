<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];

// Include database connection
require_once '../includes/conn.php';
require_once '../includes/db.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start database transaction
    $conn->begin_transaction();

    try {
        // Validate and sanitize input
        $referenceId = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;
        $customerId = isset($_POST['customer_id']) ? DbSecurity::validateInput($_POST['customer_id'], 'int', ['min' => 0]) : null;
        $originalAmount = isset($_POST['original_amount']) ? DbSecurity::validateInput($_POST['original_amount'], 'float', ['min' => 0]) : null;
        $newAmount = isset($_POST['amount']) ? DbSecurity::validateInput($_POST['amount'], 'float', ['min' => 0]) : null;
        $notes = isset($_POST['notes']) ? DbSecurity::validateInput($_POST['notes'], 'string', ['maxlength' => 255]) : null;
        $reference = isset($_POST['reference']) ? DbSecurity::validateInput($_POST['reference'], 'string', ['maxlength' => 50]) : null;
        $mainAccountId = isset($_POST['main_account_id']) ? DbSecurity::validateInput($_POST['main_account_id'], 'int', ['min' => 0]) : null;
        $newDate = isset($_POST['created_at']) ? DbSecurity::validateInput($_POST['created_at'], 'date') : date('Y-m-d H:i:s');

        // Validate required fields
        if (!$referenceId || !$customerId || !$newAmount || !$mainAccountId) {
            throw new Exception('Missing required fields');
        }

        // Get transaction details before update
        $stmt = $conn->prepare("SELECT id, amount, currency, type, main_account_id, created_at, transaction_of FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'withdrawal_sarafi' AND tenant_id = ?");
        $stmt->bind_param("ii", $referenceId, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Transaction not found");
        }
        
        $transaction = $result->fetch_assoc();
        $currency = $transaction['currency'];
        $mainAccountId = $transaction['main_account_id'];
        $originalDate = $transaction['created_at'];
        $transactionId = $transaction['id'];
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
            // For withdrawal transactions, always treat as debit
            $balanceAdjustment = -$amountDifference;
            
            $updateSubsequentQuery = "UPDATE main_account_transactions 
                                     SET balance = balance + ? 
                                     WHERE main_account_id = ? 
                                     AND currency = ? 
                                     AND created_at > ? 
                                     AND id != ?
                                     AND tenant_id = ?";
            $updateSubsequentStmt = $conn->prepare($updateSubsequentQuery);
            $updateSubsequentStmt->bind_param("dissi", $balanceAdjustment, $mainAccountId, $currency, $originalDate, $transactionId, $tenant_id);
            
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
        $stmt = $conn->prepare("UPDATE main_account_transactions SET amount = ?, description = ?, created_at = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("dssii", $newAmount, $notes, $newDate, $transactionId, $tenant_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update transaction: " . $stmt->error);
        }
        
        // Update main account balance if amount changed
        if ($amountDifference != 0 && $mainAccountId) {
            // For withdrawal transactions, always decrease balance
            $balanceAdjustment = -$amountDifference;
            
            $stmt = $conn->prepare("UPDATE main_account SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ?");
            $stmt->bind_param("dii", $balanceAdjustment, $mainAccountId, $tenant_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update main account balance: " . $stmt->error);
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
        
        // Update customer wallet balance
        $stmt = $conn->prepare("UPDATE customer_wallets SET balance = balance - ? WHERE customer_id = ? AND currency = ? AND tenant_id = ?");
        $stmt->bind_param("disi", $amountDifference, $customerId, $currency, $tenant_id);
        $stmt->execute();

        // Update the sarafi transaction
        $stmt = $conn->prepare("UPDATE sarafi_transactions SET amount = ?, notes = ?, reference_number = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("dssii", $newAmount, $notes, $reference, $referenceId, $tenant_id);
        $stmt->execute();
        
        // Add activity logging
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Prepare old values
        $old_values = [
            'transaction_id' => $transactionId,
            'customer_id' => $customerId,
            'amount' => $originalAmount,
            'description' => $transaction['notes'] ?? '',
            'created_at' => $originalDate,
            'type' => 'withdrawal',
            'currency' => $currency
        ];
        
        // Prepare new values
        $new_values = [
            'amount' => $newAmount,
            'description' => $notes,
            'created_at' => $newDate
        ];
        $action = 'update';
        $table_name = 'main_account_transactions';
        $old_values_json = json_encode($old_values);
        $new_values_json = json_encode($new_values);
        
        // Insert activity log
        $activity_log_stmt = $conn->prepare("INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $activity_log_stmt->bind_param("isisssssi", 
            $user_id, 
            $action, 
            $table_name, 
            $transactionId, 
            $old_values_json, 
            $new_values_json, 
            $ip_address, 
            $user_agent,
            $tenant_id
        );
        $activity_log_stmt->execute();

        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Withdrawal transaction updated successfully',
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 