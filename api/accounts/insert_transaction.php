<?php
session_start();

header('Content-Type: application/json');

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset(); session_destroy();
    echo json_encode(['success' => false, 'message' => 'Session expired']); exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit();
}

require_once('../../includes/db.php');

$username  = $_SESSION['name']    ?? null;
$user_id   = $_SESSION['user_id'] ?? null;

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents("php://input"), true);
} else {
    $data = $_POST;
}

$csrfToken = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!$csrfToken || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed.']);
    exit;
}

// Accept tenant_id/branch_id from form data (super admin) or fall back to session
$tenant_id = (int)($data['tenant_id'] ?? $_SESSION['tenant_id'] ?? 0);
$branch_id = (int)($data['branch_id'] ?? $_SESSION['branch_id'] ?? 0);

$entityType = $data['entity_type'] ?? null;
$entityId   = (int)($data['entity_id'] ?? 0);
$txnType    = $data['transaction_type'] ?? null;
$currency   = strtoupper(trim($data['currency'] ?? ''));
$amount     = (float)($data['amount'] ?? 0);
$receipt    = trim($data['receipt'] ?? '');
$remarks    = trim($data['remarks'] ?? '');
$refTxnId   = !empty($data['reference_transaction_id']) ? (int)$data['reference_transaction_id'] : null;

