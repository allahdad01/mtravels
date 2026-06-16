<?php

require_once '../../admin/security.php';
enforce_auth();

$data = json_decode(file_get_contents('php://input'), true);
if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once('../../includes/db.php');

$username = $_SESSION['name'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

$accountId = (int)($data['main_account_id'] ?? 0);
$currency = strtoupper(trim($data['currency'] ?? ''));
$amount = (float)($data['amount'] ?? 0);
$receiptNumber = trim($data['receipt_number'] ?? '');
$remarks = trim($data['remarks'] ?? '');

if (!$accountId || !$currency || $amount <= 0 || !$receiptNumber) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$balanceField = ($currency === 'EUR') ? 'euro_balance' : strtolower($currency) . '_balance';

$accountStmt = $pdo->prepare("SELECT name, {$balanceField} FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
$accountStmt->bindParam(1, $accountId, PDO::PARAM_INT);
$accountStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$accountStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$accountStmt->execute();
$account = $accountStmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    echo json_encode(['success' => false, 'message' => 'Account not found']);
    exit;
}

$currentBalance = (float)$account[$balanceField];
if ($currentBalance < $amount) {
    echo json_encode(['success' => false, 'message' => 'Insufficient balance']);
    exit;
}

$pdo->beginTransaction();
try {
    $updateStmt = $pdo->prepare("UPDATE main_account SET {$balanceField} = {$balanceField} - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $updateStmt->bindParam(1, $amount, PDO::PARAM_STR);
    $updateStmt->bindParam(2, $accountId, PDO::PARAM_INT);
    $updateStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $updateStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update account balance");
    }

    $newBalance = $currentBalance - $amount;
    $accountName = $account['name'];
    $description = "Withdrawal from {$accountName} ({$currency}), processed by: {$username}, Receipt: {$receiptNumber}, Remarks: {$remarks}";

    $txnStmt = $pdo->prepare("
        INSERT INTO main_account_transactions
            (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id, branch_id)
        VALUES (?, 'debit', ?, ?, ?, 'withdraw_fund', ?, ?, ?, ?, ?)
    ");
    $txnStmt->bindParam(1, $accountId, PDO::PARAM_INT);
    $txnStmt->bindParam(2, $amount, PDO::PARAM_STR);
    $txnStmt->bindParam(3, $currency, PDO::PARAM_STR);
    $txnStmt->bindParam(4, $description, PDO::PARAM_STR);
    $txnStmt->bindParam(5, $user_id, PDO::PARAM_INT);
    $txnStmt->bindParam(6, $newBalance, PDO::PARAM_STR);
    $txnStmt->bindParam(7, $receiptNumber, PDO::PARAM_STR);
    $txnStmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
    $txnStmt->bindParam(9, $branch_id, PDO::PARAM_INT);
    if (!$txnStmt->execute()) {
        throw new Exception("Failed to log transaction");
    }
    $lastInsertId = $pdo->lastInsertId();

    $notifMsg = "Main account withdrawal: {$amount} {$currency} from {$accountName}, processed by: {$username}, Receipt: {$receiptNumber}";
    $notifStmt = $pdo->prepare("
        INSERT INTO notifications (transaction_id, transaction_type, message, status, created_at, tenant_id, branch_id)
        VALUES (?, 'withdraw_fund', ?, 'Unread', NOW(), ?, ?)
    ");
    $notifStmt->bindParam(1, $lastInsertId, PDO::PARAM_INT);
    $notifStmt->bindParam(2, $notifMsg, PDO::PARAM_STR);
    $notifStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $notifStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $notifStmt->execute();

    $pdo->commit();

    $old_values = json_encode(['balance' => $currentBalance]);
    $new_values = json_encode([
        'balance' => $newBalance,
        'amount' => $amount,
        'currency' => $currency,
        'remarks' => $remarks,
        'receipt' => $receiptNumber
    ]);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $logStmt = $pdo->prepare("
        INSERT INTO activity_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, 'withdraw', 'main_account', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $logStmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $logStmt->bindParam(2, $accountId, PDO::PARAM_INT);
    $logStmt->bindParam(3, $old_values, PDO::PARAM_STR);
    $logStmt->bindParam(4, $new_values, PDO::PARAM_STR);
    $logStmt->bindParam(5, $ip, PDO::PARAM_STR);
    $logStmt->bindParam(6, $ua, PDO::PARAM_STR);
    $logStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $logStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
    $logStmt->execute();

    echo json_encode(['success' => true, 'message' => 'Withdrawal successful']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
}
