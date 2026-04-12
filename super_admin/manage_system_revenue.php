<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

// Check session timeout
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../includes/db.php';

// Pagination and filters
$items_per_page = 15;
$current_page = intval($_GET['page'] ?? 1);
$search_query = $_GET['search'] ?? '';
$revenue_type = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build WHERE clause
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

// Count total
$count_query = "SELECT COUNT(*) as total FROM system_revenue sr LEFT JOIN tenants t ON sr.tenant_id = t.id {$where_clause}";
$stmt = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Fetch revenue records
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

// Get all tenants for dropdown
$tenant_stmt = $pdo->query("SELECT id, name FROM tenants WHERE status != 'deleted' ORDER BY name");
$tenants = $tenant_stmt->fetchAll();

// Summary stats
$summary_query = "SELECT 
    COUNT(*) as total_count,
    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_amount,
    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
    SUM(CASE WHEN status = 'failed' THEN amount ELSE 0 END) as failed_amount,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
    FROM system_revenue sr {$where_clause}";
$stmt = $pdo->prepare($summary_query);
$stmt->execute(array_slice($filter_params, 0, -2)); // Exclude limit/offset
$summary = $stmt->fetch();
?>

<?php include '../includes/header_super_admin.php'; ?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- Breadcrumb -->
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="page-header-title">
                                    <h5 class="m-b-10">System Revenue</h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="#!">Financial</a></li>
                                    <li class="breadcrumb-item active"><a href="#!">System Revenue</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-xl-3 col-md-6">
                                <div class="card statustic-card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                                    <div class="flex items-center">
                                        <div class="bg-green-100 text-green-600 rounded-full p-3">
                                            <i class="feather icon-check-circle text-2xl"></i>
                                        </div>
                                        <div class="ml-4">
                                            <h5 class="text-2xl font-semibold text-gray-800 dark:text-white">$<?= number_format($summary['completed_amount'] ?? 0, 2) ?></h5>
                                            <span class="text-gray-600 dark:text-gray-300">Completed Revenue</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card statustic-card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                                    <div class="flex items-center">
                                        <div class="bg-yellow-100 text-yellow-600 rounded-full p-3">
                                            <i class="feather icon-clock text-2xl"></i>
                                        </div>
                                        <div class="ml-4">
                                            <h5 class="text-2xl font-semibold text-gray-800 dark:text-white">$<?= number_format($summary['pending_amount'] ?? 0, 2) ?></h5>
                                            <span class="text-gray-600 dark:text-gray-300">Pending Revenue</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card statustic-card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                                    <div class="flex items-center">
                                        <div class="bg-red-100 text-red-600 rounded-full p-3">
                                            <i class="feather icon-x-circle text-2xl"></i>
                                        </div>
                                        <div class="ml-4">
                                            <h5 class="text-2xl font-semibold text-gray-800 dark:text-white">$<?= number_format($summary['failed_amount'] ?? 0, 2) ?></h5>
                                            <span class="text-gray-600 dark:text-gray-300">Failed Transactions</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card statustic-card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                                    <div class="flex items-center">
                                        <div class="bg-blue-100 text-blue-600 rounded-full p-3">
                                            <i class="feather icon-layers text-2xl"></i>
                                        </div>
                                        <div class="ml-4">
                                            <h5 class="text-2xl font-semibold text-gray-800 dark:text-white"><?= $summary['total_count'] ?? 0 ?></h5>
                                            <span class="text-gray-600 dark:text-gray-300">Total Transactions</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Main Card -->
                        <div class="row">
                            <div class="col-xl-12">
                                <?php if (isset($_GET['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="feather icon-check-circle mr-2"></i>
                                    <?= htmlspecialchars($_GET['success']) ?>
                                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                                </div>
                                <?php endif; ?>

                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5>Revenue Records</h5>
                                        <button class="btn btn-primary" data-toggle="modal" data-target="#addRevenueModal">
                                            <i class="feather icon-plus mr-1"></i>Add Revenue
                                        </button>
                                    </div>

                                    <div class="card-body">
                                        <!-- Filters -->
                                        <form method="GET" class="mb-4">
                                            <div class="row">
                                                <div class="col-md-2">
                                                    <input type="text" class="form-control" name="search" 
                                                           placeholder="Search..." 
                                                           value="<?= htmlspecialchars($search_query) ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <select name="type" class="form-control">
                                                        <option value="">All Types</option>
                                                        <option value="subscription" <?= $revenue_type === 'subscription' ? 'selected' : '' ?>>Subscription</option>
                                                        <option value="addon" <?= $revenue_type === 'addon' ? 'selected' : '' ?>>Addon</option>
                                                        <option value="commission" <?= $revenue_type === 'commission' ? 'selected' : '' ?>>Commission</option>
                                                        <option value="fee" <?= $revenue_type === 'fee' ? 'selected' : '' ?>>Fee</option>
                                                        <option value="other" <?= $revenue_type === 'other' ? 'selected' : '' ?>>Other</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <select name="status" class="form-control">
                                                        <option value="">All Status</option>
                                                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                        <option value="failed" <?= $status_filter === 'failed' ? 'selected' : '' ?>>Failed</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="date" class="form-control" name="date_from" 
                                                           value="<?= htmlspecialchars($date_from) ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="date" class="form-control" name="date_to" 
                                                           value="<?= htmlspecialchars($date_to) ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                                                </div>
                                            </div>
                                        </form>

                                        <!-- Table -->
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Tenant</th>
                                                        <th>Type</th>
                                                        <th>Amount</th>
                                                        <th>Description</th>
                                                        <th>Status</th>
                                                        <th>Reference</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($revenues)): ?>
                                                        <?php foreach ($revenues as $rev): ?>
                                                        <tr>
                                                            <td><?= date('M d, Y', strtotime($rev['payment_date'])) ?></td>
                                                            <td><?= htmlspecialchars($rev['tenant_name'] ?? 'System') ?></td>
                                                            <td>
                                                                <span class="badge badge-info">
                                                                    <?= ucfirst(htmlspecialchars($rev['revenue_type'])) ?>
                                                                </span>
                                                            </td>
                                                            <td><strong>$<?= number_format($rev['amount'], 2) ?></strong></td>
                                                            <td><?= htmlspecialchars(substr($rev['description'] ?? '', 0, 40)) ?>...</td>
                                                            <td>
                                                                <?php 
                                                                $status_color = [
                                                                    'completed' => 'success',
                                                                    'pending' => 'warning',
                                                                    'failed' => 'danger'
                                                                ];
                                                                $color = $status_color[$rev['status']] ?? 'secondary';
                                                                ?>
                                                                <span class="badge badge-<?= $color ?>">
                                                                    <?= ucfirst($rev['status']) ?>
                                                                </span>
                                                            </td>
                                                            <td><?= htmlspecialchars($rev['reference_id'] ?? '-') ?></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#editRevenueModal" 
                                                                        onclick="loadRevenueDetails(<?= $rev['id'] ?>)">
                                                                    <i class="feather icon-edit"></i>
                                                                </button>
                                                                <a href="delete_system_revenue.php?id=<?= $rev['id'] ?>&csrf=<?= htmlspecialchars($_SESSION['csrf_token']) ?>" 
                                                                   class="btn btn-sm btn-danger" onclick="return confirm('Delete this revenue record?')">
                                                                    <i class="feather icon-trash-2"></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center text-muted">No revenue records found</td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Pagination -->
                                        <?php if ($total_pages > 1): ?>
                                        <nav class="mt-4">
                                            <ul class="pagination justify-content-center">
                                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search_query) ?>&type=<?= $revenue_type ?>&status=<?= $status_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">
                                                        <?= $i ?>
                                                    </a>
                                                </li>
                                                <?php endfor; ?>
                                            </ul>
                                        </nav>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Revenue Modal -->
    <div class="modal fade" id="addRevenueModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Add System Revenue</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form action="create_system_revenue.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Tenant *</label>
                            <select name="tenant_id" class="form-control" required>
                                <option value="">Select Tenant</option>
                                <?php foreach ($tenants as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Revenue Type *</label>
                            <select name="revenue_type" class="form-control" required>
                                <option value="">Select Type</option>
                                <option value="subscription">Subscription</option>
                                <option value="addon">Addon</option>
                                <option value="commission">Commission</option>
                                <option value="fee">Fee</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Amount *</label>
                                <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Currency *</label>
                                <select name="currency" class="form-control" required>
                                    <option value="USD">USD</option>
                                    <option value="AFS">AFS</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Payment Date *</label>
                                <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Status *</label>
                                <select name="status" class="form-control" required>
                                    <option value="completed">Completed</option>
                                    <option value="pending">Pending</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Revenue description"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Reference ID</label>
                            <input type="text" name="reference_id" class="form-control" placeholder="e.g., subscription ID or transaction ID">
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Revenue</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Revenue Modal -->
    <div class="modal fade" id="editRevenueModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">Edit Revenue</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form action="update_system_revenue.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="id" id="edit_revenue_id">
                    <div class="modal-body" id="editRevenueBody">
                        <!-- Loaded via AJAX -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Revenue</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
function loadRevenueDetails(revenueId) {
    document.getElementById('edit_revenue_id').value = revenueId;
}
</script>

</body>
</html>
