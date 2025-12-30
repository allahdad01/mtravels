<?php
/**
 * Branch Add-On System - Usage Examples
 * 
 * This file demonstrates common usage patterns for the BranchAddonManager class.
 * These are examples - adjust paths and variable names for your implementation.
 */

// Include the necessary files
require_once 'conn.php';
require_once 'BranchAddonManager.php';

// ============================================================================
// EXAMPLE 1: Check if a tenant can create more branches
// ============================================================================
function canCreateBranch($tenant_id) {
    global $conn;
    
    $addon_manager = new BranchAddonManager($conn, $tenant_id);
    
    if ($addon_manager->canAddMoreBranches()) {
        return [
            'can_create' => true,
            'available' => $addon_manager->getMaxAllowedBranches() - 
                          $addon_manager->getCurrentBranchCount()
        ];
    }
    
    return [
        'can_create' => false,
        'current' => $addon_manager->getCurrentBranchCount(),
        'max' => $addon_manager->getMaxAllowedBranches()
    ];
}

// Usage:
// $result = canCreateBranch($_SESSION['tenant_id']);
// if (!$result['can_create']) {
//     echo "Cannot create more branches. Max: {$result['max']}, Current: {$result['current']}";
// }

// ============================================================================
// EXAMPLE 2: Get tenant summary for dashboard widget
// ============================================================================
function getTenantBranchSummary($tenant_id) {
    global $conn;
    
    $addon_manager = new BranchAddonManager($conn, $tenant_id);
    
    $plan = $addon_manager->getTenantPlanInfo($tenant_id);
    if (!$plan) {
        return null; // No active subscription
    }
    
    $current = $addon_manager->getCurrentBranchCount($tenant_id);
    $additional = $addon_manager->getTotalAdditionalBranches($tenant_id);
    $max = $addon_manager->getMaxAllowedBranches($tenant_id);
    
    return [
        'plan_name' => $plan['name'],
        'plan_branches' => $plan['max_branches'],
        'additional_branches' => $additional,
        'current_branches' => $current,
        'max_allowed' => $max,
        'available_slots' => $max - $current,
        'can_add_more' => $max - $current > 0,
        'currency' => $plan['currency']
    ];
}

// Usage:
// $summary = getTenantBranchSummary($_SESSION['tenant_id']);
// if ($summary) {
//     echo "{$summary['current_branches']}/{$summary['max_allowed']} branches used";
// }

// ============================================================================
// EXAMPLE 3: Request additional branches for a tenant
// ============================================================================
function submitBranchAddOnRequest($tenant_id, $num_branches, $billing_cycle = 'monthly') {
    global $conn;
    
    $addon_manager = new BranchAddonManager($conn, $tenant_id);
    
    // Validate the request
    $plan = $addon_manager->getTenantPlanInfo($tenant_id);
    if (!$plan) {
        return [
            'success' => false,
            'error' => 'No active subscription found'
        ];
    }
    
    if ($num_branches <= 0) {
        return [
            'success' => false,
            'error' => 'Number of branches must be greater than 0'
        ];
    }
    
    // Calculate cost
    $cost = $addon_manager->calculateAddonCost(
        $num_branches,
        $billing_cycle,
        $plan['currency']
    );
    
    // Submit the request
    $result = $addon_manager->requestAdditionalBranches(
        $tenant_id,
        $num_branches,
        $billing_cycle
    );
    
    if ($result['success']) {
        return [
            'success' => true,
            'request_id' => $result['request_id'],
            'estimated_cost' => $cost,
            'currency' => $plan['currency'],
            'branches' => $num_branches,
            'billing_cycle' => $billing_cycle,
            'message' => "Your request for {$num_branches} additional branches has been submitted."
        ];
    }
    
    return [
        'success' => false,
        'error' => $result['message']
    ];
}

