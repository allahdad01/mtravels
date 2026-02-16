<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to support_tickets_list.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once '../includes/db.php';
require_once '../includes/SupportTicketManager.php';

$ticketManager = new SupportTicketManager($pdo);

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$tenant_filter = $_GET['tenant_id'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

$filters = [];
if ($status_filter && in_array($status_filter, ['open', 'in_progress', 'resolved', 'closed'])) {
    $filters['status'] = $status_filter;
}
if ($tenant_filter && is_numeric($tenant_filter)) {
    $filters['tenant_id'] = intval($tenant_filter);
}

// Get all tickets for super admin
$tickets = $ticketManager->getAllTickets($filters);
$stats = $ticketManager->getStatistics();

// Get list of tenants
try {
    $stmt = $pdo->query("SELECT id, name FROM tenants WHERE status = 'active' ORDER BY name");
    $tenants = $stmt->fetchAll();
} catch (Exception $e) {
    $tenants = [];
}

include '../includes/header_super_admin.php';
?>

<style>
.card-header {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
    color: #ffffff !important;
    border-bottom: none !important;
}

.card-header h5 {
    color: #ffffff !important;
    margin-bottom: 0 !important;
}

.stat-card {
    border-top: 4px solid #007bff;
    padding: 20px;
    border-radius: 6px;
    background: #f8f9fa;
    text-align: center;
}

.stat-card.open {
    border-top-color: #dc3545;
}

.stat-card.in_progress {
    border-top-color: #ffc107;
}

.stat-card.resolved {
    border-top-color: #28a745;
}

.stat-number {
    font-size: 28px;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 13px;
    color: #666;
    text-transform: uppercase;
}

.table-responsive {
    border-radius: 6px;
    overflow: hidden;
}

.priority-badge {
    padding: 4px 8px;
    font-size: 11px;
    font-weight: 500;
    border-radius: 3px;
}

.table-hover tbody tr:hover {
    background-color: #f8f9fa;
}

.filter-section {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
}
</style>

<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Support Tickets Management</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="javascript:">Support Tickets</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Total Tickets</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card open">
                    <div class="stat-number"><?php echo $stats['open'] ?? 0; ?></div>
                    <div class="stat-label">Open</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card in_progress">
                    <div class="stat-number"><?php echo $stats['in_progress'] ?? 0; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card resolved">
                    <div class="stat-number"><?php echo $stats['resolved'] ?? 0; ?></div>
                    <div class="stat-label">Resolved</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12">
                <!-- Filters -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Filter by Status</label>
                            <select name="status" class="form-control" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Filter by Tenant</label>
                            <select name="tenant_id" class="form-control" onchange="this.form.submit()">
                                <option value="">All Tenants</option>
                                <?php foreach ($tenants as $tenant): ?>
                                    <option value="<?php echo $tenant['id']; ?>" 
                                            <?php echo $tenant_filter === (string)$tenant['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tenant['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="reset" class="btn btn-secondary btn-block">Clear Filters</button>
                        </div>
                    </form>
                </div>

                <!-- Tickets Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>Support Tickets</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tickets)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox text-muted" style="font-size: 48px;"></i>
                                <p class="text-muted mt-3">No support tickets found.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Ticket #</th>
                                            <th>Subject</th>
                                            <th>Tenant</th>
                                            <th>Submitted By</th>
                                            <th>Category</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tickets as $ticket): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($ticket['ticket_number']); ?></strong>
                                                </td>
                                                <td>
                                                    <span title="<?php echo htmlspecialchars($ticket['subject'] ?? ''); ?>">
                                                        <?php echo htmlspecialchars(substr($ticket['subject'] ?? '', 0, 40)); ?>
                                                        <?php echo strlen($ticket['subject'] ?? '') > 40 ? '...' : ''; ?>
                                                    </span>
                                                </td>
                                                <td><small><?php echo htmlspecialchars($ticket['tenant_id'] ?? ''); ?></small></td>
                                                <td><small><?php echo htmlspecialchars($ticket['submitted_by_name'] ?? ''); ?></small></td>
                                                <td><small><?php echo htmlspecialchars($ticket['category'] ?? ''); ?></small></td>
                                                <td>
                                                    <span class="priority-badge badge badge-<?php 
                                                        echo $ticket['priority'] === 'urgent' ? 'danger' : 
                                                        ($ticket['priority'] === 'high' ? 'warning' : 
                                                        ($ticket['priority'] === 'medium' ? 'info' : 'success')); 
                                                    ?>">
                                                        <?php echo ucfirst($ticket['priority']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php 
                                                        echo $ticket['status'] === 'open' ? 'primary' : 
                                                        ($ticket['status'] === 'in_progress' ? 'warning' : 
                                                        ($ticket['status'] === 'resolved' ? 'success' : 'secondary')); 
                                                    ?>">
                                                        <?php echo ucfirst($ticket['status']); ?>
                                                    </span>
                                                </td>
                                                <td><small><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></small></td>
                                                <td>
                                                    <a href="support_ticket_view.php?id=<?php echo $ticket['id']; ?>" 
                                                       class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>