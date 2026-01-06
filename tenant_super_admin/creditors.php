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

// Build query for creditors
$query = "SELECT c.*,
                 b.name as branch_name,
                 COUNT(ct.id) as transaction_count,
                 COALESCE(SUM(CASE WHEN ct.transaction_type = 'debit' THEN ct.amount ELSE -ct.amount END), 0) as current_balance
          FROM creditors c
          LEFT JOIN branches b ON c.branch_id = b.id
          LEFT JOIN creditor_transactions ct ON c.id = ct.creditor_id AND ct.tenant_id = c.tenant_id
          WHERE c.tenant_id = ?";

// Add branch filtering
if ($selected_branch !== 'all') {
    $query .= " AND c.branch_id = ?";
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
}

// Group by and ordering with pagination
$query .= " GROUP BY c.id ORDER BY c.name ASC LIMIT ? OFFSET ?";

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
$creditors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM creditors c WHERE c.tenant_id = ?";
$count_params = [$tenant_id];

if ($selected_branch !== 'all') {
    $count_query .= " AND c.branch_id = ?";
    $count_params[] = $selected_branch;
}

if (!empty($search)) {
    $count_query .= " AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_creditors = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_creditors / $results_per_page);

// Get summary data
$summary_query = "SELECT
    COUNT(*) as total_creditors,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_creditors,
    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_creditors,
    SUM(balance) as total_outstanding,
    AVG(balance) as avg_credit_amount
FROM creditors
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
                                        <h5 class="mb-0"><i class="feather icon-users mr-2"></i>Creditors Management</h5>
                                        <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;">Manage your creditors and track outstanding balances</p>
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
                                    <div class="card text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col">
                                                    <p class="m-b-5 text-white" style="opacity: 0.9;">Total Creditors</p>
                                                    <h4 class="m-b-0 font-weight-bold"><?= number_format($summary['total_creditors'] ?? 0) ?></h4>
                                                </div>
                                                <div class="col col-auto text-right">
                                                    <i class="fas fa-users f-50" style="opacity: 0.3;"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-md-6">
                                    <div class="card text-white" style="background: linear-gradient(135deg, #2ed8b6 0%, #20bf6b 100%);">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col">
                                                    <p class="m-b-5 text-white" style="opacity: 0.9;">Active Creditors</p>
                                                    <h4 class="m-b-0 font-weight-bold"><?= number_format($summary['active_creditors'] ?? 0) ?></h4>
                                                </div>
                                                <div class="col col-auto text-right">
                                                    <i class="fas fa-user-check f-50" style="opacity: 0.3;"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-md-6">
                                    <div class="card text-white" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col">
                                                    <p class="m-b-5 text-white" style="opacity: 0.9;">Total Outstanding</p>
                                                    <h4 class="m-b-0 font-weight-bold">$<?= number_format($summary['total_outstanding'] ?? 0, 2) ?></h4>
                                                </div>
                                                <div class="col col-auto text-right">
                                                    <i class="fas fa-dollar-sign f-50" style="opacity: 0.3;"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-md-6">
                                    <div class="card text-white" style="background: linear-gradient(135deg, #feca57 0%, #ff9f43 100%);">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col">
                                                    <p class="m-b-5 text-white" style="opacity: 0.9;">Average Credit</p>
                                                    <h4 class="m-b-0 font-weight-bold">$<?= number_format($summary['avg_credit_amount'] ?? 0, 2) ?></h4>
                                                </div>
                                                <div class="col col-auto text-right">
                                                    <i class="fas fa-chart-line f-50" style="opacity: 0.3;"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <!-- Branch and Search Section -->
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col-md-4">
                                                    <div class="branch-selector">
                                                        <label for="branchSelect" class="form-label mb-2">
                                                            <i class="feather icon-map-pin mr-1"></i>Select Branch:
                                                        </label>
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
                                                <div class="col-md-8">
                                                    <div class="search-box">
                                                        <label for="searchInput" class="form-label mb-2">
                                                            <i class="feather icon-search mr-1"></i>Search:
                                                        </label>
                                                        <div class="input-group">
                                                            <input type="text" id="searchInput" class="form-control" placeholder="Search by creditor name, email, or phone" value="<?= htmlspecialchars($search) ?>">
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

                                    <!-- Creditors Table Section -->
                                    <div class="card">
                                        <div class="card-header">
                                            <h5><i class="feather icon-list mr-2"></i>Creditors List</h5>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center" width="50">#</th>
                                                            <th width="100">
                                                                <i class="feather icon-cog mr-1"></i>Action
                                                            </th>
                                                            <th>
                                                                <i class="feather icon-user mr-1"></i>Creditor Details
                                                            </th>
                                                            <th>
                                                                <i class="feather icon-dollar-sign mr-1"></i>Financial Info
                                                            </th>
                                                            <th>
                                                                <i class="feather icon-map-pin mr-1"></i>Branch
                                                            </th>
                                                            <th>
                                                                <i class="feather icon-info mr-1"></i>Status
                                                            </th>
                                                            <th>
                                                                <i class="feather icon-calendar mr-1"></i>Created Date
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="creditorsTable">
                                                        <?php
                                                        $counter = $offset + 1;
                                                        foreach ($creditors as $creditor):
                                                        ?>
                                                        <tr>
                                                            <td class="text-center"><?= $counter++ ?></td>
                                                            <td>
                                                                <div class="dropdown">
                                                                    <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                                        <i class="feather icon-more-vertical"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu dropdown-menu-right">
                                                                        <button class="dropdown-item view-details" data-creditor='<?= htmlspecialchars(json_encode($creditor)) ?>'>
                                                                            <i class="feather icon-eye text-primary mr-2"></i> View Details
                                                                        </button>
                                                                        <button class="dropdown-item view-transactions" data-creditor-id="<?= $creditor['id'] ?>" data-creditor-name="<?= htmlspecialchars($creditor['name']) ?>">
                                                                            <i class="feather icon-activity text-info mr-2"></i> View Transactions
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <td>
                                                                <div class="creditor-info">
                                                                    <div class="creditor-info__name">
                                                                        <strong><?= htmlspecialchars($creditor['name']) ?></strong>
                                                                    </div>
                                                                    <?php if (!empty($creditor['email'])): ?>
                                                                    <div class="creditor-info__email">
                                                                        <small class="text-muted">
                                                                            <i class="feather icon-mail mr-1"></i>
                                                                            <?= htmlspecialchars($creditor['email']) ?>
                                                                        </small>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($creditor['phone'])): ?>
                                                                    <div class="creditor-info__phone">
                                                                        <small class="text-muted">
                                                                            <i class="feather icon-phone mr-1"></i>
                                                                            <?= htmlspecialchars($creditor['phone']) ?>
                                                                        </small>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>

                                                            <td>
                                                                <div class="financial-info">
                                                                    <div class="financial-info__balance">
                                                                        <strong class="text-<?= $creditor['current_balance'] >= 0 ? 'success' : 'danger' ?>">
                                                                            $<?= number_format(abs($creditor['current_balance']), 2) ?>
                                                                        </strong>
                                                                        <small class="text-muted d-block">
                                                                            (<?= $creditor['current_balance'] >= 0 ? 'We owe' : 'Owed to us' ?>)
                                                                        </small>
                                                                    </div>
                                                                    <div class="financial-info__transactions mt-1">
                                                                        <small class="text-muted">
                                                                            <i class="feather icon-activity mr-1"></i>
                                                                            <?= number_format($creditor['transaction_count']) ?> transactions
                                                                        </small>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <td>
                                                                <span class="badge badge-secondary">
                                                                    <?= htmlspecialchars($creditor['branch_name'] ?? 'N/A') ?>
                                                                </span>
                                                            </td>

                                                            <td>
                                                                <div class="status-info">
                                                                    <span class="badge badge-<?= $creditor['status'] === 'active' ? 'success' : 'secondary' ?> badge-pill">
                                                                        <?= ucfirst(htmlspecialchars($creditor['status'])) ?>
                                                                    </span>
                                                                </div>
                                                            </td>

                                                            <td>
                                                                <div class="date-info">
                                                                    <div class="date-info__date">
                                                                        <?= date('d/m/Y', strtotime($creditor['created_at'])) ?>
                                                                    </div>
                                                                    <div class="date-info__time">
                                                                        <small class="text-muted">
                                                                            <?= date('H:i', strtotime($creditor['created_at'])) ?>
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
                                            <?php if ($total_pages > 1): ?>
                                            <div class="card-footer bg-white">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="text-muted">
                                                        Showing <?= min(($page - 1) * $results_per_page + 1, $total_creditors) ?> to <?= min($page * $results_per_page, $total_creditors) ?> of <?= $total_creditors ?> creditors
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
        color: #ffffff;
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

    .form-label {
        font-weight: 500;
        color: #495057;
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

    .badge {
        font-size: 0.85em;
        padding: 0.5em 0.75em;
        border-radius: 20px;
        font-weight: 500;
    }

    .badge-success {
        background-color: #28a745;
    }

    .badge-secondary {
        background-color: #6c757d;
    }

    .pagination .page-link {
        border-radius: 50%;
        margin: 0 2px;
        border: none;
        color: #495057;
    }

    .pagination .page-item.active .page-link {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        color: white;
    }

    .pagination .page-link:hover {
        background: #f1f3f4;
    }

    .dropdown-menu {
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border: none;
    }

    .dropdown-item {
        border-radius: 5px;
        margin: 2px 5px;
        padding: 0.5rem 1rem;
    }

    .dropdown-item:hover {
        background-color: #f1f3f4;
    }
</style>

<!-- Creditor Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h5 class="modal-title">
                    <i class="feather icon-user mr-2"></i>Creditor Details
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
                                        <h6 class="card-subtitle mb-3 text-muted">Creditor Information</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Name</span>
                                            <strong id="creditor-name">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Status</span>
                                            <strong id="creditor-status">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Branch</span>
                                            <strong id="creditor-branch">-</strong>
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
                                            <strong id="creditor-currency">-</strong>
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
                                    <strong id="creditor-email">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Phone</span>
                                    <strong id="creditor-phone">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Address</span>
                                    <strong id="creditor-address">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Creditor ID</span>
                                    <strong id="creditor-id">-</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Created At</span>
                                    <strong id="creditor-created-at">-</strong>
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
            <div class="modal-header text-white border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h5 class="modal-title">
                    <i class="feather icon-activity mr-2"></i>Transaction History - <span id="creditor-name-header"></span>
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
        const creditorData = JSON.parse(this.getAttribute('data-creditor'));

        // Populate modal with creditor data
        const balance = parseFloat(creditorData.current_balance || 0);
        document.getElementById('current-balance').textContent = '$' + Math.abs(balance).toFixed(2);
        document.getElementById('balance-status').textContent = balance >= 0 ? 'We owe' : 'Owed to us';
        document.getElementById('transaction-count').textContent = creditorData.transaction_count + ' transactions';
        document.getElementById('created-date').textContent = creditorData.created_at ? new Date(creditorData.created_at).toLocaleString() : 'N/A';

        document.getElementById('creditor-name').textContent = creditorData.name;
        document.getElementById('creditor-status').textContent = creditorData.status.charAt(0).toUpperCase() + creditorData.status.slice(1);
        document.getElementById('creditor-branch').textContent = creditorData.branch_name || 'N/A';

        document.getElementById('original-balance').textContent = '$' + parseFloat(creditorData.balance || 0).toFixed(2);
        document.getElementById('current-balance-detail').textContent = '$' + Math.abs(balance).toFixed(2);
        document.getElementById('creditor-currency').textContent = creditorData.currency;
        document.getElementById('total-transactions').textContent = creditorData.transaction_count;

        document.getElementById('creditor-email').textContent = creditorData.email || 'N/A';
        document.getElementById('creditor-phone').textContent = creditorData.phone || 'N/A';
        document.getElementById('creditor-address').textContent = creditorData.address || 'N/A';
        document.getElementById('creditor-id').textContent = creditorData.id;
        document.getElementById('creditor-created-at').textContent = creditorData.created_at ? new Date(creditorData.created_at).toLocaleString() : 'N/A';

        // Show modal
        $('#detailsModal').modal('show');
    });
});

// Handle view transactions modal
document.querySelectorAll('.view-transactions').forEach(button => {
    button.addEventListener('click', function() {
        const creditorId = this.getAttribute('data-creditor-id');
        const creditorName = this.getAttribute('data-creditor-name');

        document.getElementById('creditor-name-header').textContent = creditorName;

        // Load transactions via AJAX
        fetch('get_creditor_transactions.php?creditor_id=' + creditorId)
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