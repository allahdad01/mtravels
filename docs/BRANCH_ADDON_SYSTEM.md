# Branch Add-On System Documentation

## Overview

The Branch Add-On System allows tenants with active subscriptions to request and purchase additional branches beyond their plan limits. It includes:

- **Default Branch Limits:**
  - **Basic Plan**: 1 branch
  - **Pro Plan**: 2 branches  
  - **Enterprise Plan**: 5 branches
  - **Umrah Plan**: 1 branch

- **Add-On Pricing**: $50 USD per additional branch per month (configurable)

- **Flexible Billing**: Monthly, Quarterly, or Yearly payment options

## Database Schema

### 1. `branch_addons` Table
Tracks active additional branch subscriptions for each tenant.

```sql
Fields:
- id: Unique identifier
- tenant_id: Reference to tenant
- plan_id: Reference to base plan
- base_branches: Branches included in the plan
- additional_branches: Extra branches purchased
- addon_price_per_branch: Price per additional branch ($50 default)
- currency: Payment currency (USD, AFS)
- total_addon_cost: Total monthly cost of add-ons
- billing_cycle: Monthly, Quarterly, or Yearly
- status: active, inactive, pending, cancelled
- next_renewal_date: When the add-on renews
- created_by: User ID who created this
- created_at/updated_at: Timestamps
```

### 2. `branch_addon_payments` Table
Tracks all payments for branch add-ons.

```sql
Fields:
- id: Unique identifier
- addon_id: Reference to branch_addon
- tenant_id: Reference to tenant
- amount: Payment amount
- currency: Payment currency
- payment_method: Stripe, PayPal, Bank Transfer, etc.
- transaction_id: Payment gateway transaction ID
- status: pending, completed, failed, refunded
- payment_date: When payment was processed
- period_start/period_end: Billing period dates
- receipt_url: URL to receipt
- notes: Payment notes
- created_by: User ID who recorded this
```

### 3. `branch_addon_requests` Table
Tracks tenant requests for additional branches (approval workflow).

```sql
Fields:
- id: Unique identifier
- tenant_id: Reference to tenant
- requested_additional_branches: Number of branches requested
- estimated_monthly_cost: Calculated cost
- currency: Payment currency
- status: pending, approved, rejected, cancelled
- approval_notes: Notes from approver
- approved_by: User ID who approved/rejected
- approved_at: When decision was made
- requested_at/updated_at: Timestamps
```

### 4. Updated `plans` Table
Added `max_branches` column to track branch limits per plan.

```sql
ALTER TABLE plans ADD COLUMN max_branches INT(11) NOT NULL DEFAULT 1;
```

## Core Classes

### BranchAddonManager Class
Located in: `/includes/BranchAddonManager.php`

#### Key Methods

**Getters:**
```php
getTenantPlanInfo($tenant_id)           // Get current plan details
getCurrentBranchCount($tenant_id)       // Get current active branches
getActiveBranchAddons($tenant_id)       // Get active add-ons
getTotalAdditionalBranches($tenant_id)  // Get total additional branches purchased
getMaxAllowedBranches($tenant_id)       // Get max allowed (plan + add-ons)
canAddMoreBranches($tenant_id)          // Check if can create more branches
```

**Request Management:**
```php
requestAdditionalBranches($tenant_id, $num_branches, $billing_cycle)
    // Tenant submits request for additional branches
    // Returns: ['success' => bool, 'request_id' => int, 'estimated_cost' => float]

approveBranchRequest($request_id, $approved_by_user_id, $approval_notes)
    // Super admin approves request (creates branch_addon record)
    // Returns: ['success' => bool, 'addon_id' => int, 'total_cost' => float]

rejectBranchRequest($request_id, $rejected_by_user_id, $reason)
    // Super admin rejects request
    // Returns: ['success' => bool, 'message' => string]
```

**Payment Processing:**
```php
calculateAddonCost($num_branches, $billing_cycle, $currency)
    // Calculate cost for N branches
    // Billing cycle multipliers: monthly=1x, quarterly=3x, yearly=12x

recordAddonPayment($addon_id, $tenant_id, $amount, $currency, $payment_method, $transaction_id)
    // Record successful payment and update renewal date
    // Returns: ['success' => bool, 'payment_id' => int]
```

**Data Retrieval:**
```php
getTenantAddonRequests($tenant_id, $status = null)       // Get tenant's requests
getPendingAddonRequests()                                 // Get all pending (super admin)
getAddonPaymentHistory($tenant_id)                        // Get payment history
```

**Cancellation:**
```php
cancelBranchAddon($addon_id, $tenant_id)
    // Cancel an active branch add-on
```

## User Interfaces

### 1. Tenant Portal: `/admin/request_branch_addon.php`

**Features:**
- View current plan and branch limits
- See current branch count and available slots
- Submit request for additional branches
- View pending requests
- See active add-ons
- View payment history

**Flow:**
1. Tenant selects number of branches and billing cycle
2. System calculates estimated cost
3. Tenant submits request
4. Request appears in "Pending Requests" section
5. Once approved by admin, appears in "Active Add-ons"

### 2. Super Admin Panel: `/super_admin/manage_branch_addons.php`

**Features:**
- View all pending branch add-on requests
- Approve requests with optional notes
- Reject requests with reason
- See request details (tenant, plan, requested branches, cost)

**Approval Flow:**
1. Request submitted by tenant → "pending" status
2. Super admin reviews on manage_branch_addons.php
3. Clicks "Approve" or "Reject" button
4. If approved:
   - Creates record in `branch_addons` table
   - Sets status to "active"
   - Tenant can now create additional branches
5. If rejected:
   - Request marked as "rejected"
   - Admin provides reason to tenant

## Integration Points

