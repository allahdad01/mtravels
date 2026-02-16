<?php
/**
 * Support Tickets - Admin Interface
 *
 * Manage and track support tickets for the tenant.
 */

require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once '../includes/SupportTicketManager.php';
require_once '../includes/SLACalculator.php';
require_once '../admin/security.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

$pageTitle = __('support_tickets');
require_once '../includes/header.php';
?>

<style>
/* Enhanced custom styles for better layout and design */
.page-header.card {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: #ffffff;
    border: none;
    margin-bottom: 20px;
    padding: 20px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-radius: 10px;
}

.page-header.card .row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-header.card h5 {
    color: #ffffff;
    margin: 0;
    font-weight: 600;
}

.page-header.card .text-end {
    text-align: right;
}

.page-header.card .btn {
    background: rgba(255,255,255,0.2);
    color: #ffffff;
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 25px;
    transition: all 0.3s ease;
}

.page-header.card .btn:hover {
    background: rgba(255,255,255,0.3);
    border-color: rgba(255,255,255,0.5);
    transform: translateY(-1px);
}

.card {
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border: none;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px 10px 0 0;
    padding: 1rem 1.5rem;
    border: none;
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.table-responsive {
    border-radius: 10px;

}

.table {
    margin-bottom: 0;
}

.table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    padding: 1rem;
}

.table tbody tr:hover {
    background-color: #f1f3f4;
}

.table tbody td {
    padding: 1rem;
    vertical-align: middle;
}

.form-control {
    border-radius: 8px;
    border: 1px solid #ced4da;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    padding: 0.75rem;
}

.form-control:focus {
    border-color: #4099ff;
    box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
}

.btn-primary {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
}

