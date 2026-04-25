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

$query = "SELECT s.*, b.name as branch_name,
    COUNT(st.id) as transaction_count,
    COALESCE(SUM(CASE WHEN st.transaction_type='Debit'  THEN st.amount ELSE 0 END),0) as total_debits,
    COALESCE(SUM(CASE WHEN st.transaction_type='Credit' THEN st.amount ELSE 0 END),0) as total_credits
FROM suppliers s
LEFT JOIN branches b ON s.branch_id = b.id
LEFT JOIN supplier_transactions st ON s.id = st.supplier_id
WHERE s.tenant_id = ? AND s.status = 'active'";

$params = [$tenant_id];
if ($selected_branch !== 'all') { $query .= " AND s.branch_id = ?"; $params[] = $selected_branch; }
if (!empty($search)) {
    $query .= " AND (s.name LIKE ? OR s.contact_person LIKE ? OR s.phone LIKE ? OR s.email LIKE ?)";
    $sp = "%$search%"; $params = array_merge($params, [$sp,$sp,$sp,$sp]);
}
$query .= " GROUP BY s.id ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
$params[] = $results_per_page; $params[] = $offset;

$stmt = $pdo->prepare($query); $stmt->execute($params);
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cq = "SELECT COUNT(*) as total FROM suppliers WHERE tenant_id = ? AND status = 'active'";
$cp = [$tenant_id];
if ($selected_branch !== 'all') { $cq .= " AND branch_id = ?"; $cp[] = $selected_branch; }
if (!empty($search)) {
    $cq .= " AND (name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $sp = "%$search%"; $cp = array_merge($cp, [$sp,$sp,$sp,$sp]);
}
$cs = $pdo->prepare($cq); $cs->execute($cp);
$total_suppliers = $cs->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages     = max(1, ceil($total_suppliers / $results_per_page));

$sq = "SELECT COUNT(*) as total_suppliers,
    SUM(CASE WHEN balance > 0 AND currency='USD' THEN balance ELSE 0 END) as usd_owed_by,
    SUM(CASE WHEN balance > 0 AND currency='AFS' THEN balance ELSE 0 END) as afs_owed_by,
    SUM(CASE WHEN balance < 0 AND currency='USD' THEN ABS(balance) ELSE 0 END) as usd_owed_to,
    SUM(CASE WHEN balance < 0 AND currency='AFS' THEN ABS(balance) ELSE 0 END) as afs_owed_to
FROM suppliers WHERE tenant_id = ? AND status = 'active'";
$sp2 = [$tenant_id];
if ($selected_branch !== 'all') { $sq .= " AND branch_id = ?"; $sp2[] = $selected_branch; }
$ss = $pdo->prepare($sq); $ss->execute($sp2);
$summary = $ss->fetch(PDO::FETCH_ASSOC);

$bs = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND status='active' ORDER BY name");
$bs->execute([$tenant_id]);
$branches = $bs->fetchAll(PDO::FETCH_ASSOC);

$from = min(($page - 1) * $results_per_page + 1, $total_suppliers);
$to   = min($page * $results_per_page, $total_suppliers);

function currency_symbol($currency) {
    $symbols = [
        'USD'    => '$',
        'AFS'    => '؋',
        'EUR'    => '€',
        'DARHAM' => 'د.إ',
    ];
    return $symbols[strtoupper($currency ?? '')] ?? '';
}
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root {
    --surface:#f4f7fe; --card-bg:#ffffff; --border:#e8edf5;
    --text-main:#1a2340; --text-sub:#6b7a99;
    --green:#22c55e; --red:#ef4444; --blue:#4099ff;
    --radius:14px; --shadow:0 2px 12px rgba(64,153,255,0.08);
}
*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}

/* Header —  slate → indigo for supplier/vendor management */
.dash-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 32px rgba(51,65,85,0.25);position:relative;overflow:hidden}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;letter-spacing:-0.4px;position:relative}
.dash-header p{color:rgba(255,255,255,0.8);margin:0;font-size:13px;position:relative}

