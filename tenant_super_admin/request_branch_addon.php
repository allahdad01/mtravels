<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset(); session_destroy();
    header('Location: ../login.php?timeout=1'); exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    header('Location: ../login.php'); exit();
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'header.php';
require_once '../includes/BranchAddonManager.php';

$tenant_id     = $_SESSION['tenant_id'];
$addon_manager = new BranchAddonManager($pdo, $tenant_id);
$pricing       = $addon_manager->getAddonPricing($tenant_id);
$plan_info     = $addon_manager->getTenantPlanInfo($tenant_id);
$default_billing_cycle = 'monthly';
if (is_array($plan_info) && !empty($plan_info['billing_cycle']) && in_array($plan_info['billing_cycle'], ['monthly', 'quarterly', 'yearly'], true)) {
    $default_billing_cycle = $plan_info['billing_cycle'];
}

if (!$plan_info) {
    $error = 'You do not have an active subscription. Please contact support.';
} else {
    $current_branches    = $addon_manager->getCurrentBranchCount($tenant_id);
    $additional_branches = $addon_manager->getTotalAdditionalBranches($tenant_id);
    $max_allowed         = $addon_manager->getMaxAllowedBranches($tenant_id);
    $can_add             = $addon_manager->canAddMoreBranches($tenant_id);
    $pending_requests    = $addon_manager->getTenantAddonRequests($tenant_id, 'pending');
    $approved_addons     = $addon_manager->getActiveBranchAddons($tenant_id);
    $addon_payment_history = $addon_manager->getAddonPaymentHistory($tenant_id);

    $items_per_page  = 10;
    $current_page    = intval($_GET['page'] ?? 1);
    $search_query    = $_GET['search'] ?? '';

    $filtered_history = $addon_payment_history;
    if (!empty($search_query)) {
        $sl = strtolower($search_query);
        $filtered_history = array_filter($addon_payment_history, function($p) use ($sl) {
            return strpos(strtolower($p['additional_branches']), $sl) !== false
                || strpos(strtolower($p['amount']), $sl) !== false
                || strpos(strtolower($p['currency']), $sl) !== false
                || strpos(strtolower($p['status']), $sl) !== false
                || strpos(strtolower(date('M d, Y', strtotime($p['payment_date'] ?? ''))), $sl) !== false;
        });
    }

    $total_items  = count($filtered_history);
    $total_pages  = max(1, ceil($total_items / $items_per_page));
    $current_page = max(1, min($current_page, $total_pages));
    $offset       = ($current_page - 1) * $items_per_page;
    $paginated_history = array_slice(array_values($filtered_history), $offset, $items_per_page);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_branches') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? null)) {
        $form_error = 'Invalid CSRF token';
    } else {
        $num_branches  = intval($_POST['num_branches'] ?? 0);
        $billing_cycle = $_POST['billing_cycle'] ?? $default_billing_cycle;
        if ($num_branches <= 0) {
            $form_error = 'Please enter a valid number of branches';
        } else {
            $result = $addon_manager->requestAdditionalBranches($tenant_id, $num_branches, $billing_cycle);
            if ($result['success']) {
                $form_success    = 'Your branch add-on request has been submitted successfully. Our team will review it shortly.';
                $pending_requests = $addon_manager->getTenantAddonRequests($tenant_id, 'pending');
            } else {
                $form_error = $result['message'];
            }
        }
    }
}

$currency    = htmlspecialchars($plan_info['currency'] ?? 'USD');
$default_cycle_price = floatval($pricing[$default_billing_cycle] ?? $pricing['monthly']);
$default_cycle_suffix = ['monthly' => '/month', 'quarterly' => '(3 months)', 'yearly' => '(12 months)'][$default_billing_cycle] ?? '/month';
$avail_slots = isset($max_allowed, $current_branches) ? $max_allowed - $current_branches : 0;
$usage_pct   = isset($max_allowed, $current_branches) && $max_allowed > 0 ? round(($current_branches / $max_allowed) * 100) : 0;
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

