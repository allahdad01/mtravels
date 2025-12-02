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
if ($user_branch_id) {
    $branch_query = "SELECT name FROM branches WHERE id = ? AND tenant_id = ?";
    $stmt = $pdo->prepare($branch_query);
    $stmt->execute([$user_branch_id, $tenant_id]);
    $branch_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($branch_data) {
        $current_branch_name = $branch_data['name'];
    }
}

// Build query for salary management
$query = "SELECT sm.*,
                 u.name as employee_name,
                 u.email as employee_email,
                 u.phone as employee_phone,
                 u.hire_date,
                 b.name as branch_name,
                 COUNT(sp.id) as payment_count,
                 COALESCE(SUM(sp.amount), 0) as total_paid
          FROM salary_management sm
          LEFT JOIN users u ON sm.user_id = u.id
          LEFT JOIN branches b ON u.branch_id = b.id
          LEFT JOIN salary_payments sp ON sm.user_id = sp.user_id AND sp.tenant_id = sm.tenant_id
          WHERE sm.tenant_id = ?";

// Add branch filtering
if ($selected_branch !== 'all') {
    $query .= " AND u.branch_id = ?";
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
}

// Group by and ordering with pagination
$query .= " GROUP BY sm.id ORDER BY u.name ASC LIMIT ? OFFSET ?";

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
$salaries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM salary_management sm
                LEFT JOIN users u ON sm.user_id = u.id
                WHERE sm.tenant_id = ?";
$count_params = [$tenant_id];

if ($selected_branch !== 'all') {
    $count_query .= " AND u.branch_id = ?";
    $count_params[] = $selected_branch;
}

if (!empty($search)) {
    $count_query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_salaries = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_salaries / $results_per_page);

// Get summary data
$summary_query = "SELECT
    COUNT(*) as total_employees,
    SUM(sm.base_salary) as total_salary_budget,
    AVG(sm.base_salary) as avg_salary,
    COUNT(CASE WHEN sm.status = 'active' THEN 1 END) as active_employees
FROM salary_management sm
LEFT JOIN users u ON sm.user_id = u.id
WHERE sm.tenant_id = ?";

$summary_params = [$tenant_id];

