<?php
/**
 * CSRF Token Protection Handler
 * Generates, validates, and regenerates CSRF tokens for form protection
 * 
 * @package MTravels
 * @author Security Team
 */

class CsrfProtection {
    
    const TOKEN_LENGTH = 32;
    const REGENERATE_PROBABILITY = 0.1; // 10% chance to regenerate on validation
    
    /**
     * Generate a new CSRF token
     * @return string New token
     */
    public static function generateToken() {
        // Generate a new token and store it in session
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    /**
     * Get current CSRF token (generates if doesn't exist)
     * @return string Token
     */
    public static function getToken() {
        if (!isset($_SESSION['csrf_token'])) {
            return self::generateToken();
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token from request
     * @param string|null $token Token to validate (if null, checks POST/header)
     * @return bool True if valid
     */
    public static function validateToken($token = null) {
        // Try to get token from parameter, POST, or header
        if ($token === null) {
            $token = $_POST['csrf_token'] 
                ?? $_SERVER['HTTP_X_CSRF_TOKEN'] 
                ?? $_SERVER['HTTP_X_CSRF_TOKEN'] 
                ?? null;
        }
        
        // Token must be present
        if (!$token || !isset($_SESSION['csrf_token'])) {
            error_log("CSRF validation failed: token missing");
            return false;
        }
        
        // Use constant-time comparison to prevent timing attacks
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            error_log("CSRF validation failed: token mismatch from {$_SERVER['REMOTE_ADDR']}");
            return false;
        }
        
        // Check token age (optional: regenerate if older than 1 hour)
        if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 3600) {
            error_log("CSRF token expired, regenerating");
            self::regenerateToken();
        }
        
        // Randomly regenerate token after successful validation
        if (mt_rand(1, 100) <= (self::REGENERATE_PROBABILITY * 100)) {
            self::regenerateToken();
        }
        
        return true;
    }
    
    /**
     * Validate request for CSRF and method type
     * @param string $required_method Required HTTP method (POST, PUT, DELETE, PATCH)
     * @return bool True if valid
     */
    public static function validateRequest($required_method = 'POST') {
        // Check HTTP method
        if ($_SERVER['REQUEST_METHOD'] !== $required_method) {
            error_log("Method mismatch: expected {$required_method}, got {$_SERVER['REQUEST_METHOD']}");
            return false;
        }
        
        // Validate CSRF token
        return self::validateToken();
    }
    
    /**
     * Regenerate CSRF token
     * @return string New token
     */
    public static function regenerateToken() {
        return self::generateToken();
    }
    
    /**
     * Validate and regenerate in one call
     * Useful for forms that submit via AJAX or need immediate renewal
     * @return bool True if token was valid (will regenerate regardless)
     */
    public static function validateAndRegenerate() {
        $is_valid = self::validateToken();
        self::regenerateToken();
        return $is_valid;
    }
    
    /**
     * Get token for inclusion in HTML
     * Safe for direct output
     * @return string HTML-escaped token
     */
    public static function getTokenField() {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    /**
     * Output JSON response with CSRF token
     * Useful for API endpoints
     * @param array $data Data to include in response
     * @return string JSON string
     */
    public static function addTokenToJSON($data = []) {
        $data['csrf_token'] = self::getToken();
        return json_encode($data);
    }
    
    /**
     * Check if token needs regeneration
     * @return bool True if should be regenerated
     */
    public static function shouldRegenerate() {
        if (!isset($_SESSION['csrf_token_time'])) {
            return true;
        }
        
        // Regenerate every 24 hours regardless
        return (time() - $_SESSION['csrf_token_time']) > 86400;
    }
}
?>
