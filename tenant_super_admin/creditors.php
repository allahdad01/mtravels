<?php
include 'header.php';

$tenant_id      = $_SESSION['tenant_id'];
$user_id        = $_SESSION['user_id'];
$user_role      = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? null;

$search          = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_branch = isset($_GET['branch']) ? $_GET['branch'] : ($user_branch_id ?: 'all');
$page            = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 25;
$offset          = ($page - 1) * $results_per_page;

$query = "SELECT c.*,
                 b.name as branch_name,
                 COUNT(ct.id) as transaction_count,
                 COALESCE(SUM(CASE WHEN ct.transaction_type='debit' THEN ct.amount ELSE -ct.amount END), 0) as current_balance
          FROM creditors c
          LEFT JOIN branches b ON c.branch_id = b.id
          LEFT JOIN creditor_transactions ct ON c.id = ct.creditor_id AND ct.tenant_id = c.tenant_id
          WHERE c.tenant_id = ?";

$params = [$tenant_id];
if ($selected_branch !== 'all') { $query .= " AND c.branch_id = ?"; $params[] = $selected_branch; }
if (!empty($search)) {
    $query .= " AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $sp = "%$search%"; $params = array_merge($params, [$sp,$sp,$sp]);
}
$query .= " GROUP BY c.id ORDER BY c.name ASC LIMIT ? OFFSET ?";
$params[] = $results_per_page; $params[] = $offset;

$stmt = $pdo->prepare($query); $stmt->execute($params);
$creditors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cq = "SELECT COUNT(*) as total FROM creditors c WHERE c.tenant_id = ?";
$cp = [$tenant_id];
if ($selected_branch !== 'all') { $cq .= " AND c.branch_id = ?"; $cp[] = $selected_branch; }
if (!empty($search)) {
    $cq .= " AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $sp = "%$search%"; $cp = array_merge($cp, [$sp,$sp,$sp]);
}
$cs = $pdo->prepare($cq); $cs->execute($cp);
$total_creditors = $cs->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages     = max(1, ceil($total_creditors / $results_per_page));

$sq = "SELECT COUNT(*) as total_creditors,
    COUNT(CASE WHEN status='active'   THEN 1 END) as active_creditors,
    COUNT(CASE WHEN status='inactive' THEN 1 END) as inactive_creditors,
    SUM(balance) as total_outstanding,
    AVG(balance) as avg_credit_amount
FROM creditors WHERE tenant_id = ?";
$sp2 = [$tenant_id];
if ($selected_branch !== 'all') { $sq .= " AND branch_id = ?"; $sp2[] = $selected_branch; }
$ss = $pdo->prepare($sq); $ss->execute($sp2);
$summary = $ss->fetch(PDO::FETCH_ASSOC);

$bs = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND status='active' ORDER BY name");
$bs->execute([$tenant_id]);
$branches = $bs->fetchAll(PDO::FETCH_ASSOC);

$from = min(($page - 1) * $results_per_page + 1, $total_creditors);
$to   = min($page * $results_per_page, $total_creditors);
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root {
    --surface:#f4f7fe; --card-bg:#ffffff; --border:#e8edf5;
    --text-main:#1a2340; --text-sub:#6b7a99;
    --green:#22c55e; --red:#ef4444;
    /* Creditors identity: purple â†’ indigo (liabilities) */
    --c1:#7c3aed; --c2:#4f46e5;
    --radius:14px; --shadow:0 2px 12px rgba(124,58,237,0.08);
}
*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}

.dash-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 32px rgba(124,58,237,0.28);position:relative;overflow:hidden}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.06'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;letter-spacing:-0.4px;position:relative}
.dash-header p{color:rgba(255,255,255,0.8);margin:0;font-size:13px;position:relative}

/* Stat grid */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
@media(max-width:900px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:500px){.stat-grid{grid-template-columns:1fr}}
.stat-card{border-radius:var(--radius);padding:20px 22px;color:#fff;position:relative;overflow:hidden}
.stat-card::after{content:'';position:absolute;right:-10px;bottom:-10px;width:70px;height:70px;border-radius:50%;background:rgba(255,255,255,0.1)}
.stat-card.total    {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(124,58,237,0.3)}
.stat-card.active   {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(5,150,105,0.3)}
.stat-card.owed     {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(79,70,229,0.3)}
.stat-card.avg      {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(180,83,9,0.3)}
.stat-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;opacity:.8;margin-bottom:8px}
.stat-value{font-family:'JetBrains Mono',monospace;font-size:20px;font-weight:800;line-height:1}
.stat-icon{position:absolute;right:18px;top:50%;transform:translateY(-50%);font-size:30px;opacity:.22}