/* â”€â”€ Page Header â”€â”€ */
.dash-header { background:var(--grad); border-radius:var(--radius); padding:24px 28px; margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 8px 32px rgba(64,153,255,0.22); position:relative; overflow:hidden; }
.dash-header::before { content:''; position:absolute; inset:0; background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat; }
.dash-header h4 { font-size:22px; font-weight:800; color:#fff; margin:0 0 4px; letter-spacing:-0.4px; position:relative; }
.dash-header p  { color:rgba(255,255,255,0.8); margin:0; font-size:13px; position:relative; }

/* â”€â”€ Alerts â”€â”€ */
.dash-alert { display:flex; align-items:flex-start; gap:12px; padding:14px 20px; border-radius:var(--radius); margin-bottom:16px; font-size:14px; font-weight:500; animation:slideDown 0.3s ease; }
.dash-alert-success { background:#dcfce7; color:#166534; border-left:4px solid var(--green); }
.dash-alert-danger  { background:#fee2e2; color:#991b1b; border-left:4px solid var(--red); }
.dash-alert-warning { background:#fef3c7; color:#92400e; border-left:4px solid var(--amber); }
.dash-alert-info    { background:#eff6ff; color:#1e40af; border-left:4px solid var(--blue); }
.dash-alert .close-btn { background:none; border:none; cursor:pointer; opacity:0.5; font-size:18px; line-height:1; padding:0; color:inherit; margin-left:auto; flex-shrink:0; }
.dash-alert .close-btn:hover { opacity:1; }
@keyframes slideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }

/* â”€â”€ Two-column layout â”€â”€ */
.page-grid { display:grid; grid-template-columns:1fr 340px; gap:20px; align-items:start; }
@media(max-width:1024px){ .page-grid{ grid-template-columns:1fr; } }

/* â”€â”€ Shared card â”€â”€ */
.dash-card { background:var(--card-bg); border-radius:var(--radius); border:1px solid var(--border); box-shadow:var(--shadow); overflow:hidden; margin-bottom:20px; }
.dash-card-head { padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.dash-card-head h6 { font-size:15px; font-weight:700; margin:0; display:flex; align-items:center; gap:8px; }
.dash-card-head h6 .ico { width:30px; height:30px; border-radius:8px; background:var(--grad); display:flex; align-items:center; justify-content:center; color:#fff; font-size:14px; }
.dash-card-body { padding:20px; }
.count-badge { background:rgba(64,153,255,0.1); color:var(--blue); border-radius:20px; padding:3px 12px; font-size:12px; font-weight:700; margin-left:auto; }
.warn-badge  { background:rgba(245,158,11,0.1); color:var(--amber); border-radius:20px; padding:3px 12px; font-size:12px; font-weight:700; margin-left:auto; }

/* â”€â”€ Status card (sidebar) â”€â”€ */
.stat-row { display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1px solid var(--border); }
.stat-row:last-child { border-bottom:none; }
.stat-row .sr-label { font-size:13px; color:var(--text-sub); display:flex; align-items:center; gap:7px; font-weight:500; }
.stat-row .sr-val { font-weight:800; font-family:'JetBrains Mono',monospace; font-size:14px; }
.sv-blue   { color:var(--blue); }
.sv-teal   { color:var(--teal); }
.sv-green  { color:var(--green); }
.sv-amber  { color:var(--amber); }
.sv-red    { color:var(--red); }

.plan-badge { display:inline-flex; align-items:center; gap:6px; background:var(--grad); color:#fff; border-radius:20px; padding:5px 14px; font-size:12px; font-weight:700; }

/* Usage bar */
.usage-bar-wrap { padding:14px 0 0; }
.usage-bar-meta { display:flex; justify-content:space-between; font-size:12px; font-weight:600; color:var(--text-sub); margin-bottom:7px; }
.usage-bar { height:7px; background:var(--border); border-radius:99px; overflow:hidden; }
.usage-bar-fill { height:100%; border-radius:99px; background:var(--grad); transition:width 0.6s ease; }
.usage-bar-fill.warn { background:linear-gradient(90deg,var(--amber),var(--red)); }

/* â”€â”€ Request Form â”€â”€ */
.form-label { font-size:12px; font-weight:700; color:var(--text-sub); text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:7px; }
.form-input { width:100%; border:1.5px solid var(--border); border-radius:10px; padding:11px 14px; font-family:inherit; font-size:14px; color:var(--text-main); background:var(--surface); outline:none; transition:border-color 0.2s; }
.form-input:focus { border-color:var(--blue); background:#fff; box-shadow:0 0 0 3px rgba(64,153,255,0.12); }
.form-hint { font-size:12px; color:var(--text-sub); margin-top:5px; }

.cost-box { background:var(--surface); border:1.5px solid var(--border); border-radius:12px; padding:18px 20px; margin:20px 0; }
.cost-box-label { font-size:11px; font-weight:700; color:var(--text-sub); text-transform:uppercase; letter-spacing:0.6px; margin-bottom:10px; display:flex; align-items:center; gap:6px; }
.cost-box-row { display:flex; align-items:center; justify-content:space-between; }
.cost-breakdown { font-size:13px; color:var(--text-sub); }
.cost-total { font-size:22px; font-weight:800; color:var(--green); font-family:'JetBrains Mono',monospace; }

.submit-btn { width:100%; background:var(--grad); color:#fff; border:none; border-radius:10px; padding:13px; font-family:inherit; font-size:15px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:all 0.2s; }
.submit-btn:hover { opacity:0.9; transform:translateY(-1px); box-shadow:0 4px 16px rgba(64,153,255,0.3); }
.submit-btn:disabled { opacity:0.4; cursor:not-allowed; transform:none; }

/* â”€â”€ Tables â”€â”€ */
.data-table { width:100%; border-collapse:collapse; }
.data-table thead th { background:var(--surface); padding:11px 16px; font-size:11px; font-weight:700; color:var(--text-sub); text-transform:uppercase; letter-spacing:0.6px; border-bottom:1.5px solid var(--border); white-space:nowrap; }
.data-table tbody tr { transition:background 0.15s; }
.data-table tbody tr:hover { background:var(--surface); }
.data-table tbody td { padding:13px 16px; border-bottom:1px solid var(--border); font-size:14px; vertical-align:middle; }
.data-table tbody tr:last-child td { border-bottom:none; }

.td-num    { font-weight:800; font-family:'JetBrains Mono',monospace; color:var(--blue); }
.td-money  { font-weight:800; font-family:'JetBrains Mono',monospace; color:var(--green); }
.td-date   { font-size:12px; color:var(--text-sub); font-family:'JetBrains Mono',monospace; }
.td-period { font-size:12px; color:var(--text-sub); }
.td-cycle  { font-weight:600; color:var(--teal); }

.status-pill { display:inline-flex; align-items:center; gap:5px; border-radius:20px; padding:4px 11px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; }
.sp-pending   { background:rgba(245,158,11,0.12); color:#92400e; }
.sp-active    { background:rgba(34,197,94,0.12);  color:#166534; }
.sp-completed { background:rgba(34,197,94,0.12);  color:#166534; }
.sp-other     { background:rgba(107,122,153,0.12); color:var(--text-sub); }

/* search bar */
.search-wrap { display:flex; gap:8px; margin-left:auto; }
.search-input { border:1.5px solid var(--border); border-radius:10px; padding:8px 14px; font-family:inherit; font-size:13px; color:var(--text-main); background:var(--surface); outline:none; width:210px; transition:border-color 0.2s; }
.search-input:focus { border-color:var(--blue); background:#fff; }
.search-btn { background:var(--grad); color:#fff; border:none; border-radius:10px; padding:8px 14px; cursor:pointer; font-size:13px; }
.clear-btn  { background:var(--surface); color:var(--text-sub); border:1.5px solid var(--border); border-radius:10px; padding:8px 14px; cursor:pointer; font-size:13px; font-family:inherit; transition:all 0.2s; }
.clear-btn:hover { border-color:var(--red); color:var(--red); }

/* Pagination */
.pag-wrap  { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; padding:16px 20px; border-top:1px solid var(--border); }
.pag-info  { font-size:13px; color:var(--text-sub); }
.pag-links { display:flex; gap:4px; }
.pag-btn   { min-width:36px; height:36px; border-radius:9px; border:1.5px solid var(--border); background:var(--card-bg); color:var(--text-main); font-size:13px; font-weight:600; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; padding:0 10px; transition:all 0.15s; }
.pag-btn:hover  { border-color:var(--blue); color:var(--blue); text-decoration:none; }
.pag-btn.active { background:var(--grad); border-color:transparent; color:#fff; }
.pag-btn.disabled { opacity:0.4; pointer-events:none; }
.pag-dots  { display:flex; align-items:center; padding:0 4px; color:var(--text-sub); }

/* Empty */
.empty-state { text-align:center; padding:48px 20px; }
.empty-state i { font-size:40px; opacity:0.2; display:block; margin-bottom:14px; }
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
            <h4><i class="feather icon-plus-circle" style="margin-right:8px;"></i>Request Additional Branches</h4>
            <p>Expand your branch network by requesting add-on branches</p>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="dash-alert dash-alert-danger">
        <i class="feather icon-alert-circle"></i>
        <div style="flex:1;"><?= htmlspecialchars($error) ?></div>
    </div>
    <?php else: ?>

    <?php if (isset($form_success)): ?>
    <div class="dash-alert dash-alert-success">
        <i class="feather icon-check-circle"></i>
        <div style="flex:1;"><?= htmlspecialchars($form_success) ?></div>
        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>
    <?php if (isset($form_error)): ?>
    <div class="dash-alert dash-alert-danger">
        <i class="feather icon-alert-circle"></i>
        <div style="flex:1;"><?= htmlspecialchars($form_error) ?></div>
        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Two-col: Form + Status sidebar -->
    <div class="page-grid">

        <!-- LEFT: Request Form -->
        <div>
            <div class="dash-card">
                <div class="dash-card-head">
                    <h6><span class="ico"><i class="feather icon-plus-square"></i></span>Add-on Request</h6>
                </div>
                <div class="dash-card-body">
                    <?php if (!$can_add): ?>
                    <div class="dash-alert dash-alert-info" style="margin-bottom:0;">
                        <i class="feather icon-info"></i>
                        <div>You have reached the maximum number of branches. Please contact support to discuss plan upgrades.</div>
                    </div>
                    <?php else: ?>
                    <form method="POST" id="requestForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="request_branches">

                        <div class="row">
                            <div class="col-md-6">
                                <div style="margin-bottom:18px;">
                                    <label class="form-label"><i class="feather icon-hash" style="margin-right:5px;"></i>Number of Branches</label>
                                    <input type="number" class="form-input" id="num_branches" name="num_branches"
                                           min="1" max="<?= $avail_slots ?>" value="1" required onchange="updateCost()">
                                    <div class="form-hint"><i class="feather icon-info" style="margin-right:3px;"></i><?= $avail_slots ?> slots available</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div style="margin-bottom:18px;">
                                    <label class="form-label"><i class="feather icon-calendar" style="margin-right:5px;"></i>Billing Cycle</label>
                                    <select class="form-input" id="billing_cycle" name="billing_cycle" onchange="updateCost()">
                                        <option value="monthly" <?= $default_billing_cycle === 'monthly' ? 'selected' : '' ?>>Monthly —  <?= number_format($pricing['monthly'], 2) ?> <?= $currency ?>/branch</option>
                                        <option value="quarterly" <?= $default_billing_cycle === 'quarterly' ? 'selected' : '' ?>>Quarterly —  <?= number_format($pricing['quarterly'], 2) ?> <?= $currency ?>/branch</option>
                                        <option value="yearly" <?= $default_billing_cycle === 'yearly' ? 'selected' : '' ?>>Yearly —  <?= number_format($pricing['yearly'], 2) ?> <?= $currency ?>/branch</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Cost estimator -->
                        <div class="cost-box">
                            <div class="cost-box-label">
                                <i class="feather icon-dollar-sign"></i>Estimated Cost
                            </div>
                            <div class="cost-box-row">
                                <span class="cost-breakdown" id="costBreakdown">
                                    1 branch x <?= number_format($default_cycle_price, 2) ?> <?= $currency ?> = <?= number_format($default_cycle_price, 2) ?> <?= $currency ?> <?= $default_cycle_suffix ?>
                                </span>
                                <span class="cost-total" id="estimatedCost"><?= number_format($default_cycle_price, 2) ?> <?= $currency ?></span>
                            </div>
                        </div>

                        <button type="submit" class="submit-btn">
                            <i class="feather icon-send"></i>Submit Request
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Requests -->
            <?php if (!empty($pending_requests)): ?>
            <div class="dash-card">
                <div class="dash-card-head">
                    <h6><span class="ico" style="background:linear-gradient(135deg,var(--amber),#f97316);"><i class="feather icon-clock"></i></span>Pending Requests</h6>
                    <span class="warn-badge"><?= count($pending_requests) ?> pending</span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Branches</th>
                                <th>Est. Cost</th>
                                <th>Status</th>
                                <th>Requested On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_requests as $req): ?>
                            <tr>
                                <td class="td-num">+<?= intval($req['requested_additional_branches']) ?></td>
                                <td class="td-money"><?= number_format($req['estimated_monthly_cost'], 2) ?> <?= htmlspecialchars($req['currency']) ?></td>
                                <td><span class="status-pill sp-pending"><i class="feather icon-clock"></i>Pending Review</span></td>
                                <td class="td-date"><?= date('M d, Y', strtotime($req['requested_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Active Add-ons -->
            <?php if (!empty($approved_addons)): ?>
            <div class="dash-card">
                <div class="dash-card-head">
                    <h6><span class="ico" style="background:linear-gradient(135deg,var(--green),#16a34a);"><i class="feather icon-check-circle"></i></span>Active Add-ons</h6>
                    <span class="count-badge"><?= count($approved_addons) ?> active</span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Branches</th>
                                <th>Price/Branch</th>
                                <th>Total Cost</th>
                                <th>Cycle</th>
                                <th>Status</th>
                                <th>Next Renewal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($approved_addons as $addon): ?>
                            <tr>
                                <td class="td-num"><?= intval($addon['additional_branches']) ?></td>
                                <td style="color:var(--text-sub);font-family:'JetBrains Mono',monospace;font-size:13px;"><?= number_format($addon['addon_price_per_branch'], 2) ?> <?= htmlspecialchars($addon['currency']) ?></td>
                                <td class="td-money"><?= number_format($addon['total_addon_cost'], 2) ?> <?= htmlspecialchars($addon['currency']) ?></td>
                                <td class="td-cycle"><?= ucfirst($addon['billing_cycle']) ?></td>
                                <td><span class="status-pill sp-active"><i class="feather icon-check"></i><?= ucfirst($addon['status']) ?></span></td>
                                <td class="td-date"><?= $addon['next_renewal_date'] ? date('M d, Y', strtotime($addon['next_renewal_date'])) : '— ' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Payment History -->
            <?php if (!empty($addon_payment_history)): ?>
            <div class="dash-card">
                <div class="dash-card-head">
                    <h6><span class="ico"><i class="feather icon-clock"></i></span>Add-on Payment History</h6>
                    <form method="GET" class="search-wrap">
                        <input type="text" name="search" class="search-input" placeholder="Search..." value="<?= htmlspecialchars($search_query) ?>">
                        <button type="submit" class="search-btn"><i class="feather icon-search"></i></button>
                        <?php if (!empty($search_query)): ?>
                        <a href="request_branch_addon.php" class="clear-btn"><i class="feather icon-x"></i></a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if (empty($paginated_history)): ?>
                <div class="empty-state">
                    <i class="feather icon-search"></i>
                    <p>No results for "<?= htmlspecialchars($search_query) ?>"</p>
                </div>
                <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Branches</th>
                                <th>Amount</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th>Payment Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginated_history as $payment):
                                $sp = match($payment['status']) {
                                    'completed' => 'sp-completed',
                                    'active'    => 'sp-active',
                                    'pending'   => 'sp-pending',
                                    default     => 'sp-other'
                                };
                            ?>
                            <tr>
                                <td class="td-num"><?= intval($payment['additional_branches']) ?></td>
                                <td class="td-money"><?= number_format($payment['amount'], 2) ?> <?= htmlspecialchars($payment['currency']) ?></td>
                                <td class="td-period">
                                    <?= date('M d, Y', strtotime($payment['period_start'])) ?>
                                    <span style="color:var(--border);margin:0 4px;">→</span>
                                    <?= date('M d, Y', strtotime($payment['period_end'])) ?>
                                </td>
                                <td><span class="status-pill <?= $sp ?>"><?= ucfirst($payment['status']) ?></span></td>
                                <td class="td-date"><?= $payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '— ' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1):
                    $qs = !empty($search_query) ? '&search=' . urlencode($search_query) : '';
                    $sp2 = max(1, $current_page - 2);
                    $ep  = min($total_pages, $current_page + 2);
                ?>
                <div class="pag-wrap">
                    <div class="pag-info">Page <?= $current_page ?> of <?= $total_pages ?> Â· <?= count($paginated_history) ?> of <?= $total_items ?> records</div>
                    <div class="pag-links">
                        <a href="request_branch_addon.php?page=<?= $current_page - 1 . $qs ?>" class="pag-btn <?= $current_page === 1 ? 'disabled' : '' ?>"><i class="feather icon-chevron-left"></i></a>
                        <?php if ($sp2 > 1): ?>
                            <a href="request_branch_addon.php?page=1<?= $qs ?>" class="pag-btn">1</a>
                            <?php if ($sp2 > 2): ?><span class="pag-dots">...</span><?php endif; ?>
                        <?php endif; ?>
                        <?php for ($i = $sp2; $i <= $ep; $i++): ?>
                        <a href="request_branch_addon.php?page=<?= $i . $qs ?>" class="pag-btn <?= $i === $current_page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($ep < $total_pages): ?>
                            <?php if ($ep < $total_pages - 1): ?><span class="pag-dots">...</span><?php endif; ?>
                            <a href="request_branch_addon.php?page=<?= $total_pages . $qs ?>" class="pag-btn"><?= $total_pages ?></a>
                        <?php endif; ?>
                        <a href="request_branch_addon.php?page=<?= $current_page + 1 . $qs ?>" class="pag-btn <?= $current_page === $total_pages ? 'disabled' : '' ?>"><i class="feather icon-chevron-right"></i></a>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT: Status Sidebar -->
        <div>
            <div class="dash-card">
                <div class="dash-card-head">
                    <h6><span class="ico"><i class="feather icon-bar-chart-2"></i></span>Current Status</h6>
                </div>
                <div class="dash-card-body">
                    <div style="margin-bottom:14px;">
                        <div style="font-size:11px;font-weight:700;color:var(--text-sub);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Plan</div>
                        <span class="plan-badge"><i class="feather icon-package"></i><?= htmlspecialchars($plan_info['name']) ?></span>
                    </div>

                    <div class="stat-row">
                        <div class="sr-label"><i class="feather icon-git-branch"></i>Included</div>
                        <div class="sr-val sv-blue"><?= intval($plan_info['max_branches']) ?></div>
                    </div>
                    <div class="stat-row">
                        <div class="sr-label"><i class="feather icon-layers"></i>Current</div>
                        <div class="sr-val sv-teal"><?= intval($current_branches) ?></div>
                    </div>
                    <div class="stat-row">
                        <div class="sr-label"><i class="feather icon-plus-circle"></i>Add-ons</div>
                        <div class="sr-val sv-amber"><?= $additional_branches > 0 ? '+' . intval($additional_branches) : '— ' ?></div>
                    </div>
                    <div class="stat-row">
                        <div class="sr-label"><i class="feather icon-maximize"></i>Max Allowed</div>
                        <div class="sr-val sv-blue"><?= intval($max_allowed) ?></div>
                    </div>
                    <div class="stat-row">
                        <div class="sr-label"><i class="feather icon-check-circle"></i>Available Slots</div>
                        <div class="sr-val <?= $avail_slots > 0 ? 'sv-green' : 'sv-red' ?>"><?= intval($avail_slots) ?></div>
                    </div>

                    <div class="usage-bar-wrap">
                        <div class="usage-bar-meta">
                            <span>Usage</span>
                            <span><?= $current_branches ?> / <?= $max_allowed ?> (<?= $usage_pct ?>%)</span>
                        </div>
                        <div class="usage-bar">
                            <div class="usage-bar-fill <?= $usage_pct >= 90 ? 'warn' : '' ?>" style="width:<?= $usage_pct ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pricing Reference -->
            <div class="dash-card">
                <div class="dash-card-head">
                    <h6><span class="ico"><i class="feather icon-tag"></i></span>Pricing</h6>
                </div>
                <div class="dash-card-body">
                    <?php foreach ([
                        ['Monthly',   $pricing['monthly'],   'icon-calendar'],
                        ['Quarterly', $pricing['quarterly'], 'icon-layers'],
                        ['Yearly',    $pricing['yearly'],    'icon-award'],
                    ] as [$label, $price, $icon]): ?>
                    <div class="stat-row">
                        <div class="sr-label"><i class="feather <?= $icon ?>"></i><?= $label ?></div>
                        <div style="font-family:'JetBrains Mono',monospace;font-weight:700;color:var(--green);font-size:13px;">
                            <?= number_format($price, 2) ?> <?= $currency ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div style="margin-top:12px;font-size:12px;color:var(--text-sub);">Price per branch per billing period.</div>
                </div>
            </div>
        </div>

    </div><!-- /page-grid -->

    <?php endif; ?>
</div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
const pricingData = {
    monthly:   <?= floatval($pricing['monthly']) ?>,
    quarterly: <?= floatval($pricing['quarterly']) ?>,
    yearly:    <?= floatval($pricing['yearly']) ?>,
    currency:  '<?= addslashes($currency) ?>'
};

function updateCost() {
    const n     = parseInt(document.getElementById('num_branches').value) || 1;
    const cycle = document.getElementById('billing_cycle').value;
    const cur   = pricingData.currency;
    const price = pricingData[cycle] || pricingData.monthly;
    const total = n * price;
    const suffix = { monthly: '/month', quarterly: '(3 months)', yearly: '(12 months)' }[cycle];
    const label  = `${n} branch${n > 1 ? 'es' : ''} Ã— ${price.toFixed(2)} ${cur} = ${total.toFixed(2)} ${cur} ${suffix}`;
    document.getElementById('costBreakdown').textContent = label;
    document.getElementById('estimatedCost').textContent = `${total.toFixed(2)} ${cur}`;
}

document.addEventListener('DOMContentLoaded', updateCost);
</script>
