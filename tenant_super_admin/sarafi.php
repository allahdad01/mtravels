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

$query = "SELECT st.*, c.name as customer_name, c.phone as customer_phone, b.name as branch_name
          FROM sarafi_transactions st
          LEFT JOIN customers c ON st.customer_id = c.id
          LEFT JOIN branches b ON st.branch_id = b.id
          WHERE st.tenant_id = ?";

$params = [$tenant_id];
if ($selected_branch !== 'all') { $query .= " AND st.branch_id = ?"; $params[] = $selected_branch; }
if (!empty($search)) {
    $query .= " AND (c.name LIKE ? OR st.reference_number LIKE ? OR st.notes LIKE ?)";
    $sp = "%$search%"; $params = array_merge($params, [$sp,$sp,$sp]);
}
$query .= " ORDER BY st.created_at DESC LIMIT ? OFFSET ?";
$params[] = $results_per_page; $params[] = $offset;

$stmt = $pdo->prepare($query); $stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cq = "SELECT COUNT(*) as total FROM sarafi_transactions st LEFT JOIN customers c ON st.customer_id = c.id WHERE st.tenant_id = ?";
$cp = [$tenant_id];
if ($selected_branch !== 'all') { $cq .= " AND st.branch_id = ?"; $cp[] = $selected_branch; }
if (!empty($search)) {
    $cq .= " AND (c.name LIKE ? OR st.reference_number LIKE ? OR st.notes LIKE ?)";
    $sp = "%$search%"; $cp = array_merge($cp, [$sp,$sp,$sp]);
}
$cs = $pdo->prepare($cq); $cs->execute($cp);
$total_transactions = $cs->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages        = max(1, ceil($total_transactions / $results_per_page));

$sq = "SELECT COUNT(*) as total_transactions,
    COUNT(CASE WHEN type='deposit' THEN 1 END) as deposit_count,
    COUNT(CASE WHEN type='withdrawal' THEN 1 END) as withdrawal_count,
    COUNT(CASE WHEN type='exchange' THEN 1 END) as exchange_count,
    SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END) as total_deposits,
    SUM(CASE WHEN type='withdrawal' THEN amount ELSE 0 END) as total_withdrawals
FROM sarafi_transactions WHERE tenant_id = ?";
$sp2 = [$tenant_id];
if ($selected_branch !== 'all') { $sq .= " AND branch_id = ?"; $sp2[] = $selected_branch; }
$ss = $pdo->prepare($sq); $ss->execute($sp2);
$summary = $ss->fetch(PDO::FETCH_ASSOC);

$bs = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND status='active' ORDER BY name");
$bs->execute([$tenant_id]);
$branches = $bs->fetchAll(PDO::FETCH_ASSOC);

$from = min(($page - 1) * $results_per_page + 1, $total_transactions);
$to   = min($page * $results_per_page, $total_transactions);

function typeBadgeClass($type) {
    return match(strtolower($type)) {
        'deposit'                      => 'dep',
        'withdrawal'                   => 'wit',
        'exchange'                     => 'exc',
        'hawala_send','hawala_receive' => 'haw',
        'adjustment'                   => 'adj',
        default                        => 'def',
    };
}
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root {
    --surface:#f4f7fe; --card-bg:#ffffff; --border:#e8edf5;
    --text-main:#1a2340; --text-sub:#6b7a99;
    --green:#22c55e; --red:#ef4444;
    /* Sarafi identity: teal â†’ emerald (money exchange) */
    --c1:#0f766e; --c2:#059669;
    --radius:14px; --shadow:0 2px 12px rgba(15,118,110,0.08);
}
*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}

.dash-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 32px rgba(15,118,110,0.28);position:relative;overflow:hidden}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.06'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;letter-spacing:-0.4px;position:relative}
.dash-header p{color:rgba(255,255,255,0.8);margin:0;font-size:13px;position:relative}

/* 6-col stat grid */
.stat-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:24px}
@media(max-width:1100px){.stat-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:700px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:440px){.stat-grid{grid-template-columns:1fr}}
.stat-card{border-radius:var(--radius);padding:18px 20px;color:#fff;position:relative;overflow:hidden}
.stat-card::after{content:'';position:absolute;right:-8px;bottom:-8px;width:60px;height:60px;border-radius:50%;background:rgba(255,255,255,0.1)}
.stat-card.total {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 5px 18px rgba(15,118,110,0.3)}
.stat-card.dep   {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 5px 18px rgba(5,150,105,0.3)}
.stat-card.wit   {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 5px 18px rgba(220,38,38,0.3)}
.stat-card.exc   {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 5px 18px rgba(180,83,9,0.3)}
.stat-card.tdep  {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 5px 18px rgba(29,78,216,0.3)}
.stat-card.twit  {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 5px 18px rgba(124,58,237,0.3)}
.stat-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;opacity:.8;margin-bottom:6px}
.stat-value{font-family:'JetBrains Mono',monospace;font-size:17px;font-weight:800;line-height:1}
.stat-icon{position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:26px;opacity:.2}

