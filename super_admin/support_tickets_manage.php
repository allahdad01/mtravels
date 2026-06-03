<?php
session_start();

// Security checks
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../access_denied.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/SupportTicketManager.php';
require_once '../includes/SLACalculator.php';

$ticketManager = new SupportTicketManager($pdo);
$slaCalculator = new SLACalculator($pdo);

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$sla_status_filter = $_GET['sla_status'] ?? '';
$tenant_filter = $_GET['tenant'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

$filters = [];
if ($status_filter && in_array($status_filter, ['open', 'in_progress', 'resolved', 'closed'])) {
    $filters['status'] = $status_filter;
}
if ($sla_status_filter && in_array($sla_status_filter, ['on_track', 'at_risk', 'breached', 'resolved'])) {
    $filters['sla_status'] = $sla_status_filter;
}
if ($tenant_filter && is_numeric($tenant_filter)) {
    $filters['tenant_id'] = intval($tenant_filter);
}
if ($priority_filter && in_array($priority_filter, ['low', 'medium', 'high', 'critical'])) {
    $filters['priority'] = $priority_filter;
}

// Get all tickets
$tickets = $ticketManager->getAllTickets($filters);

// Get tenants for dropdown
$tenantStmt = $pdo->query("SELECT id, name FROM tenants ORDER BY name");
$tenants = $tenantStmt->fetchAll(PDO::FETCH_ASSOC);

// Get overall stats
$statsStmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
        SUM(CASE WHEN sla_status = 'breached' THEN 1 ELSE 0 END) as breached,
        SUM(CASE WHEN sla_status = 'at_risk' THEN 1 ELSE 0 END) as at_risk
    FROM support_tickets
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Update SLA displays
foreach ($tickets as &$ticket) {
    $ticket['sla_display'] = $slaCalculator->getSLADisplay($ticket);
}

$pageTitle = "Support Tickets Management";
require_once '../includes/header_super_admin.php';
?>

<style>
:root {
    --primary: #4099ff; --primary-dark: #2673cc; --primary-glow: rgba(64,153,255,0.2);
    --secondary: #2ed8b6; --grad: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    --bg: #f0f8ff; --surface: #ffffff; --surface2: #f3f8ff;
    --text: #1a2332; --muted: #6b7280; --border: #e2e8f0;
    --radius: 10px; --green: #10b981; --red: #ef4444; --amber: #f59e0b; --blue: #3b82f6;
}
.page-header.card {
    background: linear-gradient(135deg, #4099ff 0%, #2673cc 50%, #2ed8b6 100%) !important;
    color: #fff; border: none !important; margin-bottom: 24px;
    padding: 22px 28px !important; box-shadow: 0 4px 20px rgba(64,153,255,0.3);
    border-radius: 12px; position: relative; overflow: hidden;
}
.page-header.card::after {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 60%);
    pointer-events: none;
}
.page-header.card h5 { color: #fff !important; margin: 0; font-weight: 700; font-size: 1.15rem; position: relative; z-index: 1; }
.page-header.card .btn {
    background: rgba(255,255,255,0.12) !important; color: #fff;
    border: 1px solid rgba(255,255,255,0.25) !important; border-radius: 8px;
    padding: 7px 16px; font-size: 0.8rem; font-weight: 500;
    transition: all 0.2s; position: relative; z-index: 1;
    backdrop-filter: blur(4px); text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px; cursor: pointer;
}
.page-header.card .btn:hover { background: rgba(255,255,255,0.2) !important; border-color: rgba(255,255,255,0.4) !important; transform: translateY(-1px); }
.page-header.card .row { display: flex; align-items: center; justify-content: space-between; width: 100%; position: relative; z-index: 2; }
.page-header.card .col-md-6:last-child { text-align: right; margin-left: auto; }
.sa-stats { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; margin-bottom: 20px; }
@media (max-width: 992px) { .sa-stats { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 576px) { .sa-stats { grid-template-columns: repeat(2, 1fr); } }
.sa-stat-card {
    background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
    padding: 14px; display: flex; align-items: center; gap: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: box-shadow 0.2s, transform 0.2s;
}
.sa-stat-card:hover { box-shadow: 0 4px 16px var(--primary-glow); transform: translateY(-2px); }
.sa-stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sa-stat-icon svg { width: 20px; height: 20px; }
.sa-stat-blue { background: rgba(64,153,255,0.1); color: var(--primary); }
.sa-stat-green { background: rgba(16,185,129,0.1); color: var(--green); }
.sa-stat-amber { background: rgba(245,158,11,0.1); color: var(--amber); }
.sa-stat-red { background: rgba(239,68,68,0.1); color: var(--red); }
.sa-stat-gray { background: rgba(107,114,128,0.1); color: var(--muted); }
.sa-stat-body { display: flex; flex-direction: column; gap: 1px; }
.sa-stat-value { font-size: 1.3rem; font-weight: 700; color: var(--text); line-height: 1.2; }
.sa-stat-label { font-size: 0.68rem; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
.sa-toolbar {
    background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
    padding: 14px 18px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.sa-toolbar-inner { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.sa-filter-select {
    padding: 8px 12px; background: var(--surface2); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text); font-size: 0.82rem; outline: none; cursor: pointer;
    min-width: 140px;
}
.sa-filter-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
.sa-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 8px 18px; border-radius: 8px; border: none; cursor: pointer;
    font-size: 0.82rem; font-weight: 600; transition: all 0.2s;
    text-decoration: none; white-space: nowrap;
}
.sa-btn-primary { background: var(--grad); color: white; }
.sa-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 14px var(--primary-glow); }
.sa-btn-ghost { background: var(--surface2); color: var(--muted); border: 1px solid var(--border); }
.sa-btn-ghost:hover { background: rgba(64,153,255,0.08); border-color: var(--primary); color: var(--primary); }
.sa-btn-sm { padding: 6px 12px; font-size: 0.75rem; }
.sa-section-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1px solid var(--border); }
.sa-section-header h2 { font-size: 1.15rem; font-weight: 600; margin: 0; color: var(--text); }
.sa-section-header p { margin: 2px 0 0 0; font-size: 0.75rem; color: var(--muted); }
.sa-empty { text-align: center; padding: 60px 24px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; }
.sa-empty svg { color: var(--muted); opacity: 0.3; margin-bottom: 16px; }
.sa-empty-title { font-weight: 600; margin-bottom: 6px; color: var(--text); font-size: 1.05rem; }
.sa-empty-desc { font-size: 0.85rem; color: var(--muted); }
.sa-table-wrap {
    background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
    overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.sa-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.sa-table thead { background: var(--surface2); }
.sa-table th { padding: 12px 16px; text-align: left; font-weight: 600; color: var(--muted); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid var(--border); white-space: nowrap; }
.sa-table td { padding: 14px 16px; border-bottom: 1px solid var(--border); color: var(--text); vertical-align: middle; }
.sa-table tr:last-child td { border-bottom: none; }
.sa-table tr:hover td { background: rgba(64,153,255,0.03); }
.sa-th-actions { text-align: right; width: 100px; }
.sa-td-actions { text-align: right; white-space: nowrap; }
.sa-icon-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 8px; border: 1px solid transparent;
    background: var(--surface2); color: var(--muted); cursor: pointer;
    transition: all 0.2s; text-decoration: none;
}
.sa-icon-btn:hover { background: rgba(64,153,255,0.08); border-color: var(--primary); color: var(--primary); }
.sa-td-title { font-weight: 600; color: var(--text); }
.sa-td-sub { font-size: 0.75rem; color: var(--muted); margin-top: 2px; display: flex; align-items: center; gap: 4px; }
.sa-td-date { color: var(--muted); font-size: 0.8rem; white-space: nowrap; }
.sa-na { color: var(--muted); font-size: 0.8rem; }
.pill {
    font-size: 0.62rem; font-weight: 700; padding: 3px 8px; border-radius: 20px;
    text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap;
    display: inline-flex; align-items: center; gap: 4px;
}
.pill-green { background: rgba(34,211,160,0.12); color: var(--green); }
.pill-amber { background: rgba(245,158,11,0.12); color: var(--amber); }
.pill-red { background: rgba(244,63,94,0.12); color: var(--red); }
.pill-blue { background: rgba(56,189,248,0.12); color: var(--blue); }
.pill-gray { background: rgba(107,114,128,0.12); color: var(--muted); }
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="page-header card">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>Support Tickets Management
                        </h5>
                    </div>
                    <div class="col-md-6 text-end">
                        <button type="button" onclick="location.reload()" class="btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>Refresh SLA Status
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="sa-stats">
                <div class="sa-stat-card">
                    <div class="sa-stat-icon sa-stat-blue"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                    <div class="sa-stat-body"><span class="sa-stat-value"><?php echo $stats['total'] ?? 0; ?></span><span class="sa-stat-label">Total</span></div>
                </div>
                <div class="sa-stat-card">
                    <div class="sa-stat-icon sa-stat-blue"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg></div>
                    <div class="sa-stat-body"><span class="sa-stat-value"><?php echo $stats['open'] ?? 0; ?></span><span class="sa-stat-label">Open</span></div>
                </div>
                <div class="sa-stat-card">
                    <div class="sa-stat-icon sa-stat-amber"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                    <div class="sa-stat-body"><span class="sa-stat-value"><?php echo $stats['in_progress'] ?? 0; ?></span><span class="sa-stat-label">In Progress</span></div>
                </div>
                <div class="sa-stat-card">
                    <div class="sa-stat-icon sa-stat-green"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                    <div class="sa-stat-body"><span class="sa-stat-value"><?php echo $stats['resolved'] ?? 0; ?></span><span class="sa-stat-label">Resolved</span></div>
                </div>
                <div class="sa-stat-card">
                    <div class="sa-stat-icon sa-stat-red"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                    <div class="sa-stat-body"><span class="sa-stat-value"><?php echo $stats['breached'] ?? 0; ?></span><span class="sa-stat-label">SLA Breached</span></div>
                </div>
                <div class="sa-stat-card">
                    <div class="sa-stat-icon sa-stat-red"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
                    <div class="sa-stat-body"><span class="sa-stat-value"><?php echo $stats['at_risk'] ?? 0; ?></span><span class="sa-stat-label">At Risk</span></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="sa-toolbar">
                <form method="GET" class="sa-toolbar-inner">
                    <select name="status" class="sa-filter-select" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                    <select name="sla_status" class="sa-filter-select" onchange="this.form.submit()">
                        <option value="">All SLA</option>
                        <option value="on_track" <?php echo $sla_status_filter === 'on_track' ? 'selected' : ''; ?>>On Track</option>
                        <option value="at_risk" <?php echo $sla_status_filter === 'at_risk' ? 'selected' : ''; ?>>At Risk</option>
                        <option value="breached" <?php echo $sla_status_filter === 'breached' ? 'selected' : ''; ?>>Breached</option>
                        <option value="resolved" <?php echo $sla_status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    </select>
                    <select name="priority" class="sa-filter-select" onchange="this.form.submit()">
                        <option value="">All Priorities</option>
                        <option value="critical" <?php echo $priority_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                        <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                    </select>
                    <select name="tenant" class="sa-filter-select" onchange="this.form.submit()">
                        <option value="">All Tenants</option>
                        <?php foreach ($tenants as $tenant): ?>
                        <option value="<?php echo $tenant['id']; ?>" <?php echo $tenant_filter == $tenant['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($tenant['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <a href="support_tickets_manage.php" class="sa-btn sa-btn-ghost">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Reset
                    </a>
                </form>
            </div>

            <!-- Data Table -->
            <?php if (!empty($tickets)): ?>
            <div class="sa-table-wrap">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Tenant</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>SLA</th>
                            <th>Time Left</th>
                            <th>Created</th>
                            <th class="sa-th-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td>
                                <div class="sa-td-title">#<?= htmlspecialchars($ticket['ticket_number']) ?></div>
                                <div class="sa-td-sub"><?= htmlspecialchars($ticket['title']) ?></div>
                            </td>
                            <td><span style="font-size:0.82rem;"><?= htmlspecialchars($ticket['tenant_name']) ?></span></td>
                            <td><span class="pill <?= $ticket['priority'] === 'critical' ? 'pill-red' : ($ticket['priority'] === 'high' ? 'pill-amber' : ($ticket['priority'] === 'medium' ? 'pill-blue' : 'pill-gray')) ?>"><?= ucfirst($ticket['priority']) ?></span></td>
                            <td><span class="pill <?= $ticket['status'] === 'open' ? 'pill-blue' : ($ticket['status'] === 'in_progress' ? 'pill-amber' : ($ticket['status'] === 'resolved' ? 'pill-green' : 'pill-gray')) ?>"><?= ucwords(str_replace('_', ' ', $ticket['status'])) ?></span></td>
                            <td><span class="pill pill-<?= $ticket['sla_display']['color'] ?>"><?= $ticket['sla_display']['status'] ?></span></td>
                            <td class="sa-td-date"><?= $ticket['sla_display']['hours_remaining'] ?>h</td>
                            <td class="sa-td-date"><?= date('M d, Y', strtotime($ticket['created_at'])) ?></td>
                            <td class="sa-td-actions">
                                <a href="support_ticket_view.php?id=<?= $ticket['id'] ?>" class="sa-icon-btn" title="View">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="sa-empty">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <div class="sa-empty-title">No Tickets Found</div>
                <div class="sa-empty-desc">No support tickets match your filters.</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<?php require_once '../includes/admin_footer.php'; ?>
