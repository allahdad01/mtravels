<?php
include 'header.php';

$tenant_id     = $_SESSION['tenant_id'];
$user_id       = $_SESSION['user_id'];
$user_role     = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? null;

$search       = isset($_GET['search'])     ? trim($_GET['search'])     : '';
$user_filter  = isset($_GET['user_id'])    ? trim($_GET['user_id'])    : '';
$action_filter= isset($_GET['action'])     ? trim($_GET['action'])     : '';
$table_filter = isset($_GET['table_name']) ? trim($_GET['table_name']) : '';

$page             = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1;
$results_per_page = 25;
$offset           = ($page - 1) * $results_per_page;

// Branch name
$current_branch_name = "All Branches";
if ($user_branch_id) {
    $s = $pdo->prepare("SELECT name FROM branches WHERE id=? AND tenant_id=?");
    $s->execute([$user_branch_id, $tenant_id]);
    $bd = $s->fetch(PDO::FETCH_ASSOC);
    if ($bd) $current_branch_name = $bd['name'];
}

// Main query
$q = "SELECT al.*,u.name as user_name,b.name as branch_name FROM activity_log al LEFT JOIN users u ON al.user_id=u.id LEFT JOIN branches b ON u.branch_id=b.id WHERE al.tenant_id=?";
$p = [$tenant_id];
if ($user_branch_id) { $q .= " AND u.branch_id=?"; $p[] = $user_branch_id; }
if (!empty($user_filter))  { $q .= " AND al.user_id=?";   $p[] = $user_filter; }
if (!empty($action_filter)){ $q .= " AND al.action=?";     $p[] = $action_filter; }
if (!empty($table_filter)) { $q .= " AND al.table_name=?"; $p[] = $table_filter; }
if (!empty($search)) {
    $q .= " AND (u.name LIKE ? OR al.action LIKE ? OR al.table_name LIKE ? OR al.record_id LIKE ?)";
    $sp = "%$search%"; $p = array_merge($p,[$sp,$sp,$sp,$sp]);
}
$q .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
$p[] = $results_per_page; $p[] = $offset;
$stmt = $pdo->prepare($q); $stmt->execute($p);
$activity_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count query
$cq = "SELECT COUNT(*) as total FROM activity_log al LEFT JOIN users u ON al.user_id=u.id WHERE al.tenant_id=?";
$cp = [$tenant_id];
if ($user_branch_id) { $cq .= " AND u.branch_id=?"; $cp[] = $user_branch_id; }
if (!empty($user_filter))  { $cq .= " AND al.user_id=?";   $cp[] = $user_filter; }
if (!empty($action_filter)){ $cq .= " AND al.action=?";     $cp[] = $action_filter; }
if (!empty($table_filter)) { $cq .= " AND al.table_name=?"; $cp[] = $table_filter; }
if (!empty($search)) {
    $cq .= " AND (u.name LIKE ? OR al.action LIKE ? OR al.table_name LIKE ? OR al.record_id LIKE ?)";
    $sp = "%$search%"; $cp = array_merge($cp,[$sp,$sp,$sp,$sp]);
}
$cs = $pdo->prepare($cq); $cs->execute($cp);
$total_logs  = $cs->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_logs / $results_per_page);

// Filter options
$uq = "SELECT DISTINCT u.id,u.name FROM users u WHERE u.tenant_id=?";
$up = [$tenant_id];
if ($user_branch_id){ $uq .= " AND u.branch_id=?"; $up[] = $user_branch_id; }
$uq .= " ORDER BY u.name";
$us = $pdo->prepare($uq); $us->execute($up);
$users = $us->fetchAll(PDO::FETCH_ASSOC);

$aq = "SELECT DISTINCT action FROM activity_log WHERE tenant_id=?"; $ap = [$tenant_id];
if ($user_branch_id){ $aq .= " AND user_id IN(SELECT id FROM users WHERE tenant_id=? AND branch_id=?)"; $ap=array_merge($ap,[$tenant_id,$user_branch_id]); }
$as2 = $pdo->prepare($aq); $as2->execute($ap); $actions = $as2->fetchAll(PDO::FETCH_ASSOC);

