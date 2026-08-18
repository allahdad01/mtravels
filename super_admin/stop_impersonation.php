<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only valid while impersonating
if (empty($_SESSION['impersonating']) || empty($_SESSION['impersonator_user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/db.php';

$impersonated_user_id = (int)($_SESSION['user_id'] ?? 0);
$impersonated_user_name = $_SESSION['name'] ?? '';

// Restore the original super admin session
$_SESSION['loggedin'] = true;
$_SESSION['user_id'] = (int)$_SESSION['impersonator_user_id'];
$_SESSION['tenant_id'] = $_SESSION['impersonator_tenant_id'];
$_SESSION['branch_id'] = $_SESSION['impersonator_branch_id'];
$_SESSION['name'] = $_SESSION['impersonator_name'];
$_SESSION['email'] = $_SESSION['impersonator_email'];
$_SESSION['role'] = $_SESSION['impersonator_role'];
$_SESSION['user_type'] = 'staff';
$_SESSION['login_time'] = time();
$_SESSION['last_activity'] = time();

// Log the impersonation exit for audit
try {
    $details = json_encode([
        'impersonated_user' => $impersonated_user_id,
        'impersonated_user_name' => $impersonated_user_name,
    ]);
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES (?, 'impersonate_exit', 'user', ?, ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $impersonated_user_id ?: 0, $details, $_SERVER['REMOTE_ADDR']]);
} catch (PDOException $e) {
    error_log('stop_impersonation audit log error: ' . $e->getMessage());
}

// Clear impersonation keys
unset($_SESSION['impersonating']);
unset($_SESSION['impersonator_user_id']);
unset($_SESSION['impersonator_role']);
unset($_SESSION['impersonator_tenant_id']);
unset($_SESSION['impersonator_branch_id']);
unset($_SESSION['impersonator_name']);
unset($_SESSION['impersonator_email']);

// Regenerate session ID for security
session_regenerate_id(true);

// Back to the super admin dashboard
header('Location: dashboard.php');
exit;