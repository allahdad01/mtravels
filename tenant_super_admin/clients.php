<?php
include 'header.php';

$tenant_id = $_SESSION['tenant_id'];
$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 25;
$offset = ($page - 1) * $results_per_page;

$query = "SELECT c.*,
    COUNT(ct.id) as transaction_count,
    COALESCE(SUM(CASE WHEN ct.type='credit' THEN ct.amount ELSE 0 END),0) as total_credits,
    COALESCE(SUM(CASE WHEN ct.type='debit'  THEN ct.amount ELSE 0 END),0) as total_debits
FROM clients c
LEFT JOIN client_transactions ct ON c.id = ct.client_id
WHERE c.tenant_id = ? AND c.status = 'active'";

$params = [$tenant_id];
if (!empty($search)) {
    $query .= " AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $sp = "%$search%"; $params = array_merge($params, [$sp,$sp,$sp]);
}
$query .= " GROUP BY c.id ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
$params[] = $results_per_page; $params[] = $offset;

$stmt = $pdo->prepare($query); $stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cq = "SELECT COUNT(*) as total FROM clients WHERE tenant_id = ? AND status = 'active'";
$cp = [$tenant_id];
if (!empty($search)) {
    $cq .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $sp = "%$search%"; $cp = array_merge($cp, [$sp,$sp,$sp]);
}
$cs = $pdo->prepare($cq); $cs->execute($cp);
$total_clients = $cs->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages   = max(1, ceil($total_clients / $results_per_page));

$ss = $pdo->prepare("SELECT COUNT(*) as total_clients,
    SUM(usd_balance) as total_usd_balance, SUM(afs_balance) as total_afs_balance,
    SUM(CASE WHEN usd_balance > 0 THEN usd_balance ELSE 0 END) as positive_usd_balance,
    SUM(CASE WHEN afs_balance > 0 THEN afs_balance ELSE 0 END) as positive_afs_balance,
    SUM(CASE WHEN usd_balance < 0 THEN ABS(usd_balance) ELSE 0 END) as negative_usd_balance,
    SUM(CASE WHEN afs_balance < 0 THEN ABS(afs_balance) ELSE 0 END) as negative_afs_balance
FROM clients WHERE tenant_id = ? AND status = 'active'");
$ss->execute([$tenant_id]);
$summary = $ss->fetch(PDO::FETCH_ASSOC);

$from = min(($page - 1) * $results_per_page + 1, $total_clients);
$to   = min($page * $results_per_page, $total_clients);
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root {
    --surface:#f4f7fe; --card-bg:#ffffff; --border:#e8edf5;
    --text-main:#1a2340; --text-sub:#6b7a99;
    --green:#22c55e; --red:#ef4444; --blue:#4099ff; --teal:#2ed8b6;
    /* Client identity: green â†’ teal (preserving the original .bg-success green) */
    --c1:#059669; --c2:#0d9488;
    --radius:14px; --shadow:0 2px 12px rgba(64,153,255,0.08);
}
*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}

/* Header â€” emerald â†’ teal (client green identity) */
.dash-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 32px rgba(5,150,105,0.25);position:relative;overflow:hidden}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.06'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;letter-spacing:-0.4px;position:relative}
.dash-header p{color:rgba(255,255,255,0.8);margin:0;font-size:13px;position:relative}

/* Two-col layout */
.page-layout{display:grid;grid-template-columns:220px 1fr;gap:20px;align-items:start}
@media(max-width:900px){.page-layout{grid-template-columns:1fr}}

