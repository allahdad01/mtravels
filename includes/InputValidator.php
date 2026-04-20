<?php
/**
 * InputValidator - Centralized Input Validation Helper
 * 
 * Provides reusable validation methods for all user input:
 * - Type casting (int, bool, string)
 * - Format validation (email, date, phone)
 * - Range/length validation
 * - Enum/whitelist validation
 * - Pattern matching with regex
 * 
 * Usage:
 *   $id = InputValidator::getInt($_GET['id'], 0);
 *   $email = InputValidator::getEmail($_POST['email']);
 *   $status = InputValidator::getEnum($_GET['status'], ['active', 'inactive']);
 */

class InputValidator {
    
    /**
     * Get integer value from input
     * Safely casts to int, returns default if invalid
     * 
     * @param mixed $value The input value
     * @param int $default Default value if invalid
     * @param int $min Minimum allowed value (optional)
     * @param int $max Maximum allowed value (optional)
     * @return int
     */
    public static function getInt($value, $default = 0, $min = null, $max = null) {
        if (!is_numeric($value)) {
            return $default;
        }
        
        $int = (int)$value;
        
        if ($min !== null && $int < $min) {
            return $default;
        }
        
        if ($max !== null && $int > $max) {
            return $default;
        }
        
        return $int;
    }
    