if (empty($entityType) || !in_array($entityType, ['client', 'supplier', 'main_account'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid entity type.']);
    exit;
}

if ($entityId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Entity ID is required.']);
    exit;
}

if (!in_array($txnType, ['credit', 'debit'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Transaction type must be credit or debit.']);
    exit;
}

$validCurrencies = ['USD', 'AFS', 'EUR', 'DARHAM', 'SAR'];
if (!in_array($currency, $validCurrencies)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid currency.']);
    exit;
}

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Amount must be greater than zero.']);
    exit;
}

$pdo->beginTransaction();

try {
    if ($entityType === 'client') {
        insertClientTransaction($pdo, $entityId, $txnType, $currency, $amount, $receipt, $remarks, $refTxnId, $tenant_id, $branch_id, $user_id, $username);
    } elseif ($entityType === 'supplier') {
        insertSupplierTransaction($pdo, $entityId, $txnType, $currency, $amount, $receipt, $remarks, $refTxnId, $tenant_id, $branch_id, $user_id, $username);
    } elseif ($entityType === 'main_account') {
        insertMainAccountTransaction($pdo, $entityId, $txnType, $currency, $amount, $receipt, $remarks, $refTxnId, $tenant_id, $branch_id, $user_id, $username);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => ucfirst($txnType) . ' transaction inserted successfully.']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
}

// ──────────────────────────────────────────────────────────────────────
// CLIENT TRANSACTION
// ──────────────────────────────────────────────────────────────────────
function insertClientTransaction($pdo, $clientId, $txnType, $currency, $amount, $receipt, $remarks, $refTxnId, $tenant_id, $branch_id, $user_id, $username) {
    $clientField = $currency === 'USD' ? 'usd_balance' : 'afs_balance';

    $stmt = $pdo->prepare("SELECT name, $clientField AS current_balance FROM clients WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$clientId, $tenant_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        throw new Exception('Client not found.');
    }

    $direction = $txnType === 'credit' ? 'Credit' : 'Debit';
    $sym = $currency === 'USD' ? '$' : '؋';
    $fullRemark = "Balance $direction of $sym" . number_format($amount, 2) . " $currency to client: {$client['name']}, processed by: $username" . ($remarks ? ". Remarks: $remarks" : '');

    $currentBalance = (float)$client['current_balance'];
    $newBalance = $txnType === 'credit' ? $currentBalance + $amount : $currentBalance - $amount;

    $updateStmt = $pdo->prepare("UPDATE clients SET $clientField = ? WHERE id = ? AND tenant_id = ?");
    $updateStmt->execute([$newBalance, $clientId, $tenant_id]);

    if ($refTxnId) {
        $insertBalance = $txnType === 'credit' ? $amount : -$amount;

        $txnStmt = $pdo->prepare("INSERT INTO client_transactions (id, client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt, tenant_id, branch_id)
                                  VALUES (?, ?, ?, ?, ?, ?, 'fund', ?, NULL, ?, ?, ?)");
        $txnStmt->execute([$refTxnId, $clientId, $txnType, $currency, $amount, $insertBalance, $fullRemark, $receipt, $tenant_id, $branch_id]);

        $shift = $txnType === 'credit' ? $amount : -$amount;
        $shiftStmt = $pdo->prepare("UPDATE client_transactions SET balance = balance + ? WHERE client_id = ? AND currency = ? AND id > ?");
        $shiftStmt->execute([$shift, $clientId, $currency, $refTxnId]);
    } else {
        $txnStmt = $pdo->prepare("INSERT INTO client_transactions (client_id, type, currency, amount, balance, transaction_of, description, reference_id, receipt, tenant_id, branch_id)
                                  VALUES (?, ?, ?, ?, ?, 'fund', ?, NULL, ?, ?, ?)");
        $txnStmt->execute([$clientId, $txnType, $currency, $amount, $newBalance, $fullRemark, $receipt, $tenant_id, $branch_id]);
    }
}

// ──────────────────────────────────────────────────────────────────────
// SUPPLIER TRANSACTION
// ──────────────────────────────────────────────────────────────────────
function insertSupplierTransaction($pdo, $supplierId, $txnType, $currency, $amount, $receipt, $remarks, $refTxnId, $tenant_id, $branch_id, $user_id, $username) {
    $stmt = $pdo->prepare("SELECT name, balance, currency FROM suppliers WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$supplierId, $tenant_id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        throw new Exception('Supplier not found.');
    }

    $direction = $txnType === 'credit' ? 'Credit' : 'Debit';
    $fullRemark = "Balance $direction of $amount $currency to supplier: {$supplier['name']}, processed by: $username" . ($remarks ? ". Remarks: $remarks" : '');

    $currentBalance = (float)$supplier['balance'];
    $newBalance = $txnType === 'credit' ? $currentBalance + $amount : $currentBalance - $amount;

    $updateStmt = $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?");
    $updateStmt->execute([$newBalance, $supplierId, $tenant_id]);

    if ($refTxnId) {
        $insertBalance = $txnType === 'credit' ? $amount : -$amount;

        $txnStmt = $pdo->prepare("INSERT INTO supplier_transactions (id, supplier_id, transaction_type, amount, transaction_of, reference_id, remarks, balance, receipt, tenant_id, branch_id)
                                  VALUES (?, ?, ?, ?, 'fund', 0, ?, ?, ?, ?, ?)");
        $txnStmt->execute([$refTxnId, $supplierId, ucfirst($txnType), $amount, $fullRemark, $insertBalance, $receipt, $tenant_id, $branch_id]);

        $shift = $txnType === 'credit' ? $amount : -$amount;
        $shiftStmt = $pdo->prepare("UPDATE supplier_transactions SET balance = balance + ? WHERE supplier_id = ? AND id > ?");
        $shiftStmt->execute([$shift, $supplierId, $refTxnId]);
    } else {
        $txnStmt = $pdo->prepare("INSERT INTO supplier_transactions (supplier_id, transaction_type, amount, transaction_of, reference_id, remarks, balance, receipt, tenant_id, branch_id)
                                  VALUES (?, ?, ?, 'fund', 0, ?, ?, ?, ?, ?)");
        $txnStmt->execute([$supplierId, ucfirst($txnType), $amount, $fullRemark, $newBalance, $receipt, $tenant_id, $branch_id]);
    }
}

// ──────────────────────────────────────────────────────────────────────
// MAIN ACCOUNT TRANSACTION
// ──────────────────────────────────────────────────────────────────────
function insertMainAccountTransaction($pdo, $accountId, $txnType, $currency, $amount, $receipt, $remarks, $refTxnId, $tenant_id, $branch_id, $user_id, $username) {
    $balanceFieldMap = [
        'USD'    => 'usd_balance',
        'AFS'    => 'afs_balance',
        'EUR'    => 'euro_balance',
        'DARHAM' => 'darham_balance',
        'SAR'    => 'sar_balance',
    ];
    $balanceField = $balanceFieldMap[$currency];

    $stmt = $pdo->prepare("SELECT name, $balanceField AS current_balance FROM main_account WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$accountId, $tenant_id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        throw new Exception('Main account not found.');
    }

    $direction = $txnType === 'credit' ? 'Credit' : 'Debit';
    $fullRemark = "Balance $direction of $amount $currency to account: {$account['name']}, processed by: $username" . ($remarks ? ". Remarks: $remarks" : '');

    $currentBalance = (float)$account['current_balance'];
    $newBalance = $txnType === 'credit' ? $currentBalance + $amount : $currentBalance - $amount;

    $updateStmt = $pdo->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ?");
    $updateStmt->execute([$newBalance, $accountId, $tenant_id]);

    if ($refTxnId) {
        $insertBalance = $txnType === 'credit' ? $amount : -$amount;

        $txnStmt = $pdo->prepare("INSERT INTO main_account_transactions (id, main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id, branch_id, created_by)
                                  VALUES (?, ?, ?, ?, ?, ?, 'fund', NULL, ?, ?, ?, ?, ?)");
        $txnStmt->execute([$refTxnId, $accountId, $txnType, $amount, $currency, $fullRemark, $insertBalance, $receipt, $tenant_id, $branch_id, $user_id]);

        $shift = $txnType === 'credit' ? $amount : -$amount;
        $shiftStmt = $pdo->prepare("UPDATE main_account_transactions SET balance = balance + ? WHERE main_account_id = ? AND currency = ? AND id > ?");
        $shiftStmt->execute([$shift, $accountId, $currency, $refTxnId]);
    } else {
        $txnStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id, branch_id, created_by)
                                  VALUES (?, ?, ?, ?, ?, 'fund', NULL, ?, ?, ?, ?, ?)");
        $txnStmt->execute([$accountId, $txnType, $amount, $currency, $fullRemark, $newBalance, $receipt, $tenant_id, $branch_id, $user_id]);
    }
}
