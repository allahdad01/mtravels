<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../includes/db.php';

$items_per_page = 15;
$current_page = intval($_GET['page'] ?? 1);
$search_query = $_GET['search'] ?? '';
$revenue_type = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where_conditions = [];
$filter_params = [];

if (!empty($search_query)) {
    $where_conditions[] = "(sr.description LIKE ? OR t.name LIKE ? OR sr.reference_id LIKE ?)";
    $search_term = "%{$search_query}%";
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
}

if (!empty($revenue_type)) {
    $where_conditions[] = "sr.revenue_type = ?";
    $filter_params[] = $revenue_type;
}

if (!empty($status_filter)) {
    $where_conditions[] = "sr.status = ?";
    $filter_params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "sr.payment_date >= ?";
    $filter_params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "sr.payment_date <= ?";
    $filter_params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$count_query = "SELECT COUNT(*) as total FROM system_revenue sr LEFT JOIN tenants t ON sr.tenant_id = t.id {$where_clause}";
$stmt = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

$query = "SELECT sr.*, t.name as tenant_name FROM system_revenue sr
          LEFT JOIN tenants t ON sr.tenant_id = t.id
          {$where_clause}
          ORDER BY sr.payment_date DESC LIMIT ? OFFSET ?";

$query_params = $filter_params;
$query_params[] = $items_per_page;
$query_params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($query_params);
$revenues = $stmt->fetchAll();

$tenant_stmt = $pdo->query("SELECT id, name FROM tenants WHERE status != 'deleted' ORDER BY name");
$tenants = $tenant_stmt->fetchAll();

$summary_query = "SELECT
    COUNT(*) as total_count,
    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_amount,
    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
    SUM(CASE WHEN status = 'failed' THEN amount ELSE 0 END) as failed_amount,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
    FROM system_revenue sr {$where_clause}";
$stmt = $pdo->prepare($summary_query);
$stmt->execute(array_slice($filter_params, 0, -2));
$summary = $stmt->fetch();
?>
<?php include '../includes/header_super_admin.php'; ?>
<style>
:root {
    --brand: #4099ff;
    --brand2: #2ed8b6;
    --bg: #f0f2f5;
    --surface: #fff;
    --border: #e5e7eb;
    --text: #1f2937;
    --muted: #6b7280;
    --radius: 12px;
    --grad: linear-gradient(135deg, var(--brand), var(--brand2));
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); font-size: 14px; }
.page-header.card {
    background: var(--grad) !important; color: #fff; border: none !important;
    margin-bottom: 20px; padding: 22px 28px !important;
    box-shadow: 0 4px 20px rgba(64,153,255,0.3); border-radius: 12px;
    position: relative; overflow: hidden;
}
.page-header.card::after {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 60%);
    pointer-events: none;
}
.page-header.card h5 { color: #fff !important; margin: 0; font-weight: 700; font-size: 1.15rem; position: relative; z-index: 1; }
.page-header.card .row { display: flex; align-items: center; justify-content: space-between; width: 100%; position: relative; z-index: 2; }
.page-header.card .col-md-6:last-child { text-align: right; margin-left: auto; }

/* ─── METRIC GRID ─── */
.metric-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
@media (max-width: 992px) { .metric-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 576px) { .metric-grid { grid-template-columns: 1fr; } }
.metric-card {
    background: var(--surface); border-radius: var(--radius);
    border: 1px solid var(--border); padding: 20px;
    display: flex; align-items: center; gap: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.metric-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.metric-icon.green { background: #d1fae5; color: #059669; }
.metric-icon.yellow { background: #fef3c7; color: #d97706; }
.metric-icon.red { background: #fee2e2; color: #dc2626; }
.metric-icon.blue { background: #dbeafe; color: #2563eb; }
.metric-value { font-size: 1.4rem; font-weight: 700; line-height: 1.2; }
.metric-label { font-size: .8rem; color: var(--muted); }

/* ─── DATA TABLE ─── */
.sa-table-wrap {
    background: var(--surface); border-radius: var(--radius);
    border: 1px solid var(--border); overflow-x: auto;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    -webkit-overflow-scrolling: touch;
}
.sa-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid var(--border); gap: 12px; flex-wrap: wrap;
}
.sa-toolbar h3 { font-size: 1rem; font-weight: 600; }
.sa-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border: none; border-radius: 8px;
    font-size: .85rem; font-weight: 500; cursor: pointer;
    background: linear-gradient(135deg, var(--brand), var(--brand2));
    color: #fff; text-decoration: none; transition: opacity .15s;
}
.sa-btn:hover { opacity: .85; }
.sa-btn-sm { padding: 6px 10px; font-size: .8rem; border-radius: 6px; }
.sa-btn-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border: none; border-radius: 6px;
    cursor: pointer; transition: all .15s;
    background: transparent; color: var(--muted);
}
.sa-btn-icon:hover { background: var(--bg); color: var(--text); }
.sa-btn-icon.danger:hover { background: #fee2e2; color: #ef4444; }
.sa-table { width: 100%; border-collapse: collapse; }
.sa-table th {
    text-align: left; padding: 12px 20px; font-size: .75rem;
    font-weight: 600; color: var(--muted); text-transform: uppercase;
    letter-spacing: .04em; background: var(--bg); border-bottom: 1px solid var(--border);
}
.sa-table td {
    padding: 12px 20px; font-size: .85rem;
    border-bottom: 1px solid var(--border); vertical-align: middle;
}
.sa-table tr:last-child td { border-bottom: none; }
.sa-table tr:hover td { background: #f8fafc; }
.sa-td-actions { white-space: nowrap; text-align: right; }

/* ─── FILTER GRID ─── */
.filter-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; }
@media (max-width: 992px) { .filter-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 576px) { .filter-grid { grid-template-columns: 1fr; } }
.sa-form-control {
    width: 100%; padding: 8px 12px; border: 1px solid var(--border);
    border-radius: 8px; font-size: .85rem; font-family: inherit;
    background: var(--surface); color: var(--text);
}
.sa-form-control:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(64,153,255,.15); }

/* ─── BADGE / PILL ─── */
.sa-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 10px; border-radius: 20px;
    font-size: .75rem; font-weight: 600;
}
.sa-pill.completed { background: #d1fae5; color: #065f46; }
.sa-pill.pending { background: #fef3c7; color: #92400e; }
.sa-pill.failed { background: #fee2e2; color: #991b1b; }
.sa-pill.type {
    background: #dbeafe; color: #1e40af;
}

/* ─── PAGINATION ─── */
.pagination-wrap {
    display: flex; align-items: center; justify-content: center;
    gap: 8px; padding: 16px 20px; border-top: 1px solid var(--border);
    flex-wrap: wrap;
}
.pag-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 36px; height: 36px; padding: 0 10px;
    border: 1px solid var(--border); border-radius: 8px;
    background: var(--surface); color: var(--text);
    font-size: .8rem; text-decoration: none; transition: all .15s;
}
.pag-btn:hover { border-color: var(--brand); color: var(--brand); }
.pag-btn.active { background: linear-gradient(135deg, var(--brand), var(--brand2)); border-color: var(--brand2); color: #fff; }
.pag-btn.disabled { opacity: .4; pointer-events: none; }
.pag-info { font-size: .75rem; color: var(--muted); }

/* ─── ALERT / EMPTY / MODAL ─── */
.sa-alert {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: .85rem;
}
.sa-alert.success { background: #d1fae5; color: #065f46; }
.sa-alert.error { background: #fee2e2; color: #991b1b; }
.empty-state { text-align: center; padding: 48px 20px; color: var(--muted); }
.sa-modal-overlay {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.5); align-items: center; justify-content: center;
}
.sa-modal-overlay.active { display: flex; }
.sa-modal {
    background: var(--surface); border-radius: var(--radius);
    width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}
.sa-modal-hdr {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid var(--border);
}
.sa-modal-hdr h3 { font-size: 1rem; font-weight: 600; }
.sa-modal-close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--muted); padding: 4px; line-height: 1; }
.sa-modal-body { padding: 20px; }
.sa-modal-ftr {
    display: flex; align-items: center; justify-content: flex-end;
    gap: 8px; padding: 16px 20px; border-top: 1px solid var(--border);
}
.sa-form-group { margin-bottom: 16px; }
.sa-form-group label { display: block; font-weight: 600; margin-bottom: 6px; font-size: .85rem; }
.sa-btn-secondary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border: 1px solid var(--border); border-radius: 8px;
    font-size: .85rem; font-weight: 500; cursor: pointer;
    background: var(--surface); color: var(--text);
}
.sa-btn-secondary:hover { background: var(--bg); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
                                    <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                </svg>
                                System Revenue
                            </h5>
                            <p class="mb-0 mt-1" style="font-size:14px;opacity:0.9">Track system-wide revenue, commissions, and fees</p>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->

                <div class="main-body">
                    <div class="page-wrapper">

                        <?php if (isset($_GET['success'])): ?>
                        <div class="sa-alert success">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <?= htmlspecialchars($_GET['success']) ?>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['error'])): ?>
                        <div class="sa-alert error">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <?= htmlspecialchars($_GET['error']) ?>
                        </div>
                        <?php endif; ?>

                        <!-- Metric Cards -->
                        <div class="metric-grid">
                            <div class="metric-card">
                                <div class="metric-icon green">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                </div>
                                <div>
                                    <div class="metric-value">$<?= number_format($summary['completed_amount'] ?? 0, 2) ?></div>
                                    <div class="metric-label">Completed Revenue</div>
                                </div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-icon yellow">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                </div>
                                <div>
                                    <div class="metric-value">$<?= number_format($summary['pending_amount'] ?? 0, 2) ?></div>
                                    <div class="metric-label">Pending Revenue</div>
                                </div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-icon red">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                </div>
                                <div>
                                    <div class="metric-value">$<?= number_format($summary['failed_amount'] ?? 0, 2) ?></div>
                                    <div class="metric-label">Failed Transactions</div>
                                </div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-icon blue">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                </div>
                                <div>
                                    <div class="metric-value"><?= $summary['total_count'] ?? 0 ?></div>
                                    <div class="metric-label">Total Transactions</div>
                                </div>
                            </div>
                        </div>

                        <!-- Data Table -->
                        <div class="sa-table-wrap">
                            <div class="sa-toolbar">
                                <h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px">
                                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                    </svg>
                                    Revenue Records
                                </h3>
                                <button type="button" class="sa-btn" onclick="openModal('addRevenueModal')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    Add Revenue
                                </button>
                            </div>

                            <!-- Filters -->
                            <div style="padding:16px 20px;border-bottom:1px solid var(--border)">
                                <form method="GET">
                                    <div class="filter-grid">
                                        <input type="text" class="sa-form-control" name="search" placeholder="Search..." value="<?= htmlspecialchars($search_query) ?>">
                                        <select name="type" class="sa-form-control">
                                            <option value="">All Types</option>
                                            <option value="subscription" <?= $revenue_type === 'subscription' ? 'selected' : '' ?>>Subscription</option>
                                            <option value="addon" <?= $revenue_type === 'addon' ? 'selected' : '' ?>>Addon</option>
                                            <option value="commission" <?= $revenue_type === 'commission' ? 'selected' : '' ?>>Commission</option>
                                            <option value="fee" <?= $revenue_type === 'fee' ? 'selected' : '' ?>>Fee</option>
                                            <option value="other" <?= $revenue_type === 'other' ? 'selected' : '' ?>>Other</option>
                                        </select>
                                        <select name="status" class="sa-form-control">
                                            <option value="">All Status</option>
                                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="failed" <?= $status_filter === 'failed' ? 'selected' : '' ?>>Failed</option>
                                        </select>
                                        <input type="date" class="sa-form-control" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                                        <input type="date" class="sa-form-control" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                                        <button type="submit" class="sa-btn" style="justify-content:center;width:100%">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                            Filter
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <?php if (!empty($revenues)): ?>
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Tenant</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Reference</th>
                                        <th class="sa-td-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($revenues as $rev): ?>
                                    <tr>
                                        <td><?= date('M d, Y', strtotime($rev['payment_date'])) ?></td>
                                        <td><?= htmlspecialchars($rev['tenant_name'] ?? 'System') ?></td>
                                        <td><span class="sa-pill type"><?= ucfirst(htmlspecialchars($rev['revenue_type'])) ?></span></td>
                                        <td><strong>$<?= number_format($rev['amount'], 2) ?></strong></td>
                                        <td style="color:var(--muted)"><?= htmlspecialchars(substr($rev['description'] ?? '', 0, 40)) ?>...</td>
                                        <td><span class="sa-pill <?= $rev['status'] ?>"><?= ucfirst($rev['status']) ?></span></td>
                                        <td style="color:var(--muted)"><?= htmlspecialchars($rev['reference_id'] ?? '-') ?></td>
                                        <td class="sa-td-actions">
                                            <button type="button" class="sa-btn-icon" title="Edit"
                                                onclick="document.getElementById('edit_revenue_id').value=<?= $rev['id'] ?>;openModal('editRevenueModal')">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            </button>
                                            <a href="delete_system_revenue.php?id=<?= $rev['id'] ?>&csrf=<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                                               class="sa-btn-icon danger" title="Delete"
                                               onclick="return confirm('Delete this revenue record?')">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="empty-state">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.4;margin-bottom:12px"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                <p>No revenue records found</p>
                            </div>
                            <?php endif; ?>

                            <?php if ($total_pages > 1): ?>
                            <div class="pagination-wrap">
                                <a class="pag-btn <?= $current_page <= 1 ? 'disabled' : '' ?>"
                                   href="?page=<?= $current_page - 1 ?>&search=<?= urlencode($search_query) ?>&type=<?= $revenue_type ?>&status=<?= $status_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                                </a>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a class="pag-btn <?= $i == $current_page ? 'active' : '' ?>"
                                   href="?page=<?= $i ?>&search=<?= urlencode($search_query) ?>&type=<?= $revenue_type ?>&status=<?= $status_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"><?= $i ?></a>
                                <?php endfor; ?>
                                <a class="pag-btn <?= $current_page >= $total_pages ? 'disabled' : '' ?>"
                                   href="?page=<?= $current_page + 1 ?>&search=<?= urlencode($search_query) ?>&type=<?= $revenue_type ?>&status=<?= $status_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Revenue Modal -->
    <div class="sa-modal-overlay" id="addRevenueModal">
        <div class="sa-modal">
            <div class="sa-modal-hdr">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add System Revenue
                </h3>
                <button type="button" class="sa-modal-close" onclick="closeModal('addRevenueModal')">&times;</button>
            </div>
            <form action="create_system_revenue.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="sa-modal-body">
                    <div class="sa-form-group">
                        <label>Tenant *</label>
                        <select name="tenant_id" class="sa-form-control" required>
                            <option value="">Select Tenant</option>
                            <?php foreach ($tenants as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sa-form-group">
                        <label>Revenue Type *</label>
                        <select name="revenue_type" class="sa-form-control" required>
                            <option value="">Select Type</option>
                            <option value="subscription">Subscription</option>
                            <option value="addon">Addon</option>
                            <option value="commission">Commission</option>
                            <option value="fee">Fee</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="sa-form-group">
                            <label>Amount *</label>
                            <input type="number" name="amount" class="sa-form-control" step="0.01" min="0" required>
                        </div>
                        <div class="sa-form-group">
                            <label>Currency *</label>
                            <select name="currency" class="sa-form-control" required>
                                <option value="USD">USD</option>
                                <option value="AFS">AFS</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="sa-form-group">
                            <label>Payment Date *</label>
                            <input type="date" name="payment_date" class="sa-form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="sa-form-group">
                            <label>Status *</label>
                            <select name="status" class="sa-form-control" required>
                                <option value="completed">Completed</option>
                                <option value="pending">Pending</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                    </div>
                    <div class="sa-form-group">
                        <label>Description</label>
                        <textarea name="description" class="sa-form-control" rows="3" placeholder="Revenue description"></textarea>
                    </div>
                    <div class="sa-form-group">
                        <label>Reference ID</label>
                        <input type="text" name="reference_id" class="sa-form-control" placeholder="e.g., subscription ID or transaction ID">
                    </div>
                    <div class="sa-form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="sa-form-control" rows="2" placeholder="Additional notes"></textarea>
                    </div>
                </div>
                <div class="sa-modal-ftr">
                    <button type="button" class="sa-btn-secondary" onclick="closeModal('addRevenueModal')">Cancel</button>
                    <button type="submit" class="sa-btn">Save Revenue</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Revenue Modal -->
    <div class="sa-modal-overlay" id="editRevenueModal">
        <div class="sa-modal">
            <div class="sa-modal-hdr">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit Revenue
                </h3>
                <button type="button" class="sa-modal-close" onclick="closeModal('editRevenueModal')">&times;</button>
            </div>
            <form action="update_system_revenue.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="id" id="edit_revenue_id">
                <div class="sa-modal-body" id="editRevenueBody">
                    <p style="color:var(--muted)">Loading revenue details...</p>
                </div>
                <div class="sa-modal-ftr">
                    <button type="button" class="sa-btn-secondary" onclick="closeModal('editRevenueModal')">Cancel</button>
                    <button type="submit" class="sa-btn">Update Revenue</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.sa-modal-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
});
</script>
</body>
</html>
