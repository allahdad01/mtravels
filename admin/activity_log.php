<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
require_once '../includes/conn.php';
require_once '../includes/db.php';

// Initialize messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear session messages after retrieving them
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Build redirect URL with current query parameters
$redirect_url = $_SERVER['PHP_SELF'];
if (!empty($_GET)) {
    $redirect_url .= '?' . http_build_query($_GET);
}

// Pagination settings
$records_per_page = 50;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $records_per_page;

// Handle activity log filtering
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$table_name = isset($_GET['table_name']) ? $_GET['table_name'] : '';

// Get all users for filter dropdown
$users_query = "SELECT id, name FROM users WHERE tenant_id = ? ORDER BY name";
$users_result = $conn->prepare($users_query);
$users_result->bind_param("i", $tenant_id);
$users_result->execute();
$users_result_set = $users_result->get_result();
$users = [];
while ($row = $users_result_set->fetch_assoc()) {
    $users[] = $row;
}

// Build base query for counting total records
$count_query = "SELECT COUNT(*) as total 
                FROM activity_log a 
                WHERE a.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND a.tenant_id = ?";
$count_params = [$date_from, $date_to, $tenant_id];
$count_types = "sss";

if ($user_id > 0) {
    $count_query .= " AND a.user_id = ?";
    $count_params[] = $user_id;
    $count_types .= "i";
}

if (!empty($action)) {
    $count_query .= " AND a.action = ?";
    $count_params[] = $action;
    $count_types .= "s";
}

if (!empty($table_name)) {
    $count_query .= " AND a.table_name = ?";
    $count_params[] = $table_name;
    $count_types .= "s";
}

// Get total records count
$count_stmt = $conn->prepare($count_query);
if ($count_params) {
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_records = $count_row['total'];
$total_pages = ceil($total_records / $records_per_page);

// Build query for activity logs with pagination
$query = "SELECT a.*, u.name as user_name
          FROM activity_log a 
          LEFT JOIN users u ON a.user_id = u.id 
          WHERE a.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY) AND a.tenant_id = ?";
$params = [$date_from, $date_to, $tenant_id];
$types = "sss";

