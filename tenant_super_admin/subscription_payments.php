<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tenant_super_admin') {
    error_log("Unauthorized access attempt: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/BranchAddonManager.php';
require_once '../includes/UserAddonManager.php';

if (!isset($pdo) || !$pdo) {
    die("Database connection failed. Please contact administrator.");
}

// Get tenant_id from session
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    header('Location: dashboard.php');
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

// Initialize BranchAddonManager
$addonManager = new BranchAddonManager($pdo, $tenant_id);
$userAddonManager = new UserAddonManager($pdo, $tenant_id);

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

// Fetch all subscription payment history for pagination
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
     $all_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
     error_log("Error fetching payments: " . $e->getMessage());
     $all_payments = [];
}

// Pagination for payments
$payment_items_per_page = 10;
$payment_current_page = intval($_GET['payment_page'] ?? 1);
$payment_search_query = $_GET['payment_search'] ?? '';

// Filter payments based on search
$filtered_payments = $all_payments;
if (!empty($payment_search_query)) {
     $search_lower = strtolower($payment_search_query);
     $filtered_payments = array_filter($all_payments, function($payment) use ($search_lower) {
         return 
             strpos(strtolower($payment['plan_name'] ?? ''), $search_lower) !== false ||
             strpos(strtolower($payment['payment_method'] ?? ''), $search_lower) !== false ||
             strpos(strtolower($payment['receipt_number'] ?? ''), $search_lower) !== false ||
             strpos(strtolower($payment['currency'] ?? ''), $search_lower) !== false;
     });
}

// Calculate pagination
$payment_total_items = count($filtered_payments);
$payment_total_pages = ceil($payment_total_items / $payment_items_per_page);
$payment_current_page = max(1, min($payment_current_page, $payment_total_pages));
$payment_offset = ($payment_current_page - 1) * $payment_items_per_page;
$payments = array_slice(array_values($filtered_payments), $payment_offset, $payment_items_per_page);

