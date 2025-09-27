<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();



// Database connection for logging (optional)
require_once('../includes/db.php');

try {
    // Log the logout time if you want to track user sessions
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("INSERT INTO login_history (tenant_id, user_id, action, action_time) VALUES (?, ?, 'logout', NOW())");
        $stmt->execute([$tenant_id, $_SESSION['user_id']]);
    }
} catch (PDOException $e) {
    error_log("Logout Error: " . $e->getMessage());
}

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Clear any other cookies you might have set
setcookie('remember_me', '', time()-3600, '/');
setcookie('user_preferences', '', time()-3600, '/');

// Redirect to login page with a logged out message
header('Location: ../login.php?status=logged_out');
exit();
?> 