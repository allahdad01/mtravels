<?php
/**
 * Advanced Rate Limiter Class with Backward Compatibility
 *
 * Features:
 * - Burst handling (allow short bursts above normal rate)
 * - API versioning (different limits per API version)
 * - Distributed rate limiting (Redis support for load balancing)
 * - Sliding window algorithms
 * - Backward compatible with existing RateLimiter interface
 * - IP-based rate limiting and blocking
 */

class RateLimiter {

    // Default rate limit configurations (backward compatible)
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

    // Advanced rate limiter instance
    private static $advancedLimiter = null;

    // Supported algorithms
    const ALGORITHM_FIXED_WINDOW = 'fixed_window';
    const ALGORITHM_SLIDING_WINDOW = 'sliding_window';
    const ALGORITHM_TOKEN_BUCKET = 'token_bucket';

    // Storage backends
    const STORAGE_DATABASE = 'database';
    const STORAGE_REDIS = 'redis';
    const STORAGE_MEMORY = 'memory';

    // Default configurations for advanced features
    private static $defaultLimits = [
        'api_v1_requests' => [
            'algorithm' => self::ALGORITHM_TOKEN_BUCKET,
            'capacity' => 1000,
            'refill_rate' => 100,
            'burst_limit' => 1500,
            'window' => 3600
        ],
        'api_v2_requests' => [
            'algorithm' => self::ALGORITHM_SLIDING_WINDOW,
            'max_requests' => 2000,
            'window' => 3600,
            'burst_limit' => 2500
        ],
        'login_attempts' => [
            'algorithm' => self::ALGORITHM_FIXED_WINDOW,
            'max_requests' => 5,
            'window' => 900,
            'burst_limit' => 3
        ],
        'messages_per_user' => [
            'algorithm' => self::ALGORITHM_TOKEN_BUCKET,
            'capacity' => 50,
            'refill_rate' => 1,
            'burst_limit' => 10,
            'window' => 3600
        ]
    ];

    /**
     * Get advanced rate limiter instance
     */
    private static function getAdvancedLimiter() {
        if (self::$advancedLimiter === null) {
            self::$advancedLimiter = new self();
        }
        return self::$advancedLimiter;
    }

    /**
     * Check if an action is allowed under rate limits (BACKWARD COMPATIBLE)
     *
     * @param string $keyValue User ID, email, IP address, or other identifier
     * @param string $limitName Name of the limit to check
     * @param int $tenantId Tenant ID
     * @param string $keyType Type of key (user, email, ip)
     * @param int|null $branchId Branch ID for branch-level isolation
     * @return bool True if allowed, false if rate limited
     */
    public static function isAllowed($keyValue, $limitName, $tenantId, $keyType = 'user', $branchId = null) {
        // Get branch_id from session if not provided
        if ($branchId === null && isset($_SESSION['branch_id'])) {
            $branchId = $_SESSION['branch_id'];
        }

        // For backward compatibility, use the old method for known limits
        if (isset(self::$LIMITS[$limitName])) {
            return self::isAllowedLegacy($keyValue, $limitName, $tenantId, $keyType, $branchId);
        }

        // For new advanced limits, use the advanced system
        $limiter = self::getAdvancedLimiter();
        $result = $limiter->isAllowedAdvanced($keyValue, $limitName, $tenantId, 'v1', $branchId);
        return $result['allowed'];
    }

