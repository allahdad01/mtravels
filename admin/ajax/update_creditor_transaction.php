<?php
// Include database security module for input validation
require_once '../includes/db_security.php';

// Include security module
require_once '../security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];

// Include database connection
include '../../includes/conn.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $transactionId = $_POST['transaction_id'] ?? 0;
    $creditorId = $_POST['creditor_id'] ?? 0;
    $originalAmount = floatval($_POST['original_amount'] ?? 0);
    $newAmount = floatval($_POST['payment_amount'] ?? 0);
    $paymentDate = $_POST['payment_date'] ?? '';
    $paymentTime = $_POST['payment_time'] ?? '';
    $newDescription = $_POST['payment_description'] ?? '';
    $newReference = $_POST['reference_number'] ?? '';
    
    // Parse the separate date and time fields to YYYY-MM-DD HH:MM:SS
    $newDateTime = '';
    if (!empty($paymentDate)) {
        // Parse date from DD/MM/YYYY format
        $dateObj = DateTime::createFromFormat('d/m/Y', $paymentDate);
        if ($dateObj === false) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format. Please use DD/MM/YYYY']);
            exit;
        }
        
        // Parse time or use default
        if (!empty($paymentTime)) {
            // Check if time has valid format
            $timeObj = DateTime::createFromFormat('H:i:s', $paymentTime);
            if ($timeObj === false) {
                echo json_encode(['success' => false, 'message' => 'Invalid time format. Please use HH:MM:SS']);
                exit;
            }
            // Combine date and time
            $newDateTime = $dateObj->format('Y-m-d') . ' ' . $paymentTime;
        } else {
            // Use date with default time
            $newDateTime = $dateObj->format('Y-m-d') . ' 00:00:00';
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Payment date is required']);
        exit;
    }
    
    // Validate inputs
    $transaction_id = DbSecurity::validateInput($transactionId, 'int', ['min' => 0]);
    $creditor_id = DbSecurity::validateInput($creditorId, 'int', ['min' => 0]);
    $payment_amount = DbSecurity::validateInput($newAmount, 'float', ['min' => 0]);
    $payment_date = DbSecurity::validateInput($newDateTime, 'datetime');
    $payment_description = DbSecurity::validateInput($newDescription, 'string', ['maxlength' => 255]);
    $reference_number = DbSecurity::validateInput($newReference, 'string', ['maxlength' => 255]);
    
    if (!$transaction_id || !$creditor_id) {
        echo json_encode(['success' => false, 'message' => 'Missing transaction or creditor ID']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get transaction details before update
        $stmt = $conn->prepare("SELECT * FROM creditor_transactions WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $transaction_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Transaction not found");
        }
        
                 $transaction = $result->fetch_assoc();
         $currency = $transaction['currency'];
         $originalPaymentDate = $transaction['created_at'];
         $transactionType = $transaction['transaction_type'];
        
        // Calculate the difference between original and new amount
        $amountDifference = $newAmount - $originalAmount;
        
                 // Update the creditor transaction
         $stmt = $conn->prepare("UPDATE creditor_transactions SET amount = ?, created_at = ?, description = ?, reference_number = ? WHERE id = ? AND tenant_id = ?");
         $stmt->bind_param("dsssii", $newAmount, $newDateTime, $newDescription, $newReference, $transaction_id, $tenant_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update transaction: " . $stmt->error);
        }
        
        // Get creditor information
        $stmt = $conn->prepare("SELECT balance FROM creditors WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $creditor_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $creditor = $result->fetch_assoc();
        
        if (!$creditor) {
            throw new Exception("Creditor not found");
        }
        
        // Update creditor balance based on transaction type and amount difference
        $newCreditorBalance = $creditor['balance'];
        if ($transactionType == 'debit') {
            // If it's a payment (debit), subtracting more means reducing the balance more
            $newCreditorBalance = $creditor['balance'] - $amountDifference;
        } else {
            // If it's a credit, adding more means increasing the balance
            $newCreditorBalance = $creditor['balance'] + $amountDifference;
        }
        
        // Update creditor balance
        $stmt = $conn->prepare("UPDATE creditors SET balance = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("did", $newCreditorBalance, $creditor_id, $tenant_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update creditor balance: " . $stmt->error);
        }
        
        // Get the linked main account transaction
        $stmt = $conn->prepare("SELECT * FROM main_account_transactions WHERE transaction_of = 'creditor' AND reference_id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $transaction_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $mainTransaction = $result->fetch_assoc();
        
        if ($mainTransaction) {
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
            $mainAccountId = $mainTransaction['main_account_id'];
            
            // Update main account transaction
            $stmt = $conn->prepare("UPDATE main_account_transactions SET amount = ?, description = ?, created_at = ? WHERE id = ? AND tenant_id = ?");
            $stmt->bind_param("dssii", $newAmount, $newDescription, $newDateTime, $mainTransaction['id'], $tenant_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update main account transaction: " . $stmt->error);
            }
            
            // If amount changed, we need to update the current transaction balance first
            if ($amountDifference != 0) {
                // Get the current balance of the transaction
                $getCurrentBalanceQuery = "SELECT balance FROM main_account_transactions WHERE id = ? AND tenant_id = ?";
                $getCurrentBalanceStmt = $conn->prepare($getCurrentBalanceQuery);
                $getCurrentBalanceStmt->bind_param("ii", $mainTransaction['id'], $tenant_id);
                
                if (!$getCurrentBalanceStmt->execute()) {
                    throw new Exception("Failed to get current transaction balance: " . $getCurrentBalanceStmt->error);
                }
                
                $balanceResult = $getCurrentBalanceStmt->get_result();
                $currentBalance = $balanceResult->fetch_assoc()['balance'];
                
                // Calculate new balance for this transaction
                // For creditor transactions: 
                // If type is debit (payment to creditor), we subtract from the balance
                // If type is credit (adding to creditor debt), we add to the balance
                $balanceAdjustment = $mainTransaction['type'] == 'credit' ? $amountDifference : -$amountDifference;
                $newBalance = $currentBalance + $balanceAdjustment;
                
                // Update the balance of the current transaction
                $updateCurrentBalanceQuery = "UPDATE main_account_transactions SET balance = ? WHERE id = ? AND tenant_id = ?";
                $updateCurrentBalanceStmt = $conn->prepare($updateCurrentBalanceQuery);
                $updateCurrentBalanceStmt->bind_param("di", $newBalance, $mainTransaction['id'], $tenant_id);
                
                if (!$updateCurrentBalanceStmt->execute()) {
                    throw new Exception("Failed to update current transaction balance: " . $updateCurrentBalanceStmt->error);
                }
                
                // Update subsequent transactions' balances
                             $updateSubsequentQuery = "UPDATE main_account_transactions 
                                     SET balance = balance + ?
                                     WHERE main_account_id = ? 
                                     AND currency = ? 
                                     AND created_at > ? 
                                     AND id != ? AND tenant_id = ?";
             $updateSubsequentStmt = $conn->prepare($updateSubsequentQuery);
             $updateSubsequentStmt->bind_param("dissi", $balanceAdjustment, $mainTransaction['main_account_id'], $currency, $newDateTime, $mainTransaction['id'], $tenant_id);
                
                if (!$updateSubsequentStmt->execute()) {
                    throw new Exception("Failed to update subsequent transactions: " . $updateSubsequentStmt->error);
                }
                
                // Update the main account balance directly
                $stmt = $conn->prepare("UPDATE main_account SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ?");
                $stmt->bind_param("dii", $balanceAdjustment, $mainAccountId, $tenant_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update main account balance: " . $stmt->error);
                }
            }
            
            // If amount or date changed, we need to recalculate balances of subsequent transactions
            if ($amountDifference != 0 || $newDateTime != $originalPaymentDate) {
                // If date changed, we need to reorder transactions and recalculate all balances
                if ($newDateTime != $originalPaymentDate) {
                    // Get all transactions for this account and currency, ordered by date
                    $stmt = $conn->prepare("SELECT id, amount, type, created_at 
                                           FROM main_account_transactions 
                                           WHERE main_account_id = ? AND currency = ? 
                                           ORDER BY created_at ASC, id ASC AND tenant_id = ?");
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
                    
                    // Update the final balance in the main account table
                    $stmt = $conn->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ?");
                    $stmt->bind_param("dii", $runningBalance, $mainAccountId, $tenant_id);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update main account balance: " . $stmt->error);
                    }
                }
            }
        }
        
        // Log the activity
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Prepare old values
        $old_values = [
            'transaction_id' => $transaction_id,
            'creditor_id' => $creditor_id,
            'amount' => $originalAmount,
            'description' => $transaction['description'] ?? '',
            'created_at' => $originalPaymentDate,
            'reference_number' => $transaction['reference_number'] ?? '',
            'currency' => $currency
        ];
        
        // Prepare new values
        $new_values = [
            'amount' => $newAmount,
            'description' => $newDescription,
            'created_at' => $newDateTime,
            'reference_number' => $newReference
        ];
        
        $action = 'update';
        $table_name = 'creditor_transactions';
        $record_id = $transaction_id;
        $old_values = json_encode($old_values);
        $new_values = json_encode($new_values);
        
        // Insert activity log
        $activity_log_stmt = $conn->prepare("INSERT INTO activity_log 
            (user_id, tenant_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $activity_log_stmt->bind_param("isisssssi", 
            $user_id, 
            $tenant_id,
            $action, 
            $table_name, 
            $record_id, 
            $old_values, 
            $new_values, 
            $ip_address, 
            $user_agent
        );
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