/* Cards */
.dash-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.dash-card:last-child{margin-bottom:0}
.dash-card-head{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.dash-card-head h6{font-size:14px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}
.dash-card-head h6 .ico{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0}
.dash-card-body{padding:20px}
.count-badge{background:rgba(15,118,110,.1);color:#0f766e;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;margin-left:auto}

/* Search */
.search-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:end}
@media(max-width:700px){.search-row{grid-template-columns:1fr}}
.form-label-custom{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:block;margin-bottom:6px}
.search-group{display:flex;gap:8px}
.form-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:9px 13px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s}
.form-input:focus{border-color:#0f766e;background:#fff;box-shadow:0 0 0 3px rgba(15,118,110,.1)}
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

/* Customer cell */
.cust-name{font-weight:700;color:var(--text-main);margin-bottom:3px}
.cust-phone{display:flex;align-items:center;gap:5px;font-size:12px;color:var(--text-sub)}
.cust-phone i{font-size:11px}

/* Transaction type badge */
.type-badge{display:inline-flex;align-items:center;gap:4px;border-radius:20px;padding:4px 10px;font-size:11px;font-weight:700;margin-bottom:5px}
.tb-dep{background:rgba(5,150,105,.12);color:#059669}
.tb-wit{background:rgba(239,68,68,.1);color:#dc2626}
.tb-exc{background:rgba(180,83,9,.1);color:#b45309}
.tb-haw{background:rgba(8,145,178,.1);color:#0891b2}
.tb-adj{background:rgba(180,83,9,.1);color:#b45309}
.tb-def{background:rgba(107,122,153,.1);color:var(--text-sub)}
.ref-num{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text-sub);display:flex;align-items:center;gap:4px}

/* Amount cell */
.amt-val{font-family:'JetBrains Mono',monospace;font-size:14px;font-weight:800}
.av-usd{color:#059669} .av-other{color:#0891b2}

/* Status + date cell */
.status-pill{display:inline-flex;align-items:center;gap:4px;border-radius:20px;padding:4px 11px;font-size:11px;font-weight:700;margin-bottom:4px}
.sp-completed{background:rgba(34,197,94,.12);color:#166534}
.sp-pending  {background:rgba(245,158,11,.12);color:#92400e}
.sp-failed   {background:rgba(239,68,68,.1);color:#991b1b}
.sp-default  {background:rgba(107,122,153,.1);color:var(--text-sub)}
.txn-date{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text-sub)}

/* Branch */
.branch-pill{display:inline-flex;align-items:center;gap:5px;background:rgba(15,118,110,.08);color:#0f766e;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700}

/* Action */
.act-btn{width:30px;height:30px;border-radius:8px;border:1.5px solid var(--border);background:var(--card-bg);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;color:var(--text-sub);transition:all .15s}
.act-btn:hover{background:rgba(15,118,110,.08);border-color:#0f766e;color:#0f766e}
.dropdown-menu{border-radius:12px;border:1px solid var(--border);box-shadow:0 8px 24px rgba(0,0,0,.1);padding:6px;min-width:170px}
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
.pag-btn:hover{border-color:#0f766e;color:#0f766e;text-decoration:none}
.pag-btn.active{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-color:transparent;color:#fff}
.pag-btn.disabled{opacity:.4;pointer-events:none}
.pag-dots{display:flex;align-items:center;padding:0 4px;color:var(--text-sub);font-size:13px}

/* Modal */
.modal-content{border:none;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.18);font-family:inherit}
.modal-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border-radius:16px 16px 0 0;border:none;padding:18px 24px}
.modal-header .modal-title{font-weight:700;font-size:15px}
.modal-header .close{color:#fff;opacity:.8;font-size:22px}
.modal-header .close:hover{opacity:1}

/* 2-col strip */
.modal-summary{display:grid;grid-template-columns:1fr 1fr;background:var(--surface);border-bottom:1px solid var(--border)}
.ms-cell{padding:20px;text-align:center;border-right:1px solid var(--border)}
.ms-cell:last-child{border-right:none}
.ms-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:6px}
.ms-val{font-size:22px;font-weight:800;font-family:'JetBrains Mono',monospace;line-height:1;margin-bottom:4px}
.ms-val.teal {color:#0f766e}
.ms-val.green{color:#059669}
.ms-sub{font-size:11px;font-weight:600;color:var(--text-sub)}

.modal-body{padding:0}
.modal-tabs{display:flex;gap:6px;padding:16px 24px 0;border-bottom:1px solid var(--border)}
.modal-tab{background:none;border:none;border-bottom:3px solid transparent;padding:8px 16px 12px;font-family:inherit;font-size:13px;font-weight:700;color:var(--text-sub);cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px;margin-bottom:-1px}
.modal-tab.active{color:#0f766e;border-bottom-color:#0f766e}
.modal-tab:hover{color:#0f766e}
.modal-pane{display:none;padding:24px}
.modal-pane.active{display:block}
.detail-section{background:var(--surface);border-radius:12px;padding:18px;margin-bottom:14px}
.detail-section:last-child{margin-bottom:0}
.ds-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:14px}
.ds-row{display:flex;justify-content:space-between;align-items:flex-start;padding:7px 0;border-bottom:1px solid var(--border)}
.ds-row:last-child{border-bottom:none}
.ds-key{font-size:13px;color:var(--text-sub);flex-shrink:0;margin-right:12px}
.ds-val{font-size:13px;font-weight:700;color:var(--text-main);text-align:right}
.ds-val.teal {color:#0f766e}
.ds-val.green{color:#059669}
.notes-box{background:var(--card-bg);border:1px solid var(--border);border-radius:10px;padding:14px;font-size:13px;color:var(--text-main);line-height:1.6;margin-top:8px}

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
            <h4><i class="feather icon-refresh-cw" style="margin-right:8px;"></i>Sarafi —  Money Exchange</h4>
            <p>View and manage all money exchange transactions</p>
        </div>
    </div>

    <!-- 6-col summary -->
    <div class="stat-grid">
        <div class="stat-card total">
            <div class="stat-label">Total Txns</div>
            <div class="stat-value"><?= number_format($summary['total_transactions'] ?? 0) ?></div>
            <i class="feather icon-refresh-cw stat-icon"></i>
        </div>
        <div class="stat-card dep">
            <div class="stat-label">Deposits</div>
            <div class="stat-value"><?= number_format($summary['deposit_count'] ?? 0) ?></div>
            <i class="feather icon-arrow-down-circle stat-icon"></i>
        </div>
        <div class="stat-card wit">
            <div class="stat-label">Withdrawals</div>
            <div class="stat-value"><?= number_format($summary['withdrawal_count'] ?? 0) ?></div>
            <i class="feather icon-arrow-up-circle stat-icon"></i>
        </div>
        <div class="stat-card exc">
            <div class="stat-label">Exchanges</div>
            <div class="stat-value"><?= number_format($summary['exchange_count'] ?? 0) ?></div>
            <i class="feather icon-repeat stat-icon"></i>
        </div>
        <div class="stat-card tdep">
            <div class="stat-label">Total Deposits</div>
            <div class="stat-value">$<?= number_format($summary['total_deposits'] ?? 0, 0) ?></div>
            <i class="feather icon-dollar-sign stat-icon"></i>
        </div>
        <div class="stat-card twit">
            <div class="stat-label">Total Withdrawals</div>
            <div class="stat-value">$<?= number_format($summary['total_withdrawals'] ?? 0, 0) ?></div>
            <i class="feather icon-trending-down stat-icon"></i>
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
                    <label class="form-label-custom">Search</label>
                    <div class="search-group">
                        <input type="text" id="searchInput" class="form-input" placeholder="Customer, reference number, or notes..." value="<?= htmlspecialchars($search) ?>">
                        <button class="search-btn" id="searchBtn"><i class="feather icon-search"></i>Search</button>
                        <?php if (!empty($search)): ?>
                        <a href="?branch=<?= urlencode($selected_branch) ?>" class="clear-btn"><i class="feather icon-x"></i>Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="dash-card">
        <div class="dash-card-head">
            <h6><span class="ico"><i class="feather icon-list"></i></span>Sarafi Transactions</h6>
            <span class="count-badge"><?= number_format($total_transactions) ?> total</span>
        </div>

        <?php if (!empty($transactions)): ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:44px;">#</th>
                        <th style="width:60px;"></th>
                        <th>Customer</th>
                        <th>Transaction Info</th>
                        <th>Amount</th>
                        <th>Branch</th>
                        <th>Status &amp; Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php $counter = $offset + 1; foreach ($transactions as $txn):
                    $tc  = typeBadgeClass($txn['type']);
                    $isUSD = strtoupper($txn['currency']) === 'USD';
                    $curr  = $isUSD ? '$' : htmlspecialchars($txn['currency']).' ';
                    $amtCls= $isUSD ? 'av-usd' : 'av-other';
                    $stCls = match($txn['status'] ?? '') {
                        'completed' => 'sp-completed',
                        'pending'   => 'sp-pending',
                        'failed'    => 'sp-failed',
                        default     => 'sp-default',
                    };
                ?>
                <tr>
                    <td class="td-ctr"><?= $counter++ ?></td>
                    <td>
                        <div class="dropdown">
                            <button class="act-btn dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="feather icon-more-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <button class="dropdown-item view-details" data-transaction='<?= htmlspecialchars(json_encode($txn)) ?>'>
                                    <i class="feather icon-eye" style="color:#0f766e"></i>View Details
                                </button>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="cust-name"><?= htmlspecialchars($txn['customer_name'] ?? 'Unknown') ?></div>
                        <?php if (!empty($txn['customer_phone'])): ?>
                        <div class="cust-phone"><i class="feather icon-phone"></i><?= htmlspecialchars($txn['customer_phone']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="type-badge tb-<?= $tc ?>"><?= ucfirst(htmlspecialchars($txn['type'])) ?></span>
                        <?php if (!empty($txn['reference_number'])): ?>
                        <div class="ref-num"><i class="feather icon-hash"></i><?= htmlspecialchars($txn['reference_number']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="amt-val <?= $amtCls ?>"><?= $curr ?><?= number_format($txn['amount'], 2) ?></div>
                    </td>
                    <td>
                        <span class="branch-pill"><i class="feather icon-git-branch"></i><?= htmlspecialchars($txn['branch_name'] ?? 'N/A') ?></span>
                    </td>
                    <td>
                        <span class="status-pill <?= $stCls ?>"><?= ucfirst(htmlspecialchars($txn['status'] ?? '— ')) ?></span>
                        <div class="txn-date"><?= date('d/m/Y', strtotime($txn['created_at'])) ?> <?= date('H:i', strtotime($txn['created_at'])) ?></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pag-wrap">
            <div class="pag-info">Showing <?= $from ?>â€“<?= $to ?> of <?= number_format($total_transactions) ?> transactions</div>
            <div class="pag-links">
                <?php $base = '?branch='.urlencode($selected_branch).'&search='.urlencode($search); ?>
                <a href="<?= $base ?>&page=1" class="pag-btn <?= $page<=1?'disabled':'' ?>"><i class="feather icon-chevrons-left"></i></a>
                <a href="<?= $base ?>&page=<?= $page-1 ?>" class="pag-btn <?= $page<=1?'disabled':'' ?>"><i class="feather icon-chevron-left"></i></a>
                <?php
                $sp2=max(1,$page-2); $ep=min($total_pages,$page+2);
                if($sp2>1){echo '<a href="'.$base.'&page=1" class="pag-btn">1</a>';if($sp2>2)echo '<span class="pag-dots">...</span>';}
                for($i=$sp2;$i<=$ep;$i++) echo '<a href="'.$base.'&page='.$i.'" class="pag-btn '.($i==$page?'active':'').'">'.$i.'</a>';
                if($ep<$total_pages){if($ep<$total_pages-1)echo '<span class="pag-dots">...</span>';echo '<a href="'.$base.'&page='.$total_pages.'" class="pag-btn">'.$total_pages.'</a>';}
                ?>
                <a href="<?= $base ?>&page=<?= $page+1 ?>" class="pag-btn <?= $page>=$total_pages?'disabled':'' ?>"><i class="feather icon-chevron-right"></i></a>
                <a href="<?= $base ?>&page=<?= $total_pages ?>" class="pag-btn <?= $page>=$total_pages?'disabled':'' ?>"><i class="feather icon-chevrons-right"></i></a>
            </div>
        </div>

        <?php else: ?>
        <div class="empty-state">
            <i class="feather icon-refresh-cw"></i>
            <p>No transactions found<?= !empty($search) ? ' for "'.$search.'"' : '' ?>.</p>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- Transaction Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-refresh-cw" style="margin-right:8px;"></i>Transaction Details</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <!-- 2-col strip: amount / status -->
            <div class="modal-summary">
                <div class="ms-cell">
                    <div class="ms-label">Transaction Amount</div>
                    <div class="ms-val teal" id="modal-amount">— </div>
                    <div class="ms-sub" id="modal-type">— </div>
                </div>
                <div class="ms-cell">
                    <div class="ms-label">Status</div>
                    <div class="ms-val green" id="modal-status">— </div>
                    <div class="ms-sub" id="modal-date">— </div>
                </div>
            </div>

            <div class="modal-body">
                <div class="modal-tabs">
                    <button class="modal-tab active" onclick="switchTab('summary',this)"><i class="feather icon-info"></i>Summary</button>
                    <button class="modal-tab" onclick="switchTab('notes',this)"><i class="feather icon-file-text"></i>Notes &amp; Details</button>
                </div>

                <div class="modal-pane active" id="pane-summary">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div class="detail-section">
                            <div class="ds-title">Customer Information</div>
                            <div class="ds-row"><span class="ds-key">Customer</span><span class="ds-val" id="cust-name">— </span></div>
                            <div class="ds-row"><span class="ds-key">Phone</span><span class="ds-val" id="cust-phone" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                            <div class="ds-row"><span class="ds-key">Branch</span><span class="ds-val" id="txn-branch">— </span></div>
                        </div>
                        <div class="detail-section">
                            <div class="ds-title">Transaction Details</div>
                            <div class="ds-row"><span class="ds-key">Type</span><span class="ds-val teal" id="txn-type">— </span></div>
                            <div class="ds-row"><span class="ds-key">Amount</span><span class="ds-val green" id="txn-amount" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                            <div class="ds-row"><span class="ds-key">Currency</span><span class="ds-val" id="txn-currency" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                            <div class="ds-row"><span class="ds-key">Reference</span><span class="ds-val" id="txn-ref" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                        </div>
                    </div>
                </div>

                <div class="modal-pane" id="pane-notes">
                    <div class="detail-section">
                        <div class="ds-title">Additional Information</div>
                        <div class="ds-row"><span class="ds-key">Transaction ID</span><span class="ds-val" id="txn-id" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                        <div class="ds-row"><span class="ds-key">Status</span><span class="ds-val" id="txn-status-detail">— </span></div>
                        <div class="ds-row"><span class="ds-key">Created At</span><span class="ds-val" id="txn-created" style="font-family:'JetBrains Mono',monospace;font-size:11px;">— </span></div>
                        <div class="ds-row"><span class="ds-key">Updated At</span><span class="ds-val" id="txn-updated" style="font-family:'JetBrains Mono',monospace;font-size:11px;">— </span></div>
                    </div>
                    <div class="detail-section">
                        <div class="ds-title">Notes</div>
                        <div class="notes-box" id="txn-notes">— </div>
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
        const t    = JSON.parse(this.getAttribute('data-transaction'));
        const curr = t.currency === 'USD' ? '$' : (t.currency + ' ');
        const amt  = parseFloat(t.amount || 0).toFixed(2);
        const type = (t.type||'').charAt(0).toUpperCase() + (t.type||'').slice(1);
        const stat = (t.status||'').charAt(0).toUpperCase() + (t.status||'').slice(1);

        document.getElementById('modal-amount').textContent = curr + amt;
        document.getElementById('modal-type').textContent   = type;
        document.getElementById('modal-status').textContent = stat;
        document.getElementById('modal-date').textContent   = t.created_at ? new Date(t.created_at).toLocaleDateString() : '— ';

        document.getElementById('cust-name').textContent  = t.customer_name  || 'Unknown';
        document.getElementById('cust-phone').textContent = t.customer_phone || 'N/A';
        document.getElementById('txn-branch').textContent = t.branch_name    || 'N/A';

        document.getElementById('txn-type').textContent     = type;
        document.getElementById('txn-amount').textContent   = curr + amt;
        document.getElementById('txn-currency').textContent = t.currency || '— ';
        document.getElementById('txn-ref').textContent      = t.reference_number || 'N/A';

        document.getElementById('txn-id').textContent          = t.id || '— ';
        document.getElementById('txn-status-detail').textContent = stat;
        document.getElementById('txn-created').textContent    = t.created_at ? new Date(t.created_at).toLocaleString() : '— ';
        document.getElementById('txn-updated').textContent    = t.updated_at ? new Date(t.updated_at).toLocaleString() : '— ';
        document.getElementById('txn-notes').textContent      = t.notes || 'No notes available.';

        switchTab('summary', document.querySelector('.modal-tab'));
        $('#detailsModal').modal('show');
    });
});
</script>