// Usage:
// $result = submitBranchAddOnRequest($_SESSION['tenant_id'], 2, 'monthly');
// if ($result['success']) {
//     echo "Request {$result['request_id']} created. Cost: {$result['estimated_cost']}";
// } else {
//     echo "Error: {$result['error']}";
// }

// ============================================================================
// EXAMPLE 4: Super admin approves a request
// ============================================================================
function approveBranchRequest($request_id, $admin_user_id, $notes = '') {
    global $conn;
    
    $addon_manager = new BranchAddonManager($conn);
    
    $result = $addon_manager->approveBranchRequest(
        $request_id,
        $admin_user_id,
        $notes
    );
    
    if ($result['success']) {
        return [
            'success' => true,
            'addon_id' => $result['addon_id'],
            'branches' => $result['additional_branches'],
            'total_cost' => $result['total_cost'],
            'message' => "Request approved. {$result['additional_branches']} branches added."
        ];
    }
    
    return [
        'success' => false,
        'error' => $result['message']
    ];
}

// Usage:
// $result = approveBranchRequest(5, $_SESSION['user_id'], 'Approved for Q1 campaign');
// if ($result['success']) {
//     echo "Addon {$result['addon_id']} created";
//     sendEmailNotification($tenant_id, "Branch add-on approved");
// }

// ============================================================================
// EXAMPLE 5: Super admin rejects a request
// ============================================================================
function rejectBranchRequest($request_id, $admin_user_id, $reason) {
    global $conn;
    
    $addon_manager = new BranchAddonManager($conn);
    
    $result = $addon_manager->rejectBranchRequest(
        $request_id,
        $admin_user_id,
        $reason
    );
    
    return $result;
}

// Usage:
// $result = rejectBranchRequest(3, $_SESSION['user_id'], 'Budget constraints');
// if ($result['success']) {
//     sendEmailNotification($tenant_id, "Branch request denied: Budget constraints");
// }

// ============================================================================
// EXAMPLE 6: Record a payment for branch add-on
// ============================================================================
function recordBranchAddonPayment($addon_id, $tenant_id, $amount, $currency, $stripe_txn_id) {
    global $conn;
    
    $addon_manager = new BranchAddonManager($conn, $tenant_id);
    
    $result = $addon_manager->recordAddonPayment(
        $addon_id,
        $tenant_id,
        $amount,
        $currency,
        'stripe',  // payment method
        $stripe_txn_id
    );
    
    if ($result['success']) {
        return [
            'success' => true,
            'payment_id' => $result['payment_id'],
            'message' => 'Payment recorded successfully'
        ];
    }
    
    return [
        'success' => false,
        'error' => $result['message']
    ];
}

// Usage:
// After processing Stripe payment:
// $result = recordBranchAddonPayment(1, 123, 100.00, 'USD', 'ch_1234567890');
// if ($result['success']) {
//     updateInvoiceStatus($invoice_id, 'paid');
// }

// ============================================================================
// EXAMPLE 7: Get all pending requests (for admin dashboard)
// ============================================================================
function getAllPendingRequests() {
    global $conn;
    
    $addon_manager = new BranchAddonManager($conn);
    $pending = $addon_manager->getPendingAddonRequests();
    
    $formatted = [];
    foreach ($pending as $request) {
        $formatted[] = [
            'id' => $request['id'],
            'tenant' => $request['tenant_name'],
            'plan' => $request['plan_name'],
            'requested_branches' => $request['requested_additional_branches'],
            'cost' => $request['estimated_monthly_cost'],
            'currency' => $request['currency'],
            'requested_at' => date('M d, Y H:i', strtotime($request['requested_at']))
        ];
    }
    
    return $formatted;
}

// Usage:
// $pending = getAllPendingRequests();
// echo "There are " . count($pending) . " pending requests";
// foreach ($pending as $req) {
//     echo "{$req['tenant']} requested {$req['requested_branches']} branches";
// }

