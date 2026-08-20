<?php
session_start();

require_once 'security.php';
enforce_auth();
require_permission('hr.salary');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id   = $_SESSION['user_id'];

require_once "../includes/db.php";

$search          = isset($_GET['search']) ? trim($_GET['search']) : '';
$month_filter    = isset($_GET['month'])    ? $_GET['month']    : '';
$type_filter     = isset($_GET['type'])     ? $_GET['type']     : 'all';
$currency_filter = isset($_GET['currency']) ? $_GET['currency'] : 'all';

$query  = "SELECT sp.*, u.name as employee_name, ma.name as account_name FROM salary_payments sp JOIN users u ON sp.user_id=u.id JOIN main_account ma ON sp.main_account_id=ma.id WHERE sp.tenant_id=? AND sp.branch_id=? AND sp.user_id=?";
$params = [$tenant_id, $branch_id, $user_id];

if (!empty($search)) {
    $query .= " AND (sp.receipt LIKE ? OR sp.description LIKE ?)";
    $sp = "%$search%";
    $params[] = $sp; $params[] = $sp;
}
if (!empty($month_filter)) {
    $query .= " AND DATE_FORMAT(sp.payment_for_month,'%Y-%m')=?";
    $params[] = $month_filter;
}
if ($type_filter !== 'all')     { $query .= " AND sp.payment_type=?"; $params[] = $type_filter; }
if ($currency_filter !== 'all') { $query .= " AND sp.currency=?";     $params[] = $currency_filter; }

$query .= " ORDER BY sp.payment_date DESC, sp.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_usd = $total_afs = 0;
foreach ($payments as $p) {
    if ($p['currency'] === 'USD') $total_usd += $p['amount'];
    else                          $total_afs += $p['amount'];
}

// Distinct months for filter
$ms = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(payment_for_month,'%Y-%m') as month_value, DATE_FORMAT(payment_for_month,'%M %Y') as month_label FROM salary_payments WHERE tenant_id=? AND branch_id=? AND user_id=? ORDER BY month_value DESC");
$ms->execute([$tenant_id, $branch_id, $user_id]);
$months = $ms->fetchAll(PDO::FETCH_ASSOC);

$has_filters = !empty($search) || !empty($month_filter) || $type_filter !== 'all' || $currency_filter !== 'all';

include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Salary Payments</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --ink:       #0f1117;
    --surface:   #ffffff;
    --muted:     #f4f5f7;
    --border:    #e8eaed;
    --accent:    #3d6cff;
    --accent2:   #00d9a6;
    --warn:      #ff9f43;
    --danger:    #ff4757;
    --text-sub:  #6b7280;
    --radius:    12px;
    --shadow-sm: 0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
    --shadow-md: 0 4px 16px rgba(0,0,0,.08);
    --shadow-lg: 0 12px 40px rgba(0,0,0,.12);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:#f0f2f5;color:var(--ink)}

.sp-page{padding:28px 32px;max-width:1300px}

/* Hero */
.page-hero{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:28px;flex-wrap:wrap;gap:16px}
.page-hero-title{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;color:var(--ink);letter-spacing:-.5px;line-height:1.1}
.page-hero-subtitle{font-size:13px;color:var(--text-sub);margin-top:4px}
.hero-actions{display:flex;gap:10px;flex-wrap:wrap}

/* Stat cards */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px}
.stat-card{background:var(--surface);border-radius:var(--radius);padding:20px 22px;box-shadow:var(--shadow-sm);border:1px solid var(--border);position:relative;overflow:hidden;transition:box-shadow .2s}
.stat-card:hover{box-shadow:var(--shadow-md)}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.stat-card.blue::before{background:var(--accent)}
.stat-card.green::before{background:var(--accent2)}
.stat-card.yellow::before{background:var(--warn)}
.stat-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--text-sub);margin-bottom:8px}
.stat-value{font-family:'Syne',sans-serif;font-size:24px;font-weight:700;color:var(--ink);line-height:1}
.stat-icon{position:absolute;right:18px;top:50%;transform:translateY(-50%);font-size:28px;opacity:.1}

/* Card */
.sm-card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow-sm);overflow:hidden;margin-bottom:24px}
.sm-card-header{display:flex;justify-content:space-between;align-items:center;padding:18px 24px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:12px}
.sm-card-title{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:8px}
.sm-card-title svg{color:var(--accent);flex-shrink:0}
.sm-card-body{padding:24px}

