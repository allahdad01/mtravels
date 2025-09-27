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
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to manage_subscriptions.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once '../includes/conn.php';

// Handle AJAX requests for edit subscription
if (isset($_GET['action']) && $_GET['action'] === 'get_subscription' && isset($_GET['subscription_id'])) {
    $subscription_id = intval($_GET['subscription_id']);

    $stmt = $conn->prepare("
        SELECT ts.id, ts.tenant_id, ts.plan_id, ts.status, ts.billing_cycle, ts.start_date, ts.end_date,
            ts.amount, ts.currency, ts.payment_method, ts.last_payment_date, ts.next_billing_date,
            ts.transaction_id, COALESCE(t.name, 'Deleted Tenant') as tenant_name, p.name as plan_name
        FROM tenant_subscriptions ts
        LEFT JOIN tenants t ON ts.tenant_id = t.id
        LEFT JOIN plans p ON ts.plan_id = p.id
        WHERE ts.id = ?
    ");
    $stmt->bind_param('i', $subscription_id);
    $stmt->execute();

    if ($stmt->error) {
        error_log("Database error in get_subscription: " . $stmt->error);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error occurred']);
        exit();
    }

    $subscription = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($subscription) {
        header('Content-Type: application/json');
        echo json_encode($subscription);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Subscription not found']);
    }
    exit();
}

// Handle form submission for updating subscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_subscription') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: manage_subscriptions.php?error=invalid_csrf');
        exit();
    }

    $subscription_id = intval($_POST['subscription_id']);
    $plan_id = $_POST['plan_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $billing_cycle = $_POST['billing_cycle'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $currency = $_POST['currency'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $next_billing_date = $_POST['next_billing_date'] ?? '';

    $errors = [];

    // Validate input
    if (empty($plan_id) || empty($status) || empty($billing_cycle) || empty($amount) || empty($currency)) {
        $errors[] = "All required fields must be filled.";
    }
    if (!in_array($status, ['active', 'pending', 'expired', 'cancelled'])) {
        $errors[] = "Invalid status.";
    }
    if (!in_array($billing_cycle, ['monthly', 'quarterly', 'yearly'])) {
        $errors[] = "Invalid billing cycle.";
    }
    if (!is_numeric($amount) || $amount < 0) {
        $errors[] = "Invalid amount.";
    }
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        $errors[] = "Invalid currency code.";
    }
    if ($next_billing_date && !strtotime($next_billing_date)) {
        $errors[] = "Invalid next billing date.";
    }

    // Verify plan exists
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM plans WHERE id = ? AND status = 'active'");
    $stmt->bind_param('i', $plan_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()['count'] == 0) {
        $errors[] = "Invalid or inactive plan selected.";
    }
    $stmt->close();

    if (empty($errors)) {
        // Update subscription
        $stmt = $conn->prepare("
            UPDATE tenant_subscriptions
            SET plan_id = ?, status = ?, billing_cycle = ?, amount = ?, currency = ?,
                payment_method = ?, next_billing_date = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('sssdsssi', $plan_id, $status, $billing_cycle, $amount, $currency,
                          $payment_method, $next_billing_date, $subscription_id);
        $stmt->execute();
        $stmt->close();

        // Log action
        $user_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                                VALUES (?, 'update_subscription', 'subscription', ?, ?, ?, NOW())");
        $details = json_encode([
            'subscription_id' => $subscription_id,
            'plan_id' => $plan_id,
            'status' => $status,
            'billing_cycle' => $billing_cycle
        ]);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param('iiss', $user_id, $subscription_id, $details, $ip_address);
        $stmt->execute();
        $stmt->close();

        header('Location: manage_subscriptions.php?success=subscription_updated');
        exit();
    } else {
        header('Location: manage_subscriptions.php?error=' . urlencode(implode(', ', $errors)));
        exit();
    }
}