// ============================================================================
// EXAMPLE 8: Get tenant's add-on status
// ============================================================================
function getTenantAddonStatus($tenant_id) {
    global $conn;
    
    $addon_manager = new BranchAddonManager($conn, $tenant_id);
    
    return [
        'pending_requests' => $addon_manager->getTenantAddonRequests($tenant_id, 'pending'),
        'approved_requests' => $addon_manager->getTenantAddonRequests($tenant_id, 'approved'),
        'active_addons' => $addon_manager->getActiveBranchAddons($tenant_id),
        'payment_history' => $addon_manager->getAddonPaymentHistory($tenant_id),
        'total_additional_branches' => $addon_manager->getTotalAdditionalBranches($tenant_id),
        'max_allowed_branches' => $addon_manager->getMaxAllowedBranches($tenant_id)
    ];
}

// Usage:
// $status = getTenantAddonStatus($_SESSION['tenant_id']);
// echo "Pending: " . count($status['pending_requests']);
// echo "Active: " . count($status['active_addons']);
// echo "Max branches: {$status['max_allowed_branches']}";

// ============================================================================
// EXAMPLE 9: Calculate cost comparison for different billing cycles
// ============================================================================
function compareAddonPricing($num_branches) {
    global $conn;
    
    $addon_manager = new BranchAddonManager($conn);
    
    return [
        'monthly' => $addon_manager->calculateAddonCost($num_branches, 'monthly'),
        'quarterly' => $addon_manager->calculateAddonCost($num_branches, 'quarterly'),
        'yearly' => $addon_manager->calculateAddonCost($num_branches, 'yearly')
    ];
}

// Usage:
// $pricing = compareAddonPricing(3);
// echo "3 branches:";
// echo "  Monthly: {$pricing['monthly']}";
// echo "  Quarterly: {$pricing['quarterly']}";
// echo "  Yearly: {$pricing['yearly']}";

// ============================================================================
// EXAMPLE 10: Check if tenant needs to upgrade branches
// ============================================================================
function checkBranchLimitStatus($tenant_id, $alert_threshold = 80) {
    global $conn;
    
    $addon_manager = new BranchAddonManager($conn, $tenant_id);
    
    $current = $addon_manager->getCurrentBranchCount($tenant_id);
    $max = $addon_manager->getMaxAllowedBranches($tenant_id);
    $usage_percent = ($current / $max) * 100;
    
    return [
        'current' => $current,
        'max' => $max,
        'usage_percent' => round($usage_percent, 2),
        'status' => match(true) {
            $current >= $max => 'LIMIT_REACHED',
            $usage_percent >= $alert_threshold => 'APPROACHING_LIMIT',
            default => 'OK'
        },
        'action_needed' => $usage_percent >= $alert_threshold
    ];
}

// Usage:
// $status = checkBranchLimitStatus($_SESSION['tenant_id'], 75);
// if ($status['action_needed']) {
//     echo "You are using {$status['usage_percent']}% of your branch limit";
//     echo "Consider requesting additional branches";
// }

// ============================================================================
// EXAMPLE 11: Cancel an add-on (downgrade)
// ============================================================================
function cancelBranchAddon($addon_id, $tenant_id) {
    global $conn;
    
    $addon_manager = new BranchAddonManager($conn, $tenant_id);
    
    $result = $addon_manager->cancelBranchAddon($addon_id, $tenant_id);
    
    if ($result['success']) {
        // Update branch limits
        $max_before = $addon_manager->getMaxAllowedBranches($tenant_id);
        
        // Note: After cancellation, max_before is outdated
        // You should recalculate or fetch again
        
        return [
            'success' => true,
            'message' => 'Add-on cancelled successfully'
        ];
    }
    
    return [
        'success' => false,
        'error' => $result['message']
    ];
}

// Usage:
// $result = cancelBranchAddon(2, $_SESSION['tenant_id']);
// if ($result['success']) {
//     echo "Add-on cancelled. Your branch limit will be adjusted at next renewal";
// }

