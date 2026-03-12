<?php
/**
 * Request User Add-on - Tenant Interface
 */
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once '../includes/UserAddonManager.php';
require_once '../admin/security.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

$userAddonManager = new UserAddonManager($pdo, $tenant_id);
$usageStats       = $userAddonManager->getUsageStats();
$plan             = $usageStats['plan'];
$addonPricing     = $userAddonManager->getAddonPricing();
$currency         = htmlspecialchars($plan['currency'] ?? 'USD');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_addon'])) {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? null)) {
        $error = __('invalid_csrf_token');
    } else {
        $num_users     = intval($_POST['num_users'] ?? 0);
        $billing_cycle = $_POST['billing_cycle'] ?? 'monthly';
        if ($num_users <= 0) {
            $error = __('invalid_number_of_users');
        } elseif ($num_users > 100) {
            $error = __('max_users_per_request_exceeded');
        } else {
            $result = $userAddonManager->requestAdditionalUsers($tenant_id, $num_users, $billing_cycle);
            if ($result['success']) {
                $success    = sprintf(__('user_addon_request_submitted'), $num_users, $result['estimated_cost'], $result['currency']);
                $usageStats = $userAddonManager->getUsageStats();
            } else {
                $error = $result['message'];
            }
        }
    }
}

$all_pending_requests = $userAddonManager->getTenantAddonRequests($tenant_id, 'pending');
$all_active_addons    = $userAddonManager->getActiveUserAddons($tenant_id);

// Pagination — pending
$p_per_page     = 5;
$p_page         = max(1, intval($_GET['pending_page'] ?? 1));
$p_total        = count($all_pending_requests);
$p_pages        = max(1, ceil($p_total / $p_per_page));
$p_page         = min($p_page, $p_pages);
$pending_requests = array_slice($all_pending_requests, ($p_page - 1) * $p_per_page, $p_per_page);

// Pagination — active addons
$a_per_page     = 5;
$a_page         = max(1, intval($_GET['addon_page'] ?? 1));
$a_total        = count($all_active_addons);
$a_pages        = max(1, ceil($a_total / $a_per_page));
$a_page         = min($a_page, $a_pages);
$active_addons  = array_slice($all_active_addons, ($a_page - 1) * $a_per_page, $a_per_page);

$usage_pct      = min(100, intval($usageStats['usage_percentage'] ?? 0));
$page_title     = __('request_more_users');
include 'header.php';
?>

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
    --radius:    14px;
    --shadow:    0 2px 12px rgba(64,153,255,0.08);
    --shadow-md: 0 6px 24px rgba(64,153,255,0.13);
}

*, *::before, *::after { box-sizing: border-box; }
body, .pcoded-main-container { font-family: 'Plus Jakarta Sans', sans-serif !important; background: var(--surface) !important; color: var(--text-main) !important; }