// Handle HesabPay redirect
if (isset($_GET['payment'], $_GET['subscription_id'])) {
    $payment_status = $_GET['payment'];
    $subscription_id_raw = $_GET['subscription_id'];

    // Handle malformed URL where ? is used instead of & after subscription_id
    $sub_id = 0;
    $data = null;
    if (strpos($subscription_id_raw, '?') !== false) {
        // Parse subscription_id=1?data=... as subscription_id=1&data=...
        list($sub_id_str, $data_query) = explode('?', $subscription_id_raw, 2);
        $sub_id = intval($sub_id_str);
        parse_str($data_query, $data_params);
        if (isset($data_params['data'])) {
            $raw = urldecode($data_params['data']);
            $data = json_decode($raw, true);
            if ($data === null && strpos($raw, '{') !== false) {
                // fallback for malformed JSON
                $raw = str_replace(['\\"', "'"], ['"', '"'], $raw);
                $data = json_decode($raw, true);
            }
        }
    } else {
        $sub_id = intval($subscription_id_raw);
        // Safely decode JSON from data param
        if (!empty($_GET['data'])) {
            $raw = urldecode($_GET['data']);
            $data = json_decode($raw, true);
            if ($data === null && strpos($raw, '{') !== false) {
                // fallback for malformed JSON
                $raw = str_replace(['\\"', "'"], ['"', '"'], $raw);
                $data = json_decode($raw, true);
            }
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
                    (subscription_id, amount, currency, payment_method, payment_date, processed_by, transaction_id, receipt_number)
                    VALUES (?, ?, ?, 'HesabPay', CURDATE(), ?, ?, ?)
                ");
                $stmt2->execute([$sub_id, $subscription['amount'], $subscription['currency'], $processed_by, $transaction_id, $transaction_id]);

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


<?php include 'header.php'; ?>

<style>
/* Enhanced custom styles for better layout and design */
.page-header h3 {
    color: #007bff;
    font-weight: 600;
}
.card {
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border: none;
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}
.subscription-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    
}

.subscription-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px 15px 0 0;
    padding: 1rem 1.5rem;
    border: none;
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-active {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}

.status-pending {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
    color: white;
}

.status-expired {
    background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
    color: white;
}

.status-cancelled {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    color: white;
}

.table-responsive {
    border-radius: 10px;
    
}

.table {
    margin-bottom: 0;
}

.table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    padding: 1rem;
}

.table tbody tr:hover {
    background-color: #f1f3f4;
}

.table tbody td {
    padding: 1rem;
    vertical-align: middle;
}

.btn {
    border-radius: 25px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border: none;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,123,255,0.3);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

.alert {
    border-radius: 10px;
    border: none;
    padding: 1rem 1.5rem;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
}

.alert-warning {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    color: #856404;
}

.text-primary {
    color: #007bff !important;
}

.text-success {
    color: #28a745 !important;
}

.text-info {
    color: #17a2b8 !important;
}

.text-muted {
    color: #6c757d !important;
}

.font-weight-bold {
    font-weight: 700 !important;
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
                                        <h3 class="mb-0"><i class="feather icon-credit-card mr-2"></i><?= __('subscription_payments') ?></h3>
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
                                <div class="alert alert-<?= $tenant_payment_status === 'warning' ? 'warning' : 'danger' ?> alert-dismissible fade show shadow-sm" role="alert">
                                    <i class="feather icon-alert-triangle mr-2"></i>
                                    <strong>Payment Status:</strong>
                                    <?php if ($tenant_payment_status === 'warning'): ?>
                                        Your subscription payment is due soon.
                                        <?php if (isset($payment_due_date)): ?>
                                        Please ensure payment is made before <strong><?= date('M d, Y', strtotime($payment_due_date)) ?></strong>.
                                        <?php endif; ?>
                                    <?php elseif ($tenant_payment_status === 'overdue'): ?>
                                        Your subscription payment is overdue.
                                        <?php if (isset($payment_due_date)): ?>
                                        Payment was due on <strong><?= date('M d, Y', strtotime($payment_due_date)) ?></strong>.
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
                                    <?php
                                    // Get active branch add-ons for this subscription
                                    $activeBranchAddons = $addonManager->getActiveBranchAddons($tenant_id);
                                    $branchAddonCost = 0;
                                    foreach ($activeBranchAddons as $addon) {
                                        $branchAddonCost += floatval($addon['total_addon_cost'] ?? 0);
                                    }
                                    
                                    // Get active user add-ons for this tenant
                                    $activeUserAddons = $userAddonManager->getActiveUserAddons($tenant_id);
                                    $userAddonCost = 0;
                                    foreach ($activeUserAddons as $addon) {
                                        $userAddonCost += floatval($addon['total_addon_cost'] ?? 0);
                                    }
                                    
                                    $totalAddonCost = $branchAddonCost + $userAddonCost;
                                    $totalAmount = floatval($subscription['amount']) + $totalAddonCost;
                                    $symbol = getCurrencySymbol($subscription['currency']);
                                    ?>
                                    <div class="col-md-6 col-xl-4 mb-4">
                                        <div class="card subscription-card">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="feather icon-package mr-2"></i><?= htmlspecialchars($subscription['plan_name'] ?? 'Subscription') ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <span class="status-badge status-<?= strtolower($subscription['status']) ?>">
                                                        <i class="feather icon-<?= strtolower($subscription['status']) === 'active' ? 'check-circle' : (strtolower($subscription['status']) === 'pending' ? 'clock' : 'x-circle') ?> mr-1"></i>
                                                        <?= ucfirst($subscription['status']) ?>
                                                    </span>
                                                </div>
                                                <div class="row">
                                                    <div class="col-6">
                                                        <small class="text-muted"><i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?></small>
                                                        <h6 class="mb-0 font-weight-bold text-primary"><?= $symbol . number_format($subscription['amount'], 2) ?></h6>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted"><i class="feather icon-calendar mr-1"></i><?= __('billing_cycle') ?></small>
                                                        <h6 class="mb-0 font-weight-bold text-info"><?= ucfirst($subscription['billing_cycle']) ?></h6>
                                                    </div>
                                                </div>
                                                <?php if ($totalAddonCost > 0): ?>
                                                <hr class="my-3">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <small class="text-muted"><i class="feather icon-plus-circle mr-1"></i>Add-on Cost</small>
                                                        <h6 class="mb-0 text-info font-weight-bold"><?= $symbol . number_format($totalAddonCost, 2) ?></h6>
                                                        <?php if ($branchAddonCost > 0 || $userAddonCost > 0): ?>
                                                        <small class="text-muted">
                                                            <?php if ($branchAddonCost > 0): ?>
                                                                <i class="feather icon-git-branch mr-1"></i>Branch: <?= $symbol . number_format($branchAddonCost, 2) ?>
                                                            <?php endif; ?>
                                                            <?php if ($userAddonCost > 0): ?>
                                                                <br><i class="feather icon-users mr-1"></i>Users: <?= $symbol . number_format($userAddonCost, 2) ?>
                                                            <?php endif; ?>
                                                        </small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted"><i class="feather icon-credit-card mr-1"></i>Total Due</small>
                                                        <h6 class="mb-0 text-success font-weight-bold"><?= $symbol . number_format($totalAmount, 2) ?></h6>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <hr class="my-3">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <small class="text-muted"><i class="feather icon-play mr-1"></i><?= __('start_date') ?></small>
                                                        <p class="mb-0 small font-weight-bold"><?= date('M d, Y', strtotime($subscription['start_date'])) ?></p>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted"><i class="feather icon-refresh-cw mr-1"></i><?= __('next_billing') ?></small>
                                                        <p class="mb-0 small font-weight-bold">
                                                            <?php if ($subscription['next_billing_date']): ?>
                                                                <?= date('M d, Y', strtotime($subscription['next_billing_date'])) ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <?php if ($subscription['last_payment_date']): ?>
                                                    <div class="mt-2">
                                                        <small class="text-muted"><i class="feather icon-check mr-1"></i><?= __('last_payment') ?>: <strong><?= date('M d, Y', strtotime($subscription['last_payment_date'])) ?></strong></small>
                                                    </div>
                                                <?php endif; ?>

                                                    <form method="post" action="process_subscription_payment.php" class="mt-3">
                                                         <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                         <input type="hidden" name="subscription_id" value="<?php echo $subscription['id']; ?>">
                                                         <input type="hidden" name="amount" value="<?php echo $totalAmount; ?>">
                                                         <input type="hidden" name="currency" value="<?php echo $subscription['currency']; ?>">
                                                         <input type="hidden" name="addon_cost" value="<?php echo $totalAddonCost; ?>">
                                                         <button type="submit" class="btn btn-primary btn-sm btn-block">
                                                             <i class="feather icon-credit-card mr-2"></i> Pay Now
                                                         </button>
                                                     </form>

                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-body text-center py-5">
                                            <div class="mb-4">
                                                <i class="feather icon-package text-muted" style="font-size: 4rem;"></i>
                                            </div>
                                            <h5 class="text-muted font-weight-bold mb-2"><?= __('no_subscriptions_found') ?></h5>
                                            <p class="text-muted mb-4"><?= __('contact_admin_for_subscription_setup') ?></p>
                                            <button type="button" class="btn btn-primary btn-lg">
                                                <i class="feather icon-mail mr-2"></i>Contact Administrator
                                            </button>
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
                                         <div class="row align-items-center">
                                             <div class="col-md-6">
                                                 <h5><i class="feather icon-history mr-2"></i><?= __('payment_history') ?> <span class="badge badge-pill badge-info"><?= $payment_total_items ?> total</span></h5>
                                             </div>
                                             <div class="col-md-6">
                                                 <form method="GET" class="form-inline float-right">
                                                     <input type="text" name="payment_search" class="form-control form-control-sm mr-2" 
                                                            placeholder="Search payments..." value="<?= htmlspecialchars($payment_search_query) ?>" style="width: 250px;">
                                                     <button type="submit" class="btn btn-sm btn-primary">
                                                         <i class="feather icon-search"></i>
                                                     </button>
                                                     <?php if (!empty($payment_search_query)): ?>
                                                     <a href="subscription_payments.php" class="btn btn-sm btn-secondary ml-2">
                                                         <i class="feather icon-x"></i> Clear
                                                     </a>
                                                     <?php endif; ?>
                                                 </form>
                                             </div>
                                         </div>
                                     </div>
                                     <div class="card-body">
                                         <?php if (count($payments) > 0): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th><i class="feather icon-calendar mr-1"></i><?= __('payment_date') ?></th>
                                                            <th><i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?></th>
                                                            <th><i class="feather icon-tag mr-1"></i><?= __('currency') ?></th>
                                                            <th><i class="feather icon-package mr-1"></i><?= __('plan') ?></th>
                                                            <th><i class="feather icon-credit-card mr-1"></i><?= __('payment_method') ?></th>
                                                            <th><i class="feather icon-file-text mr-1"></i><?= __('receipt_number') ?></th>
                                                            <th><i class="feather icon-user mr-1"></i><?= __('processed_by') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($payments as $payment): ?>
                                                            <?php $paymentSymbol = getCurrencySymbol($payment['currency']); ?>
                                                            <tr>
                                                                <td class="font-weight-bold text-muted"><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                                                <td>
                                                                    <span class="font-weight-bold text-success h6">
                                                                        <i class="feather icon-dollar-sign mr-1"></i><?= $paymentSymbol . number_format($payment['amount'], 2) ?>
                                                                    </span>
                                                                </td>
                                                                <td class="font-weight-bold text-info"><?= htmlspecialchars($payment['currency']) ?></td>
                                                                <td>
                                                                    <span class="font-weight-bold text-primary">
                                                                        <i class="feather icon-package mr-1"></i><?= htmlspecialchars($payment['plan_name'] ?: $payment['plan_id']) ?>
                                                                    </span>
                                                                </td>
                                                                <td class="font-weight-bold">
                                                                    <?php if ($payment['payment_method']): ?>
                                                                        <i class="feather icon-credit-card mr-1 text-primary"></i><?= htmlspecialchars($payment['payment_method']) ?>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">N/A</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if ($payment['receipt_number']): ?>
                                                                        <span class="font-weight-bold text-dark">
                                                                            <i class="feather icon-file-text mr-1 text-info"></i><?= htmlspecialchars($payment['receipt_number']) ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">N/A</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="font-weight-bold">
                                                                    <i class="feather icon-user mr-1 text-secondary"></i><?= htmlspecialchars($payment['processed_by_name'] ?: 'System') ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                    </table>
                                                    </div>

                                                    <!-- Pagination for Payments -->
                                                    <?php if ($payment_total_pages > 1): ?>
                                                    <nav aria-label="Payments pagination" class="mt-3 mb-0">
                                                    <ul class="pagination justify-content-center mb-0">
                                                    <li class="page-item <?= $payment_current_page === 1 ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="subscription_payments.php?payment_page=<?= $payment_current_page - 1 ?><?= !empty($payment_search_query) ? '&payment_search=' . urlencode($payment_search_query) : '' ?>">
                                                    <i class="feather icon-chevron-left"></i> Previous
                                                    </a>
                                                    </li>
                                                    <?php 
                                                    $payment_start_page = max(1, $payment_current_page - 2);
                                                    $payment_end_page = min($payment_total_pages, $payment_current_page + 2);
                                                    if ($payment_start_page > 1): ?>
                                                    <li class="page-item">
                                                    <a class="page-link" href="subscription_payments.php?payment_page=1<?= !empty($payment_search_query) ? '&payment_search=' . urlencode($payment_search_query) : '' ?>">1</a>
                                                    </li>
                                                    <?php if ($payment_start_page > 2): ?>
                                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                                    <?php endif; ?>
                                                    <?php endif; ?>
                                                    <?php for ($i = $payment_start_page; $i <= $payment_end_page; $i++): ?>
                                                    <li class="page-item <?= $i === $payment_current_page ? 'active' : '' ?>">
                                                    <a class="page-link" href="subscription_payments.php?payment_page=<?= $i ?><?= !empty($payment_search_query) ? '&payment_search=' . urlencode($payment_search_query) : '' ?>"><?= $i ?></a>
                                                    </li>
                                                    <?php endfor; ?>
                                                    <?php if ($payment_end_page < $payment_total_pages): ?>
                                                    <?php if ($payment_end_page < $payment_total_pages - 1): ?>
                                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                                    <?php endif; ?>
                                                    <li class="page-item">
                                                    <a class="page-link" href="subscription_payments.php?payment_page=<?= $payment_total_pages ?><?= !empty($payment_search_query) ? '&payment_search=' . urlencode($payment_search_query) : '' ?>"><?= $payment_total_pages ?></a>
                                                    </li>
                                                    <?php endif; ?>
                                                    <li class="page-item <?= $payment_current_page === $payment_total_pages ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="subscription_payments.php?payment_page=<?= $payment_current_page + 1 ?><?= !empty($payment_search_query) ? '&payment_search=' . urlencode($payment_search_query) : '' ?>">
                                                    Next <i class="feather icon-chevron-right"></i>
                                                    </a>
                                                    </li>
                                                    </ul>
                                                    </nav>
                                                    <div class="text-center mt-2 text-muted small">
                                                    Page <?= $payment_current_page ?> of <?= $payment_total_pages ?> | Showing <?= count($payments) ?> of <?= $payment_total_items ?> payments
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php else: ?>
                                                    <div class="text-center py-5">
                                                    <div class="mb-4">
                                                    <i class="feather icon-receipt text-muted" style="font-size: 4rem;"></i>
                                                    </div>
                                                    <h5 class="text-muted font-weight-bold mb-2"><?= __('no_payment_history_found') ?></h5>
                                                    <p class="text-muted mb-4"><?= __('payment_history_will_appear_here') ?></p>
                                                    <button type="button" class="btn btn-outline-primary btn-lg">
                                                    <i class="feather icon-refresh-cw mr-2"></i>Check for Updates
                                                    </button>
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
