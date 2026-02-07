<?php
// Start output buffering at the very beginning
ob_start();

/**
 * Central Security Module for Admin Panel
 * 
 * This file should be included at the top of every admin page to enforce security best practices.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session parameters
    ini_set('session.cookie_httponly', 1);
    
    // Only enable secure cookies in production with HTTPS
    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    if ($is_https) {
        ini_set('session.cookie_secure', 1);
    }
    
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax'); // Changed from Strict to Lax for better local development
    
    session_start();
}

// Generate nonce for inline scripts/styles (for CSP compliance)
if (!isset($_SESSION['csp_nonce'])) {
    $_SESSION['csp_nonce'] = bin2hex(random_bytes(16));
}
$nonce = $_SESSION['csp_nonce'];

// Set secure HTTP headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

// Content Security Policy - prevents XSS and injection attacks
// Allow inline styles for Bootstrap and other libraries, plus external CDNs
// Added worker-src, blob:, data:, and unsafe-eval for Tesseract.js WASM OCR worker
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://code.jquery.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.datatables.net https://maxcdn.bootstrapcdn.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob: https:; connect-src 'self' https: blob: data:; worker-src 'self' blob:; frame-ancestors 'none'; base-uri 'self'; form-action 'self';");
header("Content-Security-Policy-Report-Only: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://code.jquery.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.datatables.net https://maxcdn.bootstrapcdn.com; img-src 'self' data: blob:; connect-src 'self' https: blob: data:; worker-src 'self' blob:;");

header("Referrer-Policy: strict-origin-when-cross-origin");

// Only set HSTS header for HTTPS connections
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}

// Define allowed roles for admin panel
$admin_roles = ['admin', 'finance'];

// Check session timeout (30 minutes = 1800 seconds)
$sessionTimeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    // Session expired due to inactivity, destroy session and redirect to login
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time(); // Update last activity time on each request

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Check if user is authenticated and has appropriate role
 * 
 * @param array $allowed_roles Roles allowed to access the page
 * @return bool Whether the user has access
 */
function check_auth($allowed_roles = null) {
    global $admin_roles;
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    
    // If no specific roles are provided, use the global admin roles
    if ($allowed_roles === null) {
        $allowed_roles = $admin_roles;
    }
    
    // Check if user's role is in the allowed roles
    return in_array($_SESSION['role'], $allowed_roles);
}

/**
 * Enforce authentication for the current page
 * 
 * @param array $allowed_roles Roles allowed to access the page
 * @return void
 */
function enforce_auth($allowed_roles = null) {
    if (!check_auth($allowed_roles)) {
        // Log unauthorized access attempt
        error_log("Unauthorized access attempt to " . $_SERVER['PHP_SELF'] . 
                 " - IP: " . $_SERVER['REMOTE_ADDR'] . 
                 ", User ID: " . ($_SESSION['user_id'] ?? 'unknown') . 
                 ", Role: " . ($_SESSION['role'] ?? 'none'));
        
        // Store the current URL for later redirect after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Redirect to login page
        header('Location: ../login.php');
        exit();
    }
}

/**
 * Verify CSRF token from POST request
 * 
 * @param string $token Optional CSRF token to verify (defaults to $_POST['csrf_token'])
 * @return bool Whether the CSRF token is valid
 */
function verify_csrf_token($token = null) {
    // Get token from parameter, POST data, or JSON body
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? null;
        
        // If not in POST, check JSON body
        if (!$token && $_SERVER["REQUEST_METHOD"] == "POST") {
            $data = json_decode(file_get_contents("php://input"), true);
            $token = $data['csrf_token'] ?? null;
        }
        
        // Also check headers for CSRF token (common in AJAX requests)
        if (!$token && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
    }
    
    if (!$token || !isset($_SESSION['csrf_token'])) {
        // Log potential CSRF attack
        error_log("CSRF attack detected - missing token in " . $_SERVER['PHP_SELF'] . 
                 " - IP: " . $_SERVER['REMOTE_ADDR'] . 
                 ", User ID: " . ($_SESSION['user_id'] ?? 'unknown'));
        return false;
    }
    
    // Use hash_equals() to prevent timing attacks
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        // Log potential CSRF attack
        error_log("CSRF attack detected - invalid token in " . $_SERVER['PHP_SELF'] . 
                 " - IP: " . $_SERVER['REMOTE_ADDR'] . 
                 ", User ID: " . ($_SESSION['user_id'] ?? 'unknown'));
        return false;
    }
    
    return true;
}

/**
 * Enforce CSRF protection for POST requests
 * 
 * @param string $redirect_url URL to redirect to on failure
 * @return void
 */
function enforce_csrf($redirect_url = null) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf_token()) {
            if ($redirect_url) {
                $_SESSION['error'] = 'Security validation failed. Please try again.';
                header("Location: $redirect_url");
            } else {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Security validation failed']);
            }
            exit();
        }
    }
}

/**
 * Rate limiting for API endpoints
 * 
 * @param string $endpoint Name of the endpoint
 * @param int $max_requests Maximum allowed requests in the time window
 * @param int $window_seconds Time window in seconds
 * @return bool Whether the request is allowed
 */
function check_rate_limit($endpoint, $max_requests = 30, $window_seconds = 60) {
    $currentTime = time();
    
    // Initialize rate limiting data
    if (!isset($_SESSION['api_rate_limits'])) {
        $_SESSION['api_rate_limits'] = [];
    }
    
    if (!isset($_SESSION['api_rate_limits'][$endpoint])) {
        $_SESSION['api_rate_limits'][$endpoint] = [
            'count' => 0,
            'window_start' => $currentTime
        ];
    }
    
    // Reset if window has expired
    if ($currentTime - $_SESSION['api_rate_limits'][$endpoint]['window_start'] > $window_seconds) {
        $_SESSION['api_rate_limits'][$endpoint] = [
            'count' => 0,
            'window_start' => $currentTime
        ];
    }
    
    // Increment count
    $_SESSION['api_rate_limits'][$endpoint]['count']++;
    
    // Check if over limit
    if ($_SESSION['api_rate_limits'][$endpoint]['count'] > $max_requests) {
        return false;
    }
    
    return true;
}

/**
 * Enforce rate limiting for API endpoints
 * 
 * @param string $endpoint Name of the endpoint
 * @param int $max_requests Maximum allowed requests in the time window
 * @param int $window_seconds Time window in seconds
 * @return void
 */
function enforce_rate_limit($endpoint, $max_requests = 30, $window_seconds = 60) {
    if (!check_rate_limit($endpoint, $max_requests, $window_seconds)) {
        http_response_code(429);
        echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please try again later.']);
        exit();
    }
}

/**
 * Log security events
 * 
 * @param string $message Log message
 * @param string $level Log level (info, warning, error)
 * @return void
 */
function security_log($message, $level = 'info') {
    $user_id = $_SESSION['user_id'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'];
    $script = $_SERVER['PHP_SELF'];
    
    $log_message = date('Y-m-d H:i:s') . " [$level] $message - User: $user_id, IP: $ip, Script: $script";
    error_log($log_message);
}
?> 