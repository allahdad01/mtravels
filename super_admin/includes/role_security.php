<?php
/**
 * Role-Based Access Control (RBAC) Module
 * 
 * Provides secure role validation and privilege hierarchy management
 */

// Define role hierarchy (higher number = more privilege)
if (!defined('ROLE_HIERARCHY')) {
    define('ROLE_HIERARCHY', [
        'user' => 0,
        'tenant_admin' => 1,
        'super_admin' => 2
    ]);
}

/**
 * Check if a role is valid
 * 
 * @param string $role The role to validate
 * @return bool True if role is valid, false otherwise
 */
function isValidRole($role) {
    return isset(ROLE_HIERARCHY[$role]);
}

/**
 * Get the privilege level of a role
 * 
 * @param string $role The role name
 * @return int Privilege level, or -1 if invalid
 */
function getRoleLevel($role) {
    return ROLE_HIERARCHY[$role] ?? -1;
}

/**
 * Check if current user can assign a specific role
 * 
 * @param string $new_role The role to be assigned
 * @param string $current_user_role The role of the user assigning the role
 * @return bool True if assignment is allowed, false otherwise
 */
function canAssignRole($new_role, $current_user_role) {
    if (!isset(ROLE_HIERARCHY[$new_role]) || !isset(ROLE_HIERARCHY[$current_user_role])) {
        return false;
    }
    
    // Can only assign roles equal or lower than your own
    return ROLE_HIERARCHY[$new_role] <= ROLE_HIERARCHY[$current_user_role];
}

/**
 * Get current user's role level
 * 
 * @return int Current role level, or -1 if not authenticated
 */
function getCurrentRoleLevel() {
    if (!isset($_SESSION['role']) || !isset(ROLE_HIERARCHY[$_SESSION['role']])) {
        return -1;
    }
    return ROLE_HIERARCHY[$_SESSION['role']];
}

/**
 * Check if current user has minimum required role level
 * 
 * @param int $required_level Minimum required privilege level
 * @return bool True if user meets or exceeds the level, false otherwise
 */
function hasMinimumRole($required_level) {
    return getCurrentRoleLevel() >= $required_level;
}

/**
 * Check if user can modify another user's role
 * 
 * @param string $current_user_role Role of the user making the change
 * @param string $target_user_role Original role of the target user
 * @param string $new_role New role to be assigned
 * @return array Array with 'allowed' bool and 'reason' string
 */
function validateRoleChange($current_user_role, $target_user_role, $new_role) {
    // Validate all roles are valid
    if (!isValidRole($current_user_role) || !isValidRole($target_user_role) || !isValidRole($new_role)) {
        return [
            'allowed' => false,
            'reason' => 'Invalid role'
        ];
    }
    
    // Check if current user can assign the new role
    if (!canAssignRole($new_role, $current_user_role)) {
        return [
            'allowed' => false,
            'reason' => 'You cannot assign a role with higher privileges than your own'
        ];
    }
    
    // Check if trying to modify a role higher than current user
    if (ROLE_HIERARCHY[$target_user_role] > ROLE_HIERARCHY[$current_user_role]) {
        return [
            'allowed' => false,
            'reason' => 'You cannot modify users with higher privileges than your own'
        ];
    }
    
    return [
        'allowed' => true,
        'reason' => null
    ];
}

/**
 * Log role change for audit trail
 * 
 * @param int $admin_user_id ID of admin making the change
 * @param int $target_user_id ID of user being modified
 * @param string $old_role Previous role
 * @param string $new_role New role
 * @param PDO $pdo Database connection (optional)
 * @return bool True on success
 */
function logRoleChange($admin_user_id, $target_user_id, $old_role, $new_role, $pdo = null) {
    global $pdo;
    
    // Use provided PDO or global
    $db = $pdo ?? ($GLOBALS['pdo'] ?? null);
    
    // Log to error log
    error_log("ROLE_CHANGE: Admin {$admin_user_id} changed user {$target_user_id}'s role from {$old_role} to {$new_role} - IP: {$_SERVER['REMOTE_ADDR']}");
    
    // Log to database if PDO available
    if ($db) {
        try {
            $stmt = $db->prepare("
                INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                VALUES (?, 'role_change', 'user', ?, ?, ?, NOW())
            ");
            
            $details = json_encode([
                'target_user_id' => $target_user_id,
                'old_role' => $old_role,
                'new_role' => $new_role
            ]);
            
            $stmt->execute([
                $admin_user_id,
                $target_user_id,
                $details,
                $_SERVER['REMOTE_ADDR']
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Failed to log role change: " . $e->getMessage());
            return false;
        }
    }
    
    return true;
}

/**
 * Validate and sanitize role input
 * 
 * @param string $role_input The role input to validate
 * @return string|null Validated role or null if invalid
 */
function sanitizeRoleInput($role_input) {
    // Trim whitespace
    $role = trim((string)$role_input);
    
    // Check if valid
    if (!isValidRole($role)) {
        return null;
    }
    
    return $role;
}

/**
 * Get role name for display
 * 
 * @param string $role The role code
 * @return string Human-readable role name
 */
function getRoleDisplayName($role) {
    $role_names = [
        'user' => 'User',
        'tenant_admin' => 'Tenant Administrator',
        'super_admin' => 'Super Administrator'
    ];
    
    return $role_names[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

/**
 * Get all available roles for current user
 * 
 * @return array Array of roles the current user can assign
 */
function getAvailableRolesForCurrentUser() {
    $current_level = getCurrentRoleLevel();
    
    $available = [];
    foreach (ROLE_HIERARCHY as $role => $level) {
        if ($level <= $current_level) {
            $available[$role] = getRoleDisplayName($role);
        }
    }
    
    return $available;
}
?>
