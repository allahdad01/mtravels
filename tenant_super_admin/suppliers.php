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
                                    <h5 class="m-b-10">Suppliers</h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="javascript:">Suppliers</a></li>
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
                            <div class="col-xl-4 col-md-6">
                                <div class="card bg-c-blue text-white">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <p class="m-b-5">Total Suppliers</p>
                                                <h4 class="m-b-0"><?= number_format($summary['total_suppliers'] ?? 0) ?></h4>
                                            </div>
                                            <div class="col col-auto text-right">
                                                <i class="fas fa-users f-50 text-c-blue"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-4 col-md-6">
                                <div class="card bg-c-green text-white">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <p class="m-b-5">Total Owed By Suppliers</p>
                                                <h4 class="m-b-0">$<?= number_format($summary['total_owed_by_suppliers'] ?? 0, 2) ?></h4>
                                            </div>
                                            <div class="col col-auto text-right">
                                                <i class="fas fa-dollar-sign f-50 text-c-green"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-4 col-md-6">
                                <div class="card bg-c-red text-white">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <p class="m-b-5">Total Owed To Suppliers</p>
                                                <h4 class="m-b-0">$<?= number_format($summary['total_owed_to_suppliers'] ?? 0, 2) ?></h4>
                                            </div>
                                            <div class="col col-auto text-right">
                                                <i class="fas fa-money-bill-wave f-50 text-c-red"></i>
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
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" width="50">#</th>
                                                        <th width="100">Action</th>
                                                        <th>Supplier Info</th>
                                                        <th>Contact Details</th>
                                                        <th>Balance</th>
                                                        <th>Branch</th>
                                                        <th>Activity</th>
                                                        <th>Status</th>
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
                                                                            <?= ucfirst(htmlspecialchars($supplier['supplier_type'])) ?>
                                                                        </span>
                                                                    </div>
                                                                    <?php if (!empty($supplier['contact_person'])): ?>
                                                                    <div class="supplier-info__contact">
                                                                        Contact: <?= htmlspecialchars($supplier['contact_person']) ?>
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
                                                                        <i class="feather icon-phone mr-1"></i>
                                                                        <?= htmlspecialchars($supplier['phone']) ?>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($supplier['email'])): ?>
                                                                    <div class="contact-info__email">
                                                                        <i class="feather icon-mail mr-1"></i>
                                                                        <?= htmlspecialchars($supplier['email']) ?>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                    <?php if (empty($supplier['phone']) && empty($supplier['email'])): ?>
                                                                    <div class="text-muted">
                                                                        <em>No contact info</em>
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
                                                                        <?= $supplier['balance'] >= 0 ? 'Owed by supplier' : 'Owed to supplier' ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <span class="badge badge-secondary">
                                                                <?= htmlspecialchars($supplier['branch_name'] ?? 'N/A') ?>
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
                                                                        Debits: $<?= number_format($supplier['total_debits'], 2) ?><br>
                                                                        Credits: $<?= number_format($supplier['total_credits'], 2) ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <span class="badge badge-<?= $supplier['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                                <?= ucfirst(htmlspecialchars($supplier['status'])) ?>
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
                                                    Showing <?= min(($page - 1) * $results_per_page + 1, $total_suppliers) ?> to <?= min($page * $results_per_page, $total_suppliers) ?> of <?= $total_suppliers ?> suppliers
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