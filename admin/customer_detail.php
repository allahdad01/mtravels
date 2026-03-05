<?php
require_once '../includes/db.php';
require_once 'security.php';
require_once '../includes/language_helpers.php';
require_once '../includes/SecureFileUpload.php';
require_once '../includes/InputValidator.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    error_log("Unauthorized access attempt to dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - Role: " . ($_SESSION['role'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Validate customer ID parameter
$customerId = InputValidator::getInt($_GET['id'] ?? '', 0, 1);

// Build redirect URL with current query parameters
$redirect_url = $_SERVER['PHP_SELF'] . '?id=' . $customerId;

// Debug POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('POST Data: ' . print_r($_POST, true));
    error_log('FILES Data: ' . print_r($_FILES, true));
}

// Handle deposit submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_deposit'])) {
    error_log('Processing deposit...');
    
    try {
        $customer_id = InputValidator::getInt($_POST['customer_id'] ?? '', 0, 1);
        $amount = InputValidator::getString($_POST['amount'] ?? '', 20);
        $currency = InputValidator::getEnum(
            $_POST['currency'] ?? '',
            ['USD', 'EUR', 'AFS', 'DARHAM', 'PKR', 'INR'],
            'USD'
        );
        $notes = InputValidator::getString($_POST['notes'] ?? '', 500);
        $reference = InputValidator::getString($_POST['reference'] ?? '', 100);

        error_log("Deposit data - Customer: $customer_id, Amount: $amount, Currency: $currency, Reference: $reference");

        $pdo->beginTransaction();

        // Insert the deposit transaction
        $stmt = $pdo->prepare("INSERT INTO sarafi_transactions (customer_id, amount, currency, type, notes, reference_number, status, tenant_id, branch_id) VALUES (?, ?, ?, 'deposit', ?, ?, 'completed', ?, ?)");
        $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $amount, PDO::PARAM_STR);
        $stmt->bindParam(3, $currency, PDO::PARAM_STR);
        $stmt->bindParam(4, $notes, PDO::PARAM_STR);
        $stmt->bindParam(5, $reference, PDO::PARAM_STR);
        $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            throw new Exception(__("error_inserting_transaction"));
        }

        $transaction_id = $pdo->lastInsertId();
        error_log("Transaction created with ID: $transaction_id");

        // First check if wallet exists
        $stmt = $pdo->prepare("SELECT id FROM customer_wallets WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $currency, PDO::PARAM_STR);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll();

        if (count($result) > 0) {
            $stmt = $pdo->prepare("UPDATE customer_wallets SET balance = balance + ? WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->bindParam(1, $amount, PDO::PARAM_STR);
            $stmt->bindParam(2, $customer_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $currency, PDO::PARAM_STR);
            $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare("INSERT INTO customer_wallets (customer_id, currency, balance, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $currency, PDO::PARAM_STR);
            $stmt->bindParam(3, $amount, PDO::PARAM_STR);
            $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        }

        if (!$stmt->execute()) {
            throw new Exception(__("error_updating_wallet"));
        }

        error_log("Wallet updated successfully");

        // Handle receipt upload if provided
        if (isset($_FILES['receipt'])) {
            $uploader = new SecureFileUpload(10 * 1024 * 1024, '../uploads/');
            $result = $uploader->upload('receipt', 'receipts');
            
            if ($result['success']) {
                $receipt_filename = $result['data']['filename'];
                $stmt = $pdo->prepare("UPDATE sarafi_transactions SET receipt_path = ? WHERE id = ? AND tenant_id = ?");
                $stmt->bindParam(1, $receipt_filename, PDO::PARAM_STR);
                $stmt->bindParam(2, $transaction_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                if (!$stmt->execute()) {
                    throw new Exception(__("error_updating_receipt_path"));
                }
                error_log("Receipt uploaded successfully: $receipt_filename");
            } else {
                error_log("Receipt upload failed: " . $result['error']);
            }
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Deposit processed successfully!";
        error_log("Deposit completed successfully");
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Deposit error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error processing deposit: " . $e->getMessage();
    }
    
    header('Location: ' . $redirect_url);
    exit();
}

// Handle withdrawal submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_withdrawal'])) {
    $customer_id = InputValidator::getInt($_POST['customer_id'] ?? '', 0, 1);
    $amount = InputValidator::getString($_POST['amount'] ?? '', 20);
    $currency = InputValidator::getEnum(
        $_POST['currency'] ?? '',
        ['USD', 'EUR', 'AFS', 'DARHAM', 'PKR', 'INR'],
        'USD'
    );
    $notes = InputValidator::getString($_POST['notes'] ?? '', 500);
    $reference = InputValidator::getString($_POST['reference'] ?? '', 100);
    
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT balance FROM customer_wallets WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $currency, PDO::PARAM_STR);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $wallet = $stmt->fetch();

        if (!$wallet || $wallet['balance'] < $amount) {
            throw new Exception(__("insufficient_balance"));
        }

        $stmt = $pdo->prepare("INSERT INTO sarafi_transactions (customer_id, amount, currency, type, notes, reference_number, tenant_id, branch_id) VALUES (?, ?, ?, 'withdrawal', ?, ?, ?, ?)");
        $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $amount, PDO::PARAM_STR);
        $stmt->bindParam(3, $currency, PDO::PARAM_STR);
        $stmt->bindParam(4, $notes, PDO::PARAM_STR);
        $stmt->bindParam(5, $reference, PDO::PARAM_STR);
        $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $transaction_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("UPDATE customer_wallets SET balance = balance - ? WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $amount, PDO::PARAM_STR);
        $stmt->bindParam(2, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $currency, PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        // Handle receipt upload if provided
        if (isset($_FILES['receipt'])) {
            $uploader = new SecureFileUpload(10 * 1024 * 1024, '../uploads/');
            $result = $uploader->upload('receipt', 'receipts');
            
            if ($result['success']) {
                $receipt_filename = $result['data']['filename'];
                $stmt = $pdo->prepare("UPDATE sarafi_transactions SET receipt_path = ? WHERE id = ? AND tenant_id = ?");
                $stmt->bindParam(1, $receipt_filename, PDO::PARAM_STR);
                $stmt->bindParam(2, $transaction_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt->execute();
                error_log("Withdrawal receipt uploaded: $receipt_filename");
            } else {
                error_log("Withdrawal receipt upload failed: " . $result['error']);
            }
        }

        $pdo->commit();
        $_SESSION['success_message'] = __("withdrawal_processed_successfully");
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = __("error_processing_withdrawal") . ": " . $e->getMessage();
    }
    
    header('Location: ' . $redirect_url);
    exit();
}

// Validate customer ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = __("invalid_customer_id");
    header('Location: customers.php');
    exit();
}

$customer_id = intval($_GET['id']);

// Fetch customer details
$stmt = $pdo->prepare("
    SELECT c.*,
           GROUP_CONCAT(DISTINCT CONCAT(w.currency, ':', w.balance) SEPARATOR ',') as wallet_balances
    FROM customers c
    LEFT JOIN customer_wallets w ON c.id = w.customer_id AND w.tenant_id = c.tenant_id AND w.branch_id = c.branch_id
    WHERE c.id = ? AND c.tenant_id = ? AND c.branch_id = ?
    GROUP BY c.id
");
$stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$customer = $stmt->fetch();

if (!$customer) {
    $_SESSION['error_message'] = __("customer_not_found");
    header('Location: customers.php');
    exit();
}

// Parse wallet balances
$wallets = [];
if ($customer['wallet_balances']) {
    foreach (explode(',', $customer['wallet_balances']) as $wallet) {
        list($currency, $balance) = explode(':', $wallet);
        $wallets[$currency] = $balance;
    }
}

// Fetch recent transactions
$stmt = $pdo->prepare("
    SELECT t.*,
           CASE
               WHEN t.type = 'hawala_send' THEN (
                   SELECT CONCAT('Code: ', h.secret_code)
                   FROM hawala_transfers h
                   WHERE h.sender_transaction_id = t.id
               )
               WHEN t.type = 'exchange' THEN (
                   SELECT CONCAT(e.to_currency, ' ', e.to_amount)
                   FROM exchange_transactions e
                   WHERE e.transaction_id = t.id
               )
               ELSE NULL
           END as additional_info
    FROM sarafi_transactions t
    WHERE t.customer_id = ? AND t.tenant_id = ? AND t.branch_id = ?
    ORDER BY t.created_at DESC
    LIMIT 100
");
$stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$transactions = $stmt->fetchAll();

// Fetch main account data for these transactions
$transaction_ids = array_column($transactions, 'id');
$main_account_data = [];

if (!empty($transaction_ids)) {
    $placeholders = str_repeat('?,', count($transaction_ids) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT mat.reference_id, ma.name
        FROM main_account_transactions mat
        JOIN main_account ma ON mat.main_account_id = ma.id
        WHERE mat.reference_id IN ($placeholders) AND mat.tenant_id = ? AND mat.branch_id = ?
    ");

    foreach ($transaction_ids as $index => $id) {
        $stmt->bindParam($index + 1, $transaction_ids[$index], PDO::PARAM_INT);
    }
    $stmt->bindParam(count($transaction_ids) + 1, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(count($transaction_ids) + 2, $branch_id, PDO::PARAM_INT);

    $stmt->execute();
    $result = $stmt->fetchAll();

    foreach ($result as $row) {
        $main_account_data[$row['reference_id']] = $row['name'];
    }
}

// Calculate transaction statistics
$stats = [
    'total_deposits' => 0,
    'total_withdrawals' => 0,
    'total_hawala_sent' => 0,
    'total_hawala_received' => 0,
    'total_exchanges' => 0
];

foreach ($transactions as $transaction) {
    switch ($transaction['type']) {
        case 'deposit':
            $stats['total_deposits']++;
            break;
        case 'withdrawal':
            $stats['total_withdrawals']++;
            break;
        case 'hawala_send':
            $stats['total_hawala_sent']++;
            break;
        case 'hawala_receive':
            $stats['total_hawala_received']++;
            break;
        case 'exchange':
            $stats['total_exchanges']++;
            break;
    }
}

// Initialize messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear session messages
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// RTL detection
$isRTL = in_array($_SESSION['lang'] ?? 'en', ['fa', 'ps']);

// Currency accent colors
$currencyColors = [
    'USD' => '#27ae60',
    'EUR' => '#2980b9',
    'AFS' => '#8e44ad',
    'DARHAM' => '#d4a017',
    'PKR' => '#d35400',
    'INR' => '#c0392b'
];
?>

<?php include '../includes/header.php'; ?>

<link rel="stylesheet" href="../css/general/modal-styles.css">

<style>
/* ============================================
   CUSTOMER DETAIL PAGE - REDESIGNED STYLES
   ============================================ */

/* Page Header / Breadcrumb */
.page-breadcrumb {
    padding: 16px 0 8px;
    margin-bottom: 8px;
}

.page-breadcrumb a {
    color: #8898aa;
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.2s ease;
}

.page-breadcrumb a:hover {
    color: #4680ff;
}

.page-breadcrumb .separator {
    margin: 0 8px;
    color: #ccc;
}

.page-breadcrumb .current-page {
    color: #333;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Customer Profile Card */
.customer-profile-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    overflow: hidden;
}

.customer-profile-card .card-body {
    padding: 24px;
}

.customer-avatar {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4680ff, #6c5ce7);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 auto 12px;
    letter-spacing: 1px;
    box-shadow: 0 4px 14px rgba(70, 128, 255, 0.3);
}

.customer-name {
    font-size: 1.2rem;
    font-weight: 700;
    color: #2d3436;
    margin-bottom: 4px;
}

.customer-phone {
    font-size: 0.875rem;
    color: #8898aa;
}

.customer-info-list {
    list-style: none;
    padding: 0;
    margin: 16px 0 0;
}

.customer-info-list li {
    display: flex;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f5f5f5;
    font-size: 0.875rem;
    color: #636e72;
}

.customer-info-list li:last-child {
    border-bottom: none;
}

.customer-info-list li i {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border-radius: 8px;
    margin-right: 12px;
    color: #4680ff;
    font-size: 0.85rem;
    flex-shrink: 0;
}

/* Wallet Balances Card */
.wallet-balances-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
}

.wallet-balances-card .card-header {
    background: transparent;
    border-bottom: 1px solid #f0f0f0;
    padding: 16px 20px;
}

.wallet-balances-card .card-header h5 {
    font-size: 0.95rem;
    font-weight: 600;
    color: #2d3436;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.wallet-balances-card .card-header h5 i {
    color: #4680ff;
}

.wallet-balances-card .card-body {
    padding: 16px 20px;
}

.wallet-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-radius: 12px;
    padding: 14px 16px;
    margin-bottom: 10px;
    border-left: 4px solid #4680ff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.wallet-card:last-child {
    margin-bottom: 0;
}

.wallet-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

.wallet-currency-label {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    color: #6c757d;
    letter-spacing: 0.5px;
}

.wallet-currency-sub {
    font-size: 0.7rem;
    color: #adb5bd;
    margin-top: 2px;
}

.wallet-balance-amount {
    font-size: 1.3rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.wallet-balance-amount.positive {
    color: #2ecc71;
}

.wallet-balance-amount.negative {
    color: #e74c3c;
}

.wallet-empty {
    text-align: center;
    padding: 24px 16px;
    color: #adb5bd;
}

.wallet-empty i {
    font-size: 2rem;
    margin-bottom: 8px;
    display: block;
}

/* Stat Pills - Horizontal */
.stats-row {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.stat-pill {
    flex: 1;
    min-width: 120px;
    background: white;
    border-radius: 12px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    border: 1px solid #f0f0f0;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-pill:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-pill-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.stat-pill-icon.deposits {
    color: #2ecc71;
    background: #e8f8f0;
}

.stat-pill-icon.withdrawals {
    color: #e67e22;
    background: #fef5e7;
}

.stat-pill-icon.hawala {
    color: #3498db;
    background: #ebf5fb;
}

.stat-pill-icon.exchanges {
    color: #9b59b6;
    background: #f4ecf7;
}

.stat-pill-info {
    display: flex;
    flex-direction: column;
}

.stat-pill-count {
    font-size: 1.3rem;
    font-weight: 700;
    color: #2d3436;
    line-height: 1;
}

.stat-pill-label {
    font-size: 0.7rem;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-top: 4px;
}

/* Transaction History Card */
.transaction-history-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    overflow: hidden;
}

.transaction-history-card .card-header {
    background: transparent;
    border-bottom: 1px solid #f0f0f0;
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.transaction-history-card .card-header .header-title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.transaction-history-card .card-header .header-title h5 {
    font-size: 0.95rem;
    font-weight: 600;
    color: #2d3436;
    margin: 0;
}

.transaction-history-card .card-header .header-title i {
    color: #4680ff;
}

.header-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.header-actions .btn {
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 8px 16px;
    display: flex;
    align-items: center;
    gap: 6px;
    border: none;
    transition: all 0.2s ease;
}

.header-actions .btn-deposit {
    background: linear-gradient(135deg, #2ecc71, #27ae60);
    color: white;
}

.header-actions .btn-deposit:hover {
    background: linear-gradient(135deg, #27ae60, #219a52);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(46, 204, 113, 0.3);
}

.header-actions .btn-withdrawal {
    background: linear-gradient(135deg, #e67e22, #d35400);
    color: white;
}

.header-actions .btn-withdrawal:hover {
    background: linear-gradient(135deg, #d35400, #c0392b);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(230, 126, 34, 0.3);
}

/* Alert Messages */
.alert-custom {
    border: none;
    border-radius: 12px;
    padding: 12px 16px;
    margin: 16px 20px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.875rem;
    font-weight: 500;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert-custom.alert-success {
    background: #e8f8f0;
    color: #1e8449;
}

.alert-custom.alert-danger {
    background: #fde8e8;
    color: #c0392b;
}

/* Transaction Table */
.transaction-table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.transaction-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin: 0;
}

.transaction-table thead th {
    padding: 12px 16px;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #8898aa;
    background: #fafbfc;
    border-bottom: 2px solid #e9ecef;
    font-weight: 600;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 1;
}

.transaction-table tbody tr {
    border-left: 3px solid transparent;
    transition: background-color 0.15s ease;
}

.transaction-table tbody tr:hover {
    background-color: #f8f9fe;
}

.transaction-table tbody tr[data-type="deposit"] {
    border-left-color: #2ecc71;
}

.transaction-table tbody tr[data-type="withdrawal"] {
    border-left-color: #e67e22;
}

.transaction-table tbody tr[data-type="hawala_send"],
.transaction-table tbody tr[data-type="hawala_receive"] {
    border-left-color: #3498db;
}

.transaction-table tbody tr[data-type="exchange"] {
    border-left-color: #9b59b6;
}

.transaction-table tbody td {
    padding: 12px 16px;
    vertical-align: middle;
    font-size: 0.85rem;
    border-bottom: 1px solid #f5f5f5;
    color: #636e72;
}

.transaction-table tbody tr:last-child td {
    border-bottom: none;
}

/* Transaction Date Cell */
.tx-date {
    white-space: nowrap;
    font-size: 0.8rem;
}

.tx-date .date-main {
    font-weight: 600;
    color: #2d3436;
}

.tx-date .date-time {
    font-size: 0.7rem;
    color: #adb5bd;
    display: block;
    margin-top: 2px;
}

/* Transaction Type Badge */
.tx-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}

.tx-type-badge.type-deposit {
    background: #e8f8f0;
    color: #1e8449;
}

.tx-type-badge.type-withdrawal {
    background: #fef5e7;
    color: #d35400;
}

.tx-type-badge.type-hawala_send,
.tx-type-badge.type-hawala_receive {
    background: #ebf5fb;
    color: #2471a3;
}

.tx-type-badge.type-exchange {
    background: #f4ecf7;
    color: #7d3c98;
}

.tx-type-badge i {
    font-size: 0.8rem;
}

.tx-additional-info {
    font-size: 0.7rem;
    color: #adb5bd;
    display: block;
    margin-top: 4px;
}

/* Amount Cell */
.tx-amount {
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.tx-amount .amount-value {
    font-size: 0.9rem;
    color: #2d3436;
}

.tx-amount .amount-currency {
    font-size: 0.7rem;
    color: #8898aa;
    font-weight: 500;
    margin-left: 4px;
}

/* Status Badge */
.tx-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    white-space: nowrap;
}

.tx-status.status-completed {
    background: #e8f8f0;
    color: #1e8449;
}

.tx-status.status-pending {
    background: #fef9e7;
    color: #d4ac0d;
}

.tx-status.status-cancelled {
    background: #fde8e8;
    color: #c0392b;
}

.tx-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

.status-completed .tx-status-dot {
    background: #1e8449;
}

.status-pending .tx-status-dot {
    background: #d4ac0d;
}

.status-cancelled .tx-status-dot {
    background: #c0392b;
}

/* Paid To Cell */
.tx-paid-to {
    font-size: 0.8rem;
    color: #636e72;
    display: flex;
    align-items: center;
    gap: 6px;
}

.tx-paid-to i {
    color: #adb5bd;
    font-size: 0.8rem;
}

/* Notes Tooltip */
.tx-notes {
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.8rem;
    color: #adb5bd;
    cursor: help;
}

/* Action Buttons */
.tx-actions {
    display: flex;
    gap: 4px;
    white-space: nowrap;
}

.tx-actions .btn-action {
    width: 32px;
    height: 32px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: none;
    background: #f0f2f5;
    color: #666;
    transition: all 0.2s ease;
    cursor: pointer;
    text-decoration: none;
}

.tx-actions .btn-action:hover {
    transform: scale(1.1);
}

.tx-actions .btn-action.action-view:hover {
    background: #ebf5fb;
    color: #3498db;
}

.tx-actions .btn-action.action-receipt:hover {
    background: #ebf5fb;
    color: #2980b9;
}

.tx-actions .btn-action.action-delete:hover {
    background: #fde8e8;
    color: #e74c3c;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 48px 24px;
    color: #adb5bd;
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 12px;
    opacity: 0.5;
    display: block;
}

.empty-state-title {
    font-size: 1rem;
    font-weight: 600;
    color: #636e72;
    margin-bottom: 4px;
}

.empty-state-text {
    font-size: 0.85rem;
    color: #adb5bd;
}

/* Transaction Details Modal */
.modal .modal-content {
    border: none;
    border-radius: 16px;
    overflow: hidden;
}

.modal .modal-header {
    background: linear-gradient(135deg, #4680ff, #6c5ce7);
    color: white;
    border: none;
    padding: 16px 24px;
}

.modal .modal-header .modal-title {
    font-size: 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modal .modal-body {
    padding: 24px;
}

.modal .modal-footer {
    border-top: 1px solid #f0f0f0;
    padding: 12px 24px;
    background: #fafbfc;
}

.detail-section-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #4680ff;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.detail-table {
    width: 100%;
}

.detail-table th {
    padding: 8px 0;
    font-size: 0.8rem;
    color: #8898aa;
    font-weight: 500;
    width: 40%;
    vertical-align: top;
}

.detail-table td {
    padding: 8px 0;
    font-size: 0.85rem;
    color: #2d3436;
}

/* RTL Support */
<?php if ($isRTL): ?>
.customer-info-list li i {
    margin-right: 0;
    margin-left: 12px;
}

.wallet-card {
    border-left: none;
    border-right: 4px solid #4680ff;
}

.transaction-table tbody tr {
    border-left: none;
    border-right: 3px solid transparent;
}

.transaction-table tbody tr[data-type="deposit"] {
    border-right-color: #2ecc71;
}

.transaction-table tbody tr[data-type="withdrawal"] {
    border-right-color: #e67e22;
}

.transaction-table tbody tr[data-type="hawala_send"],
.transaction-table tbody tr[data-type="hawala_receive"] {
    border-right-color: #3498db;
}

.transaction-table tbody tr[data-type="exchange"] {
    border-right-color: #9b59b6;
}

.tx-amount .amount-currency {
    margin-left: 0;
    margin-right: 4px;
}

.card-header {
    flex-direction: row-reverse !important;
}

.header-actions {
    flex-direction: row-reverse;
}
<?php endif; ?>

/* Responsive */
@media (max-width: 991px) {
    .stats-row {
        gap: 8px;
    }
    
    .stat-pill {
        min-width: calc(50% - 8px);
        padding: 10px 12px;
    }
    
    .stat-pill-count {
        font-size: 1.1rem;
    }
}

@media (max-width: 767px) {
    .header-actions {
        width: 100%;
        justify-content: stretch;
    }
    
    .header-actions .btn {
        flex: 1;
        justify-content: center;
    }
    
    .stat-pill {
        min-width: calc(50% - 4px);
    }
    
    .transaction-table thead th,
    .transaction-table tbody td {
        padding: 10px 10px;
    }
    
    .tx-notes {
        max-width: 80px;
    }
}

@media (max-width: 576px) {
    .stat-pill {
        min-width: 100%;
    }
}

/* Toast Notifications */
.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
    max-width: 400px;
    padding: 14px 18px;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.875rem;
    font-weight: 500;
    transform: translateX(120%);
    transition: transform 0.3s ease;
}

.toast-notification.show {
    transform: translateX(0);
}

.toast-notification.toast-success {
    background: #2ecc71;
    color: white;
}

.toast-notification.toast-error {
    background: #e74c3c;
    color: white;
}

/* Print Styles */
@media print {
    .header-actions,
    .tx-actions,
    .page-breadcrumb,
    .stat-pill,
    .stats-row {
        display: none !important;
    }
    
    .transaction-history-card {
        box-shadow: none !important;
        border: 1px solid #ddd;
    }
}
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        
                        <!-- Breadcrumb -->
                        <div class="page-breadcrumb">
                            <a href="customers.php">
                                <i class="feather icon-arrow-left"></i>
                                <?= __("customers") ?>
                            </a>
                            <span class="separator">/</span>
                            <span class="current-page"><?= htmlspecialchars($customer['name']) ?></span>
                        </div>

                        <!-- [ Main Content ] start -->
                        <div class="row">
                            
                            <!-- Left Column - Customer Info & Wallets -->
                            <div class="col-lg-4 col-md-5 col-12">
                                
                                <!-- Customer Profile Card -->
                                <div class="card customer-profile-card">
                                    <div class="card-body">
                                        <div class="text-center">
                                            <div class="customer-avatar">
                                                <?= strtoupper(mb_substr($customer['name'], 0, 2)) ?>
                                            </div>
                                            <div class="customer-name"><?= htmlspecialchars($customer['name']) ?></div>
                                            <div class="customer-phone"><?= htmlspecialchars($customer['phone']) ?></div>
                                        </div>
                                        
                                        <ul class="customer-info-list">
                                            <?php if ($customer['email']): ?>
                                            <li>
                                                <i class="feather icon-mail"></i>
                                                <span><?= htmlspecialchars($customer['email']) ?></span>
                                            </li>
                                            <?php endif; ?>
                                            <?php if ($customer['address']): ?>
                                            <li>
                                                <i class="feather icon-map-pin"></i>
                                                <span><?= htmlspecialchars($customer['address']) ?></span>
                                            </li>
                                            <?php endif; ?>
                                            <li>
                                                <i class="feather icon-calendar"></i>
                                                <span><?= __("created") ?>: <?= date('M d, Y', strtotime($customer['created_at'])) ?></span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Wallet Balances Card -->
                                <div class="card wallet-balances-card">
                                    <div class="card-header">
                                        <h5>
                                            <i class="feather icon-credit-card"></i>
                                            <?= __("wallet_balances") ?>
                                        </h5>
                                    </div>
                                    <div class="card-body" id="customerBalance">
                                        <?php if (!empty($wallets)): ?>
                                            <?php foreach ($wallets as $currency => $balance): 
                                                $accentColor = $currencyColors[$currency] ?? '#4680ff';
                                            ?>
                                            <div class="wallet-card" data-currency="<?= htmlspecialchars($currency) ?>" style="border-left-color: <?= $accentColor ?>; <?= $isRTL ? 'border-right-color: ' . $accentColor . '; border-left-color: transparent;' : '' ?>">
                                                <div>
                                                    <div class="wallet-currency-label"><?= htmlspecialchars($currency) ?></div>
                                                    <div class="wallet-currency-sub"><?= __("available_balance") ?? 'Available Balance' ?></div>
                                                </div>
                                                <div class="wallet-balance-amount <?= $balance >= 0 ? 'positive' : 'negative' ?>">
                                                    <?= number_format($balance, 2) ?>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="wallet-empty">
                                                <i class="feather icon-inbox"></i>
                                                <div><?= __("no_wallet_balances") ?? 'No balances yet' ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column - Stats + Transactions -->
                            <div class="col-lg-8 col-md-7 col-12">
                                
                                <!-- Stats Pills Row -->
                                <div class="stats-row">
                                    <div class="stat-pill">
                                        <div class="stat-pill-icon deposits">
                                            <i class="feather icon-plus-circle"></i>
                                        </div>
                                        <div class="stat-pill-info">
                                            <span class="stat-pill-count"><?= $stats['total_deposits'] ?></span>
                                            <span class="stat-pill-label"><?= __("deposits") ?></span>
                                        </div>
                                    </div>
                                    <div class="stat-pill">
                                        <div class="stat-pill-icon withdrawals">
                                            <i class="feather icon-minus-circle"></i>
                                        </div>
                                        <div class="stat-pill-info">
                                            <span class="stat-pill-count"><?= $stats['total_withdrawals'] ?></span>
                                            <span class="stat-pill-label"><?= __("withdrawals") ?></span>
                                        </div>
                                    </div>
                                    <div class="stat-pill">
                                        <div class="stat-pill-icon hawala">
                                            <i class="feather icon-repeat"></i>
                                        </div>
                                        <div class="stat-pill-info">
                                            <span class="stat-pill-count"><?= $stats['total_hawala_sent'] + $stats['total_hawala_received'] ?></span>
                                            <span class="stat-pill-label"><?= __("hawala") ?? 'Hawala' ?></span>
                                        </div>
                                    </div>
                                    <div class="stat-pill">
                                        <div class="stat-pill-icon exchanges">
                                            <i class="feather icon-refresh-cw"></i>
                                        </div>
                                        <div class="stat-pill-info">
                                            <span class="stat-pill-count"><?= $stats['total_exchanges'] ?></span>
                                            <span class="stat-pill-label"><?= __("exchanges") ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Transaction History Card -->
                                <div class="card transaction-history-card">
                                    <div class="card-header">
                                        <div class="header-title">
                                            <i class="feather icon-list"></i>
                                            <h5><?= __("transaction_history") ?></h5>
                                        </div>
                                        <div class="header-actions">
                                            <button class="btn btn-deposit" data-toggle="modal" data-target="#depositModal">
                                                <i class="feather icon-plus"></i>
                                                <?= __("new_deposit") ?>
                                            </button>
                                            <button class="btn btn-withdrawal" data-toggle="modal" data-target="#withdrawalModal">
                                                <i class="feather icon-minus"></i>
                                                <?= __("new_withdrawal") ?>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        
                                        <!-- Success/Error Messages -->
                                        <?php if (isset($success_message)): ?>
                                            <div class="alert-custom alert-success">
                                                <i class="feather icon-check-circle"></i>
                                                <span><?= htmlspecialchars($success_message) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($error_message)): ?>
                                            <div class="alert-custom alert-danger">
                                                <i class="feather icon-alert-circle"></i>
                                                <span><?= htmlspecialchars($error_message) ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($transactions)): ?>
                                        <div class="transaction-table-wrapper">
                                            <table class="transaction-table">
                                                <thead>
                                                    <tr>
                                                        <th><?= __("date") ?></th>
                                                        <th><?= __("type") ?></th>
                                                        <th><?= __("amount") ?></th>
                                                        <th><?= __("paid_to") ?></th>
                                                        <th><?= __("status") ?></th>
                                                        <th><?= __("notes") ?></th>
                                                        <th><?= __("actions") ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($transactions as $transaction): 
                                                        $type_icon = '';
                                                        switch ($transaction['type']) {
                                                            case 'deposit':
                                                                $type_icon = 'plus-circle';
                                                                break;
                                                            case 'withdrawal':
                                                                $type_icon = 'minus-circle';
                                                                break;
                                                            case 'hawala_send':
                                                            case 'hawala_receive':
                                                                $type_icon = 'repeat';
                                                                break;
                                                            case 'exchange':
                                                                $type_icon = 'refresh-cw';
                                                                break;
                                                            default:
                                                                $type_icon = 'circle';
                                                        }
                                                    ?>
                                                    <tr data-type="<?= htmlspecialchars($transaction['type']) ?>">
                                                        <!-- Date -->
                                                        <td>
                                                            <div class="tx-date">
                                                                <span class="date-main"><?= date('M d, Y', strtotime($transaction['created_at'])) ?></span>
                                                                <span class="date-time"><?= date('h:i A', strtotime($transaction['created_at'])) ?></span>
                                                            </div>
                                                        </td>
                                                        
                                                        <!-- Type -->
                                                        <td>
                                                            <span class="tx-type-badge type-<?= htmlspecialchars($transaction['type']) ?>">
                                                                <i class="feather icon-<?= $type_icon ?>"></i>
                                                                <?= __($transaction['type']) ?>
                                                            </span>
                                                            <?php if ($transaction['additional_info']): ?>
                                                                <span class="tx-additional-info"><?= htmlspecialchars($transaction['additional_info']) ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        
                                                        <!-- Amount + Currency combined -->
                                                        <td>
                                                            <div class="tx-amount">
                                                                <span class="amount-value"><?= number_format($transaction['amount'], 2) ?></span>
                                                                <span class="amount-currency"><?= htmlspecialchars($transaction['currency']) ?></span>
                                                            </div>
                                                        </td>
                                                        
                                                        <!-- Paid To -->
                                                        <td>
                                                            <?php if (!empty($main_account_data[$transaction['id']])): ?>
                                                                <div class="tx-paid-to">
                                                                    <i class="feather icon-user"></i>
                                                                    <?= htmlspecialchars($main_account_data[$transaction['id']]) ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="text-muted">
                                                                    <?= htmlspecialchars(isset($transaction['paid_to']) ? $transaction['paid_to'] : '-') ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        
                                                        <!-- Status -->
                                                        <td>
                                                            <span class="tx-status status-<?= htmlspecialchars($transaction['status']) ?>">
                                                                <span class="tx-status-dot"></span>
                                                                <?= __($transaction['status']) ?>
                                                            </span>
                                                        </td>
                                                        
                                                        <!-- Notes -->
                                                        <td>
                                                            <?php if ($transaction['notes']): ?>
                                                                <span class="tx-notes" title="<?= htmlspecialchars($transaction['notes']) ?>" data-toggle="tooltip">
                                                                    <?= htmlspecialchars($transaction['notes']) ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        
                                                        <!-- Actions -->
                                                        <td>
                                                            <div class="tx-actions">
                                                                <?php if ($transaction['receipt_path']): ?>
                                                                <a href="../uploads/receipts/<?= htmlspecialchars($transaction['receipt_path']) ?>" 
                                                                   class="btn-action action-receipt" target="_blank" 
                                                                   data-toggle="tooltip" title="<?= __('view_receipt') ?>">
                                                                    <i class="feather icon-file"></i>
                                                                </a>
                                                                <?php endif; ?>
                                                                
                                                                <button class="btn-action action-view view-transaction" 
                                                                        data-id="<?= $transaction['id'] ?>"
                                                                        data-toggle="tooltip" title="<?= __('view_details') ?>">
                                                                    <i class="feather icon-eye"></i>
                                                                </button>

                                                                <?php if (in_array($transaction['type'], ['deposit', 'withdrawal', 'hawala_send'])): ?>
                                                                <button class="btn-action action-delete delete-transaction" 
                                                                        data-id="<?= $transaction['id'] ?>"
                                                                        data-type="<?= $transaction['type'] ?>"
                                                                        data-amount="<?= $transaction['amount'] ?>"
                                                                        data-toggle="tooltip" title="<?= __('delete') ?>">
                                                                    <i class="feather icon-trash-2"></i>
                                                                </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php else: ?>
                                        <!-- Empty State -->
                                        <div class="empty-state">
                                            <i class="feather icon-inbox empty-state-icon"></i>
                                            <div class="empty-state-title"><?= __("no_transactions_yet") ?? 'No transactions yet' ?></div>
                                            <div class="empty-state-text"><?= __("start_by_adding_deposit") ?? 'Start by adding a deposit or withdrawal' ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- [ Main Content ] end -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
// Initialize tooltips
$(function () {
    $('[data-toggle="tooltip"]').tooltip();
});

// Function to cancel Hawala transfer
function cancelHawala(hawalaId) {
    if (confirm('Are you sure you want to cancel this Hawala transfer?')) {
        fetch('ajax/cancel_hawala.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ hawala_id: hawalaId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Hawala transfer cancelled successfully');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast('error', 'Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'An error occurred while cancelling the transfer');
        });
    }
}

// Auto-select customer in modals
document.addEventListener('DOMContentLoaded', function() {
    const customerId = <?= $customer_id ?>;
    
    function setCustomerInModal(modalId) {
        const modal = document.querySelector(modalId);
        if (modal) {
            const customerSelect = modal.querySelector('select[name="customer_id"], select[name="sender_id"]');
            if (customerSelect) {
                customerSelect.value = customerId;
                customerSelect.setAttribute('readonly', true);
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = customerSelect.name;
                hiddenInput.value = customerId;
                customerSelect.parentNode.appendChild(hiddenInput);
            }
        }
    }

    ['#depositModal', '#withdrawalModal', '#hawalaModal', '#exchangeModal'].forEach(modalId => {
        $(modalId).on('show.bs.modal', function() {
            setCustomerInModal(modalId);
        });
    });

    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert-custom').forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
});

// Function to load customer balance
function loadCustomerBalance(customerId) {
    const balanceContainer = document.getElementById('customerBalance');
    if (!balanceContainer) {
        console.warn('Customer balance container not found');
        return;
    }
    
    if (!customerId) {
        balanceContainer.innerHTML = '';
        return;
    }
    
    fetch('ajax/get_customer_balance.php?customer_id=' + customerId)
        .then(response => response.json())
        .then(data => {
            const currencyColors = {
                'USD': '#27ae60',
                'EUR': '#2980b9',
                'AFS': '#8e44ad',
                'DARHAM': '#d4a017',
                'PKR': '#d35400',
                'INR': '#c0392b'
            };
            
            let balanceHtml = '';
            let hasBalance = false;
            
            for (let currency in data) {
                hasBalance = true;
                const balance = parseFloat(data[currency]);
                const color = currencyColors[currency] || '#4680ff';
                const isRTL = <?= $isRTL ? 'true' : 'false' ?>;
                const borderStyle = isRTL 
                    ? `border-right-color: ${color}; border-left-color: transparent;`
                    : `border-left-color: ${color};`;
                
                balanceHtml += `
                    <div class="wallet-card" data-currency="${currency}" style="${borderStyle}">
                        <div>
                            <div class="wallet-currency-label">${currency}</div>
                            <div class="wallet-currency-sub"><?= __("available_balance") ?? 'Available Balance' ?></div>
                        </div>
                        <div class="wallet-balance-amount ${balance >= 0 ? 'positive' : 'negative'}">
                            ${balance.toFixed(2)}
                        </div>
                    </div>`;
            }
            
            if (!hasBalance) {
                balanceHtml = `
                    <div class="wallet-empty">
                        <i class="feather icon-inbox"></i>
                        <div><?= __("no_wallet_balances") ?? 'No balances yet' ?></div>
                    </div>`;
            }
            
            balanceContainer.innerHTML = balanceHtml;
        })
        .catch(error => {
            console.error('Error:', error);
            balanceContainer.innerHTML = 
                '<div class="wallet-empty"><i class="feather icon-alert-circle"></i><div><?= __("error_loading_balance") ?></div></div>';
        });
}

// Handle view transaction click
$(document).on('click', '.view-transaction', function(e) {
    e.preventDefault();
    const transactionId = $(this).data('id');
    viewTransaction(transactionId);
});

// Function to view transaction details
function viewTransaction(transactionId) {
    const modal = $('#transactionDetailsModal');
    const content = document.getElementById('transactionDetailsContent');
    
    content.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only"><?= __("loading") ?>...</span>
            </div>
            <div class="mt-2 text-muted" style="font-size: 0.85rem;"><?= __("loading") ?>...</div>
        </div>
    `;
    
    modal.modal('show');
    
    fetch(`view_sarafi_transaction.php?id=${transactionId}`)
        .then(response => response.json())
        .then(response => {
            if (response.success) {
                const data = response.data;
                let details = `
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-4">
                                <div class="detail-section-title">
                                    <i class="feather icon-file-text"></i>
                                    <?= __("transaction_details") ?>
                                </div>
                                <table class="detail-table">
                                    <tr>
                                        <th><?= __("type") ?>:</th>
                                        <td>
                                            <span class="tx-type-badge type-${data.transaction.type}">
                                                ${capitalizeFirstLetter(data.transaction.type.replace('_', ' '))}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><?= __("amount") ?>:</th>
                                        <td>
                                            <span style="font-weight: 700; font-size: 1.1rem;">
                                                ${parseFloat(data.transaction.amount).toFixed(2)}
                                                <span style="font-size: 0.8rem; color: #8898aa; margin-left: 4px;">
                                                    ${data.transaction.currency}
                                                </span>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><?= __("reference") ?>:</th>
                                        <td>${data.transaction.reference_number || '<span class="text-muted">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th><?= __("status") ?>:</th>
                                        <td>
                                            <span class="tx-status status-${data.transaction.status}">
                                                <span class="tx-status-dot"></span>
                                                ${capitalizeFirstLetter(data.transaction.status)}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><?= __("date") ?>:</th>
                                        <td>${formatDate(data.transaction.created_at)}</td>
                                    </tr>
                                    ${data.transaction.notes ? `
                                    <tr>
                                        <th><?= __("notes") ?>:</th>
                                        <td>${data.transaction.notes}</td>
                                    </tr>
                                    ` : ''}
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-4">
                                <div class="detail-section-title">
                                    <i class="feather icon-user"></i>
                                    <?= __("customer_details") ?>
                                </div>
                                <table class="detail-table">
                                    <tr>
                                        <th><?= __("name") ?>:</th>
                                        <td>${data.customer.name}</td>
                                    </tr>
                                    <tr>
                                        <th><?= __("phone") ?>:</th>
                                        <td>${data.customer.phone}</td>
                                    </tr>
                                    <tr>
                                        <th><?= __("wallet_balance") ?>:</th>
                                        <td>
                                            <span class="tx-status status-${parseFloat(data.customer.wallet_balance) >= 0 ? 'completed' : 'cancelled'}">
                                                <span class="tx-status-dot"></span>
                                                ${data.transaction.currency} ${parseFloat(data.customer.wallet_balance).toFixed(2)}
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>`;
                content.innerHTML = details;
            } else {
                content.innerHTML = `
                    <div class="alert-custom alert-danger" style="margin: 0;">
                        <i class="feather icon-alert-circle"></i>
                        <span>${response.message || '<?= __("error_loading_transaction_details") ?>'}</span>
                    </div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `
                <div class="alert-custom alert-danger" style="margin: 0;">
                    <i class="feather icon-alert-circle"></i>
                    <span><?= __("error_loading_transaction_details") ?></span>
                </div>`;
        });
}

// Function to delete deposit
function deleteDeposit(transactionId, amount) {
    if (confirm('<?= __("are_you_sure_you_want_to_delete_this_deposit_transaction") ?>')) {
        fetch('delete_sarafi_deposit.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `transaction_id=${transactionId}&amount=${amount}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Deposit deleted successfully');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast('error', 'Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'An error occurred while deleting the deposit');
        });
    }
}

// Function to delete withdrawal
function deleteWithdrawal(transactionId, amount) {
    if (confirm('<?= __("are_you_sure_you_want_to_delete_this_withdrawal_transaction") ?>')) {
        fetch('delete_sarafi_withdrawal.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `transaction_id=${transactionId}&amount=${amount}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Withdrawal deleted successfully');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast('error', 'Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'An error occurred while deleting the withdrawal');
        });
    }
}

// Function to delete hawala transfer
function deleteHawala(transactionId, amount) {
    if (confirm('<?= __("are_you_sure_you_want_to_delete_this_hawala_transfer") ?>')) {
        fetch('delete_sarafi_hawala.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `transaction_id=${transactionId}&amount=${amount}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Hawala transfer deleted successfully');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast('error', 'Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'An error occurred while deleting the hawala transfer');
        });
    }
}

// Handle delete exchange transaction
$(document).on('click', '.delete-exchange', function(e) {
    e.preventDefault();
    const transactionId = $(this).data('id');
    
    if (confirm('<?= __("are_you_sure_you_want_to_delete_this_exchange_transaction_this_action_cannot_be_undone") ?>')) {
        $.ajax({
            url: 'delete_sarafi_exchange.php',
            type: 'POST',
            data: {
                transaction_id: transactionId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('success', response.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('error', response.message || '<?= __("failed_to_delete_exchange_transaction") ?>');
                }
            },
            error: function() {
                showToast('error', '<?= __("error_occurred_while_deleting_exchange_transaction") ?>');
            }
        });
    }
});

// Handle delete transaction click
$(document).on('click', '.delete-transaction', function(e) {
    e.preventDefault();
    const transactionId = $(this).data('id');
    const type = $(this).data('type');
    const amount = $(this).data('amount');
    
    switch(type) {
        case 'deposit':
            deleteDeposit(transactionId, amount);
            break;
        case 'withdrawal':
            deleteWithdrawal(transactionId, amount);
            break;
        case 'hawala_send':
            deleteHawala(transactionId, amount);
            break;
    }
});

// Handle print transaction
$(document).on('click', '#printTransaction', function(e) {
    e.preventDefault();
    const content = document.getElementById('transactionDetailsContent').innerHTML;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title><?= __("transaction_details") ?></title>
                <link rel="stylesheet" href="../assets/css/style.css">
                <style>
                    body { padding: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
                    .modal-footer, .close, .tx-actions { display: none !important; }
                    .detail-section-title { font-size: 14px; font-weight: 600; color: #4680ff; margin-bottom: 12px; }
                    .detail-table { width: 100%; }
                    .detail-table th { padding: 8px 0; font-size: 12px; color: #8898aa; width: 40%; }
                    .detail-table td { padding: 8px 0; font-size: 13px; color: #2d3436; }
                    .tx-type-badge { padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; }
                    .tx-status { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
                    @media print {
                        body { padding: 0; }
                    }
                </style>
            </head>
            <body>
                <h3 style="margin-bottom: 20px;"><?= __("transaction_details") ?></h3>
                ${content}
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(function() { window.close(); }, 500);
                    };
                <\/script>
            </body>
        </html>
    `);
    printWindow.document.close();
});

// Helper function to capitalize first letter
function capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

// Helper function to format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Toast notification function
function showToast(type, message) {
    // Remove existing toasts
    document.querySelectorAll('.toast-notification').forEach(t => t.remove());
    
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <i class="feather icon-${type === 'success' ? 'check-circle' : 'alert-circle'}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(toast);
    
    // Force reflow
    toast.offsetHeight;
    
    setTimeout(() => {
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }, 50);
}

// Helper functions for transaction types and status
function getTypeClass(type) {
    switch (type) {
        case 'deposit': return 'success';
        case 'withdrawal': return 'warning';
        case 'hawala_send':
        case 'hawala_receive': return 'info';
        case 'exchange': return 'primary';
        default: return 'secondary';
    }
}

function getStatusClass(status) {
    switch (status) {
        case 'completed': return 'success';
        case 'pending': return 'warning';
        case 'cancelled': return 'danger';
        default: return 'secondary';
    }
}
</script>

<!-- Transaction Details Modal -->
<div class="modal fade" id="transactionDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-file-text"></i>
                    <?= __("transaction_details") ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="transactionDetailsContent">
                    <div class="text-center p-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only"><?= __("loading") ?>...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 10px;">
                    <i class="feather icon-x mr-2"></i><?= __("close") ?>
                </button>
                <button type="button" class="btn btn-primary" id="printTransaction" style="border-radius: 10px;">
                    <i class="feather icon-printer mr-2"></i><?= __("print") ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Include Modals -->
<?php 
$customers = [$customer];
include 'includes/sarafi_modals.php'; 
?>
</body>
</html>