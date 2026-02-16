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

// Build query for main accounts
$query = "SELECT
    ma.*,
    b.name as branch_name,
    COUNT(mat.id) as transaction_count,
    COALESCE(SUM(CASE WHEN mat.type = 'credit' THEN mat.amount ELSE 0 END), 0) as total_credits,
    COALESCE(SUM(CASE WHEN mat.type = 'debit' THEN mat.amount ELSE 0 END), 0) as total_debits
FROM main_account ma
LEFT JOIN branches b ON ma.branch_id = b.id
LEFT JOIN main_account_transactions mat ON ma.id = mat.main_account_id AND mat.tenant_id = ma.tenant_id
WHERE ma.tenant_id = ?";

// Add branch filtering
if ($selected_branch !== 'all') {
    $query .= " AND ma.branch_id = ?";
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (ma.name LIKE ? OR ma.bank_account_number LIKE ? OR ma.bank_name LIKE ?)";
}

// Group by main account and add ordering and pagination
$query .= " GROUP BY ma.id LIMIT ? OFFSET ?";

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
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM main_account ma WHERE ma.tenant_id = ?";
$count_params = [$tenant_id];

if ($selected_branch !== 'all') {
    $count_query .= " AND ma.branch_id = ?";
    $count_params[] = $selected_branch;
}

if (!empty($search)) {
    $count_query .= " AND (ma.name LIKE ? OR ma.bank_account_number LIKE ? OR ma.bank_name LIKE ?)";
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_accounts = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_accounts / $results_per_page);

// Get total balances for summary
$summary_query = "SELECT
    SUM(usd_balance) as total_usd,
    SUM(afs_balance) as total_afs,
    SUM(euro_balance) as total_euro,
    SUM(darham_balance) as total_darham
FROM main_account
WHERE tenant_id = ? AND status = 'active'";

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

.progress {
    border-radius: 15px;
    
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
}

