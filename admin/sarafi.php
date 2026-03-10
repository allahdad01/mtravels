<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';
// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    error_log("Unauthorized access attempt to dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - Role: " . ($_SESSION['role'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}
if (!isset($_SESSION['tenant_id'])) {
    $_SESSION['error_message'] = "Tenant ID not found. Please log in again.";
    header("Location: ../login.php");
    exit();
}

require_once '../includes/db.php';
require_once '../includes/SecureFileUpload.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message   = isset($_SESSION['error_message'])   ? $_SESSION['error_message']   : null;
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

$redirect_url = $_SERVER['PHP_SELF'];
if (!empty($_GET)) {
    $redirect_url .= '?' . http_build_query($_GET);
}

// ── DEPOSIT ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_deposit'])) {
    $customer_id = $_POST['customer_id'];
    if (!isset($_POST['amount']) || !is_numeric($_POST['amount']) || floatval($_POST['amount']) <= 0) {
        die(json_encode(['success' => false, 'message' => 'Invalid amount: must be a positive number']));
    }
    $amount   = floatval($_POST['amount']);
    if (!isset($_POST['currency']) || empty($_POST['currency'])) {
        die(json_encode(['success' => false, 'message' => 'Currency is required']));
    }
    $currency        = $_POST['currency'];
    $notes           = $_POST['notes'];
    $reference       = $_POST['reference'];
    $main_account_id = $_POST['main_account_id'];

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT name FROM customers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id,   PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id,   PDO::PARAM_INT);
        $stmt->execute();
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("INSERT INTO sarafi_transactions (customer_id, amount, currency, type, notes, reference_number, tenant_id, branch_id) VALUES (?, ?, ?, 'deposit', ?, ?, ?, ?)");
        $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $amount,      PDO::PARAM_STR);
        $stmt->bindParam(3, $currency,    PDO::PARAM_STR);
        $stmt->bindParam(4, $notes,       PDO::PARAM_STR);
        $stmt->bindParam(5, $reference,   PDO::PARAM_STR);
        $stmt->bindParam(6, $tenant_id,   PDO::PARAM_INT);
        $stmt->bindParam(7, $branch_id,   PDO::PARAM_INT);
        $stmt->execute();
        $transaction_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("SELECT id FROM customer_wallets WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $currency,    PDO::PARAM_STR);
        $stmt->bindParam(3, $tenant_id,   PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id,   PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $stmt = $pdo->prepare("UPDATE customer_wallets SET balance = balance + ? WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->bindParam(1, $amount,      PDO::PARAM_STR);
            $stmt->bindParam(2, $customer_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $currency,    PDO::PARAM_STR);
            $stmt->bindParam(4, $tenant_id,   PDO::PARAM_INT);
            $stmt->bindParam(5, $branch_id,   PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare("INSERT INTO customer_wallets (customer_id, currency, balance, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $currency,    PDO::PARAM_STR);
            $stmt->bindParam(3, $amount,      PDO::PARAM_STR);
            $stmt->bindParam(4, $tenant_id,   PDO::PARAM_INT);
            $stmt->bindParam(5, $branch_id,   PDO::PARAM_INT);
        }
        $stmt->execute();

        $balanceField = $currency === 'USD' ? 'usd_balance' : ($currency === 'AFS' ? 'afs_balance' : ($currency === 'EUR' ? 'euro_balance' : 'darham_balance'));
        $stmt = $pdo->prepare("SELECT $balanceField as current_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $main_account_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id,       PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id,       PDO::PARAM_INT);
        $stmt->execute();
        $balanceResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $newBalance    = $balanceResult['current_balance'] + $amount;

        $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $newBalance,      PDO::PARAM_STR);
        $stmt->bindParam(2, $main_account_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id,       PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id,       PDO::PARAM_INT);
        $stmt->execute();

        $transaction_of = 'deposit_sarafi';
        $stmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id, branch_id) VALUES (?, 'credit', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bindParam(1,  $main_account_id,  PDO::PARAM_INT);
        $stmt->bindParam(2,  $amount,            PDO::PARAM_STR);
        $stmt->bindParam(3,  $currency,          PDO::PARAM_STR);
        $stmt->bindParam(4,  $notes,             PDO::PARAM_STR);
        $stmt->bindParam(5,  $transaction_of,    PDO::PARAM_STR);
        $stmt->bindParam(6,  $transaction_id,    PDO::PARAM_INT);
        $stmt->bindParam(7,  $newBalance,        PDO::PARAM_STR);
        $stmt->bindParam(8,  $reference,         PDO::PARAM_STR);
        $stmt->bindParam(9,  $tenant_id,         PDO::PARAM_INT);
        $stmt->bindParam(10, $branch_id,         PDO::PARAM_INT);
        $stmt->execute();
        $main_transaction_id = $pdo->lastInsertId();

        $notificationMessage = sprintf(__('new_deposit_notification'), $customer['name'], $currency, $amount, $reference);
        $stmt = $pdo->prepare("INSERT INTO notifications (transaction_id, transaction_type, message, status, created_at, tenant_id, branch_id) VALUES (?, ?, ?, 'Unread', NOW(), ?, ?)");
        $stmt->bindParam(1, $main_transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $transaction_of,      PDO::PARAM_STR);
        $stmt->bindParam(3, $notificationMessage, PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id,           PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id,           PDO::PARAM_INT);
        $stmt->execute();

        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            error_log("Receipt file detected for deposit: " . print_r($_FILES['receipt'], true));
            $uploader = new SecureFileUpload(10 * 1024 * 1024, '../uploads/');
            $result   = $uploader->upload('receipt', 'receipts');
            error_log("Upload result: " . print_r($result, true));
            if ($result['success']) {
                $stmt = $pdo->prepare("UPDATE sarafi_transactions SET receipt_path = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt->bindParam(1, $result['data']['filename'], PDO::PARAM_STR);
                $stmt->bindParam(2, $transaction_id,             PDO::PARAM_INT);
                $stmt->bindParam(3, $tenant_id,                  PDO::PARAM_INT);
                $stmt->bindParam(4, $branch_id,                  PDO::PARAM_INT);
                $stmt->execute();
                error_log("Receipt saved: " . $result['data']['filename']);
            } else {
                error_log("Receipt upload failed: " . $result['error']);
            }
        } else {
            error_log("No receipt file or upload error: " . ($_FILES['receipt']['error'] ?? 'not set'));
        }

        $pdo->commit();
        $_SESSION['success_message'] = __('deposit_success');
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $pdo->rollback();
        $_SESSION['error_message'] = sprintf(__('processing_error'), __('deposit'), $e->getMessage());
        header('Location: ' . $redirect_url);
        exit();
    }
}

// ── WITHDRAWAL ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_withdrawal'])) {
    $customer_id     = $_POST['customer_id'];
    $amount          = $_POST['amount'];
    $currency        = $_POST['currency'];
    $notes           = $_POST['notes'];
    $reference       = $_POST['reference'];
    $main_account_id = $_POST['main_account_id'];

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT name FROM customers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id,   PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id,   PDO::PARAM_INT);
        $stmt->execute();
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT balance FROM customer_wallets WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $currency,    PDO::PARAM_STR);
        $stmt->bindParam(3, $tenant_id,   PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id,   PDO::PARAM_INT);
        $stmt->execute();
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$wallet || $wallet['balance'] < $amount) {
            throw new Exception(__('insufficient_balance'));
        }

        $balanceField = $currency === 'USD' ? 'usd_balance' : ($currency === 'AFS' ? 'afs_balance' : ($currency === 'EUR' ? 'euro_balance' : 'darham_balance'));
        $stmt = $pdo->prepare("SELECT $balanceField as current_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $main_account_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id,       PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id,       PDO::PARAM_INT);
        $stmt->execute();
        $balanceResult = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$balanceResult || $balanceResult['current_balance'] < $amount) {
            throw new Exception(__('insufficient_main_account_balance'));
        }
        $newBalance = $balanceResult['current_balance'] - $amount;

        $stmt = $pdo->prepare("INSERT INTO sarafi_transactions (customer_id, amount, currency, type, notes, reference_number, tenant_id, branch_id) VALUES (?, ?, ?, 'withdrawal', ?, ?, ?, ?)");
        $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $amount,      PDO::PARAM_STR);
        $stmt->bindParam(3, $currency,    PDO::PARAM_STR);
        $stmt->bindParam(4, $notes,       PDO::PARAM_STR);
        $stmt->bindParam(5, $reference,   PDO::PARAM_STR);
        $stmt->bindParam(6, $tenant_id,   PDO::PARAM_INT);
        $stmt->bindParam(7, $branch_id,   PDO::PARAM_INT);
        $stmt->execute();
        $transaction_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("UPDATE customer_wallets SET balance = balance - ? WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $amount,      PDO::PARAM_STR);
        $stmt->bindParam(2, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $currency,    PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id,   PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id,   PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $newBalance,      PDO::PARAM_STR);
        $stmt->bindParam(2, $main_account_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id,       PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id,       PDO::PARAM_INT);
        $stmt->execute();

        $transaction_of = 'withdrawal_sarafi';
        $stmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id, branch_id) VALUES (?, 'debit', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bindParam(1,  $main_account_id, PDO::PARAM_INT);
        $stmt->bindParam(2,  $amount,          PDO::PARAM_STR);
        $stmt->bindParam(3,  $currency,        PDO::PARAM_STR);
        $stmt->bindParam(4,  $notes,           PDO::PARAM_STR);
        $stmt->bindParam(5,  $transaction_of,  PDO::PARAM_STR);
        $stmt->bindParam(6,  $transaction_id,  PDO::PARAM_INT);
        $stmt->bindParam(7,  $newBalance,      PDO::PARAM_STR);
        $stmt->bindParam(8,  $reference,       PDO::PARAM_STR);
        $stmt->bindParam(9,  $tenant_id,       PDO::PARAM_INT);
        $stmt->bindParam(10, $branch_id,       PDO::PARAM_INT);
        $stmt->execute();
        $main_transaction_id = $pdo->lastInsertId();

        $notificationMessage = sprintf(__('new_withdrawal_notification'), $customer['name'], $currency, $amount, $reference);
        $stmt = $pdo->prepare("INSERT INTO notifications (transaction_id, transaction_type, message, status, created_at, tenant_id, branch_id) VALUES (?, ?, ?, 'Unread', NOW(), ?, ?)");
        $stmt->bindParam(1, $main_transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $transaction_of,      PDO::PARAM_STR);
        $stmt->bindParam(3, $notificationMessage, PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id,           PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id,           PDO::PARAM_INT);
        $stmt->execute();

        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            error_log("Receipt file detected for withdrawal: " . print_r($_FILES['receipt'], true));
            $uploader = new SecureFileUpload(10 * 1024 * 1024, '../uploads/');
            $result   = $uploader->upload('receipt', 'receipts');
            error_log("Upload result: " . print_r($result, true));
            if ($result['success']) {
                $stmt = $pdo->prepare("UPDATE sarafi_transactions SET receipt_path = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt->bindParam(1, $result['data']['filename'], PDO::PARAM_STR);
                $stmt->bindParam(2, $transaction_id,             PDO::PARAM_INT);
                $stmt->bindParam(3, $tenant_id,                  PDO::PARAM_INT);
                $stmt->bindParam(4, $branch_id,                  PDO::PARAM_INT);
                $stmt->execute();
                error_log("Receipt saved: " . $result['data']['filename']);
            } else {
                error_log("Receipt upload failed: " . $result['error']);
            }
        } else {
            error_log("No receipt file or upload error: " . ($_FILES['receipt']['error'] ?? 'not set'));
        }

        $pdo->commit();
        $_SESSION['success_message'] = __('withdrawal_success');
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $pdo->rollback();
        $_SESSION['error_message'] = sprintf(__('processing_error'), __('withdrawal'), $e->getMessage());
        header('Location: ' . $redirect_url);
        exit();
    }
}

