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

$query = "SELECT e.*, ec.name as category_name, b.name as branch_name
          FROM expenses e
          LEFT JOIN expense_categories ec ON e.category_id = ec.id
          LEFT JOIN branches b ON e.branch_id = b.id
          WHERE e.tenant_id = ?";

$params = [$tenant_id];
if ($selected_branch !== 'all') { $query .= " AND e.branch_id = ?"; $params[] = $selected_branch; }
if (!empty($search)) {
    $query .= " AND (e.description LIKE ? OR ec.name LIKE ?)";
    $sp = "%$search%"; $params = array_merge($params, [$sp,$sp]);
}
$query .= " ORDER BY e.date DESC LIMIT ? OFFSET ?";
$params[] = $results_per_page; $params[] = $offset;

$stmt = $pdo->prepare($query); $stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cq = "SELECT COUNT(*) as total FROM expenses e
       LEFT JOIN expense_categories ec ON e.category_id = ec.id
       WHERE e.tenant_id = ?";
$cp = [$tenant_id];
if ($selected_branch !== 'all') { $cq .= " AND e.branch_id = ?"; $cp[] = $selected_branch; }
if (!empty($search)) {
    $cq .= " AND (e.description LIKE ? OR ec.name LIKE ?)";
    $sp = "%$search%"; $cp = array_merge($cp, [$sp,$sp]);
}
$cs = $pdo->prepare($cq); $cs->execute($cp);
$total_expenses = $cs->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages    = max(1, ceil($total_expenses / $results_per_page));

$sq = "SELECT COUNT(*) as total_expenses,
    SUM(CASE WHEN currency='USD' THEN amount ELSE 0 END) as total_usd_expenses,
    SUM(CASE WHEN currency='AFS' THEN amount ELSE 0 END) as total_afs_expenses,
    AVG(amount) as avg_expense_amount
FROM expenses WHERE tenant_id = ?";
$sp2 = [$tenant_id];
if ($selected_branch !== 'all') { $sq .= " AND branch_id = ?"; $sp2[] = $selected_branch; }
$ss = $pdo->prepare($sq); $ss->execute($sp2);
$summary = $ss->fetch(PDO::FETCH_ASSOC);

$bs = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND status='active' ORDER BY name");
$bs->execute([$tenant_id]);
$branches = $bs->fetchAll(PDO::FETCH_ASSOC);

$from = min(($page - 1) * $results_per_page + 1, $total_expenses);
$to   = min($page * $results_per_page, $total_expenses);
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

/* Header —  rose â†’ orange for expenses/spending */
.dash-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 32px rgba(190,18,60,0.25);position:relative;overflow:hidden}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.06'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;letter-spacing:-0.4px;position:relative}
.dash-header p{color:rgba(255,255,255,0.8);margin:0;font-size:13px;position:relative}

