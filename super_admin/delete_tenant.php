<?php
session_start();
require_once '../includes/db.php';

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

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: manage_tenants.php?error=invalid_csrf');
    exit();
}

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to delete_tenant.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

$tenant_id = $_POST['tenant_id'] ?? '';
$errors = [];

// Validate input
if (empty($tenant_id) || !is_numeric($tenant_id)) {
    $errors[] = "Invalid tenant ID.";
}

// Check if tenant exists
$stmt = $pdo->prepare("SELECT name, status FROM tenants WHERE id = ? AND status != 'deleted'");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch();
if (!$tenant) {
    $errors[] = "Tenant not found or already deleted.";
}

if (empty($errors)) {
    // Soft delete tenant
    $stmt = $pdo->prepare("UPDATE tenants SET status = 'deleted', deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$tenant_id]);
    // Log action
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) 
                            VALUES (?, 'delete_tenant', 'tenant', ?, ?, ?, NOW())");
    $details = json_encode(['name' => $tenant['name']]);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->execute([$user_id, $tenant_id, $details, $ip_address]);
    header('Location: manage_tenants.php?success=tenant_deleted');
} else {
    header('Location: manage_tenants.php?error=' . urlencode(implode(', ', $errors)));
}
exit();
?>
