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
require_once '../../includes/conn.php';
require_once '../includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'] ?? 0;

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
$conn->begin_transaction();

try {
    // Fetch transaction details
    $getTransaction = $conn->prepare("
        SELECT t.*, t.currency as transaction_currency, t.created_at as transaction_date,
               tw.ticket_id, tb.paid_to as main_account_id
        FROM main_account_transactions t
        JOIN ticket_weights tw ON t.reference_id = tw.id
        JOIN ticket_bookings tb ON tw.ticket_id = tb.id
        WHERE t.id = ? AND t.reference_id = ? AND t.transaction_of = 'weight' AND t.tenant_id = ?
    ");
    $getTransaction->bind_param('iii', $transactionId, $weightId, $tenant_id);
    $getTransaction->execute();
    $transactionResult = $getTransaction->get_result();
    $transaction = $transactionResult->fetch_assoc();

    if (!$transaction) {
        throw new Exception('Transaction not found');
    }

    // Determine balance column
    $balanceColumn = '';
    switch (strtoupper($transaction['currency'])) {
        case 'USD': $balanceColumn = 'usd_balance'; break;
        case 'AFS': $balanceColumn = 'afs_balance'; break;
        case 'EUR': $balanceColumn = 'euro_balance'; break;
        case 'DARHAM': $balanceColumn = 'darham_balance'; break;
        default: throw new Exception('Unsupported currency: ' . $transaction['currency']);
    }

    // Update subsequent transactions
    $updateSubsequent = $conn->prepare("
        UPDATE main_account_transactions 
        SET balance = balance - ?
        WHERE main_account_id = ? AND tenant_id = ? AND currency = ? AND created_at > ? AND id != ?
    ");
    $updateSubsequent->bind_param(
        'diissi', 
        $amount,
        $transaction['main_account_id'],
        $tenant_id,
        $transaction['currency'],
        $transaction['transaction_date'],
        $transactionId
    );
    if (!$updateSubsequent->execute()) {
        throw new Exception('Failed to update subsequent transaction balances');
    }

    // Delete main transaction
    $deleteMainTransaction = $conn->prepare("
        DELETE FROM main_account_transactions 
        WHERE id = ? AND reference_id = ? AND transaction_of = 'weight' AND tenant_id = ?
    ");
    $deleteMainTransaction->bind_param('iii', $transactionId, $weightId, $tenant_id);
    if (!$deleteMainTransaction->execute() || $deleteMainTransaction->affected_rows === 0) {
        throw new Exception('Failed to delete transaction');
    }

    // Update main account balance
    $updateBalance = $conn->prepare("
        UPDATE main_account 
        SET $balanceColumn = $balanceColumn - ?
        WHERE id = ? AND tenant_id = ?
    ");
    $updateBalance->bind_param('dii', $amount, $transaction['main_account_id'], $tenant_id);
    if (!$updateBalance->execute()) {
        throw new Exception('Failed to update main account balance');
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

    $activityLog = $conn->prepare("
        INSERT INTO activity_log 
        (user_id, tenant_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at) 
        VALUES (?, ?, 'delete', 'main_account_transactions', ?, ?, NULL, ?, ?, NOW())
    ");
    $activityLog->bind_param("iiisss", $user_id, $tenant_id, $transactionId, $old_values, $ip_address, $user_agent);
    if (!$activityLog->execute()) {
        throw new Exception('Failed to log activity');
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Transaction deleted successfully'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error in delete_weight_transaction.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close statements and connection
$getTransaction->close();
$updateSubsequent->close();
$deleteMainTransaction->close();
$updateBalance->close();
$activityLog->close();
$conn->close();

// No closing PHP tag to avoid accidental whitespace
