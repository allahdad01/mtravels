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

// Build query for sarafi transactions
$query = "SELECT st.*,
                 c.name as customer_name,
                 c.phone as customer_phone,
                 b.name as branch_name
          FROM sarafi_transactions st
          LEFT JOIN customers c ON st.customer_id = c.id
          LEFT JOIN branches b ON st.branch_id = b.id
          WHERE st.tenant_id = ?";

// Add branch filtering
if ($selected_branch !== 'all') {
    $query .= " AND st.branch_id = ?";
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (c.name LIKE ? OR st.reference_number LIKE ? OR st.notes LIKE ?)";
}

// Group by and ordering with pagination
$query .= " ORDER BY st.created_at DESC LIMIT ? OFFSET ?";

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
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM sarafi_transactions st
                LEFT JOIN customers c ON st.customer_id = c.id
                WHERE st.tenant_id = ?";
$count_params = [$tenant_id];

if ($selected_branch !== 'all') {
    $count_query .= " AND st.branch_id = ?";
    $count_params[] = $selected_branch;
}

if (!empty($search)) {
    $count_query .= " AND (c.name LIKE ? OR st.reference_number LIKE ? OR st.notes LIKE ?)";
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_transactions = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_transactions / $results_per_page);

// Get summary data
$summary_query = "SELECT
    COUNT(*) as total_transactions,
    COUNT(CASE WHEN type = 'deposit' THEN 1 END) as deposit_count,
    COUNT(CASE WHEN type = 'withdrawal' THEN 1 END) as withdrawal_count,
    COUNT(CASE WHEN type = 'exchange' THEN 1 END) as exchange_count,
    SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END) as total_deposits,
    SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END) as total_withdrawals
