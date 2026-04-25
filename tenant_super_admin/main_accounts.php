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

$query = "SELECT ma.*, b.name as branch_name,
    COUNT(mat.id) as transaction_count,
    COALESCE(SUM(CASE WHEN mat.type='credit' THEN mat.amount ELSE 0 END),0) as total_credits,
    COALESCE(SUM(CASE WHEN mat.type='debit'  THEN mat.amount ELSE 0 END),0) as total_debits
FROM main_account ma
LEFT JOIN branches b ON ma.branch_id = b.id
LEFT JOIN main_account_transactions mat ON ma.id = mat.main_account_id AND mat.tenant_id = ma.tenant_id
WHERE ma.tenant_id = ?";

$params = [$tenant_id];
if ($selected_branch !== 'all') { $query .= " AND ma.branch_id = ?"; $params[] = $selected_branch; }
if (!empty($search)) {
    $query .= " AND (ma.name LIKE ? OR ma.bank_account_number LIKE ? OR ma.bank_name LIKE ?)";
    $sp = "%$search%"; $params = array_merge($params, [$sp,$sp,$sp]);
}
$query .= " GROUP BY ma.id LIMIT ? OFFSET ?";
$params[] = $results_per_page; $params[] = $offset;

$stmt = $pdo->prepare($query); $stmt->execute($params);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cq = "SELECT COUNT(*) as total FROM main_account ma WHERE ma.tenant_id = ?";
$cp = [$tenant_id];
if ($selected_branch !== 'all') { $cq .= " AND ma.branch_id = ?"; $cp[] = $selected_branch; }
if (!empty($search)) {
    $cq .= " AND (ma.name LIKE ? OR ma.bank_account_number LIKE ? OR ma.bank_name LIKE ?)";
    $sp = "%$search%"; $cp = array_merge($cp, [$sp,$sp,$sp]);
}
$cs = $pdo->prepare($cq); $cs->execute($cp);
$total_accounts = $cs->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages    = max(1, ceil($total_accounts / $results_per_page));

$sq = "SELECT SUM(usd_balance) as total_usd, SUM(afs_balance) as total_afs,
              SUM(euro_balance) as total_euro, SUM(darham_balance) as total_darham
       FROM main_account WHERE tenant_id = ? AND status = 'active'";
$sp2 = [$tenant_id];
if ($selected_branch !== 'all') { $sq .= " AND branch_id = ?"; $sp2[] = $selected_branch; }
$ss = $pdo->prepare($sq); $ss->execute($sp2);
$summary = $ss->fetch(PDO::FETCH_ASSOC);

$bs = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND status='active' ORDER BY name");
$bs->execute([$tenant_id]);
$branches = $bs->fetchAll(PDO::FETCH_ASSOC);

$from = min(($page - 1) * $results_per_page + 1, $total_accounts);
$to   = min($page * $results_per_page, $total_accounts);
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root {
    --teal:#2ed8b6; --blue:#4099ff;
    --surface:#f4f7fe; --card-bg:#ffffff; --border:#e8edf5;
    --text-main:#1a2340; --text-sub:#6b7a99;
    --green:#22c55e; --amber:#f59e0b; --red:#ef4444; --purple:#8b5cf6;
    --radius:14px; --shadow:0 2px 12px rgba(64,153,255,0.08);
}
*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}

.dash-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 32px rgba(29,78,216,0.22);position:relative;overflow:hidden}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;letter-spacing:-0.4px;position:relative}
.dash-header p{color:rgba(255,255,255,0.8);margin:0;font-size:13px;position:relative}

.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
@media(max-width:900px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:500px){.stat-grid{grid-template-columns:1fr}}
.stat-card{border-radius:var(--radius);padding:20px 22px;color:#fff;position:relative;overflow:hidden}
.stat-card::after{content:'';position:absolute;right:-10px;bottom:-10px;width:70px;height:70px;border-radius:50%;background:rgba(255,255,255,0.1)}
.stat-card.usd   {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(29,78,216,0.3)}
.stat-card.afs   {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(15,118,110,0.3)}
.stat-card.euro  {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(180,83,9,0.3)}
.stat-card.darham{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(124,58,237,0.3)}
.stat-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;opacity:.8;margin-bottom:8px}
.stat-value{font-family:'JetBrains Mono',monospace;font-size:22px;font-weight:800;line-height:1}
.stat-icon{position:absolute;right:18px;top:50%;transform:translateY(-50%);font-size:32px;opacity:.25}

