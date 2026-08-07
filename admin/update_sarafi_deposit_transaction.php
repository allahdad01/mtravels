<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db_security.php';
require_once 'security.php';
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../includes/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$transaction_id   = isset($_POST['transaction_id'])  ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;
$main_account_id  = isset($_POST['main_account_id']) ? DbSecurity::validateInput($_POST['main_account_id'], 'int', ['min' => 0]) : null;
$original_amount  = isset($_POST['original_amount']) ? DbSecurity::validateInput($_POST['original_amount'], 'float', ['min' => 0]) : null;
$new_amount       = isset($_POST['amount'])          ? DbSecurity::validateInput($_POST['amount'], 'float', ['min' => 0]) : null;
$reference        = isset($_POST['reference'])       ? DbSecurity::validateInput($_POST['reference'], 'string', ['maxlength' => 255]) : '';
$notes            = isset($_POST['notes'])           ? DbSecurity::validateInput($_POST['notes'], 'string', ['maxlength' => 1000]) : '';

if (!$transaction_id) {
    echo json_encode(['success' => false, 'message' => 'Missing transaction ID']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM sarafi_transactions WHERE id = ? AND type = 'deposit' AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception('Deposit transaction not found');
    }

    $currency = $transaction['currency'];

    $stmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'deposit_sarafi' AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $mainTransaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mainTransaction) {
        throw new Exception('Main account transaction not found');
    }

    $mainAccountId = $mainTransaction['main_account_id'];

    $currencyFieldMap = [
        'USD' => 'usd_balance',
        'AFS' => 'afs_balance',
        'EUR' => 'euro_balance',
        'AED' => 'darham_balance',
        'SAR' => 'sar_balance'
    ];

    if (!isset($currencyFieldMap[$currency])) {
        throw new Exception('Unknown currency: ' . $currency);
    }

    $balanceField = $currencyFieldMap[$currency];

    $amountDifference = $new_amount - $original_amount;

    if ($amountDifference != 0) {
        $balanceAdjustment = $amountDifference;

        $stmt = $pdo->prepare("UPDATE main_account_transactions SET balance = balance + ? WHERE main_account_id = ? AND currency = ? AND id > ? AND id != ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $balanceAdjustment, PDO::PARAM_STR);
        $stmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
        $stmt->bindParam(3, $currency, PDO::PARAM_STR);
        $stmt->bindParam(4, $mainTransaction['id'], PDO::PARAM_INT);
        $stmt->bindParam(5, $mainTransaction['id'], PDO::PARAM_INT);
        $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("SELECT balance FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $mainTransaction['id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $currentBalance = $stmt->fetch(PDO::FETCH_ASSOC)['balance'];

        $newBalance = $currentBalance + $amountDifference;

        $stmt = $pdo->prepare("UPDATE main_account_transactions SET amount = ?, balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $new_amount, PDO::PARAM_STR);
        $stmt->bindParam(2, $newBalance, PDO::PARAM_STR);
        $stmt->bindParam(3, $mainTransaction['id'], PDO::PARAM_INT);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("UPDATE customer_wallets SET balance = balance + ? WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $amountDifference, PDO::PARAM_STR);
        $stmt->bindParam(2, $transaction['customer_id'], PDO::PARAM_INT);
        $stmt->bindParam(3, $currency, PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $balanceAdjustment, PDO::PARAM_STR);
        $stmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("UPDATE main_account_transactions SET amount = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $new_amount, PDO::PARAM_STR);
        $stmt->bindParam(2, $mainTransaction['id'], PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    $stmt = $pdo->prepare("UPDATE sarafi_transactions SET amount = ?, reference_number = ?, notes = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $new_amount, PDO::PARAM_STR);
    $stmt->bindParam(2, $reference, PDO::PARAM_STR);
    $stmt->bindParam(3, $notes, PDO::PARAM_STR);
    $stmt->bindParam(4, $transaction_id, PDO::PARAM_INT);
    $stmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(6, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $old_vals = json_encode([
        'sarafi_transaction_id' => $transaction_id,
        'amount' => $original_amount,
        'reference' => $transaction['reference_number'],
        'notes' => $transaction['notes']
    ]);
    $new_vals = json_encode([
        'amount' => $new_amount,
        'reference' => $reference,
        'notes' => $notes
    ]);

    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id, branch_id) VALUES (?, 'update', 'sarafi_transactions', ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $transaction_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $old_vals, PDO::PARAM_STR);
    $stmt->bindParam(4, $new_vals, PDO::PARAM_STR);
    $stmt->bindParam(5, $ip_address, PDO::PARAM_STR);
    $stmt->bindParam(6, $user_agent, PDO::PARAM_STR);
    $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(8, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Deposit transaction updated successfully']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
