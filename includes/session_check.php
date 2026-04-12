<?php


// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in and session is valid
function checkSessionValid() {
    // Check if user is logged in
    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
        return false;
    }
    
    // Check for session timeout (30 minutes = 1800 seconds)
    $session_timeout = 1800; // 30 minutes in seconds
    
    // Use last_activity for timeout checking, not login_time
    // This ensures the session expires after 30 minutes of INACTIVITY
    if (!isset($_SESSION["last_activity"])) {
        $_SESSION["last_activity"] = time();
    }
    
    if (time() - $_SESSION["last_activity"] > $session_timeout) {
        // Session timed out due to inactivity
        session_unset();
        session_destroy();
        return false;
    }
    
    // Verify IP address hasn't changed (session hijacking prevention)
    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        session_unset();
        session_destroy();
        return false;
    }
    
    // Verify User-Agent hasn't changed (session hijacking prevention)
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_unset();
        session_destroy();
        return false;
    }
    
    // Update last activity time (this extends the session on active use)
    $_SESSION["last_activity"] = time();
    
    // Regenerate session ID periodically to prevent session fixation
    if (!isset($_SESSION["last_regeneration"]) || (time() - $_SESSION["last_regeneration"] > 300)) {
        session_regenerate_id(true);
        $_SESSION["last_regeneration"] = time();
    }
    
    return true;
}

// Check if current request is for a protected page
$current_script = basename($_SERVER['SCRIPT_NAME']);
$public_pages = array('login.php', 'index.php', 'forgot_password.php', 'reset_password.php');

// Check if user is trying to access a protected page
if (!in_array($current_script, $public_pages)) {
    // Check if user is in TOTP verification phase
    if (isset($_SESSION["totp_verification"]) && $_SESSION["totp_verification"] === true) {
        // Only allow access to login.php during TOTP verification
        header("location: " . determineBasePath() . "login.php");
        exit;
    }
    
    // Check if session is valid
    if (!checkSessionValid()) {
        // Redirect to login page
        header("location: " . determineBasePath() . "login.php");
        exit;
    }
    
    // Check for proper authorization based on role
    checkRoleAuthorization();
}

// Function to check if user has proper authorization for current section
function checkRoleAuthorization() {
    if (!isset($_SESSION["role"])) {
        header("location: " . determineBasePath() . "login.php");
        exit;
    }

    $current_path = $_SERVER['REQUEST_URI'];
    $role = strtolower($_SESSION["role"]);

    // Check tenant payment status for non-super-admin users
    if ($role !== 'super_admin') {
        checkTenantPaymentStatus();
    }

    // Admin and tenant_super_admin can access all sections
    if ($role === 'admin' || $role === 'tenant_super_admin') {
        return true;
    }
    
    // Check if user is trying to access a section they don't have permission for
     // Admin folder is shared by admin, finance, sales, umrah, and staff roles
     $allowed_admin_roles = ['admin', 'tenant_super_admin', 'finance', 'sales', 'umrah', 'staff'];
    if (strpos($current_path, '/admin/') !== false && !in_array($role, $allowed_admin_roles)) {
        header("location: " . determineBasePath() . "access_denied.php");
        exit;
    }
    
    if (strpos($current_path, '/sales/') !== false && $role !== 'sales') {
        header("location: " . determineBasePath() . "access_denied.php");
        exit;
    }
    
    if (strpos($current_path, '/finance/') !== false && $role !== 'finance') {
        header("location: " . determineBasePath() . "access_denied.php");
        exit;
    }
    
    if (strpos($current_path, '/umrah/') !== false && $role !== 'umrah') {
        header("location: " . determineBasePath() . "access_denied.php");
        exit;
    }
    
    if (strpos($current_path, '/visa/') !== false && $role !== 'visa') {
        header("location: " . determineBasePath() . "access_denied.php");
        exit;
    }
    
    if (strpos($current_path, '/client/') !== false && $role !== 'client') {
        header("location: " . determineBasePath() . "access_denied.php");
        exit;
    }
    
    return true;
}

// Helper function to determine the base path for redirects
function determineBasePath() {
    $path_parts = explode('/', $_SERVER['SCRIPT_NAME']);
    array_pop($path_parts); // Remove the script name
    
    // If in a subdirectory, go back to root
    if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false ||
        strpos($_SERVER['SCRIPT_NAME'], '/sales/') !== false ||
        strpos($_SERVER['SCRIPT_NAME'], '/finance/') !== false ||
        strpos($_SERVER['SCRIPT_NAME'], '/umrah/') !== false ||
        strpos($_SERVER['SCRIPT_NAME'], '/visa/') !== false ||
        strpos($_SERVER['SCRIPT_NAME'], '/client/') !== false ||
        strpos($_SERVER['SCRIPT_NAME'], '/tenant_super_admin/') !== false) {
        return "../";
    }
    
    return "./";
}

// Function to check tenant payment status and block access if suspended
function checkTenantPaymentStatus() {
    global $pdo; // Declare global to access PDO from outside function
    
    // Skip check for super admin and public pages
    if (isset($_SESSION["role"]) && strtolower($_SESSION["role"]) === 'super_admin') {
        return;
    }

    $current_script = basename($_SERVER['SCRIPT_NAME']);
    $public_pages = array('login.php', 'index.php', 'forgot_password.php', 'reset_password.php', 'payment_required.php');

    if (in_array($current_script, $public_pages)) {
        return;
    }

    // Check if tenant_id is set in session
    if (!isset($_SESSION["tenant_id"])) {
        return; // No tenant context, allow access
    }

    $tenant_id = $_SESSION["tenant_id"];

    // Database connection - only require if not already initialized
    if (!isset($pdo) || $pdo === null) {
        require_once 'db.php';
    }

    if (!isset($pdo) || !$pdo) {
        return; // Database not available, allow access
    }

    try {
        // Check tenant payment status
        $stmt = $pdo->prepare("SELECT payment_status, status FROM tenants WHERE id = ?");
        $stmt->execute([$tenant_id]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tenant) {
            // If tenant is suspended due to payment issues, redirect to payment required page
            if ($tenant['payment_status'] === 'suspended' || $tenant['status'] === 'suspended') {
                header("location: " . determineBasePath() . "payment_required.php");
                exit;
            }
        }
    } catch (Exception $e) {
        // Log error but don't block access if database check fails
    }
}
?>