/* ── Page Header ── */
.dash-header { background:var(--grad); border-radius:var(--radius); padding:24px 28px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 8px 32px rgba(64,153,255,0.22); position:relative; overflow:hidden; }
.dash-header::before { content:''; position:absolute; inset:0; background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat; }
.dash-header h4 { font-size:22px; font-weight:800; color:#fff; margin:0 0 4px; letter-spacing:-0.4px; position:relative; }
.dash-header p  { color:rgba(255,255,255,0.8); margin:0; font-size:13px; position:relative; }
.btn-back { display:inline-flex; align-items:center; gap:7px; background:rgba(255,255,255,0.18); color:#fff; border:1px solid rgba(255,255,255,0.3); border-radius:10px; padding:8px 16px; font-family:inherit; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; transition:all 0.2s; position:relative; }
.btn-back:hover { background:rgba(255,255,255,0.28); color:#fff; text-decoration:none; }

/* ── Alerts ── */
.dash-alert { display:flex; align-items:flex-start; gap:12px; padding:14px 20px; border-radius:var(--radius); margin-bottom:16px; font-size:14px; font-weight:500; animation:slideDown 0.3s ease; }
.dash-alert-success { background:#dcfce7; color:#166534; border-left:4px solid var(--green); }
.dash-alert-danger  { background:#fee2e2; color:#991b1b; border-left:4px solid var(--red); }
.dash-alert-warning { background:#fef3c7; color:#92400e; border-left:4px solid var(--amber); }
.dash-alert-info    { background:#eff6ff; color:#1e40af; border-left:4px solid var(--blue); }
.dash-alert .close-btn { background:none; border:none; cursor:pointer; opacity:0.5; font-size:18px; line-height:1; padding:0; color:inherit; margin-left:auto; flex-shrink:0; }
.dash-alert .close-btn:hover { opacity:1; }
@keyframes slideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }

/* ── Layout ── */
.page-grid { display:grid; grid-template-columns:300px 1fr; gap:20px; align-items:start; }
@media(max-width:1024px){ .page-grid{ grid-template-columns:1fr; } }

/* ── Cards ── */
.dash-card { background:var(--card-bg); border-radius:var(--radius); border:1px solid var(--border); box-shadow:var(--shadow); overflow:hidden; margin-bottom:20px; }
.dash-card:last-child { margin-bottom:0; }
.dash-card-head { padding:15px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; }
.dash-card-head h6 { font-size:14px; font-weight:700; margin:0; display:flex; align-items:center; gap:8px; }
.dash-card-head h6 .ico { width:28px; height:28px; border-radius:8px; background:var(--grad); display:flex; align-items:center; justify-content:center; color:#fff; font-size:13px; flex-shrink:0; }
.dash-card-body { padding:20px; }
.count-badge { background:rgba(64,153,255,0.1); color:var(--blue); border-radius:20px; padding:3px 10px; font-size:11px; font-weight:700; margin-left:auto; }
.warn-badge  { background:rgba(245,158,11,0.12); color:var(--amber); border-radius:20px; padding:3px 10px; font-size:11px; font-weight:700; margin-left:auto; }

/* ── Usage widget ── */
.usage-circle-wrap { text-align:center; padding:4px 0 20px; }
.usage-nums { font-size:36px; font-weight:800; font-family:'JetBrains Mono',monospace; color:var(--blue); line-height:1; margin-bottom:4px; }
.usage-nums span { font-size:18px; font-weight:600; color:var(--text-sub); }
.usage-label { font-size:12px; color:var(--text-sub); font-weight:600; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:16px; }

.usage-bar { height:8px; background:var(--border); border-radius:99px; overflow:hidden; margin-bottom:6px; }
.usage-bar-fill { height:100%; border-radius:99px; background:var(--grad); transition:width 0.6s ease; }
.usage-bar-fill.warn { background:linear-gradient(90deg,var(--amber),var(--red)); }
.usage-bar-pct { display:flex; justify-content:flex-end; font-size:11px; font-weight:700; color:var(--text-sub); margin-bottom:16px; }

.stat-row { display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--border); }
.stat-row:last-child { border-bottom:none; }
.sr-label { font-size:13px; color:var(--text-sub); display:flex; align-items:center; gap:6px; }
.sr-val   { font-weight:800; font-family:'JetBrains Mono',monospace; font-size:14px; }
.sv-blue  { color:var(--blue); }
.sv-green { color:var(--green); }
.sv-teal  { color:var(--teal); }

.plan-pill { display:inline-flex; align-items:center; gap:6px; background:var(--grad); color:#fff; border-radius:20px; padding:5px 14px; font-size:12px; font-weight:700; }

/* ── Pricing table ── */
.price-row { display:flex; align-items:center; justify-content:space-between; padding:11px 0; border-bottom:1px solid var(--border); }
.price-row:last-child { border-bottom:none; }
.price-label { font-size:13px; color:var(--text-sub); display:flex; align-items:center; gap:7px; }
.price-val   { font-family:'JetBrains Mono',monospace; font-weight:700; color:var(--green); font-size:14px; }

/* ── Form ── */
.form-label-custom { font-size:12px; font-weight:700; color:var(--text-sub); text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:7px; }
.form-input { width:100%; border:1.5px solid var(--border); border-radius:10px; padding:11px 14px; font-family:inherit; font-size:14px; color:var(--text-main); background:var(--surface); outline:none; transition:border-color 0.2s; }
.form-input:focus { border-color:var(--blue); background:#fff; box-shadow:0 0 0 3px rgba(64,153,255,0.12); }
.form-hint { font-size:12px; color:var(--text-sub); margin-top:5px; }
.form-row  { margin-bottom:18px; }

.cost-box { background:var(--surface); border:1.5px solid var(--border); border-radius:12px; padding:18px 20px; margin:20px 0; }
.cost-box-label { font-size:11px; font-weight:700; color:var(--text-sub); text-transform:uppercase; letter-spacing:0.6px; margin-bottom:10px; display:flex; align-items:center; gap:6px; }
.cost-total { font-size:28px; font-weight:800; color:var(--green); font-family:'JetBrains Mono',monospace; line-height:1; margin-bottom:4px; }
.cost-period { font-size:13px; color:var(--text-sub); font-weight:600; }
.cost-breakdown { font-size:12px; color:var(--text-sub); margin-top:6px; }

.form-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:20px; }
.submit-btn { display:inline-flex; align-items:center; gap:7px; background:var(--grad); color:#fff; border:none; border-radius:10px; padding:12px 24px; font-family:inherit; font-size:14px; font-weight:700; cursor:pointer; transition:all 0.2s; }
.submit-btn:hover { opacity:0.9; transform:translateY(-1px); box-shadow:0 4px 16px rgba(64,153,255,0.3); }
.cancel-btn { display:inline-flex; align-items:center; gap:7px; background:var(--surface); color:var(--text-sub); border:1.5px solid var(--border); border-radius:10px; padding:12px 22px; font-family:inherit; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; transition:all 0.2s; }
.cancel-btn:hover { border-color:var(--text-sub); color:var(--text-main); text-decoration:none; }

/* ── Tables ── */
.data-table { width:100%; border-collapse:collapse; }
.data-table thead th { background:var(--surface); padding:11px 16px; font-size:11px; font-weight:700; color:var(--text-sub); text-transform:uppercase; letter-spacing:0.6px; border-bottom:1.5px solid var(--border); white-space:nowrap; }
.data-table tbody tr { transition:background 0.15s; }
.data-table tbody tr:hover { background:var(--surface); }
.data-table tbody td { padding:13px 16px; border-bottom:1px solid var(--border); font-size:14px; vertical-align:middle; }
.data-table tbody tr:last-child td { border-bottom:none; }

.td-num   { font-weight:800; font-family:'JetBrains Mono',monospace; }
.td-money { font-weight:800; font-family:'JetBrains Mono',monospace; color:var(--green); }
.td-date  { font-size:12px; color:var(--text-sub); font-family:'JetBrains Mono',monospace; }
.td-cycle { font-weight:600; color:var(--teal); }
.td-muted { font-size:13px; color:var(--text-sub); font-family:'JetBrains Mono',monospace; }

.num-pill { display:inline-flex; align-items:center; gap:4px; background:rgba(64,153,255,0.1); color:var(--blue); border-radius:20px; padding:4px 12px; font-size:12px; font-weight:800; font-family:'JetBrains Mono',monospace; }
.num-pill.green { background:rgba(34,197,94,0.1); color:var(--green); }

.status-pill { display:inline-flex; align-items:center; gap:5px; border-radius:20px; padding:4px 11px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; }
.sp-pending { background:rgba(245,158,11,0.12); color:#92400e; }
.sp-active  { background:rgba(34,197,94,0.12);  color:#166534; }

/* Pagination */
.pag-wrap  { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:14px 20px; border-top:1px solid var(--border); }
.pag-info  { font-size:12px; color:var(--text-sub); }
.pag-links { display:flex; gap:4px; }
.pag-btn   { min-width:32px; height:32px; border-radius:8px; border:1.5px solid var(--border); background:var(--card-bg); color:var(--text-main); font-size:12px; font-weight:600; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; padding:0 8px; transition:all 0.15s; }
.pag-btn:hover  { border-color:var(--blue); color:var(--blue); text-decoration:none; }
.pag-btn.active { background:var(--grad); border-color:transparent; color:#fff; }
.pag-btn.disabled { opacity:0.4; pointer-events:none; }
.pag-dots  { display:flex; align-items:center; padding:0 4px; color:var(--text-sub); font-size:13px; }

/* Empty */
.empty-state { text-align:center; padding:40px 20px; }
.empty-state i { font-size:36px; opacity:0.2; display:block; margin-bottom:12px; }
.empty-state p { color:var(--text-sub); font-size:14px; margin:0; }

/* Overrides */
.pcoded-content { padding:20px !important; }
.page-header { display:none !important; }
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <!-- Header -->
    <div class="dash-header">
        <div>
            <h4><i class="feather icon-user-plus" style="margin-right:8px;"></i><?= __('request_more_users') ?></h4>
            <p><?= __('request_additional_user_slots') ?></p>
        </div>
        <a href="users.php" class="btn-back">
            <i class="feather icon-arrow-left"></i><?= __('back_to_add_employee') ?>
        </a>
    </div>

    <!-- Alerts -->
    <?php if (isset($error)): ?>
    <div class="dash-alert dash-alert-danger">
        <i class="feather icon-alert-circle"></i>
        <div style="flex:1;"><?= $error ?></div>
        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>
    <?php if (isset($success)): ?>
    <div class="dash-alert dash-alert-success">
        <i class="feather icon-check-circle"></i>
        <div style="flex:1;"><?= $success ?></div>
        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <div class="page-grid">

        <!-- LEFT SIDEBAR -->
        <div>
            <!-- Current Usage -->
            <div class="dash-card">
                <div class="dash-card-head">
                    <h6><span class="ico"><i class="feather icon-bar-chart-2"></i></span><?= __('current_usage') ?></h6>
                </div>
                <div class="dash-card-body">
                    <div class="usage-circle-wrap">
                        <div class="usage-nums"><?= $usageStats['current_users'] ?><span> / <?= $usageStats['max_users'] ?></span></div>
                        <div class="usage-label"><?= __('users_used') ?></div>
                        <div class="usage-bar">
                            <div class="usage-bar-fill <?= $usage_pct >= 75 ? 'warn' : '' ?>" style="width:<?= $usage_pct ?>%"></div>
                        </div>
                        <div class="usage-bar-pct"><?= $usage_pct ?>%</div>
                        <span class="plan-pill"><i class="feather icon-package"></i><?= htmlspecialchars($plan['name'] ?? __('no_plan')) ?></span>
                    </div>

                    <div class="stat-row">
                        <div class="sr-label"><i class="feather icon-home"></i><?= __('base_users') ?></div>
                        <div class="sr-val sv-blue"><?= $usageStats['base_users'] ?></div>
                    </div>
                    <div class="stat-row">
                        <div class="sr-label"><i class="feather icon-plus-circle"></i><?= __('addon_users') ?></div>
                        <div class="sr-val sv-green">+<?= $usageStats['additional_users'] ?></div>
                    </div>
                    <div class="stat-row">
                        <div class="sr-label"><i class="feather icon-maximize"></i><?= __('max_users') ?? 'Max Allowed' ?></div>
                        <div class="sr-val sv-teal"><?= $usageStats['max_users'] ?></div>
                    </div>
                </div>
            </div>

            <!-- Pricing -->
            <div class="dash-card">
                <div class="dash-card-head">
                    <h6><span class="ico"><i class="feather icon-tag"></i></span><?= __('addon_pricing') ?></h6>
                </div>
                <div class="dash-card-body">
                    <?php foreach ([
                        [__('monthly'),   $addonPricing['monthly'],   'icon-calendar'],
                        [__('quarterly'), $addonPricing['quarterly'], 'icon-layers'],
                        [__('yearly'),    $addonPricing['yearly'],    'icon-award'],
                    ] as [$label, $price, $icon]): ?>
                    <div class="price-row">
                        <div class="price-label"><i class="feather <?= $icon ?>"></i><?= $label ?></div>
                        <div class="price-val"><?= number_format($price, 2) ?> <?= $currency ?></div>
                    </div>
                    <?php endforeach; ?>
                    <div style="margin-top:12px;font-size:12px;color:var(--text-sub);text-align:center;"><?= __('per_user_per_billing_cycle') ?></div>
                </div>
            </div>
        </div>

        <!-- RIGHT MAIN CONTENT -->
        <div>
            <!-- Request Form -->
            <div class="dash-card">
                <div class="dash-card-head">
                    <h6><span class="ico"><i class="feather icon-user-plus"></i></span><?= __('request_additional_users') ?></h6>
                </div>
                <div class="dash-card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="request_addon" value="1">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-row">
                                    <label class="form-label-custom">
                                        <i class="feather icon-hash" style="margin-right:4px;"></i><?= __('number_of_additional_users') ?> *
                                    </label>
                                    <input type="number" class="form-input" id="num_users" name="num_users"
                                           min="1" max="100" value="1" required onchange="updateEstimatedCost()">
                                    <div class="form-hint"><i class="feather icon-info" style="margin-right:3px;"></i><?= __('max_100_users_per_request') ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-row">
                                    <label class="form-label-custom">
                                        <i class="feather icon-repeat" style="margin-right:4px;"></i><?= __('billing_cycle') ?> *
                                    </label>
                                    <select class="form-input" id="billing_cycle" name="billing_cycle" required onchange="updateEstimatedCost()">
                                        <option value="monthly"><?= __('monthly') ?> — <?= number_format($addonPricing['monthly'], 2) ?> <?= $currency ?>/<?= __('user') ?></option>
                                        <option value="quarterly"><?= __('quarterly') ?> — <?= number_format($addonPricing['quarterly'], 2) ?> <?= $currency ?>/<?= __('user') ?></option>
                                        <option value="yearly"><?= __('yearly') ?> — <?= number_format($addonPricing['yearly'], 2) ?> <?= $currency ?>/<?= __('user') ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Cost Estimator -->
                        <div class="cost-box">
                            <div class="cost-box-label"><i class="feather icon-dollar-sign"></i><?= __('estimated_cost') ?></div>
                            <div class="cost-total"><span id="estimated_cost"><?= number_format($addonPricing['monthly'], 2) ?></span> <?= $currency ?></div>
                            <div class="cost-period" id="cost_period">/ <?= __('month') ?></div>
                            <div class="cost-breakdown" id="cost_breakdown">
                                1 user × <?= number_format($addonPricing['monthly'], 2) ?> <?= $currency ?> = <?= number_format($addonPricing['monthly'], 2) ?> <?= $currency ?>/<?= __('month') ?>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="submit-btn">
                                <i class="feather icon-send"></i><?= __('submit_request') ?>
                            </button>
                            <a href="add_employee.php" class="cancel-btn">
                                <i class="feather icon-x"></i><?= __('cancel') ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Pending Requests -->
            <?php if (!empty($all_pending_requests)): ?>
            <div class="dash-card">
                <div class="dash-card-head">
                    <h6>
                        <span class="ico" style="background:linear-gradient(135deg,var(--amber),#f97316);"><i class="feather icon-clock"></i></span>
                        <?= __('pending_requests') ?>
                    </h6>
                    <span class="warn-badge"><?= $p_total ?> pending</span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><?= __('requested_users') ?></th>
                                <th><?= __('estimated_cost') ?></th>
                                <th><?= __('requested_at') ?></th>
                                <th><?= __('status') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_requests as $req): ?>
                            <tr>
                                <td><span class="num-pill">+<?= intval($req['requested_additional_users']) ?></span></td>
                                <td class="td-money"><?= number_format($req['estimated_monthly_cost'], 2) ?> <?= $currency ?></td>
                                <td class="td-date"><?= date('M d, Y H:i', strtotime($req['requested_at'])) ?></td>
                                <td><span class="status-pill sp-pending"><i class="feather icon-clock"></i>Pending</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($p_pages > 1): ?>
                <div class="pag-wrap">
                    <div class="pag-info">Page <?= $p_page ?> of <?= $p_pages ?></div>
                    <div class="pag-links">
                        <a href="?pending_page=<?= $p_page - 1 ?>" class="pag-btn <?= $p_page === 1 ? 'disabled' : '' ?>"><i class="feather icon-chevron-left"></i></a>
                        <?php for ($i = max(1,$p_page-2); $i <= min($p_pages,$p_page+2); $i++): ?>
                        <a href="?pending_page=<?= $i ?>" class="pag-btn <?= $i === $p_page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <a href="?pending_page=<?= $p_page + 1 ?>" class="pag-btn <?= $p_page === $p_pages ? 'disabled' : '' ?>"><i class="feather icon-chevron-right"></i></a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Active Add-ons -->
            <?php if (!empty($all_active_addons)): ?>
            <div class="dash-card">
                <div class="dash-card-head">
                    <h6>
                        <span class="ico" style="background:linear-gradient(135deg,var(--green),#16a34a);"><i class="feather icon-check-circle"></i></span>
                        <?= __('active_user_addons') ?>
                    </h6>
                    <span class="count-badge"><?= $a_total ?> active</span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><?= __('additional_users') ?></th>
                                <th><?= __('cost_per_user') ?></th>
                                <th><?= __('total_cost') ?></th>
                                <th><?= __('billing_cycle') ?></th>
                                <th><?= __('renewal_date') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_addons as $addon): ?>
                            <tr>
                                <td><span class="num-pill green">+<?= intval($addon['additional_users']) ?></span></td>
                                <td class="td-muted"><?= number_format($addon['addon_price_per_user'], 2) ?> <?= $currency ?></td>
                                <td class="td-money"><?= number_format($addon['total_addon_cost'], 2) ?> <?= $currency ?></td>
                                <td class="td-cycle"><?= ucfirst(htmlspecialchars($addon['billing_cycle'])) ?></td>
                                <td class="td-date"><?= $addon['next_renewal_date'] ? date('M d, Y', strtotime($addon['next_renewal_date'])) : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($a_pages > 1): ?>
                <div class="pag-wrap">
                    <div class="pag-info">Page <?= $a_page ?> of <?= $a_pages ?></div>
                    <div class="pag-links">
                        <a href="?addon_page=<?= $a_page - 1 ?>" class="pag-btn <?= $a_page === 1 ? 'disabled' : '' ?>"><i class="feather icon-chevron-left"></i></a>
                        <?php for ($i = max(1,$a_page-2); $i <= min($a_pages,$a_page+2); $i++): ?>
                        <a href="?addon_page=<?= $i ?>" class="pag-btn <?= $i === $a_page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <a href="?addon_page=<?= $a_page + 1 ?>" class="pag-btn <?= $a_page === $a_pages ? 'disabled' : '' ?>"><i class="feather icon-chevron-right"></i></a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div><!-- /right -->
    </div><!-- /page-grid -->

</div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
const pricing = {
    monthly:   <?= floatval($addonPricing['monthly']) ?>,
    quarterly: <?= floatval($addonPricing['quarterly']) ?>,
    yearly:    <?= floatval($addonPricing['yearly']) ?>
};
const currency = '<?= addslashes($currency) ?>';
const cycleLabels = {
    monthly:   '<?= addslashes(__("month")) ?>',
    quarterly: '<?= addslashes(__("quarter")) ?>',
    yearly:    '<?= addslashes(__("year")) ?>'
};

function updateEstimatedCost() {
    const n     = parseInt(document.getElementById('num_users').value) || 1;
    const cycle = document.getElementById('billing_cycle').value;
    const price = pricing[cycle];
    const total = n * price;
    const label = cycleLabels[cycle];

    document.getElementById('estimated_cost').textContent = total.toFixed(2);
    document.getElementById('cost_period').textContent    = '/ ' + label;
    document.getElementById('cost_breakdown').textContent =
        n + ' user' + (n > 1 ? 's' : '') + ' × ' + price.toFixed(2) + ' ' + currency +
        ' = ' + total.toFixed(2) + ' ' + currency + '/' + label;
}

document.addEventListener('DOMContentLoaded', updateEstimatedCost);
</script>

<?php include '../includes/admin_footer.php'; ?>