// ── HAWALA / EXCHANGE handlers (unchanged logic) ─────────────────────────────
require_once 'includes/hawala_handler.php';
require_once 'includes/exchange_handler.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_hawala'])) {
    $data = [
        'sender_id'           => $_POST['sender_id'],
        'send_amount'         => $_POST['send_amount'],
        'send_currency'       => $_POST['send_currency'],
        'notes'               => $_POST['notes'],
        'reference'           => uniqid('HWL'),
        'secret_code'         => $_POST['secret_code'],
        'commission_amount'   => $_POST['commission_amount'],
        'commission_currency' => $_POST['commission_currency'],
        'main_account_id'     => $_POST['main_account_id'],
    ];

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT name FROM customers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $data['sender_id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id,         PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id,         PDO::PARAM_INT);
        $stmt->execute();
        $sender = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data['send_currency'] !== $data['commission_currency']) {
            throw new Exception(__('commission_currency_mismatch'));
        }
        $net_amount   = $data['send_amount'] - $data['commission_amount'];
        $balanceField = $data['send_currency'] === 'USD' ? 'usd_balance' : ($data['send_currency'] === 'AFS' ? 'afs_balance' : ($data['send_currency'] === 'EUR' ? 'euro_balance' : 'darham_balance'));

        $stmt = $pdo->prepare("SELECT $balanceField as current_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $data['main_account_id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id,               PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id,               PDO::PARAM_INT);
        $stmt->execute();
        $balanceResult = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$balanceResult || $balanceResult['current_balance'] < $net_amount) {
            throw new Exception(__('insufficient_main_account_balance_hawala'));
        }
        $newBalance = $balanceResult['current_balance'] - $net_amount;

        $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $newBalance,              PDO::PARAM_STR);
        $stmt->bindParam(2, $data['main_account_id'], PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id,               PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id,               PDO::PARAM_INT);
        $stmt->execute();

        $result = processHawalaTransfer($pdo, $data);

        if ($result['success']) {
            $transaction_of = 'hawala_sarafi';
            $stmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id, branch_id) VALUES (?, 'debit', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bindParam(1,  $data['main_account_id'], PDO::PARAM_INT);
            $stmt->bindParam(2,  $net_amount,              PDO::PARAM_STR);
            $stmt->bindParam(3,  $data['send_currency'],   PDO::PARAM_STR);
            $stmt->bindParam(4,  $data['notes'],           PDO::PARAM_STR);
            $stmt->bindParam(5,  $transaction_of,          PDO::PARAM_STR);
            $stmt->bindParam(6,  $result['sender_transaction_id'], PDO::PARAM_INT);
            $stmt->bindParam(7,  $newBalance,              PDO::PARAM_STR);
            $stmt->bindParam(8,  $data['reference'],       PDO::PARAM_STR);
            $stmt->bindParam(9,  $tenant_id,               PDO::PARAM_INT);
            $stmt->bindParam(10, $branch_id,               PDO::PARAM_INT);
            $stmt->execute();
            $main_transaction_id = $pdo->lastInsertId();

            $notificationMessage = sprintf(__('new_hawala_transfer_notification'), $sender['name'], $data['send_currency'], $data['send_amount'], $data['commission_currency'], $data['commission_amount'], $data['send_currency'], $net_amount, $data['reference']);
            $stmt = $pdo->prepare("INSERT INTO notifications (transaction_id, transaction_type, message, status, created_at, tenant_id, branch_id) VALUES (?, ?, ?, 'Unread', NOW(), ?, ?)");
            $stmt->bindParam(1, $main_transaction_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $transaction_of,      PDO::PARAM_STR);
            $stmt->bindParam(3, $notificationMessage, PDO::PARAM_STR);
            $stmt->bindParam(4, $tenant_id,           PDO::PARAM_INT);
            $stmt->bindParam(5, $branch_id,           PDO::PARAM_INT);
            $stmt->execute();

            $pdo->commit();
            $_SESSION['success_message'] = $result['message'];
        } else {
            throw new Exception($result['message']);
        }
    } catch (Exception $e) {
        try {
            $pdo->rollBack();
        } catch (Exception $rollbackError) {
            // Transaction may not have been started
        }
        $_SESSION['error_message'] = $e->getMessage();
    }
    header('Location: ' . $redirect_url);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_exchange'])) {
    $data   = ['customer_id' => $_POST['customer_id'], 'from_amount' => $_POST['from_amount'], 'from_currency' => $_POST['from_currency'], 'to_amount' => $_POST['to_amount'], 'to_currency' => $_POST['to_currency'], 'rate' => $_POST['rate'], 'notes' => $_POST['notes']];
    $result = processCurrencyExchange($pdo, $data);
    if ($result['success']) { $_SESSION['success_message'] = $result['message']; } else { $_SESSION['error_message'] = $result['message']; }
    header('Location: ' . $redirect_url);
    exit();
}

