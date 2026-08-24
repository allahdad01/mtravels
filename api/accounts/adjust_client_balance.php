<?php
session_start();
require_once('../../admin/security.php');
require_once('../../includes/db.php');

enforce_auth();

$username  = $_SESSION['name']    ?? null;
$user_id   = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
$branch_id = $_SESSION['branch_id'] ?? null;

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents("php://input"), true);
} else {
    $data = $_POST;
}

if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed.']);
    exit;
}

$clientId       = (int)($data['client_id'] ?? 0);
$balanceCurrency = $data['balance_currency'] ?? null;
$adjustmentType  = $data['adjustment_type'] ?? null; // 'credit' or 'debit'
$amount          = (float)($data['amount'] ?? 0);
$receipt         = trim($data['receipt_number'] ?? '');
$remarks         = trim($data['remarks'] ?? '');

if (empty($clientId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Client ID is required.']);
    exit;
}

if (!in_array($balanceCurrency, ['USD', 'AFS'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid balance currency is required.']);
    exit;
}

if (!in_array($adjustmentType, ['credit', 'debit'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Adjustment type must be credit or debit.']);
    exit;
}

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Amount must be greater than zero.']);
    exit;
}

$clientField = $balanceCurrency === 'USD' ? 'usd_balance' : 'afs_balance';

$stmt = $pdo->prepare("SELECT name, $clientField AS current_balance FROM clients WHERE id = ? AND tenant_id = ?");
$stmt->bindParam(1, $clientId, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->execute();
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Client not found.']);
    exit;
}

$direction = $adjustmentType === 'credit' ? 'Credit' : 'Debit';
$symbol    = $balanceCurrency === 'USD' ? '$' : '؋';
$fullRemark = "Balance Adjustment ($direction) of $symbol" . number_format($amount, 2) . " $balanceCurrency to client: {$client['name']}, processed by: $username" . ($remarks ? ". Remarks: $remarks" : '');

$pdo->beginTransaction();

try {
    $currentBalance = (float)$client['current_balance'];

    if ($adjustmentType === 'credit') {
        $newBalance = $currentBalance + $amount;
    } else {
        $newBalance = $currentBalance - $amount;
    }

    $updateStmt = $pdo->prepare("UPDATE clients SET $clientField = ? WHERE id = ? AND tenant_id = ?");
    $updateStmt->bindParam(1, $newBalance, PDO::PARAM_STR);
    $updateStmt->bindParam(2, $clientId, PDO::PARAM_INT);
    $updateStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $updateStmt->execute();

    $txnStmt = $pdo->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt, tenant_id, branch_id)
                              VALUES (?, ?, ?, ?, ?, 'fund', ?, NULL, ?, ?, ?)");
    $txnStmt->bindParam(1, $clientId, PDO::PARAM_INT);
    $txnStmt->bindParam(2, $adjustmentType, PDO::PARAM_STR);
    $txnStmt->bindParam(3, $balanceCurrency, PDO::PARAM_STR);
    $txnStmt->bindParam(4, $amount, PDO::PARAM_STR);
    $txnStmt->bindParam(5, $newBalance, PDO::PARAM_STR);
    $txnStmt->bindParam(6, $fullRemark, PDO::PARAM_STR);
    $txnStmt->bindParam(7, $receipt, PDO::PARAM_STR);
    $txnStmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
    $txnStmt->bindParam(9, $branch_id, PDO::PARAM_INT);
    $txnStmt->execute();

    $pdo->commit();

    echo json_encode([
        'success'    => true,
        'message'    => "Client balance adjusted successfully.",
        'new_balance' => $newBalance,
        'currency'   => $balanceCurrency,
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Failed to adjust balance: ' . $e->getMessage()]);
}