FROM sarafi_transactions
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
    /* Page Header - Gradient Background */
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

    .page-header.card .breadcrumb {
        background: transparent;
    }

    .page-header.card .breadcrumb-item a {
        color: rgba(255,255,255,0.8);
    }

    .page-header.card .breadcrumb-item a:hover {
        color: #ffffff;
    }

    .page-header.card .breadcrumb-item.active {
        color: rgba(255,255,255,0.9);
    }

    .breadcrumb-item + .breadcrumb-item::before {
        color: rgba(255,255,255,0.7);
    }

    /* Card Styles */
    .card {
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        border: none;
        margin-bottom: 20px;
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
        color: #ffffff;
    }

    .card-body {
        padding: 1.5rem;
    }

    .card-footer {
        border-radius: 0 0 10px 10px;
        background: #f8f9fa;
        border-top: 1px solid #eee;
    }

    /* Summary Cards */
    .card.bg-c-blue, .card.bg-c-green, .card.bg-c-red, .card.bg-c-yellow, .card.bg-c-purple, .card.bg-c-pink {
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        transition: transform 0.2s ease-in-out;
    }

    .card.bg-c-blue:hover, .card.bg-c-green:hover, .card.bg-c-red:hover, .card.bg-c-yellow:hover, .card.bg-c-purple:hover, .card.bg-c-pink:hover {
        transform: translateY(-3px);
    }

    /* Table Styles */
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

    /* Form Controls */
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

    /* Buttons */
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
        background: linear-gradient(135deg, #2ed8b6 0%, #4099ff 100%);
    }

    .btn-secondary {
        border-radius: 25px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-secondary:hover {
        transform: translateY(-1px);
    }

    .btn {
        border-radius: 8px;
    }

    /* Pagination */
    .pagination .page-link {
        border-radius: 8px;
        margin: 0 3px;
        border: none;
        color: #495057;
    }

    .pagination .page-link:hover {
        background-color: #e9ecef;
        color: #4099ff;
    }

    .pagination .page-item.active .page-link {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        color: white;
    }

    /* Badge Styles */
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

    .badge-danger {
        background-color: #dc3545;
    }

    .badge-info {
        background-color: #17a2b8;
    }

    .badge-primary {
        background-color: #007bff;
    }

    .badge-secondary {
        background-color: #6c757d;
    }

    /* Modal Styles */
    .modal-content {
        border-radius: 15px;
        border: none;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }

    .modal-header {
        border-radius: 15px 15px 0 0;
        padding: 1.5rem;
    }

    .modal-header.bg-info {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        border-radius: 0 0 15px 15px;
    }

    /* Input Group */
    .input-group .form-control {
        border-radius: 8px 0 0 8px;
    }

    .input-group-append .btn {
        border-radius: 0 8px 8px 0;
    }

    /* Dropdown */
    .dropdown-menu {
        border-radius: 10px;
        border: none;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }

    .dropdown-item {
        padding: 0.75rem 1.5rem;
        border-radius: 5px;
        margin: 0 5px;
    }

    .dropdown-item:hover {
        background-color: #f8f9fa;
    }

    /* Nav Pills */
    .nav-pills .nav-link {
        border-radius: 25px;
        padding: 0.75rem 1.5rem;
    }

    .nav-pills .nav-link.active {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    }

    /* Alert Styles */
    .alert {
        border-radius: 10px;
        border: none;
        padding: 1rem 1.5rem;
    }

    /* Custom Scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header card">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <h5 class="m-b-10"><i class="feather icon-exchange mr-2"></i>Sarafi (Money Exchange)</h5>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                <li class="breadcrumb-item active">Sarafi</li>
                            </ol>
                        </div>
                    </div>
                </div>
                <!-- [ Main Content ] start -->
                <div class="main-content">
                    <!-- Summary Cards -->
                    <div class="row">
                        <div class="col-xl-2 col-md-6">
                            <div class="card bg-c-blue text-white">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <p class="m-b-5">Total Transactions</p>
                                            <h4 class="m-b-0"><?= number_format($summary['total_transactions'] ?? 0) ?></h4>
                                        </div>
                                        <div class="col col-auto text-right">
                                            <i class="fas fa-exchange-alt f-50 text-c-blue"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-6">
                            <div class="card bg-c-green text-white">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <p class="m-b-5">Deposits</p>
                                            <h4 class="m-b-0"><?= number_format($summary['deposit_count'] ?? 0) ?></h4>
                                        </div>
                                        <div class="col col-auto text-right">
                                            <i class="fas fa-arrow-down f-50 text-c-green"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-6">
                            <div class="card bg-c-red text-white">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <p class="m-b-5">Withdrawals</p>
                                            <h4 class="m-b-0"><?= number_format($summary['withdrawal_count'] ?? 0) ?></h4>
                                        </div>
                                        <div class="col col-auto text-right">
                                            <i class="fas fa-arrow-up f-50 text-c-red"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-6">
                            <div class="card bg-c-yellow text-white">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <p class="m-b-5">Exchanges</p>
                                            <h4 class="m-b-0"><?= number_format($summary['exchange_count'] ?? 0) ?></h4>
                                        </div>
                                        <div class="col col-auto text-right">
                                            <i class="fas fa-sync f-50 text-c-yellow"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-6">
                            <div class="card bg-c-purple text-white">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <p class="m-b-5">Total Deposits</p>
                                            <h4 class="m-b-0">$<?= number_format($summary['total_deposits'] ?? 0, 2) ?></h4>
                                        </div>
                                        <div class="col col-auto text-right">
                                            <i class="fas fa-dollar-sign f-50 text-c-purple"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-2 col-md-6">
                            <div class="card bg-c-pink text-white">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <p class="m-b-5">Total Withdrawals</p>
                                            <h4 class="m-b-0">$<?= number_format($summary['total_withdrawals'] ?? 0, 2) ?></h4>
                                        </div>
                                        <div class="col col-auto text-right">
                                            <i class="fas fa-money-bill-wave f-50 text-c-pink"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-12">
                            <!-- Branch and Search Section -->
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="feather icon-filter mr-2"></i>Filter & Search</h5>
                                </div>
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
                                                        <input type="text" id="searchInput" class="form-control" placeholder="Search by customer name, reference number, or notes" value="<?= htmlspecialchars($search) ?>">
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

                            <!-- Sarafi Transactions Table Section -->
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="feather icon-list mr-2"></i>Sarafi Transactions</h5>
                                </div>
                                <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" width="50">#</th>
                                                        <th width="100">Action</th>
                                                        <th>Customer Details</th>
                                                        <th>Transaction Info</th>
                                                        <th>Amount & Currency</th>
                                                        <th>Branch</th>
                                                        <th>Status & Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="sarafiTable">
                                                    <?php
                                                    $counter = $offset + 1;
                                                    foreach ($transactions as $transaction):
                                                    ?>
                                                    <tr>
                                                        <td class="text-center"><?= $counter++ ?></td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                                    <i class="feather icon-more-vertical"></i>
                                                                </button>
                                                                <div class="dropdown-menu dropdown-menu-right">
                                                                    <button class="dropdown-item view-details" data-transaction='<?= htmlspecialchars(json_encode($transaction)) ?>'>
                                                                        <i class="feather icon-eye text-primary mr-2"></i> View Details
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="customer-info">
                                                                <div class="customer-info__name">
                                                                    <strong><?= htmlspecialchars($transaction['customer_name'] ?? 'Unknown Customer') ?></strong>
                                                                </div>
                                                                <?php if (!empty($transaction['customer_phone'])): ?>
                                                                <div class="customer-info__phone">
                                                                    <small class="text-muted">
                                                                        <i class="feather icon-phone mr-1"></i>
                                                                        <?= htmlspecialchars($transaction['customer_phone']) ?>
                                                                    </small>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="transaction-info">
                                                                <div class="transaction-info__type">
                                                                    <span class="badge badge-<?= getTransactionTypeBadgeClass($transaction['type']) ?>">
                                                                        <?= ucfirst(htmlspecialchars($transaction['type'])) ?>
                                                                    </span>
                                                                </div>
                                                                <?php if (!empty($transaction['reference_number'])): ?>
                                                                <div class="transaction-info__reference">
                                                                    <small class="text-muted">
                                                                        Ref: <?= htmlspecialchars($transaction['reference_number']) ?>
                                                                    </small>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="amount-info">
                                                                <div class="amount-info__amount">
                                                                    <strong class="text-<?= $transaction['currency'] === 'USD' ? 'success' : 'info' ?>">
                                                                        <?= $transaction['currency'] === 'USD' ? '$' : $transaction['currency'] . ' ' ?>
                                                                        <?= number_format($transaction['amount'], 2) ?>
                                                                    </strong>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <span class="badge badge-secondary">
                                                                <?= htmlspecialchars($transaction['branch_name'] ?? 'N/A') ?>
                                                            </span>
                                                        </td>

                                                        <td>
                                                            <div class="status-date-info">
                                                                <div class="status-date-info__status">
                                                                    <span class="badge badge-<?= $transaction['status'] === 'completed' ? 'success' : ($transaction['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                                        <?= ucfirst(htmlspecialchars($transaction['status'])) ?>
                                                                    </span>
                                                                </div>
                                                                <div class="status-date-info__date">
                                                                    <small class="text-muted">
                                                                        <?= date('d/m/Y', strtotime($transaction['created_at'])) ?>
                                                                    </small>
                                                                </div>
                                                                <div class="status-date-info__time">
                                                                    <small class="text-muted">
                                                                        <?= date('H:i', strtotime($transaction['created_at'])) ?>
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
                                                    Showing <?= min(($page - 1) * $results_per_page + 1, $total_transactions) ?> to <?= min($page * $results_per_page, $total_transactions) ?> of <?= $total_transactions ?> transactions
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

<!-- Transaction Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white border-0">
                <h5 class="modal-title">
                    <i class="feather icon-exchange mr-2"></i>Sarafi Transaction Details
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
                            <div class="transaction-summary">
                                <div class="transaction-summary__label">Transaction Amount</div>
                                <div class="transaction-summary__amount" id="transaction-amount">-</div>
                                <div class="transaction-summary__type" id="transaction-type">-</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="status-summary">
                                <div class="status-summary__label">Transaction Status</div>
                                <div class="status-summary__status" id="transaction-status">-</div>
                                <div class="status-summary__date" id="transaction-date">-</div>
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
                        <a class="nav-link" id="details-notes-tab" data-toggle="tab" href="#details-notes" role="tab">
                            <i class="feather icon-file-text mr-2"></i>Notes & Details
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
                                        <h6 class="card-subtitle mb-3 text-muted">Customer Information</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Customer Name</span>
                                            <strong id="customer-name">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Phone</span>
                                            <strong id="customer-phone">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Branch</span>
                                            <strong id="transaction-branch">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted">Transaction Details</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Type</span>
                                            <strong id="transaction-type-detail">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Amount</span>
                                            <strong id="transaction-amount-detail">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Currency</span>
                                            <strong id="transaction-currency">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Reference Number</span>
                                            <strong id="reference-number">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes & Details Tab -->
                    <div class="tab-pane fade" id="details-notes" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted">Additional Information</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Transaction ID</span>
                                    <strong id="transaction-id">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Status</span>
                                    <strong id="transaction-status-detail">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Created At</span>
                                    <strong id="created-at">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Updated At</span>
                                    <strong id="updated-at">-</strong>
                                </div>
                                <div class="mb-3">
                                    <span class="text-muted">Notes</span>
                                    <div class="mt-1">
                                        <p id="transaction-notes" class="mb-0 p-2 bg-light rounded">-</p>
                                    </div>
                                </div>
                                <?php if (!empty($transaction['receipt_path'])): ?>
                                <div class="mb-3">
                                    <span class="text-muted">Receipt</span>
                                    <div class="mt-1">
                                        <a id="receipt-link" href="#" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="feather icon-file mr-1"></i>View Receipt
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
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

<?php
function getTransactionTypeBadgeClass($type) {
    switch (strtolower($type)) {
        case 'deposit':
            return 'success';
        case 'withdrawal':
            return 'danger';
        case 'exchange':
            return 'primary';
        case 'hawala_send':
        case 'hawala_receive':
            return 'info';
        case 'adjustment':
            return 'warning';
        default:
            return 'secondary';
    }
}
?>

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
        const transactionData = JSON.parse(this.getAttribute('data-transaction'));

        // Populate modal with transaction data
        document.getElementById('transaction-amount').textContent = (transactionData.currency === 'USD' ? '$' : transactionData.currency + ' ') + parseFloat(transactionData.amount || 0).toFixed(2);
        document.getElementById('transaction-type').textContent = transactionData.type.charAt(0).toUpperCase() + transactionData.type.slice(1);
        document.getElementById('transaction-status').textContent = transactionData.status.charAt(0).toUpperCase() + transactionData.status.slice(1);
        document.getElementById('transaction-date').textContent = transactionData.created_at ? new Date(transactionData.created_at).toLocaleString() : 'N/A';

        document.getElementById('customer-name').textContent = transactionData.customer_name || 'Unknown Customer';
        document.getElementById('customer-phone').textContent = transactionData.customer_phone || 'N/A';
        document.getElementById('transaction-branch').textContent = transactionData.branch_name || 'N/A';

        document.getElementById('transaction-type-detail').textContent = transactionData.type.charAt(0).toUpperCase() + transactionData.type.slice(1);
        document.getElementById('transaction-amount-detail').textContent = (transactionData.currency === 'USD' ? '$' : transactionData.currency + ' ') + parseFloat(transactionData.amount || 0).toFixed(2);
        document.getElementById('transaction-currency').textContent = transactionData.currency;
        document.getElementById('reference-number').textContent = transactionData.reference_number || 'N/A';

        document.getElementById('transaction-id').textContent = transactionData.id;
        document.getElementById('transaction-status-detail').textContent = transactionData.status.charAt(0).toUpperCase() + transactionData.status.slice(1);
        document.getElementById('created-at').textContent = transactionData.created_at ? new Date(transactionData.created_at).toLocaleString() : 'N/A';
        document.getElementById('updated-at').textContent = transactionData.updated_at ? new Date(transactionData.updated_at).toLocaleString() : 'N/A';
        document.getElementById('transaction-notes').textContent = transactionData.notes || 'No notes available';

        // Show modal
        $('#detailsModal').modal('show');
    });
});
</script>