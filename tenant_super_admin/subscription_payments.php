<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session timeout (30 mins)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tenant_super_admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/BranchAddonManager.php';
require_once '../includes/UserAddonManager.php';
require_once '../includes/CommunicationAddonManager.php';

if (!isset($pdo) || !$pdo) {
    die("Database connection failed. Please contact administrator.");
}

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    header('Location: dashboard.php');
    exit();
}

function getCurrencySymbol($currencyCode) {
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'AFN' => '؋',
        'AED' => 'د.إ',
        'INR' => '₹',
        'PKR' => '₨',
    ];
    return $symbols[$currencyCode] ?? $currencyCode;
}

$addonManager     = new BranchAddonManager($pdo, $tenant_id);
$userAddonManager = new UserAddonManager($pdo, $tenant_id);
$communicationAddonManager = new CommunicationAddonManager($pdo, $tenant_id);

$tenant_payment_status = 'current';
try {
    $stmt = $pdo->prepare("SELECT payment_status, payment_due_date FROM tenants WHERE id = ?");
    $stmt->execute([$tenant_id]);
    $tenant_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($tenant_data) {
        $tenant_payment_status = $tenant_data['payment_status'];
        $payment_due_date      = $tenant_data['payment_due_date'];
    }
} catch (PDOException $e) {}