/* Filter panel */
.filter-panel{background:linear-gradient(135deg,#eef2ff,#f0fdf9);border:1px solid #dbe4ff;border-radius:var(--radius);padding:20px 24px;margin-bottom:24px}
.filter-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:14px;align-items:end}
.filter-title{font-family:'Syne',sans-serif;font-size:12px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px;display:flex;align-items:center;gap:6px}
.field-label{font-size:11px;font-weight:600;color:var(--text-sub);text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:5px}
.field-control{height:40px;padding:0 12px;font-size:13.5px;font-family:'DM Sans',sans-serif;color:var(--ink);background:var(--surface);border:1.5px solid var(--border);border-radius:8px;outline:none;transition:border-color .18s,box-shadow .18s;width:100%}
.field-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(61,108,255,.12)}

/* Buttons */
.btn-sm-primary{display:inline-flex;align-items:center;gap:6px;background:var(--accent);color:#fff;border:none;border-radius:8px;padding:9px 16px;font-size:13px;font-weight:500;font-family:'DM Sans',sans-serif;cursor:pointer;transition:background .18s,transform .12s;text-decoration:none;white-space:nowrap;height:40px}
.btn-sm-primary:hover{background:#2d5be0;color:#fff;transform:translateY(-1px)}
.btn-sm-ghost{display:inline-flex;align-items:center;gap:6px;background:transparent;color:var(--text-sub);border:1px solid var(--border);border-radius:8px;padding:9px 16px;font-size:13px;font-weight:500;font-family:'DM Sans',sans-serif;cursor:pointer;transition:all .18s;text-decoration:none;white-space:nowrap}
.btn-sm-ghost:hover{background:var(--muted);color:var(--ink);border-color:#d0d5dd}
.btn-clear-filter{display:inline-flex;align-items:center;gap:5px;font-size:12px;color:var(--accent);background:none;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;padding:0;text-decoration:underline;text-underline-offset:2px;margin-top:10px}

/* Active filter tags */
.filter-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.filter-tag{display:inline-flex;align-items:center;gap:5px;background:rgba(61,108,255,.1);color:var(--accent);border-radius:50px;padding:3px 10px;font-size:12px;font-weight:500}
.filter-tag a{color:var(--accent);text-decoration:none;font-weight:700;margin-left:2px;opacity:.7}
.filter-tag a:hover{opacity:1}

/* Table */
.sm-table{width:100%;border-collapse:separate;border-spacing:0;font-size:13.5px}
.sm-table thead th{background:var(--muted);color:var(--text-sub);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;padding:11px 16px;border-bottom:1px solid var(--border);white-space:nowrap}
.sm-table thead th:first-child{border-radius:8px 0 0 0}
.sm-table thead th:last-child{border-radius:0 8px 0 0}
.sm-table tbody tr{transition:background .15s}
.sm-table tbody tr:hover td{background:#f8f9ff}
.sm-table tbody td{padding:13px 16px;border-bottom:1px solid var(--border);vertical-align:middle}
.sm-table tbody tr:last-child td{border-bottom:none}

/* Amount display */
.amount-val{font-family:'Syne',sans-serif;font-weight:700;font-size:14px;color:var(--ink)}
.currency-tag{font-size:11px;font-weight:600;color:var(--text-sub);margin-left:3px}

/* Receipt */
.receipt-code{font-family:monospace;font-size:11.5px;color:var(--text-sub);background:var(--muted);padding:2px 7px;border-radius:5px;display:inline-block}

/* Custom Salary Type Badges */
.salary-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: relative;
    transition: all 0.2s ease;
}

.salary-badge::before {
    content: '';
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

.salary-regular {
    background: linear-gradient(135deg, #eef2ff, #f0fdf9);
    color: #3d5cbb;
    border: 1.5px solid #3d6cff;
}

.salary-regular::before {
    background: #3d6cff;
}

.salary-bonus {
    background: linear-gradient(135deg, #d4f8f0, #e8fdf9);
    color: #008866;
    border: 1.5px solid #00d9a6;
}

.salary-bonus::before {
    background: #00d9a6;
}

.salary-advance {
    background: linear-gradient(135deg, #fff5e6, #fffaf0);
    color: #cc7a00;
    border: 1.5px solid #ff9f43;
}

.salary-advance::before {
    background: #ff9f43;
}

.salary-other {
    background: linear-gradient(135deg, #f5f5f5, #fafafa);
    color: #555;
    border: 1.5px solid #d0d0d0;
}

.salary-other::before {
    background: #888;
}

/* Description cell */
.desc-cell{max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text-sub);font-size:13px}

/* Empty state */
.empty-state{text-align:center;padding:60px 20px}
.empty-icon{font-size:40px;margin-bottom:12px;opacity:.4}
.empty-title{font-family:'Syne',sans-serif;font-size:16px;font-weight:700;color:var(--ink);margin-bottom:6px}
.empty-sub{font-size:13px;color:var(--text-sub)}

/* Table footer */
.table-footer{display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-top:1px solid var(--border);font-size:13px;color:var(--text-sub);flex-wrap:wrap;gap:8px}

/* Toast */
.toast-wrap{position:fixed;top:24px;right:24px;z-index:9999}
.toast-msg{background:var(--surface);border-radius:10px;padding:14px 18px;box-shadow:var(--shadow-lg);display:flex;align-items:center;gap:10px;font-size:13.5px;font-weight:500;border-left:3px solid var(--accent2);animation:slideIn .3s ease;min-width:240px}
@keyframes slideIn{from{transform:translateX(30px);opacity:0}to{transform:translateX(0);opacity:1}}

/* Summary row at bottom */
.summary-strip{display:flex;gap:24px;flex-wrap:wrap;padding:14px 20px;border-top:1px solid var(--border);background:var(--muted)}
.summary-item{display:flex;flex-direction:column;gap:2px}
.summary-item-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.6px;color:var(--text-sub)}
.summary-item-val{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;color:var(--ink)}

@media(max-width:900px){
    .sp-page{padding:16px}
    .filter-grid{grid-template-columns:1fr 1fr;gap:10px}
    .filter-grid .btn-sm-primary{grid-column:span 2}
    .stat-grid{grid-template-columns:1fr 1fr}
    .page-hero{flex-direction:column;align-items:flex-start}
}
@media(max-width:500px){
    .stat-grid{grid-template-columns:1fr}
    .filter-grid{grid-template-columns:1fr}
    .filter-grid .btn-sm-primary{grid-column:span 1}
}
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">
<div class="sp-page">

    <!-- Toast placeholder -->
    <div class="toast-wrap" id="toastWrap" style="display:none">
        <div class="toast-msg">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <span id="toastText"></span>
        </div>
    </div>

    <!-- Page Hero -->
    <div class="page-hero">
        <div>
            <div class="page-hero-title"><?= __('my_salary_payments') ?></div>
            <div class="page-hero-subtitle">View your full payment history and receipts</div>
        </div>
        <div class="hero-actions">
            <a href="print_payroll.php?user_id=<?= $user_id ?>" target="_blank" class="btn-sm-ghost">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print My Payroll
            </a>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="stat-grid">
        <div class="stat-card blue">
            <div class="stat-label">Total USD Received</div>
            <div class="stat-value">$<?= number_format($total_usd, 2) ?></div>
            <div class="stat-icon">💵</div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-label">Total AFS Received</div>
            <div class="stat-value"><?= number_format($total_afs, 0) ?> ؋</div>
            <div class="stat-icon">💴</div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Total Payments</div>
            <div class="stat-value"><?= count($payments) ?></div>
            <div class="stat-icon">📋</div>
        </div>
    </div>

    <!-- Filter Panel -->
    <div class="filter-panel">
        <div class="filter-title">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            Filter Payments
        </div>
        <form method="GET" id="filterForm">
            <div class="filter-grid">
                <div>
                    <label class="field-label">Search</label>
                    <input type="text" class="field-control" name="search"
                           placeholder="Receipt number or description…"
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div>
                    <label class="field-label">Month</label>
                    <select class="field-control" name="month">
                        <option value="">All months</option>
                        <?php foreach ($months as $m): ?>
                        <option value="<?= $m['month_value'] ?>" <?= $month_filter == $m['month_value'] ? 'selected' : '' ?>>
                            <?= $m['month_label'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="field-label">Type</label>
                    <select class="field-control" name="type">
                        <option value="all">All types</option>
                        <option value="regular" <?= $type_filter === 'regular' ? 'selected' : '' ?>>Regular</option>
                        <option value="bonus"   <?= $type_filter === 'bonus'   ? 'selected' : '' ?>>Bonus</option>
                        <option value="advance" <?= $type_filter === 'advance' ? 'selected' : '' ?>>Advance</option>
                        <option value="other"   <?= $type_filter === 'other'   ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Currency</label>
                    <select class="field-control" name="currency">
                        <option value="all">All</option>
                        <option value="USD" <?= $currency_filter === 'USD' ? 'selected' : '' ?>>USD</option>
                        <option value="AFS" <?= $currency_filter === 'AFS' ? 'selected' : '' ?>>AFS</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn-sm-primary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        Search
                    </button>
                </div>
            </div>

            <!-- Active filter tags -->
            <?php if ($has_filters): ?>
            <div class="filter-tags">
                <?php if (!empty($search)): ?>
                <span class="filter-tag">Search: "<?= htmlspecialchars($search) ?>" <a href="?<?= http_build_query(array_merge($_GET, ['search' => ''])) ?>">×</a></span>
                <?php endif; ?>
                <?php if (!empty($month_filter)): ?>
                <span class="filter-tag">Month: <?= htmlspecialchars($month_filter) ?> <a href="?<?= http_build_query(array_merge($_GET, ['month' => ''])) ?>">×</a></span>
                <?php endif; ?>
                <?php if ($type_filter !== 'all'): ?>
                <span class="filter-tag">Type: <?= ucfirst($type_filter) ?> <a href="?<?= http_build_query(array_merge($_GET, ['type' => 'all'])) ?>">×</a></span>
                <?php endif; ?>
                <?php if ($currency_filter !== 'all'): ?>
                <span class="filter-tag">Currency: <?= $currency_filter ?> <a href="?<?= http_build_query(array_merge($_GET, ['currency' => 'all'])) ?>">×</a></span>
                <?php endif; ?>
                <a href="salary_payments.php" class="btn-clear-filter">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    Clear all filters
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Payment History Table -->
    <div class="sm-card">
        <div class="sm-card-header">
            <div class="sm-card-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Payment History
                <span style="font-family:'DM Sans',sans-serif;font-size:13px;font-weight:400;color:var(--text-sub)">(<?= count($payments) ?> records)</span>
            </div>
        </div>

        <?php if (count($payments) > 0): ?>
        <div style="overflow-x:auto">
            <table class="sm-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Account</th>
                        <th>For Month</th>
                        <th>Paid On</th>
                        <th>Receipt</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $i => $p):
                    $typeMap = ['regular'=>'salary-regular','bonus'=>'salary-bonus','advance'=>'salary-advance','other'=>'salary-other'];
                    $bc = $typeMap[$p['payment_type']] ?? 'salary-other';
                ?>
                <tr>
                    <td style="color:var(--text-sub);font-size:12px;width:40px"><?= $p['id'] ?></td>
                    <td>
                        <span class="amount-val"><?= number_format($p['amount'], 2) ?></span>
                        <span class="currency-tag"><?= $p['currency'] ?></span>
                    </td>
                    <td><span class="salary-badge <?= $bc ?>"><?= ucfirst($p['payment_type']) ?></span></td>
                    <td style="font-size:13px;color:var(--text-sub)"><?= htmlspecialchars($p['account_name']) ?></td>
                    <td style="font-size:13px;font-weight:500"><?= date('M Y', strtotime($p['payment_for_month'])) ?></td>
                    <td style="font-size:13px;color:var(--text-sub)"><?= date('M d, Y', strtotime($p['payment_date'])) ?></td>
                    <td><span class="receipt-code"><?= htmlspecialchars($p['receipt']) ?></span></td>
                    <td class="desc-cell" title="<?= htmlspecialchars($p['description']) ?>"><?= htmlspecialchars($p['description']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Summary strip -->
        <div class="summary-strip">
            <?php if ($total_usd > 0): ?>
            <div class="summary-item">
                <span class="summary-item-label">Total USD</span>
                <span class="summary-item-val">$<?= number_format($total_usd, 2) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($total_afs > 0): ?>
            <div class="summary-item">
                <span class="summary-item-label">Total AFS</span>
                <span class="summary-item-val"><?= number_format($total_afs, 0) ?> ؋</span>
            </div>
            <?php endif; ?>
            <div class="summary-item">
                <span class="summary-item-label">Records Shown</span>
                <span class="summary-item-val"><?= count($payments) ?></span>
            </div>
        </div>

        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📭</div>
            <div class="empty-title"><?php if ($has_filters): ?>No payments match your filters<?php else: ?>No payments found<?php endif; ?></div>
            <div class="empty-sub">
                <?php if ($has_filters): ?>
                    Try adjusting or <a href="salary_payments.php" style="color:var(--accent)">clearing your filters</a>.
                <?php else: ?>
                    Your salary payment history will appear here once payments are processed.
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>
</div>

<?php include '../includes/admin_footer.php'; ?>
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>