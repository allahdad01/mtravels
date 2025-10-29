<?php
/**
 * Secure Logging Utility for Admin Panel
 * 
 * This class provides a secure way to log important events in the admin panel,
 * particularly for security-related and sensitive operations.
 */
class Logger {
    // Log levels
    const DEBUG = 0;
    const INFO = 1;
    const WARNING = 2;
    const ERROR = 3;
    const CRITICAL = 4;
    
    // Log file path - ensure this is outside the web root
    private static $logFilePath = '../../logs/admin_log.log';
    
    // Current minimum log level
    private static $minLogLevel = self::INFO;
    
    /**
     * Log a message with context information
     * 
     * @param string $message Log message
     * @param int $level Log level
     * @param array $context Additional context data
     * @return bool Whether logging was successful
     */
    public static function log($message, $level = self::INFO, $context = []) {
        // Only log if level is at or above minimum
        if ($level < self::$minLogLevel) {
            return true;
        }
        
        // Get level name
        $levelName = self::getLevelName($level);
        
        // Get user info
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'unknown';
        $userName = isset($_SESSION['name']) ? $_SESSION['name'] : 'unknown';
        $userRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'unknown';
        
        // Get request info
        $ip = $_SERVER['REMOTE_ADDR'];
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown';
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'unknown';
        
        // Format timestamp
        $timestamp = date('Y-m-d H:i:s');
        
        // Format log entry
        $logEntry = sprintf(
            "[%s] [%s] [User %s (%s) - %s] [%s %s] %s",
            $timestamp,
            $levelName,
            $userId,
            $userName,
            $userRole,
            $method,
            $requestUri,
            $message
        );
        
        // Add IP address
        $logEntry .= sprintf(" [IP: %s]", $ip);
        
        // Add context if provided
        if (!empty($context)) {
            // Sanitize and limit context data
            $safeContext = self::sanitizeContext($context);
            $logEntry .= " Context: " . json_encode($safeContext);
        }
        
        // Write to log file
        try {
            $result = file_put_contents(
                self::$logFilePath, 
                $logEntry . PHP_EOL, 
                FILE_APPEND | LOCK_EX
            );
            
            // If file_put_contents fails, try PHP error log
            if ($result === false) {
                error_log($logEntry);
            }
            
            return $result !== false;
        } catch (Exception $e) {
            // Fallback to error_log if exception occurs
            error_log('Logger error: ' . $e->getMessage());
            error_log($logEntry);
            return false;
        }
    }
    
    /**
     * Convenience method for DEBUG level logging
     */
    public static function debug($message, $context = []) {
        return self::log($message, self::DEBUG, $context);
    }
    
    /**
     * Convenience method for INFO level logging
     */
    public static function info($message, $context = []) {
        return self::log($message, self::INFO, $context);
    }
    
    /**
     * Convenience method for WARNING level logging
     */
    public static function warning($message, $context = []) {
        return self::log($message, self::WARNING, $context);
    }
    
    /**
     * Convenience method for ERROR level logging
     */
    public static function error($message, $context = []) {
        return self::log($message, self::ERROR, $context);
    }
    
    /**
     * Convenience method for CRITICAL level logging
     */
    public static function critical($message, $context = []) {
        return self::log($message, self::CRITICAL, $context);
    }
    
    /**
     * Log a security-related event (always logged regardless of min level)
     */
    public static function security($message, $context = []) {
        $originalMinLevel = self::$minLogLevel;
        self::$minLogLevel = self::INFO; // Temporarily lower min level
        
        $result = self::log('[SECURITY] ' . $message, self::WARNING, $context);
        
        self::$minLogLevel = $originalMinLevel; // Restore original min level
        return $result;
    }
    
    /**
     * Log a database query (debug level)
     */
    public static function query($query, $params = [], $result = null) {
        $context = [
            'query' => self::truncate($query, 500),
            'params' => self::sanitizeContext($params)
        ];
        
        if ($result !== null) {
            $context['result'] = self::truncate(print_r($result, true), 200);
        }
        
        return self::debug('Database query executed', $context);
    }
    
    /**
     * Convert log level to string name
     */
    private static function getLevelName($level) {
        switch ($level) {
            case self::DEBUG:
                return 'DEBUG';
            case self::INFO:
                return 'INFO';
            case self::WARNING:
                return 'WARNING';
            case self::ERROR:
                return 'ERROR';
            case self::CRITICAL:
                return 'CRITICAL';
            default:
                return 'UNKNOWN';
        }
    }
    
    /**
     * Sanitize context data for logging
     */
    private static function sanitizeContext($context) {
        if (!is_array($context)) {
            return self::truncate(print_r($context, true), 200);
        }
        
        $safeContext = [];
        
        foreach ($context as $key => $value) {
            // Skip sensitive keys
            if (self::isSensitiveKey($key)) {
                $safeContext[$key] = '[REDACTED]';
                continue;
            }
            
            // Handle nested arrays
            if (is_array($value)) {
                $safeContext[$key] = self::sanitizeContext($value);
            } else {
                // Truncate long values
                $safeContext[$key] = self::truncate((string)$value, 200);
            }
        }
        
        return $safeContext;
    }
    
    /**
     * Check if a key might contain sensitive information
     */
    private static function isSensitiveKey($key) {
        $sensitiveKeys = [
            'password', 'pass', 'pwd', 'secret', 'token', 'key', 'auth',
            'credential', 'credit_card', 'card', 'cvv', 'ssn', 'account'
        ];
        
        $key = strtolower($key);
        
        foreach ($sensitiveKeys as $sensitive) {
            if (strpos($key, $sensitive) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Truncate a string to a maximum length
     */
    private static function truncate($string, $maxLength = 100) {
        if (strlen($string) <= $maxLength) {
            return $string;
        }
        
        return substr($string, 0, $maxLength) . '...';
    }
    
    /**
     * Set the minimum log level
     */
    public static function setMinLogLevel($level) {
        self::$minLogLevel = $level;
    }
    
    /**
     * Set the log file path
     */
    public static function setLogFilePath($path) {
        self::$logFilePath = $path;
    }
}
?> 