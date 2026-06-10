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
require_once 'includes/exchange_handler.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$transaction_id    = isset($_POST['transaction_id'])    ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;
$original_amount   = isset($_POST['original_amount'])   ? DbSecurity::validateInput($_POST['original_amount'], 'float', ['min' => 0]) : null;
$from_amount       = isset($_POST['amount'])            ? DbSecurity::validateInput($_POST['amount'], 'float', ['min' => 0]) : null;
$to_amount         = isset($_POST['to_amount'])         ? DbSecurity::validateInput($_POST['to_amount'], 'float', ['min' => 0]) : null;
$rate              = isset($_POST['rate'])              ? DbSecurity::validateInput($_POST['rate'], 'float', ['min' => 0]) : null;
$notes             = isset($_POST['notes'])             ? DbSecurity::validateInput($_POST['notes'], 'string', ['maxlength' => 1000]) : '';

if (!$transaction_id || !$from_amount || !$to_amount || !$rate) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT st.*, et.from_amount, et.from_currency, et.to_amount, et.to_currency, et.rate, et.profit_amount
                           FROM sarafi_transactions st
                           JOIN exchange_transactions et ON st.id = et.transaction_id
                           WHERE st.id = ? AND st.type = 'exchange' AND st.tenant_id = ? AND st.branch_id = ?");
    $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception('Exchange transaction not found');
    }

    $from_currency = $transaction['from_currency'];
    $to_currency = $transaction['to_currency'];

    $from_diff = $from_amount - $original_amount;
    $to_diff = $to_amount - $transaction['to_amount'];

    $stmt = $pdo->prepare("SELECT balance FROM customer_wallets WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $transaction['customer_id'], PDO::PARAM_INT);
    $stmt->bindParam(2, $from_currency, PDO::PARAM_STR);
    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($from_diff > 0 && (!$wallet || $wallet['balance'] < $from_diff)) {
        throw new Exception('Insufficient customer wallet balance for this update');
    }

    $profit_amount = 0;
    try {
        $market_rate = getCurrentMarketRate($pdo, $from_currency, $to_currency);
        $market_amount = $from_amount * $market_rate;
        $profit_amount = $to_amount - $market_amount;
    } catch (Exception $e) {
        $profit_amount = 0;
    }

    if ($from_diff != 0) {
        $stmt = $pdo->prepare("UPDATE customer_wallets SET balance = balance - ? WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $from_diff, PDO::PARAM_STR);
        $stmt->bindParam(2, $transaction['customer_id'], PDO::PARAM_INT);
        $stmt->bindParam(3, $from_currency, PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    if ($to_diff != 0) {
        $stmt = $pdo->prepare("UPDATE customer_wallets SET balance = balance + ? WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $to_diff, PDO::PARAM_STR);
        $stmt->bindParam(2, $transaction['customer_id'], PDO::PARAM_INT);
        $stmt->bindParam(3, $to_currency, PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    $stmt = $pdo->prepare("UPDATE sarafi_transactions SET amount = ?, notes = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $from_amount, PDO::PARAM_STR);
    $stmt->bindParam(2, $notes, PDO::PARAM_STR);
    $stmt->bindParam(3, $transaction_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $pdo->prepare("UPDATE exchange_transactions SET from_amount = ?, to_amount = ?, rate = ?, profit_amount = ? WHERE transaction_id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $from_amount, PDO::PARAM_STR);
    $stmt->bindParam(2, $to_amount, PDO::PARAM_STR);
    $stmt->bindParam(3, $rate, PDO::PARAM_STR);
    $stmt->bindParam(4, $profit_amount, PDO::PARAM_STR);
    $stmt->bindParam(5, $transaction_id, PDO::PARAM_INT);
    $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    try {
        updateExchangeRate($pdo, $from_currency, $to_currency, $rate, $tenant_id, $branch_id);
    } catch (Exception $e) {
        error_log("Warning: Could not update exchange rate history: " . $e->getMessage());
    }

    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $old_vals = json_encode([
        'sarafi_transaction_id' => $transaction_id,
        'from_amount' => $original_amount,
        'to_amount' => $transaction['to_amount'],
        'rate' => $transaction['rate'],
        'notes' => $transaction['notes']
    ]);
    $new_vals = json_encode([
        'from_amount' => $from_amount,
        'to_amount' => $to_amount,
        'rate' => $rate,
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
    echo json_encode(['success' => true, 'message' => 'Exchange transaction updated successfully']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