/* Sidebar stat cards */
.stat-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);padding:18px 20px;margin-bottom:14px;text-align:center}
.stat-card:last-child{margin-bottom:0}
.stat-value{font-family:'JetBrains Mono',monospace;font-weight:800;line-height:1;margin-bottom:5px}
.stat-value.big{font-size:32px;color:#059669}
.stat-value.med{font-size:20px}
.stat-value.usd-pos{color:#059669}
.stat-value.afs-pos{color:#0d9488}
.stat-value.neg  {color:var(--red)}
.stat-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub)}
.stat-icon{font-size:18px;margin-bottom:8px;opacity:.6}

/* Cards */
.dash-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.dash-card:last-child{margin-bottom:0}
.dash-card-head{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.dash-card-head h6{font-size:14px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}
.dash-card-head h6 .ico{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0}
.dash-card-body{padding:20px}
.count-badge{background:rgba(5,150,105,.1);color:#059669;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;margin-left:auto}

/* Search */
.search-group{display:flex;gap:8px}
.form-label-custom{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:block;margin-bottom:6px}
.form-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:9px 13px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s}
.form-input:focus{border-color:#059669;background:#fff;box-shadow:0 0 0 3px rgba(5,150,105,.1)}
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

/* Client cell */
.cli-name{font-weight:700;color:var(--text-main);margin-bottom:4px}
.cli-type-pill{display:inline-flex;align-items:center;gap:4px;border-radius:20px;padding:3px 9px;font-size:10px;font-weight:700}
.ctp-agency  {background:rgba(5,150,105,.1);color:#059669}
.ctp-default {background:rgba(107,122,153,.1);color:var(--text-sub)}

/* Contact cell */
.contact-row{display:flex;align-items:center;gap:6px;font-size:13px;margin-bottom:4px;color:var(--text-main)}
.contact-row:last-child{margin-bottom:0}
.contact-row i{font-size:12px;color:var(--text-sub);flex-shrink:0}
.no-contact{font-size:12px;color:var(--text-sub);font-style:italic}

/* Balance cells */
.bal-amount{font-family:'JetBrains Mono',monospace;font-size:14px;font-weight:800;margin-bottom:2px}
.bal-pos-usd{color:#059669} .bal-neg-usd{color:var(--red)}
.bal-pos-afs{color:#0d9488} .bal-neg-afs{color:var(--red)}
.bal-label{font-size:11px;font-weight:600;color:var(--text-sub)}

/* Activity */
.txn-count{font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:700;color:var(--text-main);margin-bottom:3px}
.txn-flows{display:flex;gap:10px;font-size:11px;font-family:'JetBrains Mono',monospace}
.txn-cr{color:#059669;font-weight:700}
.txn-dr{color:var(--red);font-weight:700}

/* Status */
.status-pill{display:inline-flex;align-items:center;gap:4px;border-radius:20px;padding:4px 11px;font-size:11px;font-weight:700}
.sp-active  {background:rgba(34,197,94,.12);color:#166534}
.sp-inactive{background:rgba(107,122,153,.1);color:var(--text-sub)}

/* Action */
.act-btn{width:30px;height:30px;border-radius:8px;border:1.5px solid var(--border);background:var(--card-bg);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;color:var(--text-sub);transition:all .15s}
.act-btn:hover{background:rgba(5,150,105,.08);border-color:#059669;color:#059669}
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
.pag-btn:hover{border-color:#059669;color:#059669;text-decoration:none}
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

/* 2-balance strip */
.modal-summary{display:grid;grid-template-columns:1fr 1fr;background:var(--surface);border-bottom:1px solid var(--border)}
.ms-cell{padding:20px;text-align:center;border-right:1px solid var(--border)}
.ms-cell:last-child{border-right:none}
.ms-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:5px}
.ms-val{font-size:24px;font-weight:800;font-family:'JetBrains Mono',monospace;line-height:1;margin-bottom:3px}
.ms-val.usd-p{color:#059669} .ms-val.usd-n{color:var(--red)}
.ms-val.afs-p{color:#0d9488} .ms-val.afs-n{color:var(--red)}
.ms-sub{font-size:11px;font-weight:600;color:var(--text-sub)}

.modal-body{padding:0}
.modal-tabs{display:flex;gap:6px;padding:16px 24px 0;border-bottom:1px solid var(--border)}
.modal-tab{background:none;border:none;border-bottom:3px solid transparent;padding:8px 16px 12px;font-family:inherit;font-size:13px;font-weight:700;color:var(--text-sub);cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px;margin-bottom:-1px}
.modal-tab.active{color:#059669;border-bottom-color:#059669}
.modal-tab:hover{color:#059669}
.modal-pane{display:none;padding:24px}
.modal-pane.active{display:block}
.detail-section{background:var(--surface);border-radius:12px;padding:18px;margin-bottom:14px}
.detail-section:last-child{margin-bottom:0}
.ds-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:14px}
.ds-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border)}
.ds-row:last-child{border-bottom:none}
.ds-key{font-size:13px;color:var(--text-sub)}
.ds-val{font-size:13px;font-weight:700;color:var(--text-main);text-align:right}
.ds-val.green{color:#059669} .ds-val.red{color:var(--red)} .ds-val.teal{color:#0d9488}

.modal-footer-custom{padding:16px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end}
.btn-close-modal{display:inline-flex;align-items:center;gap:7px;background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border);border-radius:10px;padding:10px 20px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
.btn-close-modal:hover{border-color:var(--text-sub);color:var(--text-main)}

.txn-loading{text-align:center;padding:40px}
.spinner{width:32px;height:32px;border:3px solid var(--border);border-top-color:#059669;border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 12px}
@keyframes spin{to{transform:rotate(360deg)}}

.pcoded-content{padding:20px!important}
.page-header{display:none!important}
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <!-- Header -->
    <div class="dash-header">
        <div>
            <h4><i class="feather icon-users" style="margin-right:8px;"></i>Clients</h4>
            <p>Manage and view all your clients</p>
        </div>
    </div>

    <div class="page-layout">

        <!-- Sidebar stats -->
        <div>
            <div class="stat-card">
                <div class="stat-icon"><i class="feather icon-users" style="color:#059669;font-size:22px;"></i></div>
                <div class="stat-value big"><?= number_format($summary['total_clients'] ?? 0) ?></div>
                <div class="stat-label">Total Clients</div>
            </div>
            <div class="stat-card">
                <div class="stat-label" style="margin-bottom:6px;">Positive USD</div>
                <div class="stat-value med usd-pos">$<?= number_format($summary['positive_usd_balance'] ?? 0, 2) ?></div>
                <div class="stat-label">Credit Balances</div>
            </div>
            <div class="stat-card">
                <div class="stat-label" style="margin-bottom:6px;">Positive AFS</div>
                <div class="stat-value med afs-pos">Ø‹<?= number_format($summary['positive_afs_balance'] ?? 0, 2) ?></div>
                <div class="stat-label">Credit Balances</div>
            </div>
            <div class="stat-card">
                <div class="stat-label" style="margin-bottom:6px;">Outstanding</div>
                <div class="stat-value med neg">$<?= number_format(($summary['negative_usd_balance'] ?? 0) + ($summary['negative_afs_balance'] ?? 0), 2) ?></div>
                <div class="stat-label">Overdue Balances</div>
            </div>
        </div>

        <!-- Main content -->
        <div>
            <!-- Search -->
            <div class="dash-card">
                <div class="dash-card-head">
                    <h6><span class="ico"><i class="feather icon-search"></i></span>Search Clients</h6>
                </div>
                <div class="dash-card-body">
                    <label class="form-label-custom">Search</label>
                    <div class="search-group">
                        <input type="text" id="searchInput" class="form-input" placeholder="Client name, email, or phoneâ€¦" value="<?= htmlspecialchars($search) ?>">
                        <button class="search-btn" id="searchBtn"><i class="feather icon-search"></i>Search</button>
                        <?php if (!empty($search)): ?>
                        <a href="?" class="clear-btn"><i class="feather icon-x"></i>Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="dash-card">
                <div class="dash-card-head">
                    <h6><span class="ico"><i class="feather icon-list"></i></span>Clients List</h6>
                    <span class="count-badge"><?= number_format($total_clients) ?> total</span>
                </div>

                <?php if (!empty($clients)): ?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:44px;">#</th>
                                <th style="width:60px;"></th>
                                <th>Client Info</th>
                                <th>Contact Details</th>
                                <th>USD Balance</th>
                                <th>AFS Balance</th>
                                <th>Activity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $counter = $offset + 1; foreach ($clients as $cli):
                            $usd = floatval($cli['usd_balance']);
                            $afs = floatval($cli['afs_balance']);
                            $usdCls = $usd >= 0 ? 'bal-pos-usd' : 'bal-neg-usd';
                            $afsCls = $afs >= 0 ? 'bal-pos-afs' : 'bal-neg-afs';
                            $isAgency = strtolower($cli['client_type'] ?? '') === 'agency';
                            $statusCls = $cli['status'] === 'active' ? 'sp-active' : 'sp-inactive';
                        ?>
                        <tr>
                            <td class="td-ctr"><?= $counter++ ?></td>
                            <td>
                                <div class="dropdown">
                                    <button class="act-btn dropdown-toggle" type="button" data-toggle="dropdown">
                                        <i class="feather icon-more-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <button class="dropdown-item view-details" data-client='<?= htmlspecialchars(json_encode($cli)) ?>'>
                                            <i class="feather icon-eye" style="color:var(--blue)"></i>View Details
                                        </button>
                                        <button class="dropdown-item view-transactions" data-client-id="<?= $cli['id'] ?>" data-client-name="<?= htmlspecialchars($cli['name']) ?>">
                                            <i class="feather icon-list" style="color:#0d9488"></i>View Transactions
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="cli-name"><?= htmlspecialchars($cli['name']) ?></div>
                                <span class="cli-type-pill <?= $isAgency ? 'ctp-agency' : 'ctp-default' ?>">
                                    <i class="feather <?= $isAgency ? 'icon-briefcase' : 'icon-user' ?>"></i>
                                    <?= ucfirst(htmlspecialchars($cli['client_type'] ?? 'Client')) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($cli['phone'])): ?>
                                <div class="contact-row"><i class="feather icon-phone"></i><?= htmlspecialchars($cli['phone']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($cli['email'])): ?>
                                <div class="contact-row"><i class="feather icon-mail"></i><?= htmlspecialchars($cli['email']) ?></div>
                                <?php endif; ?>
                                <?php if (empty($cli['phone']) && empty($cli['email'])): ?>
                                <div class="no-contact"><i class="feather icon-minus-circle" style="margin-right:4px;font-size:11px;"></i>No contact info</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="bal-amount <?= $usdCls ?>">$<?= number_format($usd, 2) ?></div>
                                <div class="bal-label"><?= $usd >= 0 ? 'Credit' : 'Debit' ?></div>
                            </td>
                            <td>
                                <div class="bal-amount <?= $afsCls ?>">Ø‹<?= number_format($afs, 2) ?></div>
                                <div class="bal-label"><?= $afs >= 0 ? 'Credit' : 'Debit' ?></div>
                            </td>
                            <td>
                                <div class="txn-count"><?= number_format($cli['transaction_count']) ?> txns</div>
                                <div class="txn-flows">
                                    <span class="txn-cr">C $<?= number_format($cli['total_credits'], 0) ?></span>
                                    <span class="txn-dr">D $<?= number_format($cli['total_debits'], 0) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="status-pill <?= $statusCls ?>"><?= ucfirst(htmlspecialchars($cli['status'])) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pag-wrap">
                    <div class="pag-info">Showing <?= $from ?>â€“<?= $to ?> of <?= number_format($total_clients) ?> clients</div>
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
                    <i class="feather icon-users"></i>
                    <p>No clients found<?= !empty($search) ? ' for "'.$search.'"' : '' ?>.</p>
                </div>
                <?php endif; ?>
            </div>
        </div><!-- /main col -->

    </div><!-- /page-layout -->

</div>
</div>

<!-- Client Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-user" style="margin-right:8px;"></i>Client Details</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <!-- Dual-balance strip -->
            <div class="modal-summary">
                <div class="ms-cell">
                    <div class="ms-label">USD Balance</div>
                    <div class="ms-val" id="modal-usd-bal">â€”</div>
                    <div class="ms-sub" id="modal-usd-dir">â€”</div>
                </div>
                <div class="ms-cell">
                    <div class="ms-label">AFS Balance</div>
                    <div class="ms-val" id="modal-afs-bal">â€”</div>
                    <div class="ms-sub" id="modal-afs-dir">â€”</div>
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
                            <div class="ds-title">Client Information</div>
                            <div class="ds-row"><span class="ds-key">Client Name</span><span class="ds-val" id="client-name">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Type</span><span class="ds-val" id="client-type">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Status</span><span class="ds-val" id="client-status">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Created At</span><span class="ds-val" id="created-at" style="font-family:'JetBrains Mono',monospace;font-size:11px;">â€”</span></div>
                        </div>
                        <div class="detail-section">
                            <div class="ds-title">Financial Summary</div>
                            <div class="ds-row"><span class="ds-key">Total Credits</span><span class="ds-val green" id="total-credits" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Total Debits</span><span class="ds-val red" id="total-debits" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                            <div class="ds-row"><span class="ds-key">Net Position</span><span class="ds-val" id="net-position" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                        </div>
                    </div>
                </div>

                <div class="modal-pane" id="pane-contact">
                    <div class="detail-section">
                        <div class="ds-title">Contact Information</div>
                        <div class="ds-row"><span class="ds-key">Phone</span><span class="ds-val" id="contact-phone" style="font-family:'JetBrains Mono',monospace;">â€”</span></div>
                        <div class="ds-row"><span class="ds-key">Email</span><span class="ds-val" id="contact-email">â€”</span></div>
                        <div class="ds-row"><span class="ds-key">Address</span><span class="ds-val" id="contact-address">â€”</span></div>
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
                <h5 class="modal-title"><i class="feather icon-list" style="margin-right:8px;"></i>Transactions â€” <span id="client-name-header"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:0;">
                <div id="transactionsContent">
                    <div class="txn-loading">
                        <div class="spinner"></div>
                        <p style="color:var(--text-sub);font-size:13px;margin:0;">Loading transactionsâ€¦</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-close-modal" data-dismiss="modal"><i class="feather icon-x"></i>Close</button>
            </div>
        </div>
    </div>
</div>

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
        const c   = JSON.parse(this.getAttribute('data-client'));
        const usd = parseFloat(c.usd_balance || 0);
        const afs = parseFloat(c.afs_balance || 0);

        const uBalEl = document.getElementById('modal-usd-bal');
        uBalEl.textContent = '$' + usd.toFixed(2);
        uBalEl.className = 'ms-val ' + (usd >= 0 ? 'usd-p' : 'usd-n');
        document.getElementById('modal-usd-dir').textContent = usd >= 0 ? 'Credit balance' : 'Debit balance';

        const aBalEl = document.getElementById('modal-afs-bal');
        aBalEl.textContent = 'Ø‹' + afs.toFixed(2);
        aBalEl.className = 'ms-val ' + (afs >= 0 ? 'afs-p' : 'afs-n');
        document.getElementById('modal-afs-dir').textContent = afs >= 0 ? 'Credit balance' : 'Debit balance';

        document.getElementById('client-name').textContent  = c.name || 'â€”';
        document.getElementById('client-type').textContent  = (c.client_type||'').charAt(0).toUpperCase() + (c.client_type||'').slice(1);
        document.getElementById('client-status').textContent = (c.status||'').charAt(0).toUpperCase() + (c.status||'').slice(1);
        document.getElementById('created-at').textContent   = c.created_at || 'N/A';

        const cr  = parseFloat(c.total_credits || 0);
        const dr  = parseFloat(c.total_debits  || 0);
        const net = cr - dr;
        document.getElementById('total-credits').textContent = '$' + cr.toFixed(2);
        document.getElementById('total-debits').textContent  = '$' + dr.toFixed(2);
        const nEl = document.getElementById('net-position');
        nEl.textContent = (net >= 0 ? '+' : '') + '$' + net.toFixed(2);
        nEl.className = 'ds-val ' + (net >= 0 ? 'green' : 'red');

        document.getElementById('contact-phone').textContent   = c.phone   || 'N/A';
        document.getElementById('contact-email').textContent   = c.email   || 'N/A';
        document.getElementById('contact-address').textContent = c.address || 'N/A';

        switchTab('summary', document.querySelector('.modal-tab'));
        $('#detailsModal').modal('show');
    });
});

document.querySelectorAll('.view-transactions').forEach(btn => {
    btn.addEventListener('click', function() {
        const id   = this.getAttribute('data-client-id');
        const name = this.getAttribute('data-client-name');
        document.getElementById('client-name-header').textContent = name;
        document.getElementById('transactionsContent').innerHTML =
            '<div class="txn-loading"><div class="spinner"></div><p style="color:var(--text-sub);font-size:13px;margin:0;">Loading transactionsâ€¦</p></div>';
        $('#transactionsModal').modal('show');
        fetch('get_client_transactions.php?client_id=' + id)
            .then(r => r.text())
            .then(html => { document.getElementById('transactionsContent').innerHTML = html; })
            .catch(err => {
                document.getElementById('transactionsContent').innerHTML =
                    '<div style="padding:20px;color:var(--red);">Error: ' + err.message + '</div>';
            });
    });
});
</script>

<?php include 'footer.php'; ?>
