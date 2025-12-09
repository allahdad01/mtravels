<?php
/**
 * Global Helper Functions
 * These functions are used throughout the application for common tasks
 */

// Prevent direct access
if (count(get_included_files()) == 1) {
    header("HTTP/1.0 403 Forbidden");
    exit("Direct access to this file is not allowed.");
}

/**
 * HTML escape/sanitize output
 * Safely escapes HTML special characters to prevent XSS attacks
 * 
 * @param string $string Input string to sanitize
 * @return string Sanitized string safe for HTML output
 */
if (!function_exists('h')) {
    function h($string) {
        if ($string === null) {
            return '';
        }
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Sanitize user input
 * Removes unwanted characters and normalizes whitespace
 * 
 * @param string $data Input data to sanitize
 * @return string Sanitized data
 */
if (!function_exists('sanitize')) {
    function sanitize($data) {
        $data = trim($data);
        $data = stripslashes($data);
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Format currency for display
 * 
 * @param float $amount Amount to format
 * @param string $currency Currency code (USD, AFS, EUR, AED)
 * @return string Formatted currency string
 */
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount, $currency = 'USD') {
        $symbols = [
            'USD' => '$',
            'AFS' => '؋',
            'EUR' => '€',
            'AED' => 'د.إ',
            'DARHAM' => '؋'
        ];
        
        $symbol = $symbols[$currency] ?? $currency;
        return $symbol . ' ' . number_format($amount, 2);
    }
}

/**
 * Format number with thousands separator
 * 
 * @param float|int $number Number to format
 * @param int $decimals Number of decimal places
 * @return string Formatted number
 */
if (!function_exists('formatNumber')) {
    function formatNumber($number, $decimals = 2) {
        return number_format($number, $decimals);
    }
}

/**
 * Check if user has a specific role
 * 
 * @param string|array $role Role(s) to check
 * @return bool True if user has the role
 */
if (!function_exists('hasRole')) {
    function hasRole($role) {
        if (!isset($_SESSION['role'])) {
            return false;
        }
        
        if (is_array($role)) {
            return in_array(strtolower($_SESSION['role']), array_map('strtolower', $role));
        }
        
        return strtolower($_SESSION['role']) === strtolower($role);
    }
}

/**
 * Check if user is authenticated
 * 
 * @return bool True if user is logged in
 */
if (!function_exists('isAuthenticated')) {
    function isAuthenticated() {
        return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
    }
}

/**
 * Get current user ID
 * 
 * @return int|null User ID or null if not authenticated
 */
if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
        return isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    }
}

/**
 * Get current tenant ID
 * 
 * @return int|null Tenant ID or null if not set
 */
if (!function_exists('getCurrentTenantId')) {
    function getCurrentTenantId() {
        return isset($_SESSION['tenant_id']) ? intval($_SESSION['tenant_id']) : null;
    }
}

/**
 * Get current branch ID
 * 
 * @return int|null Branch ID or null if not set
 */
if (!function_exists('getCurrentBranchId')) {
    function getCurrentBranchId() {
        return isset($_SESSION['branch_id']) ? intval($_SESSION['branch_id']) : null;
    }
}

/**
 * Redirect to URL
 * 
 * @param string $url URL to redirect to
 * @param int $statusCode HTTP status code (default 302)
 */
if (!function_exists('redirect')) {
    function redirect($url, $statusCode = 302) {
        http_response_code($statusCode);
        header("Location: " . $url);
        exit();
    }
}

/**
 * Check if variable is set and not empty
 * 
 * @param mixed $var Variable to check
 * @return bool True if variable is set and not empty
 */
if (!function_exists('notEmpty')) {
    function notEmpty($var) {
        return isset($var) && !empty($var);
    }
}
?>