/* Cards */
.dash-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.dash-card:last-child{margin-bottom:0}
.dash-card-head{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.dash-card-head h6{font-size:14px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}
.dash-card-head h6 .ico{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0}
.dash-card-body{padding:20px}
.count-badge{background:rgba(124,58,237,.1);color:#7c3aed;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;margin-left:auto}

/* Search */
.search-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:end}
@media(max-width:700px){.search-row{grid-template-columns:1fr}}
.form-label-custom{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:block;margin-bottom:6px}
.search-group{display:flex;gap:8px}
.form-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:9px 13px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s}
.form-input:focus{border-color:#7c3aed;background:#fff;box-shadow:0 0 0 3px rgba(124,58,237,.1)}
.search-btn{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border:none;border-radius:10px;padding:9px 18px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0;transition:opacity .2s}
.search-btn:hover{opacity:.9}
.clear-btn{display:inline-flex;align-items:center;gap:6px;background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border);border-radius:10px;padding:9px 14px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;white-space:nowrap;flex-shrink:0;transition:all .2s}
.clear-btn:hover{border-color:var(--text-sub);color:var(--text-main);text-decoration:none}

/* Table */
.data-table{width:100%;border-collapse:collapse}
.data-table thead th{background:var(--surface);padding:11px 16px;font-size:11px;font-weight:700;color:var(--text-sub);text-transform:uppercase;letter-spacing:.6px;border-bottom:1.5px solid var(--border);white-space:nowrap}
.data-table tbody tr{transition:background .15s}
.data-table tbody tr:hover{background:var(--surface)}
.data-table tbody td{padding:13px 16px;border-bottom:1px solid var(--border);font-size:13px;vertical-align:middle}
.data-table tbody tr:last-child td{border-bottom:none}
.td-ctr{text-align:center;font-size:12px;color:var(--text-sub);font-family:'JetBrains Mono',monospace}

/* Creditor cell */
.cred-name{font-weight:700;color:var(--text-main);margin-bottom:4px}
.cred-contact{display:flex;align-items:center;gap:5px;font-size:12px;color:var(--text-sub);margin-bottom:3px}
.cred-contact:last-child{margin-bottom:0}
.cred-contact i{font-size:11px}

