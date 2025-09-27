<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
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
require_once '../includes/conn.php';

// Fetch super admins for filter
$stmt = $conn->prepare("SELECT id, name FROM users WHERE role = 'super_admin' AND tenant_id IS NULL");
$stmt->execute();
$super_admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch audit logs with optional filters
$user_id = $_GET['user_id'] ?? '';
$action = $_GET['action'] ?? '';
$query = "SELECT al.user_id, al.action, al.entity_type, al.entity_id, al.details, al.ip_address, al.created_at, u.name as user_name 
          FROM audit_logs al 
          JOIN users u ON al.user_id = u.id 
          WHERE u.role = 'super_admin' AND u.tenant_id IS NULL";
$params = [];
$types = '';
if ($user_id) {
    $query .= " AND al.user_id = ?";
    $params[] = $user_id;
    $types .= 'i';
}
if ($action) {
    $query .= " AND al.action = ?";
    $params[] = $action;
    $types .= 's';
}
$query .= " ORDER BY al.created_at DESC";
$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$audit_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch distinct actions for filter
$stmt = $conn->prepare("SELECT DISTINCT action FROM audit_logs WHERE user_id IN (SELECT id FROM users WHERE role = 'super_admin' AND tenant_id IS NULL)");
$stmt->execute();
$actions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
                                        <h5><?= __('audit_logs') ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="GET" action="audit_logs.php">
                                            <div class="row">
                                                <div class="col-md-4">
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
                                                <div class="col-md-4">
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