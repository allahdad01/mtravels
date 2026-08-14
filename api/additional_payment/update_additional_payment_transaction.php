<?php
session_start();
// Include database security module for input validation
require_once __DIR__ . '/../../admin/includes/db_security.php';

// Include security module
require_once __DIR__ . '/../../admin/security.php';

// Enforce authentication
enforce_auth();

// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once('../../includes/db.php');

// Validate receipt
$receipt = isset($_POST['receipt_number']) ? DbSecurity::validateInput($_POST['receipt_number'], 'string', ['maxlength' => 255]) : null;

// Validate payment_time
$payment_time = isset($_POST['payment_time']) ? DbSecurity::validateInput($_POST['payment_time'], 'string', ['maxlength' => 255]) : null;

// Validate payment_date
$payment_date = isset($_POST['payment_date']) ? DbSecurity::validateInput($_POST['payment_date'], 'date') : null;

// Validate payment_description
$payment_description = isset($_POST['payment_description']) ? DbSecurity::validateInput($_POST['payment_description'], 'string', ['maxlength' => 255]) : null;

// Validate payment_amount
$payment_amount = isset($_POST['payment_amount']) ? DbSecurity::validateInput($_POST['payment_amount'], 'float', ['min' => 0]) : null;

// Validate payment_id
$payment_id = isset($_POST['payment_id']) ? DbSecurity::validateInput($_POST['payment_id'], 'int', ['min' => 0]) : null;

// Validate transaction_id
$transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $transactionId = intval($_POST['transaction_id']);
        $paymentId = intval($_POST['payment_id']);
        $amount = floatval($_POST['payment_amount']);
        $description = $_POST['payment_description'];

        // Begin transaction
        $pdo->beginTransaction();

        // Get transaction details
        $stmt = $pdo->prepare("
            SELECT t.*, t.currency as transaction_currency, t.created_at as transaction_date
            FROM main_account_transactions t
            WHERE t.id = ? AND t.reference_id = ? AND t.transaction_of = 'additional_payment' AND t.tenant_id = ? AND t.branch_id = ?
        ");
        $stmt->execute([$transactionId, $paymentId, $tenant_id, $branch_id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            throw new Exception('Transaction not found');
        }

        // Calculate the difference between original and new amount
        $amountDifference = $amount - $transaction['amount'];

        // Update subsequent transactions' balances if amount changed
        if ($amountDifference != 0) {
            // For credit transactions, subsequent balances increase when amount increases
            $balanceAdjustment = $amountDifference;
            
            $updateSubsequentStmt = $pdo->prepare("
                UPDATE main_account_transactions
                SET balance = balance + ?
                WHERE main_account_id = ?
                AND currency = ?
                AND id > ?
                AND id != ?
                AND tenant_id = ?
                AND branch_id = ?
            ");
            $updateSubsequentStmt->execute([
                $balanceAdjustment,
                $transaction['main_account_id'],
                $transaction['currency'],
                $transactionId,
                $transactionId,
                $tenant_id,
                $branch_id
            ]);

            // Update the balance of the current transaction
            $newBalance = $transaction['balance'] + $balanceAdjustment;
            $updateCurrentBalanceStmt = $pdo->prepare("
                UPDATE main_account_transactions
                SET balance = ?
                WHERE id = ?
                AND tenant_id = ?
                AND branch_id = ?
            ");
            $updateCurrentBalanceStmt->execute([$newBalance, $transactionId, $tenant_id, $branch_id]);
        }

        // Update the transaction
        $stmt = $pdo->prepare("
            UPDATE main_account_transactions
            SET amount = ?, description = ?, receipt = ?
            WHERE id = ?
            AND tenant_id = ?
            AND branch_id = ?
        ");
        $stmt->execute([$amount, $description, $receipt, $transactionId, $tenant_id, $branch_id]);

        // Update main account balance if amount changed
        if ($amountDifference != 0) {
            $balanceFields = [
                'USD' => 'usd_balance',
                'AFS' => 'afs_balance',
                'EUR' => 'euro_balance',
                'DARHAM' => 'darham_balance',
                'SAR' => 'sar_balance',
            ];
            $balanceField = $balanceFields[$transaction['currency']] ?? null;
            if (!$balanceField) {
                throw new Exception("Unknown currency: " . $transaction['currency']);
            }
            $updateStmt = $pdo->prepare("
                UPDATE main_account
                SET $balanceField = $balanceField + ?
                WHERE id = ?
                AND tenant_id = ?
                AND branch_id = ?
            ");
            $updateStmt->execute([$amountDifference, $transaction['main_account_id'], $tenant_id, $branch_id]);
        }

        // Add activity logging
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Prepare old values
        $old_values = [
            'transaction_id' => $transactionId,
            'payment_id' => $paymentId,
            'amount' => $transaction['amount'],
            'description' => $transaction['description'],
            'created_at' => $transaction['created_at'],
            'receipt' => $transaction['receipt'] ?? ''
        ];
        
        // Prepare new values
        $new_values = [
            'amount' => $amount,
            'description' => $description,
            'receipt' => $receipt
        ];
        
        // Insert activity log
        $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id, branch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $activity_log_stmt->execute([
            $user_id,
            'update',
            'main_account_transactions',
            $transactionId,
            json_encode($old_values),
            json_encode($new_values),
            $ip_address,
            $user_agent,
            $tenant_id,
            $branch_id
        ]);

        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Transaction updated successfully']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 