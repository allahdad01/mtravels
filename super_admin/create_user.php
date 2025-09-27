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
    header('Location: manage_users.php?error=invalid_csrf');
    exit();
}

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to create_user.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? '';
$tenant_id = $_POST['tenant_id'] ?? '';
$errors = [];

// Validate input
if (empty($name) || empty($email) || empty($password) || empty($role)) {
    $errors[] = "All required fields must be filled.";
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format.";
}
if (strlen($password) < 8) {
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

// Check if email exists
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
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
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, tenant_id, created_at, updated_at) 
                            VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
    $tenant_id = $tenant_id ?: null;
    $stmt->bind_param('ssssi', $name, $email, $hashed_password, $role, $tenant_id);
    $stmt->execute();
    $user_id = $conn->insert_id;
    $stmt->close();

    // Log action
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) 
                            VALUES (?, 'create_user', 'user', ?, ?, ?, NOW())");
    $details = json_encode(['name' => $name, 'email' => $email, 'role' => $role, 'tenant_id' => $tenant_id]);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param('iiss', $_SESSION['user_id'], $user_id, $details, $ip_address);
    $stmt->execute();
    $stmt->close();

    header('Location: manage_users.php?success=user_created');
} else {
    header('Location: manage_users.php?error=' . urlencode(implode(', ', $errors)));
}
exit();
?>