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
enforce_super_admin();

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
                         // Log for debugging
                         $stmt = $pdo->prepare("SELECT name, billing_email FROM tenants WHERE id = ?");
                         $stmt->execute([$tenant_id]);
                         $tenant_info = $stmt->fetch(PDO::FETCH_ASSOC);
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

// Fetch recent payments with pagination
$payment_items_per_page = 10;
$payment_current_page = intval($_GET['payment_page'] ?? 1);
$payment_search_query = $_GET['payment_search'] ?? '';

try {
     $stmt = $pdo->prepare("
         SELECT sp.*, ts.plan_id, t.name as tenant_name, t.identifier as tenant_identifier,
                u.name as processed_by_name
         FROM subscription_payments sp
         LEFT JOIN tenant_subscriptions ts ON sp.subscription_id = ts.id
         LEFT JOIN tenants t ON ts.tenant_id = t.id
         LEFT JOIN users u ON sp.processed_by = u.id
         ORDER BY sp.created_at DESC
     ");
     $stmt->execute();
     $all_recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
     $all_recent_payments = [];
}

// Filter payments based on search
$filtered_payments = $all_recent_payments;
if (!empty($payment_search_query)) {
     $search_lower = strtolower($payment_search_query);
     $filtered_payments = array_filter($all_recent_payments, function($payment) use ($search_lower) {
         return 
             strpos(strtolower($payment['tenant_name'] ?? ''), $search_lower) !== false ||
             strpos(strtolower($payment['tenant_identifier'] ?? ''), $search_lower) !== false ||
             strpos(strtolower($payment['transaction_id'] ?? ''), $search_lower) !== false ||
             strpos(strtolower($payment['receipt_number'] ?? ''), $search_lower) !== false;
     });
}

// Calculate pagination for payments
$payment_total_items = count($filtered_payments);
$payment_total_pages = ceil($payment_total_items / $payment_items_per_page);
$payment_current_page = max(1, min($payment_current_page, $payment_total_pages));
$payment_offset = ($payment_current_page - 1) * $payment_items_per_page;
$recent_payments = array_slice(array_values($filtered_payments), $payment_offset, $payment_items_per_page);

// Use payment pagination defined above (payment_current_page, payment_total_pages, payment_search_query)
$pay_items_per_page = $payment_items_per_page;
$pay_current_page = $payment_current_page;
$pay_search_query = $payment_search_query;
$pay_total_items = $payment_total_items;
$pay_total_pages = $payment_total_pages;
?>

<style>
/* ─── ROOT VARIABLES ──────────────────────────────────────────── */
:root {
    --muted: #999;
    --surface: #ffffff;
    --surface2: #f5f5f5;
    --border: #e0e0e0;
    --text: #333333;
    --green: #28a745;
    --red: #dc3545;
}

/* ─── SUBSCRIPTION CARD STYLES ───────────────────────────── */
.sa-subscription-list, .sa-payment-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.sa-subscription-card, .sa-payment-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 20px;
    transition: all 0.2s ease;
}

.sa-subscription-card:hover, .sa-payment-card:hover {
    border-color: rgba(64, 153, 255, 0.3);
    box-shadow: 0 4px 16px rgba(64, 153, 255, 0.15);
}

.ssc-header, .spc-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e0e0e0;
}

.ssc-info h4, .spc-info h4 {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 6px 0;
    color: #333;
}

.ssc-identifier, .spc-identifier {
    font-size: 0.85rem;
    color: #999;
    margin: 0;
}

.ssc-plan, .spc-date {
    font-size: 0.85rem;
    color: #4099ff;
    margin: 8px 0 0 0;
    display: flex;
    align-items: center;
    gap: 6px;
}

.ssc-plan i, .spc-date i {
    font-size: 0.9rem;
}

.ssc-status, .spc-amount {
    text-align: right;
}

.amount-value {
    font-size: 1.4rem;
    font-weight: 700;
    color: #2ed8b6;
}

.amount-currency {
    font-size: 0.85rem;
    color: #888;
    margin-left: 4px;
}

.ssc-details, .spc-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}

.ssc-detail-item, .spc-detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.ssc-detail-label, .spc-detail-label {
    font-size: 0.75rem;
    color: #999;
    font-weight: 600;
    text-transform: uppercase;
}

