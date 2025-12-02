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

// Build query for debtors
$query = "SELECT d.*,
                 ma.name as main_account_name,
                 b.name as branch_name,
                 COUNT(dt.id) as transaction_count,
                 COALESCE(SUM(CASE WHEN dt.transaction_type = 'debit' THEN dt.amount ELSE -dt.amount END), 0) as current_balance
          FROM debtors d
          LEFT JOIN main_account ma ON d.main_account_id = ma.id
          LEFT JOIN branches b ON d.branch_id = b.id
          LEFT JOIN debtor_transactions dt ON d.id = dt.debtor_id AND dt.tenant_id = d.tenant_id
          WHERE d.tenant_id = ?";

// Add branch filtering
if ($selected_branch !== 'all') {
    $query .= " AND d.branch_id = ?";
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (d.name LIKE ? OR d.email LIKE ? OR d.phone LIKE ?)";
}

// Group by and ordering with pagination
$query .= " GROUP BY d.id ORDER BY d.name ASC LIMIT ? OFFSET ?";

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
$debtors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM debtors d WHERE d.tenant_id = ?";
$count_params = [$tenant_id];

if ($selected_branch !== 'all') {
    $count_query .= " AND d.branch_id = ?";
    $count_params[] = $selected_branch;
}

if (!empty($search)) {
    $count_query .= " AND (d.name LIKE ? OR d.email LIKE ? OR d.phone LIKE ?)";
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_debtors = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_debtors / $results_per_page);

// Get summary data
$summary_query = "SELECT
    COUNT(*) as total_debtors,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_debtors,
    COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_debtors,
    SUM(balance) as total_outstanding,
    AVG(balance) as avg_debt_amount
FROM debtors
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
                                    <h5 class="m-b-10">Debtors Management</h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="javascript:">Debtors</a></li>
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
                                                <p class="m-b-5">Total Debtors</p>
                                                <h4 class="m-b-0"><?= number_format($summary['total_debtors'] ?? 0) ?></h4>
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
                                                <p class="m-b-5">Active Debtors</p>
                                                <h4 class="m-b-0"><?= number_format($summary['active_debtors'] ?? 0) ?></h4>
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
                                                <p class="m-b-5">Total Outstanding</p>
                                                <h4 class="m-b-0">$<?= number_format($summary['total_outstanding'] ?? 0, 2) ?></h4>
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
                                                <p class="m-b-5">Average Debt</p>
                                                <h4 class="m-b-0">$<?= number_format($summary['avg_debt_amount'] ?? 0, 2) ?></h4>
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
                                                        <input type="text" id="searchInput" class="form-control" placeholder="Search by debtor name, email, or phone" value="<?= htmlspecialchars($search) ?>">
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

                                <!-- Debtors Table Section -->
                                <div class="card">
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" width="50">#</th>
                                                        <th width="100">Action</th>
                                                        <th>Debtor Details</th>
                                                        <th>Financial Info</th>
                                                        <th>Branch</th>
                                                        <th>Status</th>
                                                        <th>Created Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="debtorsTable">
                                                    <?php
                                                    $counter = $offset + 1;
                                                    foreach ($debtors as $debtor):
                                                    ?>
                                                    <tr>
                                                        <td class="text-center"><?= $counter++ ?></td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                                    <i class="feather icon-more-vertical"></i>
                                                                </button>
                                                                <div class="dropdown-menu dropdown-menu-right">
                                                                    <button class="dropdown-item view-details" data-debtor='<?= htmlspecialchars(json_encode($debtor)) ?>'>
                                                                        <i class="feather icon-eye text-primary mr-2"></i> View Details
                                                                    </button>
                                                                    <button class="dropdown-item view-transactions" data-debtor-id="<?= $debtor['id'] ?>" data-debtor-name="<?= htmlspecialchars($debtor['name']) ?>">
                                                                        <i class="feather icon-activity text-info mr-2"></i> View Transactions
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="debtor-info">
                                                                <div class="debtor-info__name">
                                                                    <strong><?= htmlspecialchars($debtor['name']) ?></strong>
                                                                </div>
                                                                <?php if (!empty($debtor['email'])): ?>
                                                                <div class="debtor-info__email">
                                                                    <small class="text-muted">
                                                                        <i class="feather icon-mail mr-1"></i>
                                                                        <?= htmlspecialchars($debtor['email']) ?>
                                                                    </small>
                                                                </div>
                                                                <?php endif; ?>
                                                                <?php if (!empty($debtor['phone'])): ?>
                                                                <div class="debtor-info__phone">
                                                                    <small class="text-muted">
                                                                        <i class="feather icon-phone mr-1"></i>
                                                                        <?= htmlspecialchars($debtor['phone']) ?>
                                                                    </small>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="financial-info">
                                                                <div class="financial-info__balance">
                                                                    <strong class="text-<?= $debtor['current_balance'] >= 0 ? 'success' : 'danger' ?>">
                                                                        $<?= number_format(abs($debtor['current_balance']), 2) ?>
                                                                    </strong>
                                                                    <small class="text-muted">
                                                                        (<?= $debtor['current_balance'] >= 0 ? 'Owed to us' : 'We owe' ?>)
                                                                    </small>
                                                                </div>
                                                                <div class="financial-info__transactions">
                                                                    <small class="text-muted">
                                                                        <i class="feather icon-activity mr-1"></i>
                                                                        <?= number_format($debtor['transaction_count']) ?> transactions
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <span class="badge badge-secondary">
                                                                <?= htmlspecialchars($debtor['branch_name'] ?? 'N/A') ?>
                                                            </span>
                                                        </td>

                                                        <td>
                                                            <div class="status-info">
                                                                <span class="badge badge-<?= $debtor['status'] === 'active' ? 'success' : ($debtor['status'] === 'paid' ? 'info' : 'secondary') ?>">
                                                                    <?= ucfirst(htmlspecialchars($debtor['status'])) ?>
                                                                </span>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="date-info">
                                                                <div class="date-info__date">
                                                                    <?= date('d/m/Y', strtotime($debtor['created_at'])) ?>
                                                                </div>
                                                                <div class="date-info__time">
                                                                    <small class="text-muted">
                                                                        <?= date('H:i', strtotime($debtor['created_at'])) ?>
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
                                                    Showing <?= min(($page - 1) * $results_per_page + 1, $total_debtors) ?> to <?= min($page * $results_per_page, $total_debtors) ?> of <?= $total_debtors ?> debtors
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