### 1. Branch Creation Validation
When creating a new branch, check:
```php
$addon_manager = new BranchAddonManager($conn, $tenant_id);
if (!$addon_manager->canAddMoreBranches()) {
    // Show error: Max branches reached
}
```

### 2. Subscription Changes
When plan is upgraded/downgraded:
- Review existing add-ons
- Update branch limits accordingly
- Consider cost implications

### 3. Payment Integration
For payment processing, capture:
```php
$addon_manager->recordAddonPayment(
    $addon_id, 
    $tenant_id, 
    $amount, 
    $currency, 
    'stripe', 
    $stripe_transaction_id
);
```

## Pricing Configuration

### Default Add-On Pricing
Currently set to **$50 USD per branch per month** in `BranchAddonManager::DEFAULT_ADDON_PRICE`.

**Billing Cycle Multipliers:**
- Monthly: 1x ($50/month)
- Quarterly: 3x ($150 for 3 months)
- Yearly: 12x ($600 for 12 months)

**To Change Pricing:**
1. Update `BranchAddonManager::DEFAULT_ADDON_PRICE` constant
2. Consider impact on existing add-ons
3. New calculations apply to new requests only

## Workflow Examples

### Example 1: Basic Plan Tenant Requests 2 Additional Branches

1. **Request Phase:**
   - Tenant: Basic Plan (1 branch included)
   - Current branches: 1
   - Max allowed: 1
   - Requests: +2 branches
   - System calculates: 2 × $50 = $100/month or $600/year

2. **Approval Phase:**
   - Super admin reviews request
   - Clicks "Approve" with notes "Approved for Q1 campaign"
   - System creates branch_addon record with:
     - additional_branches: 2
     - total_addon_cost: $100 (monthly) / $600 (yearly)
     - status: active

3. **New Limits:**
   - Max allowed: 1 (plan) + 2 (addon) = 3 branches
   - Tenant can now create 2 more branches
   - Current usage: 1/3 (can add 2 more)

4. **Payment Phase:**
   - Tenant pays invoice for $100/month
   - Payment recorded in branch_addon_payments
   - next_renewal_date set to 1 month from payment

### Example 2: Pro Plan Upgrade with Existing Add-ons

1. **Before Upgrade:**
   - Plan: Pro (2 branches)
   - Current branches: 2
   - Add-ons: 2 additional branches ($100/month)
   - Max allowed: 4 branches

2. **Upgrade to Enterprise:**
   - New plan: Enterprise (5 branches)
   - Add-ons remain active (can be cancelled if desired)
   - New max allowed: 5 + 2 = 7 branches

## Notifications & Alerts

### Tenant Alerts
- Request submitted confirmation
- Request approved notification with new branch limits
- Request rejected notification with reason
- Payment due reminder (before renewal date)
- Payment received confirmation

### Admin Alerts
- New branch add-on request pending approval
- Approval submitted confirmation
- Payment recorded for add-on
- Add-on renewal due soon

## Best Practices

### For Tenants
1. **Plan ahead** - Request branches before you need them
2. **Review costs** - Understand monthly/quarterly/yearly pricing
3. **Monitor usage** - Check available branches regularly
4. **Keep updated** - Renew payments to maintain active add-ons

### For Admins
1. **Review requests promptly** - Don't leave tenants waiting
2. **Provide feedback** - Use approval notes to explain decisions
3. **Track payments** - Monitor renewal dates and collect payments
4. **Document decisions** - Keep audit trail of approvals/rejections

## Troubleshooting

### Tenant Can't Create Branch
- Check current branch count: `getCurrentBranchCount()`
- Check max allowed: `getMaxAllowedBranches()`
- Verify active add-ons: `getActiveBranchAddons()`
- Check for pending requests: `getTenantAddonRequests($tenant_id, 'pending')`

### Add-on Not Appearing
- Verify request is approved (status = "approved")
- Check branch_addon record exists
- Verify status = "active"
- Check tenant_id matches

### Payment Issues
- Verify payment record in branch_addon_payments
- Check transaction_id from payment gateway
- Verify amount matches calculated cost
- Check payment_date and period dates

## Security Considerations

1. **CSRF Protection** - All forms use CSRF tokens
2. **Authorization** - Verify tenant owns the add-on before updating
3. **Input Validation** - Validate branches count, amounts, dates
4. **Audit Logging** - All approvals/rejections logged with user ID
5. **Payment Validation** - Verify transaction_id before marking complete

## API Reference

### BranchAddonManager Constructor
```php
$addon_manager = new BranchAddonManager($connection, $tenant_id = null);
```

### Common Usage Patterns

**Check branch availability:**
```php
$addon_manager = new BranchAddonManager($conn, $_SESSION['tenant_id']);
if ($addon_manager->canAddMoreBranches()) {
    // Allow branch creation
}
```

**Get tenant status:**
```php
$plan = $addon_manager->getTenantPlanInfo();
$current = $addon_manager->getCurrentBranchCount();
$max = $addon_manager->getMaxAllowedBranches();
echo "Current: $current, Max: $max, Plan: " . $plan['name'];
```

**Request branches:**
```php
$result = $addon_manager->requestAdditionalBranches($tenant_id, 3, 'monthly');
if ($result['success']) {
    echo "Request created. Cost: {$result['estimated_cost']} {$result['currency']}";
}
```

## Future Enhancements

- [ ] Automated billing/invoicing system
- [ ] Payment gateway integration (Stripe, PayPal)
- [ ] Tier-based pricing (discounts for more branches)
- [ ] Auto-renewal with payment method on file
- [ ] Usage analytics and reporting
- [ ] Downgrade with proration calculation
- [ ] Bulk branch requests for enterprise customers
- [ ] SLA guarantees for request approval time