.ssc-detail-value, .spc-detail-value {
    font-size: 0.9rem;
    font-weight: 500;
    color: #444;
}

.ssc-addons {
    font-size: 0.8rem;
    color: #4099ff;
}

.ssc-total {
    font-weight: 700;
    color: #2ed8b6;
}

.ssc-actions, .spc-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

/* ─── PAGE HEADER ─────────────────────────────────────────── */
.page-header.card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 2rem 2.5rem;
    border: none;
    margin-bottom: 2rem;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.25);
    position: relative;
    overflow: hidden;
}

.page-header.card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    pointer-events: none;
}

.page-header.card::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -5%;
    width: 300px;
    height: 300px;
    background: rgba(255,255,255,0.03);
    border-radius: 50%;
    pointer-events: none;
}

.page-header.card .row {
    position: relative;
    z-index: 1;
}

.page-header-content {
    padding: 0.5rem 0;
}

.page-title {
    font-size: 1.75rem;
    font-weight: 700;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    line-height: 1.2;
}

.page-title i {
    font-size: 2rem;
    margin-right: 0.75rem;
    opacity: 0.95;
}

.page-subtitle {
    font-size: 0.95rem;
    opacity: 0.85;
    font-weight: 400;
    letter-spacing: 0.3px;
}

.page-header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    justify-content: flex-end;
    width: 100%;
}

.page-header.card .btn {
    background: rgba(255,255,255,0.10) !important;
    color: #ffffff;
    border: 1px solid rgba(255,255,255,0.30) !important;
    border-radius: 25px;
    transition: all 0.3s ease;
    position: relative;
    z-index: 1;
}

.page-header.card .btn:hover {
    background: rgba(255,255,255,0.22) !important;
    border-color: rgba(255,255,255,0.50) !important;
    transform: translateY(-1px);
}

/* ─── CARDS ───────────────────────────────────────────────── */
.sa-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border: none;
}

.sa-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.sa-card-body {
    padding: 1.5rem;
}

/* ─── BUTTONS ─────────────────────────────────────────────── */
.sa-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    border: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    font-size: 0.9rem;
}

.sa-btn-primary {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: white;
}

.sa-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
}

.sa-btn-small {
    padding: 6px 12px;
    font-size: 0.75rem;
}

.sa-btn-ghost {
    background: #f0f0f0;
    color: #333;
    border: 1px solid #e0e0e0;
}

.sa-btn-ghost:hover {
    background: #e8e8e8;
    border-color: #d0d0d0;
}

.sa-btn-info {
    background: linear-gradient(135deg, #11cdef 0%, #2dd4bf 100%);
    color: white;
}

.sa-btn-info:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(17, 207, 239, 0.3);
}