$tq = "SELECT DISTINCT table_name FROM activity_log WHERE tenant_id=?"; $tp = [$tenant_id];
if ($user_branch_id){ $tq .= " AND user_id IN(SELECT id FROM users WHERE tenant_id=? AND branch_id=?)"; $tp=array_merge($tp,[$tenant_id,$user_branch_id]); }
$ts = $pdo->prepare($tq); $ts->execute($tp); $tables = $ts->fetchAll(PDO::FETCH_ASSOC);

// Summary
$sq = "SELECT COUNT(*) as total_logs,COUNT(DISTINCT user_id) as unique_users,COUNT(DISTINCT DATE(created_at)) as active_days FROM activity_log WHERE tenant_id=?";
$sp2 = [$tenant_id];
if ($user_branch_id){ $sq .= " AND user_id IN(SELECT id FROM users WHERE tenant_id=? AND branch_id=?)"; $sp2=array_merge($sp2,[$tenant_id,$user_branch_id]); }
$ss = $pdo->prepare($sq); $ss->execute($sp2);
$summary = $ss->fetch(PDO::FETCH_ASSOC);

function getActionStyle($action) {
    $a = strtolower($action);
    if (in_array($a,['create','insert'])) return ['cls'=>'act-create','icon'=>'plus-circle'];
    if (in_array($a,['update','edit']))   return ['cls'=>'act-update','icon'=>'edit-2'];
    if (in_array($a,['delete','remove'])) return ['cls'=>'act-delete','icon'=>'trash-2'];
    if ($a==='login')                     return ['cls'=>'act-login', 'icon'=>'log-in'];
    if ($a==='logout')                    return ['cls'=>'act-logout','icon'=>'log-out'];
    return ['cls'=>'act-other','icon'=>'activity'];
}

function buildPaginationUrl($p,$search,$uf,$af,$tf){
    return "?page=$p&search=".urlencode($search)."&user_id=".urlencode($uf)."&action=".urlencode($af)."&table_name=".urlencode($tf);
}
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root{
    --surface:#f4f7fe;--card-bg:#ffffff;--border:#e8edf5;
    --text-main:#1a2340;--text-sub:#6b7a99;
    --green:#22c55e;--red:#ef4444;--amber:#f59e0b;
    /* Activity Logs: Slate → Indigo */
    --c1:#334155;--c2:#4f46e5;
    --radius:14px;--shadow:0 2px 12px rgba(51,65,85,.08);
}
*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}
.pcoded-content{padding:20px!important}
.page-header{display:none!important}

/* Header */
.dash-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 32px rgba(51,65,85,.28);position:relative;overflow:hidden}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='30' cy='30' r='20' fill='%23ffffff' fill-opacity='0.05'/%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;letter-spacing:-.4px;position:relative}
.dash-header p{color:rgba(255,255,255,.8);margin:0;font-size:13px;position:relative}
.back-btn{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.2);color:#fff;border:1.5px solid rgba(255,255,255,.3);border-radius:10px;padding:9px 16px;font-family:inherit;font-size:12px;font-weight:700;text-decoration:none;transition:all .2s;position:relative}
.back-btn:hover{background:rgba(255,255,255,.3);color:#fff;text-decoration:none}

/* Stat tiles */
.stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}
.stat-tile{border-radius:var(--radius);padding:20px;color:#fff;position:relative;overflow:hidden;text-align:center}
.stat-tile::after{content:'';position:absolute;right:-10px;bottom:-10px;width:60px;height:60px;border-radius:50%;background:rgba(255,255,255,.1)}
.st-logs  {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(51,65,85,.28)}
.st-users {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(5,150,105,.25)}
.st-days  {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(217,119,6,.25)}
.stat-icon{width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:18px;margin:0 auto 12px}
.stat-val{font-family:'JetBrains Mono',monospace;font-size:28px;font-weight:800;line-height:1;margin-bottom:4px}
.stat-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;opacity:.85}

/* Cards */
.dash-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.dash-card-head{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px}
.dash-card-head h6{font-size:14px;font-weight:700;margin:0}
.ico{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0}
.dash-card-body{padding:18px}

