<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in with proper role (super admin)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once '../config.php';
require_once '../includes/conn.php';
require_once '../includes/db.php';

// Check if $pdo is available
if (!isset($pdo) || !$pdo) {
    die("Database connection failed. Please contact administrator.");
}

// Handle form submissions before including header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'record_payment') {
            // Record a new subscription payment
            $subscription_id = intval($_POST['subscription_id']);
            $amount = floatval($_POST['amount']);
            $currency = $_POST['currency'];
            $payment_date = $_POST['payment_date'];
            $payment_method = $_POST['payment_method'];
            $transaction_id = $_POST['transaction_id'];
            $receipt_number = $_POST['receipt_number'];
            $notes = $_POST['notes'];
            $processed_by = $_SESSION['user_id'];

            try {
                // Start transaction
                $pdo->beginTransaction();

                // Insert payment record
                $stmt = $pdo->prepare("
                    INSERT INTO subscription_payments
                    (subscription_id, amount, currency, payment_date, payment_method, transaction_id, receipt_number, notes, processed_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$subscription_id, $amount, $currency, $payment_date, $payment_method, $transaction_id, $receipt_number, $notes, $processed_by]);

                // Update subscription last_payment_date and next_billing_date
                $stmt = $pdo->prepare("SELECT billing_cycle, tenant_id FROM tenant_subscriptions WHERE id = ?");
                $stmt->execute([$subscription_id]);
                $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($subscription) {
                    $billing_cycle = $subscription['billing_cycle'];
                    $tenant_id = $subscription['tenant_id'];
                    $next_billing_date = calculateNextBillingDate($payment_date, $billing_cycle);

                    $stmt = $pdo->prepare("
                        UPDATE tenant_subscriptions
                        SET last_payment_date = ?, next_billing_date = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$payment_date, $next_billing_date, $subscription_id]);

                    // Update tenant payment status to current and reset warning flag
                    $stmt = $pdo->prepare("
                        UPDATE tenants
                        SET payment_status = 'current',
                            payment_due_date = ?,
                            last_payment_date = ?,
                            payment_warning_sent = 0,
                            status = 'active',
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$next_billing_date, $payment_date, $tenant_id]);
                }

                $pdo->commit();
                // Redirect to prevent form resubmission
                header('Location: subscription_payments.php?success=1');
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = "Error recording payment: " . $e->getMessage();
            }
        }
    }
}

require_once '../includes/header_super_admin.php';

// Function to calculate next billing date
function calculateNextBillingDate($payment_date, $billing_cycle) {
    $date = new DateTime($payment_date);

    switch ($billing_cycle) {
        case 'monthly':
            $date->modify('+1 month');
            break;
        case 'quarterly':
            $date->modify('+3 months');
            break;
        case 'yearly':
            $date->modify('+1 year');
            break;
        default:
            $date->modify('+1 month'); // Default to monthly
    }

    return $date->format('Y-m-d');
}