/* ─── SEARCH & FILTER ─────────────────────────────────────── */
.sa-search-filter {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.sa-search-group {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex: 1;
    min-width: 300px;
}

.sa-search-input {
    flex: 1;
    min-width: 150px;
    padding: 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 8px;
    font-size: 0.9rem;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.sa-search-input:focus {
    outline: none;
    border-color: #4099ff;
    box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
}

/* ─── SECTION HEADER ──────────────────────────────────────── */
.sa-shdr {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.sa-shdr h2 {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    color: #333;
}

.sa-shdr p {
    margin: 4px 0 0 0;
    font-size: 0.75rem;
    color: var(--muted);
}

/* ─── PILLS ───────────────────────────────────────────────── */
.pill {
    font-size: 0.62rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

.pill-green {
    background: rgba(16,185,129,0.12);
    color: #10b981;
}

.pill-amber {
    background: rgba(245,158,11,0.12);
    color: #f59e0b;
}

/* ─── PAGINATION ──────────────────────────────────────────── */
.sa-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 20px;
    padding: 14px;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    flex-wrap: wrap;
}

.sa-pagination-item {
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    background: #f5f5f5;
    color: #333;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
}

.sa-pagination-item:hover:not(.active) {
    background: rgba(64, 153, 255, 0.1);
    border-color: #4099ff;
    color: #4099ff;
}

.sa-pagination-item.active {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    border-color: #4099ff;
    color: white;
}

.sa-pagination-ellipsis {
    color: #999;
    font-size: 0.8rem;
}

.sa-pagination-info {
    font-size: 0.8rem;
    color: #999;
    margin-left: auto;
}

/* ─── RESPONSIVE ──────────────────────────────────────────── */
@media (max-width: 768px) {
    .ssc-header, .spc-header {
        flex-direction: column;
    }
    
    .ssc-status, .spc-amount {
        text-align: left;
        margin-top: 12px;
    }
    
    .ssc-details, .spc-details {
        grid-template-columns: 1fr;
    }
    
    .ssc-actions, .spc-actions {
        width: 100%;
    }
    
    .page-header.card {
        padding: 1.5rem;
    }
}
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="page-header-content">
                                <h5 class="page-title mb-0">
                                    <i class="feather icon-credit-card mr-2"></i>Subscription Payments
                                </h5>
                                <p class="page-subtitle mb-0 mt-2">
                                    Manage subscription payments and billing
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="page-header-actions">
                                <button type="button" class="btn" data-toggle="modal" data-target="#recordPaymentModal">
                                    <i class="feather icon-plus mr-1"></i>Record Payment
                                </button>
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


                        <!-- Subscriptions Header -->
                        <div class="sa-shdr" style="margin-bottom: 16px;">
                            <div>
                                <h2>All Subscriptions</h2>
                                <p style="margin: 4px 0 0 0; font-size: 0.75rem; color: var(--text-muted);">Total: <?= $sub_total_items ?> subscriptions</p>
                            </div>
                        </div>

                        <!-- Filter Bar -->
                        <div class="sa-card" style="margin-bottom: 20px;">
                            <div class="sa-card-body">
                                <form method="GET" action="subscription_payments.php" class="sa-search-filter">
                                    <div class="sa-search-group">
                                        <input type="text" class="sa-search-input" name="sub_search" placeholder="Search subscriptions..." value="<?= htmlspecialchars($sub_search_query) ?>">
                                        <button type="submit" class="sa-btn sa-btn-primary">Search</button>
                                        <?php if (!empty($sub_search_query)): ?>
                                        <a href="subscription_payments.php" class="sa-btn sa-btn-ghost">Clear</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Subscriptions Cards -->
                        <?php if (empty($subscriptions)): ?>
                        <div class="sa-card">
                            <div class="sa-card-body" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                                <div style="font-size: 2rem; margin-bottom: 12px;">📋</div>
                                <div style="font-weight: 600; margin-bottom: 4px;">No Subscriptions Found</div>
                                <div style="font-size: 0.8rem;"><?= !empty($sub_search_query) ? 'Try adjusting your search.' : 'No subscriptions available.' ?></div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="sa-subscription-list">
                            <?php foreach ($subscriptions as $sub): ?>
                            <?php 
                            $symbol = getCurrencySymbol($sub['currency'] ?? 'USD');
                            $totalWithAddons = floatval($sub['amount']) + floatval($sub['total_addon_cost']);
                            ?>
                            <div class="sa-subscription-card">
                                <div class="ssc-header">
                                    <div class="ssc-info">
                                        <h4><?= htmlspecialchars($sub['tenant_name']) ?></h4>
                                        <p class="ssc-identifier"><?= htmlspecialchars($sub['tenant_identifier']) ?></p>
                                        <p class="ssc-plan">
                                            <i class="feather icon-credit-card"></i>
                                            <?= htmlspecialchars($sub['plan_name'] ?? 'N/A') ?>
                                        </p>
                                    </div>
                                    <div class="ssc-status">
                                        <span class="pill <?= $sub['status'] === 'active' ? 'pill-green' : ($sub['status'] === 'pending' ? 'pill-amber' : 'pill-red') ?>">
                                            <?= htmlspecialchars(ucfirst($sub['status'])) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="ssc-details">
                                    <div class="ssc-detail-item">
                                        <span class="ssc-detail-label">Billing Cycle</span>
                                        <span class="ssc-detail-value"><?= htmlspecialchars(ucfirst($sub['billing_cycle'])) ?></span>
                                    </div>
                                    <div class="ssc-detail-item">
                                        <span class="ssc-detail-label">Amount</span>
                                        <span class="ssc-detail-value"><?= $symbol . number_format($sub['amount'], 2) ?></span>
                                    </div>
                                    <div class="ssc-detail-item">
                                        <span class="ssc-detail-label">Add-ons</span>
                                        <span class="ssc-detail-value ssc-addons">
                                            <?php if ($sub['branch_addon_cost'] > 0 || $sub['user_addon_cost'] > 0): ?>
                                                <?php if ($sub['branch_addon_cost'] > 0): ?>
                                                +<?= $symbol . number_format($sub['branch_addon_cost'], 2) ?> branch
                                                <?php endif; ?>
                                                <?php if ($sub['user_addon_cost'] > 0): ?>
                                                +<?= $symbol . number_format($sub['user_addon_cost'], 2) ?> users
                                                <?php endif; ?>
                                            <?php else: ?>
                                            -
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="ssc-detail-item">
                                        <span class="ssc-detail-label">Last Payment</span>
                                        <span class="ssc-detail-value"><?= $sub['last_payment_date'] ? date('M d, Y', strtotime($sub['last_payment_date'])) : 'Never' ?></span>
                                    </div>
                                    <div class="ssc-detail-item">
                                        <span class="ssc-detail-label">Next Billing</span>
                                        <span class="ssc-detail-value"><?= $sub['next_billing_date'] ? date('M d, Y', strtotime($sub['next_billing_date'])) : 'N/A' ?></span>
                                    </div>
                                    <div class="ssc-detail-item">
                                        <span class="ssc-detail-label">Total Paid</span>
                                        <span class="ssc-detail-value ssc-total"><?= $symbol . number_format($sub['total_paid'] ?? 0, 2) ?></span>
                                    </div>
                                </div>
                                
                                <div class="ssc-actions">
                                    <button class="sa-btn sa-btn-small sa-btn-primary" onclick="viewSubscriptionPayments(<?= $sub['id'] ?>)">
                                        <i class="feather icon-eye"></i> View Payments
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Pagination for Subscriptions -->
                        <?php if ($sub_total_pages > 1): ?>
                        <div class="sa-pagination">
                            <?php 
                            $query_string = !empty($sub_search_query) ? '&sub_search=' . urlencode($sub_search_query) : '';
                            $start_page = max(1, $sub_current_page - 2);
                            $end_page = min($sub_total_pages, $sub_current_page + 2);
                            ?>
                            
                            <?php if ($sub_current_page > 1): ?>
                            <a href="?sub_page=1<?= $query_string ?>" class="sa-pagination-item">First</a>
                            <a href="?sub_page=<?= $sub_current_page - 1 ?><?= $query_string ?>" class="sa-pagination-item">← Prev</a>
                            <?php endif; ?>
                            
                            <?php if ($start_page > 1): ?>
                            <span class="sa-pagination-ellipsis">...</span>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?sub_page=<?= $i ?><?= $query_string ?>" class="sa-pagination-item <?= $i === $sub_current_page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $sub_total_pages): ?>
                            <span class="sa-pagination-ellipsis">...</span>
                            <?php endif; ?>
                            
                            <?php if ($sub_current_page < $sub_total_pages): ?>
                            <a href="?sub_page=<?= $sub_current_page + 1 ?><?= $query_string ?>" class="sa-pagination-item">Next →</a>
                            <a href="?sub_page=<?= $sub_total_pages ?><?= $query_string ?>" class="sa-pagination-item">Last</a>
                            <?php endif; ?>
                            
                            <span class="sa-pagination-info">Page <?= $sub_current_page ?> of <?= $sub_total_pages ?></span>
                        </div>
                        <?php endif; ?>

                        <!-- Recent Payments Header -->
                        <div class="sa-shdr" style="margin-top: 30px; margin-bottom: 16px;">
                            <div>
                                <h2>Recent Payments</h2>
                                <p style="margin: 4px 0 0 0; font-size: 0.75rem; color: var(--text-muted);">Total: <?= $pay_total_items ?> payments</p>
                            </div>
                        </div>

                        <!-- Payments Filter Bar -->
                        <div class="sa-card" style="margin-bottom: 20px;">
                            <div class="sa-card-body">
                                <form method="GET" action="subscription_payments.php" class="sa-search-filter">
                                    <div class="sa-search-group">
                                        <input type="text" class="sa-search-input" name="pay_search" placeholder="Search payments..." value="<?= htmlspecialchars($pay_search_query) ?>">
                                        <button type="submit" class="sa-btn sa-btn-primary">Search</button>
                                        <?php if (!empty($pay_search_query)): ?>
                                        <a href="subscription_payments.php" class="sa-btn sa-btn-ghost">Clear</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Recent Payments Cards -->
                        <?php if (empty($recent_payments)): ?>
                        <div class="sa-card">
                            <div class="sa-card-body" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                                <div style="font-size: 2rem; margin-bottom: 12px;">💳</div>
                                <div style="font-weight: 600; margin-bottom: 4px;">No Payments Found</div>
                                <div style="font-size: 0.8rem;"><?= !empty($pay_search_query) ? 'Try adjusting your search.' : 'No payments recorded yet.' ?></div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="sa-payment-list">
                            <?php foreach ($recent_payments as $payment): ?>
                            <?php $paymentSymbol = getCurrencySymbol($payment['currency'] ?? 'USD'); ?>
                            <div class="sa-payment-card">
                                <div class="spc-header">
                                    <div class="spc-info">
                                        <h4><?= htmlspecialchars($payment['tenant_name']) ?></h4>
                                        <p class="spc-identifier"><?= htmlspecialchars($payment['tenant_identifier']) ?></p>
                                        <p class="spc-date">
                                            <i class="feather icon-calendar"></i>
                                            <?= date('M d, Y', strtotime($payment['payment_date'])) ?>
                                        </p>
                                    </div>
                                    <div class="spc-amount">
                                        <span class="amount-value"><?= $paymentSymbol . number_format($payment['amount'], 2) ?></span>
                                        <span class="amount-currency"><?= htmlspecialchars($payment['currency'] ?? 'USD') ?></span>
                                    </div>
                                </div>
                                
                                <div class="spc-details">
                                    <div class="spc-detail-item">
                                        <span class="spc-detail-label">Plan</span>
                                        <span class="spc-detail-value"><?= htmlspecialchars($payment['plan_id']) ?></span>
                                    </div>
                                    <div class="spc-detail-item">
                                        <span class="spc-detail-label">Method</span>
                                        <span class="spc-detail-value"><?= htmlspecialchars($payment['payment_method'] ?: 'N/A') ?></span>
                                    </div>
                                    <div class="spc-detail-item">
                                        <span class="spc-detail-label">Receipt</span>
                                        <span class="spc-detail-value"><?= htmlspecialchars($payment['receipt_number'] ?: 'N/A') ?></span>
                                    </div>
                                    <div class="spc-detail-item">
                                        <span class="spc-detail-label">Processed By</span>
                                        <span class="spc-detail-value"><?= htmlspecialchars($payment['processed_by_name'] ?: 'System') ?></span>
                                    </div>
                                </div>
                                
                                <div class="spc-actions">
                                    <button class="sa-btn sa-btn-small sa-btn-info" onclick="downloadInvoice(<?= $payment['id'] ?>)">
                                        <i class="feather icon-download"></i> Download Invoice
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Pagination for Payments -->
                        <?php if ($pay_total_pages > 1): ?>
                        <div class="sa-pagination">
                            <?php 
                            $query_string = !empty($pay_search_query) ? '&pay_search=' . urlencode($pay_search_query) : '';
                            $start_page = max(1, $pay_current_page - 2);
                            $end_page = min($pay_total_pages, $pay_current_page + 2);
                            ?>
                            
                            <?php if ($pay_current_page > 1): ?>
                            <a href="?pay_page=1<?= $query_string ?>" class="sa-pagination-item">First</a>
                            <a href="?pay_page=<?= $pay_current_page - 1 ?><?= $query_string ?>" class="sa-pagination-item">← Prev</a>
                            <?php endif; ?>
                            
                            <?php if ($start_page > 1): ?>
                            <span class="sa-pagination-ellipsis">...</span>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?pay_page=<?= $i ?><?= $query_string ?>" class="sa-pagination-item <?= $i === $pay_current_page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $pay_total_pages): ?>
                            <span class="sa-pagination-ellipsis">...</span>
                            <?php endif; ?>
                            
                            <?php if ($pay_current_page < $pay_total_pages): ?>
                            <a href="?pay_page=<?= $pay_current_page + 1 ?><?= $query_string ?>" class="sa-pagination-item">Next →</a>
                            <a href="?pay_page=<?= $pay_total_pages ?><?= $query_string ?>" class="sa-pagination-item">Last</a>
                            <?php endif; ?>
                            
                            <span class="sa-pagination-info">Page <?= $pay_current_page ?> of <?= $pay_total_pages ?></span>
                        </div>
                        <?php endif; ?>

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
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);">
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
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);">
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
