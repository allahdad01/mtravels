<?php
include 'header.php';
require_once '../includes/BranchAddonManager.php';

$tenant_id    = $_SESSION['tenant_id'];
$addon_manager = new BranchAddonManager($pdo, $tenant_id);

$plan_info            = $addon_manager->getTenantPlanInfo($tenant_id);
$current_branches     = $addon_manager->getCurrentBranchCount($tenant_id);
$max_allowed_branches = $addon_manager->getMaxAllowedBranches($tenant_id);
$additional_branches  = $addon_manager->getTotalAdditionalBranches($tenant_id);

$message     = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? null)) {
        $message = 'Security token validation failed. Please try again.';
        $messageType = 'danger';
    } elseif (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $name       = trim($_POST['name'] ?? '');
                $code       = trim($_POST['code'] ?? '');
                $address    = trim($_POST['address'] ?? '');
                $phone      = trim($_POST['phone'] ?? '');
                $email      = trim($_POST['email'] ?? '');
                $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;

                if (empty($name) || empty($code)) {
                    $message = 'Branch name and code are required.';
                    $messageType = 'danger';
                } elseif (!$addon_manager->canAddMoreBranches()) {
                    $message = "You have reached the maximum number of branches (" . $max_allowed_branches . "). ";
                    $message .= ($additional_branches > 0)
                        ? "To create more branches, please contact support or request additional branches."
                        : "Please <a href='../admin/request_branch_addon.php' style='font-weight:bold;text-decoration:underline;'>request additional branches</a> to exceed your plan limit.";
                    $messageType = 'danger';
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO branches (tenant_id, name, code, address, phone, email, manager_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$tenant_id, $name, $code, $address, $phone, $email, $manager_id, $_SESSION['user_id']]);
                        logActivity($pdo, $tenant_id, $_SESSION['user_id'], 'create', 'branches', $pdo->lastInsertId(), null, json_encode(compact('name','code','address','phone','email','manager_id')));
                        $message = 'Branch created successfully.';
                        $messageType = 'success';
                        $current_branches = $addon_manager->getCurrentBranchCount($tenant_id);
                    } catch (PDOException $e) {
                        $message = $e->getCode() == 23000 ? 'Branch code already exists.' : 'Error creating branch: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'update':
                $branch_id  = $_POST['branch_id'] ?? 0;
                $name       = trim($_POST['name'] ?? '');
                $code       = trim($_POST['code'] ?? '');
                $address    = trim($_POST['address'] ?? '');
                $phone      = trim($_POST['phone'] ?? '');
                $email      = trim($_POST['email'] ?? '');
                $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
                $status     = $_POST['status'] ?? 'active';

                if (empty($name) || empty($code) || !$branch_id) {
                    $message = 'Branch name, code, and ID are required.';
                    $messageType = 'danger';
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ? AND tenant_id = ?");
                        $stmt->execute([$branch_id, $tenant_id]);
                        $oldBranch = $stmt->fetch(PDO::FETCH_ASSOC);
                        $pdo->prepare("UPDATE branches SET name=?, code=?, address=?, phone=?, email=?, manager_id=?, status=?, updated_at=NOW() WHERE id=? AND tenant_id=?")
                            ->execute([$name, $code, $address, $phone, $email, $manager_id, $status, $branch_id, $tenant_id]);
                        logActivity($pdo, $tenant_id, $_SESSION['user_id'], 'update', 'branches', $branch_id, json_encode($oldBranch), json_encode(compact('name','code','address','phone','email','manager_id','status')));
                        $message = 'Branch updated successfully.';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = $e->getCode() == 23000 ? 'Branch code already exists.' : 'Error updating branch: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;

            case 'delete':
                $branch_id = $_POST['branch_id'] ?? 0;
                if (!$branch_id) {
                    $message = 'Branch ID is required.';
                    $messageType = 'danger';
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) as user_count FROM users WHERE branch_id = ? AND tenant_id = ?");
                        $stmt->execute([$branch_id, $tenant_id]);
                        $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['user_count'];
                        if ($userCount > 0) {
                            $message = 'Cannot delete branch with existing users. Please reassign users first.';
                            $messageType = 'danger';
                        } else {
                            $stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ? AND tenant_id = ?");
                            $stmt->execute([$branch_id, $tenant_id]);
                            $branchData = $stmt->fetch(PDO::FETCH_ASSOC);
                            $pdo->prepare("DELETE FROM branches WHERE id = ? AND tenant_id = ?")->execute([$branch_id, $tenant_id]);
                            logActivity($pdo, $tenant_id, $_SESSION['user_id'], 'delete', 'branches', $branch_id, json_encode($branchData), null);
                            $message = 'Branch deleted successfully.';
                            $messageType = 'success';
                        }
                    } catch (PDOException $e) {
                        $message = 'Error deleting branch: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

try {
    $stmt = $pdo->prepare("SELECT b.*, u.name as manager_name, (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND tenant_id = b.tenant_id) as user_count FROM branches b LEFT JOIN users u ON b.manager_id = u.id WHERE b.tenant_id = ? ORDER BY b.created_at DESC");
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $branches = []; }

try {
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE tenant_id = ? AND role IN ('admin','sales','finance','umrah','visa') ORDER BY name");
    $stmt->execute([$tenant_id]);
    $availableManagers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $availableManagers = []; }

function logActivity($pdo, $tenant_id, $user_id, $action, $table_name, $record_id, $old_values, $new_values) {
    try {
        $pdo->prepare("INSERT INTO activity_log (tenant_id, user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$tenant_id, $user_id, $action, $table_name, $record_id, $old_values, $new_values, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
    } catch (PDOException $e) { error_log("Failed to log activity: " . $e->getMessage()); }
}

$can_add = $addon_manager->canAddMoreBranches();
$usage_pct = $max_allowed_branches > 0 ? round(($current_branches / $max_allowed_branches) * 100) : 0;
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
.dash-header {
    background: var(--grad);
    border-radius: var(--radius); padding: 24px 28px; margin-bottom: 24px;
    display: flex; align-items: center; justify-content: space-between;
    box-shadow: 0 8px 32px rgba(64,153,255,0.22); position: relative; overflow: hidden;
}
.dash-header::before { content:''; position:absolute; inset:0; background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat; }
.dash-header h4 { font-size:22px; font-weight:800; color:#fff; margin:0 0 4px; letter-spacing:-0.4px; position:relative; }
.dash-header p  { color:rgba(255,255,255,0.8); margin:0; font-size:13px; position:relative; }
.dash-header-right { position:relative; }

/* ── Alerts ── */
.dash-alert { display:flex; align-items:flex-start; gap:12px; padding:14px 20px; border-radius:var(--radius); margin-bottom:16px; font-size:14px; font-weight:500; animation:slideDown 0.3s ease; }
.dash-alert-success { background:#dcfce7; color:#166534; border-left:4px solid var(--green); }
.dash-alert-danger  { background:#fee2e2; color:#991b1b; border-left:4px solid var(--red); }
.dash-alert-warning { background:#fef3c7; color:#92400e; border-left:4px solid var(--amber); }
.dash-alert-info    { background:#eff6ff; color:#1e40af; border-left:4px solid var(--blue); }
.dash-alert .close-btn { background:none; border:none; cursor:pointer; opacity:0.5; font-size:18px; line-height:1; padding:0; color:inherit; margin-left:auto; flex-shrink:0; }
.dash-alert .close-btn:hover { opacity:1; }
@keyframes slideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }

/* ── Plan Info Card ── */
.plan-card {
    background: var(--card-bg); border-radius: var(--radius);
    border: 1px solid var(--border); box-shadow: var(--shadow);
    margin-bottom: 20px; overflow: hidden;
}
.plan-card-header {
    padding: 14px 20px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 8px;
}
.plan-card-header h6 { font-size:14px; font-weight:700; margin:0; }
.plan-card-header .ico { width:28px; height:28px; border-radius:8px; background:var(--grad); display:flex; align-items:center; justify-content:center; color:#fff; font-size:13px; }

.plan-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:1px; background:var(--border); }
@media(max-width:768px){ .plan-stats{ grid-template-columns:repeat(2,1fr); } }
.plan-stat { background:var(--card-bg); padding:20px; text-align:center; }
.plan-stat .ps-icon { width:44px; height:44px; border-radius:12px; margin:0 auto 10px; display:flex; align-items:center; justify-content:center; font-size:18px; }
.ps-blue   { background:rgba(64,153,255,0.1);  color:var(--blue); }
.ps-teal   { background:rgba(46,216,182,0.1);  color:var(--teal); }
.ps-green  { background:rgba(34,197,94,0.1);   color:var(--green); }
.ps-amber  { background:rgba(245,158,11,0.1);  color:var(--amber); }
.plan-stat .ps-label { font-size:11px; font-weight:600; color:var(--text-sub); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px; }
.plan-stat .ps-value { font-size:22px; font-weight:800; font-family:'JetBrains Mono',monospace; }
.ps-blue .ps-value-c  { color:var(--blue); }
.ps-teal .ps-value-c  { color:var(--teal); }
.ps-green .ps-value-c { color:var(--green); }
.ps-amber .ps-value-c { color:var(--amber); }

/* Progress bar */
.usage-bar-wrap { padding:16px 20px; border-top:1px solid var(--border); }
.usage-bar-meta { display:flex; justify-content:space-between; font-size:12px; font-weight:600; color:var(--text-sub); margin-bottom:8px; }
.usage-bar { height:8px; background:var(--border); border-radius:99px; overflow:hidden; }
.usage-bar-fill { height:100%; border-radius:99px; background:var(--grad); transition:width 0.6s ease; }
.usage-bar-fill.danger { background:linear-gradient(90deg,var(--amber),var(--red)); }

/* ── Action row ── */
.action-row { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
.btn-create {
    display:inline-flex; align-items:center; gap:8px;
    background:var(--grad); color:#fff; border:none; border-radius:10px;
    padding:11px 22px; font-family:inherit; font-size:14px; font-weight:700;
    cursor:pointer; transition:all 0.2s; text-decoration:none;
}
.btn-create:hover { opacity:0.9; transform:translateY(-1px); box-shadow:0 4px 16px rgba(64,153,255,0.3); color:#fff; }
.btn-create:disabled, .btn-create.disabled { opacity:0.45; cursor:not-allowed; transform:none; box-shadow:none; }

/* ── Main table card ── */
.dash-card { background:var(--card-bg); border-radius:var(--radius); border:1px solid var(--border); box-shadow:var(--shadow); overflow:hidden; }
.dash-card-head { padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; }
.dash-card-head h6 { font-size:15px; font-weight:700; margin:0; display:flex; align-items:center; gap:8px; }
.dash-card-head h6 .ico { width:30px; height:30px; border-radius:8px; background:var(--grad); display:flex; align-items:center; justify-content:center; color:#fff; font-size:14px; }
.count-badge { background:rgba(64,153,255,0.1); color:var(--blue); border-radius:20px; padding:3px 12px; font-size:12px; font-weight:700; margin-left:auto; }

.branch-table { width:100%; border-collapse:collapse; }
.branch-table thead th { background:var(--surface); padding:12px 16px; font-size:11px; font-weight:700; color:var(--text-sub); text-transform:uppercase; letter-spacing:0.6px; border-bottom:1.5px solid var(--border); white-space:nowrap; }
.branch-table tbody tr { transition:background 0.15s; }
.branch-table tbody tr:hover { background:var(--surface); }
.branch-table tbody td { padding:14px 16px; border-bottom:1px solid var(--border); font-size:14px; vertical-align:middle; }
.branch-table tbody tr:last-child td { border-bottom:none; }

.td-code { font-family:'JetBrains Mono',monospace; font-weight:700; color:var(--blue); font-size:13px; }
.td-name { font-weight:700; color:var(--text-main); }
.td-manager-yes { font-weight:600; color:var(--green); display:flex; align-items:center; gap:5px; }
.td-manager-no  { color:var(--text-sub); font-style:italic; font-size:13px; }

.user-pill { display:inline-flex; align-items:center; gap:5px; background:rgba(64,153,255,0.1); color:var(--blue); border-radius:20px; padding:4px 12px; font-size:12px; font-weight:700; }

.status-pill { display:inline-flex; align-items:center; gap:5px; border-radius:20px; padding:5px 12px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; }
.sp-active   { background:rgba(34,197,94,0.12); color:#166534; }
.sp-inactive { background:rgba(107,122,153,0.12); color:var(--text-sub); }

.contact-cell { font-size:12px; line-height:1.8; color:var(--text-sub); }
.contact-cell span { display:flex; align-items:center; gap:5px; }
.contact-cell .ci { color:var(--blue); }

.td-date { font-size:12px; color:var(--text-sub); font-family:'JetBrains Mono',monospace; }

/* Action buttons */
.act-btn { width:34px; height:34px; border-radius:9px; border:1.5px solid var(--border); background:var(--card-bg); display:inline-flex; align-items:center; justify-content:center; cursor:pointer; transition:all 0.15s; font-size:14px; margin:0 2px; }
.act-btn.edit  { color:var(--blue); }
.act-btn.edit:hover  { background:rgba(64,153,255,0.1); border-color:var(--blue); }
.act-btn.del   { color:var(--red); }
.act-btn.del:hover   { background:rgba(239,68,68,0.1); border-color:var(--red); }

/* Empty state */
.empty-state { text-align:center; padding:60px 24px; }
.empty-state .ei { font-size:48px; opacity:0.2; display:block; margin-bottom:16px; }
.empty-state h5 { font-weight:700; margin-bottom:6px; }
.empty-state p  { color:var(--text-sub); font-size:14px; margin-bottom:20px; }

/* ── Modals ── */
.modal-content { border:none; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,0.18); font-family:inherit; }
.modal-header  { background:var(--grad); color:#fff; border-radius:16px 16px 0 0; border:none; padding:18px 24px; }
.modal-header .modal-title { font-weight:700; font-size:16px; }
.modal-header .close { color:#fff; opacity:0.8; font-size:22px; }
.modal-header .close:hover { opacity:1; }
.modal-body   { padding:24px; }
.modal-footer { border-top:1px solid var(--border); padding:16px 24px; gap:8px; display:flex; justify-content:flex-end; }

.form-group label { font-size:12px; font-weight:700; color:var(--text-sub); margin-bottom:6px; display:block; text-transform:uppercase; letter-spacing:0.5px; }
.form-control { border:1.5px solid var(--border); border-radius:10px; padding:10px 14px; font-family:inherit; font-size:14px; transition:border-color 0.2s; background:var(--surface); color:var(--text-main); }
.form-control:focus { border-color:var(--blue); outline:none; box-shadow:0 0 0 3px rgba(64,153,255,0.15); background:#fff; }
.form-text { font-size:12px; color:var(--text-sub); margin-top:4px; }

.btn-modal-primary { background:var(--grad); color:#fff; border:none; border-radius:10px; padding:10px 22px; font-family:inherit; font-size:14px; font-weight:700; cursor:pointer; transition:all 0.2s; }
.btn-modal-primary:hover { opacity:0.9; transform:translateY(-1px); }
.btn-modal-secondary { background:var(--surface); color:var(--text-sub); border:1.5px solid var(--border); border-radius:10px; padding:10px 20px; font-family:inherit; font-size:14px; font-weight:600; cursor:pointer; transition:all 0.2s; }
.btn-modal-secondary:hover { border-color:var(--text-sub); color:var(--text-main); }
.btn-modal-danger { background:var(--red); color:#fff; border:none; border-radius:10px; padding:10px 22px; font-family:inherit; font-size:14px; font-weight:700; cursor:pointer; transition:all 0.2s; }
.btn-modal-danger:hover { opacity:0.88; }

.delete-warning { background:#fef3c7; border-left:4px solid var(--amber); border-radius:10px; padding:14px 16px; font-size:13px; color:#92400e; display:flex; gap:10px; align-items:flex-start; margin-top:12px; }

/* Overrides */
.pcoded-content { padding:20px !important; }
.page-header { display:none !important; }
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <!-- Header -->
    <div class="dash-header">
        <div>
            <h4><i class="feather icon-git-branch" style="margin-right:8px;"></i>Branch Management</h4>
            <p>Create and manage your business branches</p>
        </div>
        <div class="dash-header-right">
            <button type="button" class="btn-create <?= !$can_add ? 'disabled' : '' ?>"
                    <?= $can_add ? 'data-toggle="modal" data-target="#createBranchModal"' : 'disabled' ?>>
                <i class="feather icon-plus"></i>New Branch
            </button>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($message): ?>
    <div class="dash-alert dash-alert-<?= $messageType ?>">
        <i class="feather icon-<?= $messageType === 'success' ? 'check-circle' : 'alert-circle' ?>"></i>
        <div style="flex:1;">
            <?php if ($messageType === 'danger' && strpos($message, 'request additional branches') !== false): ?>
                <?= $message ?>
            <?php else: ?>
                <?= htmlspecialchars($message) ?>
            <?php endif; ?>
        </div>
        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <?php if (!$can_add): ?>
    <div class="dash-alert dash-alert-warning">
        <i class="feather icon-alert-triangle"></i>
        <div style="flex:1;">You have reached your maximum branch limit.
            <a href="request_branch_addon.php" style="font-weight:700;color:inherit;text-decoration:underline;">Request more branches</a>
        </div>
    </div>
    <?php elseif ($current_branches < $max_allowed_branches): ?>
    <div class="dash-alert dash-alert-info">
        <i class="feather icon-info"></i>
        <div style="flex:1;"><?= $max_allowed_branches - $current_branches ?> more branch slot<?= ($max_allowed_branches - $current_branches) !== 1 ? 's' : '' ?> available on your current plan.</div>
        <button class="close-btn" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Plan Info Card -->
    <?php if ($plan_info): ?>
    <div class="plan-card">
        <div class="plan-card-header">
            <span class="ico"><i class="feather icon-package"></i></span>
            <h6>Plan Usage</h6>
            <span style="margin-left:auto;font-size:12px;font-weight:600;color:var(--text-sub);"><?= htmlspecialchars($plan_info['name']) ?></span>
        </div>
        <div class="plan-stats">
            <div class="plan-stat">
                <div class="ps-icon ps-blue"><i class="feather icon-git-branch"></i></div>
                <div class="ps-label">Current</div>
                <div class="ps-value ps-blue"><span class="ps-value-c"><?= $current_branches ?></span></div>
            </div>
            <div class="plan-stat">
                <div class="ps-icon ps-teal"><i class="feather icon-layers"></i></div>
                <div class="ps-label">Max Allowed</div>
                <div class="ps-value ps-teal"><span class="ps-value-c"><?= $max_allowed_branches ?></span></div>
            </div>
            <div class="plan-stat">
                <div class="ps-icon ps-green"><i class="feather icon-check"></i></div>
                <div class="ps-label">Plan Included</div>
                <div class="ps-value ps-green"><span class="ps-value-c"><?= htmlspecialchars($plan_info['max_branches']) ?></span></div>
            </div>
            <div class="plan-stat">
                <div class="ps-icon ps-amber"><i class="feather icon-plus-circle"></i></div>
                <div class="ps-label">Add-ons</div>
                <div class="ps-value ps-amber"><span class="ps-value-c"><?= $additional_branches > 0 ? '+' . $additional_branches : '—' ?></span></div>
            </div>
        </div>
        <div class="usage-bar-wrap">
            <div class="usage-bar-meta">
                <span>Branch Usage</span>
                <span><?= $current_branches ?> / <?= $max_allowed_branches ?> (<?= $usage_pct ?>%)</span>
            </div>
            <div class="usage-bar">
                <div class="usage-bar-fill <?= $usage_pct >= 90 ? 'danger' : '' ?>" style="width:<?= $usage_pct ?>%"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Branches Table -->
    <div class="dash-card">
        <div class="dash-card-head">
            <h6><span class="ico"><i class="feather icon-git-branch"></i></span>All Branches</h6>
            <span class="count-badge"><?= count($branches) ?> branch<?= count($branches) !== 1 ? 'es' : '' ?></span>
        </div>

        <?php if (!empty($branches)): ?>
        <div style="overflow-x:auto;">
            <table class="branch-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Manager</th>
                        <th>Users</th>
                        <th>Status</th>
                        <th>Contact</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $branch): ?>
                    <tr>
                        <td class="td-code"><?= htmlspecialchars($branch['code']) ?></td>
                        <td class="td-name"><?= htmlspecialchars($branch['name']) ?></td>
                        <td>
                            <?php if ($branch['manager_name']): ?>
                                <div class="td-manager-yes">
                                    <i class="feather icon-user-check"></i>
                                    <?= htmlspecialchars($branch['manager_name']) ?>
                                </div>
                            <?php else: ?>
                                <span class="td-manager-no">Not assigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="user-pill">
                                <i class="feather icon-users"></i>
                                <?= $branch['user_count'] ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-pill <?= $branch['status'] === 'active' ? 'sp-active' : 'sp-inactive' ?>">
                                <i class="feather icon-<?= $branch['status'] === 'active' ? 'check-circle' : 'x-circle' ?>"></i>
                                <?= ucfirst($branch['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($branch['phone'] || $branch['email']): ?>
                            <div class="contact-cell">
                                <?php if ($branch['phone']): ?>
                                <span><i class="feather icon-phone ci"></i><?= htmlspecialchars($branch['phone']) ?></span>
                                <?php endif; ?>
                                <?php if ($branch['email']): ?>
                                <span><i class="feather icon-mail ci"></i><?= htmlspecialchars($branch['email']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                                <span style="color:var(--border);font-style:italic;font-size:13px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="td-date"><?= date('M d, Y', strtotime($branch['created_at'])) ?></td>
                        <td>
                            <button class="act-btn edit" onclick="editBranch(<?= $branch['id'] ?>)" title="Edit">
                                <i class="feather icon-edit"></i>
                            </button>
                            <button class="act-btn del" onclick="deleteBranch(<?= $branch['id'] ?>, '<?= htmlspecialchars($branch['name'], ENT_QUOTES) ?>')" title="Delete">
                                <i class="feather icon-trash-2"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="feather icon-git-branch ei"></i>
            <h5>No branches yet</h5>
            <p>Create your first branch to start managing your business network.</p>
            <?php if ($can_add): ?>
            <button type="button" class="btn-create" data-toggle="modal" data-target="#createBranchModal">
                <i class="feather icon-plus"></i>Create Your First Branch
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- Create Branch Modal -->
<div class="modal fade" id="createBranchModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-plus-circle" style="margin-right:8px;"></i>Create New Branch</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Branch Name *</label>
                                <input type="text" class="form-control" name="name" required placeholder="e.g. Kabul Main Office">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Branch Code *</label>
                                <input type="text" class="form-control" name="code" required placeholder="e.g. KBL-01">
                                <div class="form-text">Unique identifier for this branch</div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" class="form-control" name="phone" placeholder="+93 70 000 0000">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" name="email" placeholder="branch@example.com">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea class="form-control" name="address" rows="3" placeholder="Full branch address…"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Branch Manager</label>
                        <select class="form-control" name="manager_id">
                            <option value="">Select Manager (Optional)</option>
                            <?php foreach ($availableManagers as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> · <?= htmlspecialchars($m['email']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-modal-primary"><i class="feather icon-plus" style="margin-right:6px;"></i>Create Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Branch Modal -->
<div class="modal fade" id="editBranchModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-edit" style="margin-right:8px;"></i>Edit Branch</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" id="editBranchForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="branch_id" id="editBranchId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Branch Name *</label>
                                <input type="text" class="form-control" id="editBranchName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Branch Code *</label>
                                <input type="text" class="form-control" id="editBranchCode" name="code" required>
                                <div class="form-text">Unique identifier for this branch</div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" class="form-control" id="editBranchPhone" name="phone">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" id="editBranchEmail" name="email">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea class="form-control" id="editBranchAddress" name="address" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Branch Manager</label>
                                <select class="form-control" id="editBranchManager" name="manager_id">
                                    <option value="">Select Manager (Optional)</option>
                                    <?php foreach ($availableManagers as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> · <?= htmlspecialchars($m['email']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>
                                <select class="form-control" id="editBranchStatus" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-modal-primary"><i class="feather icon-save" style="margin-right:6px;"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Branch Modal -->
<div class="modal fade" id="deleteBranchModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#ef4444,#dc2626);">
                <h5 class="modal-title"><i class="feather icon-trash-2" style="margin-right:8px;"></i>Delete Branch</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" id="deleteBranchForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="branch_id" id="deleteBranchId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-body">
                    <p style="font-size:15px;color:var(--text-main);">
                        Are you sure you want to delete <strong id="deleteBranchName"></strong>?
                    </p>
                    <div class="delete-warning">
                        <i class="feather icon-alert-triangle" style="flex-shrink:0;margin-top:1px;"></i>
                        <span>This action is permanent and cannot be undone. All branch data will be removed.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-modal-danger"><i class="feather icon-trash-2" style="margin-right:6px;"></i>Delete Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editBranch(branchId) {
    $.ajax({
        url: 'get_branch.php', type: 'GET', data: { id: branchId }, dataType: 'json',
        success: function(r) {
            if (r.success) {
                const b = r.branch;
                $('#editBranchId').val(b.id);
                $('#editBranchName').val(b.name);
                $('#editBranchCode').val(b.code);
                $('#editBranchPhone').val(b.phone || '');
                $('#editBranchEmail').val(b.email || '');
                $('#editBranchAddress').val(b.address || '');
                $('#editBranchManager').val(b.manager_id || '');
                $('#editBranchStatus').val(b.status);
                $('#editBranchModal').modal('show');
            } else {
                alert('Error loading branch: ' + (r.message || 'Unknown error'));
            }
        },
        error: function(xhr, s, e) { alert('Error loading branch data: ' + e); }
    });
}

function deleteBranch(id, name) {
    $('#deleteBranchId').val(id);
    $('#deleteBranchName').text('"' + name + '"');
    $('#deleteBranchModal').modal('show');
}
</script>

<?php include 'footer.php'; ?>