if ($user_id > 0) {
    $query .= " AND a.user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

if (!empty($action)) {
    $query .= " AND a.action = ?";
    $params[] = $action;
    $types .= "s";
}

if (!empty($table_name)) {
    $query .= " AND a.table_name = ?";
    $params[] = $table_name;
    $types .= "s";
}

$query .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

// Get actions for filter dropdown
$actions_query = "SELECT DISTINCT action FROM activity_log WHERE tenant_id = ? ORDER BY action";
$actions_result = $conn->prepare($actions_query);
$actions_result->bind_param("i", $tenant_id);
$actions_result->execute();
$actions_result_set = $actions_result->get_result();
$actions = [];
while ($row = $actions_result_set->fetch_assoc()) {
    $actions[] = $row['action'];
}

// Get table names for filter dropdown
$tables_query = "SELECT DISTINCT table_name FROM activity_log WHERE tenant_id = ? ORDER BY table_name";
$tables_result = $conn->prepare($tables_query);
$tables_result->bind_param("i", $tenant_id);
$tables_result->execute();
$tables_result_set = $tables_result->get_result();
$tables = [];
while ($row = $tables_result_set->fetch_assoc()) {
    $tables[] = $row['table_name'];
}

// Handle log deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_log'])) {
    $log_id = $_POST['log_id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM activity_log WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $log_id, $tenant_id);
        $stmt->execute();
        $_SESSION['success_message'] = "Log entry deleted successfully!";
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error deleting log entry: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle bulk log deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    $delete_before_date = $_POST['delete_before_date'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM activity_log WHERE created_at < ? AND tenant_id = ?");
        $stmt->bind_param("si", $delete_before_date, $tenant_id);
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $_SESSION['success_message'] = "$affected_rows log entries deleted successfully!";
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error deleting log entries: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Fetch user data with proper error handling
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$_SESSION['user_id'], $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Log the error
        error_log("User not found: " . $_SESSION['user_id']);
        
        // For debugging
        echo "<!-- Debug: User ID = " . $_SESSION['user_id'] . " -->";
        echo "<!-- Debug: SQL = SELECT * FROM users WHERE id = " . $_SESSION['user_id'] . " -->";
        
        // Redirect to login if user not found
        session_destroy();
        header('Location: ../login.php');
        exit();
    }

} catch (PDOException $e) {
    // Log the error
    error_log("Database Error: " . $e->getMessage());
    
    // For debugging
    echo "<!-- Debug: Database Error = " . $e->getMessage() . " -->";
    
    $user = null;
}



?>
    <!-- Custom CSS for modals -->
    <style>
        /* Fix for modal content overflow */
        .modal-body pre {
            white-space: pre-wrap;       /* CSS3 */
            word-wrap: break-word;       /* Internet Explorer 5.5+ */
            max-height: 300px;
            overflow-y: auto;
        }
        
        .json-content {
            max-width: 100%;
            overflow-x: auto;
        }
        
        /* Enhanced fix for modal content */
        .modal-body {
            word-break: break-word;
            overflow-wrap: break-word;
        }
        
        .modal-body p {
            word-break: break-word;
            max-width: 100%;
        }
        
        /* Fix for long user agent strings */
        .user-agent-wrapper {
            width: 100%;
            overflow-x: visible;
        }
        
        .user-agent-info {
            word-break: break-all;
            word-wrap: break-word;
            overflow-wrap: break-word;
            max-width: 100%;
            display: inline-block;
            font-size: 0.9rem;
            font-family: monospace;
            white-space: normal;
        }
    </style>
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

<?php include '../includes/header.php'; ?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="container mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h2><?= __('activity_log') ?></h2>
                                <?php if (!empty($logs)): ?>
                                <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#bulkDeleteModal">
                                    <i class="fas fa-trash"></i> <?= __('bulk_delete') ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (isset($success_message)): ?>
                                <div class="alert alert-success"><?php echo h($success_message); ?></div>
                            <?php endif; ?>
                            
                            <?php if (isset($error_message)): ?>
                                <div class="alert alert-danger"><?php echo h($error_message); ?></div>
                            <?php endif; ?>
                            
                            <!-- Filters Section -->
                            <div class="card mb-4 shadow-sm">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="feather icon-filter mr-2"></i><?= __('filter_logs') ?></h5>
                                </div>
                                <div class="card-body">
                                    <form method="GET" action="activity_log.php">
                                        <div class="row">
                                            <div class="col-md-3 mb-3">
                                                <label for="date_from"><?= __('date_from') ?></label>
                                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo h($date_from); ?>">
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label for="date_to"><?= __('date_to') ?></label>
                                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo h($date_to); ?>">
                                            </div>
                                            <div class="col-md-2 mb-3">
                                                <label for="user_id"><?= __('user') ?></label>
                                                <select class="form-control" id="user_id" name="user_id">
                                                    <option value="0"><?= __('all_users') ?></option>
                                                    <?php foreach ($users as $u): ?>
                                                        <option value="<?php echo h($u['id']); ?>" <?php echo $user_id == $u['id'] ? 'selected' : ''; ?>>
                                                            <?php echo h($u['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2 mb-3">
                                                <label for="action"><?= __('action') ?></label>
                                                <select class="form-control" id="action" name="action">
                                                    <option value=""><?= __('all_actions') ?></option>
                                                    <?php foreach ($actions as $act): ?>
                                                        <option value="<?php echo h($act); ?>" <?php echo $action == $act ? 'selected' : ''; ?>>
                                                            <?php echo h(ucfirst($act)); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2 mb-3">
                                                <label for="table_name"><?= __('table') ?></label>
                                                <select class="form-control" id="table_name" name="table_name">
                                                    <option value=""><?= __('all_tables') ?></option>
                                                    <?php foreach ($tables as $tbl): ?>
                                                        <option value="<?php echo h($tbl); ?>" <?php echo $table_name == $tbl ? 'selected' : ''; ?>>
                                                            <?php echo h($tbl); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="feather icon-filter mr-1"></i><?= __('apply_filters') ?>
                                            </button>
                                            <a href="activity_log.php" class="btn btn-secondary ml-2">
                                                <i class="feather icon-refresh-cw mr-1"></i><?= __('reset') ?>
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Bulk Delete Modal -->
                            <div class="modal fade" id="bulkDeleteModal" tabindex="-1" role="dialog" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="bulkDeleteModalLabel"><?= __('bulk_delete_log_entries') ?></h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <form method="POST">
                                            <!-- CSRF Protection -->
                                            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                            
                                            <div class="modal-body">
                                                <div class="alert alert-warning">
                                                    <i class="feather icon-alert-triangle mr-1"></i>
                                                    <?= __('warning_this_action_will_permanently_delete_all_log_entries_before_the_selected_date') ?>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label"><?= __('delete_logs_before_date') ?> *</label>
                                                    <input type="date" class="form-control" name="delete_before_date" required>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                                                <button type="submit" name="bulk_delete" class="btn btn-danger"><?= __('delete_logs') ?></button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card shadow-sm border-0">
                                <div class="card-header bg-transparent py-3">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <h5 class="mb-0 text-primary">
                                                <i class="feather icon-activity mr-2"></i><?= __('activity_logs') ?>
                                                <span class="badge badge-pill badge-primary ml-2"><?php echo $total_records; ?> <?= __('entries') ?></span>
                                                <span class="badge badge-pill badge-secondary ml-2"><?= __('page') ?> <?php echo $page; ?> <?= __('of') ?> <?php echo $total_pages; ?></span>
                                            </h5>
                                        </div>
                                        <div class="col-auto">
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="logSearch" placeholder="<?= __('search_logs') ?>...">
                                                <div class="input-group-append">
                                                    <span class="input-group-text bg-primary border-primary text-white">
                                                        <i class="feather icon-search"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped mb-0" id="logsTable">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th class="border-top-0">
                                                        <div class="d-flex align-items-center">
                                                            <i class="feather icon-clock mr-2 text-muted"></i><?= __('date_time') ?>
                                                        </div>
                                                    </th>
                                                    <th class="border-top-0">
                                                        <div class="d-flex align-items-center">
                                                            <i class="feather icon-user mr-2 text-muted"></i><?= __('user') ?>
                                                        </div>
                                                    </th>
                                                    <th class="border-top-0">
                                                        <div class="d-flex align-items-center">
                                                            <i class="feather icon-tag mr-2 text-muted"></i><?= __('action') ?>
                                                        </div>
                                                    </th>
                                                    <th class="border-top-0">
                                                        <div class="d-flex align-items-center">
                                                            <i class="feather icon-database mr-2 text-muted"></i><?= __('table') ?>
                                                        </div>
                                                    </th>
                                                    <th class="border-top-0">
                                                        <div class="d-flex align-items-center">
                                                            <i class="feather icon-hash mr-2 text-muted"></i><?= __('record_id') ?>
                                                        </div>
                                                    </th>
                                                    <th class="border-top-0">
                                                        <div class="d-flex align-items-center">
                                                                <i class="feather icon-monitor mr-2 text-muted"></i><?= __('ip_address') ?>
                                                        </div>
                                                    </th>
                                                    <th class="border-top-0 text-center"><?= __('actions') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (count($logs) > 0): ?>
                                                    <?php foreach ($logs as $log): ?>
                                                        <tr class="log-row">
                                                            <td><?php echo h(date('Y-m-d H:i:s', strtotime($log['created_at']))); ?></td>
                                                            <td>
                                                                <span class="d-inline-block text-truncate" style="max-width: 150px;">
                                                                    <?php echo h($log['user_name']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php 
                                                                $badge_class = 'badge-secondary';
                                                                if ($log['action'] == 'login') $badge_class = 'badge-success';
                                                                if ($log['action'] == 'logout') $badge_class = 'badge-warning';
                                                                if ($log['action'] == 'create') $badge_class = 'badge-primary';
                                                                if ($log['action'] == 'update') $badge_class = 'badge-info';
                                                                if ($log['action'] == 'delete') $badge_class = 'badge-danger';
                                                                if ($log['action'] == 'insert') $badge_class = 'badge-primary';
                                                                ?>
                                                                <span class="badge <?php echo $badge_class; ?>">
                                                                    <?php echo h(ucfirst($log['action'])); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo h($log['table_name']); ?></td>
                                                            <td><?php echo h($log['record_id']); ?></td>
                                                            <td><?php echo h($log['ip_address']); ?></td>
                                                            <td class="text-center">
                                                                <div class="btn-group">
                                                                    <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#viewLogModal<?php echo h($log['id']); ?>">
                                                                        <i class="feather icon-eye"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-sm btn-danger" data-toggle="modal" data-target="#deleteLogModal<?php echo h($log['id']); ?>">
                                                                        <i class="feather icon-trash-2"></i>
                                                                    </button>
                                                                </div>
                                                                
                                                                <!-- View Log Modal -->
                                                                <div class="modal fade" id="viewLogModal<?php echo h($log['id']); ?>" tabindex="-1" role="dialog" aria-labelledby="viewLogModalLabel<?php echo h($log['id']); ?>" aria-hidden="true">
                                                                    <div class="modal-dialog modal-lg" role="document">
                                                                        <div class="modal-content">
                                                                            <div class="modal-header">
                                                                                <h5 class="modal-title" id="viewLogModalLabel<?php echo h($log['id']); ?>"><?= __('log_details') ?></h5>
                                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                                    <span aria-hidden="true">&times;</span>
                                                                                </button>
                                                                            </div>
                                                                            <div class="modal-body">
                                                                                <div class="row">
                                                                                    <div class="col-md-6">
                                                                                        <p><strong><?= __('date_time') ?>:</strong> <?php echo h(date('Y-m-d H:i:s', strtotime($log['created_at']))); ?></p>
                                                                                        <p><strong><?= __('user') ?>:</strong> <?php echo h($log['user_name']); ?></p>
                                                                                        <p><strong><?= __('action') ?>:</strong> <?php echo h(ucfirst($log['action'])); ?></p>
                                                                                        <p><strong><?= __('table') ?>:</strong> <?php echo h($log['table_name']); ?></p>
                                                                                        <p><strong><?= __('record_id') ?>:</strong> <?php echo h($log['record_id']); ?></p>
                                                                                    </div>
                                                                                    <div class="col-md-6">
                                                                                        <p><strong><?= __('ip_address') ?>:</strong> <?php echo h($log['ip_address']); ?></p>
                                                                                        <p class="user-agent-wrapper"><strong><?= __('user_agent') ?>:</strong> <span class="user-agent-info">
                                                                                            <?php echo h($log['user_agent']); ?></span></p>
                                                                                    </div>
                                                                                </div>
                                                                                <hr>
                                                                                <div class="row">
                                                                                    <div class="col-md-6">
                                                                                        <h6><?= __('old_values') ?>:</h6>
                                                                                        <div class="p-3 bg-light rounded json-content">
                                                                                            <pre class="mb-0"><?php 
    // Check if the value is not null before decoding
    if ($log['old_values'] !== null && $log['old_values'] !== '') {
        // Decode the JSON with UTF-8 handling
        $old_values = json_decode($log['old_values'], true);
        if ($old_values !== null) {
            echo h(json_encode($old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            // Fallback if JSON is invalid
            echo h($log['old_values']);
        }
    } else {
            echo '<em class="text-muted">'.__('no_data').'</em>';
    }
?></pre>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="col-md-6">
                                                                                        <h6><?= __('new_values') ?>:</h6>
                                                                                        <div class="p-3 bg-light rounded json-content">
                                                                                            <pre class="mb-0"><?php 
    // Check if the value is not null before decoding
    if ($log['new_values'] !== null && $log['new_values'] !== '') {
        // Decode the JSON with UTF-8 handling
        $new_values = json_decode($log['new_values'], true);
        if ($new_values !== null) {
            echo h(json_encode($new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            // Fallback if JSON is invalid
            echo h($log['new_values']);
        }
    } else {
                echo '<em class="text-muted">'.__('no_data').'</em>';
    }
?></pre>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="modal-footer">
                                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                
                                                                <!-- Delete Log Modal -->
                                                                <div class="modal fade" id="deleteLogModal<?php echo h($log['id']); ?>" tabindex="-1" role="dialog" aria-labelledby="deleteLogModalLabel<?php echo h($log['id']); ?>" aria-hidden="true">
                                                                    <div class="modal-dialog" role="document">
                                                                        <div class="modal-content">
                                                                            <div class="modal-header">
                                                                                <h5 class="modal-title" id="deleteLogModalLabel<?php echo h($log['id']); ?>"><?= __('confirm_deletion') ?></h5>
                                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                                    <span aria-hidden="true">&times;</span>
                                                                                </button>
                                                                            </div>
                                                                            <form method="POST">
                                                                                <!-- CSRF Protection -->
                                                                                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                                                                <input type="hidden" name="log_id" value="<?php echo h($log['id']); ?>">
                                                                                <div class="modal-body">
                                                                                    <p><?= __('are_you_sure_you_want_to_delete_this_log_entry') ?></p>
                                                                                    <div class="alert alert-warning">
                                                                                        <i class="feather icon-alert-triangle mr-1"></i>
                                                                                        <?= __('this_action_cannot_be_undone') ?>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="modal-footer">
                                                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                                                                                    <button type="submit" name="delete_log" class="btn btn-danger"><?= __('delete') ?></button>
                                                                                </div>
                                                                            </form>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="7" class="text-center py-4">
                                                            <div class="d-flex flex-column align-items-center">
                                                                <i class="feather icon-alert-circle text-muted" style="font-size: 3rem;"></i>
                                                                <h5 class="mt-3"><?= __('no_log_entries_found') ?></h5>
                                                                <p class="text-muted"><?= __('try_adjusting_your_filters_or_check_back_later') ?></p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                <div class="card-footer bg-white">
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center mb-0">
                                            <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="<?php echo $_SERVER['PHP_SELF']; ?>?page=1<?php 
                                                    $params = $_GET;
                                                    unset($params['page']);
                                                    echo !empty($params) ? '&' . http_build_query($params) : ''; 
                                                ?>" aria-label="<?= __('first') ?>">
                                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo $page - 1; ?><?php 
                                                    $params = $_GET;
                                                    unset($params['page']);
                                                    echo !empty($params) ? '&' . http_build_query($params) : ''; 
                                                ?>" aria-label="<?= __('previous') ?>">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                            <?php endif; ?>

                                            <?php
                                            $start_page = max(1, $page - 2);
                                            $end_page = min($total_pages, $page + 2);
                                            
                                            for ($i = $start_page; $i <= $end_page; $i++): 
                                            ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo $i; ?><?php 
                                                    $params = $_GET;
                                                    unset($params['page']);
                                                    echo !empty($params) ? '&' . http_build_query($params) : ''; 
                                                ?>"><?php echo $i; ?></a>
                                            </li>
                                            <?php endfor; ?>

                                            <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo $page + 1; ?><?php 
                                                    $params = $_GET;
                                                    unset($params['page']);
                                                    echo !empty($params) ? '&' . http_build_query($params) : ''; 
                                                ?>" aria-label="<?= __('next') ?>">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo $total_pages; ?><?php 
                                                    $params = $_GET;
                                                    unset($params['page']);
                                                    echo !empty($params) ? '&' . http_build_query($params) : ''; 
                                                    ?>" aria-label="<?= __('last') ?>">
                                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- [ Main Content ] end -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- [ Main Content ] end -->

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

                                    <!-- Modal Fix for Debtors Page -->
                                    <script src="debtors-modal-fix.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('logSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.log-row');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
    
    // Initialize tooltips
    if (typeof $().tooltip === 'function') {
        $('[data-toggle="tooltip"]').tooltip();
    }
});
</script>

</body>
</html> 