if ($selected_branch !== 'all') {
    $summary_query .= " AND u.branch_id = ?";
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
                                    <h5 class="m-b-10">Salary Management</h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="javascript:">Salary Management</a></li>
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
                                                <p class="m-b-5">Total Employees</p>
                                                <h4 class="m-b-0"><?= number_format($summary['total_employees'] ?? 0) ?></h4>
                                            </div>
                                            <div class="col col-auto text-right">
                                                <i class="fas fa-users f-50 text-c-blue"></i>
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
                                                <p class="m-b-5">Active Employees</p>
                                                <h4 class="m-b-0"><?= number_format($summary['active_employees'] ?? 0) ?></h4>
                                            </div>
                                            <div class="col col-auto text-right">
                                                <i class="fas fa-user-check f-50 text-c-green"></i>
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
                                                <p class="m-b-5">Total Salary Budget</p>
                                                <h4 class="m-b-0">$<?= number_format($summary['total_salary_budget'] ?? 0, 2) ?></h4>
                                            </div>
                                            <div class="col col-auto text-right">
                                                <i class="fas fa-dollar-sign f-50 text-c-red"></i>
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
                                                <p class="m-b-5">Average Salary</p>
                                                <h4 class="m-b-0">$<?= number_format($summary['avg_salary'] ?? 0, 2) ?></h4>
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
                                                        <input type="text" id="searchInput" class="form-control" placeholder="Search by employee name, email, or phone" value="<?= htmlspecialchars($search) ?>">
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

                                <!-- Salary Management Table Section -->
                                <div class="card">
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" width="50">#</th>
                                                        <th width="100">Action</th>
                                                        <th>Employee Details</th>
                                                        <th>Salary Information</th>
                                                        <th>Branch</th>
                                                        <th>Employment</th>
                                                        <th>Payment History</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="salaryTable">
                                                    <?php
                                                    $counter = $offset + 1;
                                                    foreach ($salaries as $salary):
                                                    ?>
                                                    <tr>
                                                        <td class="text-center"><?= $counter++ ?></td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                                    <i class="feather icon-more-vertical"></i>
                                                                </button>
                                                                <div class="dropdown-menu dropdown-menu-right">
                                                                    <button class="dropdown-item view-details" data-salary='<?= htmlspecialchars(json_encode($salary)) ?>'>
                                                                        <i class="feather icon-eye text-primary mr-2"></i> View Details
                                                                    </button>
                                                                    <button class="dropdown-item view-payments" data-user-id="<?= $salary['user_id'] ?>" data-employee-name="<?= htmlspecialchars($salary['employee_name']) ?>">
                                                                        <i class="feather icon-credit-card text-info mr-2"></i> View Payments
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="employee-info">
                                                                <div class="employee-info__name">
                                                                    <strong><?= htmlspecialchars($salary['employee_name']) ?></strong>
                                                                </div>
                                                                <div class="employee-info__email">
                                                                    <small class="text-muted">
                                                                        <i class="feather icon-mail mr-1"></i>
                                                                        <?= htmlspecialchars($salary['employee_email']) ?>
                                                                    </small>
                                                                </div>
                                                                <?php if (!empty($salary['employee_phone'])): ?>
                                                                <div class="employee-info__phone">
                                                                    <small class="text-muted">
                                                                        <i class="feather icon-phone mr-1"></i>
                                                                        <?= htmlspecialchars($salary['employee_phone']) ?>
                                                                    </small>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="salary-info">
                                                                <div class="salary-info__amount">
                                                                    <strong class="text-success">
                                                                        $<?= number_format($salary['base_salary'], 2) ?>
                                                                    </strong>
                                                                    <small class="text-muted">/<?= ucfirst($salary['currency']) ?></small>
                                                                </div>
                                                                <div class="salary-info__payment-day">
                                                                    <small class="text-muted">
                                                                        Paid on day: <?= $salary['payment_day'] ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <span class="badge badge-secondary">
                                                                <?= htmlspecialchars($salary['branch_name'] ?? 'N/A') ?>
                                                            </span>
                                                        </td>

                                                        <td>
                                                            <div class="employment-info">
                                                                <div class="employment-info__status">
                                                                    <span class="badge badge-<?= $salary['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                                        <?= ucfirst(htmlspecialchars($salary['status'])) ?>
                                                                    </span>
                                                                </div>
                                                                <div class="employment-info__hire-date">
                                                                    <small class="text-muted">
                                                                        Hired: <?= $salary['hire_date'] ? date('M d, Y', strtotime($salary['hire_date'])) : 'N/A' ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="payment-history">
                                                                <div class="payment-history__count">
                                                                    <i class="feather icon-credit-card text-muted mr-1"></i>
                                                                    <?= number_format($salary['payment_count']) ?> payments
                                                                </div>
                                                                <div class="payment-history__total">
                                                                    <small class="text-muted">
                                                                        Total: $<?= number_format($salary['total_paid'], 2) ?>
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
                                                    Showing <?= min(($page - 1) * $results_per_page + 1, $total_salaries) ?> to <?= min($page * $results_per_page, $total_salaries) ?> of <?= $total_salaries ?> employees
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

<!-- Salary Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white border-0">
                <h5 class="modal-title">
                    <i class="feather icon-user mr-2"></i>Salary Details
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
                            <div class="salary-summary">
                                <div class="salary-summary__label">Base Salary</div>
                                <div class="salary-summary__amount" id="base-salary">-</div>
                                <div class="salary-summary__currency" id="salary-currency">-</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="payment-summary">
                                <div class="payment-summary__label">Payment History</div>
                                <div class="payment-summary__count" id="payment-count">-</div>
                                <div class="payment-summary__total" id="total-paid">-</div>
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
                        <a class="nav-link" id="details-employment-tab" data-toggle="tab" href="#details-employment" role="tab">
                            <i class="feather icon-briefcase mr-2"></i>Employment
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
                                        <h6 class="card-subtitle mb-3 text-muted">Employee Information</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Employee Name</span>
                                            <strong id="employee-name">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Email</span>
                                            <strong id="employee-email">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Phone</span>
                                            <strong id="employee-phone">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Branch</span>
                                            <strong id="employee-branch">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted">Salary Details</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Base Salary</span>
                                            <strong id="salary-amount">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Currency</span>
                                            <strong id="salary-currency-detail">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Payment Day</span>
                                            <strong id="payment-day">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Status</span>
                                            <strong id="salary-status">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employment Tab -->
                    <div class="tab-pane fade" id="details-employment" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted">Employment Information</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Hire Date</span>
                                    <strong id="hire-date">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Employment Status</span>
                                    <strong id="employment-status">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total Payments Made</span>
                                    <strong id="total-payments">-</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Total Amount Paid</span>
                                    <strong id="total-amount-paid">-</strong>
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

<!-- Payment History Modal -->
<div class="modal fade" id="paymentsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white border-0">
                <h5 class="modal-title">
                    <i class="feather icon-credit-card mr-2"></i>Payment History - <span id="employee-name-header"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="paymentsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-2">Loading payment history...</p>
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
// Handle branch selection
document.getElementById('branchSelect').addEventListener('change', function() {
    const branchValue = this.value;
    const searchValue = document.getElementById('searchInput').value.trim();

    let url = '?branch=' + encodeURIComponent(branchValue);
    if (searchValue) {
        url += '&search=' + encodeURIComponent(searchValue);
    }

    window.location.href = url;
});

// Handle search functionality
document.getElementById('searchBtn').addEventListener('click', function() {
    const searchValue = document.getElementById('searchInput').value.trim();
    const branchValue = document.getElementById('branchSelect').value;

    let url = '?branch=' + encodeURIComponent(branchValue);
    if (searchValue) {
        url += '&search=' + encodeURIComponent(searchValue);
    }

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
        const salaryData = JSON.parse(this.getAttribute('data-salary'));

        // Populate modal with salary data
        document.getElementById('base-salary').textContent = '$' + parseFloat(salaryData.base_salary || 0).toFixed(2);
        document.getElementById('salary-currency').textContent = salaryData.currency;
        document.getElementById('payment-count').textContent = salaryData.payment_count + ' payments';
        document.getElementById('total-paid').textContent = '$' + parseFloat(salaryData.total_paid || 0).toFixed(2);

        document.getElementById('employee-name').textContent = salaryData.employee_name;
        document.getElementById('employee-email').textContent = salaryData.employee_email || 'N/A';
        document.getElementById('employee-phone').textContent = salaryData.employee_phone || 'N/A';
        document.getElementById('employee-branch').textContent = salaryData.branch_name || 'N/A';

        document.getElementById('salary-amount').textContent = '$' + parseFloat(salaryData.base_salary || 0).toFixed(2);
        document.getElementById('salary-currency-detail').textContent = salaryData.currency;
        document.getElementById('payment-day').textContent = salaryData.payment_day;
        document.getElementById('salary-status').textContent = salaryData.status.charAt(0).toUpperCase() + salaryData.status.slice(1);

        document.getElementById('hire-date').textContent = salaryData.hire_date ? new Date(salaryData.hire_date).toLocaleDateString() : 'N/A';
        document.getElementById('employment-status').textContent = salaryData.status.charAt(0).toUpperCase() + salaryData.status.slice(1);
        document.getElementById('total-payments').textContent = salaryData.payment_count;
        document.getElementById('total-amount-paid').textContent = '$' + parseFloat(salaryData.total_paid || 0).toFixed(2);

        // Show modal
        $('#detailsModal').modal('show');
    });
});

// Handle view payments modal
document.querySelectorAll('.view-payments').forEach(button => {
    button.addEventListener('click', function() {
        const userId = this.getAttribute('data-user-id');
        const employeeName = this.getAttribute('data-employee-name');

        document.getElementById('employee-name-header').textContent = employeeName;

        // Load payments via AJAX
        fetch('get_employee_payments.php?user_id=' + userId)
            .then(response => response.text())
            .then(data => {
                document.getElementById('paymentsContent').innerHTML = data;
            })
            .catch(error => {
                document.getElementById('paymentsContent').innerHTML = '<div class="alert alert-danger">Error loading payments: ' + error.message + '</div>';
            });

        // Show modal
        $('#paymentsModal').modal('show');
    });
});
</script>