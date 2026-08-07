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
    $visaId = $_POST['visa_id'] ?? 0;
    $originalAmount = floatval($_POST['original_amount'] ?? 0);
    $newAmount = floatval($_POST['payment_amount'] ?? 0);
    $newDescription = $_POST['payment_description'] ?? '';

    // Validate required fields

// Validate payment_description
$payment_description = isset($_POST['payment_description']) ? DbSecurity::validateInput($_POST['payment_description'], 'string', ['maxlength' => 255]) : null;

// Validate payment_amount
$payment_amount = isset($_POST['payment_amount']) ? DbSecurity::validateInput($_POST['payment_amount'], 'float', ['min' => 0]) : null;

// Validate original_amount
$original_amount = isset($_POST['original_amount']) ? DbSecurity::validateInput($_POST['original_amount'], 'float', ['min' => 0]) : null;

// Validate visa_id
$visa_id = isset($_POST['visa_id']) ? DbSecurity::validateInput($_POST['visa_id'], 'int', ['min' => 0]) : null;
$payment_exchange_rate = isset($_POST['payment_exchange_rate']) ? DbSecurity::validateInput($_POST['payment_exchange_rate'], 'float', ['min' => 0]) : null;
// Validate transaction_id
$transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;
// Validate receipt_number (optional)
$receipt_number = isset($_POST['receipt_number']) ? DbSecurity::validateInput($_POST['receipt_number'], 'string', ['maxlength' => 100]) : null;
    if (!$transactionId || !$visaId) {
        echo json_encode(['success' => false, 'message' => 'Missing transaction or visa ID']);
        exit;
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Get transaction details before update
        $stmt = $pdo->prepare("SELECT amount, currency, type, main_account_id, created_at FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $transactionId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            throw new PDOException("Transaction not found");
        }

        $currency = $transaction['currency'];
        $type = $transaction['type'];
        $mainAccountId = $transaction['main_account_id'];

        // Store old values for activity log
        $oldValues = json_encode([
            'transaction_id' => $transactionId,
            'visa_id' => $visaId,
            'amount' => $transaction['amount'],
            'currency' => $currency,
            'type' => $type,
            'created_at' => $transaction['created_at']
        ]);

        // Calculate the difference between original and new amount
        $amountDifference = $newAmount - $originalAmount;

        // Map currency codes to the correct database field names
        $currencyFieldMap = [
            'USD' => 'usd_balance',
            'AFS' => 'afs_balance',
            'EUR' => 'euro_balance',
            'DARHAM' => 'darham_balance',
            'SAR' => 'sar_balance'
        ];

        // Check if the currency is in our map
        if (!isset($currencyFieldMap[$currency])) {
            throw new PDOException("Unknown currency: " . $currency);
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
                                      AND currency = ?
                                      AND id > ?
                                      AND id != ? AND tenant_id = ? AND branch_id = ?";
            $updateSubsequentStmt = $pdo->prepare($updateSubsequentQuery);
            $updateSubsequentStmt->bindParam(1, $balanceAdjustment, PDO::PARAM_STR);
            $updateSubsequentStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(3, $currency, PDO::PARAM_STR);
            $updateSubsequentStmt->bindParam(4, $transactionId, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(5, $transactionId, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(7, $branch_id, PDO::PARAM_INT);

            if (!$updateSubsequentStmt->execute()) {
                throw new PDOException("Failed to update subsequent transactions: " . $updateSubsequentStmt->error);
            }

            // Get the current balance of the transaction
            $getCurrentBalanceQuery = "SELECT balance FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $getCurrentBalanceStmt = $pdo->prepare($getCurrentBalanceQuery);
            $getCurrentBalanceStmt->bindParam(1, $transactionId, PDO::PARAM_INT);
            $getCurrentBalanceStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $getCurrentBalanceStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $getCurrentBalanceStmt->execute();
            $balanceResult = $getCurrentBalanceStmt->fetch(PDO::FETCH_ASSOC);
            $currentBalance = $balanceResult['balance'];

            // Calculate new balance for this transaction
            $newBalance = $currentBalance + (($type == 'credit') ? $amountDifference : -$amountDifference);

            // Update the balance of the current transaction
            $updateCurrentBalanceQuery = "UPDATE main_account_transactions SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $updateCurrentBalanceStmt = $pdo->prepare($updateCurrentBalanceQuery);
            $updateCurrentBalanceStmt->bindParam(1, $newBalance, PDO::PARAM_STR);
            $updateCurrentBalanceStmt->bindParam(2, $transactionId, PDO::PARAM_INT);
            $updateCurrentBalanceStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $updateCurrentBalanceStmt->bindParam(4, $branch_id, PDO::PARAM_INT);

            if (!$updateCurrentBalanceStmt->execute()) {
                throw new PDOException("Failed to update current transaction balance: " . $updateCurrentBalanceStmt->error);
            }
        }

        // Update the transaction amount in main_account_transactions table
        $updateTransactionQuery = "UPDATE main_account_transactions SET amount = ?, description = ?, exchange_rate = ?, receipt = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $updateTransactionStmt = $pdo->prepare($updateTransactionQuery);
        $updateTransactionStmt->bindParam(1, $newAmount, PDO::PARAM_STR);
        $updateTransactionStmt->bindParam(2, $newDescription, PDO::PARAM_STR);
        $updateTransactionStmt->bindParam(3, $payment_exchange_rate, PDO::PARAM_STR);
        $updateTransactionStmt->bindParam(4, $receipt_number, PDO::PARAM_STR);
        $updateTransactionStmt->bindParam(5, $transactionId, PDO::PARAM_INT);
        $updateTransactionStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $updateTransactionStmt->bindParam(7, $branch_id, PDO::PARAM_INT);

        if (!$updateTransactionStmt->execute()) {
            throw new PDOException("Failed to update transaction: " . $updateTransactionStmt->error);
        }

        // Update main account balance if amount changed
        if ($amountDifference != 0 && $mainAccountId) {
            // For credit transactions (received payments), increase balance if amount increases
            // For debit transactions (paid out), decrease balance if amount increases
                $balanceAdjustment = ($type == 'credit') ? $amountDifference : -$amountDifference;

            $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->bindParam(1, $balanceAdjustment, PDO::PARAM_STR);
            $stmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);

            if (!$stmt->execute()) {
                throw new PDOException("Failed to update main account balance: " . $stmt->error);
            }
        }

        // Create new values for activity log
        $newValues = json_encode([
            'transaction_id' => $transactionId,
            'visa_id' => $visaId,
            'amount' => $newAmount,
            'description' => $newDescription
        ]);

        // Get user information for activity log
        $userId = $_SESSION['user_id'];
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        // Insert activity log
        $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id, branch_id)
                                  VALUES (?, ?, ?, 'update', 'main_account_transactions', ?, ?, ?, NOW(), ?, ?)");
        $logStmt->bindParam(1, $userId, PDO::PARAM_INT);
        $logStmt->bindParam(2, $ipAddress, PDO::PARAM_STR);
        $logStmt->bindParam(3, $userAgent, PDO::PARAM_STR);
        $logStmt->bindParam(4, $transactionId, PDO::PARAM_INT);
        $logStmt->bindParam(5, $oldValues, PDO::PARAM_STR);
        $logStmt->bindParam(6, $newValues, PDO::PARAM_STR);
        $logStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $logStmt->bindParam(8, $branch_id, PDO::PARAM_INT);

        if (!$logStmt->execute()) {
            // Just log the error, don't throw exception to allow transaction to complete
            error_log("Failed to insert activity log: " . $logStmt->error);
        }

        // Commit transaction
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Transaction updated successfully']);
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>