/* Filter bar */
.filter-grid{display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr auto auto;gap:10px;align-items:end}
@media(max-width:1100px){.filter-grid{grid-template-columns:1fr 1fr 1fr}}
.form-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:block;margin-bottom:5px}
.form-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:9px 12px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s}
.form-input:focus{border-color:#334155;background:#fff;box-shadow:0 0 0 3px rgba(51,65,85,.1)}
select.form-input{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7a99' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;padding-right:32px}
.filter-btn{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border:none;border-radius:10px;padding:9px 18px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap}
.clear-btn{display:inline-flex;align-items:center;gap:6px;background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border);border-radius:10px;padding:9px 14px;font-family:inherit;font-size:13px;font-weight:600;text-decoration:none;white-space:nowrap}
.clear-btn:hover{border-color:var(--text-sub);color:var(--text-main);text-decoration:none}

/* Table */
.data-table{width:100%;border-collapse:collapse}
.data-table thead th{background:var(--surface);padding:10px 14px;font-size:10px;font-weight:700;color:var(--text-sub);text-transform:uppercase;letter-spacing:.6px;border-bottom:1.5px solid var(--border);white-space:nowrap;text-align:left}
.data-table tbody tr{cursor:pointer;transition:background .15s}
.data-table tbody tr:hover{background:rgba(79,70,229,.03)}
.data-table tbody td{padding:11px 14px;border-bottom:1px solid var(--border);font-size:12px;vertical-align:middle}
.data-table tbody tr:last-child td{border-bottom:none}

/* Action badges */
.act-badge{display:inline-flex;align-items:center;gap:5px;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700}
.act-create{background:rgba(34,197,94,.12);color:#166534}
.act-update{background:rgba(79,70,229,.1);color:#3730a3}
.act-delete{background:rgba(239,68,68,.1);color:#991b1b}
.act-login {background:rgba(8,145,178,.1);color:#0e7490}
.act-logout{background:rgba(107,122,153,.1);color:var(--text-sub)}
.act-other {background:rgba(217,119,6,.1);color:#92400e}

/* Cell elements */
.user-name{font-weight:700;font-size:12px;color:var(--text-main)}
.branch-tag{display:inline-flex;align-items:center;gap:3px;font-size:10px;color:var(--text-sub);margin-top:2px}
.table-tag{font-size:10px;color:var(--text-sub);margin-top:2px;font-family:'JetBrains Mono',monospace}
.record-id{font-family:'JetBrains Mono',monospace;font-size:12px;font-weight:600;color:var(--text-main)}
.change-tag{font-size:10px;font-weight:600;margin-top:2px}
.change-tag.modified{color:#3730a3}
.change-tag.created {color:#166534}
.change-tag.deleted {color:#991b1b}
.ip-code{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text-sub);background:var(--surface);border-radius:6px;padding:2px 7px}
.ts-date{font-size:12px;font-weight:600;color:var(--text-main)}
.ts-time{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--text-sub)}
.view-btn{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;border:1.5px solid var(--border);background:var(--card-bg);cursor:pointer;font-size:12px;color:var(--text-sub);transition:all .15s}
.view-btn:hover{border-color:#4f46e5;color:#4f46e5}

/* Empty state */
.empty-state{text-align:center;padding:50px 20px}
.empty-state i{font-size:44px;opacity:.2;display:block;margin-bottom:14px}
.empty-state p{color:var(--text-sub);font-size:14px}

/* Pagination */
.pag-row{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-top:1px solid var(--border);flex-wrap:wrap;gap:8px}
.pag-info{font-size:12px;color:var(--text-sub)}
.pag-btns{display:flex;gap:4px;flex-wrap:wrap}
.pag-btn{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;border-radius:8px;border:1.5px solid var(--border);background:var(--card-bg);font-family:inherit;font-size:12px;font-weight:600;color:var(--text-sub);cursor:pointer;text-decoration:none;transition:all .15s;padding:0 8px}
.pag-btn:hover{border-color:#334155;color:#334155;text-decoration:none}
.pag-btn.active{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-color:transparent;color:#fff}
.pag-btn.disabled{opacity:.4;cursor:default;pointer-events:none}
.pag-dots{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;color:var(--text-sub);font-size:12px}

/* Modal */
.modal-content{border:none;border-radius:16px;font-family:inherit;box-shadow:0 20px 60px rgba(0,0,0,.18);overflow:hidden}
.modal-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border:none;padding:18px 24px}
.modal-header .modal-title{font-weight:700;font-size:15px}
.modal-header .close{color:#fff;opacity:.8;font-size:22px}
.modal-header .close:hover{opacity:1}

/* Modal summary strip */
.modal-summary-strip{background:var(--surface);padding:18px 24px;border-bottom:1px solid var(--border);display:grid;grid-template-columns:1fr 1fr;gap:16px}
.mss-item-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:4px}
.mss-item-val{font-size:14px;font-weight:700;color:var(--text-main)}
.mss-item-sub{font-size:11px;color:var(--text-sub);margin-top:2px}

/* Modal tabs */
.modal-tab-bar{display:flex;gap:4px;padding:14px 20px 0;border-bottom:1px solid var(--border)}
.modal-tab{background:none;border:none;border-bottom:3px solid transparent;padding:8px 16px 12px;font-family:inherit;font-size:13px;font-weight:700;color:var(--text-sub);cursor:pointer;display:flex;align-items:center;gap:6px;margin-bottom:-1px;transition:all .2s}
.modal-tab.active{color:#4f46e5;border-bottom-color:#4f46e5}
.modal-tab:hover{color:#4f46e5}
.modal-pane{display:none;padding:20px}
.modal-pane.active{display:block}

/* Modal detail rows */
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.detail-card{background:var(--surface);border-radius:12px;padding:16px}
.detail-card-title{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:12px}
.detail-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.detail-row:last-child{margin-bottom:0}
.detail-label{font-size:11px;color:var(--text-sub)}
.detail-val{font-size:12px;font-weight:700;color:var(--text-main);font-family:'JetBrains Mono',monospace;text-align:right;word-break:break-all;max-width:200px}
.json-pre{background:var(--surface);border-radius:10px;padding:14px;font-family:'JetBrains Mono',monospace;font-size:11px;line-height:1.6;max-height:360px;overflow-y:auto;color:var(--text-main);border:1.5px solid var(--border)}
.ua-code{font-family:'JetBrains Mono',monospace;font-size:10px;background:var(--surface);border-radius:8px;padding:8px 12px;color:var(--text-sub);word-break:break-all;border:1.5px solid var(--border);display:block;margin-top:6px}

.btn-close-modal{display:inline-flex;align-items:center;gap:7px;background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border);border-radius:10px;padding:10px 20px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer}
.btn-close-modal:hover{border-color:var(--text-sub);color:var(--text-main)}
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <!-- Header -->
    <div class="dash-header">
        <div>
            <h4><i class="feather icon-activity" style="margin-right:8px;"></i>Activity Logs</h4>
            <p>Monitor all system activity <?= $user_branch_id ? "—  $current_branch_name" : "—  All Branches" ?></p>
        </div>
        <a href="dashboard.php" class="back-btn"><i class="feather icon-home"></i>Dashboard</a>
    </div>

    <!-- Stat tiles -->
    <div class="stat-row">
        <div class="stat-tile st-logs">
            <div class="stat-icon"><i class="feather icon-list"></i></div>
            <div class="stat-val"><?= number_format($summary['total_logs']??0) ?></div>
            <div class="stat-lbl">Total Logs</div>
        </div>
        <div class="stat-tile st-users">
            <div class="stat-icon"><i class="feather icon-users"></i></div>
            <div class="stat-val"><?= number_format($summary['unique_users']??0) ?></div>
            <div class="stat-lbl">Active Users</div>
        </div>
        <div class="stat-tile st-days">
            <div class="stat-icon"><i class="feather icon-calendar"></i></div>
            <div class="stat-val"><?= number_format($summary['active_days']??0) ?></div>
            <div class="stat-lbl">Active Days</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="dash-card">
        <div class="dash-card-head">
            <span class="ico"><i class="feather icon-filter"></i></span>
            <h6>Filter Logs</h6>
        </div>
        <div class="dash-card-body">
            <form method="GET">
                <div class="filter-grid">
                    <div>
                        <label class="form-label">Search</label>
                        <input type="text" class="form-input" name="search" placeholder="User, action, table..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div>
                        <label class="form-label">User</label>
                        <select class="form-input" name="user_id">
                            <option value="">All Users</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $user_filter==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Action</label>
                        <select class="form-input" name="action">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $a): ?>
                            <option value="<?= htmlspecialchars($a['action']) ?>" <?= $action_filter==$a['action']?'selected':'' ?>><?= htmlspecialchars($a['action']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Table</label>
                        <select class="form-input" name="table_name">
                            <option value="">All Tables</option>
                            <?php foreach ($tables as $t): ?>
                            <option value="<?= htmlspecialchars($t['table_name']) ?>" <?= $table_filter==$t['table_name']?'selected':'' ?>><?= htmlspecialchars($t['table_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="filter-btn"><i class="feather icon-filter"></i>Filter</button>
                    </div>
                    <div>
                        <label class="form-label">&nbsp;</label>
                        <a href="?" class="clear-btn"><i class="feather icon-refresh-ccw"></i>Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs table -->
    <div class="dash-card">
        <div class="dash-card-head">
            <span class="ico"><i class="feather icon-clock"></i></span>
            <h6>Activity Logs</h6>
            <span style="margin-left:auto;font-size:12px;color:var(--text-sub);">
                <?= number_format($total_logs) ?> records &middot; page <?= $page ?> of <?= max(1,$total_pages) ?>
            </span>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:38px;">#</th>
                        <th>Action / Table</th>
                        <th>User</th>
                        <th>Record</th>
                        <th>IP Address</th>
                        <th>Timestamp</th>
                        <th style="width:40px;"></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($activity_logs)): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="feather icon-activity"></i><p>No activity logs found matching your filters.</p></div></td></tr>
                <?php else: ?>
                <?php $counter = $offset+1; foreach ($activity_logs as $log):
                    $style = getActionStyle($log['action']);
                    $hasOld = !empty($log['old_values']);
                    $hasNew = !empty($log['new_values']);
                    $chgLabel = ($hasOld && $hasNew) ? 'modified' : ($hasNew ? 'created' : ($hasOld ? 'deleted' : ''));
                ?>
                <tr onclick="showDetails(<?= htmlspecialchars(json_encode($log), ENT_QUOTES) ?>)">
                    <td style="color:var(--text-sub);font-size:11px;"><?= $counter++ ?></td>
                    <td>
                        <span class="act-badge <?= $style['cls'] ?>"><i class="feather icon-<?= $style['icon'] ?>"></i><?= htmlspecialchars($log['action']) ?></span>
                        <div class="table-tag"><?= htmlspecialchars($log['table_name']) ?></div>
                    </td>
                    <td>
                        <div class="user-name"><?= htmlspecialchars($log['user_name']??'System') ?></div>
                        <?php if (!empty($log['branch_name'])): ?>
                        <div class="branch-tag"><i class="feather icon-map-pin" style="font-size:9px;"></i><?= htmlspecialchars($log['branch_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="record-id">#<?= htmlspecialchars($log['record_id']??'— ') ?></span>
                        <?php if ($chgLabel): ?><div class="change-tag <?= $chgLabel ?>"><?= ucfirst($chgLabel) ?></div><?php endif; ?>
                    </td>
                    <td><span class="ip-code"><?= htmlspecialchars($log['ip_address']??'— ') ?></span></td>
                    <td>
                        <div class="ts-date"><?= date('d M Y', strtotime($log['created_at'])) ?></div>
                        <div class="ts-time"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                    </td>
                    <td><button class="view-btn" title="View details"><i class="feather icon-eye"></i></button></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pag-row">
            <div class="pag-info">Showing <?= min(($page-1)*$results_per_page+1,$total_logs) ?>&ndash;<?= min($page*$results_per_page,$total_logs) ?> of <?= number_format($total_logs) ?></div>
            <div class="pag-btns">
                <?php if ($page>1): ?>
                <a class="pag-btn" href="<?= buildPaginationUrl(1,$search,$user_filter,$action_filter,$table_filter) ?>"><i class="feather icon-chevrons-left"></i></a>
                <a class="pag-btn" href="<?= buildPaginationUrl($page-1,$search,$user_filter,$action_filter,$table_filter) ?>"><i class="feather icon-chevron-left"></i></a>
                <?php else: ?>
                <span class="pag-btn disabled"><i class="feather icon-chevrons-left"></i></span>
                <span class="pag-btn disabled"><i class="feather icon-chevron-left"></i></span>
                <?php endif; ?>

                <?php
                $sp = max(1,$page-2); $ep = min($total_pages,$page+2);
                if ($sp>1){ echo '<a class="pag-btn" href="'.buildPaginationUrl(1,$search,$user_filter,$action_filter,$table_filter).'">1</a>'; if ($sp>2) echo '<span class="pag-dots">...</span>'; }
                for($i=$sp;$i<=$ep;$i++) echo '<a class="pag-btn'.($i==$page?' active':'').'" href="'.buildPaginationUrl($i,$search,$user_filter,$action_filter,$table_filter).'">'.$i.'</a>';
                if ($ep<$total_pages){ if($ep<$total_pages-1) echo '<span class="pag-dots">...</span>'; echo '<a class="pag-btn" href="'.buildPaginationUrl($total_pages,$search,$user_filter,$action_filter,$table_filter).'">'.$total_pages.'</a>'; }
                ?>

                <?php if ($page<$total_pages): ?>
                <a class="pag-btn" href="<?= buildPaginationUrl($page+1,$search,$user_filter,$action_filter,$table_filter) ?>"><i class="feather icon-chevron-right"></i></a>
                <a class="pag-btn" href="<?= buildPaginationUrl($total_pages,$search,$user_filter,$action_filter,$table_filter) ?>"><i class="feather icon-chevrons-right"></i></a>
                <?php else: ?>
                <span class="pag-btn disabled"><i class="feather icon-chevron-right"></i></span>
                <span class="pag-btn disabled"><i class="feather icon-chevrons-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-activity" style="margin-right:8px;"></i>Activity Log Details</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <!-- Summary strip -->
            <div class="modal-summary-strip">
                <div>
                    <div class="mss-item-label">Action</div>
                    <div class="mss-item-val" id="ms-action">— </div>
                    <div class="mss-item-sub" id="ms-table">— </div>
                </div>
                <div>
                    <div class="mss-item-label">Performed By</div>
                    <div class="mss-item-val" id="ms-user">— </div>
                    <div class="mss-item-sub" id="ms-ts">— </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="modal-tab-bar">
                <button class="modal-tab active" onclick="switchTab('summary',this)"><i class="feather icon-info"></i>Summary</button>
                <button class="modal-tab" id="tabOld" onclick="switchTab('old',this)" style="display:none;"><i class="feather icon-minus-circle"></i>Old Values</button>
                <button class="modal-tab" id="tabNew" onclick="switchTab('new',this)" style="display:none;"><i class="feather icon-plus-circle"></i>New Values</button>
                <button class="modal-tab" onclick="switchTab('tech',this)"><i class="feather icon-cpu"></i>Technical</button>
            </div>

            <!-- Panes -->
            <div class="modal-pane active" id="pane-summary">
                <div class="detail-grid">
                    <div class="detail-card">
                        <div class="detail-card-title">Activity Information</div>
                        <div class="detail-row"><span class="detail-label">Action</span><span class="detail-val" id="d-action">— </span></div>
                        <div class="detail-row"><span class="detail-label">Table</span><span class="detail-val" id="d-table">— </span></div>
                        <div class="detail-row"><span class="detail-label">Record ID</span><span class="detail-val" id="d-record">— </span></div>
                        <div class="detail-row"><span class="detail-label">Branch</span><span class="detail-val" id="d-branch">— </span></div>
                    </div>
                    <div class="detail-card">
                        <div class="detail-card-title">User &amp; System</div>
                        <div class="detail-row"><span class="detail-label">User</span><span class="detail-val" id="d-user">— </span></div>
                        <div class="detail-row"><span class="detail-label">IP Address</span><span class="detail-val" id="d-ip">— </span></div>
                        <div class="detail-row"><span class="detail-label">Timestamp</span><span class="detail-val" id="d-ts">— </span></div>
                        <div class="detail-row"><span class="detail-label">Log ID</span><span class="detail-val" id="d-logid">— </span></div>
                    </div>
                </div>
            </div>

            <div class="modal-pane" id="pane-old">
                <div class="detail-card" style="border-radius:10px;">
                    <div class="detail-card-title">Previous Values (Before Change)</div>
                    <pre class="json-pre" id="d-old">No old values available</pre>
                </div>
            </div>

            <div class="modal-pane" id="pane-new">
                <div class="detail-card" style="border-radius:10px;">
                    <div class="detail-card-title">New Values (After Change)</div>
                    <pre class="json-pre" id="d-new">No new values available</pre>
                </div>
            </div>

            <div class="modal-pane" id="pane-tech">
                <div class="detail-card" style="border-radius:10px;">
                    <div class="detail-card-title">Technical Information</div>
                    <div class="detail-row"><span class="detail-label">Log ID</span><span class="detail-val" id="t-logid">— </span></div>
                    <div class="detail-row"><span class="detail-label">Tenant ID</span><span class="detail-val" id="t-tenantid">— </span></div>
                    <div class="detail-row"><span class="detail-label">User ID</span><span class="detail-val" id="t-userid">— </span></div>
                    <div class="detail-row"><span class="detail-label">Created At</span><span class="detail-val" id="t-createdat">— </span></div>
                    <div style="margin-top:10px;"><span class="detail-label">User Agent</span><code class="ua-code" id="t-useragent">— </code></div>
                </div>
            </div>

            <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;">
                <button type="button" class="btn-close-modal" data-dismiss="modal"><i class="feather icon-x"></i>Close</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.modal-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.modal-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('pane-'+name).classList.add('active');
}

function showDetails(log) {
    // Summary strip
    document.getElementById('ms-action').textContent = log.action || '— ';
    document.getElementById('ms-table').textContent  = log.table_name || '— ';
    document.getElementById('ms-user').textContent   = log.user_name || 'System';
    document.getElementById('ms-ts').textContent     = log.created_at ? new Date(log.created_at).toLocaleString() : '— ';

    // Summary tab
    document.getElementById('d-action').textContent = log.action || '— ';
    document.getElementById('d-table').textContent  = log.table_name || '— ';
    document.getElementById('d-record').textContent = log.record_id || '— ';
    document.getElementById('d-branch').textContent = log.branch_name || '— ';
    document.getElementById('d-user').textContent   = log.user_name || 'System';
    document.getElementById('d-ip').textContent     = log.ip_address || '— ';
    document.getElementById('d-ts').textContent     = log.created_at ? new Date(log.created_at).toLocaleString() : '— ';
    document.getElementById('d-logid').textContent  = log.id;

    // Old values tab
    const tabOld = document.getElementById('tabOld');
    if (log.old_values) {
        try { document.getElementById('d-old').textContent = JSON.stringify(JSON.parse(log.old_values), null, 2); }
        catch(e) { document.getElementById('d-old').textContent = log.old_values; }
        tabOld.style.display = 'flex';
    } else {
        document.getElementById('d-old').textContent = 'No old values available';
        tabOld.style.display = 'none';
    }

    // New values tab
    const tabNew = document.getElementById('tabNew');
    if (log.new_values) {
        try { document.getElementById('d-new').textContent = JSON.stringify(JSON.parse(log.new_values), null, 2); }
        catch(e) { document.getElementById('d-new').textContent = log.new_values; }
        tabNew.style.display = 'flex';
    } else {
        document.getElementById('d-new').textContent = 'No new values available';
        tabNew.style.display = 'none';
    }

    // Technical tab
    document.getElementById('t-logid').textContent     = log.id;
    document.getElementById('t-tenantid').textContent  = log.tenant_id;
    document.getElementById('t-userid').textContent    = log.user_id || '— ';
    document.getElementById('t-createdat').textContent = log.created_at ? new Date(log.created_at).toLocaleString() : '— ';
    document.getElementById('t-useragent').textContent = log.user_agent || '— ';

    // Reset to summary tab
    document.querySelectorAll('.modal-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.modal-pane').forEach(p => p.classList.remove('active'));
    document.querySelector('.modal-tab').classList.add('active');
    document.getElementById('pane-summary').classList.add('active');

    $('#detailsModal').modal('show');
}
</script>
