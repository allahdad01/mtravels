<?php
/**
 * BranchAddonManager - Manages branch add-ons for tenants
 * 
 * Handles requests for additional branches, pricing calculations,
 * and payment tracking for tenants wanting to exceed their plan limits.
 */

class BranchAddonManager {
    private $conn;
    private $tenant_id;
    
    // Default pricing for additional branches (per month)
    private const DEFAULT_ADDON_PRICE = 50.00;
    
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
                p.max_branches,
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
     * Get current branch count for a tenant
     */
    public function getCurrentBranchCount($tenant_id = null) {
        $id = $tenant_id ?? $this->tenant_id;
        
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as branch_count
            FROM branches
            WHERE tenant_id = ? AND status = 'active'
        ");
        
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        return $result['branch_count'] ?? 0;
    }
    
    /**
     * Get active branch add-ons for a tenant
     */
    public function getActiveBranchAddons($tenant_id = null) {
        $id = $tenant_id ?? $this->tenant_id;
        
        $stmt = $this->conn->prepare("
            SELECT *
            FROM branch_addons
            WHERE tenant_id = ? AND status = 'active'
            ORDER BY created_at DESC
        ");
        
        $stmt->execute([$id]);
        $result = $stmt->fetchAll();
        
        return $result;
    }
    
    /**
     * Get total additional branches allowed via add-ons
     */
    public function getTotalAdditionalBranches($tenant_id = null) {
        $id = $tenant_id ?? $this->tenant_id;
        
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(additional_branches), 0) as total_additional
            FROM branch_addons
            WHERE tenant_id = ? AND status = 'active'
        ");
        
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        return $result['total_additional'] ?? 0;
    }
    
    /**
     * Get max allowed branches for a tenant (plan + add-ons)
     */
    public function getMaxAllowedBranches($tenant_id = null) {
        $id = $tenant_id ?? $this->tenant_id;
        
        $plan = $this->getTenantPlanInfo($id);
        if (!$plan) {
            return 1; // Default to 1 if no plan
        }
        
        $additional = $this->getTotalAdditionalBranches($id);
        return $plan['max_branches'] + $additional;
    }
    
    /**
     * Check if tenant can add more branches
     */
    public function canAddMoreBranches($tenant_id = null) {
        $id = $tenant_id ?? $this->tenant_id;
        $current = $this->getCurrentBranchCount($id);
        $max = $this->getMaxAllowedBranches($id);
        
        return $current < $max;
    }
    
