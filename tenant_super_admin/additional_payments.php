<?php
include 'header.php';

$tenant_id      = $_SESSION['tenant_id'];
$user_id        = $_SESSION['user_id'];
$user_role      = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? null;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 25;
$offset = ($page - 1) * $results_per_page;

$query = "SELECT ap.*,
                 c.name as client_name, s.name as supplier_name,
                 ma.name as main_account_name, u.name as created_by_name, b.name as branch_name
          FROM additional_payments ap
          LEFT JOIN clients c ON ap.client_id = c.id
          LEFT JOIN suppliers s ON ap.supplier_id = s.id
          LEFT JOIN main_account ma ON ap.main_account_id = ma.id
          LEFT JOIN users u ON ap.created_by = u.id
          LEFT JOIN branches b ON ap.branch_id = b.id
          WHERE ap.tenant_id = ?";

$params = [$tenant_id];
if ($user_branch_id) { $query .= " AND ap.branch_id = ?"; $params[] = $user_branch_id; }
if (!empty($search)) {
    $query .= " AND (ap.description LIKE ? OR ap.payment_type LIKE ? OR c.name LIKE ? OR s.name LIKE ?)";
    $sp = "%$search%"; $params = array_merge($params, [$sp,$sp,$sp,$sp]);
}
$query .= " ORDER BY ap.created_at DESC LIMIT ? OFFSET ?";
$params[] = $results_per_page; $params[] = $offset;

$stmt = $pdo->prepare($query); $stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cq = "SELECT COUNT(*) as total FROM additional_payments ap
       LEFT JOIN clients c ON ap.client_id = c.id
       LEFT JOIN suppliers s ON ap.supplier_id = s.id
       WHERE ap.tenant_id = ?";
$cp = [$tenant_id];
if ($user_branch_id) { $cq .= " AND ap.branch_id = ?"; $cp[] = $user_branch_id; }
if (!empty($search)) {
    $cq .= " AND (ap.description LIKE ? OR ap.payment_type LIKE ? OR c.name LIKE ? OR s.name LIKE ?)";
    $sp = "%$search%"; $cp = array_merge($cp, [$sp,$sp,$sp,$sp]);
}
$cs = $pdo->prepare($cq); $cs->execute($cp);
$total_payments = $cs->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages    = max(1, ceil($total_payments / $results_per_page));

$sq = "SELECT COUNT(*) as total_payments,
    SUM(CASE WHEN currency='USD' THEN sold_amount ELSE 0 END) as total_usd_amount,
    SUM(CASE WHEN currency='AFS' THEN sold_amount ELSE 0 END) as total_afs_amount,
    SUM(profit) as total_profit
FROM additional_payments WHERE tenant_id = ?";
$sp2 = [$tenant_id];
if ($user_branch_id) { $sq .= " AND branch_id = ?"; $sp2[] = $user_branch_id; }
$ss = $pdo->prepare($sq); $ss->execute($sp2);
$summary = $ss->fetch(PDO::FETCH_ASSOC);

$from = min(($page - 1) * $results_per_page + 1, $total_payments);
$to   = min($page * $results_per_page, $total_payments);
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root {
    --surface:#f4f7fe; --card-bg:#ffffff; --border:#e8edf5;
    --text-main:#1a2340; --text-sub:#6b7a99;
    --green:#22c55e; --red:#ef4444;
    /* Additional Payments identity: cyan â†’ blue */
    --c1:#0891b2; --c2:#1d4ed8;
    --radius:14px; --shadow:0 2px 12px rgba(8,145,178,0.08);
}
*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}

.dash-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 32px rgba(8,145,178,0.28);position:relative;overflow:hidden}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.06'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;letter-spacing:-0.4px;position:relative}
.dash-header p{color:rgba(255,255,255,0.8);margin:0;font-size:13px;position:relative}