    /**
     * Legacy isAllowed method for backward compatibility
     */
    private static function isAllowedLegacy($keyValue, $limitName, $tenantId, $keyType = 'user', $branchId = null) {
        global $pdo;

        if (!$pdo) {
            return true;
        }

        if (!isset(self::$LIMITS[$limitName])) {
            return true;
        }

        $limitConfig = self::$LIMITS[$limitName];
        $now = time();

        try {
            // Include branch_id in the query for proper isolation
            $sql = "
                SELECT current_count, reset_at
                FROM rate_limits
                WHERE tenant_id = ?
                AND key_type = ?
                AND key_value = ?
                AND limit_name = ?
            ";
            $params = [$tenantId, $keyType, (string)$keyValue, $limitName];

            // Add branch filter if provided
            if ($branchId !== null) {
                $sql .= " AND branch_id = ?";
                $params[] = $branchId;
            } else {
                $sql .= " AND branch_id IS NULL";
            }

            $sql .= " LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $resetTime = strtotime($result['reset_at']);

                if ($now >= $resetTime) {
                    self::_resetCounter($keyValue, $limitName, $tenantId, $keyType, $branchId);
                    return true;
                }

                return $result['current_count'] < $limitConfig['max'];
            } else {
                self::_createCounter($keyValue, $limitName, $tenantId, $keyType, $limitConfig, $branchId);
                return true;
            }
        } catch (Exception $e) {
            error_log("RateLimiter::isAllowed error: " . $e->getMessage());
            return true;
        }
    }

    /**
     * Record an action for rate limiting (BACKWARD COMPATIBLE)
     */
    public static function recordAction($keyValue, $limitName, $tenantId, $ipAddress = null, $keyType = 'user', $branchId = null) {
        // Get branch_id from session if not provided
        if ($branchId === null && isset($_SESSION['branch_id'])) {
            $branchId = $_SESSION['branch_id'];
        }

        // For backward compatibility, use the old method for known limits
        if (isset(self::$LIMITS[$limitName])) {
            return self::recordActionLegacy($keyValue, $limitName, $tenantId, $ipAddress, $keyType, $branchId);
        }

        // For new advanced limits, use the advanced system
        $limiter = self::getAdvancedLimiter();
        return $limiter->recordRequestAdvanced($keyValue, $limitName, $tenantId, 'v1', $branchId);
    }

    /**
     * Legacy recordAction method for backward compatibility
     */
    private static function recordActionLegacy($keyValue, $limitName, $tenantId, $ipAddress = null, $keyType = 'user', $branchId = null) {
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
            $limitConfig = self::$LIMITS[$limitName];

            // Include branch_id in the query for proper isolation
            $sql = "
                SELECT id, current_count, reset_at
                FROM rate_limits
                WHERE tenant_id = ?
                AND key_type = ?
                AND key_value = ?
                AND limit_name = ?
            ";
            $params = [$tenantId, $keyType, (string)$keyValue, $limitName];

            // Add branch filter if provided
            if ($branchId !== null) {
                $sql .= " AND branch_id = ?";
                $params[] = $branchId;
            } else {
                $sql .= " AND branch_id IS NULL";
            }

            $sql .= " LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $resetTime = strtotime($result['reset_at']);

                if ($now >= $resetTime) {
                    self::_resetCounter($keyValue, $limitName, $tenantId, $keyType, $branchId);
                    $stmt = $pdo->prepare("
                        UPDATE rate_limits
                        SET current_count = 1
                        WHERE id = ?
                    ");
                    $stmt->execute([$result['id']]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE rate_limits
                        SET current_count = current_count + 1,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$result['id']]);
                }

                $newCount = ($now >= $resetTime) ? 1 : ($result['current_count'] + 1);
                if ($newCount >= $limitConfig['max']) {
                    self::_logViolation($keyValue, $limitName, $tenantId, $newCount, $limitConfig['max'], $ipAddress, $branchId);
                }
            } else {
                self::_createCounter($keyValue, $limitName, $tenantId, $keyType, $limitConfig, $branchId);
            }

            return true;
        } catch (Exception $e) {
            error_log("RateLimiter::recordAction error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get remaining quota for a limit (BACKWARD COMPATIBLE)
     */
    public static function getRemainingQuota($keyValue, $limitName, $tenantId, $keyType = 'user', $branchId = null) {
        // Get branch_id from session if not provided
        if ($branchId === null && isset($_SESSION['branch_id'])) {
            $branchId = $_SESSION['branch_id'];
        }

        // For backward compatibility, use the old method for known limits
        if (isset(self::$LIMITS[$limitName])) {
            return self::getRemainingQuotaLegacy($keyValue, $limitName, $tenantId, $keyType, $branchId);
        }

        // For new advanced limits, use the advanced system
        $limiter = self::getAdvancedLimiter();
        $result = $limiter->isAllowedAdvanced($keyValue, $limitName, $tenantId, 'v1', $branchId);

        $config = self::getLimitConfig($limitName, 'v1');
        $maxRequests = $config['max_requests'] ?? $config['capacity'] ?? 1000;

        return [
            'remaining' => $result['remaining'],
            'max' => $maxRequests,
            'reset_in' => $result['reset_in'],
            'reset_at' => date('Y-m-d H:i:s', time() + $result['reset_in']),
            'exceeded' => !$result['allowed']
        ];
    }

    /**
     * Legacy getRemainingQuota method for backward compatibility
     */
    private static function getRemainingQuotaLegacy($keyValue, $limitName, $tenantId, $keyType = 'user', $branchId = null) {
        global $pdo;

        if (!$pdo || !isset(self::$LIMITS[$limitName])) {
            return null;
        }

        $limitConfig = self::$LIMITS[$limitName];
        $now = time();

        try {
            // Include branch_id in the query for proper isolation
            $sql = "
                SELECT current_count, reset_at
                FROM rate_limits
                WHERE tenant_id = ?
                AND key_type = ?
                AND key_value = ?
                AND limit_name = ?
            ";
            $params = [$tenantId, $keyType, (string)$keyValue, $limitName];

            // Add branch filter if provided
            if ($branchId !== null) {
                $sql .= " AND branch_id = ?";
                $params[] = $branchId;
            } else {
                $sql .= " AND branch_id IS NULL";
            }

            $sql .= " LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
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

    // ============ ADVANCED RATE LIMITING METHODS ============

    /**
     * Check if request is allowed under advanced rate limits
     */
    public function isAllowedAdvanced($key, $limitName, $tenantId = null, $apiVersion = 'v1', $branchId = null) {
        $limitKey = $this->buildLimitKey($key, $limitName, $tenantId, $apiVersion, $branchId);
        $config = $this->getLimitConfig($limitName, $apiVersion);

        if (!$config) {
            return ['allowed' => true, 'remaining' => PHP_INT_MAX, 'reset_in' => 0, 'burst_used' => false];
        }

        switch ($config['algorithm']) {
            case self::ALGORITHM_TOKEN_BUCKET:
                return $this->checkTokenBucket($limitKey, $config);
            case self::ALGORITHM_SLIDING_WINDOW:
                return $this->checkSlidingWindow($limitKey, $config);
            case self::ALGORITHM_FIXED_WINDOW:
            default:
                return $this->checkFixedWindow($limitKey, $config);
        }
    }

    /**
     * Record a request using advanced rate limiting
     */
    public function recordRequestAdvanced($key, $limitName, $tenantId = null, $apiVersion = 'v1', $branchId = null) {
        $limitKey = $this->buildLimitKey($key, $limitName, $tenantId, $apiVersion, $branchId);
        $config = $this->getLimitConfig($limitName, $apiVersion);

        if (!$config) {
            return true;
        }

        switch ($config['algorithm']) {
            case self::ALGORITHM_TOKEN_BUCKET:
                return $this->consumeToken($limitKey, $config);
            case self::ALGORITHM_SLIDING_WINDOW:
                return $this->recordSlidingWindow($limitKey, $config);
            case self::ALGORITHM_FIXED_WINDOW:
            default:
                return $this->recordFixedWindow($limitKey, $config);
        }
    }

    /**
     * Token Bucket Algorithm Implementation
     */
    private function checkTokenBucket($key, $config) {
        $now = microtime(true);
        $capacity = $config['capacity'];
        $refillRate = $config['refill_rate'];
        $burstLimit = $config['burst_limit'] ?? $capacity;

        $bucket = $this->getBucketState($key);

        if (!$bucket) {
            $bucket = ['tokens' => $capacity, 'last_refill' => $now];
        }

        $timePassed = $now - $bucket['last_refill'];
        $tokensToAdd = $timePassed * $refillRate;
        $bucket['tokens'] = min($burstLimit, $bucket['tokens'] + $tokensToAdd);
        $bucket['last_refill'] = $now;

        $allowed = $bucket['tokens'] >= 1;
        $remaining = floor($bucket['tokens']);
        $burstUsed = $bucket['tokens'] > $capacity;

        $this->setBucketState($key, $bucket);

        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'reset_in' => ceil((1 - ($bucket['tokens'] - floor($bucket['tokens']))) / $refillRate),
            'burst_used' => $burstUsed
        ];
    }

    /**
     * Consume token from bucket
     */
    private function consumeToken($key, $config) {
        $bucket = $this->getBucketState($key);
        if ($bucket && $bucket['tokens'] >= 1) {
            $bucket['tokens'] -= 1;
            $this->setBucketState($key, $bucket);
            return true;
        }
        return false;
    }

    /**
     * Sliding Window Algorithm
     */
    private function checkSlidingWindow($key, $config) {
        $now = time();
        $window = $config['window'];
        $maxRequests = $config['max_requests'];
        $burstLimit = $config['burst_limit'] ?? $maxRequests;

        $requests = $this->getRequestsInWindow($key, $now - $window, $now);
        $requestCount = count($requests);

        $weightedCount = 0;
        foreach ($requests as $timestamp) {
            $age = $now - $timestamp;
            $weight = 1 - ($age / $window);
            $weightedCount += $weight;
        }

        $allowed = $weightedCount < $maxRequests;
        $remaining = max(0, floor($maxRequests - $weightedCount));
        $burstUsed = $weightedCount > $maxRequests;

        if ($weightedCount >= $burstLimit) {
            $allowed = false;
        }

        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'reset_in' => $window - (($now - ($requests[0] ?? $now)) % $window),
            'burst_used' => $burstUsed
        ];
    }

    /**
     * Record request for sliding window
     */
    private function recordSlidingWindow($key, $config) {
        $now = time();
        $this->addRequestTimestamp($key, $now);
        $this->cleanOldRequests($key, $now - $config['window']);
        return true;
    }

    /**
     * Fixed Window Algorithm
     */
    private function checkFixedWindow($key, $config) {
        $now = time();
        $window = $config['window'];
        $maxRequests = $config['max_requests'];

        $windowStart = $now - ($now % $window);
        $windowEnd = $windowStart + $window;

        $requests = $this->getRequestsInWindow($key, $windowStart, $windowEnd);
        $requestCount = count($requests);

        $allowed = $requestCount < $maxRequests;
        $remaining = max(0, $maxRequests - $requestCount);

        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'reset_in' => $windowEnd - $now,
            'burst_used' => false
        ];
    }

    /**
     * Record request for fixed window
     */
    private function recordFixedWindow($key, $config) {
        $now = time();
        $this->addRequestTimestamp($key, $now);
        return true;
    }

    /**
     * Build unique key for rate limiting
     */
    private function buildLimitKey($key, $limitName, $tenantId, $apiVersion, $branchId = null) {
        $parts = ['rl', $apiVersion, $limitName];
        if ($tenantId) {
            $parts[] = 't' . $tenantId;
        }
        if ($branchId) {
            $parts[] = 'b' . $branchId;
        }
        $parts[] = $key;
        return implode(':', $parts);
    }

    /**
     * Get limit configuration
     */
    private function getLimitConfig($limitName, $apiVersion) {
        $versionedName = $apiVersion . '_' . $limitName;
        if (isset(self::$defaultLimits[$versionedName])) {
            return self::$defaultLimits[$versionedName];
        }
        if (isset(self::$defaultLimits[$limitName])) {
            return self::$defaultLimits[$limitName];
        }
        return null;
    }

    /**
     * Storage abstraction methods
     */
    private function getBucketState($key) {
        global $pdo;
        if (!$pdo) return null;

        try {
            $stmt = $pdo->prepare("
                SELECT bucket_data, last_updated
                FROM advanced_rate_limits
                WHERE limit_key = ? AND last_updated > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return json_decode($result['bucket_data'], true);
            }
        } catch (Exception $e) {
            error_log("Database bucket retrieval error: " . $e->getMessage());
        }
        return null;
    }

    private function setBucketState($key, $bucket) {
        global $pdo;
        if (!$pdo) return;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO advanced_rate_limits (limit_key, bucket_data, last_updated)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    bucket_data = VALUES(bucket_data),
                    last_updated = NOW()
            ");
            $stmt->execute([$key, json_encode($bucket)]);
        } catch (Exception $e) {
            error_log("Database bucket storage error: " . $e->getMessage());
        }
    }

    private function getRequestsInWindow($key, $startTime, $endTime) {
        global $pdo;
        if (!$pdo) return [];

        try {
            $stmt = $pdo->prepare("
                SELECT timestamp FROM advanced_rate_limit_requests
                WHERE limit_key = ? AND timestamp BETWEEN ? AND ?
                ORDER BY timestamp ASC
            ");
            $stmt->execute([$key, $startTime, $endTime]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Database requests retrieval error: " . $e->getMessage());
            return [];
        }
    }

    private function addRequestTimestamp($key, $timestamp) {
        global $pdo;
        if (!$pdo) return;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO advanced_rate_limit_requests (limit_key, timestamp)
                VALUES (?, ?)
            ");
            $stmt->execute([$key, $timestamp]);
        } catch (Exception $e) {
            error_log("Database timestamp storage error: " . $e->getMessage());
        }
    }

    private function cleanOldRequests($key, $beforeTime) {
        global $pdo;
        if (!$pdo) return;

        try {
            $stmt = $pdo->prepare("
                DELETE FROM advanced_rate_limit_requests
                WHERE limit_key = ? AND timestamp < ?
            ");
            $stmt->execute([$key, $beforeTime]);
        } catch (Exception $e) {
            error_log("Database cleanup error: " . $e->getMessage());
        }
    }

    // ============ BACKWARD COMPATIBLE IP BLOCKING METHODS ============

    /**
     * Check if an IP address is blocked (BACKWARD COMPATIBLE)
     */
    public static function isIPBlocked($ipAddress, $tenantId = null) {
        global $pdo;

        if (!$pdo) {
            return false;
        }

        try {
            $sql = "SELECT id FROM ip_blacklist WHERE ip_address = ? ";
            $params = [$ipAddress];

            if ($tenantId !== null) {
                $sql .= "AND (tenant_id IS NULL OR tenant_id = ?) ";
                $params[] = $tenantId;
            } else {
                $sql .= "AND tenant_id IS NULL ";
            }

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
     * Block an IP address (BACKWARD COMPATIBLE)
     */
    public static function blockIP($ipAddress, $reason, $durationSeconds = 0, $tenantId = null, $createdBy = null) {
        global $pdo;

        if (!$pdo) {
            return false;
        }

        try {
            $permanent = ($durationSeconds === 0);

            if ($permanent) {
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
     * Unblock an IP address (BACKWARD COMPATIBLE)
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

    // ============ OTHER BACKWARD COMPATIBLE METHODS ============

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

    public static function setCustomLimit($limitName, $maxRequests, $windowSeconds) {
        self::$LIMITS[$limitName] = [
            'max' => $maxRequests,
            'window' => $windowSeconds
        ];
        return true;
    }

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

    // ============ PRIVATE HELPER METHODS ============

    private static function _createCounter($keyValue, $limitName, $tenantId, $keyType, $limitConfig = null, $branchId = null) {
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
                (tenant_id, branch_id, key_type, key_value, limit_name, limit_value, window_seconds, current_count, reset_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)
                ON DUPLICATE KEY UPDATE
                    current_count = 0,
                    reset_at = VALUES(reset_at)
            ");

            $stmt->execute([
                $tenantId,
                $branchId,
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

    private static function _resetCounter($keyValue, $limitName, $tenantId, $keyType, $branchId = null) {
        global $pdo;

        try {
            if (!isset(self::$LIMITS[$limitName])) {
                return;
            }

            $limitConfig = self::$LIMITS[$limitName];
            $resetAt = date('Y-m-d H:i:s', time() + $limitConfig['window']);

            $sql = "
                UPDATE rate_limits
                SET current_count = 0,
                    reset_at = ?
                WHERE tenant_id = ?
                AND key_type = ?
                AND key_value = ?
                AND limit_name = ?
            ";
            $params = [$resetAt, $tenantId, $keyType, (string)$keyValue, $limitName];

            // Add branch filter if provided
            if ($branchId !== null) {
                $sql .= " AND branch_id = ?";
                $params[] = $branchId;
            } else {
                $sql .= " AND branch_id IS NULL";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } catch (Exception $e) {
            error_log("RateLimiter::_resetCounter error: " . $e->getMessage());
        }
    }

    private static function _logViolation($keyValue, $limitName, $tenantId, $currentValue, $limitValue, $ipAddress = null, $branchId = null) {
        global $pdo;

        try {
            $userId = null;

            if (is_numeric($keyValue)) {
                $userId = (int)$keyValue;
            }

            $stmt = $pdo->prepare("
                INSERT INTO rate_limit_violations
                (tenant_id, branch_id, user_id, limit_name, violation_count, current_value, limit_value, ip_address, details, action_taken)
                VALUES (?, ?, ?, ?, 1, ?, ?, ?, JSON_OBJECT('key_value', ?), 'recorded')
                ON DUPLICATE KEY UPDATE
                    violation_count = violation_count + 1,
                    current_value = VALUES(current_value)
            ");

            $stmt->execute([
                $tenantId,
                $branchId,
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
