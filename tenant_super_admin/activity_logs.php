<?php
include 'header.php';

// Get tenant and user info
$tenant_id = $_SESSION['tenant_id'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_branch_id = $_SESSION['branch_id'] ?? null;

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$user_filter = isset($_GET['user_id']) ? trim($_GET['user_id']) : '';
$action_filter = isset($_GET['action']) ? trim($_GET['action']) : '';
$table_filter = isset($_GET['table_name']) ? trim($_GET['table_name']) : '';

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

// Build query for activity logs
$query = "SELECT al.*,
                 u.name as user_name,
                 b.name as branch_name
          FROM activity_log al
          LEFT JOIN users u ON al.user_id = u.id
          LEFT JOIN branches b ON u.branch_id = b.id
          WHERE al.tenant_id = ?";

// Add branch filtering
if ($user_branch_id) {
    $query .= " AND u.branch_id = ?";
}

// Add filters
if (!empty($user_filter)) {
    $query .= " AND al.user_id = ?";
}
if (!empty($action_filter)) {
    $query .= " AND al.action = ?";
}
if (!empty($table_filter)) {
    $query .= " AND al.table_name = ?";
}

// Add search filter
if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR al.action LIKE ? OR al.table_name LIKE ? OR al.record_id LIKE ?)";
}

// Group by and ordering with pagination
$query .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";

// Prepare parameters
$params = [$tenant_id];

if ($user_branch_id) {
    $params[] = $user_branch_id;
}

