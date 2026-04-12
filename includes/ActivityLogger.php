<?php
/**
 * ActivityLogger - Comprehensive Audit Logging
 * 
 * Logs all critical operations for compliance (GDPR, PCI-DSS)
 * and security tracking.
 * 
 * Usage:
 *   ActivityLogger::log('user_created', $user_id, $tenant_id, ['name' => 'John Doe']);
 *   ActivityLogger::log('payment_received', $payment_id, $tenant_id, ['amount' => 1000]);
 *   ActivityLogger::log('settings_changed', $user_id, $tenant_id, ['old_value' => 'foo', 'new_value' => 'bar']);
 * 
 * @package MTravels
 * @author Security Team
 */

class ActivityLogger {
    
    /**
     * Log types for different critical operations
     */
    const LOG_TYPE_USER_CREATED = 'user_created';
    const LOG_TYPE_USER_UPDATED = 'user_updated';
    const LOG_TYPE_USER_DELETED = 'user_deleted';
    const LOG_TYPE_USER_LOCKED = 'user_locked';
    const LOG_TYPE_USER_UNLOCKED = 'user_unlocked';
    
    const LOG_TYPE_PAYMENT_RECEIVED = 'payment_received';
    const LOG_TYPE_PAYMENT_FAILED = 'payment_failed';
    const LOG_TYPE_PAYMENT_REFUNDED = 'payment_refunded';
    
    const LOG_TYPE_SETTINGS_CHANGED = 'settings_changed';
    const LOG_TYPE_CONFIG_CHANGED = 'config_changed';
    const LOG_TYPE_SUBSCRIPTION_CHANGED = 'subscription_changed';
    
    const LOG_TYPE_DATA_EXPORTED = 'data_exported';
    const LOG_TYPE_DATA_IMPORTED = 'data_imported';
    const LOG_TYPE_DATA_DELETED = 'data_deleted';
    
    const LOG_TYPE_SECURITY_ALERT = 'security_alert';
    const LOG_TYPE_LOGIN_FAILED = 'login_failed';
    const LOG_TYPE_LOGIN_SUCCESS = 'login_success';
    
