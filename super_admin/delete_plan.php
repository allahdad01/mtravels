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

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: manage_plans.php?error=invalid_csrf');
    exit();
}

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to delete_plan.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

$plan_name = $_POST['plan_name'] ?? '';
$errors = [];

// Validate input
if (empty($plan_name)) {
    $errors[] = "Invalid plan name.";
}

// Check if plan exists
$stmt = $conn->prepare("SELECT status FROM plans WHERE name = ?");
$stmt->bind_param('s', $plan_name);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$plan) {
    $errors[] = "Plan not found.";
}

// Check if plan is in use
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM tenants WHERE plan = ? AND status != 'deleted'");
$stmt->bind_param('s', $plan_name);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()['count'] > 0) {
    $errors[] = "Cannot delete plan; it is in use by active tenants.";
}
$stmt->close();

if (empty($errors)) {
    // Deactivate plan
    $stmt = $conn->prepare("UPDATE plans SET status = 'inactive', updated_at = NOW() WHERE name = ?");
    $stmt->bind_param('s', $plan_name);
    $stmt->execute();
    $stmt->close();

    // Log action
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) 
                            VALUES (?, 'delete_plan', 'plan', ?, ?, ?, NOW())");
    $details = json_encode(['name' => $plan_name]);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param('iss', $user_id, $plan_name, $details, $ip_address);
    $stmt->execute();
    $stmt->close();

    header('Location: manage_plans.php?success=plan_deleted');
} else {
    header('Location: manage_plans.php?error=' . urlencode(implode(', ', $errors)));
}
exit();
?>