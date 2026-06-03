<?php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once '../config.php';
require_once '../includes/db.php';

// Include security module
require_once 'security.php';

// Check if user is a super admin (system administrator, not tenant-based)
if (!check_super_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../includes/BranchAddonManager.php';

// Check if $pdo is available
if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Helper function to get currency symbol
function getCurrencySymbol($currencyCode) {
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'AFN' => '؋',
        'AED' => 'د.إ',
        'INR' => '₹',
        'PKR' => '₨',
    ];
    return $symbols[$currencyCode] ?? $currencyCode;
}

$subscription_id = intval($_GET['subscription_id'] ?? 0);

if (!$subscription_id) {
    echo '<div class="sa-alert sa-alert-danger">Invalid subscription ID</div>';
    exit();
}

try {
    // Get subscription details
    $stmt = $pdo->prepare("
        SELECT ts.*, t.name as tenant_name, t.identifier as tenant_identifier,
               p.name as plan_name, p.price as plan_price
        FROM tenant_subscriptions ts
        LEFT JOIN tenants t ON ts.tenant_id = t.id
        LEFT JOIN plans p ON ts.plan_id = p.id
        WHERE ts.id = ?
    ");
    $stmt->execute([$subscription_id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
         echo '<div class="sa-alert sa-alert-danger">Subscription not found</div>';
         exit();
     }

     // Get active add-ons for this tenant (branch + users + communication)
     $stmt = $pdo->prepare("
         SELECT
             COALESCE((
                 SELECT SUM(ba.total_addon_cost)
                 FROM branch_addons ba
                 WHERE ba.tenant_id = ? AND ba.status = 'active' AND ba.billing_cycle = ?
             ), 0)
             +
             COALESCE((
                 SELECT SUM(ua.total_addon_cost)
                 FROM user_addons ua
                 WHERE ua.tenant_id = ? AND ua.status = 'active' AND ua.billing_cycle = ?
             ), 0)
             +
             COALESCE((
                 SELECT SUM(ca.addon_price)
                 FROM communication_addons ca
                 WHERE ca.tenant_id = ? AND ca.status = 'active' AND ca.billing_cycle = ?
             ), 0) as total_addon_cost
     ");
     $stmt->execute([
         $subscription['tenant_id'], $subscription['billing_cycle'],
         $subscription['tenant_id'], $subscription['billing_cycle'],
         $subscription['tenant_id'], $subscription['billing_cycle']
     ]);
     $addonCostResult = $stmt->fetch(PDO::FETCH_ASSOC);
     $totalAddonCost = floatval($addonCostResult['total_addon_cost'] ?? 0);
     $totalAmount = floatval($subscription['amount']) + $totalAddonCost;

     // Get payment history
    $stmt = $pdo->prepare("
        SELECT sp.*, u.name as processed_by_name
        FROM subscription_payments sp
        LEFT JOIN users u ON sp.processed_by = u.id
        WHERE sp.subscription_id = ?
        ORDER BY sp.payment_date DESC, sp.created_at DESC
    ");
    $stmt->execute([$subscription_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="sa-card" style="margin-bottom: 20px;">
    <div class="sa-card-body">
        <h5 style="margin: 0 0 16px 0; font-size: 1rem; display: flex; align-items: center; gap: 8px; color: #333;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4099ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            Subscription Details
        </h5>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 0.85rem;">
            <div>
                <div style="margin-bottom: 8px;"><strong>Tenant:</strong> <?= htmlspecialchars($subscription['tenant_name']) ?> (<?= htmlspecialchars($subscription['tenant_identifier']) ?>)</div>
                <div style="margin-bottom: 8px;"><strong>Plan:</strong> <?= htmlspecialchars($subscription['plan_name']) ?></div>
                <div style="margin-bottom: 8px;">
                    <strong>Status:</strong>
                    <span class="pill <?= $subscription['status'] === 'active' ? 'pill-green' : ($subscription['status'] === 'pending' ? 'pill-amber' : 'pill-red') ?>">
                        <?= ucfirst(htmlspecialchars($subscription['status'])) ?>
                    </span>
                </div>
            </div>
            <div>
                <div style="margin-bottom: 8px;"><strong>Billing Cycle:</strong> <?= ucfirst(htmlspecialchars($subscription['billing_cycle'])) ?></div>
                <div style="margin-bottom: 8px;">
                    <strong>Amount:</strong>
                    <div>
                        <?php $symbol = getCurrencySymbol($subscription['currency']); ?>
                        <span style="font-weight: 600; color: #2ed8b6;"><?= $symbol . number_format($subscription['amount'], 2) ?></span>
                        <?php if ($totalAddonCost > 0): ?>
                        <br><small style="color: #4099ff;">+ <?= $symbol . number_format($totalAddonCost, 2) ?> add-ons = <?= $symbol . number_format($totalAmount, 2) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="margin-bottom: 8px;"><strong>Next Billing:</strong> <?= $subscription['next_billing_date'] ? date('M d, Y', strtotime($subscription['next_billing_date'])) : 'N/A' ?></div>
            </div>
        </div>
    </div>
</div>

<div class="sa-card">
    <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
        <h5 style="margin: 0; font-size: 1rem; display: flex; align-items: center; gap: 8px; color: #333;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4099ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            Payment History
        </h5>
        <span style="background: rgba(64,153,255,0.1); color: #4099ff; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;"><?= count($payments) ?> payments</span>
    </div>
    <?php if (empty($payments)): ?>
    <div style="text-align: center; padding: 40px 20px;">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 8px;"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
        <p style="margin: 0; color: var(--muted); font-size: 0.85rem;">No payments recorded for this subscription</p>
    </div>
    <?php else: ?>
    <div class="sa-table-wrap" style="border: none; border-radius: 0;">
        <table class="sa-table">
            <thead>
                <tr>
                    <th>Payment Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Receipt</th>
                    <th>Transaction ID</th>
                    <th>Processed By</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                 <?php $paymentSymbol = getCurrencySymbol($payment['currency']); ?>
                 <tr>
                     <td><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                     <td><strong style="color: #2ed8b6;"><?= $paymentSymbol . number_format($payment['amount'], 2) ?></strong> <span style="font-size: 0.75rem; color: var(--muted);"><?= htmlspecialchars($payment['currency']) ?></span></td>
                    <td><?= htmlspecialchars($payment['payment_method'] ?: 'N/A') ?></td>
                    <td><?= htmlspecialchars($payment['receipt_number'] ?: 'N/A') ?></td>
                    <td>
                        <?php if ($payment['transaction_id']): ?>
                        <code style="color: var(--muted); font-size: 0.8rem;"><?= htmlspecialchars($payment['transaction_id']) ?></code>
                        <?php else: ?>
                        <span style="color: var(--muted);">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($payment['processed_by_name'] ?: 'System') ?></td>
                    <td>
                        <?php if ($payment['notes']): ?>
                        <span title="<?= htmlspecialchars($payment['notes']) ?>">
                            <?= strlen($payment['notes']) > 50 ? htmlspecialchars(substr($payment['notes'], 0, 50)) . '...' : htmlspecialchars($payment['notes']) ?>
                        </span>
                        <?php else: ?>
                        <span style="color: var(--muted);">No notes</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
} catch (Exception $e) {
    echo '<div class="sa-alert sa-alert-danger">Error loading payment history: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
