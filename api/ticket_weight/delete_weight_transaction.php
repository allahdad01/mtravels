<?php
// Ensure no whitespace before <?php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only show fatal errors (prevent notices/warnings from breaking JSON)
error_reporting(E_ERROR | E_PARSE);
header('Content-Type: application/json');

// Include database connection and security
require_once '../../includes/db.php';
require_once '../../admin/includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'] ?? 0;
$branch_id = $_SESSION['branch_id'] ?? 0;

// Validate required POST parameters
if (!isset($_POST['transaction_id'], $_POST['weight_id'], $_POST['amount'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

// Sanitize input
$transactionId = DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]);
$weightId = DbSecurity::validateInput($_POST['weight_id'], 'int', ['min' => 0]);
$amount = DbSecurity::validateInput($_POST['amount'], 'float', ['min' => 0]);

// Start transaction
$pdo->beginTransaction();

try {
    // Fetch transaction details
    $getTransaction = $pdo->prepare("
        SELECT t.*, t.currency as transaction_currency, t.created_at as transaction_date,
               tw.ticket_id, tb.paid_to as main_account_id
        FROM main_account_transactions t
        JOIN ticket_weights tw ON t.reference_id = tw.id AND tw.tenant_id = ? AND tw.branch_id = ?
        JOIN ticket_bookings tb ON tw.ticket_id = tb.id AND tb.tenant_id = ? AND tb.branch_id = ?
        WHERE t.id = ? AND t.reference_id = ? AND t.transaction_of = 'weight' AND t.tenant_id = ? AND t.branch_id = ?
    ");
    $getTransaction->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $getTransaction->bindParam(2, $branch_id, PDO::PARAM_INT);
    $getTransaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $getTransaction->bindParam(4, $branch_id, PDO::PARAM_INT);
    $getTransaction->bindParam(5, $transactionId, PDO::PARAM_INT);
    $getTransaction->bindParam(6, $weightId, PDO::PARAM_INT);
    $getTransaction->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $getTransaction->bindParam(8, $branch_id, PDO::PARAM_INT);
    $getTransaction->execute();
    $transaction = $getTransaction->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new PDOException('Transaction not found');
    }

    // Determine balance column
    $balanceColumn = '';
    switch (strtoupper($transaction['currency'])) {
        case 'USD': $balanceColumn = 'usd_balance'; break;
        case 'AFS': $balanceColumn = 'afs_balance'; break;
        case 'EUR': $balanceColumn = 'euro_balance'; break;
        case 'DARHAM': $balanceColumn = 'darham_balance'; break;
        default: throw new PDOException('Unsupported currency: ' . $transaction['currency']);
    }

    // Update subsequent transactions
    $updateSubsequent = $pdo->prepare("
        UPDATE main_account_transactions
        SET balance = balance - ?
        WHERE main_account_id = ? AND tenant_id = ? AND branch_id = ? AND currency = ? AND created_at > ? AND id != ?
    ");
    $updateSubsequent->bindParam(1, $amount, PDO::PARAM_STR);
    $updateSubsequent->bindParam(2, $transaction['main_account_id'], PDO::PARAM_INT);
    $updateSubsequent->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $updateSubsequent->bindParam(4, $branch_id, PDO::PARAM_INT);
    $updateSubsequent->bindParam(5, $transaction['currency'], PDO::PARAM_STR);
    $updateSubsequent->bindParam(6, $transaction['transaction_date'], PDO::PARAM_STR);
    $updateSubsequent->bindParam(7, $transactionId, PDO::PARAM_INT);
    if (!$updateSubsequent->execute()) {
        throw new PDOException('Failed to update subsequent transaction balances');
    }

    // Delete main transaction
    $deleteMainTransaction = $pdo->prepare("
        DELETE FROM main_account_transactions
        WHERE id = ? AND reference_id = ? AND transaction_of = 'weight' AND tenant_id = ? AND branch_id = ?
    ");
    $deleteMainTransaction->bindParam(1, $transactionId, PDO::PARAM_INT);
    $deleteMainTransaction->bindParam(2, $weightId, PDO::PARAM_INT);
    $deleteMainTransaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $deleteMainTransaction->bindParam(4, $branch_id, PDO::PARAM_INT);
    if (!$deleteMainTransaction->execute() || $deleteMainTransaction->rowCount() === 0) {
        throw new PDOException('Failed to delete transaction');
    }

    // Update main account balance
    $updateBalance = $pdo->prepare("
        UPDATE main_account
        SET $balanceColumn = $balanceColumn - ?
        WHERE id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $updateBalance->bindParam(1, $amount, PDO::PARAM_STR);
    $updateBalance->bindParam(2, $transaction['main_account_id'], PDO::PARAM_INT);
    $updateBalance->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $updateBalance->bindParam(4, $branch_id, PDO::PARAM_INT);
    if (!$updateBalance->execute()) {
        throw new PDOException('Failed to update main account balance');
    }

    // Log activity
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $old_values = json_encode([
        'transaction_id' => $transactionId,
        'weight_id' => $weightId,
        'amount' => $amount,
        'currency' => $transaction['currency'],
        'main_account_id' => $transaction['main_account_id'],
        'created_at' => $transaction['transaction_date']
    ]);

    $activityLog = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, tenant_id, branch_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, 'delete', 'main_account_transactions', ?, ?, NULL, ?, ?, NOW())
    ");
    $activityLog->bindParam(1, $user_id, PDO::PARAM_INT);
    $activityLog->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $activityLog->bindParam(3, $branch_id, PDO::PARAM_INT);
    $activityLog->bindParam(4, $transactionId, PDO::PARAM_INT);
    $activityLog->bindParam(5, $old_values, PDO::PARAM_STR);
    $activityLog->bindParam(6, $ip_address, PDO::PARAM_STR);
    $activityLog->bindParam(7, $user_agent, PDO::PARAM_STR);
    if (!$activityLog->execute()) {
        throw new PDOException('Failed to log activity');
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Transaction deleted successfully'
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// No closing PHP tag to avoid accidental whitespace
?>
