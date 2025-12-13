<?php
/**
 * Environment Variables Loader
 * Loads and validates .env file
 * 
 * Usage: require_once 'includes/env_loader.php';
 */

class EnvLoader {
    private static $envLoaded = false;
    private static $errors = [];
    
    /**
     * Load environment variables from .env file
     */
    public static function load() {
        if (self::$envLoaded) {
            return true;
        }
        
        $envFile = dirname(__DIR__) . '/.env';
        
        // Check if .env exists
        if (!file_exists($envFile)) {
            self::$errors[] = "Missing .env file. Copy .env.example to .env and configure it.";
            error_log("CRITICAL: .env file not found at: $envFile");
            return false;
        }
        
        // Read and parse .env file
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Skip lines without =
            if (strpos($line, '=') === false) {
                continue;
            }
            
            // Parse key=value
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, ' "\'');
            
            // Set environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
        
        self::$envLoaded = true;
        return true;
    }
    
    /**
     * Get environment variable with optional default
     */
    public static function get($key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        return $value;
    }
    
    /**
     * Validate required environment variables
     */
    public static function validate() {
        $required = [
            'DB_SERVER',
            'DB_USERNAME',
            'DB_NAME',
            'APP_ENV'
        ];
        
        $missing = [];
        foreach ($required as $var) {
            if (getenv($var) === false) {
                $missing[] = $var;
            }
        }
        
        if (!empty($missing)) {
            self::$errors[] = "Missing required environment variables: " . implode(', ', $missing);
            return false;
        }
        
        // Validate APP_ENV value
        $appEnv = getenv('APP_ENV');
        if (!in_array($appEnv, ['development', 'production'])) {
            self::$errors[] = "Invalid APP_ENV value. Must be 'development' or 'production'";
            return false;
        }
        
        return true;
    }
    
    /**
     * Get validation errors
     */
    public static function getErrors() {
        return self::$errors;
    }
    
    /**
     * Get all loaded environment variables
     */
    public static function getAll() {
        return $_ENV ?: [];
    }
}

// Auto-load on include
EnvLoader::load();
?>