// Fetch all subscriptions with tenant info and payment history
try {
    $stmt = $pdo->prepare("
        SELECT ts.*, t.name as tenant_name, t.identifier as tenant_identifier,
               p.name as plan_name, p.price as plan_price,
               COUNT(sp.id) as payment_count,
               COALESCE(SUM(sp.amount), 0) as total_paid
        FROM tenant_subscriptions ts
        LEFT JOIN tenants t ON ts.tenant_id = t.id
        LEFT JOIN plans p ON ts.plan_id = p.name
        LEFT JOIN subscription_payments sp ON ts.id = sp.subscription_id
        GROUP BY ts.id, t.name, t.identifier, p.name, p.price
        ORDER BY ts.created_at DESC
    ");
    $stmt->execute();
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching subscriptions: " . $e->getMessage());
    $subscriptions = [];
}

// Fetch recent payments
try {
    $stmt = $pdo->prepare("
        SELECT sp.*, ts.plan_id, t.name as tenant_name, t.identifier as tenant_identifier,
               u.name as processed_by_name
        FROM subscription_payments sp
        LEFT JOIN tenant_subscriptions ts ON sp.subscription_id = ts.id
        LEFT JOIN tenants t ON ts.tenant_id = t.id
        LEFT JOIN users u ON sp.processed_by = u.id
        ORDER BY sp.created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching recent payments: " . $e->getMessage());
    $recent_payments = [];
}
?>

<style>
/* Apply gradient background to card headers matching the sidebar */
.card-header {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
    color: #ffffff !important;
    border-bottom: none !important;
}

.card-header h5 {
    color: #ffffff !important;
    margin-bottom: 0 !important;
}

.card-header .card-header-right {
    color: #ffffff !important;
}

.card-header .card-header-right .btn {
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
}

.card-header .card-header-right .btn:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
}
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="page-header-title">
                                    <h5 class="m-b-10">Subscription Payments Management</h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item">Subscription Payments</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->

                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->

                        <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="feather icon-check-circle"></i> Payment recorded successfully!
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="feather icon-alert-circle"></i> <?= htmlspecialchars($error_message) ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="feather icon-alert-circle"></i> <?= htmlspecialchars($error_message) ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                        <?php endif; ?>

                        <!-- Record Payment Button -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#recordPaymentModal">
                                    <i class="feather icon-plus-circle mr-2"></i>Record New Payment
                                </button>
                            </div>
                        </div>

                        <!-- Subscriptions Overview -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card shadow-lg border-0">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h4 class="mb-0"><i class="feather icon-credit-card mr-2"></i>All Subscriptions</h4>
                                        <span class="badge badge-pill badge-info"><?= count($subscriptions) ?> subscriptions</span>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-striped mb-0">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th>Tenant</th>
                                                        <th>Plan</th>
                                                        <th>Status</th>
                                                        <th>Billing Cycle</th>
                                                        <th>Amount</th>
                                                        <th>Last Payment</th>
                                                        <th>Next Billing</th>
                                                        <th>Payments</th>
                                                        <th>Total Paid</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($subscriptions)): ?>
                                                    <tr>
                                                        <td colspan="10" class="text-center py-4">
                                                            <i class="feather icon-inbox text-muted mb-2" style="font-size: 2rem;"></i>
                                                            <p class="text-muted">No subscriptions found</p>
                                                        </td>
                                                    </tr>
                                                    <?php else: ?>
                                                    <?php foreach ($subscriptions as $sub): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="flex-grow-1">
                                                                    <h6 class="mb-1"><?= htmlspecialchars($sub['tenant_name']) ?></h6>
                                                                    <small class="text-muted"><?= htmlspecialchars($sub['tenant_identifier']) ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-primary"><?= htmlspecialchars($sub['plan_name']) ?></span>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-<?= $sub['status'] === 'active' ? 'success' : ($sub['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                                <?= ucfirst(htmlspecialchars($sub['status'])) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= ucfirst(htmlspecialchars($sub['billing_cycle'])) ?></td>
                                                        <td>$<?= number_format($sub['amount'], 2) ?> <?= htmlspecialchars($sub['currency']) ?></td>
                                                        <td>
                                                            <?php if ($sub['last_payment_date']): ?>
                                                            <?= date('M d, Y', strtotime($sub['last_payment_date'])) ?>
                                                            <?php else: ?>
                                                            <span class="text-muted">Never</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($sub['next_billing_date']): ?>
                                                            <?= date('M d, Y', strtotime($sub['next_billing_date'])) ?>
                                                            <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-info"><?= $sub['payment_count'] ?> payments</span>
                                                        </td>
                                                        <td>$<?= number_format($sub['total_paid'], 2) ?></td>
                                                        <td>
                                                            <button class="btn btn-sm btn-outline-primary" onclick="viewSubscriptionPayments(<?= $sub['id'] ?>)">
                                                                <i class="feather icon-eye"></i> View
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Payments -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card shadow-lg border-0">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h4 class="mb-0"><i class="feather icon-clock mr-2"></i>Recent Payments</h4>
                                        <span class="badge badge-pill badge-success"><?= count($recent_payments) ?> recent</span>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-striped mb-0">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Tenant</th>
                                                        <th>Plan</th>
                                                        <th>Amount</th>
                                                        <th>Method</th>
                                                        <th>Receipt</th>
                                                        <th>Processed By</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($recent_payments)): ?>
                                                    <tr>
                                                        <td colspan="7" class="text-center py-4">
                                                            <i class="feather icon-inbox text-muted mb-2" style="font-size: 2rem;"></i>
                                                            <p class="text-muted">No payments recorded yet</p>
                                                        </td>
                                                    </tr>
                                                    <?php else: ?>
                                                    <?php foreach ($recent_payments as $payment): ?>
                                                    <tr>
                                                        <td><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="flex-grow-1">
                                                                    <h6 class="mb-1"><?= htmlspecialchars($payment['tenant_name']) ?></h6>
                                                                    <small class="text-muted"><?= htmlspecialchars($payment['tenant_identifier']) ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-primary"><?= htmlspecialchars($payment['plan_id']) ?></span>
                                                        </td>
                                                        <td>$<?= number_format($payment['amount'], 2) ?> <?= htmlspecialchars($payment['currency']) ?></td>
                                                        <td><?= htmlspecialchars($payment['payment_method'] ?: 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($payment['receipt_number'] ?: 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($payment['processed_by_name'] ?: 'System') ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- [ Main Content ] end -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1" role="dialog" aria-labelledby="recordPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="recordPaymentModalLabel">
                    <i class="feather icon-credit-card mr-2"></i>Record Subscription Payment
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="record_payment">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="subscription_id">Subscription *</label>
                                <select class="form-control" id="subscription_id" name="subscription_id" required>
                                    <option value="">Select Subscription</option>
                                    <?php foreach ($subscriptions as $sub): ?>
                                    <option value="<?= $sub['id'] ?>">
                                        <?= htmlspecialchars($sub['tenant_name']) ?> - <?= htmlspecialchars($sub['plan_name']) ?> ($<?= number_format($sub['amount'], 2) ?>/<?= $sub['billing_cycle'] ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="amount">Amount *</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">$</span>
                                    </div>
                                    <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="currency">Currency *</label>
                                <select class="form-control" id="currency" name="currency" required>
                                    <option value="USD">USD ($)</option>
                                    <option value="AFS">AFS (؋)</option>
                                    <option value="EUR">EUR (€)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="payment_date">Payment Date *</label>
                                <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="payment_method">Payment Method</label>
                                <select class="form-control" id="payment_method" name="payment_method">
                                    <option value="">Select Method</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Credit Card">Credit Card</option>
                                    <option value="PayPal">PayPal</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Check">Check</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="receipt_number">Receipt Number</label>
                                <input type="text" class="form-control" id="receipt_number" name="receipt_number" placeholder="Enter receipt number">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="transaction_id">Transaction ID</label>
                        <input type="text" class="form-control" id="transaction_id" name="transaction_id" placeholder="Enter transaction ID">
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Additional notes"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check-circle mr-1"></i>Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Subscription Payments Modal -->
<div class="modal fade" id="subscriptionPaymentsModal" tabindex="-1" role="dialog" aria-labelledby="subscriptionPaymentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="subscriptionPaymentsModalLabel">
                    <i class="feather icon-list mr-2"></i>Subscription Payment History
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="subscriptionPaymentsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
function viewSubscriptionPayments(subscriptionId) {
    // Load subscription payment history
    $('#subscriptionPaymentsContent').html('<div class="text-center"><div class="spinner-border text-info" role="status"><span class="visually-hidden">Loading...</span></div></div>');

    $.ajax({
        url: 'get_subscription_payments.php',
        method: 'GET',
        data: { subscription_id: subscriptionId },
        success: function(response) {
            $('#subscriptionPaymentsContent').html(response);
        },
        error: function() {
            $('#subscriptionPaymentsContent').html('<div class="alert alert-danger">Error loading payment history.</div>');
        }
    });

    $('#subscriptionPaymentsModal').modal('show');
}
</script>

<!-- Include Super Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>
</body>
</html>