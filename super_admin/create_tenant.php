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
    error_log("Unauthorized access attempt to create_tenant.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

$name = $_POST['name'] ?? '';
$subdomain = $_POST['subdomain'] ?? '';
$identifier = $_POST['identifier'] ?? '';
$plan = $_POST['plan'] ?? '';
$billing_email = $_POST['billing_email'] ?? '';
$agency_name = $_POST['agency_name'] ?? '';
$title = $_POST['title'] ?? '';
$phone = $_POST['phone'] ?? '';
$address = $_POST['address'] ?? '';
$errors = [];

// Validate input
if (empty($name) || empty($subdomain) || empty($identifier) || empty($plan) || empty($billing_email) || empty($agency_name) || empty($title)) {
    $errors[] = "All required fields must be filled.";
}
if (!filter_var($billing_email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid billing email format.";
}
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $subdomain)) {
    $errors[] = "Subdomain can only contain letters, numbers, hyphens, and underscores.";
}
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $identifier)) {
    $errors[] = "Identifier can only contain letters, numbers, hyphens, and underscores.";
}

// Check if subdomain or identifier already exists
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tenants WHERE subdomain = ? OR identifier = ?");
$stmt->execute([$subdomain, $identifier]);
if ($stmt->fetch()['count'] > 0) {
    $errors[] = "Subdomain or identifier already exists.";
}
// Verify plan exists
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM plans WHERE name = ? AND status = 'active'");
$stmt->execute([$plan]);
if ($stmt->fetch()['count'] == 0) {
    $errors[] = "Invalid or inactive plan selected.";
}
if (empty($errors)) {
    // Insert new tenant
    $stmt = $pdo->prepare("INSERT INTO tenants (name, subdomain, identifier, status, plan, billing_email, created_at, updated_at) 
                            VALUES (?, ?, ?, 'active', ?, ?, NOW(), NOW())");
    $stmt->execute([$name, $subdomain, $identifier, $plan, $billing_email]);
    $tenant_id = $pdo->lastInsertId();
    // Insert settings for the new tenant
    $stmt = $pdo->prepare("INSERT INTO settings (tenant_id, agency_name, title, phone, email, address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$tenant_id, $agency_name, $title, $phone, $billing_email, $address]);
    // Send welcome email to tenant
    require_once '../includes/functions.php';
    sendTenantWelcomeEmail($billing_email, $name, $agency_name, $subdomain);

    // Log action
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                            VALUES (?, 'create_tenant', 'tenant', ?, ?, ?, NOW())");
    $details = json_encode(['name' => $name, 'subdomain' => $subdomain, 'identifier' => $identifier, 'plan' => $plan]);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->execute([$user_id, $tenant_id, $details, $ip_address]);
    header('Location: manage_tenants.php?success=tenant_created');
} else {
    header('Location: manage_tenants.php?error=' . urlencode(implode(', ', $errors)));
}
exit();
?>