<!-- Debtor Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white border-0">
                <h5 class="modal-title">
                    <i class="feather icon-user mr-2"></i>Debtor Details
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
                            <div class="balance-summary">
                                <div class="balance-summary__label">Current Balance</div>
                                <div class="balance-summary__amount" id="current-balance">-</div>
                                <div class="balance-summary__status" id="balance-status">-</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="transaction-summary">
                                <div class="transaction-summary__label">Transaction History</div>
                                <div class="transaction-summary__count" id="transaction-count">-</div>
                                <div class="transaction-summary__created" id="created-date">-</div>
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
                        <a class="nav-link" id="details-contact-tab" data-toggle="tab" href="#details-contact" role="tab">
                            <i class="feather icon-phone mr-2"></i>Contact Info
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
                                        <h6 class="card-subtitle mb-3 text-muted">Debtor Information</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Name</span>
                                            <strong id="debtor-name">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Status</span>
                                            <strong id="debtor-status">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Branch</span>
                                            <strong id="debtor-branch">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Main Account</span>
                                            <strong id="main-account">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted">Financial Summary</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Original Balance</span>
                                            <strong id="original-balance">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Current Balance</span>
                                            <strong id="current-balance-detail">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Currency</span>
                                            <strong id="debtor-currency">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Total Transactions</span>
                                            <strong id="total-transactions">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Info Tab -->
                    <div class="tab-pane fade" id="details-contact" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted">Contact Information</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Email</span>
                                    <strong id="debtor-email">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Phone</span>
                                    <strong id="debtor-phone">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Address</span>
                                    <strong id="debtor-address">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Debtor ID</span>
                                    <strong id="debtor-id">-</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Created At</span>
                                    <strong id="debtor-created-at">-</strong>
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

<!-- Transaction History Modal -->
<div class="modal fade" id="transactionsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white border-0">
                <h5 class="modal-title">
                    <i class="feather icon-activity mr-2"></i>Transaction History - <span id="debtor-name-header"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="transactionsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-2">Loading transaction history...</p>
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
        const debtorData = JSON.parse(this.getAttribute('data-debtor'));

        // Populate modal with debtor data
        const balance = parseFloat(debtorData.current_balance || 0);
        document.getElementById('current-balance').textContent = '$' + Math.abs(balance).toFixed(2);
        document.getElementById('balance-status').textContent = balance >= 0 ? 'Owed to us' : 'We owe';
        document.getElementById('transaction-count').textContent = debtorData.transaction_count + ' transactions';
        document.getElementById('created-date').textContent = debtorData.created_at ? new Date(debtorData.created_at).toLocaleString() : 'N/A';

        document.getElementById('debtor-name').textContent = debtorData.name;
        document.getElementById('debtor-status').textContent = debtorData.status.charAt(0).toUpperCase() + debtorData.status.slice(1);
        document.getElementById('debtor-branch').textContent = debtorData.branch_name || 'N/A';
        document.getElementById('main-account').textContent = debtorData.main_account_name || 'N/A';

        document.getElementById('original-balance').textContent = '$' + parseFloat(debtorData.balance || 0).toFixed(2);
        document.getElementById('current-balance-detail').textContent = '$' + Math.abs(balance).toFixed(2);
        document.getElementById('debtor-currency').textContent = debtorData.currency;
        document.getElementById('total-transactions').textContent = debtorData.transaction_count;

        document.getElementById('debtor-email').textContent = debtorData.email || 'N/A';
        document.getElementById('debtor-phone').textContent = debtorData.phone || 'N/A';
        document.getElementById('debtor-address').textContent = debtorData.address || 'N/A';
        document.getElementById('debtor-id').textContent = debtorData.id;
        document.getElementById('debtor-created-at').textContent = debtorData.created_at ? new Date(debtorData.created_at).toLocaleString() : 'N/A';

        // Show modal
        $('#detailsModal').modal('show');
    });
});

// Handle view transactions modal
document.querySelectorAll('.view-transactions').forEach(button => {
    button.addEventListener('click', function() {
        const debtorId = this.getAttribute('data-debtor-id');
        const debtorName = this.getAttribute('data-debtor-name');

        document.getElementById('debtor-name-header').textContent = debtorName;

        // Load transactions via AJAX
        fetch('get_debtor_transactions.php?debtor_id=' + debtorId)
            .then(response => response.text())
            .then(data => {
                document.getElementById('transactionsContent').innerHTML = data;
            })
            .catch(error => {
                document.getElementById('transactionsContent').innerHTML = '<div class="alert alert-danger">Error loading transactions: ' + error.message + '</div>';
            });

        // Show modal
        $('#transactionsModal').modal('show');
    });
});
</script>