if (!empty($user_filter)) {
    $params[] = $user_filter;
}
if (!empty($action_filter)) {
    $params[] = $action_filter;
}
if (!empty($table_filter)) {
    $params[] = $table_filter;
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
$activity_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM activity_log al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.tenant_id = ?";
$count_params = [$tenant_id];

if ($user_branch_id) {
    $count_query .= " AND u.branch_id = ?";
    $count_params[] = $user_branch_id;
}

if (!empty($user_filter)) {
    $count_query .= " AND al.user_id = ?";
    $count_params[] = $user_filter;
}
if (!empty($action_filter)) {
    $count_query .= " AND al.action = ?";
    $count_params[] = $action_filter;
}
if (!empty($table_filter)) {
    $count_query .= " AND al.table_name = ?";
    $count_params[] = $table_filter;
}

if (!empty($search)) {
    $count_query .= " AND (u.name LIKE ? OR al.action LIKE ? OR al.table_name LIKE ? OR al.record_id LIKE ?)";
    $search_param = "%$search%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_logs = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_logs / $results_per_page);

// Get filter options
$users_query = "SELECT DISTINCT u.id, u.name FROM users u
                WHERE u.tenant_id = ?";
$user_params = [$tenant_id];

if ($user_branch_id) {
    $users_query .= " AND u.branch_id = ?";
    $user_params[] = $user_branch_id;
}

$users_query .= " ORDER BY u.name";
$users_stmt = $pdo->prepare($users_query);
$users_stmt->execute($user_params);
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

$actions_query = "SELECT DISTINCT action FROM activity_log WHERE tenant_id = ?";
$action_params = [$tenant_id];

if ($user_branch_id) {
    $actions_query .= " AND user_id IN (SELECT id FROM users WHERE tenant_id = ? AND branch_id = ?)";
    $action_params[] = $tenant_id;
    $action_params[] = $user_branch_id;
}

$actions_stmt = $pdo->prepare($actions_query);
$actions_stmt->execute($action_params);
$actions = $actions_stmt->fetchAll(PDO::FETCH_ASSOC);

$tables_query = "SELECT DISTINCT table_name FROM activity_log WHERE tenant_id = ?";
$table_params = [$tenant_id];

if ($user_branch_id) {
    $tables_query .= " AND user_id IN (SELECT id FROM users WHERE tenant_id = ? AND branch_id = ?)";
    $table_params[] = $tenant_id;
    $table_params[] = $user_branch_id;
}

$tables_stmt = $pdo->prepare($tables_query);
$tables_stmt->execute($table_params);
$tables = $tables_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary data
$summary_query = "SELECT
    COUNT(*) as total_logs,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(DISTINCT DATE(created_at)) as active_days
FROM activity_log
WHERE tenant_id = ?";

$summary_params = [$tenant_id];

if ($user_branch_id) {
    $summary_query .= " AND user_id IN (SELECT id FROM users WHERE tenant_id = ? AND branch_id = ?)";
    $summary_params[] = $tenant_id;
    $summary_params[] = $user_branch_id;
}

$summary_stmt = $pdo->prepare($summary_query);
$summary_stmt->execute($summary_params);
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
?>

<style>
    /* Enhanced custom styles for better layout and design */
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

    .summary-card {
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        border: none;
    }

    .summary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .summary-card .card-body {
        padding: 1.5rem;
    }

    .summary-card .h2 {
        font-size: 2.5rem;
    }

    .summary-card .h4 {
        font-size: 1.5rem;
    }

    .summary-card .text-muted {
        font-size: 0.875rem;
    }

    .summary-card i {
        opacity: 0.8;
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
                                            <h5 class="mb-0"><i class="feather icon-activity mr-2"></i>Activity Logs</h5>
                                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;">View and monitor all system activity logs</p>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <a href="dashboard.php" class="btn btn-sm">
                                                <i class="feather icon-home mr-1"></i>Dashboard
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Summary Cards -->
                                    <div class="col-md-4">
                                        <div class="card summary-card text-white bg-info">
                                            <div class="card-body">
                                                <div class="text-center mb-3">
                                                    <i class="feather icon-list f-50"></i>
                                                </div>
                                                <div class="text-center">
                                                    <div class="h2 font-weight-bold"><?= number_format($summary['total_logs'] ?? 0) ?></div>
                                                    <p class="text-white-50 mb-0">Total Logs</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card summary-card text-white bg-success">
                                            <div class="card-body">
                                                <div class="text-center mb-3">
                                                    <i class="feather icon-users f-50"></i>
                                                </div>
                                                <div class="text-center">
                                                    <div class="h2 font-weight-bold"><?= number_format($summary['unique_users'] ?? 0) ?></div>
                                                    <p class="text-white-50 mb-0">Active Users</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card summary-card text-white bg-warning">
                                            <div class="card-body">
                                                <div class="text-center mb-3">
                                                    <i class="feather icon-calendar f-50"></i>
                                                </div>
                                                <div class="text-center">
                                                    <div class="h2 font-weight-bold"><?= number_format($summary['active_days'] ?? 0) ?></div>
                                                    <p class="text-white-50 mb-0">Active Days</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                        <div class="row">
                            <div class="col-sm-12">
                                <!-- Filters Section -->
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <form method="GET" action="">
                                            <div class="row align-items-end">
                                                <div class="col-md-2">
                                                    <div class="form-group">
                                                        <label>Search</label>
                                                        <input type="text" class="form-control" name="search" placeholder="Search logs..." value="<?= htmlspecialchars($search) ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-group">
                                                        <label>User</label>
                                                        <select class="form-control" name="user_id">
                                                            <option value="">All Users</option>
                                                            <?php foreach ($users as $user): ?>
                                                            <option value="<?= $user['id'] ?>" <?= $user_filter == $user['id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($user['name']) ?>
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-group">
                                                        <label>Action</label>
                                                        <select class="form-control" name="action">
                                                            <option value="">All Actions</option>
                                                            <?php foreach ($actions as $action): ?>
                                                            <option value="<?= htmlspecialchars($action['action']) ?>" <?= $action_filter == $action['action'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($action['action']) ?>
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-group">
                                                        <label>Table</label>
                                                        <select class="form-control" name="table_name">
                                                            <option value="">All Tables</option>
                                                            <?php foreach ($tables as $table): ?>
                                                            <option value="<?= htmlspecialchars($table['table_name']) ?>" <?= $table_filter == $table['table_name'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($table['table_name']) ?>
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="submit" class="btn btn-primary btn-block">
                                                        <i class="feather icon-filter mr-1"></i>Filter
                                                    </button>
                                                </div>
                                                <div class="col-md-2">
                                                    <a href="?" class="btn btn-secondary btn-block">
                                                        <i class="feather icon-refresh-ccw mr-1"></i>Clear
                                                    </a>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Activity Logs Table Section -->
                                <div class="card">
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" width="50">#</th>
                                                        <th width="100">Action</th>
                                                        <th>User & Branch</th>
                                                        <th>Activity Details</th>
                                                        <th>Record Info</th>
                                                        <th>IP Address</th>
                                                        <th>Timestamp</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="activityTable">
                                                    <?php
                                                    $counter = $offset + 1;
                                                    foreach ($activity_logs as $log):
                                                    ?>
                                                    <tr>
                                                        <td class="text-center"><?= $counter++ ?></td>
                                                        <td>
                                                            <div class="dropdown">
                                                                <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                                    <i class="feather icon-more-vertical"></i>
                                                                </button>
                                                                <div class="dropdown-menu dropdown-menu-right">
                                                                    <button class="dropdown-item view-details" data-log='<?= htmlspecialchars(json_encode($log)) ?>'>
                                                                        <i class="feather icon-eye text-primary mr-2"></i> View Details
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="user-info">
                                                                <div class="user-info__name">
                                                                    <strong><?= htmlspecialchars($log['user_name'] ?? 'System') ?></strong>
                                                                </div>
                                                                <?php if (!empty($log['branch_name'])): ?>
                                                                <div class="user-info__branch">
                                                                    <small class="text-muted">
                                                                        <i class="feather icon-map-pin mr-1"></i>
                                                                        <?= htmlspecialchars($log['branch_name']) ?>
                                                                    </small>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="activity-info">
                                                                <div class="activity-info__action">
                                                                    <span class="badge badge-<?= getActionBadgeClass($log['action']) ?>">
                                                                        <?= htmlspecialchars($log['action']) ?>
                                                                    </span>
                                                                </div>
                                                                <div class="activity-info__table">
                                                                    <small class="text-muted">
                                                                        Table: <?= htmlspecialchars($log['table_name']) ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="record-info">
                                                                <div class="record-info__id">
                                                                    <strong>ID: <?= htmlspecialchars($log['record_id'] ?? 'N/A') ?></strong>
                                                                </div>
                                                                <?php if (!empty($log['old_values']) || !empty($log['new_values'])): ?>
                                                                <div class="record-info__changes">
                                                                    <small class="text-muted">
                                                                        <?php
                                                                        $has_old = !empty($log['old_values']);
                                                                        $has_new = !empty($log['new_values']);
                                                                        if ($has_old && $has_new) {
                                                                            echo 'Modified';
                                                                        } elseif ($has_new) {
                                                                            echo 'Created';
                                                                        } elseif ($has_old) {
                                                                            echo 'Deleted';
                                                                        }
                                                                        ?>
                                                                    </small>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="ip-info">
                                                                <div class="ip-info__address">
                                                                    <code><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></code>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td>
                                                            <div class="timestamp-info">
                                                                <div class="timestamp-info__date">
                                                                    <?= date('d/m/Y', strtotime($log['created_at'])) ?>
                                                                </div>
                                                                <div class="timestamp-info__time">
                                                                    <small class="text-muted">
                                                                        <?= date('H:i:s', strtotime($log['created_at'])) ?>
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
                                                    Showing <?= min(($page - 1) * $results_per_page + 1, $total_logs) ?> to <?= min($page * $results_per_page, $total_logs) ?> of <?= $total_logs ?> activity logs
                                                </div>
                                                <nav aria-label="Page navigation">
                                                    <ul class="pagination mb-0">
                                                        <?php if ($page > 1): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=1&search=<?= urlencode($search) ?>&user_id=<?= urlencode($user_filter) ?>&action=<?= urlencode($action_filter) ?>&table_name=<?= urlencode($table_filter) ?>">
                                                                    <i class="feather icon-chevrons-left"></i>
                                                                </a>
                                                            </li>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&user_id=<?= urlencode($user_filter) ?>&action=<?= urlencode($action_filter) ?>&table_name=<?= urlencode($table_filter) ?>">
                                                                    <i class="feather icon-chevron-left"></i>
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>

                                                        <?php
                                                        $start_page = max(1, $page - 2);
                                                        $end_page = min($total_pages, $page + 2);

                                                        if ($start_page > 1) {
                                                            echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&user_id=' . urlencode($user_filter) . '&action=' . urlencode($action_filter) . '&table_name=' . urlencode($table_filter) . '">1</a></li>';
                                                            if ($start_page > 2) {
                                                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                            }
                                                        }

                                                        for ($i = $start_page; $i <= $end_page; $i++) {
                                                            echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                                                <a class="page-link" href="?page=' . $i . '&search=' . urlencode($search) . '&user_id=' . urlencode($user_filter) . '&action=' . urlencode($action_filter) . '&table_name=' . urlencode($table_filter) . '">' . $i . '</a>
                                                            </li>';
                                                        }

                                                        if ($end_page < $total_pages) {
                                                            if ($end_page < $total_pages - 1) {
                                                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                            }
                                                            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&user_id=' . urlencode($user_filter) . '&action=' . urlencode($action_filter) . '&table_name=' . urlencode($table_filter) . '">' . $total_pages . '</a></li>';
                                                        }
                                                        ?>

                                                        <?php if ($page < $total_pages): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&user_id=<?= urlencode($user_filter) ?>&action=<?= urlencode($action_filter) ?>&table_name=<?= urlencode($table_filter) ?>">
                                                                    <i class="feather icon-chevron-right"></i>
                                                                </a>
                                                            </li>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&user_id=<?= urlencode($user_filter) ?>&action=<?= urlencode($action_filter) ?>&table_name=<?= urlencode($table_filter) ?>">
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

<!-- Activity Log Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">
                    <i class="feather icon-file-text mr-2"></i>Activity Log Details
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
                            <div class="activity-summary">
                                <div class="activity-summary__label">Activity Type</div>
                                <div class="activity-summary__action" id="activity-action">-</div>
                                <div class="activity-summary__timestamp" id="activity-timestamp">-</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="user-summary">
                                <div class="user-summary__label">Performed By</div>
                                <div class="user-summary__user" id="activity-user">-</div>
                                <div class="user-summary__ip" id="activity-ip">-</div>
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
                    <li class="nav-item" id="old-values-tab" style="display: none;">
                        <a class="nav-link" id="details-old-tab" data-toggle="tab" href="#details-old" role="tab">
                            <i class="feather icon-minus-circle mr-2"></i>Old Values
                        </a>
                    </li>
                    <li class="nav-item" id="new-values-tab" style="display: none;">
                        <a class="nav-link" id="details-new-tab" data-toggle="tab" href="#details-new" role="tab">
                            <i class="feather icon-plus-circle mr-2"></i>New Values
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="details-technical-tab" data-toggle="tab" href="#details-technical" role="tab">
                            <i class="feather icon-cpu mr-2"></i>Technical Details
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
                                        <h6 class="card-subtitle mb-3 text-muted">Activity Information</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Action</span>
                                            <strong id="summary-action">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Table</span>
                                            <strong id="summary-table">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Record ID</span>
                                            <strong id="summary-record-id">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Branch</span>
                                            <strong id="summary-branch">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted">User & System Info</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">User</span>
                                            <strong id="summary-user">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">IP Address</span>
                                            <strong id="summary-ip">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Timestamp</span>
                                            <strong id="summary-timestamp">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Log ID</span>
                                            <strong id="summary-log-id">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Old Values Tab -->
                    <div class="tab-pane fade" id="details-old" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted">Previous Values</h6>
                                <pre id="old-values-content" class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto; font-size: 0.875rem;">No old values available</pre>
                            </div>
                        </div>
                    </div>

                    <!-- New Values Tab -->
                    <div class="tab-pane fade" id="details-new" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted">New Values</h6>
                                <pre id="new-values-content" class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto; font-size: 0.875rem;">No new values available</pre>
                            </div>
                        </div>
                    </div>

                    <!-- Technical Details Tab -->
                    <div class="tab-pane fade" id="details-technical" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted">Technical Information</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Log ID</span>
                                    <strong id="tech-log-id">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Tenant ID</span>
                                    <strong id="tech-tenant-id">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">User ID</span>
                                    <strong id="tech-user-id">-</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Created At</span>
                                    <strong id="tech-created-at">-</strong>
                                </div>
                                <div class="mb-3">
                                    <span class="text-muted">User Agent</span>
                                    <div class="mt-1">
                                        <code id="tech-user-agent" class="d-block p-2 bg-light rounded" style="font-size: 0.75rem; word-break: break-all;">-</code>
                                    </div>
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

<?php
function getActionBadgeClass($action) {
    $action = strtolower($action);
    switch ($action) {
        case 'create':
        case 'insert':
            return 'success';
        case 'update':
        case 'edit':
            return 'primary';
        case 'delete':
        case 'remove':
            return 'danger';
        case 'login':
            return 'info';
        case 'logout':
            return 'secondary';
        default:
            return 'secondary';
    }
}
?>

<script>
// Handle view details modal
document.querySelectorAll('.view-details').forEach(button => {
    button.addEventListener('click', function() {
        const logData = JSON.parse(this.getAttribute('data-log'));

        // Populate modal with log data
        document.getElementById('activity-action').textContent = logData.action || 'N/A';
        document.getElementById('activity-timestamp').textContent = logData.created_at ? new Date(logData.created_at).toLocaleString() : 'N/A';
        document.getElementById('activity-user').textContent = logData.user_name || 'System';
        document.getElementById('activity-ip').textContent = logData.ip_address || 'N/A';

        document.getElementById('summary-action').textContent = logData.action || 'N/A';
        document.getElementById('summary-table').textContent = logData.table_name || 'N/A';
        document.getElementById('summary-record-id').textContent = logData.record_id || 'N/A';
        document.getElementById('summary-branch').textContent = logData.branch_name || 'N/A';
        document.getElementById('summary-user').textContent = logData.user_name || 'System';
        document.getElementById('summary-ip').textContent = logData.ip_address || 'N/A';
        document.getElementById('summary-timestamp').textContent = logData.created_at ? new Date(logData.created_at).toLocaleString() : 'N/A';
        document.getElementById('summary-log-id').textContent = logData.id;

        // Handle old values
        if (logData.old_values) {
            document.getElementById('old-values-content').textContent = JSON.stringify(JSON.parse(logData.old_values), null, 2);
            document.getElementById('old-values-tab').style.display = 'block';
        } else {
            document.getElementById('old-values-content').textContent = 'No old values available';
            document.getElementById('old-values-tab').style.display = 'none';
        }

        // Handle new values
        if (logData.new_values) {
            document.getElementById('new-values-content').textContent = JSON.stringify(JSON.parse(logData.new_values), null, 2);
            document.getElementById('new-values-tab').style.display = 'block';
        } else {
            document.getElementById('new-values-content').textContent = 'No new values available';
            document.getElementById('new-values-tab').style.display = 'none';
        }

        // Technical details
        document.getElementById('tech-log-id').textContent = logData.id;
        document.getElementById('tech-tenant-id').textContent = logData.tenant_id;
        document.getElementById('tech-user-id').textContent = logData.user_id || 'N/A';
        document.getElementById('tech-created-at').textContent = logData.created_at ? new Date(logData.created_at).toLocaleString() : 'N/A';
        document.getElementById('tech-user-agent').textContent = logData.user_agent || 'N/A';

        // Show modal
        $('#detailsModal').modal('show');
    });
});
</script>