/* Summary stat cards */
.stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px}
@media(max-width:800px){.stat-grid{grid-template-columns:1fr}}
.stat-card{border-radius:var(--radius);padding:20px 22px;color:#fff;position:relative;overflow:hidden}
.stat-card::after{content:'';position:absolute;right:-10px;bottom:-10px;width:70px;height:70px;border-radius:50%;background:rgba(255,255,255,0.1)}
.stat-card.total  {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(51,65,85,0.3)}
.stat-card.owed-by{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(5,150,105,0.3)}
.stat-card.owed-to{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(220,38,38,0.3)}
.stat-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;opacity:.8;margin-bottom:8px}
.stat-value{font-family:'JetBrains Mono',monospace;font-size:22px;font-weight:800;line-height:1}
.stat-icon{position:absolute;right:18px;top:50%;transform:translateY(-50%);font-size:32px;opacity:.25}

/* Cards */
.dash-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.dash-card:last-child{margin-bottom:0}
.dash-card-head{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.dash-card-head h6{font-size:14px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}
.dash-card-head h6 .ico{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0}
.dash-card-body{padding:20px}
.count-badge{background:rgba(79,70,229,.1);color:#4f46e5;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;margin-left:auto}

/* Search */
.search-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:end}
@media(max-width:700px){.search-row{grid-template-columns:1fr}}
.form-label-custom{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:block;margin-bottom:6px}
.search-group{display:flex;gap:8px}
.form-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:9px 13px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s}
.form-input:focus{border-color:#4f46e5;background:#fff;box-shadow:0 0 0 3px rgba(79,70,229,.1)}
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

/* Supplier cell */
.sup-name{font-weight:700;color:var(--text-main);margin-bottom:4px}
.sup-type-pill{display:inline-flex;align-items:center;gap:4px;border-radius:20px;padding:3px 9px;font-size:10px;font-weight:700;margin-bottom:3px}
.stp-external{background:rgba(79,70,229,.1);color:#4f46e5}
.stp-internal{background:rgba(107,122,153,.1);color:var(--text-sub)}
.sup-contact-person{font-size:12px;color:var(--text-sub);display:flex;align-items:center;gap:4px;margin-top:3px}

/* Contact cell */
.contact-row{display:flex;align-items:center;gap:6px;font-size:13px;margin-bottom:4px;color:var(--text-main)}
.contact-row:last-child{margin-bottom:0}
.contact-row i{font-size:12px;color:var(--text-sub);flex-shrink:0}
.no-contact{font-size:12px;color:var(--text-sub);font-style:italic}

/* Balance cell */
.bal-amount{font-family:'JetBrains Mono',monospace;font-size:14px;font-weight:800;margin-bottom:3px}
.bal-positive{color:#059669}
.bal-negative{color:#dc2626}
.bal-direction{font-size:11px;font-weight:600;display:flex;align-items:center;gap:4px}
.bal-dir-pos{color:#059669}
.bal-dir-neg{color:#dc2626}

/* Branch */
.branch-pill{display:inline-flex;align-items:center;gap:5px;background:rgba(64,153,255,.08);color:var(--blue);border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700}

/* Activity */
.txn-count{font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:700;color:var(--text-main);margin-bottom:3px}
.txn-flows{display:flex;gap:10px;font-size:11px;font-family:'JetBrains Mono',monospace}
.txn-dr{color:var(--red);font-weight:700}
.txn-cr{color:var(--green);font-weight:700}

/* Status */
.status-pill{display:inline-flex;align-items:center;gap:4px;border-radius:20px;padding:4px 11px;font-size:11px;font-weight:700}
.sp-active{background:rgba(34,197,94,.12);color:#166534}
.sp-inactive{background:rgba(107,122,153,.1);color:var(--text-sub)}

/* Action */
.act-btn{width:30px;height:30px;border-radius:8px;border:1.5px solid var(--border);background:var(--card-bg);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;color:var(--text-sub);transition:all .15s}
.act-btn:hover{background:rgba(79,70,229,.08);border-color:#4f46e5;color:#4f46e5}
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
.pag-btn:hover{border-color:#4f46e5;color:#4f46e5;text-decoration:none}
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

/* Balance strip in modal */
.modal-bal-strip{display:grid;grid-template-columns:1fr 1fr;background:var(--surface);border-bottom:1px solid var(--border)}
.mbs-cell{padding:20px;text-align:center;border-right:1px solid var(--border)}
.mbs-cell:last-child{border-right:none}
.mbs-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:6px}
.mbs-value{font-size:26px;font-weight:800;font-family:'JetBrains Mono',monospace;line-height:1;margin-bottom:4px}
.mbs-sub{font-size:11px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:4px}
.mbs-value.pos{color:#059669} .mbs-value.neg{color:#dc2626}
.mbs-sub.pos{color:#059669} .mbs-sub.neg{color:#dc2626}

.modal-body{padding:0}
.modal-tabs{display:flex;gap:6px;padding:16px 24px 0;border-bottom:1px solid var(--border)}
.modal-tab{background:none;border:none;border-bottom:3px solid transparent;padding:8px 16px 12px;font-family:inherit;font-size:13px;font-weight:700;color:var(--text-sub);cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px;margin-bottom:-1px}
.modal-tab.active{color:#4f46e5;border-bottom-color:#4f46e5}
.modal-tab:hover{color:#4f46e5}
.modal-pane{display:none;padding:24px}
.modal-pane.active{display:block}
.detail-section{background:var(--surface);border-radius:12px;padding:18px;margin-bottom:14px}
.detail-section:last-child{margin-bottom:0}
.ds-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:14px}
.ds-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border)}
.ds-row:last-child{border-bottom:none}
.ds-key{font-size:13px;color:var(--text-sub)}
.ds-val{font-size:13px;font-weight:700;color:var(--text-main);text-align:right}
.ds-val.green{color:#059669} .ds-val.red{color:#dc2626} .ds-val.indigo{color:#4f46e5}

.modal-footer-custom{padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end}
.btn-close-modal{display:inline-flex;align-items:center;gap:7px;background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border);border-radius:10px;padding:10px 20px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
.btn-close-modal:hover{border-color:var(--text-sub);color:var(--text-main)}

.txn-loading{text-align:center;padding:40px}
.spinner{width:32px;height:32px;border:3px solid var(--border);border-top-color:#4f46e5;border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 12px}
@keyframes spin{to{transform:rotate(360deg)}}

.pcoded-content{padding:20px!important}
.page-header{display:none!important}
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <!-- Header -->
    <div class="dash-header">
        <div>
            <h4><i class="feather icon-users" style="margin-right:8px;"></i>Suppliers</h4>
            <p>Manage your suppliers and track balances</p>
        </div>
    </div>

    <!-- Summary stat cards -->
    <div class="stat-grid">
        <div class="stat-card total">
            <div class="stat-label">Total Suppliers</div>
            <div class="stat-value"><?= number_format($summary['total_suppliers'] ?? 0) ?></div>
            <i class="feather icon-users stat-icon"></i>
        </div>
        <div class="stat-card owed-by">
            <div class="stat-label">USD Owed by Suppliers</div>
            <div class="stat-value">$<?= number_format($summary['usd_owed_by'] ?? 0, 2) ?></div>
            <i class="feather icon-trending-up stat-icon"></i>
        </div>
        <div class="stat-card owed-to">
            <div class="stat-label">AFS Owed by Suppliers</div>
            <div class="stat-value">؋<?= number_format($summary['afs_owed_by'] ?? 0, 2) ?></div>
            <i class="feather icon-trending-up stat-icon"></i>
        </div>
    </div>
    <div class="stat-grid" style="margin-top:-8px;">
        <div class="stat-card owed-by">
            <div class="stat-label">USD Owed to Suppliers</div>
            <div class="stat-value">$<?= number_format($summary['usd_owed_to'] ?? 0, 2) ?></div>
            <i class="feather icon-trending-down stat-icon"></i>
        </div>
        <div class="stat-card owed-to">
            <div class="stat-label">AFS Owed to Suppliers</div>
            <div class="stat-value">؋<?= number_format($summary['afs_owed_to'] ?? 0, 2) ?></div>
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
                        <input type="text" id="searchInput" class="form-input" placeholder="Supplier name, contact, phone, or email..." value="<?= htmlspecialchars($search) ?>">
                        <button class="search-btn" id="searchBtn"><i class="feather icon-search"></i>Search</button>
                        <?php if (!empty($search)): ?>
                        <a href="?branch=<?= urlencode($selected_branch) ?>" class="clear-btn"><i class="feather icon-x"></i>Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Suppliers Table -->
    <div class="dash-card">
        <div class="dash-card-head">
            <h6><span class="ico"><i class="feather icon-list"></i></span>Suppliers List</h6>
            <span class="count-badge"><?= number_format($total_suppliers) ?> total</span>
        </div>

        <?php if (!empty($suppliers)): ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:44px;">#</th>
                        <th style="width:60px;"></th>
                        <th>Supplier Info</th>
                        <th>Contact Details</th>
                        <th>Balance</th>
                        <th>Branch</th>
                        <th>Activity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php $counter = $offset + 1; foreach ($suppliers as $sup):
                    $bal      = floatval($sup['balance']);
                    $balCls   = $bal >= 0 ? 'bal-positive' : 'bal-negative';
                    $dirCls   = $bal >= 0 ? 'bal-dir-pos' : 'bal-dir-neg';
                    $dirLabel = $bal >= 0 ? 'Owed by supplier' : 'Owed to supplier';
                    $dirIcon  = $bal >= 0 ? 'icon-trending-up' : 'icon-trending-down';
                    $curr     = currency_symbol($sup['currency']);
                    $isExt    = $sup['supplier_type'] === 'External';
                    $statusCls = $sup['status'] === 'active' ? 'sp-active' : 'sp-inactive';
                ?>
                <tr>
                    <td class="td-ctr"><?= $counter++ ?></td>
                    <td>
                        <div class="dropdown">
                            <button class="act-btn dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="feather icon-more-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <button class="dropdown-item view-details" data-supplier='<?= htmlspecialchars(json_encode($sup)) ?>'>
                                    <i class="feather icon-eye" style="color:var(--blue)"></i>View Details
                                </button>
                                <button class="dropdown-item view-transactions" data-supplier-id="<?= $sup['id'] ?>" data-supplier-name="<?= htmlspecialchars($sup['name']) ?>">
                                    <i class="feather icon-list" style="color:#4f46e5"></i>View Transactions
                                </button>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="sup-name"><?= htmlspecialchars($sup['name']) ?></div>
                        <span class="sup-type-pill <?= $isExt ? 'stp-external' : 'stp-internal' ?>">
                            <i class="feather <?= $isExt ? 'icon-external-link' : 'icon-home' ?>"></i>
                            <?= htmlspecialchars($sup['supplier_type']) ?>
                        </span>
                        <?php if (!empty($sup['contact_person'])): ?>
                        <div class="sup-contact-person"><i class="feather icon-user"></i><?= htmlspecialchars($sup['contact_person']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($sup['phone'])): ?>
                        <div class="contact-row"><i class="feather icon-phone"></i><?= htmlspecialchars($sup['phone']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($sup['email'])): ?>
                        <div class="contact-row"><i class="feather icon-mail"></i><?= htmlspecialchars($sup['email']) ?></div>
                        <?php endif; ?>
                        <?php if (empty($sup['phone']) && empty($sup['email'])): ?>
                        <div class="no-contact"><i class="feather icon-minus-circle" style="margin-right:4px;font-size:11px;"></i>No contact info</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="bal-amount <?= $balCls ?>"><?= $curr ?><?= number_format(abs($bal), 2) ?></div>
                        <div class="bal-direction <?= $dirCls ?>">
                            <i class="feather <?= $dirIcon ?>"></i><?= $dirLabel ?>
                        </div>
                    </td>
                    <td>
                        <span class="branch-pill"><i class="feather icon-git-branch"></i><?= htmlspecialchars($sup['branch_name'] ?? 'N/A') ?></span>
                    </td>
                    <td>
                        <div class="txn-count"><?= number_format($sup['transaction_count']) ?> txns</div>
                        <div class="txn-flows">
                            <span class="txn-dr">D <?= $curr ?><?= number_format($sup['total_debits'], 0) ?></span>
                            <span class="txn-cr">C <?= $curr ?><?= number_format($sup['total_credits'], 0) ?></span>
                        </div>
                    </td>
                    <td>
                        <span class="status-pill <?= $statusCls ?>"><?= ucfirst(htmlspecialchars($sup['status'])) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pag-wrap">
            <div class="pag-info">Showing <?= $from ?>â€“<?= $to ?> of <?= number_format($total_suppliers) ?> suppliers</div>
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
            <i class="feather icon-users"></i>
            <p>No suppliers found<?= !empty($search) ? ' for "'.$search.'"' : '' ?>.</p>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- Supplier Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-users" style="margin-right:8px;"></i>Supplier Details</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <!-- Balance strip -->
            <div class="modal-bal-strip">
                <div class="mbs-cell">
                    <div class="mbs-label">Current Balance</div>
                    <div class="mbs-value" id="modal-balance">— </div>
                    <div class="mbs-sub" id="modal-balance-dir">— </div>
                </div>
                <div class="mbs-cell">
                    <div class="mbs-label">Total Transactions</div>
                    <div class="mbs-value indigo" id="modal-txn-count" style="color:#4f46e5;">— </div>
                    <div class="mbs-sub" style="color:var(--text-sub);">All time</div>
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
                            <div class="ds-title">Supplier Information</div>
                            <div class="ds-row"><span class="ds-key">Supplier Name</span><span class="ds-val" id="supplier-name">— </span></div>
                            <div class="ds-row"><span class="ds-key">Type</span><span class="ds-val" id="supplier-type">— </span></div>
                            <div class="ds-row"><span class="ds-key">Currency</span><span class="ds-val indigo" id="supplier-currency" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                            <div class="ds-row"><span class="ds-key">Status</span><span class="ds-val" id="supplier-status">— </span></div>
                            <div class="ds-row"><span class="ds-key">Created At</span><span class="ds-val" id="created-at" style="font-family:'JetBrains Mono',monospace;font-size:11px;">— </span></div>
                        </div>
                        <div class="detail-section">
                            <div class="ds-title">Financial Summary</div>
                            <div class="ds-row"><span class="ds-key">Total Debits</span><span class="ds-val red" id="total-debits" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                            <div class="ds-row"><span class="ds-key">Total Credits</span><span class="ds-val green" id="total-credits" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                            <div class="ds-row"><span class="ds-key">Net Position</span><span class="ds-val" id="net-position" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                        </div>
                    </div>
                </div>

                <div class="modal-pane" id="pane-contact">
                    <div class="detail-section">
                        <div class="ds-title">Contact Information</div>
                        <div class="ds-row"><span class="ds-key">Contact Person</span><span class="ds-val" id="contact-person">— </span></div>
                        <div class="ds-row"><span class="ds-key">Phone</span><span class="ds-val" id="contact-phone" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                        <div class="ds-row"><span class="ds-key">Email</span><span class="ds-val" id="contact-email">— </span></div>
                        <div class="ds-row"><span class="ds-key">Address</span><span class="ds-val" id="contact-address">— </span></div>
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
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header txn-header">
                <h5 class="modal-title"><i class="feather icon-list" style="margin-right:8px;"></i>Transactions —  <span id="supplier-name-header"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:0;">
                <div id="transactionsContent">
                    <div class="txn-loading">
                        <div class="spinner"></div>
                        <p style="color:var(--text-sub);font-size:13px;margin:0;">Loading transactions...</p>
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

function getCurrencySymbol(currency) {
    const symbols = { 'USD': '$', 'AFS': '؋', 'EUR': '€', 'DARHAM': 'د.إ' };
    return symbols[(currency || '').toUpperCase()] || '';
}

function switchTab(tab, btn) {
    document.querySelectorAll('.modal-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.modal-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('pane-' + tab).classList.add('active');
    btn.classList.add('active');
}

document.querySelectorAll('.view-details').forEach(btn => {
    btn.addEventListener('click', function() {
        const s   = JSON.parse(this.getAttribute('data-supplier'));
        const bal = parseFloat(s.balance || 0);
        const curr = getCurrencySymbol(s.currency);
        const isPos = bal >= 0;

        const balEl = document.getElementById('modal-balance');
        balEl.textContent = curr + Math.abs(bal).toFixed(2);
        balEl.className = 'mbs-value ' + (isPos ? 'pos' : 'neg');

        const dirEl = document.getElementById('modal-balance-dir');
        dirEl.textContent = isPos ? '↑ Owed by supplier' : '↓ Owed to supplier';
        dirEl.className = 'mbs-sub ' + (isPos ? 'pos' : 'neg');

        document.getElementById('modal-txn-count').textContent = parseInt(s.transaction_count||0).toLocaleString();

        document.getElementById('supplier-name').textContent     = s.name || '— ';
        document.getElementById('supplier-type').textContent     = s.supplier_type || '— ';
        document.getElementById('supplier-currency').textContent = s.currency || '— ';
        document.getElementById('supplier-status').textContent   = (s.status||'').charAt(0).toUpperCase() + (s.status||'').slice(1);
        document.getElementById('created-at').textContent        = s.created_at || 'N/A';

        const dr  = parseFloat(s.total_debits  || 0);
        const cr  = parseFloat(s.total_credits || 0);
        const net = cr - dr;
        document.getElementById('total-debits').textContent  = curr + dr.toFixed(2);
        document.getElementById('total-credits').textContent = curr + cr.toFixed(2);
        const nEl = document.getElementById('net-position');
        nEl.textContent  = (net >= 0 ? '+' : '') + curr + net.toFixed(2);
        nEl.className    = 'ds-val ' + (net >= 0 ? 'green' : 'red');

        document.getElementById('contact-person').textContent  = s.contact_person || 'N/A';
        document.getElementById('contact-phone').textContent   = s.phone   || 'N/A';
        document.getElementById('contact-email').textContent   = s.email   || 'N/A';
        document.getElementById('contact-address').textContent = s.address || 'N/A';

        switchTab('summary', document.querySelector('.modal-tab'));
        $('#detailsModal').modal('show');
    });
});

document.querySelectorAll('.view-transactions').forEach(btn => {
    btn.addEventListener('click', function() {
        const id   = this.getAttribute('data-supplier-id');
        const name = this.getAttribute('data-supplier-name');
        document.getElementById('supplier-name-header').textContent = name;
        document.getElementById('transactionsContent').innerHTML =
            '<div class="txn-loading"><div class="spinner"></div><p style="color:var(--text-sub);font-size:13px;margin:0;">Loading transactions...</p></div>';
        $('#transactionsModal').modal('show');
        fetch('get_supplier_transactions.php?supplier_id=' + id)
            .then(r => r.text())
            .then(html => { document.getElementById('transactionsContent').innerHTML = html; })
            .catch(err => {
                document.getElementById('transactionsContent').innerHTML =
                    '<div style="padding:20px;color:var(--red);">Error: ' + err.message + '</div>';
            });
    });
});
</script>