/* Stat grid */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
@media(max-width:900px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:500px){.stat-grid{grid-template-columns:1fr}}
.stat-card{border-radius:var(--radius);padding:20px 22px;color:#fff;position:relative;overflow:hidden}
.stat-card::after{content:'';position:absolute;right:-10px;bottom:-10px;width:70px;height:70px;border-radius:50%;background:rgba(255,255,255,0.1)}
.stat-card.total {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(8,145,178,0.3)}
.stat-card.usd   {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(2,132,199,0.3)}
.stat-card.afs   {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(29,78,216,0.3)}
.stat-card.profit{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(5,150,105,0.3)}
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
.count-badge{background:rgba(8,145,178,.1);color:#0891b2;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;margin-left:auto}

/* Search */
.form-label-custom{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:block;margin-bottom:6px}
.search-group{display:flex;gap:8px}
.form-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:9px 13px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s}
.form-input:focus{border-color:#0891b2;background:#fff;box-shadow:0 0 0 3px rgba(8,145,178,.1)}
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

/* Payment details cell */
.pay-type{display:inline-flex;align-items:center;gap:5px;background:rgba(8,145,178,.1);color:#0891b2;border-radius:20px;padding:3px 10px;font-size:10px;font-weight:700;margin-bottom:5px}
.pay-desc{font-weight:700;color:var(--text-main);margin-bottom:3px}
.pay-receipt{font-size:11px;color:var(--text-sub);font-family:'JetBrains Mono',monospace;display:flex;align-items:center;gap:4px}

/* Amounts cell */
.amt-row{display:flex;align-items:baseline;gap:6px;margin-bottom:4px;font-size:13px}
.amt-row:last-child{margin-bottom:0}
.amt-lbl{font-size:11px;color:var(--text-sub);font-weight:600;min-width:34px}
.amt-val{font-family:'JetBrains Mono',monospace;font-weight:800}
.av-base  {color:#0891b2}
.av-sold  {color:#1d4ed8}
.av-profit-pos{color:#059669}
.av-profit-neg{color:var(--red)}

/* Client/supplier cell */
.cs-row{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text-main)}
.cs-row i{font-size:12px;color:var(--text-sub)}
.cs-lbl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text-sub);margin-bottom:3px}
.no-cs{font-size:12px;color:var(--text-sub);font-style:italic}

/* Branch */
.branch-pill{display:inline-flex;align-items:center;gap:5px;background:rgba(8,145,178,.08);color:#0891b2;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700}

/* Date cell */
.exp-date{font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:700;color:var(--text-main)}
.exp-time{font-size:11px;color:var(--text-sub);margin-top:2px;font-family:'JetBrains Mono',monospace}

/* Created by */
.creator{font-size:13px;font-weight:600;color:var(--text-main);display:flex;align-items:center;gap:5px}
.creator i{font-size:12px;color:var(--text-sub)}

/* Action */
.act-btn{width:30px;height:30px;border-radius:8px;border:1.5px solid var(--border);background:var(--card-bg);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;color:var(--text-sub);transition:all .15s}
.act-btn:hover{background:rgba(8,145,178,.08);border-color:#0891b2;color:#0891b2}
.dropdown-menu{border-radius:12px;border:1px solid var(--border);box-shadow:0 8px 24px rgba(0,0,0,.1);padding:6px;min-width:160px}
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
.pag-btn:hover{border-color:#0891b2;color:#0891b2;text-decoration:none}
.pag-btn.active{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-color:transparent;color:#fff}
.pag-btn.disabled{opacity:.4;pointer-events:none}
.pag-dots{display:flex;align-items:center;padding:0 4px;color:var(--text-sub);font-size:13px}

/* Modal */
.modal-content{border:none;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.18);font-family:inherit}
.modal-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border-radius:16px 16px 0 0;border:none;padding:18px 24px}
.modal-header .modal-title{font-weight:700;font-size:15px}
.modal-header .close{color:#fff;opacity:.8;font-size:22px}
.modal-header .close:hover{opacity:1}

/* 3-col strip */
.modal-summary{display:grid;grid-template-columns:1fr 1fr 1fr;background:var(--surface);border-bottom:1px solid var(--border)}
.ms-cell{padding:18px;text-align:center;border-right:1px solid var(--border)}
.ms-cell:last-child{border-right:none}
.ms-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:5px}
.ms-val{font-size:22px;font-weight:800;font-family:'JetBrains Mono',monospace;line-height:1;margin-bottom:3px}
.ms-val.sold  {color:#1d4ed8}
.ms-val.base  {color:#0891b2}
.ms-val.ppos  {color:#059669}
.ms-val.pneg  {color:var(--red)}
.ms-sub{font-size:11px;font-weight:600;color:var(--text-sub)}

.modal-body{padding:0}
.modal-tabs{display:flex;gap:6px;padding:16px 24px 0;border-bottom:1px solid var(--border)}
.modal-tab{background:none;border:none;border-bottom:3px solid transparent;padding:8px 16px 12px;font-family:inherit;font-size:13px;font-weight:700;color:var(--text-sub);cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px;margin-bottom:-1px}
.modal-tab.active{color:#0891b2;border-bottom-color:#0891b2}
.modal-tab:hover{color:#0891b2}
.modal-pane{display:none;padding:24px}
.modal-pane.active{display:block}
.detail-section{background:var(--surface);border-radius:12px;padding:18px;margin-bottom:14px}
.detail-section:last-child{margin-bottom:0}
.ds-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:14px}
.ds-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border)}
.ds-row:last-child{border-bottom:none}
.ds-key{font-size:13px;color:var(--text-sub)}
.ds-val{font-size:13px;font-weight:700;color:var(--text-main);text-align:right}
.ds-val.cyan {color:#0891b2}
.ds-val.blue {color:#1d4ed8}
.ds-val.green{color:#059669}
.ds-val.red  {color:var(--red)}

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
            <h4><i class="feather icon-credit-card" style="margin-right:8px;"></i>Additional Payments</h4>
            <p>View and manage all additional payments</p>
        </div>
    </div>

    <!-- Summary cards -->
    <div class="stat-grid">
        <div class="stat-card total">
            <div class="stat-label">Total Payments</div>
            <div class="stat-value"><?= number_format($summary['total_payments'] ?? 0) ?></div>
            <i class="feather icon-credit-card stat-icon"></i>
        </div>
        <div class="stat-card usd">
            <div class="stat-label">Total USD</div>
            <div class="stat-value">$<?= number_format($summary['total_usd_amount'] ?? 0, 2) ?></div>
            <i class="feather icon-dollar-sign stat-icon"></i>
        </div>
        <div class="stat-card afs">
            <div class="stat-label">Total AFS</div>
            <div class="stat-value">AFS <?= number_format($summary['total_afs_amount'] ?? 0, 2) ?></div>
            <i class="feather icon-layers stat-icon"></i>
        </div>
        <div class="stat-card profit">
            <div class="stat-label">Total Profit</div>
            <div class="stat-value">$<?= number_format($summary['total_profit'] ?? 0, 2) ?></div>
            <i class="feather icon-trending-up stat-icon"></i>
        </div>
    </div>

    <!-- Search -->
    <div class="dash-card">
        <div class="dash-card-head">
            <h6><span class="ico"><i class="feather icon-search"></i></span>Search Payments</h6>
        </div>
        <div class="dash-card-body">
            <label class="form-label-custom">Search by description, type, client or supplier</label>
            <div class="search-group">
                <input type="text" id="searchInput" class="form-input" placeholder="Description, payment type, client, supplierâ€¦" value="<?= htmlspecialchars($search) ?>">
                <button class="search-btn" id="searchBtn"><i class="feather icon-search"></i>Search</button>
                <?php if (!empty($search)): ?>
                <a href="?" class="clear-btn"><i class="feather icon-x"></i>Clear</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="dash-card">
        <div class="dash-card-head">
            <h6><span class="ico"><i class="feather icon-list"></i></span>Payments List</h6>
            <span class="count-badge"><?= number_format($total_payments) ?> total</span>
        </div>

        <?php if (!empty($payments)): ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:44px;">#</th>
                        <th style="width:60px;"></th>
                        <th>Payment Details</th>
                        <th>Amounts</th>
                        <th>Client / Supplier</th>
                        <th>Branch</th>
                        <th>Date</th>
                        <th>Created By</th>
                    </tr>
                </thead>
                <tbody>
                <?php $counter = $offset + 1; foreach ($payments as $pay):
                    $curr   = $pay['currency'] === 'USD' ? '$' : 'AFS ';
                    $profit = floatval($pay['profit']);
                    $pCls   = $profit >= 0 ? 'av-profit-pos' : 'av-profit-neg';
                ?>
                <tr>
                    <td class="td-ctr"><?= $counter++ ?></td>
                    <td>
                        <div class="dropdown">
                            <button class="act-btn dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="feather icon-more-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <button class="dropdown-item view-details" data-payment='<?= htmlspecialchars(json_encode($pay)) ?>'>
                                    <i class="feather icon-eye" style="color:#0891b2"></i>View Details
                                </button>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="pay-type"><i class="feather icon-tag"></i><?= htmlspecialchars($pay['payment_type']) ?></div>
                        <div class="pay-desc"><?= htmlspecialchars($pay['description']) ?></div>
                        <?php if (!empty($pay['receipt'])): ?>
                        <div class="pay-receipt"><i class="feather icon-hash"></i><?= htmlspecialchars($pay['receipt']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="amt-row"><span class="amt-lbl">Base</span><span class="amt-val av-base"><?= $curr ?><?= number_format($pay['base_amount'], 2) ?></span></div>
                        <div class="amt-row"><span class="amt-lbl">Sold</span><span class="amt-val av-sold"><?= $curr ?><?= number_format($pay['sold_amount'], 2) ?></span></div>
                        <div class="amt-row"><span class="amt-lbl">Profit</span><span class="amt-val <?= $pCls ?>"><?= $profit >= 0 ? '+' : '' ?>$<?= number_format($profit, 2) ?></span></div>
                    </td>
                    <td>
                        <?php if (!empty($pay['client_name'])): ?>
                        <div class="cs-lbl">Client</div>
                        <div class="cs-row"><i class="feather icon-user"></i><?= htmlspecialchars($pay['client_name']) ?></div>
                        <?php elseif (!empty($pay['supplier_name'])): ?>
                        <div class="cs-lbl">Supplier</div>
                        <div class="cs-row"><i class="feather icon-truck"></i><?= htmlspecialchars($pay['supplier_name']) ?></div>
                        <?php else: ?>
                        <div class="no-cs"><i class="feather icon-minus-circle" style="margin-right:4px;font-size:11px;"></i>N/A</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="branch-pill"><i class="feather icon-git-branch"></i><?= htmlspecialchars($pay['branch_name'] ?? 'N/A') ?></span>
                    </td>
                    <td>
                        <div class="exp-date"><?= date('d/m/Y', strtotime($pay['created_at'])) ?></div>
                        <div class="exp-time"><?= date('H:i', strtotime($pay['created_at'])) ?></div>
                    </td>
                    <td>
                        <div class="creator"><i class="feather icon-user"></i><?= htmlspecialchars($pay['created_by_name'] ?? 'Unknown') ?></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pag-wrap">
            <div class="pag-info">Showing <?= $from ?>â€“<?= $to ?> of <?= number_format($total_payments) ?> payments</div>
            <div class="pag-links">
                <?php $base = '?search='.urlencode($search); ?>
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
            <i class="feather icon-credit-card"></i>
            <p>No payments found<?= !empty($search) ? ' for "'.$search.'"' : '' ?>.</p>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- Payment Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-credit-card" style="margin-right:8px;"></i>Payment Details</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <!-- 3-col strip: sold / base / profit -->
            <div class="modal-summary">
                <div class="ms-cell">
                    <div class="ms-label">Sold Amount</div>
                    <div class="ms-val sold" id="modal-sold">â€”</div>
                    <div class="ms-sub" id="modal-currency">â€”</div>
                </div>
                <div class="ms-cell">
                    <div class="ms-label">Base Amount</div>
                    <div class="ms-val base" id="modal-base">â€”</div>
                    <div class="ms-sub">Cost</div>
                </div>
                <div class="ms-cell">
                    <div class="ms-label">Profit</div>
                    <div class="ms-val" id="modal-profit">â€”</div>
                    <div class="ms-sub" id="modal-profit-dir">â€”</div>
                </div>
            </div>

            <div class="modal-body">
                <div class="modal-tabs">
                    <button class="modal-tab active" onclick="switchTab('summary',this)"><i class="feather icon-info"></i>Summary</button>
                    <button class="modal-tab" onclick="switchTab('financial',this)"><i class="feather icon-dollar-sign"></i>Financial Details</button>
                </div>

                <div class="modal-pane active" id="pane-summary">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div class="detail-section">
                            <div class="ds-title">Payment Information</div>
                            <div class="ds-row"><span class="ds-key">Payment Type</span><span class="ds-val cyan" id="pay-type">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Description</span><span class="ds-val" id="pay-desc">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Branch</span><span class="ds-val" id="pay-branch">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Created By</span><span class="ds-val" id="pay-created-by">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Created At</span><span class="ds-val" id="pay-created-at" style="font-family:'JetBrains Mono',monospace;font-size:11px;">â€”</span></div>
                        </div>
                        <div class="detail-section">
                            <div class="ds-title">Client / Supplier Details</div>
                            <div class="ds-row"><span class="ds-key">Client</span><span class="ds-val" id="pay-client">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Supplier</span><span class="ds-val" id="pay-supplier">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Main Account</span><span class="ds-val" id="pay-main-account">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Receipt</span><span class="ds-val" id="pay-receipt" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                        </div>
                    </div>
                </div>

                <div class="modal-pane" id="pane-financial">
                    <div class="detail-section">
                        <div class="ds-title">Financial Breakdown</div>
                        <div class="ds-row"><span class="ds-key">Base Amount</span><span class="ds-val cyan" id="fin-base" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                        <div class="ds-row"><span class="ds-key">Sold Amount</span><span class="ds-val blue" id="fin-sold" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                        <div class="ds-row"><span class="ds-key">Profit</span><span class="ds-val" id="fin-profit" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                        <div class="ds-row"><span class="ds-key">Currency</span><span class="ds-val" id="fin-currency" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                        <div class="ds-row"><span class="ds-key">Payment ID</span><span class="ds-val" id="fin-id" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
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
document.getElementById('searchBtn').addEventListener('click', doSearch);
document.getElementById('searchInput').addEventListener('keypress', e => { if(e.key==='Enter') doSearch(); });

function doSearch() {
    const s = document.getElementById('searchInput').value.trim();
    window.location.href = '?' + (s ? 'search=' + encodeURIComponent(s) : '');
}

function switchTab(tab, btn) {
    document.querySelectorAll('.modal-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.modal-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('pane-' + tab).classList.add('active');
    btn.classList.add('active');
}

document.querySelectorAll('.view-details').forEach(btn => {
    btn.addEventListener('click', function() {
        const p    = JSON.parse(this.getAttribute('data-payment'));
        const curr = p.currency === 'USD' ? '$' : 'AFS ';
        const sold = parseFloat(p.sold_amount || 0);
        const base = parseFloat(p.base_amount || 0);
        const prof = parseFloat(p.profit || 0);

        document.getElementById('modal-sold').textContent     = curr + sold.toFixed(2);
        document.getElementById('modal-currency').textContent = p.currency || 'â€”';
        document.getElementById('modal-base').textContent     = curr + base.toFixed(2);

        const profEl = document.getElementById('modal-profit');
        profEl.textContent = (prof >= 0 ? '+$' : '-$') + Math.abs(prof).toFixed(2);
        profEl.className = 'ms-val ' + (prof >= 0 ? 'ppos' : 'pneg');
        document.getElementById('modal-profit-dir').textContent = prof >= 0 ? 'Net gain' : 'Net loss';

        document.getElementById('pay-type').textContent       = p.payment_type || 'â€”';
        document.getElementById('pay-desc').textContent       = p.description  || 'â€”';
        document.getElementById('pay-branch').textContent     = p.branch_name  || 'â€”';
        document.getElementById('pay-created-by').textContent = p.created_by_name || 'Unknown';
        document.getElementById('pay-created-at').textContent = p.created_at ? new Date(p.created_at).toLocaleString() : 'â€”';

        document.getElementById('pay-client').textContent      = p.client_name       || 'N/A';
        document.getElementById('pay-supplier').textContent    = p.supplier_name     || 'N/A';
        document.getElementById('pay-main-account').textContent= p.main_account_name || 'N/A';
        document.getElementById('pay-receipt').textContent     = p.receipt           || 'N/A';

        document.getElementById('fin-base').textContent     = curr + base.toFixed(2);
        document.getElementById('fin-sold').textContent     = curr + sold.toFixed(2);
        const finProf = document.getElementById('fin-profit');
        finProf.textContent = (prof >= 0 ? '+$' : '-$') + Math.abs(prof).toFixed(2);
        finProf.className = 'ds-val ' + (prof >= 0 ? 'green' : 'red');
        document.getElementById('fin-currency').textContent = p.currency || 'â€”';
        document.getElementById('fin-id').textContent       = p.id || 'â€”';

        switchTab('summary', document.querySelector('.modal-tab'));
        $('#detailsModal').modal('show');
    });
});
</script>
