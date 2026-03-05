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
    
    // Use Strict for production, Lax for localhost development
    $samesite = (gethostname() === 'localhost' || $_SERVER['HTTP_HOST'] === 'localhost:80') ? 'Lax' : 'Strict';
    ini_set('session.cookie_samesite', $samesite);
    
    session_start();
}

// Set secure HTTP headers
// Note: CSP and other security headers are set in .htaccess to avoid duplication
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

// Define allowed roles for admin panel
$admin_roles = ['super_admin', 'finance'];

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    // Session expired, destroy session and redirect to login
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time(); // Update last activity time

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
 * Check if user is a super admin (system administrator, not tenant-based)
 * Super admins must have role='super_admin' and tenant_id=NULL
 * 
 * @return bool Whether the user is a valid super admin
 */
function check_super_admin() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['role']) && 
           $_SESSION['role'] === 'super_admin' && 
           (empty($_SESSION['tenant_id']) || is_null($_SESSION['tenant_id']));
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
 * Enforce super admin access (system administrator)
 * Super admins must have role='super_admin' and tenant_id=NULL
 * 
 * @return void
 */
function enforce_super_admin() {
    if (!check_super_admin()) {
        // Log unauthorized access attempt
        error_log("Unauthorized super admin access attempt to " . $_SERVER['PHP_SELF'] . 
                 " - IP: " . $_SERVER['REMOTE_ADDR'] . 
                 ", User ID: " . ($_SESSION['user_id'] ?? 'unknown') . 
                 ", Role: " . ($_SESSION['role'] ?? 'none') . 
                 ", Tenant ID: " . ($_SESSION['tenant_id'] ?? 'null'));
        
        http_response_code(403);
        header('Location: ../access_denied.php');
        exit();
    }
}

/**
 * Verify CSRF token from POST request
 * Uses timing-safe comparison to prevent timing attacks
 * 
 * @return bool Whether the CSRF token is valid
 */
function verify_csrf_token() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        // Log potential CSRF attack
        error_log("CSRF attack detected in " . $_SERVER['PHP_SELF'] . 
                 " - IP: " . $_SERVER['REMOTE_ADDR'] . 
                 ", User ID: " . ($_SESSION['user_id'] ?? 'unknown'));
        return false;
    }
    
    // Use hash_equals for timing-safe comparison
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        error_log("CSRF attack detected in " . $_SERVER['PHP_SELF'] . 
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
 * Sanitize output to prevent XSS
 * 
 * @param string $string Input string to sanitize
 * @return string Sanitized string
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Rate limiting for API endpoints - IP-based for better coverage
 * Works across sessions and for unauthenticated users
 * Uses atomic operations to prevent race conditions
 * 
 * @param string $endpoint Name of the endpoint
 * @param int $max_requests Maximum allowed requests in the time window
 * @param int $window_seconds Time window in seconds
 * @return bool Whether the request is allowed
 */
function check_rate_limit($endpoint, $max_requests = 30, $window_seconds = 60) {
    $currentTime = time();
    $clientIp = $_SERVER['REMOTE_ADDR'];
    $rateKey = hash('sha256', $clientIp . ':' . $endpoint);
    $cacheDir = sys_get_temp_dir() . '/php_rate_limits';
    
    // Create cache directory if needed
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0700, true);
    }
    
    $rateFile = $cacheDir . '/' . $rateKey . '.json';
    
    // Use atomic file operations with locking
    $lockFile = $rateFile . '.lock';
    $lock = @fopen($lockFile, 'c');
    
    if ($lock) {
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            // Cannot acquire lock, assume request is allowed (fail-open)
            return true;
        }
        
        // Read existing rate limit data
        $rateData = ['count' => 0, 'window_start' => $currentTime];
        
        if (file_exists($rateFile)) {
            $fileContent = @file_get_contents($rateFile);
            $storedData = json_decode($fileContent, true);
            
            if ($storedData && isset($storedData['window_start'])) {
                // Check if window has expired
                if ($currentTime - $storedData['window_start'] > $window_seconds) {
                    // Reset the window
                    $rateData = ['count' => 0, 'window_start' => $currentTime];
                } else {
                    $rateData = $storedData;
                }
            }
        }
        
        // Check if over limit before incrementing
        $isAllowed = $rateData['count'] < $max_requests;
        
        // Increment count
        $rateData['count']++;
        
        // Write updated data atomically
        file_put_contents($rateFile, json_encode($rateData), LOCK_EX);
        
        // Release lock
        flock($lock, LOCK_UN);
        fclose($lock);
        
        // Clean up old files (older than 1 hour)
        $oneHourAgo = $currentTime - 3600;
        $files = @glob($cacheDir . '/*.json');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (@filemtime($file) < $oneHourAgo) {
                    @unlink($file);
                }
            }
        }
        
        return $isAllowed;
    }
    
    // If lock fails, allow request (fail-open approach)
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

/**
 * Validate input with maximum length to prevent DoS attacks
 * 
 * @param string $input Input to validate
 * @param int $max_length Maximum allowed length
 * @return string|null Validated input or null if exceeds limit
 */
function validate_input_length($input, $max_length = 1000) {
    if (!is_string($input)) {
        return null;
    }
    
    if (strlen($input) > $max_length) {
        error_log("Input length exceeded limit: {$max_length} - Submitted: " . strlen($input) . " bytes - IP: {$_SERVER['REMOTE_ADDR']}");
        return null;
    }
    
    return $input;
}

/**
 * Validate and sanitize search/filter input
 * 
 * @param string $input Input to validate
 * @param int $max_length Maximum allowed length
 * @return string|null Validated input or null if invalid
 */
function sanitize_search_input($input, $max_length = 255) {
    // Trim whitespace
    $input = trim($input);
    
    // Check length
    if (strlen($input) > $max_length) {
        return null;
    }
    
    // Check for suspicious patterns (SQL injection attempts)
    $suspicious_patterns = [
        '/union\s+select/i',
        '/select\s+.*\s+from/i',
        '/insert\s+into/i',
        '/delete\s+from/i',
        '/drop\s+(table|database)/i',
        '/exec\s*\(/i',
    ];
    
    foreach ($suspicious_patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            error_log("Suspicious input detected: " . htmlspecialchars($input) . " - IP: {$_SERVER['REMOTE_ADDR']}");
            return null;
        }
    }
    
    return $input;
}
?> 
