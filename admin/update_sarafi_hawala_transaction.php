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

$transaction_id    = isset($_POST['transaction_id'])    ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;
$main_account_id   = isset($_POST['main_account_id'])   ? DbSecurity::validateInput($_POST['main_account_id'], 'int', ['min' => 0]) : null;
$original_amount   = isset($_POST['original_amount'])   ? DbSecurity::validateInput($_POST['original_amount'], 'float', ['min' => 0]) : null;
$send_amount       = isset($_POST['amount'])            ? DbSecurity::validateInput($_POST['amount'], 'float', ['min' => 0]) : null;
$commission_amount = isset($_POST['commission_amount']) ? DbSecurity::validateInput($_POST['commission_amount'], 'float', ['min' => 0]) : null;

$secret_code       = isset($_POST['secret_code'])       ? DbSecurity::validateInput($_POST['secret_code'], 'string', ['maxlength' => 255]) : '';
$reference         = isset($_POST['reference'])         ? DbSecurity::validateInput($_POST['reference'], 'string', ['maxlength' => 255]) : '';
$notes             = isset($_POST['notes'])             ? DbSecurity::validateInput($_POST['notes'], 'string', ['maxlength' => 1000]) : '';

if (!$transaction_id || !$send_amount || $commission_amount === null) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM sarafi_transactions WHERE id = ? AND type = 'hawala_send' AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception('Hawala transaction not found');
    }

    $currency = $transaction['currency'];
    $commission_currency = $currency;

    $stmt = $pdo->prepare("SELECT * FROM hawala_transfers WHERE sender_transaction_id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $hawala = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$hawala) {
        throw new Exception('Hawala transfer record not found');
    }

    if ($hawala['status'] !== 'pending') {
        throw new Exception('Only pending hawala transfers can be edited');
    }

    $stmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'hawala_sarafi' AND tenant_id = ? AND branch_id = ?");
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
        'AED' => 'darham_balance'
    ];

    if (!isset($currencyFieldMap[$currency])) {
        throw new Exception('Unknown currency: ' . $currency);
    }

    $balanceField = $currencyFieldMap[$currency];

    $original_commission = floatval($hawala['commission_amount']);
    $original_net = $original_amount - $original_commission;
    $new_net = $send_amount - $commission_amount;
    $send_diff = $send_amount - $original_amount;
    $commission_diff = $commission_amount - $original_commission;
    $net_diff = $new_net - $original_net;

    if ($send_diff > 0) {
        $stmt = $pdo->prepare("SELECT balance FROM customer_wallets WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $transaction['customer_id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $currency, PDO::PARAM_STR);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$wallet || $wallet['balance'] < $send_diff) {
            throw new Exception('Insufficient customer wallet balance for this update');
        }
    }

    if ($net_diff > 0) {
        $stmt = $pdo->prepare("SELECT $balanceField as current_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $mainAccountId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account || $account['current_balance'] < $net_diff) {
            throw new Exception('Insufficient main account balance for this update');
        }
    }

    // 1. Update sarafi_transactions
    $stmt = $pdo->prepare("UPDATE sarafi_transactions SET amount = ?, reference_number = ?, notes = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $send_amount, PDO::PARAM_STR);
    $stmt->bindParam(2, $reference, PDO::PARAM_STR);
    $stmt->bindParam(3, $notes, PDO::PARAM_STR);
    $stmt->bindParam(4, $transaction_id, PDO::PARAM_INT);
    $stmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(6, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    // 2. Update customer wallet
    if ($send_diff != 0) {
        $stmt = $pdo->prepare("UPDATE customer_wallets SET balance = balance - ? WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $send_diff, PDO::PARAM_STR);
        $stmt->bindParam(2, $transaction['customer_id'], PDO::PARAM_INT);
        $stmt->bindParam(3, $currency, PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // 3. Update hawala_transfers
    $stmt = $pdo->prepare("UPDATE hawala_transfers SET secret_code = ?, commission_amount = ?, commission_currency = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $secret_code, PDO::PARAM_STR);
    $stmt->bindParam(2, $commission_amount, PDO::PARAM_STR);
    $stmt->bindParam(3, $commission_currency, PDO::PARAM_STR);
    $stmt->bindParam(4, $hawala['id'], PDO::PARAM_INT);
    $stmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(6, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    // 4. Update general_ledger (reverse old commission, record new)
    if ($commission_diff != 0) {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(balance), 0) as max_balance FROM general_ledger WHERE account_type = 'income' AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $currency, PDO::PARAM_STR);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $maxBalance = floatval($stmt->fetch(PDO::FETCH_ASSOC)['max_balance']);

        $reversal_balance = $maxBalance - $original_commission;

        $stmt = $pdo->prepare("INSERT INTO general_ledger (account_type, entry_type, amount, currency, balance, tenant_id, branch_id) VALUES ('income', 'debit', ?, ?, ?, ?, ?)");
        $stmt->bindParam(1, $original_commission, PDO::PARAM_STR);
        $stmt->bindParam(2, $currency, PDO::PARAM_STR);
        $stmt->bindParam(3, $reversal_balance, PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        $new_balance = $reversal_balance + $commission_amount;

        $stmt = $pdo->prepare("INSERT INTO general_ledger (account_type, entry_type, amount, currency, balance, tenant_id, branch_id) VALUES ('income', 'credit', ?, ?, ?, ?, ?)");
        $stmt->bindParam(1, $commission_amount, PDO::PARAM_STR);
        $stmt->bindParam(2, $currency, PDO::PARAM_STR);
        $stmt->bindParam(3, $new_balance, PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // 5. Update main_account_transactions
    if ($net_diff != 0) {
        $balanceAdjustment = -$net_diff;

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
        $currentBalance = floatval($stmt->fetch(PDO::FETCH_ASSOC)['balance']);

        $newBalance = $currentBalance - $net_diff;

        $stmt = $pdo->prepare("UPDATE main_account_transactions SET amount = ?, balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $new_net, PDO::PARAM_STR);
        $stmt->bindParam(2, $newBalance, PDO::PARAM_STR);
        $stmt->bindParam(3, $mainTransaction['id'], PDO::PARAM_INT);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        // 6. Update main_account
        $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $balanceAdjustment, PDO::PARAM_STR);
        $stmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("UPDATE main_account_transactions SET amount = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $new_net, PDO::PARAM_STR);
        $stmt->bindParam(2, $mainTransaction['id'], PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $old_vals = json_encode([
        'sarafi_transaction_id' => $transaction_id,
        'send_amount' => $original_amount,
        'commission_amount' => $original_commission,
        'commission_currency' => $hawala['commission_currency'],
        'secret_code' => $hawala['secret_code'],
        'reference' => $transaction['reference_number'],
        'notes' => $transaction['notes']
    ]);
    $new_vals = json_encode([
        'send_amount' => $send_amount,
        'commission_amount' => $commission_amount,
        'commission_currency' => $commission_currency,
        'secret_code' => $secret_code,
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
    echo json_encode(['success' => true, 'message' => 'Hawala transfer updated successfully']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
