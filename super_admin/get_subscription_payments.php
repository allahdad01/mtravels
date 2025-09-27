<?php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in with proper role (super admin)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Database connection
require_once '../config.php';
require_once '../includes/conn.php';
require_once '../includes/db.php';

// Check if $pdo is available
if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$subscription_id = intval($_GET['subscription_id'] ?? 0);

if (!$subscription_id) {
    echo '<div class="alert alert-danger">Invalid subscription ID</div>';
    exit();
}

try {
    // Get subscription details
    $stmt = $pdo->prepare("
        SELECT ts.*, t.name as tenant_name, t.identifier as tenant_identifier,
               p.name as plan_name, p.price as plan_price
        FROM tenant_subscriptions ts
        LEFT JOIN tenants t ON ts.tenant_id = t.id
        LEFT JOIN plans p ON ts.plan_id = p.name
        WHERE ts.id = ?
    ");
    $stmt->execute([$subscription_id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        echo '<div class="alert alert-danger">Subscription not found</div>';
        exit();
    }

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

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="feather icon-credit-card mr-2"></i>
                    Subscription Details
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Tenant:</strong> <?= htmlspecialchars($subscription['tenant_name']) ?> (<?= htmlspecialchars($subscription['tenant_identifier']) ?>)</p>
                        <p><strong>Plan:</strong> <?= htmlspecialchars($subscription['plan_name']) ?></p>
                        <p><strong>Status:</strong>
                            <span class="badge badge-<?= $subscription['status'] === 'active' ? 'success' : ($subscription['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                <?= ucfirst(htmlspecialchars($subscription['status'])) ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Billing Cycle:</strong> <?= ucfirst(htmlspecialchars($subscription['billing_cycle'])) ?></p>
                        <p><strong>Amount:</strong> $<?= number_format($subscription['amount'], 2) ?> <?= htmlspecialchars($subscription['currency']) ?></p>
                        <p><strong>Next Billing:</strong> <?= $subscription['next_billing_date'] ? date('M d, Y', strtotime($subscription['next_billing_date'])) : 'N/A' ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="feather icon-list mr-2"></i>
                    Payment History
                </h5>
                <span class="badge badge-info"><?= count($payments) ?> payments</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($payments)): ?>
                <div class="text-center py-4">
                    <i class="feather icon-inbox text-muted mb-2" style="font-size: 2rem;"></i>
                    <p class="text-muted">No payments recorded for this subscription</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="bg-light">
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
                            <tr>
                                <td><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                <td>$<?= number_format($payment['amount'], 2) ?> <?= htmlspecialchars($payment['currency']) ?></td>
                                <td><?= htmlspecialchars($payment['payment_method'] ?: 'N/A') ?></td>
                                <td><?= htmlspecialchars($payment['receipt_number'] ?: 'N/A') ?></td>
                                <td>
                                    <?php if ($payment['transaction_id']): ?>
                                    <code class="text-muted small"><?= htmlspecialchars($payment['transaction_id']) ?></code>
                                    <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($payment['processed_by_name'] ?: 'System') ?></td>
                                <td>
                                    <?php if ($payment['notes']): ?>
                                    <span title="<?= htmlspecialchars($payment['notes']) ?>">
                                        <?= strlen($payment['notes']) > 50 ? htmlspecialchars(substr($payment['notes'], 0, 50)) . '...' : htmlspecialchars($payment['notes']) ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">No notes</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
} catch (Exception $e) {
    error_log("Error in get_subscription_payments.php: " . $e->getMessage());
    echo '<div class="alert alert-danger">Error loading payment history: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>