    /**
     * Get branch addon pricing configuration
     */
    public function getAddonPricing($tenant_id = null) {
        $id = $tenant_id ?? $this->tenant_id;
        
        $stmt = $this->conn->prepare("
            SELECT 
                monthly_price,
                quarterly_price,
                yearly_price
            FROM settings
            WHERE tenant_id = ?
        ");
        
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        // Return pricing with fallback defaults
        if ($result) {
            return [
                'monthly' => floatval($result['monthly_price'] ?? self::DEFAULT_ADDON_PRICE),
                'quarterly' => floatval($result['quarterly_price'] ?? (self::DEFAULT_ADDON_PRICE * 3)),
                'yearly' => floatval($result['yearly_price'] ?? (self::DEFAULT_ADDON_PRICE * 12))
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
     * Calculate the cost of adding N branches
     */
    public function calculateAddonCost($num_branches, $billing_cycle = 'monthly', $currency = 'USD') {
        $pricing = $this->getAddonPricing();
        $price_per_branch = $pricing[$billing_cycle] ?? $pricing['monthly'];
        $monthly_cost = $num_branches * $price_per_branch;
        
        // Apply multiplier based on billing cycle
        $cost = match($billing_cycle) {
            'quarterly' => $num_branches * $pricing['quarterly'],
            'yearly' => $num_branches * $pricing['yearly'],
            default => $monthly_cost // monthly
        };
        
        return round($cost, 2);
    }
    
    /**
     * Request additional branches (creates pending request)
     */
    public function requestAdditionalBranches($tenant_id, $num_branches, $billing_cycle = 'monthly') {
        $plan = $this->getTenantPlanInfo($tenant_id);
        if (!$plan) {
            return ['success' => false, 'message' => 'Tenant has no active subscription'];
        }
        
        if ($num_branches <= 0) {
            return ['success' => false, 'message' => 'Number of branches must be positive'];
        }
        
        $estimated_cost = $this->calculateAddonCost($num_branches, $billing_cycle, $plan['currency']);
        
        $stmt = $this->conn->prepare("
            INSERT INTO branch_addon_requests 
            (tenant_id, requested_additional_branches, estimated_monthly_cost, currency, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        
        try {
            $stmt->execute([$tenant_id, $num_branches, $estimated_cost, $plan['currency']]);
            $request_id = $this->conn->lastInsertId();
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
        
        return [
            'success' => true,
            'request_id' => $request_id,
            'message' => 'Branch add-on request created successfully',
            'estimated_cost' => $estimated_cost,
            'currency' => $plan['currency']
        ];
    }
    
    /**
     * Approve a branch add-on request (by super admin)
     */
    public function approveBranchRequest($request_id, $approved_by_user_id, $approval_notes = '') {
        // Get the request
        $stmt = $this->conn->prepare("
            SELECT * FROM branch_addon_requests 
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
        
        // Use the estimated cost from the request (already calculated with tenant's configured pricing)
        $total_cost = $request['estimated_monthly_cost'];
        
        // Calculate price per branch based on requested branches
        $num_branches = $request['requested_additional_branches'] ?? 1;
        $addon_price = ($num_branches > 0) ? round($total_cost / $num_branches, 2) : self::DEFAULT_ADDON_PRICE;
        
        $stmt = $this->conn->prepare("
            INSERT INTO branch_addons 
            (tenant_id, plan_id, base_branches, additional_branches, addon_price_per_branch, 
             currency, total_addon_cost, billing_cycle, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'monthly', 'active', ?)
        ");
        
        try {
            $stmt->execute([
                $tenant_id,
                $plan['id'],
                $plan['max_branches'],
                $request['requested_additional_branches'],
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
            UPDATE branch_addon_requests
            SET status = 'approved', approved_by = ?, approved_at = NOW(), approval_notes = ?
            WHERE id = ?
        ");
        
        $stmt->execute([$approved_by_user_id, $approval_notes, $request_id]);
        
        return [
            'success' => true,
            'addon_id' => $addon_id,
            'message' => 'Branch add-on request approved',
            'additional_branches' => $request['requested_additional_branches'],
            'total_cost' => $total_cost,
            'currency' => $request['currency']
        ];
    }
    
    /**
     * Reject a branch add-on request
     */
    public function rejectBranchRequest($request_id, $rejected_by_user_id, $reason = '') {
        $stmt = $this->conn->prepare("
            UPDATE branch_addon_requests
            SET status = 'rejected', approved_by = ?, approved_at = NOW(), approval_notes = ?
            WHERE id = ? AND status = 'pending'
        ");
        
        $stmt->execute([$rejected_by_user_id, $reason, $request_id]);
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Request not found or already processed'];
        }
        
        return ['success' => true, 'message' => 'Branch add-on request rejected'];
    }
    
    /**
     * Record a payment for a branch add-on
     */
    public function recordAddonPayment($addon_id, $tenant_id, $amount, $currency, $payment_method, $transaction_id = null) {
        // Verify addon belongs to tenant
        $stmt = $this->conn->prepare("
            SELECT * FROM branch_addons
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$addon_id, $tenant_id]);
        $addon = $stmt->fetch();
        
        if (!$addon) {
            return ['success' => false, 'message' => 'Add-on not found for this tenant'];
        }
        
        // Calculate period dates (current month)
        $period_start = date('Y-m-01');
        $period_end = date('Y-m-t');
        
        $stmt = $this->conn->prepare("
            INSERT INTO branch_addon_payments
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
            UPDATE branch_addons
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
     * Get branch add-on requests for a tenant
     */
    public function getTenantAddonRequests($tenant_id, $status = null) {
        $query = "
            SELECT * FROM branch_addon_requests
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
     * Get all pending branch add-on requests (for super admin)
     */
    public function getPendingAddonRequests() {
        $stmt = $this->conn->prepare("
            SELECT 
                bar.*,
                t.name as tenant_name,
                p.name as plan_name,
                p.max_branches
            FROM branch_addon_requests bar
            JOIN tenants t ON bar.tenant_id = t.id
            LEFT JOIN tenant_subscriptions ts ON t.id = ts.tenant_id AND ts.status = 'active'
            LEFT JOIN plans p ON ts.plan_id = p.id
            WHERE bar.status = 'pending'
            ORDER BY bar.requested_at DESC
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
                bap.*,
                ba.additional_branches,
                ba.addon_price_per_branch
            FROM branch_addon_payments bap
            JOIN branch_addons ba ON bap.addon_id = ba.id
            WHERE bap.tenant_id = ?
            ORDER BY bap.payment_date DESC
        ");
        
        $stmt->execute([$tenant_id]);
        $result = $stmt->fetchAll();
        
        return $result;
    }
    
    /**
     * Cancel a branch add-on
     */
    public function cancelBranchAddon($addon_id, $tenant_id) {
        // Verify addon belongs to tenant
        $stmt = $this->conn->prepare("
            UPDATE branch_addons
            SET status = 'cancelled', updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        
        $stmt->execute([$addon_id, $tenant_id]);
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Add-on not found for this tenant'];
        }
        
        return ['success' => true, 'message' => 'Branch add-on cancelled'];
    }
    
    /**
     * Suspend a branch add-on (super admin) - uses a suspension flag instead of status
     */
    public function suspendBranchAddon($addon_id) {
        // First, add suspension tracking column if needed
        try {
            $stmt = $this->conn->prepare("
                UPDATE branch_addons
                SET status = 'inactive', updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$addon_id]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Add-on not found'];
            }
            
            return ['success' => true, 'message' => 'Branch add-on suspended'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to suspend addon: ' . $e->getMessage()];
        }
    }
    
    /**
     * Reactivate a suspended branch add-on (super admin)
     */
    public function reactivateBranchAddon($addon_id) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE branch_addons
                SET status = 'active', updated_at = NOW()
                WHERE id = ? AND status = 'inactive'
            ");
            
            $stmt->execute([$addon_id]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Add-on not found or not inactive'];
            }
            
            return ['success' => true, 'message' => 'Branch add-on reactivated'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to reactivate addon: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get all branch addons (for super admin) - both approved and pending
     */
    public function getAllBranchAddons($include_status = null) {
        $query = "
            SELECT 
                'addon' as type,
                ba.id,
                ba.tenant_id,
                ba.status,
                ba.additional_branches,
                ba.addon_price_per_branch,
                ba.total_addon_cost,
                ba.currency,
                ba.created_at as created_date,
                ba.updated_at,
                t.name as tenant_name,
                p.name as plan_name,
                p.max_branches,
                u.name as created_by_name
            FROM branch_addons ba
            JOIN tenants t ON ba.tenant_id = t.id
            LEFT JOIN plans p ON ba.plan_id = p.id
            LEFT JOIN users u ON ba.created_by = u.id
        ";
        
        if ($include_status) {
            $query .= " WHERE ba.status = ?";
            $stmt = $this->conn->prepare($query . " ORDER BY ba.created_at DESC");
            $stmt->execute([$include_status]);
        } else {
            $stmt = $this->conn->prepare($query . " ORDER BY ba.created_at DESC");
            $stmt->execute();
        }
        
        return $stmt->fetchAll();
    }
    }