// ── DATA QUERIES ─────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM customers WHERE status = 'active' AND tenant_id = ? AND branch_id = ? ORDER BY created_at DESC");
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currency_totals = [];
$stmt = $pdo->prepare("SELECT currency, SUM(balance) as total FROM customer_wallets WHERE tenant_id = ? AND branch_id = ? GROUP BY currency");
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $currency_totals[$row['currency']] = $row['total'];
}

$limit  = 10;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sarafi_transactions t JOIN customers c ON t.customer_id = c.id WHERE t.tenant_id = ? AND t.branch_id = ? AND c.branch_id = ?");
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$total_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_count / $limit);

$stmt = $pdo->prepare("SELECT t.*, c.name as customer_name FROM sarafi_transactions t JOIN customers c ON t.customer_id = c.id WHERE t.tenant_id = ? AND t.branch_id = ? AND c.branch_id = ? ORDER BY t.created_at DESC LIMIT ? OFFSET ?");
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->bindParam(4, $limit,     PDO::PARAM_INT);
$stmt->bindParam(5, $offset,    PDO::PARAM_INT);
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Currency stat config
$currency_config = [
    'USD' => ['class' => 'usd', 'label' => 'USD Total Balance'],
    'AFS' => ['class' => 'afs', 'label' => 'AFS Total Balance'],
    'EUR' => ['class' => 'eur', 'label' => 'EUR Total Balance'],
    'AED' => ['class' => 'aed', 'label' => 'AED Total Balance'],
];
?>
<?php include '../includes/header.php'; ?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="../uploads/logo/<?= h($settings['logo'] ?? '') ?>" type="image/x-icon">
<link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
<link rel="stylesheet" href="../assets/plugins/animation/css/animate.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/header-styles.css">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Syne:wght@500;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════
   SARAFI — PROFESSIONAL FINANCE THEME
   Navy / Teal  ·  Data-dense  ·  Action-first
═══════════════════════════════════════════════ */
:root {
  /* Brand gradient from site header */
  --grad-start: #4099ff;
  --grad-end: #2ed8b6;
  --grad: linear-gradient(135deg, var(--grad-start) 0%, var(--grad-end) 100%);
  
  /* Light theme colors */
  --surface: #f8fafc;
  --white: #ffffff;
  --border: #e5e7eb;
  
  /* Legacy colors for compatibility */
  --navy-950: #f8fafc;
  --navy-900: #ffffff;
  --navy-800: #ffffff;
  --navy-700: #f9fafb;
  --teal-500: #2ed8b6;
  --teal-400: #2ed8b6;
  --teal-300: #22d3ee;
  --teal-glow: rgba(64,153,255,0.12);
  --teal-glow-sm: rgba(64,153,255,0.06);
  --amber: #f59e0b;
  --amber-light: #fcd34d;
  --emerald: #10b981;
  --rose: #f43f5e;
  --slate-400: #6b7280;
  --slate-300: #d1d5db;
  --mono: 'IBM Plex Mono', monospace;
  --display: 'Syne', sans-serif;
  --body: 'DM Sans', sans-serif;
  --border-soft: rgba(64,153,255,0.1);
  --radius: 10px;
  --radius-lg: 14px;
}

/* ── Reset the pcoded container to light theme ── */
body,
.pcoded-main-container,
.pcoded-wrapper,
.pcoded-content,
.pcoded-inner-content,
.main-body,
.page-wrapper {
  background: var(--surface) !important;
  color: #333 !important;
  font-family: var(--body) !important;
}

/* ── Page chrome ── */
.sarafi-page {
  padding: 20px 24px 60px;
  max-width: 1400px;
  margin: 0 auto;
  font-family: var(--body);
}

/* ── Page heading ── */
.sarafi-heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-bottom: 24px;
  padding-bottom: 18px;
  border-bottom: 1px solid var(--border-soft);
}
.sarafi-heading h2 {
   font-family: var(--display);
   font-weight: 800;
   font-size: 24px;
   letter-spacing: -0.4px;
   color: #333;
   margin: 0;
 }
.sarafi-heading .subtitle {
  font-size: 12px;
  color: var(--slate-400);
  margin-top: 4px;
  font-weight: 400;
}
.btn-view-customers {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 16px;
  background: transparent;
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--teal-400);
  font-family: var(--body);
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.18s;
}
.btn-view-customers:hover {
  background: var(--teal-glow-sm);
  border-color: var(--teal-400);
  color: var(--teal-300);
  text-decoration: none;
}