.dash-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.dash-card:last-child{margin-bottom:0}
.dash-card-head{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.dash-card-head h6{font-size:14px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}
.dash-card-head h6 .ico{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0}
.dash-card-body{padding:20px}
.count-badge{background:rgba(29,78,216,.1);color:#1d4ed8;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;margin-left:auto}

.search-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:end}
@media(max-width:700px){.search-row{grid-template-columns:1fr}}
.form-label-custom{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:block;margin-bottom:6px}
.search-group{display:flex;gap:8px}
.form-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:9px 13px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s}
.form-input:focus{border-color:#1d4ed8;background:#fff;box-shadow:0 0 0 3px rgba(29,78,216,.1)}
.search-btn{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border:none;border-radius:10px;padding:9px 18px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:all .2s;white-space:nowrap;flex-shrink:0}
.search-btn:hover{opacity:.9}
.clear-btn{display:inline-flex;align-items:center;gap:6px;background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border);border-radius:10px;padding:9px 14px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;white-space:nowrap;flex-shrink:0;transition:all .2s}
.clear-btn:hover{border-color:var(--text-sub);color:var(--text-main);text-decoration:none}

.data-table{width:100%;border-collapse:collapse}
.data-table thead th{background:var(--surface);padding:11px 16px;font-size:11px;font-weight:700;color:var(--text-sub);text-transform:uppercase;letter-spacing:.6px;border-bottom:1.5px solid var(--border);white-space:nowrap}
.data-table tbody tr{transition:background .15s}
.data-table tbody tr:hover{background:var(--surface)}
.data-table tbody td{padding:13px 16px;border-bottom:1px solid var(--border);font-size:13px;vertical-align:middle}
.data-table tbody tr:last-child td{border-bottom:none}
.td-ctr{text-align:center;font-size:12px;color:var(--text-sub);font-family:'JetBrains Mono',monospace}

.acc-name{font-weight:700;color:var(--text-main);margin-bottom:4px}
.acc-type-pill{display:inline-flex;align-items:center;gap:4px;border-radius:20px;padding:3px 9px;font-size:10px;font-weight:700}
.acc-bank{background:rgba(29,78,216,.1);color:#1d4ed8}
.acc-cash{background:rgba(107,122,153,.1);color:var(--text-sub)}

.bank-name{font-weight:700;font-size:13px;color:var(--text-main);margin-bottom:2px}
.bank-num{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--text-sub)}
.internal-tag{font-size:12px;color:var(--text-sub);font-style:italic}

