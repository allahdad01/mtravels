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
    error_log("Unauthorized access attempt to edit_tenant.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch tenant details
$tenant_id = $_GET['id'] ?? '';
$errors = [];
if ($tenant_id && is_numeric($tenant_id)) {
    $stmt = $conn->prepare("SELECT id, name, subdomain, identifier, status, plan, billing_email FROM tenants WHERE id = ? AND status != 'deleted'");
    $stmt->bind_param('i', $tenant_id);
    $stmt->execute();
    $tenant = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$tenant) {
        $errors[] = "Tenant not found or deleted.";
    }
} else {
    $errors[] = "Invalid tenant ID.";
}

// Fetch plans for dropdown
$stmt = $conn->prepare("SELECT name FROM plans WHERE status = 'active'");
$stmt->execute();
$plans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: manage_tenants.php?error=invalid_csrf');
        exit();
    }

    $name = $_POST['name'] ?? '';
    $subdomain = $_POST['subdomain'] ?? '';
    $identifier = $_POST['identifier'] ?? '';
    $plan = $_POST['plan'] ?? '';
    $billing_email = $_POST['billing_email'] ?? '';
    $status = $_POST['status'] ?? '';

    // Validate input
    if (empty($name) || empty($subdomain) || empty($identifier) || empty($plan) || empty($billing_email) || empty($status)) {
        $errors[] = "All fields are required.";
    }
    if (!filter_var($billing_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid billing email format.";
    }
    if (!preg_match('/^[a-zA-Z0-9-]+$/', $subdomain)) {
        $errors[] = "Subdomain can only contain letters, numbers, and hyphens.";
    }
    if (!preg_match('/^[a-zA-Z0-9-]+$/', $identifier)) {
        $errors[] = "Identifier can only contain letters, numbers, and hyphens.";
    }
    if (!in_array($status, ['active', 'suspended'])) {
        $errors[] = "Invalid status.";
    }

    // Check for duplicate subdomain or identifier (excluding current tenant)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tenants WHERE (subdomain = ? OR identifier = ?) AND id != ? AND status != 'deleted'");
    $stmt->bind_param('ssi', $subdomain, $identifier, $tenant_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()['count'] > 0) {
        $errors[] = "Subdomain or identifier already exists.";
    }
    $stmt->close();

    // Verify plan exists
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM plans WHERE name = ? AND status = 'active'");
    $stmt->bind_param('s', $plan);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()['count'] == 0) {
        $errors[] = "Invalid or inactive plan selected.";
    }
    $stmt->close();

    if (empty($errors)) {
        // Update tenant
        $stmt = $conn->prepare("UPDATE tenants SET name = ?, subdomain = ?, identifier = ?, status = ?, plan = ?, billing_email = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('ssssssi', $name, $subdomain, $identifier, $status, $plan, $billing_email, $tenant_id);
        $stmt->execute();
        $stmt->close();

        // Log action
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) 
                                VALUES (?, 'update_tenant', 'tenant', ?, ?, ?, NOW())");
        $details = json_encode(['name' => $name, 'subdomain' => $subdomain, 'identifier' => $identifier, 'plan' => $plan, 'status' => $status]);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param('iiss', $user_id, $tenant_id, $details, $ip_address);
        $stmt->execute();
        $stmt->close();

        header('Location: manage_tenants.php?success=tenant_updated');
        exit();
    }
}

if (!empty($errors)) {
    header('Location: manage_tenants.php?error=' . urlencode(implode(', ', $errors)));
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
                                    <h5 class="m-b-10"><?= __('edit_tenant') ?> - <?= htmlspecialchars($tenant['name']) ?></h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="manage_tenants.php"><?= __('tenants') ?></a></li>
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
                                        <h5><?= __('tenant_details') ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="edit_tenant.php?id=<?= $tenant_id ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <div class="form-group">
                                                <label for="name"><?= __('tenant_name') ?></label>
                                                <input type="text" class="form-control" id="name" name="name" 
                                                       value="<?= htmlspecialchars($tenant['name']) ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="subdomain"><?= __('subdomain') ?></label>
                                                <input type="text" class="form-control" id="subdomain" name="subdomain" 
                                                       value="<?= htmlspecialchars($tenant['subdomain']) ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="identifier"><?= __('identifier') ?></label>
                                                <input type="text" class="form-control" id="identifier" name="identifier" 
                                                       value="<?= htmlspecialchars($tenant['identifier']) ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="plan"><?= __('plan') ?></label>
                                                <select class="form-control" id="plan" name="plan" required>
                                                    <?php foreach ($plans as $plan): ?>
                                                    <option value="<?= htmlspecialchars($plan['name']) ?>" 
                                                            <?= $tenant['plan'] == $plan['name'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($plan['name']) ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="billing_email"><?= __('billing_email') ?></label>
                                                <input type="email" class="form-control" id="billing_email" name="billing_email" 
                                                       value="<?= htmlspecialchars($tenant['billing_email']) ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="status"><?= __('status') ?></label>
                                                <select class="form-control" id="status" name="status" required>
                                                    <option value="active" <?= $tenant['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                                    <option value="suspended" <?= $tenant['status'] == 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-primary"><?= __('save_changes') ?></button>
                                            <a href="manage_tenants.php" class="btn btn-outline-secondary"><?= __('cancel') ?></a>
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