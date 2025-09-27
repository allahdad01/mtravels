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
    error_log("Unauthorized access attempt to edit_plan.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch plan details
$plan_name = $_GET['name'] ?? '';
$errors = [];
if ($plan_name) {
    $stmt = $conn->prepare("SELECT name, description, features, price, max_users, trial_days, status FROM plans WHERE name = ?");
    $stmt->bind_param('s', $plan_name);
    $stmt->execute();
    $plan = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$plan) {
        $errors[] = "Plan not found.";
    }
} else {
    $errors[] = "Invalid plan name.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: manage_plans.php?error=invalid_csrf');
        exit();
    }

    $new_name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $features = $_POST['features'] ?? '';
    $price = $_POST['price'] ?? 0;
    $max_users = $_POST['max_users'] ?? 0;
    $trial_days = $_POST['trial_days'] ?? 0;
    $status = $_POST['status'] ?? '';

    // Validate input
    if (empty($new_name) || empty($description) || empty($features) || empty($status)) {
        $errors[] = "Name, description, features, and status are required.";
    }
    
    // Validate price
    if (!is_numeric($price) || $price < 0) {
        $errors[] = "Price must be a non-negative number.";
    }
    
    // Validate max_users
    if (!is_numeric($max_users) || $max_users < 0) {
        $errors[] = "Max users must be a non-negative number.";
    }
    
    // Validate trial_days
    if (!is_numeric($trial_days) || $trial_days < 0) {
        $errors[] = "Trial days must be a non-negative number.";
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $new_name)) {
        $errors[] = "Plan name can only contain letters, numbers, and underscores.";
    }
    if (!json_decode($features, true) || json_last_error() !== JSON_ERROR_NONE) {
        $errors[] = "Features must be a valid JSON array.";
    }
    if (!in_array($status, ['active', 'inactive'])) {
        $errors[] = "Invalid status.";
    }

    // Check for duplicate name (excluding current plan)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM plans WHERE name = ? AND name != ?");
    $stmt->bind_param('ss', $new_name, $plan_name);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()['count'] > 0) {
        $errors[] = "Plan name already exists.";
    }
    $stmt->close();

    // Check if plan is in use (for inactive status)
    if ($status === 'inactive') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tenants WHERE plan = ? AND status != 'deleted'");
        $stmt->bind_param('s', $plan_name);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()['count'] > 0) {
            $errors[] = "Cannot deactivate plan; it is in use by active tenants.";
        }
        $stmt->close();
    }

    if (empty($errors)) {
        // Update plan
        $stmt = $conn->prepare("UPDATE plans SET name = ?, description = ?, features = ?, price = ?, max_users = ?, trial_days = ?, status = ?, updated_at = NOW() WHERE name = ?");
        $stmt->bind_param('sssdiiss', $new_name, $description, $features, $price, $max_users, $trial_days, $status, $plan_name);
        $stmt->execute();
        $stmt->close();

        // Update tenant plans if name changed
        if ($new_name !== $plan_name) {
            $stmt = $conn->prepare("UPDATE tenants SET plan = ? WHERE plan = ?");
            $stmt->bind_param('ss', $new_name, $plan_name);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE tenant_subscriptions SET plan_id = ? WHERE plan_id = ?");
            $stmt->bind_param('ss', $new_name, $plan_name);
            $stmt->execute();
            $stmt->close();
        }

        // Log action
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) 
                                VALUES (?, 'update_plan', 'plan', ?, ?, ?, NOW())");
        $details = json_encode(['old_name' => $plan_name, 'new_name' => $new_name, 'description' => $description, 'price' => $price, 'max_users' => $max_users, 'trial_days' => $trial_days, 'status' => $status]);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param('isss', $user_id, $new_name, $details, $ip_address);
        $stmt->execute();
        $stmt->close();

        header('Location: manage_plans.php?success=plan_updated');
        exit();
    }
}

if (!empty($errors)) {
    header('Location: manage_plans.php?error=' . urlencode(implode(', ', $errors)));
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
                                    <h5 class="m-b-10"><?= __('edit_plan') ?> - <?= htmlspecialchars($plan['name']) ?></h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="manage_plans.php"><?= __('plans') ?></a></li>
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
                                <?php if (isset($_GET['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($_GET['error']) ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <?php endif; ?>
                                
                                <div class="card">
                                    <div class="card-header">
                                        <h5><?= __('plan_details') ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="edit_plan.php?name=<?= urlencode($plan_name) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <div class="form-group">
                                                <label for="name"><?= __('plan_name') ?></label>
                                                <input type="text" class="form-control" id="name" name="name" 
                                                       value="<?= htmlspecialchars($plan['name']) ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="description"><?= __('description') ?></label>
                                                <textarea class="form-control" id="description" name="description" required><?= htmlspecialchars($plan['description']) ?></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label for="features"><?= __('features') ?></label>
                                                <textarea class="form-control" id="features" name="features" required><?= htmlspecialchars($plan['features']) ?></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label for="price"><?= __('price') ?? 'Price' ?></label>
                                                <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="<?= htmlspecialchars($plan['price']) ?>">
                                            </div>
                                            <div class="form-group">
                                                <label for="max_users"><?= __('max_users') ?? 'Max Users' ?></label>
                                                <input type="number" min="0" class="form-control" id="max_users" name="max_users" value="<?= htmlspecialchars($plan['max_users']) ?>">
                                            </div>
                                            <div class="form-group">
                                                <label for="trial_days"><?= __('trial_days') ?? 'Trial Days' ?></label>
                                                <input type="number" min="0" class="form-control" id="trial_days" name="trial_days" value="<?= htmlspecialchars($plan['trial_days']) ?>">
                                            </div>
                                            <div class="form-group">
                                                <label for="status"><?= __('status') ?></label>
                                                <select class="form-control" id="status" name="status" required>
                                                    <option value="active" <?= $plan['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                                    <option value="inactive" <?= $plan['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-primary"><?= __('save_changes') ?></button>
                                            <a href="manage_plans.php" class="btn btn-outline-secondary"><?= __('cancel') ?></a>
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