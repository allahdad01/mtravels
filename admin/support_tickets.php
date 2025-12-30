<?php
session_start();

// Security checks
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    header('Location: ../access_denied.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'] ?? 0;
$user_role = $_SESSION['role'];

// Check role access
if (!in_array($user_role, ['admin', 'finance', 'sales', 'umrah'])) {
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
$category_filter = $_GET['category'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

$filters = [];
if ($status_filter && in_array($status_filter, ['open', 'in_progress', 'resolved', 'closed'])) {
    $filters['status'] = $status_filter;
}
if ($category_filter && is_numeric($category_filter)) {
    $filters['category_id'] = intval($category_filter);
}
if ($priority_filter && in_array($priority_filter, ['low', 'medium', 'high', 'critical'])) {
    $filters['priority'] = $priority_filter;
}

// Get tickets
$tickets = $ticketManager->getTicketsByTenant($tenant_id, $filters);
$categories = $ticketManager->getCategories();
$stats = $ticketManager->getStatistics($tenant_id);

// Update SLA displays
foreach ($tickets as &$ticket) {
    $ticket['sla_display'] = $slaCalculator->getSLADisplay($ticket);
}

$pageTitle = "Support Tickets";
require_once '../includes/header.php';
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
                        <h5 class="mb-0">Support Tickets</h5>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="support_ticket_create.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> New Ticket
                        </a>
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
                            <h3 class="text-warning"><?php echo $stats['at_risk'] ?? 0; ?></h3>
                            <p class="text-muted mb-0">At Risk</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <select name="status" class="form-control" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="category" class="form-control" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="priority" class="form-control" onchange="this.form.submit()">
                                <option value="">All Priorities</option>
                                <option value="critical" <?php echo $priority_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <a href="support_tickets.php" class="btn btn-secondary btn-block">Reset Filters</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tickets Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Ticket #</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>SLA Status</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tickets)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
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
                                            <?php echo htmlspecialchars(substr($ticket['title'], 0, 40)); ?>
                                        </td>
                                        <td>
                                            <span class="bg-info">
                                                <?php echo htmlspecialchars($ticket['category_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="bg-<?php echo $ticket['priority'] === 'critical' ? 'danger' : ($ticket['priority'] === 'high' ? 'warning' : 'info'); ?>">
                                                <?php echo ucfirst($ticket['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="bg-<?php 
                                                echo $ticket['status'] === 'open' ? 'primary' : 
                                                    ($ticket['status'] === 'in_progress' ? 'warning' : 
                                                    ($ticket['status'] === 'resolved' ? 'success' : 'secondary'));
                                            ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $ticket['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="bg-<?php echo $ticket['sla_display']['color']; ?>">
                                                <?php echo $ticket['sla_display']['status']; ?>
                                            </span>
                                        </td>
                                        <td class="text-muted small">
                                            <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?>
                                        </td>
                                        <td>
                                            <a href="support_ticket_detail.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                View
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
