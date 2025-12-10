<?php
/**
 * Rate Limiter Class
 * 
 * Handles all rate limiting functionality:
 * - Message rate limiting
 * - Contact discovery rate limiting
 * - Login attempt rate limiting
 * - API request rate limiting
 * - IP-based rate limiting and blocking
 */

class RateLimiter {
    
    // Default rate limit configurations
    public static $LIMITS = [
        'messages_per_hour' => ['max' => 50, 'window' => 3600],
        'messages_per_day' => ['max' => 100, 'window' => 86400],
        'messages_per_minute_per_user' => ['max' => 10, 'window' => 60],
        'contact_discovery_per_hour' => ['max' => 20, 'window' => 3600],
        'failed_searches_per_hour' => ['max' => 10, 'window' => 3600],
        'login_attempts_per_15min' => ['max' => 5, 'window' => 900],
        'otp_attempts_per_5min' => ['max' => 3, 'window' => 300],
        'api_requests_per_minute' => ['max' => 100, 'window' => 60],
        'api_requests_per_hour' => ['max' => 1000, 'window' => 3600],
    ];
    
    /**
     * Check if an action is allowed under rate limits
     * 
     * @param string $keyValue User ID, email, IP address, or other identifier
     * @param string $limitName Name of the limit to check
     * @param int $tenantId Tenant ID
     * @param string $keyType Type of key (user, email, ip)
     * @return bool True if allowed, false if rate limited
     */
    public static function isAllowed($keyValue, $limitName, $tenantId, $keyType = 'user') {
        global $pdo;
        
        if (!$pdo) {
            return true; // Allow if no database
        }
        
        // Get limit config
        if (!isset(self::$LIMITS[$limitName])) {
            return true; // Unknown limit, allow
        }
        
        $limitConfig = self::$LIMITS[$limitName];
        $now = time();
        
        try {
            // Check or create rate limit record
            $stmt = $pdo->prepare("
                SELECT current_count, reset_at 
                FROM rate_limits 
                WHERE tenant_id = ? 
                AND key_type = ? 
                AND key_value = ? 
                AND limit_name = ?
                LIMIT 1
            ");
            
            $stmt->execute([$tenantId, $keyType, (string)$keyValue, $limitName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $resetTime = strtotime($result['reset_at']);
                
                // Check if window has expired
                if ($now >= $resetTime) {
                    // Reset the counter
                    self::_resetCounter($keyValue, $limitName, $tenantId, $keyType);
                    return true;
                }
                
                // Check if limit exceeded
                return $result['current_count'] < $limitConfig['max'];
            } else {
                // No record yet, create one
                self::_createCounter($keyValue, $limitName, $tenantId, $keyType, $limitConfig);
                return true;
            }
        } catch (Exception $e) {
            error_log("RateLimiter::isAllowed error: " . $e->getMessage());
            return true; // Allow on error
        }
    }
    
    /**
     * Record an action for rate limiting
     * 
     * @param string $keyValue User ID, email, IP address, or other identifier
     * @param string $limitName Name of the limit
     * @param int $tenantId Tenant ID
     * @param string $ipAddress IP address of the request
     * @param string $keyType Type of key (user, email, ip)
     * @return bool True if recorded successfully
     */
    public static function recordAction($keyValue, $limitName, $tenantId, $ipAddress = null, $keyType = 'user') {
        global $pdo;
        
        if (!$pdo) {
            return false;
        }
        
        if (!isset(self::$LIMITS[$limitName])) {
            return false;
        }
        
        $ipAddress = $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? null;
        $now = time();
        
        try {
            // Get limit config
            $limitConfig = self::$LIMITS[$limitName];
            
            // Get current record
            $stmt = $pdo->prepare("
                SELECT id, current_count, reset_at 
                FROM rate_limits 
                WHERE tenant_id = ? 
                AND key_type = ? 
                AND key_value = ? 
                AND limit_name = ?
                LIMIT 1
            ");
            
            $stmt->execute([$tenantId, $keyType, (string)$keyValue, $limitName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $resetTime = strtotime($result['reset_at']);
                
                // Check if window has expired
                if ($now >= $resetTime) {
                    // Reset the counter
                    self::_resetCounter($keyValue, $limitName, $tenantId, $keyType);
                    // Increment by 1
                    $stmt = $pdo->prepare("
                        UPDATE rate_limits 
                        SET current_count = 1
                        WHERE id = ?
                    ");
                    $stmt->execute([$result['id']]);
                } else {
                    // Increment counter
                    $stmt = $pdo->prepare("
                        UPDATE rate_limits 
                        SET current_count = current_count + 1,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$result['id']]);
                }
                
                // Log violation if limit exceeded
                $newCount = ($now >= $resetTime) ? 1 : ($result['current_count'] + 1);
                if ($newCount >= $limitConfig['max']) {
                    self::_logViolation($keyValue, $limitName, $tenantId, $newCount, $limitConfig['max'], $ipAddress);
                }
            } else {
                // Create new record
                self::_createCounter($keyValue, $limitName, $tenantId, $keyType, $limitConfig);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("RateLimiter::recordAction error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get remaining quota for a limit
     * 
     * @param string $keyValue User ID, email, IP address, or other identifier
     * @param string $limitName Name of the limit
     * @param int $tenantId Tenant ID
     * @param string $keyType Type of key (user, email, ip)
     * @return array|null Remaining quota info or null
     */
    public static function getRemainingQuota($keyValue, $limitName, $tenantId, $keyType = 'user') {
        global $pdo;
        
        if (!$pdo || !isset(self::$LIMITS[$limitName])) {
            return null;
        }
        
        $limitConfig = self::$LIMITS[$limitName];
        $now = time();
        
        try {
            $stmt = $pdo->prepare("
                SELECT current_count, reset_at 
                FROM rate_limits 
                WHERE tenant_id = ? 
                AND key_type = ? 
                AND key_value = ? 
                AND limit_name = ?
                LIMIT 1
            ");
            
            $stmt->execute([$tenantId, $keyType, (string)$keyValue, $limitName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                return [
                    'remaining' => $limitConfig['max'],
                    'max' => $limitConfig['max'],
                    'reset_in' => $limitConfig['window'],
                    'reset_at' => date('Y-m-d H:i:s', $now + $limitConfig['window'])
                ];
            }
            
            $resetTime = strtotime($result['reset_at']);
            
            if ($now >= $resetTime) {
                // Window has expired
                return [
                    'remaining' => $limitConfig['max'],
                    'max' => $limitConfig['max'],
                    'reset_in' => $limitConfig['window'],
                    'reset_at' => date('Y-m-d H:i:s', $now + $limitConfig['window']),
                    'exceeded' => false
                ];
            }
            
            $remaining = $limitConfig['max'] - $result['current_count'];
            
            return [
                'remaining' => max(0, $remaining),
                'max' => $limitConfig['max'],
                'reset_in' => $resetTime - $now,
                'reset_at' => date('Y-m-d H:i:s', $resetTime),
                'exceeded' => $remaining <= 0
            ];
        } catch (Exception $e) {
            error_log("RateLimiter::getRemainingQuota error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get rate limit status for a user
     * 
     * @param int $userId User ID
     * @param int $tenantId Tenant ID
     * @return array Status of all limits
     */
    public static function getStatus($userId, $tenantId) {
        $status = [];
        
        foreach (array_keys(self::$LIMITS) as $limitName) {
            $quota = self::getRemainingQuota($userId, $limitName, $tenantId);
            if ($quota) {
                $status[$limitName] = $quota;
            }
        }
        
        return $status;
    }
    
    /**
     * Check if an IP address is blocked
     * 
     * @param string $ipAddress IP address to check
     * @param int|null $tenantId Tenant ID (null for global)
     * @return bool True if blocked, false if allowed
     */
    public static function isIPBlocked($ipAddress, $tenantId = null) {
        global $pdo;
        
        if (!$pdo) {
            return false;
        }
        
        try {
            // Check for active blocks: permanent = 1 OR blocked_until is in the future
            // We compare with UTC_TIMESTAMP to avoid timezone issues
            $sql = "SELECT id FROM ip_blacklist WHERE ip_address = ? ";
            $params = [$ipAddress];
            
            // Add tenant filter
            if ($tenantId !== null) {
                $sql .= "AND (tenant_id IS NULL OR tenant_id = ?) ";
                $params[] = $tenantId;
            } else {
                $sql .= "AND tenant_id IS NULL ";
            }
            
            // Add active block check: either permanent OR blocked_until hasn't passed
            // Use IFNULL to handle both permanent and temporary blocks
            $sql .= "AND (permanent = 1 OR (permanent = 0 AND blocked_until IS NOT NULL AND blocked_until > UTC_TIMESTAMP())) LIMIT 1";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("RateLimiter::isIPBlocked error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Block an IP address
     * 
     * @param string $ipAddress IP address to block
     * @param string $reason Reason for blocking
     * @param int $durationSeconds Duration in seconds (0 for permanent)
     * @param int|null $tenantId Tenant ID (null for global)
     * @param int|null $createdBy User ID of admin
     * @return bool Success
     */
    public static function blockIP($ipAddress, $reason, $durationSeconds = 0, $tenantId = null, $createdBy = null) {
        global $pdo;
        
        if (!$pdo) {
            return false;
        }
        
        try {
            $permanent = ($durationSeconds === 0);
            
            // For temporary blocks, calculate blocked_until using SQL to ensure consistency
            if ($permanent) {
                // Permanent block: blocked_until stays NULL
                $stmt = $pdo->prepare("
                    INSERT INTO ip_blacklist (ip_address, tenant_id, reason, blocked_until, permanent, created_by)
                    VALUES (?, ?, ?, NULL, 1, ?)
                    ON DUPLICATE KEY UPDATE
                        reason = VALUES(reason),
                        blocked_until = NULL,
                        permanent = 1,
                        blocked_at = NOW()
                ");
                $stmt->execute([$ipAddress, $tenantId, $reason, $createdBy]);
            } else {
                // Temporary block: use DATE_ADD to set blocked_until relative to NOW()
                $stmt = $pdo->prepare("
                    INSERT INTO ip_blacklist (ip_address, tenant_id, reason, blocked_until, permanent, created_by)
                    VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), 0, ?)
                    ON DUPLICATE KEY UPDATE
                        reason = VALUES(reason),
                        blocked_until = DATE_ADD(NOW(), INTERVAL ? SECOND),
                        permanent = 0,
                        blocked_at = NOW()
                ");
                $stmt->execute([$ipAddress, $tenantId, $reason, $durationSeconds, $createdBy, $durationSeconds]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("RateLimiter::blockIP error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Unblock an IP address
     * 
     * @param string $ipAddress IP address to unblock
     * @param int|null $tenantId Tenant ID
     * @return bool Success
     */
    public static function unblockIP($ipAddress, $tenantId = null) {
        global $pdo;
        
        if (!$pdo) {
            return false;
        }
        
        try {
            $stmt = $pdo->prepare("
                DELETE FROM ip_blacklist 
                WHERE ip_address = ? 
                AND (tenant_id IS NULL OR tenant_id = ?)
            ");
            
            $stmt->execute([$ipAddress, $tenantId]);
            return true;
        } catch (Exception $e) {
            error_log("RateLimiter::unblockIP error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get violation history for a user
     * 
     * @param int $userId User ID
     * @param int $tenantId Tenant ID
     * @param int $limit Number of records to return
     * @return array Violation records
     */
    public static function getViolations($userId, $tenantId, $limit = 50) {
        global $pdo;
        
        if (!$pdo) {
            return [];
        }
        
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM rate_limit_violations 
                WHERE user_id = ? AND tenant_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$userId, $tenantId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("RateLimiter::getViolations error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Set a custom rate limit
     * 
     * @param string $limitName Name of the limit
     * @param int $maxRequests Maximum requests allowed
     * @param int $windowSeconds Time window in seconds
     * @return bool Success
     */
    public static function setCustomLimit($limitName, $maxRequests, $windowSeconds) {
        self::$LIMITS[$limitName] = [
            'max' => $maxRequests,
            'window' => $windowSeconds
        ];
        return true;
    }
    
    /**
     * Clean up old rate limit records
     * Removes records older than 90 days
     * 
     * @param int $daysOld Number of days to keep (default 90)
     * @return int Number of records deleted
     */
    public static function cleanup($daysOld = 90) {
        global $pdo;
        
        if (!$pdo) {
            return 0;
        }
        
        try {
            $cutoffDate = date('Y-m-d H:i:s', time() - ($daysOld * 86400));
            
            $stmt = $pdo->prepare("
                DELETE FROM rate_limit_violations 
                WHERE created_at < ?
            ");
            
            $stmt->execute([$cutoffDate]);
            $violationDeleted = $stmt->rowCount();
            
            // Also clean up expired rate_limits records
            $stmt = $pdo->prepare("
                DELETE FROM rate_limits 
                WHERE reset_at < NOW()
                AND updated_at < ?
            ");
            
            $stmt->execute([$cutoffDate]);
            $limitsDeleted = $stmt->rowCount();
            
            return $violationDeleted + $limitsDeleted;
        } catch (Exception $e) {
            error_log("RateLimiter::cleanup error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get list of blocked IPs
     * 
     * @param int|null $tenantId Tenant ID
     * @param int $limit Number of records
     * @return array Blocked IP records
     */
    public static function getBlockedIPs($tenantId = null, $limit = 100) {
        global $pdo;
        
        if (!$pdo) {
            return [];
        }
        
        try {
            $sql = "SELECT * FROM ip_blacklist WHERE (tenant_id IS NULL OR tenant_id = ?)";
            $params = [$tenantId];
            
            if ($tenantId === null) {
                $sql = "SELECT * FROM ip_blacklist";
                $params = [];
            }
            
            $sql .= " ORDER BY blocked_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("RateLimiter::getBlockedIPs error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Clean up expired IP blocks
     * 
     * @return int Number of unblocked IPs
     */
    public static function cleanupExpiredBlocks() {
        global $pdo;
        
        if (!$pdo) {
            return 0;
        }
        
        try {
            $stmt = $pdo->prepare("
                DELETE FROM ip_blacklist 
                WHERE permanent = 0 
                AND blocked_until IS NOT NULL 
                AND blocked_until < NOW()
            ");
            
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("RateLimiter::cleanupExpiredBlocks error: " . $e->getMessage());
            return 0;
        }
    }
    
    // ============ Private Helper Methods ============
    
    /**
     * Create a new rate limit counter
     */
    private static function _createCounter($keyValue, $limitName, $tenantId, $keyType, $limitConfig = null) {
        global $pdo;
        
        try {
            if ($limitConfig === null && isset(self::$LIMITS[$limitName])) {
                $limitConfig = self::$LIMITS[$limitName];
            }
            
            if (!$limitConfig) {
                return;
            }
            
            $resetAt = date('Y-m-d H:i:s', time() + $limitConfig['window']);
            
            $stmt = $pdo->prepare("
                INSERT INTO rate_limits 
                (tenant_id, key_type, key_value, limit_name, limit_value, window_seconds, current_count, reset_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, ?)
                ON DUPLICATE KEY UPDATE
                    current_count = 0,
                    reset_at = VALUES(reset_at)
            ");
            
            $stmt->execute([
                $tenantId,
                $keyType,
                (string)$keyValue,
                $limitName,
                $limitConfig['max'],
                $limitConfig['window'],
                $resetAt
            ]);
        } catch (Exception $e) {
            error_log("RateLimiter::_createCounter error: " . $e->getMessage());
        }
    }
    
    /**
     * Reset a rate limit counter
     */
    private static function _resetCounter($keyValue, $limitName, $tenantId, $keyType) {
        global $pdo;
        
        try {
            if (!isset(self::$LIMITS[$limitName])) {
                return;
            }
            
            $limitConfig = self::$LIMITS[$limitName];
            $resetAt = date('Y-m-d H:i:s', time() + $limitConfig['window']);
            
            $stmt = $pdo->prepare("
                UPDATE rate_limits 
                SET current_count = 0, 
                    reset_at = ?
                WHERE tenant_id = ? 
                AND key_type = ? 
                AND key_value = ? 
                AND limit_name = ?
            ");
            
            $stmt->execute([$resetAt, $tenantId, $keyType, (string)$keyValue, $limitName]);
        } catch (Exception $e) {
            error_log("RateLimiter::_resetCounter error: " . $e->getMessage());
        }
    }
    
    /**
     * Log a rate limit violation
     */
    private static function _logViolation($keyValue, $limitName, $tenantId, $currentValue, $limitValue, $ipAddress = null) {
        global $pdo;
        
        try {
            $userId = null;
            
            // If keyValue is numeric, assume it's a user ID
            if (is_numeric($keyValue)) {
                $userId = (int)$keyValue;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO rate_limit_violations 
                (tenant_id, user_id, limit_name, violation_count, current_value, limit_value, ip_address, details, action_taken)
                VALUES (?, ?, ?, 1, ?, ?, ?, JSON_OBJECT('key_value', ?), 'recorded')
                ON DUPLICATE KEY UPDATE
                    violation_count = violation_count + 1,
                    current_value = VALUES(current_value)
            ");
            
            $stmt->execute([
                $tenantId,
                $userId,
                $limitName,
                $currentValue,
                $limitValue,
                $ipAddress,
                (string)$keyValue
            ]);
        } catch (Exception $e) {
            error_log("RateLimiter::_logViolation error: " . $e->getMessage());
        }
    }
}
?>
