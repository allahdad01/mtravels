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
// Added cdnjs.cloudflare.com for sweetalert CSS/JS
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://code.jquery.com https://cdnjs.cloudflare.com https://player.vimeo.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.datatables.net https://maxcdn.bootstrapcdn.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: blob: https:; connect-src 'self' https: blob: data:; worker-src 'self' blob:; frame-src 'self' https://mtravels.org https://www.mtravels.org https://player.vimeo.com; base-uri 'self'; form-action 'self';");
header("Content-Security-Policy-Report-Only: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://code.jquery.com https://cdnjs.cloudflare.com https://player.vimeo.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.datatables.net https://maxcdn.bootstrapcdn.com https://cdnjs.cloudflare.com; img-src 'self' data: blob:; connect-src 'self' https: blob: data:; worker-src 'self' blob:; frame-src 'self' https://mtravels.org https://www.mtravels.org https://player.vimeo.com;");

header("Referrer-Policy: strict-origin-when-cross-origin");

// Only set HSTS header for HTTPS connections
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}

// Define allowed roles for admin panel
$admin_roles = ['admin', 'finance', 'sales', 'umrah', 'staff', 'client', 'operations', 'hotel_manager', 'viewer'];

// Load Umrah capability-based permissions (Phase 29)
require_once __DIR__ . '/includes/umrah_permissions.php';

// Load Umrah audit helper (Phase 30)
require_once __DIR__ . '/includes/umrah_audit.php';

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

// Load granular permission system
require_once __DIR__ . '/../includes/permissions.php';

// Permission gate for the current page (map-based). Only runs for
// authenticated users; unauthenticated requests fall through to
// enforce_auth() on the page itself.
if (isset($_SESSION['user_id'])) {
    require_page_permission();
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
        return false;
    }
    
    // Use hash_equals() to prevent timing attacks
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
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
 * IMPROVED: IP-based rate limiting for API endpoints
 * Tracks requests per IP address, not per session
 * Prevents brute force attacks even before login
 * 
 * @param string $endpoint Name of the endpoint
 * @param int $max_requests Maximum allowed requests in the time window
 * @param int $window_seconds Time window in seconds
 * @return bool Whether the request is allowed
 */
function check_rate_limit($endpoint, $max_requests = 30, $window_seconds = 60) {
    $currentTime = time();
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Use file-based rate limiting for IP addresses
    $rate_limit_dir = dirname(__FILE__) . '/../logs/rate_limits/';
    
    // Create directory if it doesn't exist
    if (!is_dir($rate_limit_dir)) {
        @mkdir($rate_limit_dir, 0755, true);
    }
    
    // Create a file for this endpoint + IP combination
    $rate_key = md5($endpoint . ':' . $client_ip);
    $rate_file = $rate_limit_dir . $rate_key . '.json';
    
    $rate_data = [
        'count' => 0,
        'window_start' => $currentTime
    ];
    
    // Read existing rate limit data
    if (file_exists($rate_file)) {
        $file_data = json_decode(file_get_contents($rate_file), true);
        if ($file_data) {
            $rate_data = $file_data;
        }
    }
    
    // Reset if window has expired
    if ($currentTime - $rate_data['window_start'] > $window_seconds) {
        $rate_data = [
            'count' => 0,
            'window_start' => $currentTime
        ];
    }
    
    // Increment count
    $rate_data['count']++;
    
    // Write updated rate limit data back to file
    file_put_contents($rate_file, json_encode($rate_data), LOCK_EX);
    
    // Clean up old rate limit files (older than 1 hour)
    if (rand(1, 100) === 1) { // Run cleanup 1% of the time
        foreach (glob($rate_limit_dir . '*.json') as $file) {
            if (time() - filemtime($file) > 3600) {
                @unlink($file);
            }
        }
    }
    
    // Check if over limit
    if ($rate_data['count'] > $max_requests) {
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
}
?>