// Fetch all subscriptions with tenant names (including deleted tenants)
$stmt = $conn->prepare("
    SELECT ts.id, ts.tenant_id, ts.status, ts.billing_cycle, ts.start_date, ts.end_date,
           ts.amount, ts.currency, ts.payment_method, ts.last_payment_date, ts.next_billing_date,
           ts.transaction_id, COALESCE(t.name, 'Deleted Tenant') as tenant_name, t.status as tenant_status, p.name as plan_name
    FROM tenant_subscriptions ts
    LEFT JOIN tenants t ON ts.tenant_id = t.id
    LEFT JOIN plans p ON ts.plan_id = p.id
    ORDER BY ts.start_date DESC
");
$stmt->execute();
$subscriptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch active plans for the create and edit subscription modals
$stmt = $conn->prepare("SELECT id, name, description, features, price, max_users, trial_days FROM plans WHERE status = 'active' ORDER BY name");
$stmt->execute();
$plans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<?php include '../includes/header_super_admin.php'; ?>

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
                                    <h5 class="m-b-10"><?= __('manage_subscriptions') ?></h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="#!"><?= __('subscriptions') ?></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="row">
                            <div class="col-xl-12">
                                <?php if (isset($_GET['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?php 
                                    $success_message = '';
                                    switch ($_GET['success']) {
                                        case 'subscription_created':
                                            $success_message = __('subscription_created_successfully');
                                            break;
                                        case 'subscription_updated':
                                            $success_message = __('subscription_updated_successfully');
                                            break;
                                        default:
                                            $success_message = __('operation_completed_successfully');
                                    }
                                    echo $success_message;
                                    ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($_GET['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($_GET['error']) ?>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <?php endif; ?>
                                
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5><?= __('subscriptions_list') ?></h5>
                                        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createSubscriptionModal">
                                            <i class="feather icon-plus"></i> <?= __('create_subscription') ?>
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <!-- Tabs -->
                                        <ul class="nav nav-tabs" id="subscriptionTabs" role="tablist">
                                            <li class="nav-item">
                                                <a class="nav-link active" id="active-tab" data-toggle="tab" href="#active" role="tab" aria-controls="active" aria-selected="true">
                                                    <?= __('active') ?> Subscriptions
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link" id="deleted-tab" data-toggle="tab" href="#deleted" role="tab" aria-controls="deleted" aria-selected="false">
                                                    Deleted/Cancelled Subscriptions
                                                </a>
                                            </li>
                                        </ul>
                                        <div class="tab-content mt-3" id="subscriptionTabContent">
                                            <!-- Active Subscriptions Tab -->
                                            <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
                                                <div class="table-responsive">
                                                    <table class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th><?= __('tenant') ?></th>
                                                                <th><?= __('plan') ?></th>
                                                                <th><?= __('status') ?></th>
                                                                <th><?= __('billing_cycle') ?></th>
                                                                <th><?= __('amount') ?></th>
                                                                <th><?= __('start_date') ?></th>
                                                                <th><?= __('next_billing') ?></th>
                                                                <th><?= __('actions') ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="active-subscriptions-body">
                                                            <?php foreach ($subscriptions as $sub): ?>
                                                            <?php if ($sub['tenant_status'] !== 'deleted'): ?>
                                                            <tr data-tenant-status="<?= $sub['tenant_status'] ?>">
                                                                <td><?= htmlspecialchars($sub['tenant_name']) ?></td>
                                                                <td><?= htmlspecialchars($sub['plan_name']) ?></td>
                                                                <td>
                                                                    <span class="badge badge-<?= $sub['status'] === 'active' ? 'success' : ($sub['status'] === 'expired' ? 'danger' : 'warning') ?>">
                                                                        <?= htmlspecialchars($sub['status']) ?>
                                                                    </span>
                                                                </td>
                                                                <td><?= htmlspecialchars($sub['billing_cycle']) ?></td>
                                                                <td><?= number_format($sub['amount'], 2) ?> <?= htmlspecialchars($sub['currency']) ?></td>
                                                                <td><?= date('M d, Y', strtotime($sub['start_date'])) ?></td>
                                                                <td><?= $sub['next_billing_date'] ? date('M d, Y', strtotime($sub['next_billing_date'])) : '-' ?></td>
                                                                <td>
                                                                    <button type="button" class="btn btn-sm btn-primary edit-subscription-btn"
                                                                            data-subscription-id="<?= $sub['id'] ?>"
                                                                            data-tenant-id="<?= $sub['tenant_id'] ?>"
                                                                            data-toggle="modal"
                                                                            data-target="#editSubscriptionModal">
                                                                        <i class="feather icon-edit"></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                            <?php endif; ?>
                                                            <?php endforeach; ?>
                                                            <?php
                                                            $hasActive = false;
                                                            foreach ($subscriptions as $sub) {
                                                                if ($sub['tenant_status'] !== 'deleted') {
                                                                    $hasActive = true;
                                                                    break;
                                                                }
                                                            }
                                                            if (!$hasActive):
                                                            ?>
                                                            <tr><td colspan="8" class="text-center"><?= __('no_subscriptions_found') ?></td></tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <!-- Deleted Subscriptions Tab -->
                                            <div class="tab-pane fade" id="deleted" role="tabpanel" aria-labelledby="deleted-tab">
                                                <div class="table-responsive">
                                                    <table class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th><?= __('tenant') ?></th>
                                                                <th><?= __('plan') ?></th>
                                                                <th><?= __('status') ?></th>
                                                                <th><?= __('billing_cycle') ?></th>
                                                                <th><?= __('amount') ?></th>
                                                                <th><?= __('start_date') ?></th>
                                                                <th><?= __('next_billing') ?></th>
                                                                <th><?= __('actions') ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="deleted-subscriptions-body">
                                                            <?php foreach ($subscriptions as $sub): ?>
                                                            <?php if ($sub['tenant_status'] === 'deleted'): ?>
                                                            <tr data-tenant-status="<?= $sub['tenant_status'] ?>">
                                                                <td><?= htmlspecialchars($sub['tenant_name']) ?></td>
                                                                <td><?= htmlspecialchars($sub['plan_name']) ?></td>
                                                                <td>
                                                                    <span class="badge badge-<?= $sub['status'] === 'active' ? 'success' : ($sub['status'] === 'expired' ? 'danger' : 'warning') ?>">
                                                                        <?= htmlspecialchars($sub['status']) ?>
                                                                    </span>
                                                                </td>
                                                                <td><?= htmlspecialchars($sub['billing_cycle']) ?></td>
                                                                <td><?= number_format($sub['amount'], 2) ?> <?= htmlspecialchars($sub['currency']) ?></td>
                                                                <td><?= date('M d, Y', strtotime($sub['start_date'])) ?></td>
                                                                <td><?= $sub['next_billing_date'] ? date('M d, Y', strtotime($sub['next_billing_date'])) : '-' ?></td>
                                                                <td>
                                                                    <span class="text-muted">N/A</span>
                                                                </td>
                                                            </tr>
                                                            <?php endif; ?>
                                                            <?php endforeach; ?>
                                                            <?php
                                                            $hasDeleted = false;
                                                            foreach ($subscriptions as $sub) {
                                                                if ($sub['tenant_status'] === 'deleted') {
                                                                    $hasDeleted = true;
                                                                    break;
                                                                }
                                                            }
                                                            if (!$hasDeleted):
                                                            ?>
                                                            <tr><td colspan="8" class="text-center">No deleted subscriptions found</td></tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
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

<!-- Create Subscription Modal -->
<div class="modal fade" id="createSubscriptionModal" tabindex="-1" role="dialog" aria-labelledby="createSubscriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createSubscriptionModalLabel"><?= __('create_subscription') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" action="create_subscription.php" id="createSubscriptionForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    
                    <div class="form-group">
                        <label for="tenant_id"><?= __('tenant') ?></label>
                        <select class="form-control" id="tenant_id" name="tenant_id" required>
                            <option value=""><?= __('select_tenant') ?></option>
                            <?php 
                            // Fetch active tenants
                            $stmt = $conn->prepare("SELECT id, name FROM tenants WHERE status != 'deleted' ORDER BY name");
                            $stmt->execute();
                            $tenants_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            $stmt->close();
                            
                            foreach ($tenants_list as $tenant): 
                            ?>
                            <option value="<?= htmlspecialchars($tenant['id']) ?>">
                                <?= htmlspecialchars($tenant['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_plan_id"><?= __('plan') ?></label>
                        <select class="form-control" id="create_plan_id" name="plan_id" required>
                            <option value=""><?= __('select_plan') ?></option>
                            <?php foreach ($plans as $plan): ?>
                            <option value="<?= htmlspecialchars($plan['id']) ?>" 
                                    data-price="<?= htmlspecialchars($plan['price']) ?>">
                                <?= htmlspecialchars($plan['name']) ?> - 
                                <?= number_format($plan['price'], 2) ?> - 
                                <?= htmlspecialchars($plan['max_users']) ?> users - 
                                <?= htmlspecialchars($plan['trial_days']) ?> trial days
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_status"><?= __('status') ?></label>
                        <select class="form-control" id="create_status" name="status" required>
                            <option value="active"><?= __('active') ?></option>
                            <option value="pending"><?= __('pending') ?></option>
                            <option value="expired"><?= __('expired') ?></option>
                            <option value="cancelled"><?= __('cancelled') ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_billing_cycle"><?= __('billing_cycle') ?></label>
                        <select class="form-control" id="create_billing_cycle" name="billing_cycle" required>
                            <option value="monthly"><?= __('monthly') ?></option>
                            <option value="quarterly"><?= __('quarterly') ?></option>
                            <option value="yearly"><?= __('yearly') ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_amount"><?= __('amount') ?></label>
                        <input type="number" step="0.01" class="form-control" id="create_amount" name="amount" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_currency"><?= __('currency') ?></label>
                        <input type="text" class="form-control" id="create_currency" name="currency" value="USD" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_payment_method"><?= __('payment_method') ?></label>
                        <input type="text" class="form-control" id="create_payment_method" name="payment_method">
                    </div>
                    
                    <div class="form-group">
                        <label for="create_start_date"><?= __('start_date') ?></label>
                        <input type="date" class="form-control" id="create_start_date" name="start_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="create_next_billing_date"><?= __('next_billing_date') ?></label>
                        <input type="date" class="form-control" id="create_next_billing_date" name="next_billing_date">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                <button type="submit" form="createSubscriptionForm" class="btn btn-primary"><?= __('create') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Subscription Modal -->
<div class="modal fade" id="editSubscriptionModal" tabindex="-1" role="dialog" aria-labelledby="editSubscriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSubscriptionModalLabel"><?= __('edit_subscription') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="editSubscriptionLoader" class="text-center" style="display: none;">
                    <div class="spinner-border" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
                <form method="POST" action="manage_subscriptions.php" id="editSubscriptionForm" style="display: none;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="update_subscription">
                    <input type="hidden" name="subscription_id" id="edit_subscription_id">
                    
                    <div class="form-group">
                        <label for="edit_tenant_name"><?= __('tenant') ?></label>
                        <input type="text" class="form-control" id="edit_tenant_name" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_plan_id"><?= __('plan') ?></label>
                        <select class="form-control" id="edit_plan_id" name="plan_id" required>
                            <?php foreach ($plans as $plan): ?>
                            <option value="<?= htmlspecialchars($plan['id']) ?>" 
                                    data-price="<?= htmlspecialchars($plan['price']) ?>">
                                <?= htmlspecialchars($plan['name']) ?> - 
                                <?= number_format($plan['price'], 2) ?> - 
                                <?= htmlspecialchars($plan['max_users']) ?> users - 
                                <?= htmlspecialchars($plan['trial_days']) ?> trial days
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_status"><?= __('status') ?></label>
                        <select class="form-control" id="edit_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="expired">Expired</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_billing_cycle"><?= __('billing_cycle') ?></label>
                        <select class="form-control" id="edit_billing_cycle" name="billing_cycle" required>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_amount"><?= __('amount') ?></label>
                        <input type="number" step="0.01" class="form-control" id="edit_amount" name="amount" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_currency"><?= __('currency') ?></label>
                        <input type="text" class="form-control" id="edit_currency" name="currency" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_payment_method"><?= __('payment_method') ?></label>
                        <input type="text" class="form-control" id="edit_payment_method" name="payment_method">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_next_billing_date"><?= __('next_billing_date') ?></label>
                        <input type="date" class="form-control" id="edit_next_billing_date" name="next_billing_date">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                <button type="submit" form="editSubscriptionForm" class="btn btn-primary" id="saveEditSubscription"><?= __('save_changes') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
// Auto-update amount when plan changes in create subscription modal
document.getElementById('create_plan_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const price = selectedOption.getAttribute('data-price');
    if (price) {
        document.getElementById('create_amount').value = price;
    }
});

// Auto-update amount when plan changes in edit subscription modal
document.getElementById('edit_plan_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const price = selectedOption.getAttribute('data-price');
    if (price) {
        document.getElementById('edit_amount').value = price;
    }
});

// Initialize amount on create modal open
$('#createSubscriptionModal').on('shown.bs.modal', function () {
    const planSelect = document.getElementById('create_plan_id');
    if (planSelect.selectedIndex > 0) {
        const selectedOption = planSelect.options[planSelect.selectedIndex];
        const price = selectedOption.getAttribute('data-price');
        if (price) {
            document.getElementById('create_amount').value = price;
        }
    }
});

// Handle edit subscription button click
$(document).on('click', '.edit-subscription-btn', function() {
    const subscriptionId = $(this).data('subscription-id');
    const tenantId = $(this).data('tenant-id');

    // Show loader
    $('#editSubscriptionLoader').show();
    $('#editSubscriptionForm').hide();
    $('#saveEditSubscription').prop('disabled', true);

    // Fetch subscription data
    $.ajax({
        url: 'manage_subscriptions.php',
        method: 'GET',
        data: {
            action: 'get_subscription',
            subscription_id: subscriptionId
        },
        dataType: 'json',
        success: function(data) {
            if (data.error) {
                console.error('Error fetching subscription data:', data.error);
                alert('Error loading subscription data: ' + data.error);
                $('#editSubscriptionModal').modal('hide');
                return;
            }

            // Populate form fields
            $('#edit_subscription_id').val(data.id);
            $('#edit_tenant_name').val(data.tenant_name);
            $('#edit_plan_id').val(data.plan_id);
            $('#edit_status').val(data.status);
            $('#edit_billing_cycle').val(data.billing_cycle);
            $('#edit_amount').val(data.amount);
            $('#edit_currency').val(data.currency);
            $('#edit_payment_method').val(data.payment_method || '');
            $('#edit_next_billing_date').val(data.next_billing_date || '');

            // Update modal title
            $('#editSubscriptionModalLabel').text('Edit Subscription - ' + data.tenant_name);

            // Hide loader and show form
            $('#editSubscriptionLoader').hide();
            $('#editSubscriptionForm').show();
            $('#saveEditSubscription').prop('disabled', false);
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', xhr.status, status, error);
            alert('Network error occurred. Please try again.');
            $('#editSubscriptionModal').modal('hide');
        }
    });
});

// Reset edit modal when closed
$('#editSubscriptionModal').on('hidden.bs.modal', function () {
    $('#editSubscriptionForm')[0].reset();
    $('#editSubscriptionForm').hide();
    $('#editSubscriptionLoader').hide();
    $('#editSubscriptionModalLabel').text('<?= __('edit_subscription') ?>');
});
</script>
</body>
</html>