    /**
     * Get boolean value from input
     * Handles 'true', '1', 'on', 'yes' as true; others as false
     * 
     * @param mixed $value The input value
     * @return bool
     */
    public static function getBool($value) {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Get value from whitelist of allowed values (enum validation)
     * Performs strict type checking (===)
     * 
     * @param mixed $value The input value
     * @param array $allowed Array of allowed values
     * @param mixed $default Value to return if not in whitelist
     * @return mixed
     */
    public static function getEnum($value, $allowed = [], $default = null) {
        if (empty($allowed) || !is_array($allowed)) {
            return $default;
        }
        
        return in_array($value, $allowed, true) ? $value : $default;
    }
    
    /**
     * Get validated date from input
     * Validates format and actual date validity
     * 
     * @param mixed $value The input value
     * @param string $format Expected date format (default: Y-m-d)
     * @param mixed $default Value to return if invalid
     * @return mixed
     */
    public static function getDate($value, $format = 'Y-m-d', $default = null) {
        if (!is_string($value)) {
            return $default;
        }
        
        $date = DateTime::createFromFormat($format, $value);
        
        // Verify format matched exactly and date is valid
        if ($date && $date->format($format) === $value) {
            return $value;
        }
        
        return $default;
    }
    
    /**
     * Get validated email from input
     * Uses PHP's filter_var FILTER_VALIDATE_EMAIL
     * 
     * @param mixed $value The input value
     * @return string|null Email if valid, null if invalid
     */
    public static function getEmail($value) {
        if (!is_string($value)) {
            return null;
        }
        
        $email = filter_var(trim($value), FILTER_VALIDATE_EMAIL);
        
        // Additional length check (RFC 5321: max 320 chars)
        if ($email && strlen($email) <= 320) {
            return $email;
        }
        
        return null;
    }
    
    /**
     * Get validated string with max length
     * Trims whitespace, enforces max length
     * 
     * @param mixed $value The input value
     * @param int $maxLength Maximum allowed length
     * @param mixed $default Value to return if invalid
     * @return string
     */
    public static function getString($value, $maxLength = 255, $default = '') {
        if (!is_string($value)) {
            return $default;
        }
        
        $value = trim($value);
        
        if (strlen($value) > $maxLength) {
            return $default;
        }
        
        return $value;
    }
    
    /**
     * Get string with minimum and maximum length requirements
     * 
     * @param mixed $value The input value
     * @param int $minLength Minimum allowed length
     * @param int $maxLength Maximum allowed length
     * @param mixed $default Value to return if invalid
     * @return string
     */
    public static function getStringBounded($value, $minLength = 1, $maxLength = 255, $default = '') {
        if (!is_string($value)) {
            return $default;
        }
        
        $value = trim($value);
        $length = strlen($value);
        
        if ($length < $minLength || $length > $maxLength) {
            return $default;
        }
        
        return $value;
    }
    
    /**
     * Get value matching regex pattern
     * Useful for phone numbers, postal codes, etc.
     * 
     * @param mixed $value The input value
     * @param string $pattern Regex pattern to match
     * @param mixed $default Value to return if pattern doesn't match
     * @return mixed
     */
    public static function getPattern($value, $pattern, $default = null) {
        if (!is_string($value)) {
            return $default;
        }
        
        if (@preg_match($pattern, $value)) {
            return $value;
        }
        
        return $default;
    }
    
    /**
     * Get phone number with validation
     * Allows digits, spaces, hyphens, parentheses, plus sign
     * 
     * @param mixed $value The input value
     * @param mixed $default Value to return if invalid
     * @return string|null
     */
    public static function getPhone($value, $default = null) {
        if (!is_string($value)) {
            return $default;
        }
        
        $value = trim($value);
        
        // If empty, return default (allows optional phone)
        if (empty($value)) {
            return $default;
        }
        
        // Allow: digits, +, -, (), spaces | 5-20 chars (min 5 to allow flexibility)
        $pattern = '/^[\d\+\-\(\)\s]{5,20}$/';
        
        return self::getPattern($value, $pattern, $default);
    }
    
    /**
     * Get URL with validation
     * Uses PHP's filter_var FILTER_VALIDATE_URL
     * 
     * @param mixed $value The input value
     * @return string|null
     */
    public static function getUrl($value) {
        if (!is_string($value)) {
            return null;
        }
        
        return filter_var($value, FILTER_VALIDATE_URL) ?: null;
    }
    
    /**
     * Get IP address with validation
     * Accepts both IPv4 and IPv6
     * 
     * @param mixed $value The input value
     * @return string|null
     */
    public static function getIp($value) {
        if (!is_string($value)) {
            return null;
        }
        
        return filter_var($value, FILTER_VALIDATE_IP) ?: null;
    }
    
    /**
     * Get array element safely
     * Returns value if exists and matches expected type/constraints
     * 
     * @param array $array The array to search
     * @param string $key The array key
     * @param mixed $default Value to return if key doesn't exist
     * @return mixed
     */
    public static function getArrayKey($array, $key, $default = null) {
        if (!is_array($array)) {
            return $default;
        }
        
        return isset($array[$key]) ? $array[$key] : $default;
    }
    
    /**
     * Validate file upload
     * Checks file type, size, and MIME type
     * 
     * @param array $file The $_FILES array entry
     * @param array $allowedMimes Allowed MIME types
     * @param int $maxSize Maximum file size in bytes
     * @return bool
     */
    public static function validateFileUpload($file, $allowedMimes = [], $maxSize = 5242880) {
        // Check file array structure
        if (!isset($file['tmp_name']) || !isset($file['size']) || !isset($file['type'])) {
            return false;
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            return false;
        }
        
        // Check upload error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        // If no allowed mimes specified, accept any
        if (empty($allowedMimes)) {
            return true;
        }
        
        // Check MIME type using finfo (more reliable than $_FILES['type'])
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        return in_array($mime, $allowedMimes, true);
    }
    
    /**
     * Sanitize filename for safe storage
     * Removes special characters, prevents directory traversal
     * 
     * @param string $filename Original filename
     * @param string $extension Original extension (optional)
     * @return string Sanitized filename
     */
    public static function sanitizeFilename($filename, $extension = '') {
        // Remove path traversal attempts
        $filename = basename($filename);
        
        // Remove special characters, keep only alphanumeric, dot, hyphen, underscore
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // Remove leading/trailing dots and hyphens
        $filename = trim($filename, '.-');
        
        // If empty after sanitization, use default
        if (empty($filename)) {
            $filename = 'file';
        }
        
        // Add extension if provided
        if (!empty($extension)) {
            $extension = trim($extension, '.');
            $filename = $filename . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $extension);
        }
        
        return $filename;
    }
    
    /**
     * Validate that required fields are present and not empty
     * 
     * @param array $data The data array to check
     * @param array $required Array of required field names
     * @return array Array of missing field names (empty if all present)
     */
    public static function validateRequired($data, $required = []) {
        $missing = [];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }
        
        return $missing;
    }
    
    /**
     * Get month number (1-12) from input
     * Accepts numeric or month name
     * 
     * @param mixed $value Month number or name
     * @param mixed $default Default if invalid
     * @return int|null
     */
    public static function getMonth($value, $default = null) {
        // Try numeric first
        $month = self::getInt($value, 0, 1, 12);
        if ($month > 0) {
            return $month;
        }
        
        // Try month name
        if (is_string($value)) {
            $months = [
                'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
                'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
                'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
                'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
                'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12
            ];
            
            $key = strtolower($value);
            return isset($months[$key]) ? $months[$key] : $default;
        }
        
        return $default;
    }
    
    /**
     * Get year from input
     * Validates reasonable year range (1900-2100)
     * 
     * @param mixed $value The year value
     * @param mixed $default Default if invalid
     * @return int|null
     */
    public static function getYear($value, $default = null) {
        return self::getInt($value, $default, 1900, 2100);
    }
}

?>
