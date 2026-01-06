<?php
/**
 * UserAddonManager - Manages user add-ons for tenants
 * 
 * Handles requests for additional users, pricing calculations,
 * and payment tracking for tenants wanting to exceed their plan limits.
 */

class UserAddonManager {
    private $conn;
    private $tenant_id;
    
    // Default pricing for additional users (per month)
    private const DEFAULT_ADDON_PRICE = 25.00;
    
    public function __construct($connection, $tenant_id = null) {
        $this->conn = $connection;
        $this->tenant_id = $tenant_id;
    }
    
    /**
     * Get current plan details for a tenant
     */
    public function getTenantPlanInfo($tenant_id = null) {
        $id = $tenant_id ?? $this->tenant_id;
        
        $stmt = $this->conn->prepare("
            SELECT 
                p.id,
                p.name,
                p.max_users,
                p.price as plan_price,
                ts.id as subscription_id,
                ts.billing_cycle,
                ts.currency
            FROM tenant_subscriptions ts
            JOIN plans p ON ts.plan_id = p.id
            WHERE ts.tenant_id = ? AND ts.status = 'active'
            LIMIT 1
        ");
        
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        return $result;
    }
    
    /**
     * Get current user count for a tenant (excluding super_admin)
     */
    public function getCurrentUserCount($tenant_id = null) {
        $id = $tenant_id ?? $this->tenant_id;
        
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as user_count
            FROM users
            WHERE tenant_id = ? AND role != 'super_admin'
        ");
        
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        return $result['user_count'] ?? 0;
    }
    
    /**
     * Get active user add-ons for a tenant
     */
    public function getActiveUserAddons($tenant_id = null) {
        $id = $tenant_id ?? $this->tenant_id;
        
        $stmt = $this->conn->prepare("
            SELECT *
            FROM user_addons
            WHERE tenant_id = ? AND status = 'active'
            ORDER BY created_at DESC
        ");
        
        $stmt->execute([$id]);
        $result = $stmt->fetchAll();
        
        return $result;
    }
    
    /**
     * Get total additional users allowed via add-ons
     */
    public function getTotalAdditionalUsers($tenant_id = null) {
        $id = $tenant_id ?? $this->tenant_id;
        
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(additional_users), 0) as total_additional
            FROM user_addons
            WHERE tenant_id = ? AND status = 'active'
        ");
        
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        return $result['total_additional'] ?? 0;
    }
    
    /**
     * Get max allowed users for a tenant (plan + add-ons)
     */
    public function getMaxAllowedUsers($tenant_id = null) {
        $id = $tenant_id ?? $this->tenant_id;
        
        $plan = $this->getTenantPlanInfo($id);
        if (!$plan) {
            return 1; // Default to 1 if no plan
        }
        
        $additional = $this->getTotalAdditionalUsers($id);
        return $plan['max_users'] + $additional;
    }
    
    /**
     * Check if tenant can add more users
     */
    public function canAddMoreUsers($tenant_id = null) {
        $id = $tenant_id ?? $this->tenant_id;
        $current = $this->getCurrentUserCount($id);
        $max = $this->getMaxAllowedUsers($id);
        
        return $current < $max;
    }
    
    /**
     * Get user addon pricing configuration
     * Reads from dedicated user_addon pricing columns in settings table
     */
    public function getAddonPricing($tenant_id = null) {
        $id = $tenant_id ?? $this->tenant_id;
        
        $stmt = $this->conn->prepare("
            SELECT 
                user_addon_monthly_price,
                user_addon_quarterly_price,
                user_addon_yearly_price
            FROM settings
            WHERE tenant_id = ?
        ");
        
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        // Return pricing with fallback defaults
        if ($result && $result['user_addon_monthly_price'] !== null) {
            return [
                'monthly' => floatval($result['user_addon_monthly_price'] ?? self::DEFAULT_ADDON_PRICE),
                'quarterly' => floatval($result['user_addon_quarterly_price'] ?? (self::DEFAULT_ADDON_PRICE * 3)),
                'yearly' => floatval($result['user_addon_yearly_price'] ?? (self::DEFAULT_ADDON_PRICE * 12))
            ];
        }
        
        // Return defaults if no settings found
        return [
            'monthly' => self::DEFAULT_ADDON_PRICE,
            'quarterly' => self::DEFAULT_ADDON_PRICE * 3,
            'yearly' => self::DEFAULT_ADDON_PRICE * 12
        ];
    }
    
    /**
     * Calculate the cost of adding N users
     */
    public function calculateAddonCost($num_users, $billing_cycle = 'monthly', $currency = 'USD') {
        $pricing = $this->getAddonPricing();
        $cost = match($billing_cycle) {
            'quarterly' => $num_users * $pricing['quarterly'],
            'yearly' => $num_users * $pricing['yearly'],
            default => $num_users * $pricing['monthly']
        };
        
        return round($cost, 2);
    }
    
    /**
     * Request additional users (creates pending request)
     */
    public function requestAdditionalUsers($tenant_id, $num_users, $billing_cycle = 'monthly') {
        $plan = $this->getTenantPlanInfo($tenant_id);
        if (!$plan) {
            return ['success' => false, 'message' => 'Tenant has no active subscription'];
        }
        
        if ($num_users <= 0) {
            return ['success' => false, 'message' => 'Number of users must be positive'];
        }
        
        $estimated_cost = $this->calculateAddonCost($num_users, $billing_cycle, $plan['currency']);
        
        $stmt = $this->conn->prepare("
            INSERT INTO user_addon_requests 
            (tenant_id, requested_additional_users, estimated_monthly_cost, currency, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        
        try {
            $stmt->execute([$tenant_id, $num_users, $estimated_cost, $plan['currency']]);
            $request_id = $this->conn->lastInsertId();
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
        
        return [
            'success' => true,
            'request_id' => $request_id,
            'message' => 'User add-on request created successfully',
            'estimated_cost' => $estimated_cost,
            'currency' => $plan['currency']
        ];
    }
    
    /**
     * Approve a user add-on request (by super admin)
     */
    public function approveUserRequest($request_id, $approved_by_user_id, $approval_notes = '') {
        // Get the request
        $stmt = $this->conn->prepare("
            SELECT * FROM user_addon_requests 
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            return ['success' => false, 'message' => 'Request not found or already processed'];
        }
        
        $tenant_id = $request['tenant_id'];
        $plan = $this->getTenantPlanInfo($tenant_id);
        
        if (!$plan) {
            return ['success' => false, 'message' => 'Tenant has no active subscription'];
        }
        
        // Use the estimated cost from the request
        $total_cost = $request['estimated_monthly_cost'];
        
        // Calculate price per user
        $num_users = $request['requested_additional_users'] ?? 1;
        $addon_price = ($num_users > 0) ? round($total_cost / $num_users, 2) : self::DEFAULT_ADDON_PRICE;
        
        $stmt = $this->conn->prepare("
            INSERT INTO user_addons 
            (tenant_id, plan_id, base_users, additional_users, addon_price_per_user, 
             currency, total_addon_cost, billing_cycle, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'monthly', 'active', ?)
        ");
        
        try {
            $stmt->execute([
                $tenant_id,
                $plan['id'],
                $plan['max_users'],
                $request['requested_additional_users'],
                $addon_price,
                $request['currency'],
                $total_cost,
                $approved_by_user_id
            ]);
            $addon_id = $this->conn->lastInsertId();
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to create addon: ' . $e->getMessage()];
        }
        
        // Update the request to approved
        $stmt = $this->conn->prepare("
            UPDATE user_addon_requests
            SET status = 'approved', approved_by = ?, approved_at = NOW(), approval_notes = ?
            WHERE id = ?
        ");
        
        $stmt->execute([$approved_by_user_id, $approval_notes, $request_id]);
        
        return [
            'success' => true,
            'addon_id' => $addon_id,
            'message' => 'User add-on request approved',
            'additional_users' => $request['requested_additional_users'],
            'total_cost' => $total_cost,
            'currency' => $request['currency']
        ];
    }
    
    /**
     * Reject a user add-on request
     */
    public function rejectUserRequest($request_id, $rejected_by_user_id, $reason = '') {
        $stmt = $this->conn->prepare("
            UPDATE user_addon_requests
            SET status = 'rejected', rejected_by = ?, rejected_at = NOW(), rejection_reason = ?
            WHERE id = ? AND status = 'pending'
        ");
        
        $stmt->execute([$rejected_by_user_id, $reason, $request_id]);
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Request not found or already processed'];
        }
        
        return ['success' => true, 'message' => 'User add-on request rejected'];
    }
    
    /**
     * Record a payment for a user add-on
     */
    public function recordAddonPayment($addon_id, $tenant_id, $amount, $currency, $payment_method, $transaction_id = null) {
        // Verify addon belongs to tenant
        $stmt = $this->conn->prepare("
            SELECT * FROM user_addons
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$addon_id, $tenant_id]);
        $addon = $stmt->fetch();
        
        if (!$addon) {
            return ['success' => false, 'message' => 'Add-on not found for this tenant'];
        }
        
        // Calculate period dates
        $period_start = date('Y-m-01');
        $period_end = date('Y-m-t');
        
        $stmt = $this->conn->prepare("
            INSERT INTO user_addon_payments
            (addon_id, tenant_id, amount, currency, payment_method, transaction_id, 
             status, payment_date, period_start, period_end)
            VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW(), ?, ?)
        ");
        
        try {
            $stmt->execute([
                $addon_id,
                $tenant_id,
                $amount,
                $currency,
                $payment_method,
                $transaction_id,
                $period_start,
                $period_end
            ]);
            $payment_id = $this->conn->lastInsertId();
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to record payment: ' . $e->getMessage()];
        }
        
        // Update addon next renewal date
        $next_renewal = date('Y-m-d', strtotime('+1 month', strtotime($period_end)));
        $stmt = $this->conn->prepare("
            UPDATE user_addons
            SET next_renewal_date = ?
            WHERE id = ?
        ");
        $stmt->execute([$next_renewal, $addon_id]);
        
        return [
            'success' => true,
            'payment_id' => $payment_id,
            'message' => 'Payment recorded successfully'
        ];
    }
    
    /**
     * Get user add-on requests for a tenant
     */
    public function getTenantAddonRequests($tenant_id, $status = null) {
        $query = "
            SELECT * FROM user_addon_requests
            WHERE tenant_id = ?
        ";
        $params = [$tenant_id];
        
        if ($status) {
            $query .= " AND status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY requested_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll();
        
        return $result;
    }
    
    /**
     * Get all pending user add-on requests (for super admin)
     */
    public function getPendingAddonRequests() {
        $stmt = $this->conn->prepare("
            SELECT 
                uar.*,
                t.name as tenant_name,
                p.name as plan_name,
                p.max_users
            FROM user_addon_requests uar
            JOIN tenants t ON uar.tenant_id = t.id
            LEFT JOIN tenant_subscriptions ts ON t.id = ts.tenant_id AND ts.status = 'active'
            LEFT JOIN plans p ON ts.plan_id = p.id
            WHERE uar.status = 'pending'
            ORDER BY uar.requested_at DESC
        ");
        
        $stmt->execute();
        $result = $stmt->fetchAll();
        
        return $result;
    }
    
    /**
     * Get addon payment history for a tenant
     */
    public function getAddonPaymentHistory($tenant_id) {
        $stmt = $this->conn->prepare("
            SELECT 
                uap.*,
                ua.additional_users,
                ua.addon_price_per_user
            FROM user_addon_payments uap
            JOIN user_addons ua ON uap.addon_id = ua.id
            WHERE uap.tenant_id = ?
            ORDER BY uap.payment_date DESC
        ");
        
        $stmt->execute([$tenant_id]);
        $result = $stmt->fetchAll();
        
        return $result;
    }
    
    /**
     * Cancel a user add-on
     */
    public function cancelUserAddon($addon_id, $tenant_id) {
        $stmt = $this->conn->prepare("
            UPDATE user_addons
            SET status = 'cancelled', updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        
        $stmt->execute([$addon_id, $tenant_id]);
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Add-on not found for this tenant'];
        }
        
        return ['success' => true, 'message' => 'User add-on cancelled'];
    }
    
    /**
     * Suspend a user add-on (super admin)
     */
    public function suspendUserAddon($addon_id) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE user_addons
                SET status = 'inactive', updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$addon_id]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Add-on not found'];
            }
            
            return ['success' => true, 'message' => 'User add-on suspended'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to suspend addon: ' . $e->getMessage()];
        }
    }
    
    /**
     * Reactivate a suspended user add-on (super admin)
     */
    public function reactivateUserAddon($addon_id) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE user_addons
                SET status = 'active', updated_at = NOW()
                WHERE id = ? AND status = 'inactive'
            ");
            
            $stmt->execute([$addon_id]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Add-on not found or not inactive'];
            }
            
            return ['success' => true, 'message' => 'User add-on reactivated'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to reactivate addon: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get all user addons (for super admin) - both approved and pending
     */
    public function getAllUserAddons($include_status = null) {
        $query = "
            SELECT 
                'addon' as type,
                ua.id,
                ua.tenant_id,
                ua.status,
                ua.additional_users,
                ua.addon_price_per_user,
                ua.total_addon_cost,
                ua.currency,
                ua.created_at as created_date,
                ua.updated_at,
                t.name as tenant_name,
                p.name as plan_name,
                p.max_users,
                u.name as created_by_name
            FROM user_addons ua
            JOIN tenants t ON ua.tenant_id = t.id
            LEFT JOIN plans p ON ua.plan_id = p.id
            LEFT JOIN users u ON ua.created_by = u.id
        ";
        
        if ($include_status) {
            $query .= " WHERE ua.status = ?";
            $stmt = $this->conn->prepare($query . " ORDER BY ua.created_at DESC");
            $stmt->execute([$include_status]);
        } else {
            $stmt = $this->conn->prepare($query . " ORDER BY ua.created_at DESC");
            $stmt->execute();
        }
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get usage statistics for a tenant
     */
    public function getUsageStats($tenant_id = null) {
        $id = $tenant_id ?? $this->tenant_id;
        
        $plan = $this->getTenantPlanInfo($id);
        $current_users = $this->getCurrentUserCount($id);
        $max_users = $this->getMaxAllowedUsers($id);
        $additional_users = $this->getTotalAdditionalUsers($id);
        
        $active_addons = $this->getActiveUserAddons($id);
        $pending_requests = $this->getTenantAddonRequests($id, 'pending');
        
        return [
            'plan' => $plan,
            'current_users' => $current_users,
            'max_users' => $max_users,
            'base_users' => $plan['max_users'] ?? 0,
            'additional_users' => $additional_users,
            'available_slots' => $max_users - $current_users,
            'usage_percentage' => $max_users > 0 ? round(($current_users / $max_users) * 100, 1) : 0,
            'active_addons' => $active_addons,
            'pending_requests' => $pending_requests,
            'can_add_more' => $current_users < $max_users
        ];
    }
}
