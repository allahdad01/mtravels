<?php
/**
 * Rate Limiting Module
 * 
 * Provides simple rate limiting to prevent brute force and DoS attacks
 */

/**
 * Check if an action has exceeded rate limit
 * 
 * @param string $key Unique key for rate limiting (e.g., 'login_ip_192.168.1.1')
 * @param int $max_attempts Maximum number of attempts allowed
 * @param int $time_window Time window in seconds (e.g., 3600 for 1 hour)
 * @return bool True if within limits, false if exceeded
 */
function checkRateLimit($key, $max_attempts, $time_window) {
    $cache_dir = __DIR__ . '/../../cache/rate_limit';
    
    // Create cache directory if needed
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }
    
    // Use hash for file name to avoid path issues
    $cache_file = $cache_dir . '/' . md5($key) . '.json';
    $now = time();
    
    // Get existing attempt data
    $data = [];
    if (file_exists($cache_file)) {
        $json = @file_get_contents($cache_file);
        if ($json) {
            $data = json_decode($json, true) ?? [];
        }
    }
    
    // Check if cache has expired
    if (isset($data['expire']) && $data['expire'] <= $now) {
        // Cache expired, reset
        $data = [];
    }
    
    // Get attempts array
    $attempts = $data['attempts'] ?? [];
    
    // Clean old attempts outside window
    $attempts = array_filter($attempts, function($time) use ($now, $time_window) {
        return $time > ($now - $time_window);
    });
    
    // Record if limit exceeded
    $limit_exceeded = count($attempts) >= $max_attempts;
    
    // Add new attempt
    $attempts[] = $now;
    
    // Save updated attempt data
    $new_data = [
        'attempts' => array_values($attempts),  // Reset array keys
        'expire' => $now + $time_window
    ];
    
    @file_put_contents($cache_file, json_encode($new_data), LOCK_EX);
    
    return !$limit_exceeded;
}

/**
 * Get remaining attempts before rate limit
 * 
 * @param string $key Unique key for rate limiting
 * @param int $max_attempts Maximum number of attempts allowed
 * @param int $time_window Time window in seconds
 * @return int Number of remaining attempts (0 if exceeded)
 */
function getRemainingAttempts($key, $max_attempts, $time_window) {
    $cache_dir = __DIR__ . '/../../cache/rate_limit';
    $cache_file = $cache_dir . '/' . md5($key) . '.json';
    $now = time();
    
    if (!file_exists($cache_file)) {
        return $max_attempts;
    }
    
    $json = @file_get_contents($cache_file);
    if (!$json) {
        return $max_attempts;
    }
    
    $data = json_decode($json, true);
    if (!$data) {
        return $max_attempts;
    }
    
    // Check if cache has expired
    if (isset($data['expire']) && $data['expire'] <= $now) {
        return $max_attempts;
    }
    
    $attempts = $data['attempts'] ?? [];
    
    // Clean old attempts
    $valid_attempts = array_filter($attempts, function($time) use ($now, $time_window) {
        return $time > ($now - $time_window);
    });
    
    $count = count($valid_attempts);
    $remaining = max(0, $max_attempts - $count);
    
    return $remaining;
}

/**
 * Reset rate limit for a key
 * 
 * @param string $key Unique key to reset
 * @return bool True on success
 */
function resetRateLimit($key) {
    $cache_dir = __DIR__ . '/../../cache/rate_limit';
    $cache_file = $cache_dir . '/' . md5($key) . '.json';
    
    if (file_exists($cache_file)) {
        return @unlink($cache_file);
    }
    
    return true;
}

/**
 * Helper function to check rate limit and handle response
 * Useful for API endpoints
 * 
 * @param string $key Unique rate limit key
 * @param int $max_attempts Maximum attempts allowed
 * @param int $time_window Time window in seconds
 * @param string $error_message Optional custom error message
 * @return bool True if within limits, exits with 429 if exceeded
 */
function enforceRateLimit($key, $max_attempts, $time_window, $error_message = null) {
    if (!checkRateLimit($key, $max_attempts, $time_window)) {
        http_response_code(429);
        
        $remaining = getRemainingAttempts($key, $max_attempts, $time_window);
        $retry_after = $time_window;
        
        header("Retry-After: {$retry_after}");
        
        if ($error_message === null) {
            $error_message = "Too many requests. Please try again in {$retry_after} seconds.";
        }
        
        // Log rate limit violation
        error_log("RATE_LIMIT_EXCEEDED: Key={$key}, IP={$_SERVER['REMOTE_ADDR']}, User={$_SESSION['user_id'] ?? 'unknown'}");
        
        die(json_encode([
            'error' => 'Rate limit exceeded',
            'message' => $error_message,
            'retry_after' => $retry_after
        ]));
    }
    
    return true;
}

/**
 * Clean expired cache files
 * Should be called periodically (e.g., in cron job)
 * 
 * @return int Number of files deleted
 */
function cleanupExpiredRateLimits() {
    $cache_dir = __DIR__ . '/../../cache/rate_limit';
    
    if (!is_dir($cache_dir)) {
        return 0;
    }
    
    $deleted = 0;
    $now = time();
    
    $files = @scandir($cache_dir);
    if ($files === false) {
        return 0;
    }
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $file_path = $cache_dir . '/' . $file;
        
        if (!is_file($file_path)) {
            continue;
        }
        
        $json = @file_get_contents($file_path);
        if (!$json) {
            @unlink($file_path);
            $deleted++;
            continue;
        }
        
        $data = json_decode($json, true);
        if (!$data || !isset($data['expire']) || $data['expire'] <= $now) {
            @unlink($file_path);
            $deleted++;
        }
    }
    
    return $deleted;
}
?>
