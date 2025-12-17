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
    $pdo->beginTransaction();
    
    try {
        // Get transaction details before update
        $stmt = $pdo->prepare("SELECT dt.*, mat.id as main_transaction_id, mat.main_account_id
                               FROM debtor_transactions dt
                               LEFT JOIN main_account_transactions mat ON mat.reference_id = dt.id AND mat.transaction_of = 'debtor' AND mat.tenant_id = dt.tenant_id AND mat.branch_id = dt.branch_id
                               WHERE dt.id = ? AND dt.tenant_id = ? AND dt.branch_id = ?");
        $stmt->bindParam(1, $transactionId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            throw new Exception("Transaction not found");
        }
        $currency = $transaction['currency'];
        $transactionType = $transaction['transaction_type'];
        $mainTransactionId = $transaction['main_transaction_id'];
        $mainAccountId = $transaction['main_account_id'];
        $originalDate = $transaction['created_at'];
        
        // Calculate the difference between original and new amount
        $amountDifference = $newAmount - $originalAmount;
        
        // Get the debtor's current balance
        $stmt = $pdo->prepare("SELECT balance, currency FROM debtors WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $debtorId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $debtor = $stmt->fetch(PDO::FETCH_ASSOC);
        
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
        
        $stmt = $pdo->prepare("UPDATE debtors SET balance = ? WHERE id = ?");
        $stmt->bindParam(1, $newDebtorBalance, PDO::PARAM_STR);
        $stmt->bindParam(2, $debtorId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Update the debtor transaction
        $stmt = $pdo->prepare("UPDATE debtor_transactions SET amount = ?, description = ?, created_at = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $newAmount, PDO::PARAM_STR);
        $stmt->bindParam(2, $newDescription, PDO::PARAM_STR);
        $stmt->bindParam(3, $newDateTime, PDO::PARAM_STR);
        $stmt->bindParam(4, $transactionId, PDO::PARAM_INT);
        $stmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(6, $branch_id, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update debtor transaction");
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
            $stmt = $pdo->prepare("SELECT amount, balance, created_at FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->bindParam(1, $mainTransactionId, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $mainTransaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$mainTransaction) {
                throw new Exception("Main account transaction not found");
            }
            
            // For credit (payment) transactions in debtor context, it's a credit (add) to main account
            // For debit (debt) transactions in debtor context, it's a debit (subtract) from main account
            $mainBalanceAdjustment = ($transactionType == 'credit') ? $amountDifference : -$amountDifference;
            
            // Update main account balance
            $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->bindParam(1, $mainBalanceAdjustment, PDO::PARAM_STR);
            $stmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update main account balance");
            }
            
            // Get the original transaction date from main account
            $originalMainDate = $mainTransaction['created_at'];
            
            // Check if the date has changed
            if ($newDateTime != $originalMainDate) {
                // We need to reorder transactions and recalculate all balances
                
                // First update the transaction date
                $stmt = $pdo->prepare("UPDATE main_account_transactions SET amount = ?, description = ?, created_at = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt->bindParam(1, $newAmount, PDO::PARAM_STR);
                $stmt->bindParam(2, $newDescription, PDO::PARAM_STR);
                $stmt->bindParam(3, $newDateTime, PDO::PARAM_STR);
                $stmt->bindParam(4, $mainTransactionId, PDO::PARAM_INT);
                $stmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(6, $branch_id, PDO::PARAM_INT);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update main account transaction");
                }
                
                // Now get all transactions for this account and currency, ordered by date
                $stmt = $pdo->prepare("SELECT id, amount, type, created_at
                                       FROM main_account_transactions
                                       WHERE main_account_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?
                                       ORDER BY created_at ASC, id ASC");
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
                        throw new Exception("Failed to update transaction balance during reordering");
                    }
                }
            } else {
                // If date hasn't changed, just update amount and description, and handle balance adjustments
                
                // Update subsequent main account transaction balances
                $updateSubsequentQuery = "UPDATE main_account_transactions
                                         SET balance = balance + ?
                                         WHERE main_account_id = ?
                                         AND currency = ?
                                         AND id > ?
                                         AND id != ?
                                         AND tenant_id = ?
                                         AND branch_id = ?";
                $updateSubsequentStmt = $pdo->prepare($updateSubsequentQuery);
                $updateSubsequentStmt->bindParam(1, $mainBalanceAdjustment, PDO::PARAM_STR);
                $updateSubsequentStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
                $updateSubsequentStmt->bindParam(3, $currency, PDO::PARAM_STR);
                $updateSubsequentStmt->bindParam(4, $mainTransactionId, PDO::PARAM_INT);
                $updateSubsequentStmt->bindParam(5, $mainTransactionId, PDO::PARAM_INT);
                $updateSubsequentStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
                $updateSubsequentStmt->bindParam(7, $branch_id, PDO::PARAM_INT);
                if (!$updateSubsequentStmt->execute()) {
                    throw new Exception("Failed to update subsequent transactions");
                }
                
                // Update the main transaction
                $newMainBalance = $mainTransaction['balance'] + $mainBalanceAdjustment;
                $stmt = $pdo->prepare("UPDATE main_account_transactions SET amount = ?, balance = ?, description = ?, created_at = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt->bindParam(1, $newAmount, PDO::PARAM_STR);
                $stmt->bindParam(2, $newMainBalance, PDO::PARAM_STR);
                $stmt->bindParam(3, $newDescription, PDO::PARAM_STR);
                $stmt->bindParam(4, $newDateTime, PDO::PARAM_STR);
                $stmt->bindParam(5, $mainTransactionId, PDO::PARAM_INT);
                $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update main account transaction");
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
        $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id, branch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $activity_log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(2, $action, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(3, $table_name, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(4, $transactionId, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(5, $old_values_json, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(6, $new_values_json, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(7, $ip_address, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(8, $user_agent, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(10, $branch_id, PDO::PARAM_INT);
        $activity_log_stmt->execute();
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Transaction updated successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 