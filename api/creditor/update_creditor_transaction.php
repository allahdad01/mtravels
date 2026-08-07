<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Include database connection
require_once '../../includes/db.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $transactionId = $_POST['transaction_id'] ?? 0;
    $creditorId = $_POST['creditor_id'] ?? 0;
    $originalAmount = floatval($_POST['original_amount'] ?? 0);
    $newAmount = floatval($_POST['payment_amount'] ?? 0);
    $newDescription = $_POST['payment_description'] ?? '';
    $newReference = $_POST['reference_number'] ?? '';
    
    // Validate inputs
    $transaction_id = DbSecurity::validateInput($transactionId, 'int', ['min' => 0]);
    $creditor_id = DbSecurity::validateInput($creditorId, 'int', ['min' => 0]);
    $payment_amount = DbSecurity::validateInput($newAmount, 'float', ['min' => 0]);
    $payment_description = DbSecurity::validateInput($newDescription, 'string', ['maxlength' => 255]);
    $reference_number = DbSecurity::validateInput($newReference, 'string', ['maxlength' => 255]);
    
    if (!$transaction_id || !$creditor_id) {
        echo json_encode(['success' => false, 'message' => 'Missing transaction or creditor ID']);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Get transaction details before update
        $stmt = $pdo->prepare("SELECT * FROM creditor_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$transaction) {
            throw new Exception("Transaction not found");
        }
         $currency = $transaction['currency'];
         $originalPaymentDate = $transaction['created_at'];
         $transactionType = $transaction['transaction_type'];
         
         $newDateTime = $originalPaymentDate;
        
        // Calculate the difference between original and new amount
        $amountDifference = $newAmount - $originalAmount;
        
                 // Update the creditor transaction
         $stmt = $pdo->prepare("UPDATE creditor_transactions SET amount = ?, created_at = ?, description = ?, reference_number = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
         $stmt->bindParam(1, $newAmount, PDO::PARAM_STR);
         $stmt->bindParam(2, $newDateTime, PDO::PARAM_STR);
         $stmt->bindParam(3, $newDescription, PDO::PARAM_STR);
         $stmt->bindParam(4, $newReference, PDO::PARAM_STR);
         $stmt->bindParam(5, $transaction_id, PDO::PARAM_INT);
         $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
         $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update transaction: " . $stmt->error);
        }
        
        // Get creditor information
        $stmt = $pdo->prepare("SELECT balance FROM creditors WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $creditor_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $creditor = $stmt->fetch(PDO::FETCH_ASSOC);
        
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
        $stmt = $pdo->prepare("UPDATE creditors SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $newCreditorBalance, PDO::PARAM_STR);
        $stmt->bindParam(2, $creditor_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update creditor balance: " . $stmt->error);
        }
        
        // Get the linked main account transaction
        $stmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE transaction_of = 'creditor' AND reference_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $mainTransaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mainTransaction) {
            // Map currency codes to the correct database field names
            $currencyFieldMap = [
                'USD' => 'usd_balance',
                'AFS' => 'afs_balance',
                'EUR' => 'euro_balance',
                'DARHAM' => 'darham_balance',
                'SAR' => 'sar_balance',
            ];
            
            // Check if the currency is in our map
            if (!isset($currencyFieldMap[$currency])) {
                throw new Exception("Unknown currency: " . $currency);
            }
            
            // Get the correct field name
            $balanceField = $currencyFieldMap[$currency];
            $mainAccountId = $mainTransaction['main_account_id'];
            
            // Update main account transaction
            $stmt = $pdo->prepare("UPDATE main_account_transactions SET amount = ?, description = ?, created_at = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->bindParam(1, $newAmount, PDO::PARAM_STR);
            $stmt->bindParam(2, $newDescription, PDO::PARAM_STR);
            $stmt->bindParam(3, $newDateTime, PDO::PARAM_STR);
            $stmt->bindParam(4, $mainTransaction['id'], PDO::PARAM_INT);
            $stmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(6, $branch_id, PDO::PARAM_INT);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update main account transaction: " . $stmt->error);
            }
            
            // If amount changed, we need to update the current transaction balance first
            if ($amountDifference != 0) {
                // Get the current balance of the transaction
                $getCurrentBalanceQuery = "SELECT balance FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $getCurrentBalanceStmt = $pdo->prepare($getCurrentBalanceQuery);
                $getCurrentBalanceStmt->bindParam(1, $mainTransaction['id'], PDO::PARAM_INT);
                $getCurrentBalanceStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $getCurrentBalanceStmt->bindParam(3, $branch_id, PDO::PARAM_INT);

                if (!$getCurrentBalanceStmt->execute()) {
                    throw new Exception("Failed to get current transaction balance");
                }

                $currentBalance = $getCurrentBalanceStmt->fetch(PDO::FETCH_ASSOC)['balance'];
                
                // Calculate new balance for this transaction
                // For creditor transactions: 
                // If type is debit (payment to creditor), we subtract from the balance
                // If type is credit (adding to creditor debt), we add to the balance
                $balanceAdjustment = $mainTransaction['type'] == 'credit' ? $amountDifference : -$amountDifference;
                $newBalance = $currentBalance + $balanceAdjustment;
                
                // Update the balance of the current transaction
                $updateCurrentBalanceQuery = "UPDATE main_account_transactions SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $updateCurrentBalanceStmt = $pdo->prepare($updateCurrentBalanceQuery);
                $updateCurrentBalanceStmt->bindParam(1, $newBalance, PDO::PARAM_STR);
                $updateCurrentBalanceStmt->bindParam(2, $mainTransaction['id'], PDO::PARAM_INT);
                $updateCurrentBalanceStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $updateCurrentBalanceStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                
                if (!$updateCurrentBalanceStmt->execute()) {
                    throw new Exception("Failed to update current transaction balance: " . $updateCurrentBalanceStmt->error);
                }
                
                // Update subsequent transactions' balances
                             $updateSubsequentQuery = "UPDATE main_account_transactions 
                                     SET balance = balance + ?
                                     WHERE main_account_id = ? 
                                     AND currency = ? 
                                     AND created_at > ? 
                                     AND id != ? AND tenant_id = ? AND branch_id = ?";
             $updateSubsequentStmt = $pdo->prepare($updateSubsequentQuery);
             $updateSubsequentStmt->bindParam(1, $balanceAdjustment, PDO::PARAM_STR);
             $updateSubsequentStmt->bindParam(2, $mainTransaction['main_account_id'], PDO::PARAM_INT);
             $updateSubsequentStmt->bindParam(3, $currency, PDO::PARAM_STR);
             $updateSubsequentStmt->bindParam(4, $newDateTime, PDO::PARAM_STR);
             $updateSubsequentStmt->bindParam(5, $mainTransaction['id'], PDO::PARAM_INT);
             $updateSubsequentStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
             $updateSubsequentStmt->bindParam(7, $branch_id, PDO::PARAM_INT);
                
                if (!$updateSubsequentStmt->execute()) {
                    throw new Exception("Failed to update subsequent transactions: " . $updateSubsequentStmt->error);
                }
                
                // Update the main account balance directly
                $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt->bindParam(1, $balanceAdjustment, PDO::PARAM_STR);
                $stmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
                $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update main account balance: " . $stmt->error);
                }
            }
            
            // If amount or date changed, we need to recalculate balances of subsequent transactions
            if ($amountDifference != 0 || $newDateTime != $originalPaymentDate) {
                // If date changed, we need to reorder transactions and recalculate all balances
                if ($newDateTime != $originalPaymentDate) {
                    // Get all transactions for this account and currency, ordered by date
                    $stmt = $pdo->prepare("SELECT id, amount, type, created_at
                                           FROM main_account_transactions
                                           WHERE main_account_id = ? AND currency = ?
                                           ORDER BY created_at ASC, id ASC AND tenant_id = ? AND branch_id = ?");
                    $stmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
                    $stmt->bindParam(2, $currency, PDO::PARAM_STR);
                    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);

                    if (!$stmt->execute()) {
                        throw new Exception("Failed to retrieve transactions for reordering");
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
                            throw new Exception("Failed to update transaction balance during reordering: " . $updateStmt->error);
                        }
                    }
                    
                    // Update the final balance in the main account table
                    $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    $stmt->bindParam(1, $runningBalance, PDO::PARAM_STR);
                    $stmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
                    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                    
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
        $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
            (user_id, tenant_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, branch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $activity_log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(3, $action, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(4, $table_name, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(5, $record_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(6, $old_values, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(7, $new_values, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(8, $ip_address, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(9, $user_agent, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(10, $branch_id, PDO::PARAM_INT);
        $activity_log_stmt->execute();
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 