    /**
     * Log a critical activity
     * 
     * @param string $action Action type (use class constants)
     * @param int $user_id User performing the action
     * @param int $tenant_id Tenant context
     * @param array $details Additional details to log
     * @param string $table Optional table name being modified
     * @param int $record_id Optional record ID being modified
     * @return bool Success status
     */
    public static function log($action, $user_id, $tenant_id, $details = [], $table = null, $record_id = null) {
        global $pdo;
        
        
        try {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
            $details_json = json_encode($details);
            
            $stmt = $pdo->prepare("
                INSERT INTO activity_log 
                (user_id, tenant_id, action, table_name, record_id, details, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                (int)$user_id,
                (int)$tenant_id,
                $action,
                $table,
                $record_id ? (int)$record_id : null,
                $details_json,
                $ip_address,
                $user_agent
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Log user creation
     * 
     * @param int $user_id User ID being created
     * @param int $tenant_id Tenant ID
     * @param array $user_data User data
     * @param int $created_by User performing the action
     * @return bool
     */
    public static function logUserCreated($user_id, $tenant_id, $user_data, $created_by) {
        $details = [
            'created_by' => $created_by,
            'email' => $user_data['email'] ?? null,
            'name' => $user_data['name'] ?? null,
            'role' => $user_data['role'] ?? null,
            'branch_id' => $user_data['branch_id'] ?? null
        ];
        
        return self::log(self::LOG_TYPE_USER_CREATED, $created_by, $tenant_id, $details, 'users', $user_id);
    }
    
    /**
     * Log user deletion
     * 
     * @param int $user_id User ID being deleted
     * @param int $tenant_id Tenant ID
     * @param array $user_data User data before deletion
     * @param int $deleted_by User performing the action
     * @return bool
     */
    public static function logUserDeleted($user_id, $tenant_id, $user_data, $deleted_by) {
        $details = [
            'deleted_by' => $deleted_by,
            'email' => $user_data['email'] ?? null,
            'name' => $user_data['name'] ?? null,
            'role' => $user_data['role'] ?? null
        ];
        
        return self::log(self::LOG_TYPE_USER_DELETED, $deleted_by, $tenant_id, $details, 'users', $user_id);
    }
    
    /**
     * Log user update
     * 
     * @param int $user_id User ID being updated
     * @param int $tenant_id Tenant ID
     * @param array $changes Changed fields (old_value => new_value)
     * @param int $updated_by User performing the action
     * @return bool
     */
    public static function logUserUpdated($user_id, $tenant_id, $changes, $updated_by) {
        // Don't log password changes in details for security
        if (isset($changes['password'])) {
            $changes['password'] = '[REDACTED]';
        }
        
        $details = [
            'updated_by' => $updated_by,
            'changes' => $changes
        ];
        
        return self::log(self::LOG_TYPE_USER_UPDATED, $updated_by, $tenant_id, $details, 'users', $user_id);
    }
    
    /**
     * Log payment received
     * 
     * @param int $payment_id Payment ID
     * @param int $tenant_id Tenant ID
     * @param array $payment_data Payment details
     * @param int $user_id User recording the payment
     * @return bool
     */
    public static function logPaymentReceived($payment_id, $tenant_id, $payment_data, $user_id) {
        $details = [
            'subscription_id' => $payment_data['subscription_id'] ?? null,
            'amount' => $payment_data['amount'] ?? null,
            'currency' => $payment_data['currency'] ?? null,
            'method' => $payment_data['payment_method'] ?? null,
            'reference' => $payment_data['receipt_number'] ?? null
        ];
        
        return self::log(self::LOG_TYPE_PAYMENT_RECEIVED, $user_id, $tenant_id, $details, 'subscription_payments', $payment_id);
    }
    
    /**
     * Log payment failure
     * 
     * @param int $tenant_id Tenant ID
     * @param array $payment_data Payment details
     * @param string $reason Failure reason
     * @return bool
     */
    public static function logPaymentFailed($tenant_id, $payment_data, $reason) {
        $details = [
            'subscription_id' => $payment_data['subscription_id'] ?? null,
            'amount' => $payment_data['amount'] ?? null,
            'reason' => $reason
        ];
        
        return self::log(self::LOG_TYPE_PAYMENT_FAILED, 0, $tenant_id, $details, 'payment_sessions', null);
    }
    
    /**
     * Log settings change
     * 
     * @param int $user_id User making the change
     * @param int $tenant_id Tenant ID
     * @param string $setting_name Setting name
     * @param mixed $old_value Previous value
     * @param mixed $new_value New value
     * @return bool
     */
    public static function logSettingsChanged($user_id, $tenant_id, $setting_name, $old_value, $new_value) {
        // Redact sensitive settings
        $sensitive_settings = ['smtp_password', 'api_key', 'secret_key', 'password'];
        $is_sensitive = false;
        
        foreach ($sensitive_settings as $sensitive) {
            if (stripos($setting_name, $sensitive) !== false) {
                $is_sensitive = true;
                break;
            }
        }
        
        $details = [
            'setting' => $setting_name,
            'old_value' => $is_sensitive ? '[REDACTED]' : $old_value,
            'new_value' => $is_sensitive ? '[REDACTED]' : $new_value,
            'sensitive' => $is_sensitive
        ];
        
        return self::log(self::LOG_TYPE_SETTINGS_CHANGED, $user_id, $tenant_id, $details, 'platform_settings', null);
    }
    
    /**
     * Log data export
     * 
     * @param int $user_id User exporting data
     * @param int $tenant_id Tenant ID
     * @param string $data_type Type of data exported
     * @param int $record_count Number of records exported
     * @return bool
     */
    public static function logDataExported($user_id, $tenant_id, $data_type, $record_count) {
        $details = [
            'data_type' => $data_type,
            'record_count' => $record_count
        ];
        
        return self::log(self::LOG_TYPE_DATA_EXPORTED, $user_id, $tenant_id, $details);
    }
    
    /**
     * Log security alert
     * 
     * @param int $tenant_id Tenant ID
     * @param string $alert_type Type of security alert
     * @param array $details Alert details
     * @return bool
     */
    public static function logSecurityAlert($tenant_id, $alert_type, $details) {
        return self::log(self::LOG_TYPE_SECURITY_ALERT, 0, $tenant_id, array_merge(['alert_type' => $alert_type], $details));
    }
    
    /**
     * Retrieve activity logs with filters
     * 
     * @param int $tenant_id Tenant ID
     * @param array $filters Optional filters (action, user_id, date_from, date_to, limit)
     * @return array Activity logs
     */
    public static function getActivityLogs($tenant_id, $filters = []) {
        global $pdo;
        
        if (!$pdo) {
            return [];
        }
        
        try {
            $sql = "SELECT * FROM activity_log WHERE tenant_id = ?";
            $params = [(int)$tenant_id];
            
            if (isset($filters['action'])) {
                $sql .= " AND action = ?";
                $params[] = $filters['action'];
            }
            
            if (isset($filters['user_id'])) {
                $sql .= " AND user_id = ?";
                $params[] = (int)$filters['user_id'];
            }
            
            if (isset($filters['date_from'])) {
                $sql .= " AND DATE(created_at) >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (isset($filters['date_to'])) {
                $sql .= " AND DATE(created_at) <= ?";
                $params[] = $filters['date_to'];
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            $limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
            $sql .= " LIMIT ?";
            $params[] = $limit;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get activity statistics
     * 
     * @param int $tenant_id Tenant ID
     * @param int $days Number of days to analyze
     * @return array Statistics
     */
    public static function getActivityStats($tenant_id, $days = 30) {
        global $pdo;
        
        if (!$pdo) {
            return [];
        }
        
        try {
            $date_from = date('Y-m-d', time() - ($days * 86400));
            
            $stmt = $pdo->prepare("
                SELECT 
                    action,
                    COUNT(*) as count,
                    COUNT(DISTINCT user_id) as unique_users,
                    MAX(created_at) as last_occurrence
                FROM activity_log
                WHERE tenant_id = ? AND DATE(created_at) >= ?
                GROUP BY action
                ORDER BY count DESC
            ");
            
            $stmt->execute([(int)$tenant_id, $date_from]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }
}
?>
