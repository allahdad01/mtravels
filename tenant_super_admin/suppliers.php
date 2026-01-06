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

// Build query for suppliers
$query = "SELECT
    s.*,
    b.name as branch_name,
    COUNT(st.id) as transaction_count,
    COALESCE(SUM(CASE WHEN st.transaction_type = 'Debit' THEN st.amount ELSE 0 END), 0) as total_debits,
    COALESCE(SUM(CASE WHEN st.transaction_type = 'Credit' THEN st.amount ELSE 0 END), 0) as total_credits
FROM suppliers s
LEFT JOIN branches b ON s.branch_id = b.id
LEFT JOIN supplier_transactions st ON s.id = st.supplier_id
WHERE s.tenant_id = ? AND s.status = 'active'";

// Add branch filtering
if ($selected_branch !== 'all') {
    $query .= " AND s.branch_id = ?";
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (s.name LIKE ? OR s.contact_person LIKE ? OR s.phone LIKE ? OR s.email LIKE ?)";
}

// Group by supplier and add ordering and pagination
$query .= " GROUP BY s.id ORDER BY s.created_at DESC LIMIT ? OFFSET ?";

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
    $params[] = $search_param;
}

$params[] = $results_per_page;
$params[] = $offset;

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM suppliers WHERE tenant_id = ? AND status = 'active'";
$count_params = [$tenant_id];

if ($selected_branch !== 'all') {
    $count_query .= " AND branch_id = ?";
    $count_params[] = $selected_branch;
}

if (!empty($search)) {
    $count_query .= " AND (name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_suppliers = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_suppliers / $results_per_page);

// Get summary data
$summary_query = "SELECT
    COUNT(*) as total_suppliers,
    SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END) as total_owed_by_suppliers,
    SUM(CASE WHEN balance < 0 THEN ABS(balance) ELSE 0 END) as total_owed_to_suppliers
FROM suppliers
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
    overflow: hidden;
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

