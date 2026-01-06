<?php
include 'header.php';

// Get tenant and user info
$tenant_id = $_SESSION['tenant_id'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? null;

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

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

// Build query for additional payments
$query = "SELECT ap.*,
                 c.name as client_name,
                 s.name as supplier_name,
                 ma.name as main_account_name,
                 u.name as created_by_name,
                 b.name as branch_name
          FROM additional_payments ap
          LEFT JOIN clients c ON ap.client_id = c.id
          LEFT JOIN suppliers s ON ap.supplier_id = s.id
          LEFT JOIN main_account ma ON ap.main_account_id = ma.id
          LEFT JOIN users u ON ap.created_by = u.id
          LEFT JOIN branches b ON ap.branch_id = b.id
          WHERE ap.tenant_id = ?";

// Add branch filtering
if ($user_branch_id) {
    $query .= " AND ap.branch_id = ?";
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (ap.description LIKE ? OR ap.payment_type LIKE ? OR c.name LIKE ? OR s.name LIKE ?)";
}

// Group by and ordering with pagination
$query .= " ORDER BY ap.created_at DESC LIMIT ? OFFSET ?";

// Prepare parameters
$params = [$tenant_id];

if ($user_branch_id) {
    $params[] = $user_branch_id;
}

if (!empty($search)) {
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$params[] = $results_per_page;
$params[] = $offset;

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM additional_payments ap
                LEFT JOIN clients c ON ap.client_id = c.id
                LEFT JOIN suppliers s ON ap.supplier_id = s.id
                WHERE ap.tenant_id = ?";
$count_params = [$tenant_id];

if ($user_branch_id) {
    $count_query .= " AND ap.branch_id = ?";
    $count_params[] = $user_branch_id;
}

if (!empty($search)) {
    $count_query .= " AND (ap.description LIKE ? OR ap.payment_type LIKE ? OR c.name LIKE ? OR s.name LIKE ?)";
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_payments = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_payments / $results_per_page);

// Get summary data
$summary_query = "SELECT
    COUNT(*) as total_payments,
    SUM(CASE WHEN currency = 'USD' THEN sold_amount ELSE 0 END) as total_usd_amount,
    SUM(CASE WHEN currency = 'AFS' THEN sold_amount ELSE 0 END) as total_afs_amount,
    SUM(profit) as total_profit
FROM additional_payments
WHERE tenant_id = ?";

$summary_params = [$tenant_id];

if ($user_branch_id) {
    $summary_query .= " AND branch_id = ?";
    $summary_params[] = $user_branch_id;
}

$summary_stmt = $pdo->prepare($summary_query);
$summary_stmt->execute($summary_params);
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
?>

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
                                        <h5 class="mb-0"><i class="feather icon-credit-card mr-2"></i>Additional Payments</h5>
                                        <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;">View and manage all additional payments</p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                            <i class="feather icon-arrow-left mr-1"></i>Back to Dashboard
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Summary Cards -->
                                <div class="col-xl-3 col-md-6">
                                    <div class="card bg-c-blue text-white">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col">
                                                    <p class="m-b-5">Total Payments</p>
                                                    <h4 class="m-b-0"><?= number_format($summary['total_payments'] ?? 0) ?></h4>
                                                </div>
                                                <div class="col col-auto text-right">
                                                    <i class="fas fa-credit-card f-50 text-c-blue"></i>
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
                                                    <p class="m-b-5">Total USD Amount</p>
                                                    <h4 class="m-b-0">$<?= number_format($summary['total_usd_amount'] ?? 0, 2) ?></h4>
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
                                                    <p class="m-b-5">Total AFS Amount</p>
                                                    <h4 class="m-b-0">AFS <?= number_format($summary['total_afs_amount'] ?? 0, 2) ?></h4>
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
                                                    <p class="m-b-5">Total Profit</p>
                                                    <h4 class="m-b-0">$<?= number_format($summary['total_profit'] ?? 0, 2) ?></h4>
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
                                    <!-- Search Section -->
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col-md-12">
                                                    <div class="search-box">
                                                        <div class="input-group">
                                                            <input type="text" id="searchInput" class="form-control" placeholder="Search by description, payment type, client, or supplier" value="<?= htmlspecialchars($search) ?>">
                                                            <div class="input-group-append">
                                                                <button class="btn btn-primary" type="button" id="searchBtn">
                                                                    <i class="feather icon-search"></i> Search
                                                                </button>
                                                                <?php if (!empty($search)): ?>
                                                                <a href="?" class="btn btn-secondary">
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

                                    <!-- Payments Table Section -->
                                    <div class="card">
                                        <div class="card-header">
                                            <h5><i class="feather icon-list mr-2"></i>Payments List</h5>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center" width="50">#</th>
                                                            <th width="100">Action</th>
                                                            <th>Payment Details</th>
                                                            <th>Amounts</th>
                                                            <th>Client/Supplier</th>
                                                            <th>Branch</th>
                                                            <th>Date</th>
                                                            <th>Created By</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="paymentTable">
                                                        <?php
                                                        $counter = $offset + 1;
                                                        foreach ($payments as $payment):
                                                        ?>
                                                        <tr>
                                                            <td class="text-center"><?= $counter++ ?></td>
                                                            <td>
                                                                <div class="dropdown">
                                                                    <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                                        <i class="feather icon-more-vertical"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu dropdown-menu-right">
                                                                        <button class="dropdown-item view-details" data-payment='<?= htmlspecialchars(json_encode($payment)) ?>'>
                                                                            <i class="feather icon-eye text-primary mr-2"></i> View Details
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <td>
                                                                <div class="payment-info">
                                                                    <div class="payment-info__type">
                                                                        <span class="badge badge-primary">
                                                                            <?= htmlspecialchars($payment['payment_type']) ?>
                                                                        </span>
                                                                    </div>
                                                                    <div class="payment-info__description">
                                                                        <strong><?= htmlspecialchars($payment['description']) ?></strong>
                                                                    </div>
                                                                    <?php if (!empty($payment['receipt'])): ?>
                                                                    <div class="payment-info__receipt">
                                                                        <small class="text-muted">Receipt: <?= htmlspecialchars($payment['receipt']) ?></small>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>

                                                            <td>
                                                                <div class="amounts-info">
                                                                    <div class="amounts-info__base">
                                                                        <span class="text-muted">Base:</span>
                                                                        <strong class="text-info">
                                                                            <?= $payment['currency'] === 'USD' ? '$' : 'AFS ' ?>
                                                                            <?= number_format($payment['base_amount'], 2) ?>
                                                                        </strong>
                                                                    </div>
                                                                    <div class="amounts-info__sold">
                                                                        <span class="text-muted">Sold:</span>
                                                                        <strong class="text-success">
                                                                            <?= $payment['currency'] === 'USD' ? '$' : 'AFS ' ?>
                                                                            <?= number_format($payment['sold_amount'], 2) ?>
                                                                        </strong>
                                                                    </div>
                                                                    <div class="amounts-info__profit">
                                                                        <span class="text-muted">Profit:</span>
                                                                        <strong class="text-<?= $payment['profit'] >= 0 ? 'success' : 'danger' ?>">
                                                                            $<?= number_format($payment['profit'], 2) ?>
                                                                        </strong>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <td>
                                                                <div class="client-supplier-info">
                                                                    <?php if (!empty($payment['client_name'])): ?>
                                                                    <div class="client-supplier-info__client">
                                                                        <i class="feather icon-user mr-1"></i>
                                                                        <strong>Client:</strong> <?= htmlspecialchars($payment['client_name']) ?>
                                                                    </div>
                                                                    <?php elseif (!empty($payment['supplier_name'])): ?>
                                                                    <div class="client-supplier-info__supplier">
                                                                        <i class="feather icon-truck mr-1"></i>
                                                                        <strong>Supplier:</strong> <?= htmlspecialchars($payment['supplier_name']) ?>
                                                                    </div>
                                                                    <?php else: ?>
                                                                    <div class="text-muted">
                                                                        <em>No client/supplier</em>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>

                                                            <td>
                                                                <span class="badge badge-secondary">
                                                                    <?= htmlspecialchars($payment['branch_name'] ?? 'N/A') ?>
                                                                </span>
                                                            </td>

                                                            <td>
                                                                <div class="date-info">
                                                                    <div class="date-info__date">
                                                                        <?= date('d/m/Y', strtotime($payment['created_at'])) ?>
                                                                    </div>
                                                                    <div class="date-info__time">
                                                                        <small class="text-muted">
                                                                            <?= date('H:i', strtotime($payment['created_at'])) ?>
                                                                        </small>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <td>
                                                                <div class="created-by-info">
                                                                    <div class="created-by-info__name">
                                                                        <?= htmlspecialchars($payment['created_by_name'] ?? 'Unknown') ?>
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
                                                        Showing <?= min(($page - 1) * $results_per_page + 1, $total_payments) ?> to <?= min($page * $results_per_page, $total_payments) ?> of <?= $total_payments ?> payments
                                                    </div>
                                                    <nav aria-label="Page navigation">
                                                        <ul class="pagination mb-0">
                                                            <?php if ($page > 1): ?>
                                                                <li class="page-item">
                                                                    <a class="page-link" href="?page=1&search=<?= urlencode($search) ?>">
                                                                        <i class="feather icon-chevrons-left"></i>
                                                                    </a>
                                                                </li>
                                                                <li class="page-item">
                                                                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">
                                                                        <i class="feather icon-chevron-left"></i>
                                                                    </a>
                                                                </li>
                                                            <?php endif; ?>

                                                            <?php
                                                            $start_page = max(1, $page - 2);
                                                            $end_page = min($total_pages, $page + 2);

                                                            if ($start_page > 1) {
                                                                echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '">1</a></li>';
                                                                if ($start_page > 2) {
                                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                                }
                                                            }

                                                            for ($i = $start_page; $i <= $end_page; $i++) {
                                                                echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                                                    <a class="page-link" href="?page=' . $i . '&search=' . urlencode($search) . '">' . $i . '</a>
                                                                </li>';
                                                            }

                                                            if ($end_page < $total_pages) {
                                                                if ($end_page < $total_pages - 1) {
                                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                                }
                                                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '">' . $total_pages . '</a></li>';
                                                            }
                                                            ?>

                                                            <?php if ($page < $total_pages): ?>
                                                                <li class="page-item">
                                                                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">
                                                                        <i class="feather icon-chevron-right"></i>
                                                                    </a>
                                                                </li>
                                                                <li class="page-item">
                                                                    <a class="page-link" href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>">
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
</div>

<!-- Payment Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white border-0">
                <h5 class="modal-title">
                    <i class="feather icon-credit-card mr-2"></i>Payment Details
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
                                <div class="amount-summary__label">Payment Amount</div>
                                <div class="amount-summary__amount" id="payment-amount">-</div>
                                <div class="amount-summary__currency" id="payment-currency">-</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="profit-summary">
                                <div class="profit-summary__label">Profit Generated</div>
                                <div class="profit-summary__amount" id="profit-amount">-$0.00</div>
                                <div class="profit-summary__created">Created: <span id="created-date">-</span></div>
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
                        <a class="nav-link" id="details-financial-tab" data-toggle="tab" href="#details-financial" role="tab">
                            <i class="feather icon-dollar-sign mr-2"></i>Financial Details
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
                                        <h6 class="card-subtitle mb-3 text-muted">Payment Information</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Payment Type</span>
                                            <strong id="payment-type">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Description</span>
                                            <strong id="payment-description">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Branch</span>
                                            <strong id="payment-branch">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Created By</span>
                                            <strong id="payment-created-by">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted">Client/Supplier Details</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Client</span>
                                            <strong id="payment-client">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Supplier</span>
                                            <strong id="payment-supplier">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Main Account</span>
                                            <strong id="payment-main-account">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Receipt</span>
                                            <strong id="payment-receipt">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Details Tab -->
                    <div class="tab-pane fade" id="details-financial" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted">Financial Breakdown</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Base Amount</span>
                                    <strong id="base-amount">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Sold Amount</span>
                                    <strong id="sold-amount">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Profit</span>
                                    <strong id="profit-detail">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Currency</span>
                                    <strong id="currency-detail">-</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Payment ID</span>
                                    <strong id="payment-id">-</strong>
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

<style>
/* Enhanced custom styles matching request_user_addon.php */
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
    color: white;
}

.card-footer {
    border-radius: 0 0 10px 10px;
}

.badge {
    font-size: 0.85em;
    padding: 0.5em 0.75em;
    border-radius: 20px;
    font-weight: 500;
}

.badge-success {
    background-color: #28a745;
}

.badge-warning {
    background-color: #ffc107;
    color: #212529;
}

.badge-info {
    background-color: #17a2b8;
}

.badge-primary {
    background-color: #4099ff;
}

.badge-secondary {
    background-color: #6c757d;
}

.table-responsive {
    border-radius: 10px;
    overflow: hidden;
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

.btn-outline-secondary {
    border-radius: 25px;
    padding: 0.5rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s ease;
    background: rgba(255,255,255,0.2);
    color: #ffffff;
    border: 1px solid rgba(255,255,255,0.3);
}

.btn-outline-secondary:hover {
    background: rgba(255,255,255,0.3);
    border-color: rgba(255,255,255,0.5);
    color: #ffffff;
}

.alert {
    border-radius: 10px;
    border: none;
    padding: 1rem 1.5rem;
}

.pagination .page-link {
    border-radius: 25px;
    margin: 0 3px;
    border: none;
    color: #667eea;
}

.pagination .page-item.active .page-link {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.pagination .page-item.disabled .page-link {
    color: #6c757d;
}

.input-group-append .btn {
    border-radius: 0 8px 8px 0;
}

.input-group .form-control {
    border-radius: 8px 0 0 8px;
}

/* Summary card styling */
.bg-c-blue {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
}

.bg-c-green {
    background: linear-gradient(135deg, #11cdef 0%, #2ed8b6 100%);
}

.bg-c-red {
    background: linear-gradient(135deg, #f5365c 0%, #fd7e14 100%);
}

.bg-c-yellow {
    background: linear-gradient(135deg, #ffbf00 0%, #ffc107 100%);
}
</style>

<script>
// Handle search functionality
document.getElementById('searchBtn').addEventListener('click', function() {
    const searchValue = document.getElementById('searchInput').value.trim();

    let url = '?';
    if (searchValue) {
        url += 'search=' + encodeURIComponent(searchValue);
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
        const paymentData = JSON.parse(this.getAttribute('data-payment'));

        // Populate modal with payment data
        document.getElementById('payment-amount').textContent = (paymentData.currency === 'USD' ? '$' : 'AFS ') + parseFloat(paymentData.sold_amount || 0).toFixed(2);
        document.getElementById('payment-currency').textContent = paymentData.currency;
        document.getElementById('profit-amount').textContent = '$' + parseFloat(paymentData.profit || 0).toFixed(2);
        document.getElementById('created-date').textContent = paymentData.created_at ? new Date(paymentData.created_at).toLocaleString() : 'N/A';

        document.getElementById('payment-type').textContent = paymentData.payment_type || 'N/A';
        document.getElementById('payment-description').textContent = paymentData.description || 'N/A';
        document.getElementById('payment-branch').textContent = paymentData.branch_name || 'N/A';
        document.getElementById('payment-created-by').textContent = paymentData.created_by_name || 'Unknown';

        document.getElementById('payment-client').textContent = paymentData.client_name || 'N/A';
        document.getElementById('payment-supplier').textContent = paymentData.supplier_name || 'N/A';
        document.getElementById('payment-main-account').textContent = paymentData.main_account_name || 'N/A';
        document.getElementById('payment-receipt').textContent = paymentData.receipt || 'N/A';

        document.getElementById('base-amount').textContent = (paymentData.currency === 'USD' ? '$' : 'AFS ') + parseFloat(paymentData.base_amount || 0).toFixed(2);
        document.getElementById('sold-amount').textContent = (paymentData.currency === 'USD' ? '$' : 'AFS ') + parseFloat(paymentData.sold_amount || 0).toFixed(2);
        document.getElementById('profit-detail').textContent = '$' + parseFloat(paymentData.profit || 0).toFixed(2);
        document.getElementById('currency-detail').textContent = paymentData.currency;
        document.getElementById('payment-id').textContent = paymentData.id;

        // Show modal
        $('#detailsModal').modal('show');
    });
});
</script>

<?php include 'footer.php'; ?>
