<?php
include 'header.php';

// Get tenant and user info
$tenant_id = $_SESSION['tenant_id'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? null;

// Get search and branch parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_branch = isset($_GET['branch']) ? $_GET['branch'] : ($user_branch_id ? $user_branch_id : 'all');

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 25;
$offset = ($page - 1) * $results_per_page;

// Get current branch information
$current_branch_name = "All Branches";
if ($selected_branch !== 'all') {
    $branch_query = "SELECT name FROM branches WHERE id = ? AND tenant_id = ?";
    $stmt = $pdo->prepare($branch_query);
    $stmt->execute([$selected_branch, $tenant_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $current_branch_name = $branch_data['name'];
    }
}

// Build query for expenses
$query = "SELECT e.*,
                 ec.name as category_name,
                 
                 b.name as branch_name
          FROM expenses e
          LEFT JOIN expense_categories ec ON e.category_id = ec.id
          LEFT JOIN branches b ON e.branch_id = b.id
          WHERE e.tenant_id = ?";

// Add branch filtering
if ($selected_branch !== 'all') {
    $query .= " AND e.branch_id = ?";
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (e.description LIKE ? OR ec.name LIKE ? OR u.name LIKE ?)";
}

// Group by and ordering with pagination
$query .= " ORDER BY e.date DESC LIMIT ? OFFSET ?";

// Prepare parameters
$params = [$tenant_id];

if ($selected_branch !== 'all') {
    $params[] = $selected_branch;
}

if (!empty($search)) {
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$params[] = $results_per_page;
$params[] = $offset;

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM expenses e
                LEFT JOIN expense_categories ec ON e.category_id = ec.id
                WHERE e.tenant_id = ?";
$count_params = [$tenant_id];

if ($selected_branch !== 'all') {
    $count_query .= " AND e.branch_id = ?";
    $count_params[] = $selected_branch;
}

if (!empty($search)) {
    $count_query .= " AND (e.description LIKE ? OR ec.name LIKE ? OR u.name LIKE ?)";
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_expenses = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_expenses / $results_per_page);

// Get summary data
$summary_query = "SELECT
    COUNT(*) as total_expenses,
    SUM(CASE WHEN currency = 'USD' THEN amount ELSE 0 END) as total_usd_expenses,
    SUM(CASE WHEN currency = 'AFS' THEN amount ELSE 0 END) as total_afs_expenses,
    AVG(amount) as avg_expense_amount
FROM expenses
WHERE tenant_id = ?";

$summary_params = [$tenant_id];

if ($selected_branch !== 'all') {
    $summary_query .= " AND branch_id = ?";
    $summary_params[] = $selected_branch;
}

$summary_stmt = $pdo->prepare($summary_query);
$summary_stmt->execute($summary_params);
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
?>

<style>
/* Apply gradient background to card headers matching the sidebar */
.card-header {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
    color: #ffffff !important;
    border-bottom: none !important;
}

.card-header h5 {
    color: #ffffff !important;
    margin-bottom: 0 !important;
}

.card-header .card-header-right {
    color: #ffffff !important;
}

.card-header .card-header-right .btn {
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
}

.card-header .card-header-right .btn:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
}
</style>

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
                                    <h5 class="m-b-10">Expenses</h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="javascript:">Expenses</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="row">
                            <!-- Summary Cards -->
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-c-blue text-white">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <p class="m-b-5">Total Expenses</p>
                                                <h4 class="m-b-0"><?= number_format($summary['total_expenses'] ?? 0) ?></h4>
                                            </div>
                                            <div class="col col-auto text-right">
                                                <i class="fas fa-receipt f-50 text-c-blue"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-c-green text-white">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <p class="m-b-5">Total USD Expenses</p>
                                                <h4 class="m-b-0">$<?= number_format($summary['total_usd_expenses'] ?? 0, 2) ?></h4>
                                            </div>
                                            <div class="col col-auto text-right">
                                                <i class="fas fa-dollar-sign f-50 text-c-green"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-c-red text-white">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <p class="m-b-5">Total AFS Expenses</p>
                                                <h4 class="m-b-0">AFS <?= number_format($summary['total_afs_expenses'] ?? 0, 2) ?></h4>
                                            </div>
                                            <div class="col col-auto text-right">
                                                <i class="fas fa-money-bill-wave f-50 text-c-red"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-c-yellow text-white">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <p class="m-b-5">Average Expense</p>
                                                <h4 class="m-b-0">$<?= number_format($summary['avg_expense_amount'] ?? 0, 2) ?></h4>
                                            </div>
                                            <div class="col col-auto text-right">
                                                <i class="fas fa-chart-line f-50 text-c-yellow"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-12">
                                <!-- Branch and Search Section -->
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <div class="branch-selector">
                                                    <label for="branchSelect" class="form-label">Select Branch:</label>
                                                    <select id="branchSelect" class="form-control">
                                                        <option value="all" <?= $selected_branch === 'all' ? 'selected' : '' ?>>All Branches</option>
                                                        <?php
                                                        try {
                                                            $branch_stmt = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND status = 'active' ORDER BY name");
                                                            $branch_stmt->execute([$tenant_id]);
                                                            $branches = $branch_stmt->fetchAll(PDO::FETCH_ASSOC);

                                                            foreach ($branches as $branch) {
                                                                $selected = ($selected_branch == $branch['id']) ? 'selected' : '';
                                                                echo '<option value="' . $branch['id'] . '" ' . $selected . '>' . htmlspecialchars($branch['name']) . '</option>';
                                                            }
                                                        } catch (PDOException $e) {
                                                            error_log("Error fetching branches: " . $e->getMessage());
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="search-box">
                                                    <div class="input-group">
                                                        <input type="text" id="searchInput" class="form-control" placeholder="Search by description, category" value="<?= htmlspecialchars($search) ?>">
                                                        <div class="input-group-append">
                                                            <button class="btn btn-primary" type="button" id="searchBtn">
                                                                <i class="feather icon-search"></i> Search
                                                            </button>
                                                            <?php if (!empty($search)): ?>
                                                            <a href="?branch=<?= urlencode($selected_branch) ?>" class="btn btn-secondary">
                                                                <i class="feather icon-x"></i> Clear
                                                            </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Expenses Table Section -->
                                <div class="card">
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" width="50">#</th>
                                                        <th width="100">Action</th>
                                                        <th>Expense Details</th>
                                                        <th>Category & Amount</th>
                                                        <th>Branch</th>
                                                        <th>Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="expenseTable">
                                                    <?php
                                                    $counter = $offset + 1;
                                                    foreach ($expenses as $expense):
                                                    ?>
                                                    <tr>
                                                        <td class="text-center"><?= $counter++ ?></td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                                    <i class="feather icon-more-vertical"></i>
                                                                </button>
                                                                <div class="dropdown-menu dropdown-menu-right">
                                                                    <button class="dropdown-item view-details" data-expense='<?= htmlspecialchars(json_encode($expense)) ?>'>
                                                                        <i class="feather icon-eye text-primary mr-2"></i> View Details
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="expense-info">
                                                                <div class="expense-info__description">
                                                                    <strong><?= htmlspecialchars($expense['description']) ?></strong>
                                                                </div>
                                                                <?php if (!empty($expense['receipt'])): ?>
                                                                <div class="expense-info__receipt">
                                                                    <small class="text-muted">Receipt: <?= htmlspecialchars($expense['receipt']) ?></small>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="category-amount-info">
                                                                <div class="category-amount-info__category">
                                                                    <span class="badge badge-primary">
                                                                        <?= htmlspecialchars($expense['category_name'] ?? 'Uncategorized') ?>
                                                                    </span>
                                                                </div>
                                                                <div class="category-amount-info__amount">
                                                                    <strong class="text-<?= $expense['currency'] === 'USD' ? 'success' : 'info' ?>">
                                                                        <?= $expense['currency'] === 'USD' ? '$' : 'AFS ' ?>
                                                                        <?= number_format($expense['amount'], 2) ?>
                                                                    </strong>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <span class="badge badge-secondary">
                                                                <?= htmlspecialchars($expense['branch_name'] ?? 'N/A') ?>
                                                            </span>
                                                        </td>

                                                        <td>
                                                            <div class="date-info">
                                                                <div class="date-info__date">
                                                                    <?= date('d/m/Y', strtotime($expense['date'])) ?>
                                                                </div>
                                                                <div class="date-info__time">
                                                                    <small class="text-muted">
                                                                        <?= date('H:i', strtotime($expense['created_at'])) ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Pagination -->
                                        <div class="card-footer bg-white">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="text-muted">
                                                    Showing <?= min(($page - 1) * $results_per_page + 1, $total_expenses) ?> to <?= min($page * $results_per_page, $total_expenses) ?> of <?= $total_expenses ?> expenses
                                                </div>
                                                <nav aria-label="Page navigation">
                                                    <ul class="pagination mb-0">
                                                        <?php if ($page > 1): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=1&search=<?= urlencode($search) ?>&branch=<?= urlencode($selected_branch) ?>">
                                                                    <i class="feather icon-chevrons-left"></i>
                                                                </a>
                                                            </li>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&branch=<?= urlencode($selected_branch) ?>">
                                                                    <i class="feather icon-chevron-left"></i>
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>

                                                        <?php
                                                        $start_page = max(1, $page - 2);
                                                        $end_page = min($total_pages, $page + 2);

                                                        if ($start_page > 1) {
                                                            echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&branch=' . urlencode($selected_branch) . '">1</a></li>';
                                                            if ($start_page > 2) {
                                                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                            }
                                                        }

                                                        for ($i = $start_page; $i <= $end_page; $i++) {
                                                            echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                                                <a class="page-link" href="?page=' . $i . '&search=' . urlencode($search) . '&branch=' . urlencode($selected_branch) . '">' . $i . '</a>
                                                            </li>';
                                                        }

                                                        if ($end_page < $total_pages) {
                                                            if ($end_page < $total_pages - 1) {
                                                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                            }
                                                            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&branch=' . urlencode($selected_branch) . '">' . $total_pages . '</a></li>';
                                                        }
                                                        ?>

                                                        <?php if ($page < $total_pages): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&branch=<?= urlencode($selected_branch) ?>">
                                                                    <i class="feather icon-chevron-right"></i>
                                                                </a>
                                                            </li>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&branch=<?= urlencode($selected_branch) ?>">
                                                                    <i class="feather icon-chevrons-right"></i>
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </nav>
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
    </div>
</div>

<!-- Expense Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white border-0">
                <h5 class="modal-title">
                    <i class="feather icon-file-text mr-2"></i>Expense Details
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <!-- Top Summary Card -->
                <div class="bg-light p-4 border-bottom">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="amount-summary">
                                <div class="amount-summary__label">Expense Amount</div>
                                <div class="amount-summary__amount" id="expense-amount">-</div>
                                <div class="amount-summary__currency" id="expense-currency">-</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="date-summary">
                                <div class="date-summary__label">Expense Date</div>
                                <div class="date-summary__date" id="expense-date">-</div>
                                <div class="date-summary__created">Created: <span id="created-date">-</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-pills nav-fill p-3" id="detailsTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="details-summary-tab" data-toggle="tab" href="#details-summary" role="tab">
                            <i class="feather icon-info mr-2"></i>Summary
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="details-additional-tab" data-toggle="tab" href="#details-additional" role="tab">
                            <i class="feather icon-file mr-2"></i>Additional Info
                        </a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content p-4">
                    <!-- Summary Tab -->
                    <div class="tab-pane fade show active" id="details-summary" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted">Expense Information</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Description</span>
                                            <strong id="expense-description">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Category</span>
                                            <strong id="expense-category">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Branch</span>
                                            <strong id="expense-branch">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Created By</span>
                                            <strong id="expense-created-by">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted">Financial Details</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Amount</span>
                                            <strong id="expense-amount-detail">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Currency</span>
                                            <strong id="expense-currency-detail">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Main Account</span>
                                            <strong id="expense-main-account">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Receipt Number</span>
                                            <strong id="expense-receipt">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Info Tab -->
                    <div class="tab-pane fade" id="details-additional" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted">Additional Information</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Expense ID</span>
                                    <strong id="expense-id">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Created At</span>
                                    <strong id="expense-created-at">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Updated At</span>
                                    <strong id="expense-updated-at">-</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Receipt File</span>
                                    <strong id="expense-receipt-file">-</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
// Handle search functionality
document.getElementById('searchBtn').addEventListener('click', function() {
    const searchValue = document.getElementById('searchInput').value.trim();
    const branchValue = document.getElementById('branchSelect').value;

    let url = '?';
    const params = [];
    if (branchValue) {
        params.push('branch=' + encodeURIComponent(branchValue));
    }
    if (searchValue) {
        params.push('search=' + encodeURIComponent(searchValue));
    }
    url += params.join('&');

    window.location.href = url;
});

// Handle branch selector change
document.getElementById('branchSelect').addEventListener('change', function() {
    const branchValue = this.value;
    const searchValue = document.getElementById('searchInput').value.trim();

    let url = '?';
    const params = [];
    if (branchValue) {
        params.push('branch=' + encodeURIComponent(branchValue));
    }
    if (searchValue) {
        params.push('search=' + encodeURIComponent(searchValue));
    }
    url += params.join('&');

    window.location.href = url;
});

// Handle enter key in search input
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('searchBtn').click();
    }
});

