<?php
include 'header.php';

// Get tenant and user info
$tenant_id = $_SESSION['tenant_id'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 25;
$offset = ($page - 1) * $results_per_page;

// Build query for main accounts
$query = "SELECT
    ma.*,
    COUNT(mat.id) as transaction_count,
    COALESCE(SUM(CASE WHEN mat.type = 'credit' THEN mat.amount ELSE 0 END), 0) as total_credits,
    COALESCE(SUM(CASE WHEN mat.type = 'debit' THEN mat.amount ELSE 0 END), 0) as total_debits
FROM main_account ma
LEFT JOIN main_account_transactions mat ON ma.id = mat.main_account_id
WHERE ma.tenant_id = ?";

// Add search filter
if (!empty($search)) {
    $query .= " AND (ma.name LIKE ? OR ma.bank_account_number LIKE ? OR ma.bank_name LIKE ?)";
}

// Group by main account and add ordering and pagination
$query .= " GROUP BY ma.id LIMIT ? OFFSET ?";

// Prepare parameters
$params = [$tenant_id];

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

$summary_stmt = $pdo->prepare($summary_query);
$summary_stmt->execute([$tenant_id]);
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
                                    <h5 class="m-b-10">Main Accounts</h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="javascript:">Main Accounts</a></li>
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
                                                <p class="m-b-5">Total USD</p>
                                                <h4 class="m-b-0">$<?= number_format($summary['total_usd'] ?? 0, 2) ?></h4>
                                            </div>
                                            <div class="col col-auto text-right">
                                                <i class="fas fa-dollar-sign f-50 text-c-blue"></i>
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
                                                <p class="m-b-5">Total AFS</p>
                                                <h4 class="m-b-0">AFS <?= number_format($summary['total_afs'] ?? 0, 2) ?></h4>
                                            </div>
                                            <div class="col col-auto text-right">
                                                <i class="fas fa-money-bill-wave f-50 text-c-green"></i>
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
                                                <p class="m-b-5">Total EURO</p>
                                                <h4 class="m-b-0">€<?= number_format($summary['total_euro'] ?? 0, 2) ?></h4>
                                            </div>
                                            <div class="col col-auto text-right">
                                                <i class="fas fa-euro-sign f-50 text-c-yellow"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6">
                                <div class="card bg-c-pink text-white">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <p class="m-b-5">Total DARHAM</p>
                                                <h4 class="m-b-0">د.إ <?= number_format($summary['total_darham'] ?? 0, 2) ?></h4>
                                            </div>
                                            <div class="col col-auto text-right">
                                                <i class="fas fa-money-bill f-50 text-c-pink"></i>
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
                                                        <input type="text" id="searchInput" class="form-control" placeholder="Search by account name, bank account number, or bank name" value="<?= htmlspecialchars($search) ?>">
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

                                <!-- Accounts Table Section -->
                                <div class="card">
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" width="50">#</th>
                                                        <th width="100">Action</th>
                                                        <th>Account Info</th>
                                                        <th>Bank Details</th>
                                                        <th>Balances</th>
                                                        <th>Activity</th>
                                                        <th>Status</th>
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
                                                                            <?= ucfirst(htmlspecialchars($account['account_type'])) ?>
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
                                                                        Account: <?= htmlspecialchars($account['bank_account_number'] ?: 'N/A') ?>
                                                                    </div>
                                                                </div>
                                                                <?php else: ?>
                                                                <div class="text-muted">
                                                                    <em>Internal Account</em>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="balances-info">
                                                                <div class="balances-info__usd">
                                                                    <span class="text-primary">USD: $<?= number_format($account['usd_balance'], 2) ?></span>
                                                                </div>
                                                                <div class="balances-info__afs">
                                                                    <span class="text-success">AFS: <?= number_format($account['afs_balance'], 2) ?></span>
                                                                </div>
                                                                <?php if ($account['euro_balance'] > 0 || $account['darham_balance'] > 0): ?>
                                                                <div class="balances-info__other">
                                                                    <?php if ($account['euro_balance'] > 0): ?>
                                                                    <span class="text-warning">EUR: €<?= number_format($account['euro_balance'], 2) ?></span><br>
                                                                    <?php endif; ?>
                                                                    <?php if ($account['darham_balance'] > 0): ?>
                                                                    <span class="text-info">DAR: د.إ <?= number_format($account['darham_balance'], 2) ?></span>
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
                                                                        Credits: $<?= number_format($account['total_credits'], 2) ?><br>
                                                                        Debits: $<?= number_format($account['total_debits'], 2) ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <span class="badge badge-<?= $account['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                                <?= ucfirst(htmlspecialchars($account['status'])) ?>
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
                                                    Showing <?= min(($page - 1) * $results_per_page + 1, $total_accounts) ?> to <?= min($page * $results_per_page, $total_accounts) ?> of <?= $total_accounts ?> main accounts
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