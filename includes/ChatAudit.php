<?php

/**
 * ChatAudit Class
 * 
 * Handles all audit logging for chat operations.
 * Tracks: sends, reads, blocks, mutes, encryption, access attempts
 */

class ChatAudit {
    private static $pdo = null;

    /**
     * Get database connection
     */
    private static function getPDO() {
        if (self::$pdo === null) {
            global $pdo;
            self::$pdo = $pdo;
        }
        return self::$pdo;
    }

    /**
     * Get client IP address
     */
    private static function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        return trim($ip);
    }

    /**
     * Get user agent
     */
    private static function getUserAgent() {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
    }

    /**
     * Log a message send operation
     */
    public static function logSend($tenantId, $branchId, $userId, $toUserId, $messageId, $contentSize, $encrypted = false, $keyId = null) {
        $details = [
            'content_size' => $contentSize,
            'encrypted' => $encrypted,
            'encryption_key_id' => $keyId
        ];

        self::createLog([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'action' => 'send_message',
            'target_user_id' => $toUserId,
            'message_id' => $messageId,
            'details' => json_encode($details),
            'ip_address' => self::getClientIP(),
            'user_agent' => self::getUserAgent(),
            'status' => 'success'
        ]);
    }

    /**
     * Log a message read operation
     */
    public static function logRead($tenantId, $branchId, $userId, $messageId) {
        self::createLog([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'action' => 'read_message',
            'message_id' => $messageId,
            'ip_address' => self::getClientIP(),
            'user_agent' => self::getUserAgent(),
            'status' => 'success'
        ]);
    }

    /**
     * Log block/unblock action
     */
    public static function logBlock($tenantId, $branchId, $userId, $blockedUserId, $action) {
        // $action should be 'block' or 'unblock'
        self::createLog([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'action' => $action === 'unblock' ? 'unblock_user' : 'block_user',
            'target_user_id' => $blockedUserId,
            'ip_address' => self::getClientIP(),
            'user_agent' => self::getUserAgent(),
            'status' => 'success'
        ]);
    }

    /**
     * Log mute/unmute action
     */
    public static function logMute($tenantId, $branchId, $userId, $mutedUserId, $action) {
        // $action should be 'mute' or 'unmute'
        self::createLog([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'action' => $action === 'unmute' ? 'unmute_user' : 'mute_user',
            'target_user_id' => $mutedUserId,
            'ip_address' => self::getClientIP(),
            'user_agent' => self::getUserAgent(),
            'status' => 'success'
        ]);
    }

    /**
     * Log encryption operation
     */
    public static function logEncryption($tenantId, $userId, $messageId, $algorithm, $keyId, $contentSize, $encryptedSize, $success = true) {
        $details = [
            'algorithm' => $algorithm,
            'key_id' => $keyId,
            'content_size' => $contentSize,
            'encrypted_size' => $encryptedSize
        ];

        self::createLog([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => 'encrypt_message',
            'message_id' => $messageId,
            'details' => json_encode($details),
            'ip_address' => self::getClientIP(),
            'user_agent' => self::getUserAgent(),
            'status' => $success ? 'success' : 'failed'
        ]);
    }

    /**
     * Log decryption operation
     */
    public static function logDecryption($tenantId, $userId, $messageId, $success = true, $errorMsg = null) {
        self::createLog([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => 'decrypt_message',
            'message_id' => $messageId,
            'ip_address' => self::getClientIP(),
            'user_agent' => self::getUserAgent(),
            'status' => $success ? 'success' : 'failed',
            'error_message' => $errorMsg
        ]);
    }

    /**
     * Log failed/denied access attempt
     */
    public static function logFailedAccess($tenantId, $branchId, $userId, $targetUserId, $action, $reason, $errorMsg = null) {
        $details = [
            'reason' => $reason
        ];

        self::createLog([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'action' => $action,
            'target_user_id' => $targetUserId,
            'details' => json_encode($details),
            'ip_address' => self::getClientIP(),
            'user_agent' => self::getUserAgent(),
            'status' => 'denied',
            'error_message' => $errorMsg
        ]);
    }

    /**
     * Log settings change
     */
    public static function logSettingsChange($tenantId, $userId, $settingName, $oldValue, $newValue) {
        $details = [
            'setting' => $settingName,
            'old_value' => $oldValue,
            'new_value' => $newValue
        ];

        self::createLog([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => 'settings_change',
            'details' => json_encode($details),
            'ip_address' => self::getClientIP(),
            'user_agent' => self::getUserAgent(),
            'status' => 'success'
        ]);
    }

    /**
     * Create a log entry
     */
    private static function createLog($data) {
        $pdo = self::getPDO();
        
        $fields = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($fields), '?');

        $sql = "INSERT INTO chat_audit_log (" . implode(',', $fields) . ") 
                VALUES (" . implode(',', $placeholders) . ")";

        try {
            $stmt = $pdo->prepare($sql);
            if ($stmt) {
                return $stmt->execute($values);
            }
        } catch (Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
            return false;
        }

        return false;
    }

    /**
     * Get audit logs with filters
     */
    public static function getAuditLog($tenantId, $filters = []) {
        $pdo = self::getPDO();

        $sql = "SELECT * FROM chat_audit_log WHERE tenant_id = ?";
        $params = [$tenantId];

        // Filter by user
        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = ?";
            $params[] = $filters['user_id'];
        }

        // Filter by action
        if (!empty($filters['action'])) {
            $sql .= " AND action = ?";
            $params[] = $filters['action'];
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }

        // Date range
        if (!empty($filters['start_date'])) {
            $sql .= " AND created_at >= ?";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND created_at <= ?";
            $params[] = $filters['end_date'];
        }

        // Target user
        if (!empty($filters['target_user_id'])) {
            $sql .= " AND target_user_id = ?";
            $params[] = $filters['target_user_id'];
        }

        // Message ID
        if (!empty($filters['message_id'])) {
            $sql .= " AND message_id = ?";
            $params[] = $filters['message_id'];
        }

        // Order and limit
        $sql .= " ORDER BY created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = $filters['limit'];
        }

        try {
            $stmt = $pdo->prepare($sql);
            if ($stmt) {
                $stmt->execute($params);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            error_log("Audit query error: " . $e->getMessage());
            return [];
        }

        return [];
    }

    /**
     * Get audit log count
     */
    public static function getAuditLogCount($tenantId, $filters = []) {
        $pdo = self::getPDO();

        $sql = "SELECT COUNT(*) as count FROM chat_audit_log WHERE tenant_id = ?";
        $params = [$tenantId];

        // Apply same filters as getAuditLog
        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $sql .= " AND action = ?";
            $params[] = $filters['action'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND created_at >= ?";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND created_at <= ?";
            $params[] = $filters['end_date'];
        }

        try {
            $stmt = $pdo->prepare($sql);
            if ($stmt) {
                $stmt->execute($params);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result['count'] ?? 0;
            }
        } catch (Exception $e) {
            error_log("Audit count error: " . $e->getMessage());
            return 0;
        }

        return 0;
    }

    /**
     * Export audit logs (CSV)
     */
    public static function exportAuditLog($tenantId, $filters = [], $format = 'csv') {
        $logs = self::getAuditLog($tenantId, $filters);

        if ($format === 'csv') {
            return self::toCSV($logs);
        } elseif ($format === 'json') {
            return json_encode($logs, JSON_PRETTY_PRINT);
        }

        return null;
    }

    /**
     * Convert logs to CSV format
     */
    private static function toCSV($logs) {
        $csv = "ID,Tenant ID,User ID,Action,Target User ID,Message ID,Details,IP Address,Status,Error Message,Created At\n";

        foreach ($logs as $log) {
            $csv .= sprintf(
                "%d,%d,%d,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $log['id'] ?? '',
                $log['tenant_id'] ?? '',
                $log['user_id'] ?? '',
                $log['action'] ?? '',
                $log['target_user_id'] ?? '',
                $log['message_id'] ?? '',
                '"' . str_replace('"', '""', $log['details'] ?? '') . '"',
                $log['ip_address'] ?? '',
                $log['status'] ?? '',
                '"' . str_replace('"', '""', $log['error_message'] ?? '') . '"',
                $log['created_at'] ?? ''
            );
        }

        return $csv;
    }

    /**
     * Get summary statistics
     */
    public static function getSummary($tenantId, $days = 7) {
        $pdo = self::getPDO();
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $sql = "SELECT 
                    action,
                    status,
                    COUNT(*) as count
                FROM chat_audit_log 
                WHERE tenant_id = ? 
                AND created_at >= ?
                GROUP BY action, status
                ORDER BY count DESC";

        try {
            $stmt = $pdo->prepare($sql);
            if ($stmt) {
                $stmt->execute([$tenantId, $startDate]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            error_log("Audit summary error: " . $e->getMessage());
            return [];
        }

        return [];
    }

    /**
     * Get failed access attempts
     */
    public static function getFailedAttempts($tenantId, $filters = []) {
        $filters['status'] = 'denied';
        return self::getAuditLog($tenantId, $filters);
    }

    /**
     * Clean up old logs (archive after specified days)
     */
    public static function archiveOldLogs($tenantId, $daysToKeep = 90) {
        $pdo = self::getPDO();
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));

        $sql = "DELETE FROM chat_audit_log WHERE tenant_id = ? AND created_at < ?";

        try {
            $stmt = $pdo->prepare($sql);
            if ($stmt) {
                return $stmt->execute([$tenantId, $cutoffDate]);
            }
        } catch (Exception $e) {
            error_log("Archive error: " . $e->getMessage());
            return false;
        }

        return false;
    }
}

?>