.badge-danger {
    background-color: #dc3545;
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

.bg-c-red {
    background: linear-gradient(135deg, #f5365c 0%, #fb357a 100%) !important;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(245, 54, 92, 0.3);
}

.summary-card {
    border-radius: 10px;
    overflow: hidden;
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

.modal-header.bg-info {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
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
                                            <h5 class="mb-0"><i class="feather icon-users mr-2"></i>Suppliers</h5>
                                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;">Manage your suppliers and track balances</p>
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
                                    <div class="col-xl-4 col-md-6">
                                        <div class="card bg-c-blue text-white summary-card">
                                            <div class="card-body">
                                                <div class="row align-items-center">
                                                    <div class="col">
                                                        <p class="m-b-5">Total Suppliers</p>
                                                        <h4 class="m-b-0"><?= number_format($summary['total_suppliers'] ?? 0) ?></h4>
                                                    </div>
                                                    <div class="col col-auto text-right">
                                                        <i class="feather icon-users f-50"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xl-4 col-md-6">
                                        <div class="card bg-c-green text-white summary-card">
                                            <div class="card-body">
                                                <div class="row align-items-center">
                                                    <div class="col">
                                                        <p class="m-b-5">Total Owed By Suppliers</p>
                                                        <h4 class="m-b-0">$<?= number_format($summary['total_owed_by_suppliers'] ?? 0, 2) ?></h4>
                                                    </div>
                                                    <div class="col col-auto text-right">
                                                        <i class="feather icon-dollar-sign f-50"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xl-4 col-md-6">
                                        <div class="card bg-c-red text-white summary-card">
                                            <div class="card-body">
                                                <div class="row align-items-center">
                                                    <div class="col">
                                                        <p class="m-b-5">Total Owed To Suppliers</p>
                                                        <h4 class="m-b-0">$<?= number_format($summary['total_owed_to_suppliers'] ?? 0, 2) ?></h4>
                                                    </div>
                                                    <div class="col col-auto text-right">
                                                        <i class="feather icon-credit-card f-50"></i>
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
                                                                <input type="text" id="searchInput" class="form-control" placeholder="Search by supplier name, contact person, phone, or email" value="<?= htmlspecialchars($search) ?>">
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

                                        <!-- Suppliers Table Section -->
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="feather icon-list mr-2"></i>Suppliers List</h5>
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th class="text-center" width="50">#</th>
                                                                <th width="100"><i class="feather icon-cog mr-1"></i>Action</th>
                                                                <th><i class="feather icon-user mr-1"></i>Supplier Info</th>
                                                                <th><i class="feather icon-phone mr-1"></i>Contact Details</th>
                                                                <th><i class="feather icon-dollar-sign mr-1"></i>Balance</th>
                                                                <th><i class="feather icon-home mr-1"></i>Branch</th>
                                                                <th><i class="feather icon-activity mr-1"></i>Activity</th>
                                                                <th><i class="feather icon-check-circle mr-1"></i>Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="supplierTable">
                                                            <?php
                                                            $counter = $offset + 1;
                                                            foreach ($suppliers as $supplier):
                                                            ?>
                                                            <tr>
                                                                <td class="text-center"><?= $counter++ ?></td>
                                                                <td>
                                                                    <div class="dropdown">
                                                                        <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                                            <i class="feather icon-more-vertical"></i>
                                                                        </button>
                                                                        <div class="dropdown-menu dropdown-menu-right">
                                                                            <button class="dropdown-item view-details" data-supplier='<?= htmlspecialchars(json_encode($supplier)) ?>'>
                                                                                <i class="feather icon-eye text-primary mr-2"></i> View Details
                                                                            </button>
                                                                            <button class="dropdown-item view-transactions" data-supplier-id="<?= $supplier['id'] ?>" data-supplier-name="<?= htmlspecialchars($supplier['name']) ?>">
                                                                                <i class="feather icon-list text-info mr-2"></i> View Transactions
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="supplier-info">
                                                                        <div class="supplier-info__details">
                                                                            <div class="supplier-info__name">
                                                                                <strong><?= htmlspecialchars($supplier['name']) ?></strong>
                                                                            </div>
                                                                            <div class="supplier-info__type">
                                                                                <span class="badge badge-<?= $supplier['supplier_type'] === 'External' ? 'primary' : 'secondary' ?>">
                                                                                    <i class="feather <?= $supplier['supplier_type'] === 'External' ? 'icon-external-link' : 'icon-home' ?> mr-1"></i><?= ucfirst(htmlspecialchars($supplier['supplier_type'])) ?>
                                                                                </span>
                                                                            </div>
                                                                            <?php if (!empty($supplier['contact_person'])): ?>
                                                                            <div class="supplier-info__contact">
                                                                                <i class="feather icon-user mr-1 text-muted"></i><?= htmlspecialchars($supplier['contact_person']) ?>
                                                                            </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="contact-info">
                                                                        <div class="contact-info__details">
                                                                            <?php if (!empty($supplier['phone'])): ?>
                                                                            <div class="contact-info__phone">
                                                                                <i class="feather icon-phone text-primary mr-1"></i>
                                                                                <?= htmlspecialchars($supplier['phone']) ?>
                                                                            </div>
                                                                            <?php endif; ?>
                                                                            <?php if (!empty($supplier['email'])): ?>
                                                                            <div class="contact-info__email">
                                                                                <i class="feather icon-mail text-info mr-1"></i>
                                                                                <?= htmlspecialchars($supplier['email']) ?>
                                                                            </div>
                                                                            <?php endif; ?>
                                                                            <?php if (empty($supplier['phone']) && empty($supplier['email'])): ?>
                                                                            <div class="text-muted">
                                                                                <i class="feather icon-minus-circle mr-1"></i><em>No contact info</em>
                                                                            </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <div class="balance-info">
                                                                        <div class="balance-info__amount">
                                                                            <span class="text-<?= $supplier['balance'] >= 0 ? 'success' : 'danger' ?>">
                                                                                <strong>
                                                                                    <?= $supplier['currency'] === 'USD' ? '$' : 'AFS ' ?>
                                                                                    <?= number_format(abs($supplier['balance']), 2) ?>
                                                                                </strong>
                                                                            </span>
                                                                        </div>
                                                                        <div class="balance-info__status">
                                                                            <small class="text-muted">
                                                                                <i class="feather <?= $supplier['balance'] >= 0 ? 'icon-trending-up text-success' : 'icon-trending-down text-danger' ?> mr-1"></i>
                                                                                <?= $supplier['balance'] >= 0 ? 'Owed by supplier' : 'Owed to supplier' ?>
                                                                            </small>
                                                                        </div>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <span class="badge badge-secondary">
                                                                        <i class="feather icon-home mr-1"></i><?= htmlspecialchars($supplier['branch_name'] ?? 'N/A') ?>
                                                                    </span>
                                                                </td>

                                                                <td>
                                                                    <div class="activity-info">
                                                                        <div class="activity-info__transactions">
                                                                            <i class="feather icon-activity text-muted mr-1"></i>
                                                                            <?= number_format($supplier['transaction_count']) ?> transactions
                                                                        </div>
                                                                        <div class="activity-info__summary">
                                                                            <small class="text-muted">
                                                                                <i class="feather icon-trending-up text-danger mr-1"></i>$<?= number_format($supplier['total_debits'], 2) ?>
                                                                                <i class="feather icon-trending-down text-success ml-2 mr-1"></i>$<?= number_format($supplier['total_credits'], 2) ?>
                                                                            </small>
                                                                        </div>
                                                                    </div>
                                                                </td>

                                                                <td>
                                                                    <span class="badge badge-<?= $supplier['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                                        <i class="feather <?= $supplier['status'] === 'active' ? 'icon-check-circle' : 'icon-x-circle' ?> mr-1"></i><?= ucfirst(htmlspecialchars($supplier['status'])) ?>
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
                                                            <i class="feather icon-info mr-1"></i>Showing <?= min(($page - 1) * $results_per_page + 1, $total_suppliers) ?> to <?= min($page * $results_per_page, $total_suppliers) ?> of <?= $total_suppliers ?> suppliers
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

<!-- Supplier Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white border-0">
                <h5 class="modal-title">
                    <i class="feather icon-user mr-2"></i>Supplier Details
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
                            <div class="activity-summary">
                                <div class="activity-summary__label">Total Transactions</div>
                                <div class="activity-summary__count" id="total-transactions">-</div>
                                <div class="activity-summary__period">All time</div>
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
                                        <h6 class="card-subtitle mb-3 text-muted">Supplier Information</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Supplier Name</span>
                                            <strong id="supplier-name">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Type</span>
                                            <strong id="supplier-type">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Currency</span>
                                            <strong id="supplier-currency">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Status</span>
                                            <strong id="supplier-status">-</strong>
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
                                        <h6 class="card-subtitle mb-3 text-muted">Financial Summary</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Total Debits</span>
                                            <strong class="text-danger" id="total-debits">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Total Credits</span>
                                            <strong class="text-success" id="total-credits">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Net Position</span>
                                            <strong id="net-position">-</strong>
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
                                    <span class="text-muted">Contact Person</span>
                                    <strong id="contact-person">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Phone</span>
                                    <strong id="contact-phone">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Email</span>
                                    <strong id="contact-email">-</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Address</span>
                                    <strong id="contact-address">-</strong>
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
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white border-0">
                <h5 class="modal-title">
                    <i class="feather icon-list mr-2"></i>Supplier Transactions - <span id="supplier-name-header"></span>
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
        const supplierData = JSON.parse(this.getAttribute('data-supplier'));

        // Populate modal with supplier data
        document.getElementById('current-balance').textContent = (supplierData.currency === 'USD' ? '$' : 'AFS ') + Math.abs(supplierData.balance || 0).toFixed(2);
        document.getElementById('balance-status').textContent = (supplierData.balance >= 0 ? 'Owed by supplier' : 'Owed to supplier');
        document.getElementById('total-transactions').textContent = supplierData.transaction_count;

        document.getElementById('supplier-name').textContent = supplierData.name;
        document.getElementById('supplier-type').textContent = supplierData.supplier_type;
        document.getElementById('supplier-currency').textContent = supplierData.currency;
        document.getElementById('supplier-status').textContent = supplierData.status.charAt(0).toUpperCase() + supplierData.status.slice(1);
        document.getElementById('created-at').textContent = supplierData.created_at || 'N/A';

        document.getElementById('total-debits').textContent = '$' + parseFloat(supplierData.total_debits || 0).toFixed(2);
        document.getElementById('total-credits').textContent = '$' + parseFloat(supplierData.total_credits || 0).toFixed(2);
        const netPosition = parseFloat(supplierData.total_credits || 0) - parseFloat(supplierData.total_debits || 0);
        document.getElementById('net-position').innerHTML = (netPosition >= 0 ? '<span class="text-success">+$' : '<span class="text-danger">-$') + Math.abs(netPosition).toFixed(2) + '</span>';

        document.getElementById('contact-person').textContent = supplierData.contact_person || 'N/A';
        document.getElementById('contact-phone').textContent = supplierData.phone || 'N/A';
        document.getElementById('contact-email').textContent = supplierData.email || 'N/A';
        document.getElementById('contact-address').textContent = supplierData.address || 'N/A';

        // Show modal
        $('#detailsModal').modal('show');
    });
});

// Handle view transactions modal
document.querySelectorAll('.view-transactions').forEach(button => {
    button.addEventListener('click', function() {
        const supplierId = this.getAttribute('data-supplier-id');
        const supplierName = this.getAttribute('data-supplier-name');

        document.getElementById('supplier-name-header').textContent = supplierName;

        // Load transactions via AJAX
        fetch('get_supplier_transactions.php?supplier_id=' + supplierId)
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
