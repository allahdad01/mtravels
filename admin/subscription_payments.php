<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

// Session timeout (30 mins)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check user role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    error_log("Unauthorized access attempt: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

require_once '../config.php';
require_once '../includes/conn.php';
require_once '../includes/db.php';

if (!isset($pdo) || !$pdo) {
    die("Database connection failed. Please contact administrator.");
}

// Get tenant_id from session
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    header('Location: dashboard.php');
    exit();
}

// Fetch tenant payment status
$tenant_payment_status = 'current';
try {
    $stmt = $pdo->prepare("SELECT payment_status, payment_due_date FROM tenants WHERE id = ?");
    $stmt->execute([$tenant_id]);
    $tenant_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($tenant_data) {
        $tenant_payment_status = $tenant_data['payment_status'];
        $payment_due_date = $tenant_data['payment_due_date'];
    }
} catch (PDOException $e) {
    // ignore
}

// Fetch tenant subscriptions
try {
    $stmt = $pdo->prepare("
        SELECT ts.*, p.name AS plan_name, p.price AS plan_price
        FROM tenant_subscriptions ts
        LEFT JOIN plans p ON ts.plan_id = p.id
        WHERE ts.tenant_id = :tenant_id
        ORDER BY ts.created_at DESC
    ");
    $stmt->execute(['tenant_id' => $tenant_id]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching subscriptions: " . $e->getMessage());
    $subscriptions = [];
}

// Fetch subscription payment history
try {
    $stmt = $pdo->prepare("
        SELECT sp.*, ts.plan_id, p.name AS plan_name, u.name AS processed_by_name
        FROM subscription_payments sp
        LEFT JOIN tenant_subscriptions ts ON sp.subscription_id = ts.id
        LEFT JOIN plans p ON ts.plan_id = p.id
        LEFT JOIN users u ON sp.processed_by = u.id
        WHERE ts.tenant_id = :tenant_id
        ORDER BY sp.payment_date DESC, sp.created_at DESC
    ");
    $stmt->execute(['tenant_id' => $tenant_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching payments: " . $e->getMessage());
    $payments = [];
}

// Handle HesabPay redirect
if (isset($_GET['payment'], $_GET['subscription_id'])) {
    $payment_status = $_GET['payment'];
    $sub_id = intval($_GET['subscription_id']);

    // Safely decode JSON from data param
    $data = null;
    if (!empty($_GET['data'])) {
        $raw = urldecode($_GET['data']);
        $data = json_decode($raw, true);
        if ($data === null && strpos($raw, '{') !== false) {
            // fallback for malformed JSON
            $raw = str_replace(['\\"', "'"], ['"', '"'], $raw);
            $data = json_decode($raw, true);
        }
    }

    if ($payment_status === 'success') {
        try {
            $stmt = $pdo->prepare("SELECT amount, currency, billing_cycle, start_date, next_billing_date FROM tenant_subscriptions WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$sub_id, $tenant_id]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($subscription) {
                $processed_by = $_SESSION['user_id'] ?? null;
                $transaction_id = $data['transaction_id'] ?? null;

                // Insert payment record
                $stmt2 = $pdo->prepare("
                    INSERT INTO subscription_payments
                    (subscription_id, amount, currency, payment_method, payment_date, processed_by, transaction_id)
                    VALUES (?, ?, ?, 'HesabPay', CURDATE(), ?, ?)
                ");
                $stmt2->execute([$sub_id, $subscription['amount'], $subscription['currency'], $processed_by, $transaction_id]);

                // Update subscription
                $pdo->prepare("
                    UPDATE tenant_subscriptions
                    SET status = 'active', last_payment_date = CURDATE()
                    WHERE id = ? AND tenant_id = ?
                ")->execute([$sub_id, $tenant_id]);

                // Update next billing date
                $base_date = $subscription['next_billing_date'] ?: $subscription['start_date'];
                $next_billing_date = null;
                switch ($subscription['billing_cycle']) {
                    case 'monthly':
                        $next_billing_date = date('Y-m-d', strtotime('+1 month', strtotime($base_date)));
                        break;
                    case 'quarterly':
                        $next_billing_date = date('Y-m-d', strtotime('+3 months', strtotime($base_date)));
                        break;
                    case 'yearly':
                        $next_billing_date = date('Y-m-d', strtotime('+1 year', strtotime($base_date)));
                        break;
                }
                if ($next_billing_date) {
                    $pdo->prepare("UPDATE tenant_subscriptions SET next_billing_date = ? WHERE id = ? AND tenant_id = ?")
                        ->execute([$next_billing_date, $sub_id, $tenant_id]);
                }

                $payment_message = "✅ Payment successful! Subscription activated.";
            }
        } catch (PDOException $e) {
            error_log("Error processing payment: " . $e->getMessage());
            $payment_message = "⚠️ Error processing payment.";
        }
    } elseif ($payment_status === 'failed') {
        $payment_message = "❌ Payment failed. Please try again.";
    }
}
?>


<?php include '../includes/header.php'; ?>

<style>
.subscription-card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s;
}

.subscription-card:hover {
    transform: translateY(-2px);
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: 500;
}

.status-active {
    background-color: #d4edda;
    color: #155724;
}

.status-pending {
    background-color: #fff3cd;
    color: #856404;
}

.status-expired {
    background-color: #f8d7da;
    color: #721c24;
}

.status-cancelled {
    background-color: #e2e3e5;
    color: #383d41;
}
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h3 class="mb-0"><?= __('subscription_payments') ?></h3>
                                        <p class="text-muted mb-0"><?= __('view_and_manage_your_tenant_subscription_payments') ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($payment_message)): ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="alert alert-<?= $payment_status === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($payment_message) ?>
                                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Payment Status Alert -->
                        <?php if ($tenant_payment_status !== 'current'): ?>
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="alert alert-<?= $tenant_payment_status === 'warning' ? 'warning' : 'danger' ?> alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    <strong>Payment Status:</strong>
                                    <?php if ($tenant_payment_status === 'warning'): ?>
                                        Your subscription payment is due soon.
                                        <?php if (isset($payment_due_date)): ?>
                                        Please ensure payment is made before <?= date('M d, Y', strtotime($payment_due_date)) ?>.
                                        <?php endif; ?>
                                    <?php elseif ($tenant_payment_status === 'overdue'): ?>
                                        Your subscription payment is overdue.
                                        <?php if (isset($payment_due_date)): ?>
                                        Payment was due on <?= date('M d, Y', strtotime($payment_due_date)) ?>.
                                        <?php endif; ?>
                                        Please contact billing immediately.
                                    <?php elseif ($tenant_payment_status === 'suspended'): ?>
                                        Your account is suspended due to non-payment.
                                        Please contact billing to restore access.
                                    <?php endif; ?>
                                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Subscription Overview -->
                        <div class="row mb-4">
                            <?php if (count($subscriptions) > 0): ?>
                                <?php foreach ($subscriptions as $subscription): ?>
                                    <div class="col-md-6 col-xl-4 mb-4">
                                        <div class="card subscription-card">
                                            <div class="card-header bg-primary text-white">
                                                <h5 class="mb-0"><?= htmlspecialchars($subscription['plan_name'] ?? 'Subscription') ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <span class="status-badge status-<?= strtolower($subscription['status']) ?>">
                                                        <?= ucfirst($subscription['status']) ?>
                                                    </span>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6">
                                                        <small class="text-muted"><?= __('amount') ?></small>
                                                        <h6 class="mb-0">$<?= number_format($subscription['amount'], 2) ?> <?= htmlspecialchars($subscription['currency']) ?></h6>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted"><?= __('billing_cycle') ?></small>
                                                        <h6 class="mb-0"><?= ucfirst($subscription['billing_cycle']) ?></h6>
                                                    </div>
                                                </div>
                                                <hr>
                                                <div class="row">
                                                    <div class="col-6">
                                                        <small class="text-muted"><?= __('start_date') ?></small>
                                                        <p class="mb-0 small"><?= date('M d, Y', strtotime($subscription['start_date'])) ?></p>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted"><?= __('next_billing') ?></small>
                                                        <p class="mb-0 small">
                                                            <?php if ($subscription['next_billing_date']): ?>
                                                                <?= date('M d, Y', strtotime($subscription['next_billing_date'])) ?>
                                                            <?php else: ?>
                                                                N/A
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <?php if ($subscription['last_payment_date']): ?>
                                                    <div class="mt-2">
                                                        <small class="text-muted"><?= __('last_payment') ?>: <?= date('M d, Y', strtotime($subscription['last_payment_date'])) ?></small>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                    <form method="post" action="process_subscription_payment.php" class="mt-2">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="subscription_id" value="<?php echo $subscription['id']; ?>">
                                                        <input type="hidden" name="amount" value="<?php echo $subscription['amount']; ?>">
                                                        <input type="hidden" name="currency" value="<?php echo $subscription['currency']; ?>">
                                                        <button type="submit" class="btn btn-primary btn-sm">Pay Now</button>
                                                    </form>
                                                
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                                            <h5><?= __('no_subscriptions_found') ?></h5>
                                            <p class="text-muted"><?= __('contact_admin_for_subscription_setup') ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Payment History -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-history mr-2"></i><?= __('payment_history') ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (count($payments) > 0): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th><?= __('payment_date') ?></th>
                                                            <th><?= __('amount') ?></th>
                                                            <th><?= __('currency') ?></th>
                                                            <th><?= __('plan') ?></th>
                                                            <th><?= __('payment_method') ?></th>
                                                            <th><?= __('receipt_number') ?></th>
                                                            <th><?= __('processed_by') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($payments as $payment): ?>
                                                            <tr>
                                                                <td><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                                                <td>
                                                                    <span class="font-weight-bold text-success">
                                                                        $<?= number_format($payment['amount'], 2) ?>
                                                                    </span>
                                                                </td>
                                                                <td><?= htmlspecialchars($payment['currency']) ?></td>
                                                                <td>
                                                                    <span class="badge badge-primary">
                                                                        <?= htmlspecialchars($payment['plan_name'] ?: $payment['plan_id']) ?>
                                                                    </span>
                                                                </td>
                                                                <td><?= htmlspecialchars($payment['payment_method'] ?: 'N/A') ?></td>
                                                                <td>
                                                                    <?php if ($payment['receipt_number']): ?>
                                                                        <span class="badge badge-info"><?= htmlspecialchars($payment['receipt_number']) ?></span>
                                                                    <?php else: ?>
                                                                        N/A
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?= htmlspecialchars($payment['processed_by_name'] ?: 'System') ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                                <h5><?= __('no_payment_history_found') ?></h5>
                                                <p class="text-muted"><?= __('payment_history_will_appear_here') ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Required Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
// Listen for paymentSuccess event from HesabPay
window.addEventListener('message', function(event) {
    // Validate origin for security
    if (event.origin !== 'https://checkout.hesabpay.com' && event.origin !== 'https://api-sandbox.hesab.com') {
        return;
    }

    if (event.data && event.data.type === 'paymentSuccess') {
        console.log('Payment successful:', event.data);

        // Extract payment details
        const { success, message, transaction_id } = event.data;

        if (success) {
            // Show success message
            alert('Payment completed successfully! Transaction ID: ' + transaction_id);

            // Optionally redirect or refresh the page
            window.location.reload();
        } else {
            alert('Payment failed: ' + message);
        }
    }
});
</script>

<?php include '../includes/admin_footer.php'; ?>