/* Summary stat cards */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
@media(max-width:900px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:500px){.stat-grid{grid-template-columns:1fr}}
.stat-card{border-radius:var(--radius);padding:20px 22px;color:#fff;position:relative;overflow:hidden}
.stat-card::after{content:'';position:absolute;right:-10px;bottom:-10px;width:70px;height:70px;border-radius:50%;background:rgba(255,255,255,0.1)}
.stat-card.total{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(190,18,60,0.3)}
.stat-card.usd  {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(180,83,9,0.3)}
.stat-card.afs  {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(234,88,12,0.3)}
.stat-card.avg  {background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);box-shadow:0 6px 20px rgba(124,58,237,0.3)}
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
.count-badge{background:rgba(190,18,60,.1);color:#be123c;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;margin-left:auto}

/* Search */
.search-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:end}
@media(max-width:700px){.search-row{grid-template-columns:1fr}}
.form-label-custom{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:block;margin-bottom:6px}
.search-group{display:flex;gap:8px}
.form-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:9px 13px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s}
.form-input:focus{border-color:#be123c;background:#fff;box-shadow:0 0 0 3px rgba(190,18,60,.1)}
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

/* Expense detail cell */
.exp-desc{font-weight:700;color:var(--text-main);margin-bottom:3px}
.exp-receipt{font-size:11px;color:var(--text-sub);font-family:'JetBrains Mono',monospace;display:flex;align-items:center;gap:4px}

/* Category + amount cell */
.cat-badge{display:inline-flex;align-items:center;gap:5px;background:rgba(190,18,60,.08);color:#be123c;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;margin-bottom:5px}
.exp-amount{font-family:'JetBrains Mono',monospace;font-size:14px;font-weight:800}
.amt-usd{color:#b45309}
.amt-afs{color:#ea580c}

/* Branch */
.branch-pill{display:inline-flex;align-items:center;gap:5px;background:rgba(64,153,255,.08);color:var(--blue);border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700}

/* Date cell */
.exp-date{font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:700;color:var(--text-main)}
.exp-time{font-size:11px;color:var(--text-sub);margin-top:2px;font-family:'JetBrains Mono',monospace}

/* Action */
.act-btn{width:30px;height:30px;border-radius:8px;border:1.5px solid var(--border);background:var(--card-bg);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;color:var(--text-sub);transition:all .15s}
.act-btn:hover{background:rgba(190,18,60,.08);border-color:#be123c;color:#be123c}
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
.pag-btn:hover{border-color:#be123c;color:#be123c;text-decoration:none}
.pag-btn.active{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-color:transparent;color:#fff}
.pag-btn.disabled{opacity:.4;pointer-events:none}
.pag-dots{display:flex;align-items:center;padding:0 4px;color:var(--text-sub);font-size:13px}

/* Modal */
.modal-content{border:none;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.18);font-family:inherit}
.modal-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border-radius:16px 16px 0 0;border:none;padding:18px 24px}
.modal-header .modal-title{font-weight:700;font-size:15px}
.modal-header .close{color:#fff;opacity:.8;font-size:22px}
.modal-header .close:hover{opacity:1}

/* Amount + date strip */
.modal-summary{display:grid;grid-template-columns:1fr 1fr;background:var(--surface);border-bottom:1px solid var(--border)}
.ms-cell{padding:20px;text-align:center;border-right:1px solid var(--border)}
.ms-cell:last-child{border-right:none}
.ms-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:6px}
.ms-val{font-size:24px;font-weight:800;font-family:'JetBrains Mono',monospace;line-height:1;margin-bottom:4px}
.ms-val.rose  {color:#be123c}
.ms-val.orange{color:#ea580c}
.ms-sub{font-size:11px;font-weight:600;color:var(--text-sub)}

.modal-body{padding:0}
.modal-tabs{display:flex;gap:6px;padding:16px 24px 0;border-bottom:1px solid var(--border)}
.modal-tab{background:none;border:none;border-bottom:3px solid transparent;padding:8px 16px 12px;font-family:inherit;font-size:13px;font-weight:700;color:var(--text-sub);cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px;margin-bottom:-1px}
.modal-tab.active{color:#be123c;border-bottom-color:#be123c}
.modal-tab:hover{color:#be123c}
.modal-pane{display:none;padding:24px}
.modal-pane.active{display:block}
.detail-section{background:var(--surface);border-radius:12px;padding:18px;margin-bottom:14px}
.detail-section:last-child{margin-bottom:0}
.ds-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:14px}
.ds-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border)}
.ds-row:last-child{border-bottom:none}
.ds-key{font-size:13px;color:var(--text-sub)}
.ds-val{font-size:13px;font-weight:700;color:var(--text-main);text-align:right}
.ds-val.rose  {color:#be123c}
.ds-val.orange{color:#ea580c}

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
            <h4><i class="feather icon-file-text" style="margin-right:8px;"></i>Expenses</h4>
            <p>Manage and track your expenses</p>
        </div>
    </div>

    <!-- Summary cards -->
    <div class="stat-grid">
        <div class="stat-card total">
            <div class="stat-label">Total Expenses</div>
            <div class="stat-value"><?= number_format($summary['total_expenses'] ?? 0) ?></div>
            <i class="feather icon-file-text stat-icon"></i>
        </div>
        <div class="stat-card usd">
            <div class="stat-label">Total USD</div>
            <div class="stat-value">$<?= number_format($summary['total_usd_expenses'] ?? 0, 2) ?></div>
            <i class="feather icon-dollar-sign stat-icon"></i>
        </div>
        <div class="stat-card afs">
            <div class="stat-label">Total AFS</div>
            <div class="stat-value">AFS <?= number_format($summary['total_afs_expenses'] ?? 0, 2) ?></div>
            <i class="feather icon-credit-card stat-icon"></i>
        </div>
        <div class="stat-card avg">
            <div class="stat-label">Avg Expense</div>
            <div class="stat-value">$<?= number_format($summary['avg_expense_amount'] ?? 0, 2) ?></div>
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
                    <label class="form-label-custom">Search</label>
                    <div class="search-group">
                        <input type="text" id="searchInput" class="form-input" placeholder="Description or category..." value="<?= htmlspecialchars($search) ?>">
                        <button class="search-btn" id="searchBtn"><i class="feather icon-search"></i>Search</button>
                        <?php if (!empty($search)): ?>
                        <a href="?branch=<?= urlencode($selected_branch) ?>" class="clear-btn"><i class="feather icon-x"></i>Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="dash-card">
        <div class="dash-card-head">
            <h6><span class="ico"><i class="feather icon-list"></i></span>Expenses List</h6>
            <span class="count-badge"><?= number_format($total_expenses) ?> total</span>
        </div>

        <?php if (!empty($expenses)): ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:44px;">#</th>
                        <th style="width:60px;"></th>
                        <th>Expense Details</th>
                        <th>Category &amp; Amount</th>
                        <th>Branch</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php $counter = $offset + 1; foreach ($expenses as $exp):
                    $isUSD   = $exp['currency'] === 'USD';
                    $amtCls  = $isUSD ? 'amt-usd' : 'amt-afs';
                    $curr    = $isUSD ? '$' : 'AFS ';
                ?>
                <tr>
                    <td class="td-ctr"><?= $counter++ ?></td>
                    <td>
                        <div class="dropdown">
                            <button class="act-btn dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="feather icon-more-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <button class="dropdown-item view-details" data-expense='<?= htmlspecialchars(json_encode($exp)) ?>'>
                                    <i class="feather icon-eye" style="color:var(--blue)"></i>View Details
                                </button>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="exp-desc"><?= htmlspecialchars($exp['description']) ?></div>
                        <?php if (!empty($exp['receipt'])): ?>
                        <div class="exp-receipt"><i class="feather icon-hash"></i><?= htmlspecialchars($exp['receipt']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="cat-badge"><i class="feather icon-tag"></i><?= htmlspecialchars($exp['category_name'] ?? 'Uncategorized') ?></div>
                        <div class="exp-amount <?= $amtCls ?>"><?= $curr ?><?= number_format($exp['amount'], 2) ?></div>
                    </td>
                    <td>
                        <span class="branch-pill"><i class="feather icon-git-branch"></i><?= htmlspecialchars($exp['branch_name'] ?? 'N/A') ?></span>
                    </td>
                    <td>
                        <div class="exp-date"><?= date('d/m/Y', strtotime($exp['date'])) ?></div>
                        <div class="exp-time"><?= date('H:i', strtotime($exp['created_at'])) ?></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pag-wrap">
            <div class="pag-info">Showing <?= $from ?>â€“<?= $to ?> of <?= number_format($total_expenses) ?> expenses</div>
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
            <i class="feather icon-file-text"></i>
            <p>No expenses found<?= !empty($search) ? ' for "'.$search.'"' : '' ?>.</p>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- Expense Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="feather icon-file-text" style="margin-right:8px;"></i>Expense Details</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <!-- Amount + date strip -->
            <div class="modal-summary">
                <div class="ms-cell">
                    <div class="ms-label">Expense Amount</div>
                    <div class="ms-val rose" id="modal-amount">— </div>
                    <div class="ms-sub" id="modal-currency">— </div>
                </div>
                <div class="ms-cell">
                    <div class="ms-label">Expense Date</div>
                    <div class="ms-val orange" id="modal-date" style="font-size:18px;">— </div>
                    <div class="ms-sub" id="modal-created">— </div>
                </div>
            </div>

            <div class="modal-body">
                <div class="modal-tabs">
                    <button class="modal-tab active" onclick="switchTab('summary',this)"><i class="feather icon-info"></i>Summary</button>
                    <button class="modal-tab" onclick="switchTab('additional',this)"><i class="feather icon-file"></i>Additional Info</button>
                </div>

                <div class="modal-pane active" id="pane-summary">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div class="detail-section">
                            <div class="ds-title">Expense Information</div>
                            <div class="ds-row"><span class="ds-key">Description</span><span class="ds-val" id="exp-description">— </span></div>
                            <div class="ds-row"><span class="ds-key">Category</span><span class="ds-val rose" id="exp-category">— </span></div>
                            <div class="ds-row"><span class="ds-key">Branch</span><span class="ds-val" id="exp-branch">— </span></div>
                            <div class="ds-row"><span class="ds-key">Created By</span><span class="ds-val" id="exp-created-by">— </span></div>
                        </div>
                        <div class="detail-section">
                            <div class="ds-title">Financial Details</div>
                            <div class="ds-row"><span class="ds-key">Amount</span><span class="ds-val orange" id="exp-amount-detail" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                            <div class="ds-row"><span class="ds-key">Currency</span><span class="ds-val" id="exp-currency-detail" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                            <div class="ds-row"><span class="ds-key">Main Account</span><span class="ds-val" id="exp-main-account">— </span></div>
                            <div class="ds-row"><span class="ds-key">Receipt No.</span><span class="ds-val" id="exp-receipt" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                        </div>
                    </div>
                </div>

                <div class="modal-pane" id="pane-additional">
                    <div class="detail-section">
                        <div class="ds-title">Additional Information</div>
                        <div class="ds-row"><span class="ds-key">Expense ID</span><span class="ds-val" id="exp-id" style="font-family:'JetBrains Mono',monospace;">— </span></div>
                        <div class="ds-row"><span class="ds-key">Created At</span><span class="ds-val" id="exp-created-at" style="font-family:'JetBrains Mono',monospace;font-size:11px;">— </span></div>
                        <div class="ds-row"><span class="ds-key">Updated At</span><span class="ds-val" id="exp-updated-at" style="font-family:'JetBrains Mono',monospace;font-size:11px;">— </span></div>
                        <div class="ds-row"><span class="ds-key">Receipt File</span><span class="ds-val" id="exp-receipt-file">— </span></div>
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
        const e    = JSON.parse(this.getAttribute('data-expense'));
        const curr = e.currency === 'USD' ? '$' : 'AFS ';
        const amt  = parseFloat(e.amount || 0).toFixed(2);

        document.getElementById('modal-amount').textContent   = curr + amt;
        document.getElementById('modal-currency').textContent = e.currency || '— ';
        document.getElementById('modal-date').textContent     = e.date ? new Date(e.date).toLocaleDateString() : '— ';
        document.getElementById('modal-created').textContent  = e.created_at ? 'Created ' + new Date(e.created_at).toLocaleString() : '— ';

        document.getElementById('exp-description').textContent  = e.description    || '— ';
        document.getElementById('exp-category').textContent     = e.category_name  || 'Uncategorized';
        document.getElementById('exp-branch').textContent       = e.branch_name    || '— ';
        document.getElementById('exp-created-by').textContent   = e.created_by_name || '— ';

        document.getElementById('exp-amount-detail').textContent  = curr + amt;
        document.getElementById('exp-currency-detail').textContent = e.currency || '— ';
        document.getElementById('exp-main-account').textContent   = e.main_account_name || '— ';
        document.getElementById('exp-receipt').textContent        = e.receipt || '— ';

        document.getElementById('exp-id').textContent          = e.id || '— ';
        document.getElementById('exp-created-at').textContent  = e.created_at ? new Date(e.created_at).toLocaleString() : '— ';
        document.getElementById('exp-updated-at').textContent  = e.updated_at ? new Date(e.updated_at).toLocaleString() : '— ';
        document.getElementById('exp-receipt-file').textContent = e.receipt_file || 'No file attached';

        switchTab('summary', document.querySelector('.modal-tab'));
        $('#detailsModal').modal('show');
    });
});
</script>
