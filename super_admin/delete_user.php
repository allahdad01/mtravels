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
    error_log("Unauthorized access attempt to delete_user.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

$user_id = $_POST['user_id'] ?? '';
$errors = [];

// Validate input
if (empty($user_id) || !is_numeric($user_id)) {
    $errors[] = "Invalid user ID.";
}

// Prevent self-deletion
if ($user_id == $_SESSION['user_id']) {
    $errors[] = "You cannot delete your own account.";
}

// Check if user exists
$stmt = $conn->prepare("SELECT name, email, role, tenant_id, deleted_at FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user || $user['deleted_at']) {
    $errors[] = "User not found or already deleted.";
}

if (empty($errors)) {
    // Soft delete user
    $stmt = $conn->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();

    // Log action
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) 
                            VALUES (?, 'delete_user', 'user', ?, ?, ?, NOW())");
    $details = json_encode(['name' => $user['name'], 'email' => $user['email'], 'role' => $user['role'], 'tenant_id' => $user['tenant_id']]);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param('iiss', $_SESSION['user_id'], $user_id, $details, $ip_address);
    $stmt->execute();
    $stmt->close();

    header('Location: manage_users.php?success=user_deleted');
} else {
    header('Location: manage_users.php?error=' . urlencode(implode(', ', $errors)));
}
exit();
?>