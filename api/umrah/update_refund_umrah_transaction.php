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
require_once('../../includes/db.php');

// Set headers for JSON response
header('Content-Type: application/json');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $transactionId = $_POST['transaction_id'] ?? 0;
    // Handle both refund_id and refund_id for backward compatibility
    $bookingId = $_POST['booking_id'] ?? $_POST['booking_id'] ?? 0;
    $originalAmount = floatval($_POST['original_amount'] ?? $_POST['payment_amount'] ?? 0);
    $newAmount = floatval($_POST['payment_amount'] ?? 0);
    $exchange_rate = isset($_POST['exchange_rate']) ? floatval($_POST['exchange_rate']) : null;
    $newDescription = $_POST['payment_description'] ?? '';

    // Validate required fields
    // Validate payment_description
    $payment_description = isset($_POST['payment_description']) ? DbSecurity::validateInput($_POST['payment_description'], 'string', ['maxlength' => 255]) : null;

    // Validate payment_amount
    $payment_amount = isset($_POST['payment_amount']) ? DbSecurity::validateInput($_POST['payment_amount'], 'float', ['min' => 0]) : null;

    // Validate original_amount
    $original_amount = isset($_POST['original_amount']) ? DbSecurity::validateInput($_POST['original_amount'], 'float', ['min' => 0]) :
                      (isset($_POST['payment_amount']) ? DbSecurity::validateInput($_POST['payment_amount'], 'float', ['min' => 0]) : null);

    // Validate refund_id - handle both refund_id and refund_id
    $booking_id = isset($_POST['booking_id']) ? DbSecurity::validateInput($_POST['booking_id'], 'int', ['min' => 0]) : null;

    // Validate transaction_id
    $transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;
    $exchange_rate = isset($_POST['exchange_rate']) ? DbSecurity::validateInput($_POST['exchange_rate'], 'float', ['min' => 0]) : null;
    if (!$transactionId || !$bookingId) {
        echo json_encode(['success' => false, 'message' => 'Missing transaction or booking ID']);
        exit;
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Get transaction details before update
        $stmt = $pdo->prepare("SELECT amount, currency, type, main_account_id, created_at, receipt, exchange_rate FROM main_account_transactions WHERE id = ? AND transaction_of = 'umrah_refund' AND tenant_id = ? AND branch_id = ?");
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
                throw new Exception("Failed to update subsequent transactions: " . $updateSubsequentStmt->error);
            }

            // Get the current balance of the transaction
            $getCurrentBalanceQuery = "SELECT balance FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $getCurrentBalanceStmt = $pdo->prepare($getCurrentBalanceQuery);
            $getCurrentBalanceStmt->bindParam(1, $transactionId, PDO::PARAM_INT);
            $getCurrentBalanceStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $getCurrentBalanceStmt->bindParam(3, $branch_id, PDO::PARAM_INT);

            if (!$getCurrentBalanceStmt->execute()) {
                throw new Exception("Failed to get current transaction balance: " . $getCurrentBalanceStmt->error);
            }

            $balanceResult = $getCurrentBalanceStmt->fetch(PDO::FETCH_ASSOC);
            $currentBalance = $balanceResult['balance'];

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
                throw new Exception("Failed to update current transaction balance: " . $updateCurrentBalanceStmt->error);
            }
        }

        // Get receipt number
        $receipt_number = isset($_POST['receipt_number']) ? trim($_POST['receipt_number']) : '';

        // Update the transaction
        $stmt = $pdo->prepare("UPDATE main_account_transactions SET amount = ?, description = ?, exchange_rate = ?, receipt = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $newAmount, PDO::PARAM_STR);
        $stmt->bindParam(2, $newDescription, PDO::PARAM_STR);
        $stmt->bindParam(3, $exchange_rate, PDO::PARAM_STR);
        $stmt->bindParam(4, $receipt_number, PDO::PARAM_STR);
        $stmt->bindParam(5, $transactionId, PDO::PARAM_INT);
        $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new Exception("Failed to update transaction: " . $stmt->error);
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
                throw new Exception("Failed to update main account balance: " . $stmt->error);
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
            'currency' => $currency,
            'type' => $type,
            'receipt' => $transaction['receipt'] ?? '',
            'exchange_rate' => $transaction['exchange_rate'] ?? ''
        ];

        // Prepare new values
        $new_values = [
            'amount' => $newAmount,
            'description' => $newDescription,
            'receipt' => $receipt_number,
            'exchange_rate' => $exchange_rate
        ];

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
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>