// ============================================================================
// EXAMPLE 12: Integrate with branch creation form validation
// ============================================================================
function validateBranchCreation($tenant_id, $branch_name) {
    global $conn;
    
    $addon_manager = new BranchAddonManager($conn, $tenant_id);
    
    // Check if tenant can create more branches
    if (!$addon_manager->canAddMoreBranches()) {
        return [
            'valid' => false,
            'error' => 'You have reached your maximum number of branches. ' .
                      'Request additional branches to continue.'
        ];
    }
    
    // Check if branch name is provided
    if (empty($branch_name)) {
        return [
            'valid' => false,
            'error' => 'Branch name is required'
        ];
    }
    
    return [
        'valid' => true,
        'can_proceed' => true
    ];
}

// Usage in your branch creation form:
// $validation = validateBranchCreation($_SESSION['tenant_id'], $_POST['branch_name']);
// if (!$validation['valid']) {
//     $_SESSION['error'] = $validation['error'];
//     redirectTo('request_branch_addon.php');
// }
// // Continue with branch creation

// ============================================================================
// EXAMPLE 13: Generate invoice for branch add-on
// ============================================================================
function generateBranchAddonInvoice($addon_id, $tenant_id) {
    global $conn;
    
    $addon_manager = new BranchAddonManager($conn, $tenant_id);
    
    // Get the add-on details
    $addons = $addon_manager->getActiveBranchAddons($tenant_id);
    $addon = null;
    foreach ($addons as $a) {
        if ($a['id'] == $addon_id) {
            $addon = $a;
            break;
        }
    }
    
    if (!$addon) {
        return null;
    }
    
    // Create invoice structure
    return [
        'invoice_number' => 'INV-' . date('Ymd') . '-' . $addon_id,
        'date' => date('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+14 days')),
        'tenant_id' => $tenant_id,
        'addon_id' => $addon_id,
        'items' => [
            [
                'description' => 'Additional Branches',
                'quantity' => $addon['additional_branches'],
                'unit_price' => $addon['addon_price_per_branch'],
                'total' => $addon['total_addon_cost']
            ]
        ],
        'subtotal' => $addon['total_addon_cost'],
        'tax' => 0,
        'total' => $addon['total_addon_cost'],
        'currency' => $addon['currency']
    ];
}

// Usage:
// $invoice = generateBranchAddonInvoice(1, $_SESSION['tenant_id']);
// if ($invoice) {
//     sendInvoiceEmail($invoice);
// }

// ============================================================================
// EXAMPLE 14: Get analytics for admin dashboard
// ============================================================================
function getBranchAddonAnalytics() {
    global $conn;
    
    // Get all active add-ons
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_addons,
            SUM(additional_branches) as total_branches,
            SUM(total_addon_cost) as total_monthly_cost,
            COUNT(DISTINCT tenant_id) as tenants_with_addons,
            AVG(total_addon_cost) as avg_addon_cost
        FROM branch_addons
        WHERE status = 'active'
    ");
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get pending requests count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as pending_count
        FROM branch_addon_requests
        WHERE status = 'pending'
    ");
    $stmt->execute();
    $pending = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return [
        'active_addons' => $stats['total_addons'] ?? 0,
        'total_additional_branches' => $stats['total_branches'] ?? 0,
        'total_monthly_revenue' => $stats['total_monthly_cost'] ?? 0,
        'tenants_with_addons' => $stats['tenants_with_addons'] ?? 0,
        'avg_addon_cost' => $stats['avg_addon_cost'] ?? 0,
        'pending_requests' => $pending['pending_count'] ?? 0
    ];
}

// Usage:
// $analytics = getBranchAddonAnalytics();
// echo "Active add-ons: {$analytics['active_addons']}";
// echo "Monthly revenue: {$analytics['total_monthly_revenue']}";
// echo "Pending requests: {$analytics['pending_requests']}";

// ============================================================================
// End of Examples
// ============================================================================
