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
.page-header.card {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: #ffffff;
    border: none;
    margin-bottom: 20px;
    padding: 20px !important;
}

.page-header.card .row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-header.card h5 {
    color: #ffffff;
    margin: 0;
}

.page-header.card .text-end {
    text-align: right;
}

.page-header.card .btn {
    background: rgba(255,255,255,0.2);
    color: #ffffff;
    border: 1px solid rgba(255,255,255,0.3);
}

.page-header.card .btn:hover {
    background: rgba(255,255,255,0.3);
    border-color: rgba(255,255,255,0.5);
}
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="page-header card">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0">Support Tickets Management</h5>
                    </div>
                    <div class="col-md-6 text-end">
                        <button onclick="location.reload()" class="btn btn-info btn-sm">
                            <i class="fas fa-sync"></i> Refresh SLA Status
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="text-primary"><?php echo $stats['total'] ?? 0; ?></h3>
                            <p class="text-muted mb-0">Total</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="text-info"><?php echo $stats['open'] ?? 0; ?></h3>
                            <p class="text-muted mb-0">Open</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="text-warning"><?php echo $stats['in_progress'] ?? 0; ?></h3>
                            <p class="text-muted mb-0">In Progress</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="text-success"><?php echo $stats['resolved'] ?? 0; ?></h3>
                            <p class="text-muted mb-0">Resolved</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="text-danger"><?php echo $stats['breached'] ?? 0; ?></h3>
                            <p class="text-muted mb-0">SLA Breached</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="text-danger"><?php echo $stats['at_risk'] ?? 0; ?></h3>
                            <p class="text-muted mb-0">At Risk</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <select name="status" class="form-control" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="sla_status" class="form-control" onchange="this.form.submit()">
                                <option value="">All SLA</option>
                                <option value="on_track" <?php echo $sla_status_filter === 'on_track' ? 'selected' : ''; ?>>On Track</option>
                                <option value="at_risk" <?php echo $sla_status_filter === 'at_risk' ? 'selected' : ''; ?>>At Risk</option>
                                <option value="breached" <?php echo $sla_status_filter === 'breached' ? 'selected' : ''; ?>>Breached</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="priority" class="form-control" onchange="this.form.submit()">
                                <option value="">All Priorities</option>
                                <option value="critical" <?php echo $priority_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="tenant" class="form-control" onchange="this.form.submit()">
                                <option value="">All Tenants</option>
                                <?php foreach ($tenants as $tenant): ?>
                                    <option value="<?php echo $tenant['id']; ?>" <?php echo $tenant_filter == $tenant['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tenant['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <a href="support_tickets_manage.php" class="btn btn-secondary btn-block btn-sm">Reset Filters</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tickets Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Ticket #</th>
                                <th>Title</th>
                                <th>Tenant</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>SLA Status</th>
                                <th>Created</th>
                                <th>Time Left</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tickets)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        No tickets found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($ticket['ticket_number']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars(substr($ticket['title'], 0, 35)); ?>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars(substr($ticket['tenant_name'], 0, 20)); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $ticket['priority'] === 'critical' ? 'danger' : ($ticket['priority'] === 'high' ? 'warning' : 'info'); ?>">
                                                <?php echo ucfirst($ticket['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $ticket['status'] === 'open' ? 'primary' : 
                                                    ($ticket['status'] === 'in_progress' ? 'warning' : 
                                                    ($ticket['status'] === 'resolved' ? 'success' : 'secondary'));
                                            ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $ticket['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $ticket['sla_display']['color']; ?>">
                                                <?php echo $ticket['sla_display']['status']; ?>
                                            </span>
                                        </td>
                                        <td class="text-muted small">
                                            <?php echo date('M d', strtotime($ticket['created_at'])); ?>
                                        </td>
                                        <td class="small">
                                            <?php echo $ticket['sla_display']['hours_remaining']; ?>h
                                        </td>
                                        <td>
                                            <a href="support_ticket_manage.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                Manage
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
<?php require_once '../includes/admin_footer.php'; ?>