.btn-secondary {
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.badge {
    font-size: 0.85em;
    padding: 0.5em 0.75em;
    border-radius: 20px;
    font-weight: 500;
}

.badge-primary {
    background-color: #007bff;
}

.badge-info {
    background-color: #17a2b8;
}

.badge-warning {
    background-color: #ffc107;
    color: #212529;
}

.badge-success {
    background-color: #28a745;
}

.badge-danger {
    background-color: #dc3545;
}

.badge-secondary {
    background-color: #6c757d;
}

.h2 {
    font-size: 2.5rem;
}

.h4 {
    font-size: 1.5rem;
}

.h5 {
    font-size: 1.25rem;
}

.h6 {
    font-size: 1rem;
}
</style>

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="main-content">
                                <div class="page-header card">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h5 class="mb-0"><i class="feather icon-life-buoy mr-2"></i><?php echo __('support_tickets'); ?></h5>
                                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;"><?php echo __('manage_and_track_support_tickets'); ?></p>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                                <i class="feather icon-arrow-left mr-1"></i><?php echo __('back_to_dashboard'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Statistics Cards -->
                                    <div class="col-md-12">
                                        <div class="row mb-4">
                                            <div class="col-md-2">
                                                <div class="card text-center">
                                                    <div class="card-body">
                                                        <div class="h2 font-weight-bold text-primary">
                                                            <i class="feather icon-bar-chart-2 mr-2"></i><?php echo $stats['total'] ?? 0; ?>
                                                        </div>
                                                        <p class="text-muted mb-0"><?php echo __('total'); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="card text-center">
                                                    <div class="card-body">
                                                        <div class="h2 font-weight-bold text-info">
                                                            <i class="feather icon-circle mr-2"></i><?php echo $stats['open'] ?? 0; ?>
                                                        </div>
                                                        <p class="text-muted mb-0"><?php echo __('open'); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="card text-center">
                                                    <div class="card-body">
                                                        <div class="h2 font-weight-bold text-warning">
                                                            <i class="feather icon-clock mr-2"></i><?php echo $stats['in_progress'] ?? 0; ?>
                                                        </div>
                                                        <p class="text-muted mb-0"><?php echo __('in_progress'); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="card text-center">
                                                    <div class="card-body">
                                                        <div class="h2 font-weight-bold text-success">
                                                            <i class="feather icon-check-circle mr-2"></i><?php echo $stats['resolved'] ?? 0; ?>
                                                        </div>
                                                        <p class="text-muted mb-0"><?php echo __('resolved'); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="card text-center">
                                                    <div class="card-body">
                                                        <div class="h2 font-weight-bold text-danger">
                                                            <i class="feather icon-alert-triangle mr-2"></i><?php echo $stats['breached'] ?? 0; ?>
                                                        </div>
                                                        <p class="text-muted mb-0"><?php echo __('sla_breached'); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="card text-center">
                                                    <div class="card-body">
                                                        <div class="h2 font-weight-bold text-warning">
                                                            <i class="feather icon-alert-circle mr-2"></i><?php echo $stats['at_risk'] ?? 0; ?>
                                                        </div>
                                                        <p class="text-muted mb-0"><?php echo __('at_risk'); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Filters and Table -->
                                    <div class="col-md-12">
                                        <!-- Filters -->
                                        <div class="card mb-3">
                                            <div class="card-header">
                                                <h5><i class="feather icon-filter mr-2"></i><?php echo __('filters'); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <form method="GET" class="row g-3">
                                                    <div class="col-md-3">
                                                        <label for="status"><i class="feather icon-tag mr-2"></i><?php echo __('status'); ?></label>
                                                        <select name="status" class="form-control form-control-lg" id="status" onchange="this.form.submit()">
                                                            <option value=""><?php echo __('all_status'); ?></option>
                                                            <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>><?php echo __('open'); ?></option>
                                                            <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>><?php echo __('in_progress'); ?></option>
                                                            <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>><?php echo __('resolved'); ?></option>
                                                            <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>><?php echo __('closed'); ?></option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="category"><i class="feather icon-folder mr-2"></i><?php echo __('category'); ?></label>
                                                        <select name="category" class="form-control form-control-lg" id="category" onchange="this.form.submit()">
                                                            <option value=""><?php echo __('all_categories'); ?></option>
                                                            <?php foreach ($categories as $cat): ?>
                                                                <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="priority"><i class="feather icon-alert-circle mr-2"></i><?php echo __('priority'); ?></label>
                                                        <select name="priority" class="form-control form-control-lg" id="priority" onchange="this.form.submit()">
                                                            <option value=""><?php echo __('all_priorities'); ?></option>
                                                            <option value="critical" <?php echo $priority_filter === 'critical' ? 'selected' : ''; ?>><?php echo __('critical'); ?></option>
                                                            <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>><?php echo __('high'); ?></option>
                                                            <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>><?php echo __('medium'); ?></option>
                                                            <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>><?php echo __('low'); ?></option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label>&nbsp;</label>
                                                        <a href="support_tickets.php" class="btn btn-secondary btn-lg btn-block">
                                                            <i class="feather icon-refresh-cw mr-2"></i><?php echo __('reset_filters'); ?>
                                                        </a>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                        <!-- Tickets Table -->
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="feather icon-list mr-2"></i><?php echo __('support_tickets'); ?>
                                                    <span class="badge-primary badge-pill ml-2"><?php echo count($tickets); ?></span>
                                                </h5>
                                            </div>
                                            <div class="card-body table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th><i class="feather icon-hash mr-1"></i><?php echo __('ticket_number'); ?></th>
                                                            <th><i class="feather icon-file-text mr-1"></i><?php echo __('title'); ?></th>
                                                            <th><i class="feather icon-folder mr-1"></i><?php echo __('category'); ?></th>
                                                            <th><i class="feather icon-alert-circle mr-1"></i><?php echo __('priority'); ?></th>
                                                            <th><i class="feather icon-tag mr-1"></i><?php echo __('status'); ?></th>
                                                            <th><i class="feather icon-clock mr-1"></i><?php echo __('sla_status'); ?></th>
                                                            <th><i class="feather icon-calendar mr-1"></i><?php echo __('created'); ?></th>
                                                            <th><i class="feather icon-eye mr-1"></i><?php echo __('action'); ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (empty($tickets)): ?>
                                                            <tr>
                                                                <td colspan="8" class="text-center text-muted py-4">
                                                                    <i class="feather icon-inbox mr-2"></i><?php echo __('no_tickets_found'); ?>
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
                                                                        <span class="badge-info badge-pill px-3 py-2">
                                                                            <?php echo htmlspecialchars($ticket['category_name']); ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge-<?php echo $ticket['priority'] === 'critical' ? 'danger' : ($ticket['priority'] === 'high' ? 'warning' : 'info'); ?> badge-pill px-3 py-2">
                                                                            <?php echo ucfirst($ticket['priority']); ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge-<?php
                                                                            echo $ticket['status'] === 'open' ? 'primary' :
                                                                                ($ticket['status'] === 'in_progress' ? 'warning' :
                                                                                ($ticket['status'] === 'resolved' ? 'success' : 'secondary'));
                                                                        ?> badge-pill px-3 py-2">
                                                                            <?php echo ucwords(str_replace('_', ' ', $ticket['status'])); ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge-<?php echo $ticket['sla_display']['color']; ?> badge-pill px-3 py-2">
                                                                            <?php echo $ticket['sla_display']['status']; ?>
                                                                        </span>
                                                                    </td>
                                                                    <td class="text-muted"><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></td>
                                                                    <td>
                                                                        <a href="support_ticket_detail.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                            <i class="feather icon-eye mr-1"></i><?php echo __('view'); ?>
                                                                        </a>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- New Ticket Button -->
                                        <div class="text-center mt-4">
                                            <a href="support_ticket_create.php" class="btn btn-primary btn-lg">
                                                <i class="feather icon-plus mr-2"></i><?php echo __('create_new_ticket'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Required Js -->
    
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
<?php include '../includes/admin_footer.php'; ?>
