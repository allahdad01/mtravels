<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    // Session expired, destroy session and redirect to login
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time(); // Update last activity time

// Check if user is logged in with proper role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to subscription payments: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../config.php';
require_once '../includes/conn.php';
require_once '../includes/db.php';

// Check if $pdo is available
if (!isset($pdo) || !$pdo) {
    die("Database connection failed. Please contact administrator.");
}

// Get tenant_id from session (assuming tenant admins have this set)
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    // If no tenant_id, redirect or show error
    header('Location: dashboard.php');
    exit();
}

// Get tenant payment status
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
    // Ignore errors
}

// Fetch subscription data for this tenant
try {
    $stmt = $pdo->prepare("
        SELECT ts.*, p.name as plan_name, p.price as plan_price
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

// Fetch payment history from subscription_payments table
try {
    $stmt = $pdo->prepare("
        SELECT sp.*, ts.plan_id, p.name as plan_name, u.name as processed_by_name
        FROM subscription_payments sp
        LEFT JOIN tenant_subscriptions ts ON sp.subscription_id = ts.id
        LEFT JOIN plans p ON ts.plan_id = p.name
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

// Handle payment redirect
if (isset($_GET['payment']) && isset($_GET['subscription_id'])) {
    $payment_status = $_GET['payment'];
    $sub_id = intval($_GET['subscription_id']);
    if ($payment_status === 'success') {
        // Fetch subscription details
        try {
            $stmt = $pdo->prepare("SELECT amount, currency FROM tenant_subscriptions WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$sub_id, $tenant_id]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($subscription) {
                // Insert payment record
                $processed_by = $_SESSION['user_id'] ?? null;
                $stmt2 = $pdo->prepare("INSERT INTO subscription_payments (subscription_id, amount, currency, payment_method, payment_date, processed_by) VALUES (?, ?, ?, 'Hesabpay', CURDATE(), ?)");
                $stmt2->execute([$sub_id, $subscription['amount'], $subscription['currency'], $processed_by]);

                // Update subscription status to active
                $pdo->prepare("UPDATE tenant_subscriptions SET status = 'active' WHERE id = ? AND tenant_id = ?")->execute([$sub_id, $tenant_id]);
            }
        } catch (PDOException $e) {
            error_log("Error processing successful payment: " . $e->getMessage());
        }
        $payment_message = "Payment successful! Your subscription has been activated.";
    } elseif ($payment_status === 'failed') {
        $payment_message = "Payment failed. Please try again.";
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

                               <!-- Profile Modal -->
                               <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="profileModalLabel">
                    <i class="feather icon-user mr-2"></i><?= __('user_profile') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    
                    <div class="position-relative d-inline-block">
                        <img src="<?= $imagePath ?>" 
                             class="rounded-circle profile-image" 
                             alt="User Profile Image">
                        <div class="profile-status online"></div>
                    </div>
                    <h5 class="mt-3 mb-1"><?= !empty($user['name']) ? htmlspecialchars($user['name']) : 'Guest' ?></h5>
                    <p class="text-muted mb-0"><?= !empty($user['role']) ? htmlspecialchars($user['role']) : 'User' ?></p>
                </div>

                <div class="profile-info">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('email') ?></label>
                                <p class="mb-0"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('phone') ?></label>
                                <p class="mb-0"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('join_date') ?></label>
                                <p class="mb-0"><?= !empty($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('address') ?></label>
                                <p class="mb-0"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not Set' ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <h6 class="mb-3"><?= __('account_information') ?></h6>
                        <div class="activity-timeline">
                            <div class="timeline-item">
                                <i class="activity-icon fas fa-calendar-alt bg-primary"></i>
                                <div class="timeline-content">
                                    <p class="mb-0"><?= __('account_created') ?></p>
                                    <small class="text-muted"><?= !empty($user['created_at']) ? date('M d, Y H:i A', strtotime($user['created_at'])) : 'Not Available' ?></small>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __('close') ?></button>
                
            </div>
        </div>
    </div>
</div>



                            <!-- Settings Modal -->
                            <div class="modal fade" id="settingsModal" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                    <form id="updateProfileForm" enctype="multipart/form-data">
                                        <div class="modal-content shadow-lg border-0">
                                            <div class="modal-header bg-primary text-white border-0">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-settings mr-2"></i><?= __('profile_settings') ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="row">
                                                    <!-- Left Column - Profile Picture -->
                                                    <div class="col-md-4 text-center mb-4">
                                                        <div class="position-relative d-inline-block">
                                                            <img src="<?= $imagePath ?>" alt="Profile Picture" 
                                                                 class="profile-upload-preview rounded-circle border shadow-sm"
                                                                 id="profilePreview">
                                                            <label for="profileImage" class="upload-overlay">
                                                                <i class="feather icon-camera"></i>
                                                            </label>
                                                            <input type="file" class="d-none" id="profileImage" name="image" 
                                                                   accept="image/*" onchange="previewImage(this)">
                                                        </div>
                                                        <small class="text-muted d-block mt-2"><?= __('click_to_change_profile_picture') ?></small>
                                                    </div>

                                                    <!-- Right Column - Form Fields -->
                                                    <div class="col-md-8">
                                                        <!-- Personal Info Section -->
                                                        <div class="settings-section active" id="personalInfo">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-user mr-2"></i><?= __('personal_information') ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="text" class="form-control" id="updateName" name="name" 
                                                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                                                                <label for="updateName"><?= __('full_name') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="email" class="form-control" id="updateEmail" name="email" 
                                                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                                                                <label for="updateEmail"><?= __('email_address') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="tel" class="form-control" id="updatePhone" name="phone" 
                                                                       value="<?= htmlspecialchars($user['phone']) ?>">
                                                                <label for="updatePhone"><?= __('phone_number') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <textarea class="form-control" id="updateAddress" name="address" 
                                                                          rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                                                                <label for="updateAddress"><?= __('address') ?></label>
                                                            </div>
                                                        </div>

                                                        <!-- Password Section -->
                                                        <div class="settings-section mt-4">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-lock mr-2"></i><?= __('change_password') ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="password" class="form-control" id="currentPassword" 
                                                                       name="current_password">
                                                                <label for="currentPassword"><?= __('current_password') ?></label>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="newPassword" 
                                                                               name="new_password">
                                                                        <label for="newPassword"><?= __('new_password') ?></label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="confirmPassword" 
                                                                               name="confirm_password">
                                                                            <label for="confirmPassword"><?= __('confirm_password') ?></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 bg-light">
                                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                                                </button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather icon-save mr-2"></i><?= __('save_changes') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
<!-- Required Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="js/date-change/profile.js"></script>

<?php include '../includes/admin_footer.php'; ?>