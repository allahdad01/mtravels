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
require_once '../includes/db.php';
require_once '../includes/BranchAddonManager.php';
require_once '../includes/UserAddonManager.php';

// Check if $pdo is available
if (!isset($pdo) || !$pdo) {
    die("Database connection failed. Please contact administrator.");
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
                $payment_id = $pdo->lastInsertId();

                // Create system revenue record from subscription payment
                // Map currency to system_revenue supported values (USD, AFS)
                // AFN (Afghan Afghani) is same as AFS
                $revenue_currency = $currency === 'AFN' ? 'AFS' : ($currency === 'AFS' ? 'AFS' : 'USD');
                
                $revenue_stmt = $pdo->prepare("
                    INSERT INTO system_revenue
                    (tenant_id, revenue_type, amount, currency, payment_date, status, description, reference_id)
                    SELECT ts.tenant_id, 'subscription', ?, ?, ?, 'completed', CONCAT('Subscription Payment - ', p.name), ?
                    FROM tenant_subscriptions ts
                    LEFT JOIN plans p ON ts.plan_id = p.id
                    WHERE ts.id = ?
                ");
                $revenue_stmt->execute([$amount, $revenue_currency, $payment_date, 'sub_payment_' . $payment_id, $subscription_id]);

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

                    $pdo->commit();

                     // Send payment confirmation email to tenant with PDF invoice
                     require_once '../includes/functions.php';
                     $email_sent = sendPaymentConfirmationEmail($tenant_id, $amount, $currency, $payment_date, $billing_cycle, $payment_id, $subscription_id);
                     if (!$email_sent) {
                         error_log("Failed to send payment confirmation email for tenant: {$tenant_id}");
                         // Log for debugging
                         $stmt = $pdo->prepare("SELECT name, billing_email FROM tenants WHERE id = ?");
                         $stmt->execute([$tenant_id]);
                         $tenant_info = $stmt->fetch(PDO::FETCH_ASSOC);
                         error_log("Tenant billing email status: " . json_encode($tenant_info));
                     }
                } else {
                    $pdo->rollBack();
                    throw new Exception('Subscription not found');
                }

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

// Fetch all subscriptions with tenant info and payment history (excluding deleted and cancelled)
try {
    $stmt = $pdo->prepare("
        SELECT ts.*, t.id as tenant_id, t.name as tenant_name, t.identifier as tenant_identifier,
               p.name as plan_name, p.price as plan_price,
               COUNT(DISTINCT sp.id) as payment_count,
               COALESCE(SUM(sp.amount), 0) as total_paid,
               COALESCE((
                   SELECT SUM(ba.total_addon_cost) 
                   FROM branch_addons ba 
                   WHERE ba.tenant_id = ts.tenant_id AND ba.status = 'active'
               ), 0) as branch_addon_cost,
               COALESCE((
                   SELECT SUM(ua.total_addon_cost) 
                   FROM user_addons ua 
                   WHERE ua.tenant_id = ts.tenant_id AND ua.status = 'active'
               ), 0) as user_addon_cost,
               COALESCE((
                   SELECT SUM(ba.total_addon_cost) 
                   FROM branch_addons ba 
                   WHERE ba.tenant_id = ts.tenant_id AND ba.status = 'active'
               ), 0) + COALESCE((
                   SELECT SUM(ua.total_addon_cost) 
                   FROM user_addons ua 
                   WHERE ua.tenant_id = ts.tenant_id AND ua.status = 'active'
               ), 0) as total_addon_cost
        FROM tenant_subscriptions ts
        LEFT JOIN tenants t ON ts.tenant_id = t.id
        LEFT JOIN plans p ON ts.plan_id = p.id
        LEFT JOIN subscription_payments sp ON ts.id = sp.subscription_id
        WHERE ts.status IN ('active', 'pending')
        GROUP BY ts.id, t.id, t.name, t.identifier, p.name, p.price
        ORDER BY ts.created_at DESC
    ");
    $stmt->execute();
    $all_subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching subscriptions: " . $e->getMessage());
    $all_subscriptions = [];
}

// Pagination and search for subscriptions
$sub_items_per_page = 10;
$sub_current_page = intval($_GET['sub_page'] ?? 1);
$sub_search_query = $_GET['sub_search'] ?? '';

// Filter subscriptions based on search
$filtered_subscriptions = $all_subscriptions;
if (!empty($sub_search_query)) {
    $search_lower = strtolower($sub_search_query);
    $filtered_subscriptions = array_filter($all_subscriptions, function($sub) use ($search_lower) {
        return 
            strpos(strtolower($sub['tenant_name']), $search_lower) !== false ||
            strpos(strtolower($sub['tenant_identifier']), $search_lower) !== false ||
            strpos(strtolower($sub['plan_name']), $search_lower) !== false ||
            strpos(strtolower($sub['status']), $search_lower) !== false ||
            strpos(strtolower($sub['currency']), $search_lower) !== false;
    });
}

// Calculate pagination for subscriptions
$sub_total_items = count($filtered_subscriptions);
$sub_total_pages = ceil($sub_total_items / $sub_items_per_page);
$sub_current_page = max(1, min($sub_current_page, $sub_total_pages));
$sub_offset = ($sub_current_page - 1) * $sub_items_per_page;
$subscriptions = array_slice(array_values($filtered_subscriptions), $sub_offset, $sub_items_per_page);

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
        LIMIT 200
    ");
    $stmt->execute();
    $all_recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching recent payments: " . $e->getMessage());
    $all_recent_payments = [];
}

// Pagination and search for payments
$pay_items_per_page = 10;
$pay_current_page = intval($_GET['pay_page'] ?? 1);
$pay_search_query = $_GET['pay_search'] ?? '';

// Filter payments based on search
$filtered_payments = $all_recent_payments;
if (!empty($pay_search_query)) {
    $search_lower = strtolower($pay_search_query);
    $filtered_payments = array_filter($all_recent_payments, function($pay) use ($search_lower) {
        return 
            strpos(strtolower($pay['tenant_name']), $search_lower) !== false ||
            strpos(strtolower($pay['tenant_identifier']), $search_lower) !== false ||
            strpos(strtolower($pay['amount']), $search_lower) !== false ||
            strpos(strtolower($pay['currency']), $search_lower) !== false ||
            strpos(strtolower($pay['payment_method']), $search_lower) !== false;
    });
}

// Calculate pagination for payments
$pay_total_items = count($filtered_payments);
$pay_total_pages = ceil($pay_total_items / $pay_items_per_page);
$pay_current_page = max(1, min($pay_current_page, $pay_total_pages));
$pay_offset = ($pay_current_page - 1) * $pay_items_per_page;
$recent_payments = array_slice(array_values($filtered_payments), $pay_offset, $pay_items_per_page);
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
                                     <div class="card-header">
                                         <div class="row align-items-center">
                                             <div class="col-md-6">
                                                 <h4 class="mb-0"><i class="feather icon-credit-card mr-2"></i>All Subscriptions <span class="badge badge-pill badge-info"><?= $sub_total_items ?> total</span></h4>
                                             </div>
                                             <div class="col-md-6">
                                                 <form method="GET" class="form-inline float-right">
                                                     <input type="text" name="sub_search" class="form-control form-control-sm mr-2" 
                                                            placeholder="Search subscriptions..." value="<?= htmlspecialchars($sub_search_query) ?>" style="width: 250px;">
                                                     <button type="submit" class="btn btn-sm btn-primary">
                                                         <i class="feather icon-search"></i>
                                                     </button>
                                                     <?php if (!empty($sub_search_query)): ?>
                                                     <a href="subscription_payments.php" class="btn btn-sm btn-secondary ml-2">
                                                         <i class="feather icon-x"></i> Clear
                                                     </a>
                                                     <?php endif; ?>
                                                 </form>
                                             </div>
                                         </div>
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
                                                            <p class="text-muted">
                                                                <?php if (!empty($sub_search_query)): ?>
                                                                No subscriptions found for "<strong><?= htmlspecialchars($sub_search_query) ?></strong>"
                                                                <?php else: ?>
                                                                No subscriptions found
                                                                <?php endif; ?>
                                                            </p>
                                                        </td>
                                                    </tr>
                                                    <?php else: ?>
                                                    <?php foreach ($subscriptions as $sub): ?>
                                                    <?php 
                                                    $symbol = getCurrencySymbol($sub['currency'] ?? 'USD');
                                                    $totalWithAddons = floatval($sub['amount']) + floatval($sub['total_addon_cost']);
                                                    ?>
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
                                                             <span class="badge badge-primary"><?= htmlspecialchars($sub['plan_name'] ?? 'N/A') ?></span>
                                                         </td>
                                                         <td>
                                                             <span class="badge badge-<?= $sub['status'] === 'active' ? 'success' : ($sub['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                                 <?= ucfirst(htmlspecialchars($sub['status'])) ?>
                                                             </span>
                                                         </td>
                                                         <td><?= ucfirst(htmlspecialchars($sub['billing_cycle'])) ?></td>
                                                         <td>
                                                             <div><?= $symbol . number_format($sub['amount'], 2) ?></div>
                                                             <?php if ($sub['branch_addon_cost'] > 0 || $sub['user_addon_cost'] > 0): ?>
                                                             <small class="text-info">
                                                                 <?php if ($sub['branch_addon_cost'] > 0): ?>
                                                                 +<?= $symbol . number_format($sub['branch_addon_cost'], 2) ?> branch
                                                                 <?php endif; ?>
                                                                 <?php if ($sub['user_addon_cost'] > 0): ?>
                                                                 +<?= $symbol . number_format($sub['user_addon_cost'], 2) ?> users
                                                                 <?php endif; ?>
                                                             </small>
                                                             <?php endif; ?>
                                                         </td>
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
                                                         <td><?= $symbol . number_format($sub['total_paid'] ?? 0, 2) ?></td>
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
                                                    
                                                    <!-- Pagination for Subscriptions -->
                                                    <?php if ($sub_total_pages > 1): ?>
                                                    <nav aria-label="Subscriptions pagination" class="mt-3 mb-0">
                                                    <ul class="pagination justify-content-center mb-0">
                                                    <li class="page-item <?= $sub_current_page === 1 ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="subscription_payments.php?sub_page=<?= $sub_current_page - 1 ?><?= !empty($sub_search_query) ? '&sub_search=' . urlencode($sub_search_query) : '' ?>">
                                                        <i class="feather icon-chevron-left"></i> Previous
                                                    </a>
                                                    </li>
                                                    <?php 
                                                    $sub_start_page = max(1, $sub_current_page - 2);
                                                    $sub_end_page = min($sub_total_pages, $sub_current_page + 2);
                                                    if ($sub_start_page > 1): ?>
                                                    <li class="page-item">
                                                    <a class="page-link" href="subscription_payments.php?sub_page=1<?= !empty($sub_search_query) ? '&sub_search=' . urlencode($sub_search_query) : '' ?>">1</a>
                                                    </li>
                                                    <?php if ($sub_start_page > 2): ?>
                                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                                    <?php endif; ?>
                                                    <?php endif; ?>
                                                    <?php for ($i = $sub_start_page; $i <= $sub_end_page; $i++): ?>
                                                    <li class="page-item <?= $i === $sub_current_page ? 'active' : '' ?>">
                                                    <a class="page-link" href="subscription_payments.php?sub_page=<?= $i ?><?= !empty($sub_search_query) ? '&sub_search=' . urlencode($sub_search_query) : '' ?>"><?= $i ?></a>
                                                    </li>
                                                    <?php endfor; ?>
                                                    <?php if ($sub_end_page < $sub_total_pages): ?>
                                                    <?php if ($sub_end_page < $sub_total_pages - 1): ?>
                                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                                    <?php endif; ?>
                                                    <li class="page-item">
                                                    <a class="page-link" href="subscription_payments.php?sub_page=<?= $sub_total_pages ?><?= !empty($sub_search_query) ? '&sub_search=' . urlencode($sub_search_query) : '' ?>"><?= $sub_total_pages ?></a>
                                                    </li>
                                                    <?php endif; ?>
                                                    <li class="page-item <?= $sub_current_page === $sub_total_pages ? 'disabled' : '' ?>">
                                                    <a class="page-link" href="subscription_payments.php?sub_page=<?= $sub_current_page + 1 ?><?= !empty($sub_search_query) ? '&sub_search=' . urlencode($sub_search_query) : '' ?>">
                                                        Next <i class="feather icon-chevron-right"></i>
                                                    </a>
                                                    </li>
                                                    </ul>
                                                    </nav>
                                                    <div class="text-center mt-2 text-muted small">
                                                    Page <?= $sub_current_page ?> of <?= $sub_total_pages ?> | Showing <?= count($subscriptions) ?> of <?= $sub_total_items ?> subscriptions
                                                    </div>
                                                    <?php endif; ?>
                                                    </div>
                                                    </div>
                                                    </div>
                                                    </div>

                                                    <!-- Recent Payments -->
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="card shadow-lg border-0">
                                                                <div class="card-header">
                                                                    <div class="row align-items-center">
                                                                        <div class="col-md-6">
                                                                            <h4 class="mb-0"><i class="feather icon-clock mr-2"></i>Recent Payments <span class="badge badge-pill badge-success"><?= $pay_total_items ?> total</span></h4>
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <form method="GET" class="form-inline float-right">
                                                                                <input type="text" name="pay_search" class="form-control form-control-sm mr-2" 
                                                                                       placeholder="Search payments..." value="<?= htmlspecialchars($pay_search_query) ?>" style="width: 250px;">
                                                                                <button type="submit" class="btn btn-sm btn-primary">
                                                                                    <i class="feather icon-search"></i>
                                                                                </button>
                                                                                <?php if (!empty($pay_search_query)): ?>
                                                                                <a href="subscription_payments.php" class="btn btn-sm btn-secondary ml-2">
                                                                                    <i class="feather icon-x"></i> Clear
                                                                                </a>
                                                                                <?php endif; ?>
                                                                            </form>
                                                                        </div>
                                                                    </div>
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
                                                                                <th style="width: 100px; text-align: center;">Actions</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php if (empty($recent_payments)): ?>
                                                                            <tr>
                                                                                <td colspan="8" class="text-center py-4">
                                                                                    <i class="feather icon-inbox text-muted mb-2" style="font-size: 2rem;"></i>
                                                                                    <p class="text-muted">
                                                                                        <?php if (!empty($pay_search_query)): ?>
                                                                                        No payments found for "<strong><?= htmlspecialchars($pay_search_query) ?></strong>"
                                                                                        <?php else: ?>
                                                                                        No payments recorded yet
                                                                                        <?php endif; ?>
                                                                                    </p>
                                                                                </td>
                                                                            </tr>
                                                                            <?php else: ?>
                                                                            <?php foreach ($recent_payments as $payment): ?>
                                                                            <?php $paymentSymbol = getCurrencySymbol($payment['currency'] ?? 'USD'); ?>
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
                                                                                 <td><?= $paymentSymbol . number_format($payment['amount'], 2) ?> <?= htmlspecialchars($payment['currency'] ?? 'USD') ?></td>
                                                                                <td><?= htmlspecialchars($payment['payment_method'] ?: 'N/A') ?></td>
                                                                                <td><?= htmlspecialchars($payment['receipt_number'] ?: 'N/A') ?></td>
                                                                                <td><?= htmlspecialchars($payment['processed_by_name'] ?: 'System') ?></td>
                                                                                <td style="text-align: center;">
                                                                                    <button class="btn btn-sm btn-info" onclick="downloadInvoice(<?= $payment['id'] ?>)" title="Download Invoice PDF">
                                                                                        <i class="feather icon-download"></i>
                                                                                    </button>
                                                                                </td>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                            <?php endif; ?>
                                                                        </tbody>
                                                                        </table>
                                                                        </div>
                                                                        
                                                                        <!-- Pagination for Payments -->
                                                                        <?php if ($pay_total_pages > 1): ?>
                                                                        <nav aria-label="Payments pagination" class="mt-3 mb-0">
                                                                        <ul class="pagination justify-content-center mb-0">
                                                                        <li class="page-item <?= $pay_current_page === 1 ? 'disabled' : '' ?>">
                                                                        <a class="page-link" href="subscription_payments.php?pay_page=<?= $pay_current_page - 1 ?><?= !empty($pay_search_query) ? '&pay_search=' . urlencode($pay_search_query) : '' ?>">
                                                                            <i class="feather icon-chevron-left"></i> Previous
                                                                        </a>
                                                                        </li>
                                                                        <?php 
                                                                        $pay_start_page = max(1, $pay_current_page - 2);
                                                                        $pay_end_page = min($pay_total_pages, $pay_current_page + 2);
                                                                        if ($pay_start_page > 1): ?>
                                                                        <li class="page-item">
                                                                        <a class="page-link" href="subscription_payments.php?pay_page=1<?= !empty($pay_search_query) ? '&pay_search=' . urlencode($pay_search_query) : '' ?>">1</a>
                                                                        </li>
                                                                        <?php if ($pay_start_page > 2): ?>
                                                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                                                        <?php endif; ?>
                                                                        <?php endif; ?>
                                                                        <?php for ($i = $pay_start_page; $i <= $pay_end_page; $i++): ?>
                                                                        <li class="page-item <?= $i === $pay_current_page ? 'active' : '' ?>">
                                                                        <a class="page-link" href="subscription_payments.php?pay_page=<?= $i ?><?= !empty($pay_search_query) ? '&pay_search=' . urlencode($pay_search_query) : '' ?>"><?= $i ?></a>
                                                                        </li>
                                                                        <?php endfor; ?>
                                                                        <?php if ($pay_end_page < $pay_total_pages): ?>
                                                                        <?php if ($pay_end_page < $pay_total_pages - 1): ?>
                                                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                                                        <?php endif; ?>
                                                                        <li class="page-item">
                                                                        <a class="page-link" href="subscription_payments.php?pay_page=<?= $pay_total_pages ?><?= !empty($pay_search_query) ? '&pay_search=' . urlencode($pay_search_query) : '' ?>"><?= $pay_total_pages ?></a>
                                                                        </li>
                                                                        <?php endif; ?>
                                                                        <li class="page-item <?= $pay_current_page === $pay_total_pages ? 'disabled' : '' ?>">
                                                                        <a class="page-link" href="subscription_payments.php?pay_page=<?= $pay_current_page + 1 ?><?= !empty($pay_search_query) ? '&pay_search=' . urlencode($pay_search_query) : '' ?>">
                                                                            Next <i class="feather icon-chevron-right"></i>
                                                                        </a>
                                                                        </li>
                                                                        </ul>
                                                                        </nav>
                                                                        <div class="text-center mt-2 text-muted small">
                                                                        Page <?= $pay_current_page ?> of <?= $pay_total_pages ?> | Showing <?= count($recent_payments) ?> of <?= $pay_total_items ?> payments
                                                                        </div>
                                                                        <?php endif; ?>
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
                                     <?php 
                                     $subSymbol = getCurrencySymbol($sub['currency'] ?? 'USD');
                                     $subTotal = floatval($sub['amount']) + floatval($sub['total_addon_cost']);
                                     $addonsText = '';
                                     if ($sub['branch_addon_cost'] > 0 || $sub['user_addon_cost'] > 0) {
                                         $addons = [];
                                         if ($sub['branch_addon_cost'] > 0) {
                                             $addons[] = $subSymbol . number_format($sub['branch_addon_cost'], 2) . ' branch';
                                         }
                                         if ($sub['user_addon_cost'] > 0) {
                                             $addons[] = $subSymbol . number_format($sub['user_addon_cost'], 2) . ' users';
                                         }
                                         $addonsText = ' + ' . implode(', ', $addons);
                                     }
                                     ?>
                                     <option value="<?= $sub['id'] ?>" data-currency="<?= htmlspecialchars($sub['currency'] ?? 'USD') ?>" data-amount="<?= $subTotal ?>">
                                         <?= htmlspecialchars($sub['tenant_name']) ?> - <?= htmlspecialchars($sub['plan_name']) ?> (<?= $subSymbol . number_format($sub['amount'], 2) ?><?= $addonsText ?>/<?= $sub['billing_cycle'] ?>)
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
                                         <span class="input-group-text" id="amountSymbol">$</span>
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
                                     <option value="AFN">AFN (؋)</option>
                                     <option value="EUR">EUR (€)</option>
                                     <option value="GBP">GBP (£)</option>
                                     <option value="AED">AED (د.إ)</option>
                                     <option value="INR">INR (₹)</option>
                                     <option value="PKR">PKR (₨)</option>
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
// Currency symbol mapping
const currencySymbols = {
    'USD': '$',
    'EUR': '€',
    'GBP': '£',
    'JPY': '¥',
    'AFN': '؋',
    'AED': 'د.إ',
    'INR': '₹',
    'PKR': '₨',
};

$(document).ready(function() {
    // Handle subscription selection - populate currency and amount
    $('#subscription_id').on('change', function() {
        const option = $(this).find('option:selected');
        const currency = option.data('currency') || 'USD';
        const amount = option.data('amount') || '';
        const symbol = currencySymbols[currency] || currency;
        
        $('#currency').val(currency);
        $('#amount').val(amount);
        $('#amountSymbol').text(symbol);
    });
    
    // Handle currency change - update symbol
    $('#currency').on('change', function() {
        const currency = $(this).val();
        const symbol = currencySymbols[currency] || currency;
        $('#amountSymbol').text(symbol);
    });
});

function generateInvoicePreview() {
    const subscriptionId = $('#subscription_id').val();
    const amount = $('#amount').val();
    const currency = $('#currency').val();
    const paymentDate = $('#payment_date').val();
    const paymentMethod = $('#payment_method').val();
    const transactionId = $('#transaction_id').val();
    const receiptNumber = $('#receipt_number').val();
    const notes = $('#notes').val();

    if (!subscriptionId || !amount) {
        alert('Please select a subscription and enter an amount');
        return;
    }

    // Open PDF in new window or download
    window.open('generate_invoice_pdf.php?subscription_id=' + subscriptionId + 
                '&amount=' + amount + 
                '&currency=' + currency + 
                '&payment_date=' + paymentDate + 
                '&payment_method=' + encodeURIComponent(paymentMethod) +
                '&transaction_id=' + encodeURIComponent(transactionId) +
                '&receipt_number=' + encodeURIComponent(receiptNumber) +
                '&notes=' + encodeURIComponent(notes), 'invoice');
}

function downloadInvoice(paymentId) {
    if (!paymentId) {
        alert('Invalid payment ID');
        return;
    }
    
    // Open PDF for existing payment
    window.open('generate_invoice_pdf.php?payment_id=' + paymentId, 'invoice');
}

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
