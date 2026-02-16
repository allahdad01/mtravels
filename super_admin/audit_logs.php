<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to audit_logs.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once '../includes/db.php';

// Pagination and search
$items_per_page = 15;
$current_page = intval($_GET['page'] ?? 1);
$search_query = $_GET['search'] ?? '';

// Fetch super admins for filter
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'super_admin' AND tenant_id IS NULL");
$stmt->execute();
$super_admins = $stmt->fetchAll();

// Build base query with filters
$user_id = $_GET['user_id'] ?? '';
$action = $_GET['action'] ?? '';
$base_where = "WHERE u.role = 'super_admin' AND u.tenant_id IS NULL";
$filter_params = [];

if ($user_id) {
    $base_where .= " AND al.user_id = ?";
    $filter_params[] = $user_id;
}
if ($action) {
    $base_where .= " AND al.action = ?";
    $filter_params[] = $action;
}
if (!empty($search_query)) {
    $base_where .= " AND (al.details LIKE ? OR al.entity_type LIKE ? OR u.name LIKE ?)";
    $search_term = "%{$search_query}%";
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
    $filter_params[] = $search_term;
}

// Count total items
$count_query = "
    SELECT COUNT(*) as total 
    FROM audit_logs al 
    JOIN users u ON al.user_id = u.id 
    {$base_where}
";
$stmt = $pdo->prepare($count_query);
$stmt->execute($filter_params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Fetch paginated audit logs
$query = "
    SELECT al.user_id, al.action, al.entity_type, al.entity_id, al.details, al.ip_address, al.created_at, u.name as user_name 
    FROM audit_logs al 
    JOIN users u ON al.user_id = u.id 
    {$base_where}
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
";
$params = $filter_params;
$params[] = $items_per_page;
$params[] = $offset;
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$audit_logs = $stmt->fetchAll();

// Fetch distinct actions for filter
$stmt = $pdo->prepare("SELECT DISTINCT action FROM audit_logs WHERE user_id IN (SELECT id FROM users WHERE role = 'super_admin' AND tenant_id IS NULL)");
$stmt->execute();
$actions = $stmt->fetchAll();
?>

<?php include '../includes/header_super_admin.php'; ?>

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
                                    <h5 class="m-b-10"><?= __('audit_logs') ?></h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="#!"><?= __('audit_logs') ?></a></li>
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
                            <div class="col-xl-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><?= __('audit_logs') ?> <span class="badge badge-info"><?= $total_items ?> total</span></h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="GET" action="audit_logs.php">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label for="user_id"><?= __('super_admin') ?></label>
                                                        <select class="form-control" id="user_id" name="user_id">
                                                            <option value=""><?= __('all_users') ?></option>
                                                            <?php foreach ($super_admins as $admin): ?>
                                                            <option value="<?= $admin['id'] ?>" <?= $user_id == $admin['id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($admin['name']) ?>
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label for="action"><?= __('action') ?></label>
                                                        <select class="form-control" id="action" name="action">
                                                            <option value=""><?= __('all_actions') ?></option>
                                                            <?php foreach ($actions as $act): ?>
                                                            <option value="<?= htmlspecialchars($act['action']) ?>" <?= $action == $act['action'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($act['action']) ?>
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="search">Search</label>
                                                        <input type="text" class="form-control" id="search" name="search" placeholder="Details, entity type, user..." value="<?= htmlspecialchars($search_query) ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-group">
                                                        <label>&nbsp;</label>
                                                        <button type="submit" class="btn btn-primary btn-block"><?= __('filter') ?></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th><?= __('user') ?></th>
                                                        <th><?= __('action') ?></th>
                                                        <th><?= __('entity_type') ?></th>
                                                        <th><?= __('entity_id') ?></th>
                                                        <th><?= __('details') ?></th>
                                                        <th><?= __('ip_address') ?></th>
                                                        <th><?= __('created_at') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($audit_logs as $log): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($log['user_name']) ?></td>
                                                        <td><?= htmlspecialchars($log['action']) ?></td>
                                                        <td><?= htmlspecialchars($log['entity_type']) ?></td>
                                                        <td><?= htmlspecialchars($log['entity_id']) ?></td>
                                                        <td><?= htmlspecialchars($log['details']) ?></td>
                                                        <td><?= htmlspecialchars($log['ip_address']) ?></td>
                                                        <td><?= date('M d, Y H:i A', strtotime($log['created_at'])) ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($audit_logs)): ?>
                                                    <tr><td colspan="7" class="text-center"><?= __('no_audit_logs_found') ?></td></tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Pagination -->
                                        <?php if ($total_pages > 1): ?>
                                        <nav aria-label="Page navigation" class="mt-3">
                                        <ul class="pagination justify-content-center">
                                        <li class="page-item <?= $current_page === 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($user_id) ? '&user_id=' . $user_id : '' ?><?= !empty($action) ? '&action=' . urlencode($action) : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">Previous</a>
                                        </li>
                                        <?php 
                                        $start_page = max(1, $current_page - 2);
                                        $end_page = min($total_pages, $current_page + 2);
                                        if ($start_page > 1): ?>
                                        <li class="page-item">
                                        <a class="page-link" href="?page=1<?= !empty($user_id) ? '&user_id=' . $user_id : '' ?><?= !empty($action) ? '&action=' . urlencode($action) : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">1</a>
                                        </li>
                                        <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?><?= !empty($user_id) ? '&user_id=' . $user_id : '' ?><?= !empty($action) ? '&action=' . urlencode($action) : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"><?= $i ?></a>
                                        </li>
                                        <?php endfor; ?>
                                        <?php if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                        <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($user_id) ? '&user_id=' . $user_id : '' ?><?= !empty($action) ? '&action=' . urlencode($action) : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"><?= $total_pages ?></a>
                                        </li>
                                        <?php endif; ?>
                                        <li class="page-item <?= $current_page === $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($user_id) ? '&user_id=' . $user_id : '' ?><?= !empty($action) ? '&action=' . urlencode($action) : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>">Next</a>
                                        </li>
                                        </ul>
                                        </nav>
                                        <div class="text-center text-muted small mt-2">
                                        Page <?= $current_page ?> of <?= $total_pages ?> | Showing <?= count($audit_logs) ?> of <?= $total_items ?> logs
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- [ Main Content ] end -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>
