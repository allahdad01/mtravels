<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once __DIR__ . '/../../admin/includes/db_security.php';


require_once('../../includes/db.php');

// Validate payment_id
$payment_id = isset($_POST['payment_id']) ? DbSecurity::validateInput($_POST['payment_id'], 'int', ['min' => 0]) : null;

// Validate transaction_id
$transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $transactionId = intval($_POST['transaction_id']);
        $paymentId = intval($_POST['payment_id']);

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

        // Update subsequent transactions' balances
        $updateSubsequentStmt = $pdo->prepare("
            UPDATE main_account_transactions
            SET balance = balance - ?
            WHERE main_account_id = ?
            AND currency = ?
            AND id > ?
            AND id != ?
            AND tenant_id = ?
            AND branch_id = ?
        ");
        $updateSubsequentStmt->execute([
            $transaction['amount'],
            $transaction['main_account_id'],
            $transaction['currency'],
            $transactionId,
            $transactionId,
            $tenant_id,
            $branch_id
        ]);

        $mainCurrency = $transaction['currency'];

        // Update main account balance
        switch ($mainCurrency) {
            case 'USD': $balanceField = 'usd_balance'; break;
            case 'AFS': $balanceField = 'afs_balance'; break;
            case 'EUR': $balanceField = 'euro_balance'; break;
            case 'DARHAM': $balanceField = 'darham_balance'; break;
            case 'SAR': $balanceField = 'sar_balance'; break;
            default: $balanceField = 'afs_balance'; break;
        }
        $updateStmt = $pdo->prepare("
            UPDATE main_account
            SET $balanceField = $balanceField - ?
            WHERE id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $updateStmt->execute([$transaction['amount'], $transaction['main_account_id'], $tenant_id, $branch_id]);

        // Delete the transaction
        $deleteStmt = $pdo->prepare("
            DELETE FROM main_account_transactions
            WHERE id = ? AND reference_id = ? AND transaction_of = 'additional_payment' AND tenant_id = ? AND branch_id = ?
        ");
        $deleteStmt->execute([$transactionId, $paymentId, $tenant_id, $branch_id]);

        // Commit transaction
        $pdo->commit();
        
        // Log the activity
        $old_values = json_encode([
            'main_account_id' => $transaction['main_account_id'],
            'transaction_id' => $transactionId,
            'payment_id' => $paymentId,
            'amount' => $transaction['amount'],
            'currency' => $transaction['currency'],
            'type' => $transaction['type'],
            'created_at' => $transaction['transaction_date']
        ]);
        $new_values = json_encode([]);
        
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $activityStmt = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'delete', 'main_account_transactions', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $activityStmt->execute([$user_id, $transactionId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);
        
        echo json_encode(['success' => true, 'message' => 'Transaction deleted successfully']);

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