// Handle view details modal
document.querySelectorAll('.view-details').forEach(button => {
    button.addEventListener('click', function() {
        const expenseData = JSON.parse(this.getAttribute('data-expense'));

        // Populate modal with expense data
        document.getElementById('expense-amount').textContent = (expenseData.currency === 'USD' ? '$' : 'AFS ') + parseFloat(expenseData.amount || 0).toFixed(2);
        document.getElementById('expense-currency').textContent = expenseData.currency;
        document.getElementById('expense-date').textContent = expenseData.date ? new Date(expenseData.date).toLocaleDateString() : 'N/A';
        document.getElementById('created-date').textContent = expenseData.created_at ? new Date(expenseData.created_at).toLocaleString() : 'N/A';

        document.getElementById('expense-description').textContent = expenseData.description || 'N/A';
        document.getElementById('expense-category').textContent = expenseData.category_name || 'Uncategorized';
        document.getElementById('expense-branch').textContent = expenseData.branch_name || 'N/A';

        document.getElementById('expense-amount-detail').textContent = (expenseData.currency === 'USD' ? '$' : 'AFS ') + parseFloat(expenseData.amount || 0).toFixed(2);
        document.getElementById('expense-currency-detail').textContent = expenseData.currency;
        document.getElementById('expense-main-account').textContent = expenseData.main_account_name || 'N/A';
        document.getElementById('expense-receipt').textContent = expenseData.receipt || 'N/A';

        document.getElementById('expense-id').textContent = expenseData.id;
        document.getElementById('expense-created-at').textContent = expenseData.created_at ? new Date(expenseData.created_at).toLocaleString() : 'N/A';
        document.getElementById('expense-updated-at').textContent = expenseData.updated_at ? new Date(expenseData.updated_at).toLocaleString() : 'N/A';
        document.getElementById('expense-receipt-file').textContent = expenseData.receipt_file || 'No file attached';

        // Show modal
        $('#detailsModal').modal('show');
    });
});
</script>