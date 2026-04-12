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
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once '../includes/db.php';

// Pagination and search
$items_per_page = 15;
$current_page = intval($_GET['page'] ?? 1);
$search_query = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build WHERE clause
$where_conditions = [];
$filter_params = [];

if (!empty($search_query)) {
    $where_conditions[] = "(description LIKE ? OR reference_number LIKE ?)";
    $search_term = "%{$search_query}%";
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
}

if (!empty($category_filter)) {
    $where_conditions[] = "category_id = ?";
    $filter_params[] = intval($category_filter);
}

if (!empty($date_from)) {
    $where_conditions[] = "date >= ?";
    $filter_params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "date <= ?";
    $filter_params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total items
$count_query = "SELECT COUNT(*) as total FROM system_expenses {$where_clause}";
$stmt = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Fetch expenses with categories
$query = "SELECT se.*, sec.name as category_name, u.name as created_by_name
          FROM system_expenses se
          LEFT JOIN system_expense_categories sec ON se.category_id = sec.id
          LEFT JOIN users u ON se.created_by = u.id
          {$where_clause}
          ORDER BY se.date DESC LIMIT ? OFFSET ?";

$query_params = $filter_params;
$query_params[] = $items_per_page;
$query_params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($query_params);
$expenses = $stmt->fetchAll();

// Fetch categories for filter dropdown
$stmt = $pdo->query("SELECT id, name FROM system_expense_categories ORDER BY name");
$categories = $stmt->fetchAll();

// Calculate summary
$summary_where = !empty($where_conditions) ? "WHERE " . implode(" AND ", array_slice($where_conditions, 0, count($where_conditions))) : "";
$summary_query = "SELECT 
    COUNT(*) as total_count,
    SUM(amount) as total_amount,
    AVG(amount) as avg_amount,
    MIN(amount) as min_amount,
    MAX(amount) as max_amount
    FROM system_expenses {$summary_where}";
$stmt = $pdo->prepare($summary_query);
$stmt->execute(array_slice($filter_params, 0, count($filter_params) - 2)); // Exclude limit/offset params
$summary = $stmt->fetch();
?>

<?php include '../includes/header_super_admin.php'; ?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="page-header-title">
                                    <h5 class="m-b-10">System Expenses</h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="#!">Financial</a></li>
                                    <li class="breadcrumb-item active"><a href="#!">System Expenses</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->
                
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-xl-3 col-md-6">
                                <div class="card statustic-card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                                    <div class="flex items-center">
                                        <div class="bg-purple-100 text-purple-600 rounded-full p-3">
                                            <i class="feather icon-credit-card text-2xl"></i>
                                        </div>
                                        <div class="ml-4">
                                            <h5 class="text-2xl font-semibold text-gray-800 dark:text-white">$<?= number_format($summary['total_amount'] ?? 0, 2) ?></h5>
                                            <span class="text-gray-600 dark:text-gray-300">Total Expenses</span>
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
                                            <span class="text-gray-600 dark:text-gray-300">Total Records</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card statustic-card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                                    <div class="flex items-center">
                                        <div class="bg-green-100 text-green-600 rounded-full p-3">
                                            <i class="feather icon-trending-up text-2xl"></i>
                                        </div>
                                        <div class="ml-4">
                                            <h5 class="text-2xl font-semibold text-gray-800 dark:text-white">$<?= number_format($summary['avg_amount'] ?? 0, 2) ?></h5>
                                            <span class="text-gray-600 dark:text-gray-300">Average Expense</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card statustic-card bg-white dark:bg-gray-800 shadow-md rounded-lg p-6">
                                    <div class="flex items-center">
                                        <div class="bg-red-100 text-red-600 rounded-full p-3">
                                            <i class="feather icon-alert-circle text-2xl"></i>
                                        </div>
                                        <div class="ml-4">
                                            <h5 class="text-2xl font-semibold text-gray-800 dark:text-white">$<?= number_format($summary['max_amount'] ?? 0, 2) ?></h5>
                                            <span class="text-gray-600 dark:text-gray-300">Highest Expense</span>
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
                                
                                <?php if (isset($_GET['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="feather icon-alert-circle mr-2"></i>
                                    <?= htmlspecialchars($_GET['error']) ?>
                                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                                </div>
                                <?php endif; ?>

                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5>Expense Records</h5>
                                        <button class="btn btn-primary" data-toggle="modal" data-target="#addExpenseModal">
                                            <i class="feather icon-plus mr-1"></i>Add Expense
                                        </button>
                                    </div>
                                    
                                    <div class="card-body">
                                        <!-- Filters -->
                                        <form method="GET" class="mb-4">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <input type="text" class="form-control" name="search" 
                                                           placeholder="Search description..." 
                                                           value="<?= htmlspecialchars($search_query) ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <select name="category" class="form-control">
                                                        <option value="">All Categories</option>
                                                        <?php foreach ($categories as $cat): ?>
                                                        <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($cat['name']) ?>
                                                        </option>
                                                        <?php endforeach; ?>
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
                                                    <a href="manage_system_expenses.php" class="btn btn-secondary w-100 mt-2">Reset</a>
                                                </div>
                                            </div>
                                        </form>

                                        <!-- Table -->
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Category</th>
                                                        <th>Description</th>
                                                        <th>Amount</th>
                                                        <th>Payment Method</th>
                                                        <th>Reference</th>
                                                        <th>Created By</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($expenses)): ?>
                                                        <?php foreach ($expenses as $expense): ?>
                                                        <tr>
                                                            <td><?= date('M d, Y', strtotime($expense['date'])) ?></td>
                                                            <td>
                                                                <span class="badge badge-primary">
                                                                    <?= htmlspecialchars($expense['category_name']) ?>
                                                                </span>
                                                            </td>
                                                            <td><?= htmlspecialchars(substr($expense['description'], 0, 50)) ?>...</td>
                                                            <td><strong>$<?= number_format($expense['amount'], 2) ?></strong></td>
                                                            <td><?= htmlspecialchars($expense['payment_method'] ?? '-') ?></td>
                                                            <td><?= htmlspecialchars($expense['reference_number'] ?? '-') ?></td>
                                                            <td><?= htmlspecialchars($expense['created_by_name'] ?? 'System') ?></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#editExpenseModal" 
                                                                        onclick="loadExpenseDetails(<?= $expense['id'] ?>)">
                                                                    <i class="feather icon-edit"></i>
                                                                </button>
                                                                <a href="delete_system_expense.php?id=<?= $expense['id'] ?>&csrf=<?= htmlspecialchars($_SESSION['csrf_token']) ?>" 
                                                                   class="btn btn-sm btn-danger" onclick="return confirm('Delete this expense?')">
                                                                    <i class="feather icon-trash-2"></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center text-muted">No expenses found</td>
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
                                                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search_query) ?>&category=<?= $category_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">
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

    <!-- Add Expense Modal -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Add System Expense</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form action="create_system_expense.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Date *</label>
                                <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Amount *</label>
                                <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Currency *</label>
                            <select name="currency" class="form-control" required>
                                <option value="USD">USD</option>
                                <option value="AFS">AFS</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Description *</label>
                            <textarea name="description" class="form-control" rows="3" required placeholder="Detailed description of expense"></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Payment Method</label>
                                <input type="text" name="payment_method" class="form-control" placeholder="e.g., Bank Transfer, Cash">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Reference Number</label>
                                <input type="text" name="reference_number" class="form-control" placeholder="e.g., INV-2025-001">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Receipt File</label>
                            <input type="file" name="receipt_file" class="form-control" accept="image/*,.pdf">
                            <small class="form-text text-muted">Optional: Upload receipt/invoice</small>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Expense</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Expense Modal -->
    <div class="modal fade" id="editExpenseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">Edit Expense</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form action="update_system_expense.php" method="POST" id="editExpenseForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="id" id="edit_expense_id">
                    <div class="modal-body" id="editExpenseBody">
                        <!-- Loaded via JavaScript -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Expense</button>
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
function loadExpenseDetails(expenseId) {
    // This would fetch expense details via AJAX
    // For now, a placeholder
    document.getElementById('edit_expense_id').value = expenseId;
}
</script>

</body>
</html>
