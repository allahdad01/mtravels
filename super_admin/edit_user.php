<?php
session_start();
require_once '../includes/conn.php';

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
    error_log("Unauthorized access attempt to edit_user.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch user details
$user_id = $_GET['id'] ?? '';
$errors = [];
if ($user_id && is_numeric($user_id)) {
    $stmt = $conn->prepare("SELECT id, name, email, role, tenant_id FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
   
} else {
    $errors[] = "Invalid user ID.";
}

// Fetch tenants for dropdown
$stmt = $conn->prepare("SELECT id, name FROM tenants WHERE status != 'deleted'");
$stmt->execute();
$tenants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: manage_users.php?error=invalid_csrf');
        exit();
    }

    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $tenant_id = $_POST['tenant_id'] ?? '';

    // Validate input
    if (empty($name) || empty($email) || empty($role)) {
        $errors[] = "Name, email, and role are required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if ($password && strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if (!in_array($role, ['super_admin', 'tenant_admin', 'user'])) {
        $errors[] = "Invalid role.";
    }
    if ($role !== 'super_admin' && empty($tenant_id)) {
        $errors[] = "Tenant is required for non-super admin roles.";
    }
    if ($role === 'super_admin' && !empty($tenant_id)) {
        $errors[] = "Super admins cannot be assigned to a tenant.";
    }

    // Check for duplicate email (excluding current user)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param('si', $email, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()['count'] > 0) {
        $errors[] = "Email already exists.";
    }
    $stmt->close();

    // Verify tenant exists (if applicable)
    if ($tenant_id) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tenants WHERE id = ? AND status != 'deleted'");
        $stmt->bind_param('i', $tenant_id);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()['count'] == 0) {
            $errors[] = "Invalid or deleted tenant.";
        }
        $stmt->close();
    }

    if (empty($errors)) {
        // Update user
        $tenant_id = $tenant_id ?: null;
        if ($password) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ?, tenant_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('ssssi', $name, $email, $hashed_password, $role, $tenant_id, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, tenant_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('sssi', $name, $email, $role, $tenant_id, $user_id);
        }
        $stmt->execute();
        $stmt->close();

        // Log action
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) 
                                VALUES (?, 'update_user', 'user', ?, ?, ?, NOW())");
        $details = json_encode(['name' => $name, 'email' => $email, 'role' => $role, 'tenant_id' => $tenant_id, 'password_updated' => !!$password]);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param('iiss', $_SESSION['user_id'], $user_id, $details, $ip_address);
        $stmt->execute();
        $stmt->close();

        header('Location: manage_users.php?success=user_updated');
        exit();
    }
}

if (!empty($errors)) {
    header('Location: manage_users.php?error=' . urlencode(implode(', ', $errors)));
    exit();
}
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
                                    <h5 class="m-b-10"><?= __('edit_user') ?> - <?= htmlspecialchars($user['name']) ?></h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="manage_users.php"><?= __('users') ?></a></li>
                                    <li class="breadcrumb-item"><a href="#!"><?= __('edit') ?></a></li>
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
                                        <h5><?= __('user_details') ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="edit_user.php?id=<?= $user_id ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <div class="form-group">
                                                <label for="name"><?= __('name') ?></label>
                                                <input type="text" class="form-control" id="name" name="name" 
                                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="email"><?= __('email') ?></label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="password"><?= __('password') ?></label>
                                                <input type="password" class="form-control" id="password" name="password" 
                                                       placeholder="<?= __('leave_blank_to_keep_unchanged') ?>">
                                            </div>
                                            <div class="form-group">
                                                <label for="role"><?= __('role') ?></label>
                                                <select class="form-control" id="role" name="role" required>
                                                    <option value="super_admin" <?= $user['role'] == 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                                    <option value="tenant_admin" <?= $user['role'] == 'tenant_admin' ? 'selected' : '' ?>>Tenant Admin</option>
                                                    <option value="user" <?= $user['role'] == 'user' ? 'selected' : '' ?>>User</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="tenant_id"><?= __('tenant') ?></label>
                                                <select class="form-control" id="tenant_id" name="tenant_id">
                                                    <option value=""><?= __('none') ?></option>
                                                    <?php foreach ($tenants as $tenant): ?>
                                                    <option value="<?= $tenant['id'] ?>" <?= $user['tenant_id'] == $tenant['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($tenant['name']) ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-primary"><?= __('save_changes') ?></button>
                                            <a href="manage_users.php" class="btn btn-outline-secondary"><?= __('cancel') ?></a>
                                        </form>
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

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>