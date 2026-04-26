<?php
/**
 * Request Communication Add-ons (WhatsApp / SMTP)
 * Tenant interface.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/CsrfProtection.php';
require_once '../includes/CommunicationAddonManager.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'tenant_super_admin') {
    header('Location: ../login.php');
    exit();
}

$tenant_id = intval($_SESSION['tenant_id'] ?? 0);
$addon_manager = new CommunicationAddonManager($pdo, $tenant_id);
$plan_stmt = $pdo->prepare("
    SELECT ts.currency, ts.billing_cycle
    FROM tenant_subscriptions ts
    WHERE ts.tenant_id = ? AND ts.status = 'active'
    ORDER BY ts.start_date DESC
    LIMIT 1
");
$plan_stmt->execute([$tenant_id]);
$plan_row = $plan_stmt->fetch(PDO::FETCH_ASSOC);
$currency = htmlspecialchars($plan_row['currency'] ?? 'USD');
$default_billing_cycle = 'monthly';
if (!empty($plan_row['billing_cycle']) && in_array($plan_row['billing_cycle'], ['monthly', 'quarterly', 'yearly'], true)) {
    $default_billing_cycle = $plan_row['billing_cycle'];
}

if (!empty($_SESSION['comm_addon_error'])) {
    $error = $_SESSION['comm_addon_error'];
    unset($_SESSION['comm_addon_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_addon'])) {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid CSRF token.';
    } else {
        $addon_type = $_POST['addon_type'] ?? '';
        $billing_cycle = $_POST['billing_cycle'] ?? $default_billing_cycle;
        if (!in_array($addon_type, ['whatsapp', 'smtp'], true)) {
            $error = 'Invalid add-on type.';
        } elseif (!in_array($billing_cycle, ['monthly', 'quarterly', 'yearly'], true)) {
            $error = 'Invalid billing cycle.';
        } else {
        $result = $addon_manager->requestAddon($tenant_id, $addon_type, $billing_cycle);
        if (!empty($result['success'])) {
            $success = ucfirst($addon_type) . ' add-on request submitted successfully.';
        } else {
            $error = $result['message'] ?? 'Failed to submit request.';
        }
        }
    }
}

$active_addons = $addon_manager->getActiveAddons($tenant_id);
$pending_requests = $addon_manager->getTenantAddonRequests($tenant_id, 'pending');
$wa_pricing = $addon_manager->getAddonPricing($tenant_id, 'whatsapp');
$smtp_pricing = $addon_manager->getAddonPricing($tenant_id, 'smtp');
$default_estimated_cost = floatval($wa_pricing[$default_billing_cycle] ?? $wa_pricing['monthly']);

include 'header.php';
?>

<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="card">
            <div class="card-header">
                <h5>Request Communication Add-on</h5>
                <small class="text-muted">Purchase WhatsApp and SMTP access for your tenant</small>
            </div>
            <div class="card-body">
                <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CsrfProtection::getToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="request_addon" value="1">

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="addon_type">Add-on Type</label>
                            <select class="form-control" id="addon_type" name="addon_type" required>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="smtp">SMTP</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="billing_cycle">Billing Cycle</label>
                            <select class="form-control" id="billing_cycle" name="billing_cycle" required>
                                <option value="monthly" <?= $default_billing_cycle === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                <option value="quarterly" <?= $default_billing_cycle === 'quarterly' ? 'selected' : '' ?>>Quarterly</option>
                                <option value="yearly" <?= $default_billing_cycle === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <strong>Pricing:</strong><br>
                        WhatsApp: <?= number_format($wa_pricing['monthly'], 2) ?> <?= $currency ?>/mo, <?= number_format($wa_pricing['quarterly'], 2) ?> <?= $currency ?>/quarter, <?= number_format($wa_pricing['yearly'], 2) ?> <?= $currency ?>/year<br>
                        SMTP: <?= number_format($smtp_pricing['monthly'], 2) ?> <?= $currency ?>/mo, <?= number_format($smtp_pricing['quarterly'], 2) ?> <?= $currency ?>/quarter, <?= number_format($smtp_pricing['yearly'], 2) ?> <?= $currency ?>/year
                    </div>

                    <div class="alert alert-secondary" style="display:flex;justify-content:space-between;align-items:center;">
                        <span>Estimated Cost</span>
                        <strong><span id="estimatedCost"><?= number_format($default_estimated_cost, 2) ?></span> <?= $currency ?></strong>
                    </div>

                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5>Pending Requests</h5>
            </div>
            <div class="card-body">
                <?php if (empty($pending_requests)): ?>
                <p class="text-muted mb-0">No pending requests.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Billing</th>
                                <th>Cost</th>
                                <th>Status</th>
                                <th>Requested At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_requests as $item): ?>
                            <tr>
                                <td><?= ucfirst(htmlspecialchars($item['addon_type'])) ?></td>
                                <td><?= ucfirst(htmlspecialchars($item['billing_cycle'])) ?></td>
                                <td><?= htmlspecialchars($item['currency']) . ' ' . number_format(floatval($item['estimated_monthly_cost']), 2) ?></td>
                                <td><span class="badge badge-warning">Pending</span></td>
                                <td><?= htmlspecialchars($item['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5>Active Communication Add-ons</h5>
            </div>
            <div class="card-body">
                <?php if (empty($active_addons)): ?>
                <p class="text-muted mb-0">No active communication add-ons yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Billing</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Activated At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_addons as $addon): ?>
                            <tr>
                                <td><?= ucfirst(htmlspecialchars($addon['addon_type'])) ?></td>
                                <td><?= ucfirst(htmlspecialchars($addon['billing_cycle'])) ?></td>
                                <td><?= htmlspecialchars($addon['currency']) . ' ' . number_format(floatval($addon['addon_price']), 2) ?></td>
                                <td><span class="badge badge-success">Active</span></td>
                                <td><?= htmlspecialchars($addon['created_at']) ?></td>
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

<?php include 'footer.php'; ?>

<script>
const commAddonPricing = {
    whatsapp: {
        monthly: <?= floatval($wa_pricing['monthly']) ?>,
        quarterly: <?= floatval($wa_pricing['quarterly']) ?>,
        yearly: <?= floatval($wa_pricing['yearly']) ?>
    },
    smtp: {
        monthly: <?= floatval($smtp_pricing['monthly']) ?>,
        quarterly: <?= floatval($smtp_pricing['quarterly']) ?>,
        yearly: <?= floatval($smtp_pricing['yearly']) ?>
    }
};

function updateEstimatedCommCost() {
    const addonType = document.getElementById('addon_type')?.value || 'whatsapp';
    const cycle = document.getElementById('billing_cycle')?.value || 'monthly';
    const amount = (commAddonPricing[addonType] && commAddonPricing[addonType][cycle]) ? commAddonPricing[addonType][cycle] : 0;
    const costEl = document.getElementById('estimatedCost');
    if (costEl) {
        costEl.textContent = Number(amount).toFixed(2);
    }
}

document.getElementById('addon_type')?.addEventListener('change', updateEstimatedCommCost);
document.getElementById('billing_cycle')?.addEventListener('change', updateEstimatedCommCost);
updateEstimatedCommCost();
</script>