.bal-row{display:flex;align-items:center;gap:5px;margin-bottom:3px;font-family:'JetBrains Mono',monospace;font-size:12px;font-weight:600}
.bal-row:last-child{margin-bottom:0}
.bal-usd{color:#1d4ed8} .bal-afs{color:#0f766e} .bal-euro{color:#b45309} .bal-darham{color:#7c3aed}
.bal-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.bd-usd{background:#1d4ed8} .bd-afs{background:#0f766e} .bd-euro{background:#b45309} .bd-darham{background:#7c3aed}

.txn-count{font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:700;color:var(--text-main);margin-bottom:3px}
.txn-flows{display:flex;gap:10px;font-size:11px;font-family:'JetBrains Mono',monospace}
.txn-cr{color:var(--green);font-weight:700}
.txn-dr{color:var(--red);font-weight:700}

.status-pill{display:inline-flex;align-items:center;gap:4px;border-radius:20px;padding:4px 11px;font-size:11px;font-weight:700}
.sp-active{background:rgba(34,197,94,.12);color:#166534}
.sp-inactive{background:rgba(107,122,153,.1);color:var(--text-sub)}

.act-btn{width:30px;height:30px;border-radius:8px;border:1.5px solid var(--border);background:var(--card-bg);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;color:var(--text-sub);transition:all .15s}
.act-btn:hover{background:rgba(29,78,216,.08);border-color:#1d4ed8;color:#1d4ed8}
.dropdown-menu{border-radius:12px;border:1px solid var(--border);box-shadow:0 8px 24px rgba(0,0,0,.1);padding:6px;min-width:180px}
.dropdown-item{border-radius:8px;padding:8px 12px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;transition:background .15s}
.dropdown-item:hover{background:var(--surface)}

.empty-state{text-align:center;padding:60px 20px}
.empty-state i{font-size:44px;opacity:.2;display:block;margin-bottom:14px}
.empty-state p{color:var(--text-sub);font-size:14px;margin:0}

.pag-wrap{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;padding:16px 20px;border-top:1px solid var(--border)}
.pag-info{font-size:12px;color:var(--text-sub)}
.pag-links{display:flex;gap:4px}
.pag-btn{min-width:32px;height:32px;border-radius:8px;border:1.5px solid var(--border);background:var(--card-bg);color:var(--text-main);font-size:12px;font-weight:600;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;padding:0 8px;transition:all .15s}
.pag-btn:hover{border-color:#1d4ed8;color:#1d4ed8;text-decoration:none}
.pag-btn.active{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-color:transparent;color:#fff}
.pag-btn.disabled{opacity:.4;pointer-events:none}
.pag-dots{display:flex;align-items:center;padding:0 4px;color:var(--text-sub);font-size:13px}

.modal-content{border:none;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.18);font-family:inherit}
.modal-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border-radius:16px 16px 0 0;border:none;padding:18px 24px}
.modal-header.txn-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%)}
.modal-header .modal-title{font-weight:700;font-size:15px}
.modal-header .close{color:#fff;opacity:.8;font-size:22px}
.modal-header .close:hover{opacity:1}

.modal-summary{display:grid;grid-template-columns:repeat(4,1fr);background:var(--surface);border-bottom:1px solid var(--border)}
@media(max-width:600px){.modal-summary{grid-template-columns:repeat(2,1fr)}}
.ms-cell{padding:16px;text-align:center;border-right:1px solid var(--border)}
.ms-cell:last-child{border-right:none}
.ms-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:4px}
.ms-val{font-size:18px;font-weight:800;font-family:'JetBrains Mono',monospace;line-height:1}
.ms-val.usd-c{color:#1d4ed8} .ms-val.afs-c{color:#0f766e} .ms-val.euro-c{color:#b45309} .ms-val.darham-c{color:#7c3aed}

.modal-body{padding:0}
.modal-tabs{display:flex;gap:6px;padding:16px 24px 0;border-bottom:1px solid var(--border)}
.modal-tab{background:none;border:none;border-bottom:3px solid transparent;padding:8px 16px 12px;font-family:inherit;font-size:13px;font-weight:700;color:var(--text-sub);cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px;margin-bottom:-1px}
.modal-tab.active{color:#1d4ed8;border-bottom-color:#1d4ed8}
.modal-tab:hover{color:#1d4ed8}
.modal-pane{display:none;padding:24px}
.modal-pane.active{display:block}
.detail-section{background:var(--surface);border-radius:12px;padding:18px;margin-bottom:14px}
.detail-section:last-child{margin-bottom:0}
.ds-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:14px}
.ds-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border)}
.ds-row:last-child{border-bottom:none}
.ds-key{font-size:13px;color:var(--text-sub)}
.ds-val{font-size:13px;font-weight:700;color:var(--text-main);text-align:right}
.ds-val.green{color:var(--green)} .ds-val.red{color:var(--red)} .ds-val.blue{color:#1d4ed8}

.modal-footer-custom{padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end}
.btn-close-modal{display:inline-flex;align-items:center;gap:7px;background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border);border-radius:10px;padding:10px 20px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
.btn-close-modal:hover{border-color:var(--text-sub);color:var(--text-main)}

.txn-loading{text-align:center;padding:40px}
.spinner{width:32px;height:32px;border:3px solid var(--border);border-top-color:#1d4ed8;border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 12px}
@keyframes spin{to{transform:rotate(360deg)}}

.pcoded-content{padding:20px!important}
.page-header{display:none!important}
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <div class="dash-header">
        <div>
            <h4><i class="feather icon-credit-card" style="margin-right:8px;"></i>Main Accounts</h4>
            <p>Manage your main accounts and bank balances</p>
        </div>
    </div>

    <!-- Currency summary cards -->
    <div class="stat-grid">
        <div class="stat-card usd">
            <div class="stat-label">Total USD</div>
            <div class="stat-value">$<?= number_format($summary['total_usd'] ?? 0, 2) ?></div>
            <i class="feather icon-dollar-sign stat-icon"></i>
        </div>
        <div class="stat-card afs">
            <div class="stat-label">Total AFS</div>
            <div class="stat-value">AFS <?= number_format($summary['total_afs'] ?? 0, 2) ?></div>
            <i class="feather icon-credit-card stat-icon"></i>
        </div>
        <div class="stat-card euro">
    <div class="stat-label">Total Euro</div>
    <div class="stat-value">€<?= number_format($summary['total_euro'] ?? 0, 2) ?></div>
    <i class="feather icon-repeat stat-icon"></i>
</div>

<div class="stat-card darham">
    <div class="stat-label">Total AED</div>
    <div class="stat-value">د.إ <?= number_format($summary['total_darham'] ?? 0, 2) ?></div>
    <i class="feather icon-package stat-icon"></i>
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
                        <input type="text" id="searchInput" class="form-input" placeholder="Account name, bank number, or bank name..." value="<?= htmlspecialchars($search) ?>">
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
            <h6><span class="ico"><i class="feather icon-list"></i></span>Accounts List</h6>
            <span class="count-badge"><?= number_format($total_accounts) ?> total</span>
        </div>

        <?php if (!empty($accounts)): ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:44px;">#</th>
                        <th style="width:60px;"></th>
                        <th>Account Info</th>
                        <th>Bank Details</th>
                        <th>Balances</th>
                        <th>Activity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php $counter = $offset + 1; foreach ($accounts as $acc):
                    $isBank   = $acc['account_type'] === 'bank';
                    $statusCls = $acc['status'] === 'active' ? 'sp-active' : 'sp-inactive';
                    $credits  = floatval($acc['total_credits']);
                    $debits   = floatval($acc['total_debits']);
                ?>
                <tr>
                    <td class="td-ctr"><?= $counter++ ?></td>
                    <td>
                        <div class="dropdown">
                            <button class="act-btn dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="feather icon-more-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <button class="dropdown-item view-details" data-account='<?= htmlspecialchars(json_encode($acc)) ?>'>
                                    <i class="feather icon-eye" style="color:var(--blue)"></i>View Details
                                </button>
                                <button class="dropdown-item view-transactions" data-account-id="<?= $acc['id'] ?>" data-account-name="<?= htmlspecialchars($acc['name']) ?>">
                                    <i class="feather icon-list" style="color:#0f766e"></i>View Transactions
                                </button>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="acc-name"><?= htmlspecialchars($acc['name']) ?></div>
                        <span class="acc-type-pill <?= $isBank ? 'acc-bank' : 'acc-cash' ?>">
                            <i class="feather <?= $isBank ? 'icon-home' : 'icon-credit-card' ?>"></i>
                            <?= ucfirst(htmlspecialchars($acc['account_type'])) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($isBank): ?>
                        <div class="bank-name"><?= htmlspecialchars($acc['bank_name'] ?: 'N/A') ?></div>
                        <div class="bank-num"><i class="feather icon-hash" style="font-size:10px;margin-right:2px;"></i><?= htmlspecialchars($acc['bank_account_number'] ?: 'N/A') ?></div>
                        <?php else: ?>
                        <div class="internal-tag"><i class="feather icon-box" style="margin-right:4px;font-size:11px;"></i>Internal Account</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="bal-row bal-usd"><span class="bal-dot bd-usd"></span>$<?= number_format($acc['usd_balance'], 2) ?></div>
                        <div class="bal-row bal-afs"><span class="bal-dot bd-afs"></span>AFS <?= number_format($acc['afs_balance'], 2) ?></div>
                        <?php if (floatval($acc['euro_balance']) > 0): ?>
    <div class="bal-row bal-euro">
        <span class="bal-dot bd-euro"></span>
        €<?= number_format($acc['euro_balance'], 2) ?>
    </div>
<?php endif; ?>

<?php if (floatval($acc['darham_balance']) > 0): ?>
    <div class="bal-row bal-darham">
        <span class="bal-dot bd-darham"></span>
        د.إ <?= number_format($acc['darham_balance'], 2) ?>
    </div>
<?php endif; ?>
                    </td>
                    <td>
                        <div class="txn-count"><?= number_format($acc['transaction_count']) ?> txns</div>
                        <div class="txn-flows">
                            <span class="txn-cr">â†‘ $<?= number_format($credits, 0) ?></span>
                            <span class="txn-dr">â†“ $<?= number_format($debits, 0) ?></span>
                        </div>
                    </td>
                    <td>
                        <span class="status-pill <?= $statusCls ?>"><?= ucfirst(htmlspecialchars($acc['status'])) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pag-wrap">
            <div class="pag-info">Showing <?= $from ?>â€“<?= $to ?> of <?= number_format($total_accounts) ?> accounts</div>
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
            <i class="feather icon-credit-card"></i>
            <p>No accounts found<?= !empty($search) ? ' for "'.$search.'"' : '' ?>.</p>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- Account Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-credit-card" style="margin-right:8px;"></i>Main Account Details</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-summary">
                <div class="ms-cell"><div class="ms-label">USD</div><div class="ms-val usd-c" id="usd-balance">— </div></div>
                <div class="ms-cell"><div class="ms-label">AFS</div><div class="ms-val afs-c" id="afs-balance">— </div></div>
                <div class="ms-cell"><div class="ms-label">Euro</div><div class="ms-val euro-c" id="euro-balance">— </div></div>
                <div class="ms-cell"><div class="ms-label">AED</div><div class="ms-val darham-c" id="darham-balance">— </div></div>
            </div>
            <div class="modal-body">
                <div class="modal-tabs">
                    <button class="modal-tab active" onclick="switchTab('summary',this)"><i class="feather icon-info"></i>Summary</button>
                    <button class="modal-tab" onclick="switchTab('bank',this)"><i class="feather icon-home"></i>Bank Details</button>
                </div>
                <div class="modal-pane active" id="pane-summary">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div class="detail-section">
                            <div class="ds-title">Account Information</div>
                            <div class="ds-row"><span class="ds-key">Account Name</span><span class="ds-val" id="account-name">— </span></div>
                            <div class="ds-row"><span class="ds-key">Account Type</span><span class="ds-val" id="account-type">— </span></div>
                            <div class="ds-row"><span class="ds-key">Status</span><span class="ds-val" id="account-status">— </span></div>
                            <div class="ds-row"><span class="ds-key">Last Updated</span><span class="ds-val" id="last-updated" style="font-family:'JetBrains Mono',monospace;font-size:11px;">— </span></div>
                            <div class="ds-row"><span class="ds-key">Created At</span><span class="ds-val" id="created-at" style="font-family:'JetBrains Mono',monospace;font-size:11px;">— </span></div>
                        </div>
                        <div class="detail-section">
                            <div class="ds-title">Activity Summary</div>
                            <div class="ds-row"><span class="ds-key">Total Transactions</span><span class="ds-val blue" id="total-transactions" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                            <div class="ds-row"><span class="ds-key">Total Credits</span><span class="ds-val green" id="total-credits" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                            <div class="ds-row"><span class="ds-key">Total Debits</span><span class="ds-val red" id="total-debits" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                            <div class="ds-row"><span class="ds-key">Net Flow</span><span class="ds-val" id="net-flow" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                        </div>
                    </div>
                </div>
                <div class="modal-pane" id="pane-bank">
                    <div class="detail-section">
                        <div class="ds-title">Bank Information</div>
                        <div class="ds-row"><span class="ds-key">Bank Name</span><span class="ds-val" id="bank-name">— </span></div>
                        <div class="ds-row"><span class="ds-key">Account Number (USD)</span><span class="ds-val blue" id="account-number" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                        <div class="ds-row"><span class="ds-key">Account Number (AFS)</span><span class="ds-val" id="afs-account-number" style="font-family:'JetBrains Mono',monospace;color:#0f766e;">— </span></div>
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
                <h5 class="modal-title"><i class="feather icon-list" style="margin-right:8px;"></i>Transactions —  <span id="account-name-header"></span></h5>
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

function getTransactionsLoadingMarkup() {
    return '<div class="txn-loading"><div class="spinner"></div><p style="color:var(--text-sub);font-size:13px;margin:0;">Loading transactions...</p></div>';
}

function loadTransactions(accountId, page = 1) {
    const content = document.getElementById('transactionsContent');

    content.innerHTML = getTransactionsLoadingMarkup();

    fetch('get_account_transactions.php?account_id=' + encodeURIComponent(accountId) + '&page=' + encodeURIComponent(page))
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = '<div style="padding:20px;color:var(--red);">Error loading transactions: ' + error.message + '</div>';
        });
}

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
        const a = JSON.parse(this.getAttribute('data-account'));

        document.getElementById('usd-balance').textContent    = '$'    + parseFloat(a.usd_balance    || 0).toFixed(2);
document.getElementById('afs-balance').textContent    = 'AFS ' + parseFloat(a.afs_balance    || 0).toFixed(2);
document.getElementById('euro-balance').textContent   = '€'    + parseFloat(a.euro_balance   || 0).toFixed(2);
document.getElementById('darham-balance').textContent = 'د.إ ' + parseFloat(a.darham_balance || 0).toFixed(2);

document.getElementById('account-name').textContent   = a.name || '—';
document.getElementById('account-type').textContent   = (a.account_type || '').charAt(0).toUpperCase() + (a.account_type || '').slice(1);
document.getElementById('account-status').textContent = (a.status || '').charAt(0).toUpperCase() + (a.status || '').slice(1);
document.getElementById('last-updated').textContent  = a.last_updated || 'N/A';
document.getElementById('created-at').textContent    = a.created_at   || 'N/A';

        const cr  = parseFloat(a.total_credits||0);
        const dr  = parseFloat(a.total_debits ||0);
        const net = cr - dr;
        document.getElementById('total-transactions').textContent = parseInt(a.transaction_count||0).toLocaleString();
        document.getElementById('total-credits').textContent = '+$' + cr.toFixed(2);
        document.getElementById('total-debits').textContent  = '-$' + dr.toFixed(2);
        const nEl = document.getElementById('net-flow');
        nEl.textContent = (net >= 0 ? '+' : '') + '$' + net.toFixed(2);
        nEl.className = 'ds-val ' + (net >= 0 ? 'green' : 'red');

        document.getElementById('bank-name').textContent           = a.bank_name                 || 'N/A';
        document.getElementById('account-number').textContent      = a.bank_account_number       || 'N/A';
        document.getElementById('afs-account-number').textContent  = a.bank_account_afs_number   || 'N/A';

        switchTab('summary', document.querySelector('.modal-tab'));
        $('#detailsModal').modal('show');
    });
});

document.querySelectorAll('.view-transactions').forEach(btn => {
    btn.addEventListener('click', function() {
        const id   = this.getAttribute('data-account-id');
        const name = this.getAttribute('data-account-name');
        document.getElementById('account-name-header').textContent = name;
        $('#transactionsModal').modal('show');
        loadTransactions(id, 1);
    });
});
</script>