.progress-bar {
    transition: width 0.6s ease;
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

.btn-sm {
    padding: 0.5rem 1rem;
    border-radius: 20px;
}

.alert {
    border-radius: 10px;
    border: none;
    padding: 1rem 1.5rem;
}

.alert-info {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    color: #0c5460;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
}

.pagination .page-link {
    border-radius: 20px;
    margin: 0 3px;
    padding: 0.5rem 0.85rem;
    border: none;
    color: #495057;
}

.pagination .page-item.active .page-link {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: white;
}

.pagination .page-item.disabled .page-link {
    background: transparent;
    color: #6c757d;
}

.pagination .page-link:hover {
    background: #f1f3f4;
    color: #4099ff;
}

/* Summary Cards Styling */
.bg-c-blue {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
}

.bg-c-green {
    background: linear-gradient(135deg, #11cdef 0%, #2ed8b6 100%) !important;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(45, 216, 182, 0.3);
}

.bg-c-yellow {
    background: linear-gradient(135deg, #ffaf23 0%, #ffc107 100%) !important;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(255, 175, 35, 0.3);
}

.bg-c-pink {
    background: linear-gradient(135deg, #f5365c 0%, #fb357a 100%) !important;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(245, 54, 92, 0.3);
}

.summary-card {
    border-radius: 10px;
    
}

.summary-card .card-body {
    padding: 1.5rem;
}

.summary-card .card-body p {
    margin-bottom: 0.5rem;
    opacity: 0.9;
}

.summary-card .card-body h4 {
    margin-bottom: 0;
    font-weight: 600;
}

.summary-card .col-auto i {
    opacity: 0.5;
}

/* Modal Styling */
.modal-header {
    border-radius: 0;
    padding: 1rem 1.5rem;
}

.modal-header.bg-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.modal-header.bg-info {
    background: linear-gradient(135deg, #11cdef 0%, #2ed8b6 100%) !important;
}

.modal-header .close {
    color: white;
    text-shadow: none;
    opacity: 0.8;
}

.modal-header .close:hover {
    opacity: 1;
}

.dropdown-menu {
    border-radius: 10px;
    border: none;
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.dropdown-item {
    padding: 0.75rem 1.5rem;
    border-radius: 0;
}

.dropdown-item:hover {
    background-color: #f1f3f4;
}

.dropdown-toggle::after {
    display: none;
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
                                            <h5 class="mb-0"><i class="feather icon-credit-card mr-2"></i>Main Accounts</h5>
                                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;">Manage your main accounts and bank balances</p>
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
                                        <div class="card bg-c-blue text-white summary-card">
                                            <div class="card-body">
                                                <div class="row align-items-center">
                                                    <div class="col">
                                                        <p class="m-b-5">Total USD</p>
                                                        <h4 class="m-b-0">$<?= number_format($summary['total_usd'] ?? 0, 2) ?></h4>
                                                    </div>
                                                    <div class="col col-auto text-right">
                                                        <i class="feather icon-dollar-sign f-50"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xl-3 col-md-6">
                                        <div class="card bg-c-green text-white summary-card">
                                            <div class="card-body">
                                                <div class="row align-items-center">
                                                    <div class="col">
                                                        <p class="m-b-5">Total AFS</p>
                                                        <h4 class="m-b-0">AFS <?= number_format($summary['total_afs'] ?? 0, 2) ?></h4>
                                                    </div>
                                                    <div class="col col-auto text-right">
                                                        <i class="feather icon-credit-card f-50"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xl-3 col-md-6">
                                        <div class="card bg-c-yellow text-white summary-card">
                                            <div class="card-body">
                                                <div class="row align-items-center">
                                                    <div class="col">
                                                        <p class="m-b-5">Total EURO</p>
                                                        <h4 class="m-b-0">€<?= number_format($summary['total_euro'] ?? 0, 2) ?></h4>
                                                    </div>
                                                    <div class="col col-auto text-right">
                                                        <i class="feather icon-repeat f-50"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xl-3 col-md-6">
                                        <div class="card bg-c-pink text-white summary-card">
                                            <div class="card-body">
                                                <div class="row align-items-center">
                                                    <div class="col">
                                                        <p class="m-b-5">Total DARHAM</p>
                                                        <h4 class="m-b-0">د.إ <?= number_format($summary['total_darham'] ?? 0, 2) ?></h4>
                                                    </div>
                                                    <div class="col col-auto text-right">
                                                        <i class="feather icon-package f-50"></i>
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
                                            <div class="card-header">
                                                <h5><i class="feather icon-filter mr-2"></i>Filter Options</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row align-items-center">
                                                    <div class="col-md-6">
                                                        <div class="branch-selector">
                                                            <label for="branchSelect" class="form-label"><i class="feather icon-home mr-2"></i>Select Branch:</label>
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
                                                                <input type="text" id="searchInput" class="form-control" placeholder="Search by account name, bank account number, or bank name" value="<?= htmlspecialchars($search) ?>">
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

                                        <!-- Accounts Table Section -->
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="feather icon-list mr-2"></i>Accounts List <span class="badge badge-info badge-pill ml-2"><?= $total_accounts ?></span></h5>
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th class="text-center" width="50"><i class="feather icon-hash mr-1"></i>#</th>
                                                                <th width="100"><i class="feather icon-cog mr-1"></i>Action</th>
                                                                <th><i class="feather icon-credit-card mr-1"></i>Account Info</th>
                                                                <th><i class="feather icon-home mr-1"></i>Bank Details</th>
                                                                <th><i class="feather icon-dollar-sign mr-1"></i>Balances</th>
                                                                <th><i class="feather icon-activity mr-1"></i>Activity</th>
                                                                <th><i class="feather icon-check-circle mr-1"></i>Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="accountTable">
                                                            <?php
                                                            $counter = $offset + 1;
                                                            foreach ($accounts as $account):
                                                            ?>
                                                            <tr>
                                                                <td class="text-center"><?= $counter++ ?></td>
                                                                <td>
                                                                    <div class="dropdown">
                                                                        <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                                            <i class="feather icon-more-vertical"></i>
                                                                        </button>
                                                                        <div class="dropdown-menu dropdown-menu-right">
                                                                            <button class="dropdown-item view-details" data-account='<?= htmlspecialchars(json_encode($account)) ?>'>
                                                                                <i class="feather icon-eye text-primary mr-2"></i> View Details
                                                                            </button>
                                                                            <button class="dropdown-item view-transactions" data-account-id="<?= $account['id'] ?>" data-account-name="<?= htmlspecialchars($account['name']) ?>">
                                                                                <i class="feather icon-list text-info mr-2"></i> View Transactions
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="account-info">
                                                                        <div class="account-info__details">
                                                                            <div class="account-info__name">
                                                                                <strong><?= htmlspecialchars($account['name']) ?></strong>
                                                                            </div>
                                                                            <div class="account-info__type">
                                                                                <span class="badge badge-<?= $account['account_type'] === 'bank' ? 'primary' : 'secondary' ?>">
                                                                                    <i class="feather <?= $account['account_type'] === 'bank' ? 'icon-home' : 'icon-credit-card' ?> mr-1"></i><?= ucfirst(htmlspecialchars($account['account_type'])) ?>
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="bank-info">
                                                                        <?php if ($account['account_type'] === 'bank'): ?>
                                                                        <div class="bank-info__details">
                                                                            <div class="bank-info__name">
                                                                                <strong><?= htmlspecialchars($account['bank_name'] ?: 'N/A') ?></strong>
                                                                            </div>
                                                                            <div class="bank-info__account">
                                                                                <i class="feather icon-hash mr-1 text-muted"></i><?= htmlspecialchars($account['bank_account_number'] ?: 'N/A') ?>
                                                                            </div>
                                                                        </div>
                                                                        <?php else: ?>
                                                                        <div class="text-muted">
                                                                            <i class="feather icon-box mr-1"></i><em>Internal Account</em>
                                                                        </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="balances-info">
                                                                        <div class="balances-info__usd">
                                                                            <span class="text-primary"><i class="feather icon-dollar-sign mr-1"></i>$<?= number_format($account['usd_balance'], 2) ?></span>
                                                                        </div>
                                                                        <div class="balances-info__afs">
                                                                            <span class="text-success"><i class="feather icon-credit-card mr-1"></i>AFS <?= number_format($account['afs_balance'], 2) ?></span>
                                                                        </div>
                                                                        <?php if ($account['euro_balance'] > 0 || $account['darham_balance'] > 0): ?>
                                                                        <div class="balances-info__other">
                                                                            <?php if ($account['euro_balance'] > 0): ?>
                                                                            <span class="text-warning"><i class="feather icon-repeat mr-1"></i>€<?= number_format($account['euro_balance'], 2) ?></span><br>
                                                                            <?php endif; ?>
                                                                            <?php if ($account['darham_balance'] > 0): ?>
                                                                            <span class="text-info"><i class="feather icon-package mr-1"></i>د.إ <?= number_format($account['darham_balance'], 2) ?></span>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="activity-info">
                                                                        <div class="activity-info__transactions">
                                                                            <i class="feather icon-activity text-muted mr-1"></i>
                                                                            <?= number_format($account['transaction_count']) ?> transactions
                                                                        </div>
                                                                        <div class="activity-info__summary">
                                                                            <small class="text-muted">
                                                                                <i class="feather icon-trending-up text-success mr-1"></i>$<?= number_format($account['total_credits'], 2) ?>
                                                                                <i class="feather icon-trending-down text-danger ml-2 mr-1"></i>$<?= number_format($account['total_debits'], 2) ?>
                                                                            </small>
                                                                        </div>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <span class="badge badge-<?= $account['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                                        <i class="feather <?= $account['status'] === 'active' ? 'icon-check-circle' : 'icon-x-circle' ?> mr-1"></i><?= ucfirst(htmlspecialchars($account['status'])) ?>
                                                                    </span>
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
                                                            <i class="feather icon-info mr-1"></i>Showing <?= min(($page - 1) * $results_per_page + 1, $total_accounts) ?> to <?= min($page * $results_per_page, $total_accounts) ?> of <?= $total_accounts ?> main accounts
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
    </div>

<!-- Account Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title">
                    <i class="feather icon-credit-card mr-2"></i>Main Account Details
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <!-- Top Summary Card -->
                <div class="bg-light p-4 border-bottom">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <div class="small text-muted mb-1">USD Balance</div>
                            <h4 class="mb-0 text-primary" id="usd-balance">-</h4>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="small text-muted mb-1">AFS Balance</div>
                            <h4 class="mb-0 text-success" id="afs-balance">-</h4>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="small text-muted mb-1">EURO Balance</div>
                            <h4 class="mb-0 text-warning" id="euro-balance">-</h4>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="small text-muted mb-1">DARHAM Balance</div>
                            <h4 class="mb-0 text-info" id="darham-balance">-</h4>
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
                        <a class="nav-link" id="details-bank-tab" data-toggle="tab" href="#details-bank" role="tab">
                            <i class="feather icon-home mr-2"></i>Bank Details
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
                                        <h6 class="card-subtitle mb-3 text-muted">Account Information</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Account Name</span>
                                            <strong id="account-name">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Account Type</span>
                                            <strong id="account-type">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Status</span>
                                            <strong id="account-status">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Last Updated</span>
                                            <strong id="last-updated">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Created At</span>
                                            <strong id="created-at">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted">Activity Summary</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Total Transactions</span>
                                            <strong id="total-transactions">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Total Credits</span>
                                            <strong class="text-success" id="total-credits">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Total Debits</span>
                                            <strong class="text-danger" id="total-debits">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Net Flow</span>
                                            <strong id="net-flow">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bank Details Tab -->
                    <div class="tab-pane fade" id="details-bank" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted">Bank Information</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Bank Name</span>
                                    <strong id="bank-name">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Account Number</span>
                                    <strong id="account-number">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">AFS Account Number</span>
                                    <strong id="afs-account-number">-</strong>
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

<!-- Transactions Modal -->
<div class="modal fade" id="transactionsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white border-0">
                <h5 class="modal-title">
                    <i class="feather icon-list mr-2"></i>Account Transactions - <span id="account-name-header"></span>
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
                        <p class="mt-2">Loading transactions...</p>
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
        const accountData = JSON.parse(this.getAttribute('data-account'));

        // Populate modal with account data
        document.getElementById('usd-balance').textContent = '$' + parseFloat(accountData.usd_balance || 0).toFixed(2);
        document.getElementById('afs-balance').textContent = 'AFS ' + parseFloat(accountData.afs_balance || 0).toFixed(2);
        document.getElementById('euro-balance').textContent = '€' + parseFloat(accountData.euro_balance || 0).toFixed(2);
        document.getElementById('darham-balance').textContent = 'د.إ ' + parseFloat(accountData.darham_balance || 0).toFixed(2);

        document.getElementById('account-name').textContent = accountData.name;
        document.getElementById('account-type').textContent = accountData.account_type.charAt(0).toUpperCase() + accountData.account_type.slice(1);
        document.getElementById('account-status').textContent = accountData.status.charAt(0).toUpperCase() + accountData.status.slice(1);
        document.getElementById('last-updated').textContent = accountData.last_updated || 'N/A';

        document.getElementById('total-transactions').textContent = accountData.transaction_count;
        document.getElementById('total-credits').textContent = '$' + parseFloat(accountData.total_credits || 0).toFixed(2);
        document.getElementById('total-debits').textContent = '$' + parseFloat(accountData.total_debits || 0).toFixed(2);
        const netFlow = parseFloat(accountData.total_credits || 0) - parseFloat(accountData.total_debits || 0);
        document.getElementById('net-flow').innerHTML = (netFlow >= 0 ? '<span class="text-success">+$' : '<span class="text-danger">-$') + Math.abs(netFlow).toFixed(2) + '</span>';

        document.getElementById('bank-name').textContent = accountData.bank_name || 'N/A';
        document.getElementById('account-number').textContent = accountData.bank_account_number || 'N/A';
        document.getElementById('afs-account-number').textContent = accountData.bank_account_afs_number || 'N/A';

        // Show modal
        $('#detailsModal').modal('show');
    });
});

// Handle view transactions modal
document.querySelectorAll('.view-transactions').forEach(button => {
    button.addEventListener('click', function() {
        const accountId = this.getAttribute('data-account-id');
        const accountName = this.getAttribute('data-account-name');

        document.getElementById('account-name-header').textContent = accountName;

        // Load transactions via AJAX
        fetch('get_account_transactions.php?account_id=' + accountId)
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