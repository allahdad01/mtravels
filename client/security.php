<?php
/**
 * Client Panel Security Module
 * 
 * This security module ensures:
 * 1. Only clients can access the client panel
 * 2. All data is filtered by client_id (no cross-client data access)
 * 3. No modification/deletion operations are allowed (display-only)
 * 4. Session integrity is maintained
 */

// Output buffering for session headers
ob_start();

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    
    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    if ($is_https) {
        ini_set('session.cookie_secure', 1);
    }
    
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    
    session_start();
}

// Security Headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// CSP for client panel
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://code.jquery.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://maxcdn.bootstrapcdn.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self' https:; frame-ancestors 'self'; base-uri 'self'; form-action 'none';");

/**
 * Enforce Client Authentication
 * 
 * @throws RedirectException - Redirects to login if not authenticated
 */
function enforce_client_auth() {
    // Check if session exists
    if (!isset($_SESSION['user_id'])) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }
    
    // Ensure client_id is set (fallback to user_id if not)
    if (!isset($_SESSION['client_id'])) {
        $_SESSION['client_id'] = $_SESSION['user_id'];
    }
    
    // Verify user role is 'client'
    if ($_SESSION['role'] !== 'client') {
        header('Location: ../login.php');
        exit();
    }
    
    // Check if tenant_id is set
    if (!isset($_SESSION['tenant_id'])) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }
}

/**
 * Validate and filter data by client_id
 * 
 * Ensures no client can view or access another client's data
 * 
 * @param string $table Table name
 * @param int $record_id Record ID to verify
 * @param PDO $pdo Database connection
 * @return bool True if record belongs to current client
 */
function client_owns_record($table, $record_id, $pdo) {
    $client_id = $_SESSION['client_id'];
    $tenant_id = $_SESSION['tenant_id'];
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table WHERE id = ? AND client_id = ? AND tenant_id = ?");
        $stmt->execute([$record_id, $client_id, $tenant_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Prevent any modification/deletion operations
 * 
 * Client panel is read-only - block POST/PUT/DELETE/PATCH
 * Except for specific handler files that are allowed to modify data
 * 
 * @throws Exception - If modification attempt is detected
 */
function prevent_modifications() {
    // Get current script name
    $script = basename($_SERVER['PHP_SELF']);
    
    // Allow POST requests to specific handler files
    $allowed_post_files = [
        'update_profile.php',
        'api/change_password.php',
        // Add other handler files as needed
    ];
    
    // Check if this is an allowed POST file
    $is_allowed_post = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($allowed_post_files as $allowed_file) {
            if (strpos($script, $allowed_file) !== false) {
                $is_allowed_post = true;
                break;
            }
        }
    }
    
    // Block if not an allowed operation
    if (($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_allowed_post) || 
        $_SERVER['REQUEST_METHOD'] === 'PUT' || 
        $_SERVER['REQUEST_METHOD'] === 'DELETE' || 
        $_SERVER['REQUEST_METHOD'] === 'PATCH') {
        
        http_response_code(403);
        die(json_encode(['error' => 'Modifications not allowed in client panel']));
    }
}

/**
 * Build a query WHERE clause for client-scoped data
 * 
 * @param string $table_alias Optional table alias/prefix
 * @return string WHERE clause fragment
 */
function client_where_clause($table_alias = '') {
    $prefix = $table_alias ? $table_alias . '.' : '';
    return $prefix . 'client_id = ' . intval($_SESSION['client_id']) . ' AND ' . 
           $prefix . 'tenant_id = ' . intval($_SESSION['tenant_id']);
}

/**
 * Safe array access with client validation
 * 
 * @param array $data Record data
 * @return bool True if data belongs to client
 */
function validate_client_data($data) {
    return ($data['client_id'] ?? null) === $_SESSION['client_id'] &&
           ($data['tenant_id'] ?? null) === $_SESSION['tenant_id'];
}

// Auto-enforce on every client page load
enforce_client_auth();
prevent_modifications();
