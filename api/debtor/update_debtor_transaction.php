<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Include language helper
require_once '../../includes/language_helpers.php';

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
    $referenceNumber = $_POST['reference_number'] ?? '';
    
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
        
        $stmt = $pdo->prepare("UPDATE debtors SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $newDebtorBalance, PDO::PARAM_STR);
        $stmt->bindParam(2, $debtorId, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Update the debtor transaction
        $stmt = $pdo->prepare("UPDATE debtor_transactions SET amount = ?, description = ?, reference_number = ?, payment_date = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $newAmount, PDO::PARAM_STR);
        $stmt->bindParam(2, $newDescription, PDO::PARAM_STR);
        $stmt->bindParam(3, $referenceNumber, PDO::PARAM_STR);
        $stmt->bindParam(4, $newDate, PDO::PARAM_STR);
        $stmt->bindParam(5, $transactionId, PDO::PARAM_INT);
        $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
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
            $stmt = $pdo->prepare("UPDATE main_account_transactions SET amount = ?, balance = ?, description = ?, receipt = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->bindParam(1, $newAmount, PDO::PARAM_STR);
            $stmt->bindParam(2, $newMainBalance, PDO::PARAM_STR);
            $stmt->bindParam(3, $newDescription, PDO::PARAM_STR);
            $stmt->bindParam(4, $referenceNumber, PDO::PARAM_STR);
            $stmt->bindParam(5, $mainTransactionId, PDO::PARAM_INT);
            $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update main account transaction");
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
            'reference_number' => $transaction['reference_number'] ?? '',
            'transaction_type' => $transactionType,
            'currency' => $currency
        ];
        
        // Prepare new values
        $new_values = [
            'amount' => $newAmount,
            'description' => $newDescription,
            'reference_number' => $referenceNumber
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
        
        // Set success message and redirect
        $_SESSION['success_message'] = 'Transaction updated successfully!';
        $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'debtors.php';
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        $_SESSION['error_message'] = 'Error updating transaction: ' . $e->getMessage();
        $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'debtors.php';
        header('Location: ' . $redirect_url);
        exit();
    }
} else {
    $_SESSION['error_message'] = 'Invalid request method';
    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'debtors.php';
    header('Location: ' . $redirect_url);
    exit();
}
?> 