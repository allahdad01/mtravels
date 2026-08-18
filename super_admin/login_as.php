<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is a super admin (impersonation is only allowed for platform super admins)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: manage_tenants.php?error=' . urlencode('Invalid CSRF token. Please try again.'));
    exit();
}

// Database connection
require_once '../includes/db.php';

$user_id = intval($_POST['user_id'] ?? 0);
$tenant_id = (isset($_POST['tenant_id']) && $_POST['tenant_id'] !== '') ? intval($_POST['tenant_id']) : null;

if ($user_id <= 0) {
    header('Location: manage_tenants.php?error=' . urlencode('Invalid user selected.'));
    exit();
}

// Fetch the target user
$stmt = $pdo->prepare("SELECT id, tenant_id, branch_id, name, email, role FROM users WHERE id = ? AND fired = 0 AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$user_id]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target || $target['tenant_id'] === null) {
    header('Location: manage_tenants.php?error=' . urlencode('User not found or is not a tenant user.'));
    exit();
}

if (strtolower($target['role']) === 'super_admin') {
    header('Location: manage_tenants.php?error=' . urlencode('You cannot log in as another super admin.'));
    exit();
}

// Verify the tenant still exists
$stmt = $pdo->prepare("SELECT id, name FROM tenants WHERE id = ? AND status != 'deleted'");
$stmt->execute([$target['tenant_id']]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$tenant) {
    header('Location: manage_tenants.php?error=' . urlencode('Tenant not found.'));
    exit();
}

if ($tenant_id !== null && $tenant_id !== (int)$target['tenant_id']) {
    header('Location: manage_tenants.php?error=' . urlencode('User does not belong to the selected tenant.'));
    exit();
}

// Save the original super admin context so we can switch back later
$_SESSION['impersonating'] = true;
$_SESSION['impersonator_user_id'] = $_SESSION['user_id'];
$_SESSION['impersonator_role'] = $_SESSION['role'];
$_SESSION['impersonator_tenant_id'] = $_SESSION['tenant_id'] ?? null;
$_SESSION['impersonator_branch_id'] = $_SESSION['branch_id'] ?? null;
$_SESSION['impersonator_name'] = $_SESSION['name'] ?? 'Super Admin';
$_SESSION['impersonator_email'] = $_SESSION['email'] ?? '';

// Set the session to the target user (same shape as a normal login)
$_SESSION['loggedin'] = true;
$_SESSION['user_id'] = (int)$target['id'];
$_SESSION['tenant_id'] = (int)$target['tenant_id'];
$_SESSION['branch_id'] = $target['branch_id'];
$_SESSION['name'] = $target['name'];
$_SESSION['email'] = $target['email'];
$_SESSION['role'] = $target['role'];
$_SESSION['user_type'] = 'staff';
$_SESSION['login_time'] = time();
$_SESSION['last_activity'] = time();
$_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
$_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
$_SESSION['last_regeneration'] = time();

// Log the impersonation for audit
try {
    $details = json_encode([
        'impersonated_by' => (int)$_SESSION['impersonator_user_id'],
        'impersonated_by_name' => $_SESSION['impersonator_name'],
        'impersonated_user' => (int)$target['id'],
        'impersonated_user_name' => $target['name'],
        'role' => $target['role'],
        'tenant_id' => (int)$target['tenant_id'],
        'tenant_name' => $tenant['name'],
    ]);
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at, branch_id) VALUES (?, 'impersonate_login', 'user', ?, ?, ?, NOW(), ?)");
    $stmt->execute([(int)$_SESSION['impersonator_user_id'], (int)$target['id'], $details, $_SERVER['REMOTE_ADDR'], $target['branch_id']]);
} catch (PDOException $e) {
    error_log('login_as audit log error: ' . $e->getMessage());
}

// Record login history for the impersonated user
try {
    $stmt = $pdo->prepare("INSERT INTO login_history (tenant_id, user_id, action, action_time, branch_id) VALUES (?, ?, 'login', NOW(), ?)");
    $stmt->execute([(int)$target['tenant_id'], (int)$target['id'], $target['branch_id']]);
} catch (PDOException $e) {
    error_log('login_as login_history error: ' . $e->getMessage());
}

// Regenerate session ID for security
session_regenerate_id(true);

// Redirect to the impersonated user's dashboard
switch (strtolower($target['role'])) {
    case 'tenant_super_admin':
        header('Location: ../tenant_super_admin/dashboard.php');
        break;
    case 'sales_agent':
        header('Location: ../sales_agent/dashboard.php');
        break;
    case 'admin':
    case 'sales':
    case 'finance':
    case 'umrah':
    case 'staff':
        header('Location: ../admin/dashboard.php');
        break;
    default:
        header('Location: ../login.php');
}
exit;