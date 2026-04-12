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

// Database connection
require_once '../../includes/db.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get form data
    $transactionId = $_POST['transaction_id'] ?? 0;
    $ticketId = $_POST['ticket_id'] ?? 0;
    $originalAmount = floatval($_POST['original_amount'] ?? 0);
    $newAmount = floatval($_POST['payment_amount'] ?? 0);
    $newDate = $_POST['payment_date'] ?? '';
    $newDescription = $_POST['payment_description'] ?? '';
    $exchange_rate = floatval($_POST['payment_exchange_rate'] ?? 0);

    // Clean description by removing any existing exchange rate embedding
    $newDescription = preg_replace('/\s*\(Exchange Rate: [0-9.]+\)/', '', $newDescription);
    $newDescription = trim($newDescription);
    // Validate required fields

// Validate payment_description
$payment_description = isset($_POST['payment_description']) ? DbSecurity::validateInput($_POST['payment_description'], 'string', ['maxlength' => 255]) : null;

// Validate payment_date
$payment_date = isset($_POST['payment_date']) ? DbSecurity::validateInput($_POST['payment_date'], 'date') : null;

// Validate payment_amount
$payment_amount = isset($_POST['payment_amount']) ? DbSecurity::validateInput($_POST['payment_amount'], 'float', ['min' => 0]) : null;

// Validate original_amount
$original_amount = isset($_POST['original_amount']) ? DbSecurity::validateInput($_POST['original_amount'], 'float', ['min' => 0]) : null;

// Validate ticket_id
$ticket_id = isset($_POST['ticket_id']) ? DbSecurity::validateInput($_POST['ticket_id'], 'int', ['min' => 0]) : null;

// Validate transaction_id
$transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;
    if (!$transactionId || !$ticketId) {
        echo json_encode(['success' => false, 'message' => 'Missing transaction or ticket ID']);
        exit;
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

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
        $originalDate = $transaction['created_at'];

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
                                     AND id != ?
                                     AND tenant_id = ?
                                     AND branch_id = ?";
            $updateSubsequentStmt = $pdo->prepare($updateSubsequentQuery);
            $updateSubsequentStmt->bindParam(1, $balanceAdjustment, PDO::PARAM_STR);
            $updateSubsequentStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(3, $currency, PDO::PARAM_STR);
            $updateSubsequentStmt->bindParam(4, $transactionId, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(5, $transactionId, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(7, $branch_id, PDO::PARAM_INT);
            $updateSubsequentStmt->execute();

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
            $updateCurrentBalanceStmt->execute();
        }

        // Update the transaction
        $stmt = $pdo->prepare("UPDATE main_account_transactions SET amount = ?, description = ?, created_at = ?, exchange_rate = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $newAmount, PDO::PARAM_STR);
        $stmt->bindParam(2, $newDescription, PDO::PARAM_STR);
        $stmt->bindParam(3, $newDate, PDO::PARAM_STR);
        $stmt->bindParam(4, $exchange_rate, PDO::PARAM_STR);
        $stmt->bindParam(5, $transactionId, PDO::PARAM_INT);
        $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

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
            $stmt->execute();


        }

        // If date changed, we need to reorder transactions and recalculate all balances
        if ($newDate != $originalDate) {
            // Get all transactions for this account and currency, ordered by date
            $stmt = $pdo->prepare("SELECT id, amount, type, created_at
                                   FROM main_account_transactions
                                   WHERE main_account_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?
                                   ORDER BY created_at ASC, id ASC");
            $stmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
            $stmt->bindParam(2, $currency, PDO::PARAM_STR);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
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
                $updateStmt->execute();
            }
        }

        // Add activity logging
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Prepare old values
        $old_values = [
            'transaction_id' => $transactionId,
            'ticket_id' => $ticketId,
            'amount' => $originalAmount,
            'type' => $type,
            'currency' => $currency,
            'created_at' => $originalDate,
            'description' => $transaction['description'] ?? ''
        ];

        // Prepare new values
        $new_values = [
            'amount' => $newAmount,
            'description' => $newDescription,
            'created_at' => $newDate
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
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>