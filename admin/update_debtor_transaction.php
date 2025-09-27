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
    $debtorId = $_POST['debtor_id'] ?? 0;
    $originalAmount = floatval($_POST['original_amount'] ?? 0);
    $newAmount = floatval($_POST['amount'] ?? 0);
    $newDescription = $_POST['description'] ?? '';
    $newDate = $_POST['payment_date'] ?? '';
    $createdAtTime = $_POST['created_at_time'] ?? '';
    $createdAtDate = $_POST['created_at_date'] ?? $newDate;
    
    // Combine date and time for created_at if provided
    if (!empty($createdAtTime)) {
        $newDateTime = $createdAtDate . ' ' . $createdAtTime . ':00';
    } else {
        $newDateTime = $newDate . ' 00:00:00';
    }
    
    // Validate required fields
    $transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;
    $debtor_id = isset($_POST['debtor_id']) ? DbSecurity::validateInput($_POST['debtor_id'], 'int', ['min' => 0]) : null;
    $original_amount = isset($_POST['original_amount']) ? DbSecurity::validateInput($_POST['original_amount'], 'float', ['min' => 0]) : null;
    $amount = isset($_POST['amount']) ? DbSecurity::validateInput($_POST['amount'], 'float', ['min' => 0]) : null;
    $description = isset($_POST['description']) ? DbSecurity::validateInput($_POST['description'], 'string', ['maxlength' => 255]) : null;
    $payment_date = isset($_POST['payment_date']) ? DbSecurity::validateInput($_POST['payment_date'], 'date') : null;
    
    if (!$transactionId || !$debtorId) {
        echo json_encode(['success' => false, 'message' => 'Missing transaction or debtor ID']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get transaction details before update
        $stmt = $conn->prepare("SELECT dt.*, mat.id as main_transaction_id, mat.main_account_id 
                               FROM debtor_transactions dt 
                               LEFT JOIN main_account_transactions mat ON mat.reference_id = dt.id AND mat.transaction_of = 'debtor' 
                               WHERE dt.id = ? AND dt.tenant_id = ?");
        $stmt->bind_param("ii", $transactionId, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Transaction not found");
        }
        
        $transaction = $result->fetch_assoc();
        $currency = $transaction['currency'];
        $transactionType = $transaction['transaction_type'];
        $mainTransactionId = $transaction['main_transaction_id'];
        $mainAccountId = $transaction['main_account_id'];
        $originalDate = $transaction['created_at'];
        
        // Calculate the difference between original and new amount
        $amountDifference = $newAmount - $originalAmount;
        
        // Get the debtor's current balance
        $stmt = $conn->prepare("SELECT balance, currency FROM debtors WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $debtorId, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $debtor = $result->fetch_assoc();
        
        if (!$debtor) {
            throw new Exception("Debtor not found");
        }
        
        // Update debtor's balance
        // For credit (payment) transactions, if amount increases, balance decreases more
        // For debit (debt) transactions, if amount increases, balance increases more
        $balanceAdjustment = ($transactionType == 'credit') ? -$amountDifference : $amountDifference;
        $newDebtorBalance = $debtor['balance'] + $balanceAdjustment;
        
        if ($newDebtorBalance < 0 && $transactionType == 'credit') {
            throw new Exception("Adjustment would result in negative debtor balance");
        }
        
        $stmt = $conn->prepare("UPDATE debtors SET balance = ? WHERE id = ?");
        $stmt->bind_param("di", $newDebtorBalance, $debtorId);
        $stmt->execute();
        
        // Update the debtor transaction
        $stmt = $conn->prepare("UPDATE debtor_transactions SET amount = ?, description = ?, created_at = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("dssii", $newAmount, $newDescription, $newDateTime, $transactionId, $tenant_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update debtor transaction: " . $stmt->error);
        }
        
                // If there's a linked main account transaction, update it as well
        if ($mainTransactionId) {
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
            
            // Update main account transaction
            $stmt = $conn->prepare("SELECT amount, balance, created_at FROM main_account_transactions WHERE id = ? AND tenant_id = ?");
            $stmt->bind_param("ii", $mainTransactionId, $tenant_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $mainTransaction = $result->fetch_assoc();
            
            if (!$mainTransaction) {
                throw new Exception("Main account transaction not found");
            }
            
            // For credit (payment) transactions in debtor context, it's a credit (add) to main account
            // For debit (debt) transactions in debtor context, it's a debit (subtract) from main account
            $mainBalanceAdjustment = ($transactionType == 'credit') ? $amountDifference : -$amountDifference;
            
            // Update main account balance
            $stmt = $conn->prepare("UPDATE main_account SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ?");
            $stmt->bind_param("dii", $mainBalanceAdjustment, $mainAccountId, $tenant_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update main account balance: " . $stmt->error);
            }
            
            // Get the original transaction date from main account
            $originalMainDate = $mainTransaction['created_at'];
            
            // Check if the date has changed
            if ($newDateTime != $originalMainDate) {
                // We need to reorder transactions and recalculate all balances
                
                // First update the transaction date
                $stmt = $conn->prepare("UPDATE main_account_transactions SET amount = ?, description = ?, created_at = ? WHERE id = ? AND tenant_id = ?");
                $stmt->bind_param("dssii", $newAmount, $newDescription, $newDateTime, $mainTransactionId, $tenant_id);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update main account transaction: " . $stmt->error);
                }
                
                // Now get all transactions for this account and currency, ordered by date
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
            } else {
                // If date hasn't changed, just update amount and description, and handle balance adjustments
                
                // Update subsequent main account transaction balances
                $updateSubsequentQuery = "UPDATE main_account_transactions 
                                         SET balance = balance + ? 
                                         WHERE main_account_id = ? 
                                         AND currency = ? 
                                         AND created_at > (SELECT created_at FROM main_account_transactions WHERE id = ?) 
                                         AND id != ?
                                         AND tenant_id = ?";
                $updateSubsequentStmt = $conn->prepare($updateSubsequentQuery);
                $updateSubsequentStmt->bind_param("disiii", $mainBalanceAdjustment, $mainAccountId, $currency, $mainTransactionId, $mainTransactionId, $tenant_id);
                if (!$updateSubsequentStmt->execute()) {
                    throw new Exception("Failed to update subsequent transactions: " . $updateSubsequentStmt->error);
                }
                
                // Update the main transaction
                $newMainBalance = $mainTransaction['balance'] + $mainBalanceAdjustment;
                $stmt = $conn->prepare("UPDATE main_account_transactions SET amount = ?, balance = ?, description = ?, created_at = ? WHERE id = ? AND tenant_id = ?");
                $stmt->bind_param("ddssii", $newAmount, $newMainBalance, $newDescription, $newDateTime, $mainTransactionId, $tenant_id);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update main account transaction: " . $stmt->error);
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
            'debtor_id' => $debtorId,
            'amount' => $originalAmount,
            'description' => $transaction['description'] ?? '',
            'created_at' => $originalDate,
            'transaction_type' => $transactionType,
            'currency' => $currency
        ];
        
        // Prepare new values
        $new_values = [
            'amount' => $newAmount,
            'description' => $newDescription,
            'created_at' => $newDateTime
        ];
        
        $action = 'update';
        $table_name = 'debtor_transactions';
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