try {
    $stmt = $pdo->prepare("
        SELECT ts.*, p.name AS plan_name, p.price AS plan_price
        FROM tenant_subscriptions ts
        LEFT JOIN plans p ON ts.plan_id = p.id
        WHERE ts.tenant_id = :tenant_id
        ORDER BY ts.created_at DESC
    ");
    $stmt->execute(['tenant_id' => $tenant_id]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $subscriptions = [];
}

try {
    $stmt = $pdo->prepare("
        SELECT sp.*, ts.plan_id, p.name AS plan_name, u.name AS processed_by_name
        FROM subscription_payments sp
        LEFT JOIN tenant_subscriptions ts ON sp.subscription_id = ts.id
        LEFT JOIN plans p ON ts.plan_id = p.id
        LEFT JOIN users u ON sp.processed_by = u.id
        WHERE ts.tenant_id = :tenant_id
        ORDER BY sp.payment_date DESC, sp.created_at DESC
    ");
    $stmt->execute(['tenant_id' => $tenant_id]);
    $all_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_payments = [];
}

$payment_items_per_page  = 10;
$payment_current_page    = intval($_GET['payment_page'] ?? 1);
$payment_search_query    = $_GET['payment_search'] ?? '';

$filtered_payments = $all_payments;
if (!empty($payment_search_query)) {
    $search_lower = strtolower($payment_search_query);
    $filtered_payments = array_filter($all_payments, function($p) use ($search_lower) {
        return strpos(strtolower($p['plan_name'] ?? ''), $search_lower) !== false
            || strpos(strtolower($p['payment_method'] ?? ''), $search_lower) !== false
            || strpos(strtolower($p['receipt_number'] ?? ''), $search_lower) !== false
            || strpos(strtolower($p['currency'] ?? ''), $search_lower) !== false;
    });
}

$payment_total_items  = count($filtered_payments);
$payment_total_pages  = max(1, ceil($payment_total_items / $payment_items_per_page));
$payment_current_page = max(1, min($payment_current_page, $payment_total_pages));
$payment_offset       = ($payment_current_page - 1) * $payment_items_per_page;
$payments             = array_slice(array_values($filtered_payments), $payment_offset, $payment_items_per_page);

// Handle HesabPay redirect
if (isset($_GET['payment'], $_GET['subscription_id'])) {
    $payment_status      = $_GET['payment'];
    $subscription_id_raw = $_GET['subscription_id'];
    $sub_id = 0; $data = null;

    if (strpos($subscription_id_raw, '?') !== false) {
        list($sub_id_str, $data_query) = explode('?', $subscription_id_raw, 2);
        $sub_id = intval($sub_id_str);
        parse_str($data_query, $data_params);
        if (isset($data_params['data'])) {
            $raw = urldecode($data_params['data']);
            $data = json_decode($raw, true);
        }
    } else {
        $sub_id = intval($subscription_id_raw);
        if (!empty($_GET['data'])) {
            $raw = urldecode($_GET['data']);
            $data = json_decode($raw, true);
        }
    }

    if ($payment_status === 'success') {
        try {
            $stmt = $pdo->prepare("SELECT amount, currency, billing_cycle, start_date, next_billing_date FROM tenant_subscriptions WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$sub_id, $tenant_id]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($subscription) {
                $processed_by   = $_SESSION['user_id'] ?? null;
                $transaction_id = $data['transaction_id'] ?? null;
                $pdo->prepare("INSERT INTO subscription_payments (subscription_id, amount, currency, payment_method, payment_date, processed_by, transaction_id, receipt_number) VALUES (?, ?, ?, 'HesabPay', CURDATE(), ?, ?, ?)")
                    ->execute([$sub_id, $subscription['amount'], $subscription['currency'], $processed_by, $transaction_id, $transaction_id]);
                $pdo->prepare("UPDATE tenant_subscriptions SET status = 'active', last_payment_date = CURDATE() WHERE id = ? AND tenant_id = ?")
                    ->execute([$sub_id, $tenant_id]);
                $base_date = $subscription['next_billing_date'] ?: $subscription['start_date'];
                $next_billing_date = null;
                switch ($subscription['billing_cycle']) {
                    case 'monthly':   $next_billing_date = date('Y-m-d', strtotime('+1 month',  strtotime($base_date))); break;
                    case 'quarterly': $next_billing_date = date('Y-m-d', strtotime('+3 months', strtotime($base_date))); break;
                    case 'yearly':    $next_billing_date = date('Y-m-d', strtotime('+1 year',   strtotime($base_date))); break;
                }
                if ($next_billing_date) {
                    $pdo->prepare("UPDATE tenant_subscriptions SET next_billing_date = ? WHERE id = ? AND tenant_id = ?")
                        ->execute([$next_billing_date, $sub_id, $tenant_id]);
                }
                $payment_message = "Payment successful! Subscription activated.";
                $payment_msg_type = 'success';
            }
        } catch (PDOException $e) {
            $payment_message  = "Error processing payment.";
            $payment_msg_type = 'danger';
        }
    } elseif ($payment_status === 'failed') {
        $payment_message  = "Payment failed. Please try again.";
        $payment_msg_type = 'danger';
    }
}
?>
<?php include 'header.php'; ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root {
    --teal:      #2ed8b6;
    --blue:      #4099ff;
    --grad:      linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    --surface:   #f4f7fe;
    --card-bg:   #ffffff;
    --border:    #e8edf5;
    --text-main: #1a2340;
    --text-sub:  #6b7a99;
    --green:     #22c55e;
    --amber:     #f59e0b;
    --red:       #ef4444;
    --purple:    #8b5cf6;
    --radius:    14px;
    --shadow:    0 2px 12px rgba(64,153,255,0.08);
    --shadow-md: 0 6px 24px rgba(64,153,255,0.13);
}

*, *::before, *::after { box-sizing: border-box; }

body, .pcoded-main-container {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    background: var(--surface) !important;
    color: var(--text-main) !important;
}

/* â”€â”€ Page Header â”€â”€ */
.dash-header {
    background: var(--grad);
    border-radius: var(--radius);
    padding: 24px 28px;
    margin-bottom: 24px;
    display: flex; align-items: center; justify-content: space-between;
    box-shadow: 0 8px 32px rgba(64,153,255,0.22);
    position: relative; overflow: hidden;
}
.dash-header::before {
    content: '';
    position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
}
.dash-header-left h4 { font-size: 22px; font-weight: 800; color: #fff; margin: 0 0 4px; letter-spacing: -0.4px; position: relative; }
.dash-header-left p  { color: rgba(255,255,255,0.8); margin: 0; font-size: 13px; position: relative; }

/* â”€â”€ Alerts â”€â”€ */
.dash-alert {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px 20px; border-radius: var(--radius);
    margin-bottom: 16px; font-size: 14px; font-weight: 500;
    animation: slideDown 0.3s ease;
}
.dash-alert-success { background: #dcfce7; color: #166534; border-left: 4px solid var(--green); }
.dash-alert-danger  { background: #fee2e2; color: #991b1b; border-left: 4px solid var(--red); }
.dash-alert-warning { background: #fef3c7; color: #92400e; border-left: 4px solid var(--amber); }
.dash-alert i { margin-top: 1px; flex-shrink: 0; }
.dash-alert .alert-body { flex: 1; }
.dash-alert .close-btn { background: none; border: none; cursor: pointer; opacity: 0.5; font-size: 18px; line-height: 1; padding: 0; color: inherit; margin-left: auto; }
.dash-alert .close-btn:hover { opacity: 1; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

/* â”€â”€ Subscription Cards Grid â”€â”€ */
.sub-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}

.sub-card {
    background: var(--card-bg);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    overflow: hidden;
    transition: box-shadow 0.2s, transform 0.2s;
    display: flex; flex-direction: column;
}
.sub-card:hover { box-shadow: var(--shadow-md); transform: translateY(-3px); }

.sub-card-header {
    background: var(--grad);
    padding: 16px 20px;
    display: flex; align-items: center; justify-content: space-between;
}
.sub-card-header .plan-name {
    font-size: 15px; font-weight: 700; color: #fff;
    display: flex; align-items: center; gap: 8px;
}
.sub-card-header .plan-icon {
    width: 32px; height: 32px; border-radius: 9px;
    background: rgba(255,255,255,0.2);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 15px;
}

/* Status badges */
.status-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px; border-radius: 20px;
    font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
}
.status-active   { background: rgba(34,197,94,0.12);  color: #166534; }
.status-pending  { background: rgba(245,158,11,0.12); color: #92400e; }
.status-expired  { background: rgba(239,68,68,0.12);  color: #991b1b; }
.status-cancelled{ background: rgba(107,122,153,0.12);color: var(--text-sub); }

/* Sub card body */
.sub-card-body { padding: 20px; flex: 1; }

.amount-row {
    display: flex; gap: 12px; margin-bottom: 16px;
}
.amount-box {
    flex: 1; background: var(--surface); border-radius: 10px; padding: 12px 14px;
    border: 1px solid var(--border);
}
.amount-box .ab-label { font-size: 11px; font-weight: 600; color: var(--text-sub); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
.amount-box .ab-value { font-size: 18px; font-weight: 800; font-family: 'JetBrains Mono', monospace; }
.amount-box.ab-blue  .ab-value { color: var(--blue); }
.amount-box.ab-teal  .ab-value { color: var(--teal); }
.amount-box.ab-green .ab-value { color: var(--green); }
.amount-box.ab-amber .ab-value { color: var(--amber); }

.addon-breakdown {
    background: var(--surface); border-radius: 10px;
    padding: 12px 14px; margin-bottom: 16px;
    border: 1px solid var(--border);
}
.addon-breakdown .ab-title { font-size: 11px; font-weight: 700; color: var(--text-sub); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
.addon-row { display: flex; align-items: center; justify-content: space-between; font-size: 13px; margin-bottom: 4px; }
.addon-row:last-child { margin-bottom: 0; }
.addon-row .ar-label { color: var(--text-sub); display: flex; align-items: center; gap: 5px; }
.addon-row .ar-value { font-weight: 700; color: var(--text-main); font-family: 'JetBrains Mono', monospace; }

.dates-row {
    display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px;
}
.date-item .di-label { font-size: 11px; font-weight: 600; color: var(--text-sub); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
.date-item .di-value { font-size: 13px; font-weight: 700; color: var(--text-main); }

.last-payment-row {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px; color: var(--text-sub); margin-bottom: 16px;
    background: rgba(34,197,94,0.07); border-radius: 8px; padding: 8px 12px;
}
.last-payment-row strong { color: var(--green); }

/* Pay Now button */
.pay-btn {
    display: block; width: 100%;
    background: var(--grad); color: #fff; border: none;
    border-radius: 10px; padding: 12px;
    font-family: inherit; font-size: 14px; font-weight: 700;
    cursor: pointer; text-align: center; text-decoration: none;
    transition: all 0.2s;
}
.pay-btn:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(64,153,255,0.3); color: #fff; }

/* Empty state */
.empty-card {
    background: var(--card-bg); border-radius: var(--radius);
    border: 1px solid var(--border); text-align: center;
    padding: 56px 24px; grid-column: 1/-1;
}
.empty-card .empty-icon { font-size: 48px; opacity: 0.2; display: block; margin-bottom: 16px; }
.empty-card h5 { font-weight: 700; color: var(--text-main); margin-bottom: 6px; }
.empty-card p  { color: var(--text-sub); font-size: 14px; margin-bottom: 20px; }
.btn-contact {
    background: var(--grad); color: #fff; border: none;
    border-radius: 10px; padding: 11px 24px;
    font-family: inherit; font-size: 14px; font-weight: 700; cursor: pointer;
}

/* â”€â”€ History Card â”€â”€ */
.dash-card {
    background: var(--card-bg);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    overflow: hidden;
    margin-bottom: 24px;
}
.dash-card-head {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 12px;
}
.dash-card-head h6 {
    font-size: 15px; font-weight: 700; margin: 0;
    display: flex; align-items: center; gap: 8px;
}
.dash-card-head h6 .ico {
    width: 30px; height: 30px; border-radius: 8px;
    background: var(--grad); display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 14px;
}
.count-badge {
    background: rgba(64,153,255,0.1); color: var(--blue);
    border-radius: 20px; padding: 3px 12px;
    font-size: 12px; font-weight: 700;
}

/* Search bar */
.search-wrap { display: flex; gap: 8px; align-items: center; }
.search-input {
    border: 1.5px solid var(--border); border-radius: 10px;
    padding: 8px 14px; font-family: inherit; font-size: 13px;
    color: var(--text-main); background: var(--surface);
    outline: none; width: 220px; transition: border-color 0.2s;
}
.search-input:focus { border-color: var(--blue); background: #fff; }
.search-btn {
    background: var(--grad); color: #fff; border: none;
    border-radius: 10px; padding: 8px 14px; cursor: pointer;
    font-size: 13px; transition: opacity 0.2s;
}
.search-btn:hover { opacity: 0.85; }
.clear-btn {
    background: var(--surface); color: var(--text-sub);
    border: 1.5px solid var(--border); border-radius: 10px;
    padding: 8px 14px; cursor: pointer; font-size: 13px;
    font-family: inherit; transition: all 0.2s;
}
.clear-btn:hover { border-color: var(--red); color: var(--red); }

/* Table */
.pay-table { width: 100%; border-collapse: collapse; }
.pay-table thead th {
    background: var(--surface); padding: 12px 16px;
    font-size: 11px; font-weight: 700; color: var(--text-sub);
    text-transform: uppercase; letter-spacing: 0.6px;
    border-bottom: 1.5px solid var(--border); white-space: nowrap;
}
.pay-table tbody tr { transition: background 0.15s; }
.pay-table tbody tr:hover { background: var(--surface); }
.pay-table tbody td {
    padding: 14px 16px; border-bottom: 1px solid var(--border);
    font-size: 14px; vertical-align: middle;
}
.pay-table tbody tr:last-child td { border-bottom: none; }

.td-date  { font-weight: 600; color: var(--text-sub); font-family: 'JetBrains Mono', monospace; }
.td-amount{ font-weight: 800; color: var(--green); font-family: 'JetBrains Mono', monospace; font-size: 15px; }
.td-currency { font-weight: 700; color: var(--blue); }
.td-plan  { font-weight: 700; color: var(--text-main); }
.td-method{ font-weight: 600; color: var(--text-main); }
.td-receipt{ font-weight: 600; color: var(--text-sub); font-family: 'JetBrains Mono', monospace; font-size: 12px; }
.td-user  { font-weight: 600; color: var(--text-sub); }
.na-text  { color: var(--border); font-style: italic; font-weight: 400; }

/* Pagination */
.pag-wrap { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding: 16px 20px; border-top: 1px solid var(--border); }
.pag-info { font-size: 13px; color: var(--text-sub); }
.pag-links { display: flex; gap: 4px; }
.pag-btn {
    min-width: 36px; height: 36px; border-radius: 9px;
    border: 1.5px solid var(--border); background: var(--card-bg);
    color: var(--text-main); font-size: 13px; font-weight: 600;
    display: inline-flex; align-items: center; justify-content: center;
    text-decoration: none; padding: 0 10px; transition: all 0.15s;
    font-family: inherit; cursor: pointer;
}
.pag-btn:hover  { border-color: var(--blue); color: var(--blue); text-decoration: none; }
.pag-btn.active { background: var(--grad); border-color: transparent; color: #fff; }
.pag-btn.disabled { opacity: 0.4; pointer-events: none; }
.pag-dots { display: flex; align-items: center; padding: 0 4px; color: var(--text-sub); font-size: 14px; }

/* Section label */
.section-header {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;
}
.section-title { font-size: 15px; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
.section-title .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--grad); flex-shrink: 0; }

/* Overrides */
.pcoded-content { padding: 20px !important; }
.page-header { display: none !important; }
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <!-- Page Header -->
    <div class="dash-header">
        <div class="dash-header-left">
            <h4><i class="feather icon-credit-card" style="margin-right:8px;"></i><?= __('subscription_payments') ?></h4>
            <p><?= __('view_and_manage_your_tenant_subscription_payments') ?></p>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (isset($payment_message)): ?>
    <div class="dash-alert dash-alert-<?= $payment_msg_type ?? 'success' ?>">
        <i class="feather icon-<?= ($payment_msg_type ?? 'success') === 'success' ? 'check-circle' : 'alert-circle' ?>"></i>
        <div class="alert-body"><?= htmlspecialchars($payment_message) ?></div>
        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <?php if ($tenant_payment_status !== 'current'): ?>
    <div class="dash-alert dash-alert-<?= $tenant_payment_status === 'warning' ? 'warning' : 'danger' ?>">
        <i class="feather icon-alert-triangle"></i>
        <div class="alert-body">
            <strong>Payment Status: </strong>
            <?php if ($tenant_payment_status === 'warning'): ?>
                Your subscription payment is due soon.
                <?php if (isset($payment_due_date)): ?>
                    Please ensure payment is made before <strong><?= date('M d, Y', strtotime($payment_due_date)) ?></strong>.
                <?php endif; ?>
            <?php elseif ($tenant_payment_status === 'overdue'): ?>
                Your subscription payment is overdue.
                <?php if (isset($payment_due_date)): ?>
                    Payment was due on <strong><?= date('M d, Y', strtotime($payment_due_date)) ?></strong>.
                <?php endif; ?>
                Please contact billing immediately.
            <?php elseif ($tenant_payment_status === 'suspended'): ?>
                Your account is suspended due to non-payment. Please contact billing to restore access.
            <?php endif; ?>
        </div>
        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Subscriptions -->
    <div class="section-header">
        <div class="section-title"><span class="dot"></span>Active Subscriptions</div>
        <div style="font-size:12px;color:var(--text-sub);"><?= count($subscriptions) ?> plan<?= count($subscriptions) !== 1 ? 's' : '' ?></div>
    </div>

    <div class="sub-grid">
        <?php if (count($subscriptions) > 0): ?>
            <?php foreach ($subscriptions as $subscription):
                $activeBranchAddons = $addonManager->getActiveBranchAddons($tenant_id);
                $subscriptionCycle = strtolower($subscription['billing_cycle'] ?? 'monthly');
                $activeBranchAddons = array_filter($activeBranchAddons, function($addon) use ($subscriptionCycle) {
                    return strtolower($addon['billing_cycle'] ?? 'monthly') === $subscriptionCycle;
                });
                $branchAddonCost = array_sum(array_column($activeBranchAddons, 'total_addon_cost'));
                $activeUserAddons = $userAddonManager->getActiveUserAddons($tenant_id);
                $activeUserAddons = array_filter($activeUserAddons, function($addon) use ($subscriptionCycle) {
                    return strtolower($addon['billing_cycle'] ?? 'monthly') === $subscriptionCycle;
                });
                $userAddonCost = array_sum(array_column($activeUserAddons, 'total_addon_cost'));
                $activeCommunicationAddons = $communicationAddonManager->getActiveAddons($tenant_id);
                $activeCommunicationAddons = array_filter($activeCommunicationAddons, function($addon) use ($subscriptionCycle) {
                    return strtolower($addon['billing_cycle'] ?? 'monthly') === $subscriptionCycle;
                });
                $communicationAddonCost = array_sum(array_column($activeCommunicationAddons, 'addon_price'));
                $totalAddonCost = $branchAddonCost + $userAddonCost + $communicationAddonCost;
                $totalAmount    = floatval($subscription['amount']) + $totalAddonCost;
                $symbol         = getCurrencySymbol($subscription['currency']);
                $status         = strtolower($subscription['status']);
                $statusIcons    = ['active'=>'check-circle','pending'=>'clock','expired'=>'x-circle','cancelled'=>'slash'];
                $statusIcon     = $statusIcons[$status] ?? 'circle';
            ?>
            <div class="sub-card">
                <div class="sub-card-header">
                    <div class="plan-name">
                        <div class="plan-icon"><i class="feather icon-package"></i></div>
                        <?= htmlspecialchars($subscription['plan_name'] ?? 'Subscription') ?>
                    </div>
                    <span class="status-pill status-<?= $status ?>">
                        <i class="feather icon-<?= $statusIcon ?>"></i>
                        <?= ucfirst($status) ?>
                    </span>
                </div>
                <div class="sub-card-body">

                    <!-- Amount row -->
                    <div class="amount-row">
                        <div class="amount-box ab-blue">
                            <div class="ab-label"><i class="feather icon-dollar-sign" style="margin-right:3px;"></i><?= __('amount') ?></div>
                            <div class="ab-value"><?= $symbol . number_format($subscription['amount'], 2) ?></div>
                        </div>
                        <div class="amount-box ab-teal">
                            <div class="ab-label"><i class="feather icon-refresh-cw" style="margin-right:3px;"></i><?= __('billing_cycle') ?></div>
                            <div class="ab-value" style="font-size:15px;"><?= ucfirst($subscription['billing_cycle']) ?></div>
                        </div>
                    </div>

                    <!-- Add-on breakdown -->
                    <?php if ($totalAddonCost > 0): ?>
                    <div class="addon-breakdown">
                        <div class="ab-title">Add-on Costs</div>
                        <?php if ($branchAddonCost > 0): ?>
                        <div class="addon-row">
                            <span class="ar-label"><i class="feather icon-git-branch"></i>Branch Add-ons</span>
                            <span class="ar-value"><?= $symbol . number_format($branchAddonCost, 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($userAddonCost > 0): ?>
                        <div class="addon-row">
                            <span class="ar-label"><i class="feather icon-users"></i>User Add-ons</span>
                            <span class="ar-value"><?= $symbol . number_format($userAddonCost, 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($communicationAddonCost > 0): ?>
                        <div class="addon-row">
                            <span class="ar-label"><i class="feather icon-message-circle"></i>Communication Add-ons</span>
                            <span class="ar-value"><?= $symbol . number_format($communicationAddonCost, 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="addon-row" style="margin-top:8px;padding-top:8px;border-top:1px solid var(--border);">
                            <span class="ar-label" style="font-weight:700;color:var(--text-main);">Total Due</span>
                            <span class="ar-value" style="color:var(--green);font-size:15px;"><?= $symbol . number_format($totalAmount, 2) ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Dates -->
                    <div class="dates-row">
                        <div class="date-item">
                            <div class="di-label"><i class="feather icon-play" style="margin-right:3px;"></i><?= __('start_date') ?></div>
                            <div class="di-value"><?= date('M d, Y', strtotime($subscription['start_date'])) ?></div>
                        </div>
                        <div class="date-item">
                            <div class="di-label"><i class="feather icon-calendar" style="margin-right:3px;"></i><?= __('next_billing') ?></div>
                            <div class="di-value">
                                <?= $subscription['next_billing_date'] ? date('M d, Y', strtotime($subscription['next_billing_date'])) : '<span style="color:var(--text-sub);font-weight:400;">N/A</span>' ?>
                            </div>
                        </div>
                    </div>

                    <!-- Last payment -->
                    <?php if ($subscription['last_payment_date']): ?>
                    <div class="last-payment-row">
                        <i class="feather icon-check-circle" style="color:var(--green);"></i>
                        Last payment: <strong><?= date('M d, Y', strtotime($subscription['last_payment_date'])) ?></strong>
                    </div>
                    <?php endif; ?>

                    <!-- Pay button -->
                    <form method="post" action="process_subscription_payment.php">
                        <input type="hidden" name="csrf_token"      value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="subscription_id" value="<?= $subscription['id'] ?>">
                        <input type="hidden" name="amount"          value="<?= $totalAmount ?>">
                        <input type="hidden" name="currency"        value="<?= $subscription['currency'] ?>">
                        <input type="hidden" name="addon_cost"      value="<?= $totalAddonCost ?>">
                        <button type="submit" class="pay-btn">
                            <i class="feather icon-credit-card" style="margin-right:7px;"></i>Pay Now
                        </button>
                    </form>

                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-card">
                <i class="feather icon-package empty-icon"></i>
                <h5><?= __('no_subscriptions_found') ?></h5>
                <p><?= __('contact_admin_for_subscription_setup') ?></p>
                <button class="btn-contact"><i class="feather icon-mail" style="margin-right:6px;"></i>Contact Administrator</button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payment History -->
    <div class="dash-card">
        <div class="dash-card-head">
            <h6>
                <span class="ico"><i class="feather icon-clock"></i></span>
                <?= __('payment_history') ?>
                <span class="count-badge"><?= $payment_total_items ?> total</span>
            </h6>
            <form method="GET" class="search-wrap">
                <input type="text" name="payment_search" class="search-input"
                       placeholder="Search payments..." value="<?= htmlspecialchars($payment_search_query) ?>">
                <button type="submit" class="search-btn"><i class="feather icon-search"></i></button>
                <?php if (!empty($payment_search_query)): ?>
                <a href="subscription_payments.php" class="clear-btn">
                    <i class="feather icon-x"></i> Clear
                </a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (count($payments) > 0): ?>
        <div style="overflow-x:auto;">
            <table class="pay-table">
                <thead>
                    <tr>
                        <th><i class="feather icon-calendar" style="margin-right:4px;"></i><?= __('payment_date') ?></th>
                        <th><i class="feather icon-dollar-sign" style="margin-right:4px;"></i><?= __('amount') ?></th>
                        <th><?= __('currency') ?></th>
                        <th><i class="feather icon-package" style="margin-right:4px;"></i><?= __('plan') ?></th>
                        <th><i class="feather icon-credit-card" style="margin-right:4px;"></i><?= __('payment_method') ?></th>
                        <th><?= __('receipt_number') ?></th>
                        <th><i class="feather icon-user" style="margin-right:4px;"></i><?= __('processed_by') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment):
                        $sym = getCurrencySymbol($payment['currency']);
                    ?>
                    <tr>
                        <td class="td-date"><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                        <td class="td-amount"><?= $sym . number_format($payment['amount'], 2) ?></td>
                        <td class="td-currency"><?= htmlspecialchars($payment['currency']) ?></td>
                        <td class="td-plan"><?= htmlspecialchars($payment['plan_name'] ?: $payment['plan_id']) ?></td>
                        <td class="td-method">
                            <?= $payment['payment_method']
                                ? htmlspecialchars($payment['payment_method'])
                                : '<span class="na-text">N/A</span>' ?>
                        </td>
                        <td class="td-receipt">
                            <?= $payment['receipt_number']
                                ? htmlspecialchars($payment['receipt_number'])
                                : '<span class="na-text">N/A</span>' ?>
                        </td>
                        <td class="td-user"><?= htmlspecialchars($payment['processed_by_name'] ?: 'System') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($payment_total_pages > 1): ?>
        <?php
            $qs = !empty($payment_search_query) ? '&payment_search=' . urlencode($payment_search_query) : '';
            $start_p = max(1, $payment_current_page - 2);
            $end_p   = min($payment_total_pages, $payment_current_page + 2);
        ?>
        <div class="pag-wrap">
            <div class="pag-info">
                Page <?= $payment_current_page ?> of <?= $payment_total_pages ?> &nbsp;Â·&nbsp;
                Showing <?= count($payments) ?> of <?= $payment_total_items ?> payments
            </div>
            <div class="pag-links">
                <a href="subscription_payments.php?payment_page=<?= $payment_current_page - 1 . $qs ?>" 
                   class="pag-btn <?= $payment_current_page === 1 ? 'disabled' : '' ?>">
                    <i class="feather icon-chevron-left"></i>
                </a>

                <?php if ($start_p > 1): ?>
                    <a href="subscription_payments.php?payment_page=1<?= $qs ?>" class="pag-btn">1</a>
                    <?php if ($start_p > 2): ?><span class="pag-dots">...</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_p; $i <= $end_p; $i++): ?>
                <a href="subscription_payments.php?payment_page=<?= $i . $qs ?>" 
                   class="pag-btn <?= $i === $payment_current_page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($end_p < $payment_total_pages): ?>
                    <?php if ($end_p < $payment_total_pages - 1): ?><span class="pag-dots">...</span><?php endif; ?>
                    <a href="subscription_payments.php?payment_page=<?= $payment_total_pages . $qs ?>" class="pag-btn"><?= $payment_total_pages ?></a>
                <?php endif; ?>

                <a href="subscription_payments.php?payment_page=<?= $payment_current_page + 1 . $qs ?>"
                   class="pag-btn <?= $payment_current_page === $payment_total_pages ? 'disabled' : '' ?>">
                    <i class="feather icon-chevron-right"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div style="text-align:center;padding:56px 24px;">
            <i class="feather icon-file-text" style="font-size:48px;opacity:0.2;display:block;margin-bottom:16px;"></i>
            <h5 style="font-weight:700;color:var(--text-main);margin-bottom:6px;"><?= __('no_payment_history_found') ?></h5>
            <p style="color:var(--text-sub);font-size:14px;"><?= __('payment_history_will_appear_here') ?></p>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
window.addEventListener('message', function(event) {
    if (event.origin !== 'https://checkout.hesabpay.com') return;
    if (event.data && event.data.type === 'paymentSuccess') {
        const { success, message, transaction_id } = event.data;
        if (success) {
            alert('Payment completed successfully! Transaction ID: ' + transaction_id);
            window.location.reload();
        } else {
            alert('Payment failed: ' + message);
        }
    }
});
</script>

<?php include '../includes/admin_footer.php'; ?>