/* ── Currency stat cards ── */
.stats-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 18px;
}
.stat-card {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 16px 18px;
  position: relative;
  overflow: hidden;
  transition: border-color 0.2s, transform 0.2s;
}
.stat-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
}
.stat-card.usd::before { background: var(--grad); }
.stat-card.afs::before { background: linear-gradient(90deg, var(--emerald), #34d399); }
.stat-card.eur::before { background: linear-gradient(90deg, #6366f1, #8b5cf6); }
.stat-card.aed::before { background: linear-gradient(90deg, var(--amber), var(--amber-light)); }
.stat-card:hover { border-color: rgba(64,153,255,0.3); transform: translateY(-2px); }
.stat-label {
  font-family: var(--mono);
  font-size: 9px;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  color: #666;
  margin-bottom: 8px;
}
.stat-value {
  font-family: var(--mono);
  font-size: 20px;
  font-weight: 500;
  color: #333;
  letter-spacing: -0.5px;
  line-height: 1;
}
.stat-badge {
  display: inline-block;
  font-family: var(--mono);
  font-size: 9px;
  font-weight: 500;
  letter-spacing: 0.6px;
  padding: 2px 6px;
  border-radius: 4px;
  margin-top: 8px;
}
.stat-card.usd .stat-badge { background: rgba(8,145,178,0.15);  color: var(--teal-300); }
.stat-card.afs .stat-badge { background: rgba(16,185,129,0.15); color: #34d399; }
.stat-card.eur .stat-badge { background: rgba(99,102,241,0.15); color: #818cf8; }
.stat-card.aed .stat-badge { background: rgba(245,158,11,0.15); color: var(--amber-light); }

/* ── Action bar ── */
.action-bar {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 16px 20px;
  margin-bottom: 18px;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.action-bar-label {
  font-family: var(--mono);
  font-size: 9px;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  color: #666;
  margin-right: 4px;
  white-space: nowrap;
}
.action-bar-divider {
  width: 1px;
  height: 26px;
  background: var(--border);
  margin: 0 4px;
}
.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 16px;
  border-radius: 8px;
  font-family: var(--body);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.18s;
  white-space: nowrap;
  letter-spacing: 0.1px;
}
.action-btn.deposit    { background: var(--grad); color: #fff; box-shadow: 0 2px 12px rgba(64,153,255,0.2); }
.action-btn.deposit:hover { box-shadow: 0 4px 18px rgba(64,153,255,0.3); transform: translateY(-1px); }
.action-btn.withdrawal { background: rgba(244,63,94,0.11); color: #fb7185; border: 1px solid rgba(244,63,94,0.22); }
.action-btn.withdrawal:hover { background: rgba(244,63,94,0.18); border-color: rgba(244,63,94,0.45); }
.action-btn.hawala     { background: rgba(99,102,241,0.11); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.22); }
.action-btn.hawala:hover { background: rgba(99,102,241,0.18); border-color: rgba(99,102,241,0.45); }
.action-btn.exchange   { background: rgba(245,158,11,0.11); color: var(--amber-light); border: 1px solid rgba(245,158,11,0.22); }
.action-btn.exchange:hover { background: rgba(245,158,11,0.18); border-color: rgba(245,158,11,0.45); }
.action-btn.new-customer { background: rgba(16,185,129,0.11); color: #34d399; border: 1px solid rgba(16,185,129,0.22); }
.action-btn.new-customer:hover { background: rgba(16,185,129,0.18); border-color: rgba(16,185,129,0.45); }
.kbd {
  display: inline-flex;
  align-items: center;
  padding: 1px 5px;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 3px;
  font-family: var(--mono);
  font-size: 9px;
  color: rgba(255,255,255,0.5);
  letter-spacing: 0.3px;
  margin-left: 3px;
}

/* ── Flash messages ── */
.flash {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 11px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 16px;
}
.flash.success { background: rgba(16,185,129,0.10); border: 1px solid rgba(16,185,129,0.25); color: #34d399; }
.flash.error   { background: rgba(244,63,94,0.10);  border: 1px solid rgba(244,63,94,0.25);  color: #fb7185; }

/* ── Table card ── */
.table-card {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}
.table-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px;
  border-bottom: 1px solid var(--border);
  flex-wrap: wrap;
  gap: 12px;
  background: var(--white);
}
.table-card-title {
  display: flex;
  align-items: center;
  gap: 10px;
}
.table-card-title h5 {
  font-family: var(--display);
  font-weight: 700;
  font-size: 15px;
  color: #0f1117;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.table-card-title svg {
  color: var(--grad-start);
  flex-shrink: 0;
}
.tx-count-badge {
  background: #f4f5f7;
  border: 1px solid var(--border);
  color: #6b7280;
  font-family: var(--mono);
  font-size: 9px;
  font-weight: 500;
  padding: 2px 8px;
  border-radius: 20px;
}
.table-search-wrap {
  display: flex;
  align-items: center;
  gap: 7px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 7px;
  padding: 6px 12px;
  min-width: 200px;
  transition: border-color 0.18s;
}
.table-search-wrap:focus-within { border-color: var(--grad-start); }
.table-search-wrap input {
  background: none;
  border: none;
  outline: none;
  color: #333;
  font-family: var(--body);
  font-size: 12px;
  width: 100%;
}
.table-search-wrap input::placeholder { color: #999; }

/* The table itself */
.table-scroll { overflow-x: auto; }
.tx-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 13.5px;
}
.tx-table thead tr { border-bottom: 1px solid var(--border); }
.tx-table thead th {
  background: #f4f5f7;
  color: #6b7280;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.7px;
  padding: 11px 16px;
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
  text-align: left;
}
.tx-table thead th:first-child { border-radius: 8px 0 0 0; padding-left: 20px; }
.tx-table thead th:last-child  { border-radius: 0 8px 0 0; padding-right: 20px; text-align: right; }
.tx-table tbody tr { transition: background 0.15s; }
.tx-table tbody tr:hover td { background: #f8f9ff; }
.tx-table tbody tr.disabled td { background: #fff9f9; color: #888; }
.tx-table tbody tr.disabled:hover td { background: #fff3f3; }
.tx-table tbody tr:last-child td { border-bottom: none; }
.tx-table tbody td {
  padding: 13px 16px;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
  color: #0f1117;
  font-size: 13.5px;
}
.tx-table tbody td:first-child { padding-left: 20px; }
.tx-table tbody td:last-child  { padding-right: 20px; }

/* Cells */
.td-date { font-family: var(--mono); font-size: 11px; color: #666; white-space: nowrap; }
.td-customer { display: flex; align-items: center; gap: 8px; }
.mini-avatar {
  width: 26px; height: 26px;
  border-radius: 50%;
  background: var(--grad);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--display); font-weight: 700; font-size: 10px; color: #fff;
  flex-shrink: 0;
}
.customer-nm { font-weight: 500; color: #333; font-size: 13px; }

.type-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 9px; border-radius: 20px;
  font-size: 11px; font-weight: 600; letter-spacing: 0.1px; white-space: nowrap;
}
.type-badge.deposit    { background: rgba(64,153,255,0.12);  color: var(--grad-start);    border: 1px solid rgba(64,153,255,0.2); }
.type-badge.withdrawal { background: rgba(244,63,94,0.10);  color: #fb7185;            border: 1px solid rgba(244,63,94,0.2); }
.type-badge.hawala_send,
.type-badge.hawala_receive { background: rgba(99,102,241,0.10); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.2); }
.type-badge.exchange   { background: rgba(245,158,11,0.10); color: var(--amber-light); border: 1px solid rgba(245,158,11,0.2); }

.td-amount { font-family: var(--mono); font-size: 13px; font-weight: 500; color: #333; white-space: nowrap; }
.td-amount.pos { color: var(--grad-end); }
.td-amount.neg { color: #fb7185; }

.currency-chip {
  font-family: var(--mono); font-size: 10px; font-weight: 500;
  letter-spacing: 0.5px; padding: 2px 7px; border-radius: 4px;
  background: var(--surface); border: 1px solid var(--border); color: #666;
}
.td-ref { font-family: var(--mono); font-size: 11px; color: var(--slate-400); }

.status-badge {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 11px; font-weight: 500; padding: 3px 9px; border-radius: 20px;
}
.status-badge::before { content: ''; width: 5px; height: 5px; border-radius: 50%; }
.status-badge.completed { background: rgba(16,185,129,0.10); color: #34d399; }
.status-badge.completed::before { background: #34d399; }
.status-badge.pending   { background: rgba(245,158,11,0.10); color: var(--amber-light); }
.status-badge.pending::before   { background: var(--amber); }
.status-badge.failed    { background: rgba(244,63,94,0.10);  color: #fb7185; }
.status-badge.failed::before    { background: #fb7185; }

.td-actions { display: flex; align-items: center; justify-content: flex-end; gap: 4px; }
.icon-btn {
  width: 27px; height: 27px; border-radius: 6px;
  border: 1px solid var(--border); background: var(--navy-800);
  display: inline-flex; align-items: center; justify-content: center;
  cursor: pointer; transition: all 0.15s; color: var(--slate-400);
  text-decoration: none;
}
.icon-btn:hover       { border-color: var(--teal-400); color: var(--teal-300); background: var(--teal-glow-sm); }
.icon-btn.danger:hover{ border-color: rgba(244,63,94,0.4); color: #fb7185; background: rgba(244,63,94,0.08); }

/* ── Pagination ── */
.pagination-bar {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 20px; border-top: 1px solid var(--border); background: var(--navy-950);
  flex-wrap: wrap; gap: 8px;
}
.pagination-info { font-family: var(--mono); font-size: 10px; color: var(--slate-400); }
.pagination-controls { display: flex; gap: 4px; }
.page-btn {
  width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
  border-radius: 6px; border: 1px solid var(--border);
  background: var(--white); color: #666;
  font-family: var(--mono); font-size: 12px; cursor: pointer; transition: all 0.15s;
  text-decoration: none;
}
.page-btn:hover  { border-color: var(--grad-start); color: var(--grad-start); }
.page-btn.active { background: var(--grad); border-color: transparent; color: #fff; }

/* ── Modals ── */
.modal-content {
  background: var(--white) !important;
  border: 1px solid var(--border) !important;
  border-radius: 16px !important;
  color: #333;
  box-shadow: 0 24px 80px rgba(0,0,0,0.08) !important;
}
.modal-header {
  background: var(--grad) !important;
  border-bottom: none !important;
  padding: 18px 22px !important;
  border-radius: 16px 16px 0 0 !important;
}
.modal-title {
  font-family: var(--display) !important;
  font-weight: 700 !important;
  font-size: 15px !important;
  color: white !important;
  display: flex; align-items: center; gap: 10px;
}
.modal-icon-wrap {
  width: 32px; height: 32px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.modal-icon-wrap.teal   { background: rgba(64,153,255,0.15);  color: var(--grad-start); }
.modal-icon-wrap.rose   { background: rgba(244,63,94,0.12);  color: #fb7185; }
.modal-icon-wrap.indigo { background: rgba(99,102,241,0.12); color: #a5b4fc; }
.modal-icon-wrap.amber  { background: rgba(245,158,11,0.12); color: var(--amber-light); }
.modal-icon-wrap.green  { background: rgba(16,185,129,0.12); color: #34d399; }
.modal-header .close { color: #666 !important; opacity: 1; }
.modal-header .close:hover { color: #fb7185 !important; }
.modal-backdrop.show { opacity: 0.44 !important; }
.modal-body  { padding: 20px 22px !important; background: transparent !important; }
.modal-footer{
  background: var(--surface) !important;
  border-top: 1px solid var(--border) !important;
  padding: 14px 22px !important;
  border-radius: 0 0 16px 16px !important;
}

/* Form controls inside modals */
.modal-body .form-group label,
.modal-body .form-label {
  font-family: var(--mono) !important;
  font-size: 9px !important;
  letter-spacing: 1px !important;
  text-transform: uppercase !important;
  color: #666 !important;
  margin-bottom: 6px !important;
  display: block;
}
.modal-body .form-control,
.modal-body .form-select,
.modal-body select {
  background: var(--white) !important;
  border: 1px solid var(--border) !important;
  border-radius: 8px !important;
  color: #333 !important;
  font-family: var(--body) !important;
  font-size: 13px !important;
  padding: 9px 12px !important;
  transition: border-color 0.18s, box-shadow 0.18s;
  width: 100%;
}
.modal-body .form-control:focus,
.modal-body select:focus {
  border-color: var(--grad-start) !important;
  box-shadow: 0 0 0 3px rgba(64,153,255,0.1) !important;
  outline: none;
}
.modal-body .form-control::placeholder { color: #999 !important; }
.modal-body textarea.form-control { resize: vertical; min-height: 70px; }
.modal-body .input-group-text {
  background: var(--surface) !important;
  border: 1px solid var(--border) !important;
  color: #666 !important;
  font-family: var(--mono) !important;
  font-size: 11px !important;
}

/* Select2 light override */
.select2-container--bootstrap-5 .select2-selection,
.select2-container--default .select2-selection--single {
  background: var(--white) !important;
  border: 1px solid var(--border) !important;
  color: #333 !important;
  border-radius: 8px !important;
  height: 38px !important;
  padding: 4px 12px !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered { color: #333 !important; line-height: 30px !important; }
.select2-dropdown { background: var(--white) !important; border: 1px solid var(--border) !important; border-radius: 8px !important; }
.select2-results__option { color: #333 !important; font-size: 13px !important; padding: 8px 12px !important; }
.select2-results__option--highlighted { background: rgba(64,153,255,0.12) !important; color: var(--grad-start) !important; }
.select2-search__field { background: var(--surface) !important; border: 1px solid var(--border) !important; color: #333 !important; border-radius: 6px !important; }

/* Modal action buttons */
.btn-modal-primary {
  padding: 8px 20px; border-radius: 8px; border: none;
  font-family: var(--body); font-size: 13px; font-weight: 600; cursor: pointer;
  transition: all 0.18s; display: inline-flex; align-items: center; gap: 7px;
  background: var(--grad); color: #fff; box-shadow: 0 2px 10px rgba(64,153,255,0.2);
}
.btn-modal-primary.teal   { background: linear-gradient(135deg, #17a2b8, #20c997); color: #fff; box-shadow: 0 2px 10px rgba(23,162,184,0.28); }
.btn-modal-primary.rose   { background: rgba(244,63,94,0.14); color: #fb7185; border: 1px solid rgba(244,63,94,0.28); }
.btn-modal-primary.indigo { background: rgba(99,102,241,0.14); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.28); }
.btn-modal-primary.amber  { background: rgba(245,158,11,0.14); color: var(--amber-light); border: 1px solid rgba(245,158,11,0.28); }
.btn-modal-primary.green  { background: rgba(16,185,129,0.14); color: #34d399; border: 1px solid rgba(16,185,129,0.28); }
.btn-modal-primary:hover  { transform: translateY(-1px); }
.btn-modal-cancel {
  padding: 8px 18px; border-radius: 8px; border: 1px solid var(--border);
  background: var(--surface); color: #666; font-family: var(--body);
  font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.15s;
}
.btn-modal-cancel:hover { background: var(--border); color: #333; }

/* ── Toast ── */
.toast-container-sarafi {
  position: fixed; bottom: 24px; right: 24px; z-index: 9999;
  display: flex; flex-direction: column; gap: 8px;
}
.sarafi-toast {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 16px; border-radius: 10px;
  font-family: var(--body); font-size: 13px; font-weight: 500;
  box-shadow: 0 8px 32px rgba(0,0,0,0.12);
  animation: toastIn 0.25s ease;
  min-width: 280px; max-width: 380px;
}
.sarafi-toast.success { background: var(--grad); border: none; color: #fff; }
.sarafi-toast.error   { background: linear-gradient(135deg, #dc3545, #e74c3c); border: none; color: #fff; }
@keyframes toastIn { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }

/* ── Responsive ── */
@media (max-width: 1100px) { .stats-row { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 768px) {
  .sarafi-page { padding: 14px 14px 48px; }
  .stats-row { grid-template-columns: repeat(2,1fr); gap: 8px; }
  .sarafi-heading { flex-direction: column; align-items: flex-start; gap: 12px; }
  .action-bar { gap: 6px; padding: 12px 14px; }
  .action-bar-label, .action-bar-divider { display: none; }
  .action-btn { padding: 7px 11px; font-size: 11px; }
  .kbd { display: none; }
}

/* ── Spinner ── */
@keyframes spin { to { transform: rotate(360deg); } }
.btn-loading .feather { animation: spin 0.8s linear infinite; display: inline-block; }
</style>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="pcoded-content">
      <div class="pcoded-inner-content">
        <div class="main-body">
          <div class="page-wrapper">

            <div class="sarafi-page">

              <!-- ── HEADING ─────────────────────────────────── -->
              <div class="sarafi-heading">
                <div>
                  <h2>
                    <i class="feather icon-credit-card" style="color:var(--teal-400);margin-right:8px;"></i>
                    <?= __('sarafi') ?>
                  </h2>
                  <p class="subtitle"><?= __('manage_sarafi_transactions') ?></p>
                </div>
                <a href="customers.php" class="btn-view-customers">
                  <i class="feather icon-users" style="font-size:13px;"></i>
                  <?= __('view_customers') ?>
                </a>
              </div>

              <!-- ── FLASH MESSAGES ─────────────────────────── -->
              <?php if ($success_message): ?>
              <div class="flash success">
                <i class="feather icon-check-circle"></i>
                <?= htmlspecialchars($success_message) ?>
              </div>
              <?php endif; ?>
              <?php if ($error_message): ?>
              <div class="flash error">
                <i class="feather icon-alert-circle"></i>
                <?= htmlspecialchars($error_message) ?>
              </div>
              <?php endif; ?>

              <!-- ── CURRENCY STAT CARDS ────────────────────── -->
              <?php
              $stat_classes = ['USD'=>'usd','AFS'=>'afs','EUR'=>'eur','AED'=>'aed'];
              $stat_labels  = ['USD'=>'USD '.__('total'),'AFS'=>'AFS '.__('total'),'EUR'=>'EUR '.__('total'),'AED'=>'AED '.__('total')];
              ?>
              <div class="stats-row">
                <?php foreach ($stat_classes as $cur => $cls): ?>
                <div class="stat-card <?= $cls ?>">
                  <div class="stat-label"><?= $stat_labels[$cur] ?></div>
                  <div class="stat-value"><?= isset($currency_totals[$cur]) ? number_format($currency_totals[$cur], 2) : '0.00' ?></div>
                  <span class="stat-badge"><?= $cur ?></span>
                </div>
                <?php endforeach; ?>
                <?php foreach ($currency_totals as $cur => $total): ?>
                  <?php if (!array_key_exists($cur, $stat_classes)): ?>
                  <div class="stat-card aed">
                    <div class="stat-label"><?= htmlspecialchars($cur) ?> <?= __('total') ?></div>
                    <div class="stat-value"><?= number_format($total, 2) ?></div>
                    <span class="stat-badge"><?= htmlspecialchars($cur) ?></span>
                  </div>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>

              <!-- ── ACTION BAR ─────────────────────────────── -->
              <div class="action-bar">
                <span class="action-bar-label"><?= __('quick_actions') ?></span>
                <div class="action-bar-divider"></div>

                <button class="action-btn deposit" data-toggle="modal" data-target="#depositModal">
                  <i class="feather icon-plus-circle"></i>
                  <?= __('new_deposit') ?>
                  <span class="kbd">D</span>
                </button>

                <button class="action-btn withdrawal" data-toggle="modal" data-target="#withdrawalModal">
                  <i class="feather icon-minus-circle"></i>
                  <?= __('new_withdrawal') ?>
                  <span class="kbd">W</span>
                </button>

                <button class="action-btn hawala" data-toggle="modal" data-target="#hawalaModal">
                  <i class="feather icon-repeat"></i>
                  <?= __('hawala_transfer') ?>
                  <span class="kbd">H</span>
                </button>

                <button class="action-btn exchange" data-toggle="modal" data-target="#exchangeModal">
                  <i class="feather icon-refresh-cw"></i>
                  <?= __('currency_exchange') ?>
                  <span class="kbd">E</span>
                </button>

                <div class="action-bar-divider"></div>

                <button class="action-btn new-customer" data-toggle="modal" data-target="#customerModal">
                  <i class="feather icon-user-plus"></i>
                  <?= __('new_customer') ?>
                </button>
              </div>

              <!-- ── TRANSACTION TABLE ──────────────────────── -->
              <div class="table-card">
                <div class="table-card-header">
                  <div class="table-card-title">
                    <h5><?= __('recent_transactions') ?></h5>
                    <span class="tx-count-badge"><?= $total_count ?> <?= __('entries') ?></span>
                  </div>
                  <div class="table-search-wrap">
                    <i class="feather icon-search" style="color:var(--slate-400);font-size:13px;flex-shrink:0;"></i>
                    <input type="text" id="txSearchInput" placeholder="<?= __('search') ?>…">
                  </div>
                </div>

                <div class="table-scroll">
                  <table class="tx-table" id="sarafiTransactionsTable">
                    <thead>
                      <tr>
                        <th><?= __('date') ?></th>
                        <th><?= __('customer') ?></th>
                        <th><?= __('type') ?></th>
                        <th><?= __('amount') ?></th>
                        <th><?= __('currency') ?></th>
                        <th><?= __('reference') ?></th>
                        <th><?= __('status') ?></th>
                        <th><?= __('actions') ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($transactions as $tx):
                        $amtClass = in_array($tx['type'], ['deposit']) ? 'pos' : (in_array($tx['type'], ['withdrawal','hawala_send']) ? 'neg' : '');
                        $amtPrefix = $tx['type'] === 'deposit' ? '+' : ($tx['type'] === 'withdrawal' || $tx['type'] === 'hawala_send' ? '−' : '');
                      ?>
                      <tr>
                        <td>
                          <span class="td-date"><?= date('Y-m-d H:i', strtotime($tx['created_at'])) ?></span>
                        </td>
                        <td>
                          <div class="td-customer">
                            <div class="mini-avatar"><?= mb_strtoupper(mb_substr($tx['customer_name'], 0, 1)) ?></div>
                            <span class="customer-nm"><?= htmlspecialchars($tx['customer_name']) ?></span>
                          </div>
                        </td>
                        <td>
                          <span class="type-badge <?= htmlspecialchars($tx['type']) ?>">
                            <?php
                              $icons = ['deposit'=>'icon-plus-circle','withdrawal'=>'icon-minus-circle','hawala_send'=>'icon-repeat','hawala_receive'=>'icon-repeat','exchange'=>'icon-refresh-cw'];
                              $icon = $icons[$tx['type']] ?? 'icon-circle';
                            ?>
                            <i class="feather <?= $icon ?>" style="font-size:10px;"></i>
                            <?= __($tx['type']) ?>
                          </span>
                        </td>
                        <td>
                          <span class="td-amount <?= $amtClass ?>">
                            <?= $amtPrefix ?><?= number_format($tx['amount'], 2) ?>
                          </span>
                        </td>
                        <td>
                          <span class="currency-chip"><?= htmlspecialchars($tx['currency']) ?></span>
                        </td>
                        <td>
                          <span class="td-ref"><?= htmlspecialchars($tx['reference_number']) ?></span>
                        </td>
                        <td>
                          <span class="status-badge <?= $tx['status'] ?>">
                            <?= __($tx['status']) ?>
                          </span>
                        </td>
                        <td>
                          <div class="td-actions">
                            <?php if (!empty($tx['receipt_path'])): ?>
                            <a href="../uploads/receipts/<?= htmlspecialchars($tx['receipt_path']) ?>"
                               class="icon-btn" target="_blank" title="<?= __('view_receipt') ?>">
                              <i class="feather icon-file" style="font-size:12px;"></i>
                            </a>
                            <?php endif; ?>

                            <button class="icon-btn view-transaction"
                                    data-id="<?= $tx['id'] ?>" type="button" title="<?= __('view_details') ?>">
                              <i class="feather icon-eye" style="font-size:12px;"></i>
                            </button>

                            <?php if ($tx['type'] === 'deposit'): ?>
                            <button class="icon-btn danger"
                                    onclick="deleteDeposit(<?= $tx['id'] ?>, <?= $tx['amount'] ?>)"
                                    title="<?= __('delete') ?>">
                              <i class="feather icon-trash-2" style="font-size:12px;"></i>
                            </button>
                            <?php elseif ($tx['type'] === 'withdrawal'): ?>
                            <button class="icon-btn danger"
                                    onclick="deleteWithdrawal(<?= $tx['id'] ?>, <?= $tx['amount'] ?>)"
                                    title="<?= __('delete') ?>">
                              <i class="feather icon-trash-2" style="font-size:12px;"></i>
                            </button>
                            <?php elseif ($tx['type'] === 'hawala_send'): ?>
                            <button class="icon-btn danger"
                                    onclick="deleteHawala(<?= $tx['id'] ?>, <?= $tx['amount'] ?>)"
                                    title="<?= __('delete') ?>">
                              <i class="feather icon-trash-2" style="font-size:12px;"></i>
                            </button>
                            <?php elseif ($tx['type'] === 'exchange'): ?>
                            <button class="icon-btn danger delete-exchange"
                                    data-id="<?= $tx['id'] ?>" title="<?= __('delete') ?>">
                              <i class="feather icon-trash-2" style="font-size:12px;"></i>
                            </button>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-bar">
                  <span class="pagination-info">
                    <?= __('showing') ?> <?= $offset + 1 ?>–<?= min($offset + $limit, $total_count) ?>
                    <?= __('of') ?> <?= $total_count ?> <?= __('entries') ?>
                  </span>
                  <div class="pagination-controls">
                    <?php if ($page > 1): ?>
                    <a class="page-btn" href="?page=<?= $page - 1 ?>">‹</a>
                    <?php endif; ?>
                    <?php
                      $sp = max(1, $page - 2);
                      $ep = min($total_pages, $page + 2);
                      for ($i = $sp; $i <= $ep; $i++):
                    ?>
                    <a class="page-btn <?= $i == $page ? 'active' : '' ?>" href="?page=<?= $i ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                    <a class="page-btn" href="?page=<?= $page + 1 ?>">›</a>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endif; ?>
              </div>
              <!-- /table-card -->

            </div><!-- /sarafi-page -->
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Toast container -->
<div class="toast-container-sarafi" id="toastContainer"></div>

<!-- ── TRANSACTION DETAILS MODAL ────────────────── -->
<div class="modal fade" id="transactionDetailsModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <div class="modal-icon-wrap teal"><i class="feather icon-file-text"></i></div>
          <?= __('transaction_details') ?>
        </h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div id="transactionDetailsContent">
          <div class="text-center py-4">
            <div class="spinner-border text-info" role="status"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-modal-cancel" data-dismiss="modal"><?= __('close') ?></button>
        <button type="button" class="btn-modal-primary teal print-transaction" style="display:none;">
          <i class="feather icon-printer"></i><?= __('print') ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── EDIT TRANSACTION MODAL ───────────────────── -->
<div class="modal fade" id="editTransactionModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <div class="modal-icon-wrap amber"><i class="feather icon-edit-2"></i></div>
          <?= __('edit_transaction') ?>
        </h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <form id="editTransactionForm">
        <div class="modal-body">
          <input type="hidden" id="editTransactionId"   name="transaction_id">
          <input type="hidden" id="editTransactionType" name="transaction_type">
          <input type="hidden" id="editCustomerId"      name="customer_id">
          <input type="hidden" id="editMainAccountId"   name="main_account_id">
          <input type="hidden" id="editOriginalAmount"  name="original_amount">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label><?= __('customer') ?></label>
                <input type="text" class="form-control" id="editCustomerName" readonly>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label><?= __('amount') ?></label>
                <input type="number" step="0.01" min="0" class="form-control" id="editAmount" name="amount" required>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label><?= __('reference') ?></label>
            <input type="text" class="form-control" id="editReference" name="reference">
          </div>
          <div class="form-group">
            <label><?= __('notes') ?></label>
            <textarea class="form-control" id="editNotes" name="notes" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-modal-cancel" data-dismiss="modal"><?= __('cancel') ?></button>
          <button type="submit" class="btn-modal-primary amber">
            <i class="feather icon-save"></i><?= __('save_changes') ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Required JS -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<!-- Include existing modals (deposit, withdrawal, hawala, exchange, customer) -->
<?php include 'includes/sarafi_modals.php'; ?>



<script>
// ── Toast system ────────────────────────────────────────────────────────────
function showToast(message, type = 'success') {
  const icons = { success: 'icon-check-circle', error: 'icon-alert-circle', warning: 'icon-alert-triangle' };
  const toast = document.createElement('div');
  toast.className = `sarafi-toast ${type}`;
  toast.innerHTML = `<i class="feather ${icons[type] || icons.success}" style="font-size:16px;flex-shrink:0;"></i><span style="flex:1;">${message}</span>`;
  document.getElementById('toastContainer').appendChild(toast);
  setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateY(12px)'; toast.style.transition = 'all 0.3s'; setTimeout(() => toast.remove(), 300); }, 4500);
}

// ── Keyboard shortcuts ───────────────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
  if (['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName)) return;
  if (e.key === 'Escape') return;
  const map = { 'd': '#depositModal', 'w': '#withdrawalModal', 'h': '#hawalaModal', 'e': '#exchangeModal' };
  const target = map[e.key.toLowerCase()];
  if (target) $(target).modal('show');
});

// ── Select2 init ─────────────────────────────────────────────────────────────
function initializeSelect2() {
  $('select[name="customer_id"], select[name="sender_id"]').each(function() {
    if (!$(this).hasClass('select2-hidden-accessible')) {
      $(this).select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $(this).closest('.modal'),
        placeholder: '<?= __("select_customer") ?>',
        allowClear: true
      });
    }
  });
}

$(document).ready(function() {
  initializeSelect2();
  $('.modal').on('shown.bs.modal', function() { initializeSelect2(); });

  // Show flash toasts
  <?php if ($success_message): ?>
  showToast(<?= json_encode($success_message) ?>, 'success');
  <?php endif; ?>
  <?php if ($error_message): ?>
  showToast(<?= json_encode($error_message) ?>, 'error');
  <?php endif; ?>

  // View transaction
  $(document).on('click', '.view-transaction', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const transactionId = $(this).data('id');
    console.log('View transaction clicked, ID:', transactionId);
    viewTransaction(transactionId);
  });

  // Delete exchange
  $(document).on('click', '.delete-exchange', function(e) {
    e.preventDefault();
    const id = $(this).data('id');
    if (!confirm('<?= __("confirm_delete_exchange") ?>')) return;
    $.ajax({
      url: 'delete_sarafi_exchange.php', type: 'POST',
      data: { transaction_id: id }, dataType: 'json',
      success: r => handleAjaxSuccess(r, '<?= __("exchange_deleted_successfully") ?>'),
      error:   () => showToast('<?= __("error_deleting_exchange") ?>', 'error')
    });
  });

  // Edit form submit
  $(document).on('submit', '#editTransactionForm', function(e) {
    e.preventDefault();
    const type = $('#editTransactionType').val();
    const urlMap = { deposit: 'update_sarafi_deposit_transaction.php', withdrawal: 'update_sarafi_withdrawal_transaction.php' };
    if (!urlMap[type]) { showToast('<?= __("unsupported_transaction_type") ?>', 'error'); return; }
    const btn = $(this).find('button[type="submit"]');
    const orig = btn.html();
    btn.prop('disabled', true).html('<i class="feather icon-loader btn-loading"></i> <?= __("saving") ?>');
    $.ajax({
      url: urlMap[type], type: 'POST', data: new FormData(this),
      processData: false, contentType: false,
      success: r => {
        btn.prop('disabled', false).html(orig);
        const res = typeof r === 'string' ? JSON.parse(r) : r;
        if (res.success) { showToast('<?= __("transaction_updated_successfully") ?>', 'success'); $('#editTransactionModal').modal('hide'); setTimeout(() => location.reload(), 800); }
        else showToast(res.message || '<?= __("error_updating_transaction") ?>', 'error');
      },
      error: () => { btn.prop('disabled', false).html(orig); showToast('<?= __("error_updating_transaction") ?>', 'error'); }
    });
  });

  // Inline search filter (client-side)
  $('#txSearchInput').on('input', function() {
    const q = this.value.toLowerCase();
    $('#sarafiTransactionsTable tbody tr').each(function() {
      $(this).toggle($(this).text().toLowerCase().includes(q));
    });
  });

  // Button double-submit protection
  $('form').on('submit', function() {
    const btn = $(this).find('button[type="submit"]');
    if (btn.data('submitting')) return false;
    btn.data('submitting', true);
    setTimeout(() => btn.data('submitting', false), 5000);
  });
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function handleAjaxSuccess(r, msg) {
  if (r.success) { showToast(msg || r.message, 'success'); setTimeout(() => location.reload(), 800); }
  else showToast(r.message || '<?= __("operation_failed") ?>', 'error');
}

function deleteDeposit(id, amount) {
  if (!confirm('<?= __("confirm_delete_deposit") ?>')) return;
  fetch('delete_sarafi_deposit.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`transaction_id=${id}&amount=${amount}` })
    .then(r => r.json()).then(d => handleAjaxSuccess(d, '<?= __("deposit_deleted_successfully") ?>')).catch(() => showToast('<?= __("error_deleting_deposit") ?>', 'error'));
}

function deleteWithdrawal(id, amount) {
  if (!confirm('<?= __("confirm_delete_withdrawal") ?>')) return;
  fetch('delete_sarafi_withdrawal.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`transaction_id=${id}&amount=${amount}` })
    .then(r => r.json()).then(d => handleAjaxSuccess(d, '<?= __("withdrawal_deleted_successfully") ?>')).catch(() => showToast('<?= __("error_deleting_withdrawal") ?>', 'error'));
}

function deleteHawala(id, amount) {
  if (!confirm('<?= __("confirm_delete_hawala") ?>')) return;
  fetch('delete_sarafi_hawala.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`transaction_id=${id}&amount=${amount}` })
    .then(r => r.json()).then(d => handleAjaxSuccess(d, '<?= __("hawala_deleted_successfully") ?>')).catch(() => showToast('<?= __("error_deleting_hawala") ?>', 'error'));
}

// ── View transaction details ───────────────────────────────────────────────
function viewTransaction(transactionId) {
  $('#transactionDetailsModal').modal('show');
  $('.print-transaction').hide();
  $('#transactionDetailsContent').html('<div class="text-center py-4"><div class="spinner-border text-info"></div></div>');
  $.ajax({
    url: 'view_sarafi_transaction.php', type: 'GET',
    data: { id: transactionId }, dataType: 'json',
    success: function(response) {
      if (!response.success) {
        $('#transactionDetailsContent').html(`<div class="flash error"><i class="feather icon-alert-circle"></i>${response.message || '<?= __("error_loading_transaction_details") ?>'}</div>`); return;
      }
      const d = response.data;
      const typeBadge = `<span class="type-badge ${d.transaction.type}">${d.transaction.type.replace('_',' ')}</span>`;
      const statusBadge = `<span class="status-badge ${d.transaction.status}">${d.transaction.status}</span>`;
      let html = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
          <div>
            <div style="font-family:var(--mono);font-size:9px;letter-spacing:1px;text-transform:uppercase;color:var(--slate-400);margin-bottom:8px;"><?= __("customer_information") ?></div>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
              <div class="mini-avatar" style="width:36px;height:36px;font-size:14px;">${d.customer.name.charAt(0).toUpperCase()}</div>
              <div><div style="font-weight:600;color:var(--white);">${d.customer.name}</div><div style="font-size:11px;color:var(--slate-400);">${d.customer.phone || '—'}</div></div>
            </div>
            <div style="font-size:12px;color:var(--slate-400);"><?= __("wallet_balance") ?></div>
            <div style="font-family:var(--mono);font-weight:500;color:var(--white);">${d.transaction.currency} ${parseFloat(d.customer.wallet_balance || 0).toFixed(2)}</div>
          </div>
          <div>
            <div style="font-family:var(--mono);font-size:9px;letter-spacing:1px;text-transform:uppercase;color:var(--slate-400);margin-bottom:8px;"><?= __("transaction_information") ?></div>
            <table style="width:100%;font-size:12px;border-collapse:collapse;">
              <tr><td style="color:var(--slate-400);padding:4px 0;width:45%"><?= __("type") ?></td><td>${typeBadge}</td></tr>
              <tr><td style="color:var(--slate-400);padding:4px 0"><?= __("amount") ?></td><td style="font-family:var(--mono);font-weight:500;color:var(--white);">${d.transaction.currency} ${parseFloat(d.transaction.amount).toFixed(2)}</td></tr>
              <tr><td style="color:var(--slate-400);padding:4px 0"><?= __("reference") ?></td><td style="font-family:var(--mono);font-size:11px;">${d.transaction.reference_number}</td></tr>
              <tr><td style="color:var(--slate-400);padding:4px 0"><?= __("status") ?></td><td>${statusBadge}</td></tr>
              <tr><td style="color:var(--slate-400);padding:4px 0"><?= __("date") ?></td><td style="font-family:var(--mono);font-size:11px;">${new Date(d.transaction.created_at).toLocaleString()}</td></tr>
              ${d.transaction.notes ? `<tr><td style="color:var(--slate-400);padding:4px 0"><?= __("notes") ?></td><td>${d.transaction.notes}</td></tr>` : ''}
            </table>
          </div>
        </div>`;
      if (d.transaction.type === 'hawala_send' && d.hawala) {
        html += `<hr style="border-color:var(--border);margin:16px 0;">
          <div style="font-family:var(--mono);font-size:9px;letter-spacing:1px;text-transform:uppercase;color:var(--slate-400);margin-bottom:8px;"><?= __("hawala_details") ?></div>
          <table style="width:100%;font-size:12px;border-collapse:collapse;">
            <tr><td style="color:var(--slate-400);padding:4px 0;width:40%"><?= __("commission") ?></td><td style="font-family:var(--mono);">${d.hawala.commission_currency} ${parseFloat(d.hawala.commission_amount).toFixed(2)}</td></tr>
            <tr><td style="color:var(--slate-400);padding:4px 0"><?= __("secret_code") ?></td><td style="font-family:var(--mono);">${d.hawala.secret_code}</td></tr>
            <tr><td style="color:var(--slate-400);padding:4px 0"><?= __("receiver") ?></td><td>${d.hawala.receiver.name}</td></tr>
            <tr><td style="color:var(--slate-400);padding:4px 0"><?= __("receiver_phone") ?></td><td>${d.hawala.receiver.phone}</td></tr>
          </table>`;
      }
      if (d.transaction.receipt_path) {
        html += `<hr style="border-color:var(--border);margin:16px 0;">
          <div style="font-family:var(--mono);font-size:9px;letter-spacing:1px;text-transform:uppercase;color:var(--slate-400);margin-bottom:8px;"><?= __("receipt") ?></div>
          <img src="../uploads/receipts/${d.transaction.receipt_path}" style="max-width:100%;border-radius:8px;cursor:zoom-in;" onclick="window.open(this.src)">
          `;
      }
      $('#transactionDetailsContent').html(html);
      $('.print-transaction').show();
    },
    error: () => $('#transactionDetailsContent').html('<div class="flash error"><i class="feather icon-alert-circle"></i><?= __("error_loading_transaction_details") ?></div>')
  });
}

function editTransaction(transactionId) {
  $.ajax({
    url: 'view_sarafi_transaction.php', type: 'GET', data: { id: transactionId }, dataType: 'json',
    success: r => {
      if (!r.success) { showToast(r.message, 'error'); return; }
      const { transaction: tx, customer, main_account } = r.data;
      $('#editTransactionId').val(tx.id);
      $('#editTransactionType').val(tx.type);
      $('#editCustomerId').val(customer.id);
      $('#editCustomerName').val(customer.name);
      $('#editMainAccountId').val(main_account.id);
      $('#editAmount').val(parseFloat(tx.amount).toFixed(2));
      $('#editOriginalAmount').val(parseFloat(tx.amount).toFixed(2));
      $('#editReference').val(tx.reference_number || '');
      $('#editNotes').val(tx.notes || '');
      $('#editTransactionModal').modal('show');
    },
    error: () => showToast('<?= __("error_loading_transaction_details") ?>', 'error')
  });
}

// Print
$(document).on('click', '.print-transaction', function() {
  const content = document.getElementById('transactionDetailsContent').innerHTML;
  const w = window.open('', '_blank');
  w.document.write(`<html><head><title><?= __("transaction_details") ?></title><style>body{font-family:sans-serif;padding:24px;color:#111;}.type-badge,.status-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:12px;}.mini-avatar{display:inline-flex;width:30px;height:30px;border-radius:50%;background:#0891b2;color:#fff;align-items:center;justify-content:center;font-weight:700;}</style></head><body>${content}<br><button onclick="window.print()">Print</button></body></html>`);
  w.document.close();
});
</script>

<?php include '../includes/admin_footer.php'; ?>
</body>
</html>