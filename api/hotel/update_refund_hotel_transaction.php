<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();
// Managing refund transactions is admin/manager & finance only.
$editRoles = ['admin', 'finance', 'tenant_super_admin', 'super_admin'];
if (!in_array($_SESSION['role'] ?? '', $editRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
require_once('../../includes/db.php');

// Set headers for JSON response
header('Content-Type: application/json');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $transactionId = $_POST['transaction_id'] ?? 0;
    $bookingId = $_POST['booking_id'] ?? $_POST['refund_id'] ?? 0;
    $originalAmount = floatval($_POST['original_amount'] ?? $_POST['payment_amount'] ?? 0);
    $newAmount = floatval($_POST['payment_amount'] ?? 0);
    $newDescription = $_POST['payment_description'] ?? '';
    $newExchangeRate = isset($_POST['exchange_rate']) ? floatval($_POST['exchange_rate']) : null;

    // Validate required fields
    // Validate payment_description
    $payment_description = isset($_POST['payment_description']) ? DbSecurity::validateInput($_POST['payment_description'], 'string', ['maxlength' => 255]) : null;

    // Validate receipt_number
    $receipt_number = isset($_POST['receipt_number']) ? DbSecurity::validateInput($_POST['receipt_number'], 'string', ['maxlength' => 255]) : '';

    // Validate payment_amount
    $payment_amount = isset($_POST['payment_amount']) ? DbSecurity::validateInput($_POST['payment_amount'], 'float', ['min' => 0]) : null;

    // Validate original_amount
    $original_amount = isset($_POST['original_amount']) ? DbSecurity::validateInput($_POST['original_amount'], 'float', ['min' => 0]) : 
                      (isset($_POST['payment_amount']) ? DbSecurity::validateInput($_POST['payment_amount'], 'float', ['min' => 0]) : null);

    // Validate booking_id
    $booking_id = isset($_POST['booking_id']) ? DbSecurity::validateInput($_POST['booking_id'], 'int', ['min' => 0]) : null;

    // Validate transaction_id
    $transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;

    if (!$transactionId || !$bookingId) {
        echo json_encode(['success' => false, 'message' => 'Missing transaction or booking ID']);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();

    try {
        // Get transaction details before update
        $stmt = $pdo->prepare("SELECT amount, currency, type, main_account_id, created_at FROM main_account_transactions WHERE id = ? AND transaction_of = 'hotel_refund' AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $transactionId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            throw new Exception("Transaction not found or not a refund transaction");
        }
        $currency = $transaction['currency'];
        $type = $transaction['type'];
        $mainAccountId = $transaction['main_account_id'];
        $originalDate = $transaction['created_at'];
        
        // Calculate the difference between original and new amount
        $amountDifference = $newAmount - $originalAmount;
        
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
        
        // Update subsequent transactions' balances if amount changed
        if ($amountDifference != 0) {
            // For refunds, we're giving money back to the passenger, so it's always a debit
            // Regardless of what's stored in the type field, treat hotel_refund as debit
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
                throw new Exception("Failed to update subsequent transactions");
            }

            // Get the current balance of the transaction
            $getCurrentBalanceQuery = "SELECT balance FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $getCurrentBalanceStmt = $pdo->prepare($getCurrentBalanceQuery);
            $getCurrentBalanceStmt->bindParam(1, $transactionId, PDO::PARAM_INT);
            $getCurrentBalanceStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $getCurrentBalanceStmt->bindParam(3, $branch_id, PDO::PARAM_INT);

            if (!$getCurrentBalanceStmt->execute()) {
                throw new Exception("Failed to get current transaction balance");
            }

            $currentBalance = $getCurrentBalanceStmt->fetch(PDO::FETCH_ASSOC)['balance'];

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
                throw new Exception("Failed to update current transaction balance");
            }
        }
        
        // Update the transaction
        $updateFields = "amount = ?, description = ?, receipt = ?";
        $bindValues = [$newAmount, $newDescription, $receipt_number];
        $paramTypes = [PDO::PARAM_STR, PDO::PARAM_STR, PDO::PARAM_STR];

        if ($newExchangeRate !== null) {
            $updateFields .= ", exchange_rate = ?";
            $bindValues[] = $newExchangeRate;
            $paramTypes[] = PDO::PARAM_STR;
        }

        $stmt = $pdo->prepare("UPDATE main_account_transactions SET $updateFields WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $bindValues[] = $transactionId;
        $bindValues[] = $tenant_id;
        $bindValues[] = $branch_id;
        $paramTypes[] = PDO::PARAM_INT;
        $paramTypes[] = PDO::PARAM_INT;
        $paramTypes[] = PDO::PARAM_INT;

        foreach ($bindValues as $index => $value) {
            $stmt->bindParam($index + 1, $bindValues[$index], $paramTypes[$index]);
        }

        if (!$stmt->execute()) {
            throw new Exception("Failed to update transaction");
        }
        
        // Update main account balance if amount changed
        if ($amountDifference != 0 && $mainAccountId) {
            // For refunds, we're giving money back to the passenger, so it's always a debit
            // Decrease balance if amount increases
            $balanceAdjustment = -$amountDifference;
            
            $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->bindParam(1, $balanceAdjustment, PDO::PARAM_STR);
            $stmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);

            if (!$stmt->execute()) {
                throw new Exception("Failed to update main account balance");
            }
        }
        
        // Add activity logging
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Prepare old values
        $old_values = [
            'transaction_id' => $transactionId,
            'booking_id' => $bookingId,
            'amount' => $originalAmount,
            'description' => $transaction['description'] ?? '',
            'created_at' => $originalDate,
            'currency' => $currency,
            'type' => $type
        ];
        
        // Prepare new values
        $new_values = [
            'amount' => $newAmount,
            'description' => $newDescription,
            'receipt_number' => $receipt_number
        ];

        if ($newExchangeRate !== null) {
            $new_values['exchange_rate'] = $newExchangeRate;
        }
        
        $action = 'update';
        $table_name = 'main_account_transactions';
        $old_values = json_encode($old_values);
        $new_values = json_encode($new_values);
        
        // Insert activity log
        $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id, branch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $activity_log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(2, $action, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(3, $table_name, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(4, $transactionId, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(5, $old_values, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(6, $new_values, PDO::PARAM_STR);
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