/* Balance cell â€” creditor balance direction is inverse of debtor */
.bal-val{font-family:'JetBrains Mono',monospace;font-size:15px;font-weight:800;margin-bottom:3px}
.bal-val.owe {color:#dc2626}  /* we owe them */
.bal-val.due {color:#059669}  /* owed to us  */
.bal-dir{font-size:11px;font-weight:600;margin-bottom:4px}
.bal-dir.owe{color:#dc2626} .bal-dir.due{color:#059669}
.txn-count{font-size:11px;color:var(--text-sub);display:flex;align-items:center;gap:4px}

/* Status pill */
.status-pill{display:inline-flex;align-items:center;gap:4px;border-radius:20px;padding:4px 11px;font-size:11px;font-weight:700}
.sp-active  {background:rgba(34,197,94,.12);color:#166534}
.sp-inactive{background:rgba(107,122,153,.1);color:var(--text-sub)}

/* Branch */
.branch-pill{display:inline-flex;align-items:center;gap:5px;background:rgba(124,58,237,.08);color:#7c3aed;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700}

/* Date */
.date-val{font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--text-main);font-weight:600}
.time-val{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text-sub)}

/* Action */
.act-btn{width:30px;height:30px;border-radius:8px;border:1.5px solid var(--border);background:var(--card-bg);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;color:var(--text-sub);transition:all .15s}
.act-btn:hover{background:rgba(124,58,237,.08);border-color:#7c3aed;color:#7c3aed}
.dropdown-menu{border-radius:12px;border:1px solid var(--border);box-shadow:0 8px 24px rgba(0,0,0,.1);padding:6px;min-width:180px}
.dropdown-item{border-radius:8px;padding:8px 12px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;transition:background .15s}
.dropdown-item:hover{background:var(--surface)}

.empty-state{text-align:center;padding:60px 20px}
.empty-state i{font-size:44px;opacity:.2;display:block;margin-bottom:14px}
.empty-state p{color:var(--text-sub);font-size:14px;margin:0}

/* Pagination */
.pag-wrap{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;padding:16px 20px;border-top:1px solid var(--border)}
.pag-info{font-size:12px;color:var(--text-sub)}
.pag-links{display:flex;gap:4px}
.pag-btn{min-width:32px;height:32px;border-radius:8px;border:1.5px solid var(--border);background:var(--card-bg);color:var(--text-main);font-size:12px;font-weight:600;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;padding:0 8px;transition:all .15s}
.pag-btn:hover{border-color:#7c3aed;color:#7c3aed;text-decoration:none}
.pag-btn.active{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-color:transparent;color:#fff}
.pag-btn.disabled{opacity:.4;pointer-events:none}
.pag-dots{display:flex;align-items:center;padding:0 4px;color:var(--text-sub);font-size:13px}

/* Modal */
.modal-content{border:none;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.18);font-family:inherit}
.modal-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border-radius:16px 16px 0 0;border:none;padding:18px 24px}
.modal-header.txn-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%)}
.modal-header .modal-title{font-weight:700;font-size:15px}
.modal-header .close{color:#fff;opacity:.8;font-size:22px}
.modal-header .close:hover{opacity:1}

/* 2-col strip */
.modal-summary{display:grid;grid-template-columns:1fr 1fr;background:var(--surface);border-bottom:1px solid var(--border)}
.ms-cell{padding:20px;text-align:center;border-right:1px solid var(--border)}
.ms-cell:last-child{border-right:none}
.ms-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:6px}
.ms-val{font-size:24px;font-weight:800;font-family:'JetBrains Mono',monospace;line-height:1;margin-bottom:4px}
.ms-val.purple{color:#7c3aed} .ms-val.indigo{color:#4f46e5}
.ms-val.owe{color:#dc2626}    .ms-val.due{color:#059669}
.ms-sub{font-size:11px;font-weight:600;color:var(--text-sub)}

.modal-body{padding:0}
.modal-tabs{display:flex;gap:6px;padding:16px 24px 0;border-bottom:1px solid var(--border)}
.modal-tab{background:none;border:none;border-bottom:3px solid transparent;padding:8px 16px 12px;font-family:inherit;font-size:13px;font-weight:700;color:var(--text-sub);cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px;margin-bottom:-1px}
.modal-tab.active{color:#7c3aed;border-bottom-color:#7c3aed}
.modal-tab:hover{color:#7c3aed}
.modal-pane{display:none;padding:24px}
.modal-pane.active{display:block}
.detail-section{background:var(--surface);border-radius:12px;padding:18px;margin-bottom:14px}
.detail-section:last-child{margin-bottom:0}
.ds-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:14px}
.ds-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border)}
.ds-row:last-child{border-bottom:none}
.ds-key{font-size:13px;color:var(--text-sub)}
.ds-val{font-size:13px;font-weight:700;color:var(--text-main);text-align:right}
.ds-val.purple{color:#7c3aed} .ds-val.indigo{color:#4f46e5}
.ds-val.owe{color:#dc2626}    .ds-val.due{color:#059669}

.txn-loading{text-align:center;padding:40px}
.spinner{width:32px;height:32px;border:3px solid var(--border);border-top-color:#7c3aed;border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 12px}
@keyframes spin{to{transform:rotate(360deg)}}

.modal-footer-custom{padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end}
.btn-close-modal{display:inline-flex;align-items:center;gap:7px;background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border);border-radius:10px;padding:10px 20px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
.btn-close-modal:hover{border-color:var(--text-sub);color:var(--text-main)}

.pcoded-content{padding:20px!important}
.page-header{display:none!important}
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <!-- Header -->
    <div class="dash-header">
        <div>
            <h4><i class="feather icon-users" style="margin-right:8px;"></i>Creditors Management</h4>
            <p>Manage creditors and track outstanding liabilities</p>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="stat-grid">
        <div class="stat-card total">
            <div class="stat-label">Total Creditors</div>
            <div class="stat-value"><?= number_format($summary['total_creditors'] ?? 0) ?></div>
            <i class="feather icon-users stat-icon"></i>
        </div>
        <div class="stat-card active">
            <div class="stat-label">Active Creditors</div>
            <div class="stat-value"><?= number_format($summary['active_creditors'] ?? 0) ?></div>
            <i class="feather icon-user-check stat-icon"></i>
        </div>
        <div class="stat-card owed">
            <div class="stat-label">Total Outstanding</div>
            <div class="stat-value">$<?= number_format($summary['total_outstanding'] ?? 0, 0) ?></div>
            <i class="feather icon-credit-card stat-icon"></i>
        </div>
        <div class="stat-card avg">
            <div class="stat-label">Average Credit</div>
            <div class="stat-value">$<?= number_format($summary['avg_credit_amount'] ?? 0, 0) ?></div>
            <i class="feather icon-trending-up stat-icon"></i>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="dash-card">
        <div class="dash-card-head">
            <h6><span class="ico"><i class="feather icon-filter"></i></span>Filter &amp; Search</h6>
        </div>
        <div class="dash-card-body">
            <div class="search-row">
                <div>
                    <label class="form-label-custom">Branch</label>
                    <select class="form-input" id="branchSelect">
                        <option value="all" <?= $selected_branch==='all'?'selected':'' ?>>All Branches</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $selected_branch==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label-custom">Search Creditor</label>
                    <div class="search-group">
                        <input type="text" id="searchInput" class="form-input" placeholder="Name, email, or phoneâ€¦" value="<?= htmlspecialchars($search) ?>">
                        <button class="search-btn" id="searchBtn"><i class="feather icon-search"></i>Search</button>
                        <?php if (!empty($search)): ?>
                        <a href="?branch=<?= urlencode($selected_branch) ?>" class="clear-btn"><i class="feather icon-x"></i>Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="dash-card">
        <div class="dash-card-head">
            <h6><span class="ico"><i class="feather icon-list"></i></span>Creditors List</h6>
            <span class="count-badge"><?= number_format($total_creditors) ?> creditors</span>
        </div>

        <?php if (!empty($creditors)): ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:44px;">#</th>
                        <th style="width:60px;"></th>
                        <th>Creditor Details</th>
                        <th>Balance</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                <?php $counter = $offset + 1; foreach ($creditors as $cred):
                    $bal    = floatval($cred['current_balance']);
                    // For creditors: positive balance = we owe them (bad), negative = owed to us (good)
                    $weOwe  = $bal >= 0;
                    $balCls = $weOwe ? 'owe' : 'due';
                    $balDir = $weOwe ? 'We owe' : 'Owed to us';
                    $stCls  = $cred['status'] === 'active' ? 'sp-active' : 'sp-inactive';
                ?>
                <tr>
                    <td class="td-ctr"><?= $counter++ ?></td>
                    <td>
                        <div class="dropdown">
                            <button class="act-btn dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="feather icon-more-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <button class="dropdown-item view-details" data-creditor='<?= htmlspecialchars(json_encode($cred)) ?>'>
                                    <i class="feather icon-eye" style="color:#7c3aed"></i>View Details
                                </button>
                                <button class="dropdown-item view-transactions" data-creditor-id="<?= $cred['id'] ?>" data-creditor-name="<?= htmlspecialchars($cred['name']) ?>">
                                    <i class="feather icon-activity" style="color:#4f46e5"></i>View Transactions
                                </button>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="cred-name"><?= htmlspecialchars($cred['name']) ?></div>
                        <?php if (!empty($cred['email'])): ?>
                        <div class="cred-contact"><i class="feather icon-mail"></i><?= htmlspecialchars($cred['email']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($cred['phone'])): ?>
                        <div class="cred-contact"><i class="feather icon-phone"></i><?= htmlspecialchars($cred['phone']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="bal-val <?= $balCls ?>">$<?= number_format(abs($bal), 2) ?></div>
                        <div class="bal-dir <?= $balCls ?>"><?= $balDir ?></div>
                        <div class="txn-count"><i class="feather icon-activity"></i><?= number_format($cred['transaction_count']) ?> txns</div>
                    </td>
                    <td>
                        <span class="branch-pill"><i class="feather icon-git-branch"></i><?= htmlspecialchars($cred['branch_name'] ?? 'N/A') ?></span>
                    </td>
                    <td>
                        <span class="status-pill <?= $stCls ?>"><?= ucfirst(htmlspecialchars($cred['status'] ?? 'â€”')) ?></span>
                    </td>
                    <td>
                        <div class="date-val"><?= date('d/m/Y', strtotime($cred['created_at'])) ?></div>
                        <div class="time-val"><?= date('H:i', strtotime($cred['created_at'])) ?></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pag-wrap">
            <div class="pag-info">Showing <?= $from ?>â€“<?= $to ?> of <?= number_format($total_creditors) ?> creditors</div>
            <div class="pag-links">
                <?php $base = '?branch='.urlencode($selected_branch).'&search='.urlencode($search); ?>
                <a href="<?= $base ?>&page=1" class="pag-btn <?= $page<=1?'disabled':'' ?>"><i class="feather icon-chevrons-left"></i></a>
                <a href="<?= $base ?>&page=<?= $page-1 ?>" class="pag-btn <?= $page<=1?'disabled':'' ?>"><i class="feather icon-chevron-left"></i></a>
                <?php
                $sp2=max(1,$page-2); $ep=min($total_pages,$page+2);
                if($sp2>1){echo '<a href="'.$base.'&page=1" class="pag-btn">1</a>';if($sp2>2)echo '<span class="pag-dots">â€¦</span>';}
                for($i=$sp2;$i<=$ep;$i++) echo '<a href="'.$base.'&page='.$i.'" class="pag-btn '.($i==$page?'active':'').'">'.$i.'</a>';
                if($ep<$total_pages){if($ep<$total_pages-1)echo '<span class="pag-dots">â€¦</span>';echo '<a href="'.$base.'&page='.$total_pages.'" class="pag-btn">'.$total_pages.'</a>';}
                ?>
                <a href="<?= $base ?>&page=<?= $page+1 ?>" class="pag-btn <?= $page>=$total_pages?'disabled':'' ?>"><i class="feather icon-chevron-right"></i></a>
                <a href="<?= $base ?>&page=<?= $total_pages ?>" class="pag-btn <?= $page>=$total_pages?'disabled':'' ?>"><i class="feather icon-chevrons-right"></i></a>
            </div>
        </div>

        <?php else: ?>
        <div class="empty-state">
            <i class="feather icon-users"></i>
            <p>No creditors found<?= !empty($search) ? ' for "'.$search.'"' : '' ?>.</p>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- Creditor Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-user" style="margin-right:8px;"></i>Creditor Details</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <!-- 2-col strip -->
            <div class="modal-summary">
                <div class="ms-cell">
                    <div class="ms-label">Current Balance</div>
                    <div class="ms-val" id="modal-balance">â€”</div>
                    <div class="ms-sub" id="modal-balance-dir">â€”</div>
                </div>
                <div class="ms-cell">
                    <div class="ms-label">Transactions</div>
                    <div class="ms-val purple" id="modal-txn-count">â€”</div>
                    <div class="ms-sub" id="modal-created">â€”</div>
                </div>
            </div>

            <div class="modal-body">
                <div class="modal-tabs">
                    <button class="modal-tab active" onclick="switchTab('summary',this)"><i class="feather icon-info"></i>Summary</button>
                    <button class="modal-tab" onclick="switchTab('contact',this)"><i class="feather icon-phone"></i>Contact Info</button>
                </div>

                <div class="modal-pane active" id="pane-summary">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div class="detail-section">
                            <div class="ds-title">Creditor Information</div>
                            <div class="ds-row"><span class="ds-key">Name</span><span class="ds-val" id="cred-name">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Status</span><span class="ds-val" id="cred-status">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Branch</span><span class="ds-val" id="cred-branch">â€”</span></div>
                        </div>
                        <div class="detail-section">
                            <div class="ds-title">Financial Summary</div>
                            <div class="ds-row"><span class="ds-key">Original Balance</span><span class="ds-val purple" id="cred-orig-bal" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Current Balance</span><span class="ds-val" id="cred-cur-bal" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Currency</span><span class="ds-val" id="cred-currency" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Transactions</span><span class="ds-val indigo" id="cred-txn-count" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                        </div>
                    </div>
                </div>

                <div class="modal-pane" id="pane-contact">
                    <div class="detail-section">
                        <div class="ds-title">Contact Information</div>
                        <div class="ds-row"><span class="ds-key">Email</span><span class="ds-val" id="cred-email" style="font-size:12px;">â€”</span></div>
                        <div class="ds-row"><span class="ds-key">Phone</span><span class="ds-val" id="cred-phone" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                        <div class="ds-row"><span class="ds-key">Address</span><span class="ds-val" id="cred-address">â€”</span></div>
                        <div class="ds-row"><span class="ds-key">Creditor ID</span><span class="ds-val" id="cred-id" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                        <div class="ds-row"><span class="ds-key">Created At</span><span class="ds-val" id="cred-created" style="font-family:'JetBrains Mono',monospace;font-size:11px;">â€”</span></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer-custom">
                <button type="button" class="btn-close-modal" data-dismiss="modal"><i class="feather icon-x"></i>Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Transactions Modal -->
<div class="modal fade" id="transactionsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header txn-header">
                <h5 class="modal-title"><i class="feather icon-activity" style="margin-right:8px;"></i>Transaction History â€” <span id="creditor-name-header"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:0;">
                <div id="transactionsContent">
                    <div class="txn-loading">
                        <div class="spinner"></div>
                        <p style="color:var(--text-sub);font-size:13px;margin:0;">Loading transaction historyâ€¦</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-close-modal" data-dismiss="modal"><i class="feather icon-x"></i>Close</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
document.getElementById('branchSelect').addEventListener('change', doSearch);
document.getElementById('searchBtn').addEventListener('click', doSearch);
document.getElementById('searchInput').addEventListener('keypress', e => { if(e.key==='Enter') doSearch(); });

function doSearch() {
    const s = document.getElementById('searchInput').value.trim();
    const b = document.getElementById('branchSelect').value;
    window.location.href = '?branch=' + encodeURIComponent(b) + (s ? '&search=' + encodeURIComponent(s) : '');
}

function switchTab(tab, btn) {
    document.querySelectorAll('.modal-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.modal-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('pane-' + tab).classList.add('active');
    btn.classList.add('active');
}

document.querySelectorAll('.view-details').forEach(btn => {
    btn.addEventListener('click', function() {
        const c   = JSON.parse(this.getAttribute('data-creditor'));
        const bal = parseFloat(c.current_balance || 0);
        const weOwe = bal >= 0;
        const balFmt = '$' + Math.abs(bal).toFixed(2);
        const balDir = weOwe ? 'We owe' : 'Owed to us';

        const mBal = document.getElementById('modal-balance');
        mBal.textContent = balFmt;
        mBal.className   = 'ms-val ' + (weOwe ? 'owe' : 'due');
        document.getElementById('modal-balance-dir').textContent = balDir;
        document.getElementById('modal-txn-count').textContent  = c.transaction_count + ' transactions';
        document.getElementById('modal-created').textContent    = c.created_at ? new Date(c.created_at).toLocaleDateString() : 'â€”';

        document.getElementById('cred-name').textContent   = c.name || 'â€”';
        document.getElementById('cred-status').textContent = (c.status||'').charAt(0).toUpperCase() + (c.status||'').slice(1);
        document.getElementById('cred-branch').textContent = c.branch_name || 'N/A';

        document.getElementById('cred-orig-bal').textContent = '$' + parseFloat(c.balance||0).toFixed(2);
        const curEl = document.getElementById('cred-cur-bal');
        curEl.textContent = balFmt;
        curEl.className   = 'ds-val ' + (weOwe ? 'owe' : 'due');
        document.getElementById('cred-currency').textContent  = c.currency || 'â€”';
        document.getElementById('cred-txn-count').textContent = c.transaction_count;

        document.getElementById('cred-email').textContent   = c.email   || 'N/A';
        document.getElementById('cred-phone').textContent   = c.phone   || 'N/A';
        document.getElementById('cred-address').textContent = c.address || 'N/A';
        document.getElementById('cred-id').textContent      = c.id      || 'â€”';
        document.getElementById('cred-created').textContent = c.created_at ? new Date(c.created_at).toLocaleString() : 'â€”';

        switchTab('summary', document.querySelector('.modal-tab'));
        $('#detailsModal').modal('show');
    });
});

document.querySelectorAll('.view-transactions').forEach(btn => {
    btn.addEventListener('click', function() {
        const id   = this.getAttribute('data-creditor-id');
        const name = this.getAttribute('data-creditor-name');
        document.getElementById('creditor-name-header').textContent = name;
        document.getElementById('transactionsContent').innerHTML =
            '<div class="txn-loading"><div class="spinner"></div><p style="color:var(--text-sub);font-size:13px;margin:0;">Loading transactionsâ€¦</p></div>';
        $('#transactionsModal').modal('show');
        fetch('get_creditor_transactions.php?creditor_id=' + id)
            .then(r => r.text())
            .then(html => { document.getElementById('transactionsContent').innerHTML = html; })
            .catch(err => {
                document.getElementById('transactionsContent').innerHTML =
                    '<div style="padding:20px;color:var(--red);">Error: ' + err.message + '</div>';
            });
    });
});
</script>
