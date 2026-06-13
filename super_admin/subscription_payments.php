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
if (!function_exists('getCurrencySymbol')) {
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
                   WHERE ba.tenant_id = ts.tenant_id AND ba.status = 'active' AND ba.billing_cycle = ts.billing_cycle
               ), 0) as branch_addon_cost,
               COALESCE((
                   SELECT SUM(ua.total_addon_cost) 
                   FROM user_addons ua 
                   WHERE ua.tenant_id = ts.tenant_id AND ua.status = 'active' AND ua.billing_cycle = ts.billing_cycle
               ), 0) as user_addon_cost,
               COALESCE((
                   SELECT SUM(ca.addon_price)
                   FROM communication_addons ca
                   WHERE ca.tenant_id = ts.tenant_id AND ca.status = 'active' AND ca.billing_cycle = ts.billing_cycle
               ), 0) as communication_addon_cost,
               COALESCE((
                   SELECT SUM(ba.total_addon_cost) 
                   FROM branch_addons ba 
                   WHERE ba.tenant_id = ts.tenant_id AND ba.status = 'active' AND ba.billing_cycle = ts.billing_cycle
               ), 0) + COALESCE((
                   SELECT SUM(ua.total_addon_cost) 
                   FROM user_addons ua 
                   WHERE ua.tenant_id = ts.tenant_id AND ua.status = 'active' AND ua.billing_cycle = ts.billing_cycle
               ), 0) + COALESCE((
                   SELECT SUM(ca.addon_price)
                   FROM communication_addons ca
                   WHERE ca.tenant_id = ts.tenant_id AND ca.status = 'active' AND ca.billing_cycle = ts.billing_cycle
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
/* ─── ROOT VARIABLES ─────────────────────────────────────── */
:root {
    --muted: #999;
    --surface: #ffffff;
    --surface2: #f5f5f5;
    --border: #e0e0e0;
    --text: #333333;
    --green: #28a745;
    --red: #dc3545;
}

/* ─── PAGE HEADER ────────────────────────────────────────── */
.page-header.card {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: white;
    border-radius: 10px;
    padding: 2rem 2.5rem;
    border: none;
    margin-bottom: 2rem;
    box-shadow: 0 8px 24px rgba(64,153,255,0.25);
    position: relative;
    overflow: hidden;
}
.page-header-content h5 {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    letter-spacing: -0.5px;
}
.page-header-content p {
    font-size: 0.95rem;
    opacity: 0.85;
    margin: 8px 0 0 0;
}
.page-header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    justify-content: flex-end;
}
.page-header-actions .sa-btn {
    background: rgba(255,255,255,0.15);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 25px;
    padding: 0.6rem 1.4rem;
}
.page-header-actions .sa-btn:hover {
    background: rgba(255,255,255,0.25);
    border-color: rgba(255,255,255,0.5);
    transform: translateY(-1px);
}

/* ─── CARDS ──────────────────────────────────────────────── */
.sa-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: none;
}
.sa-card-body { padding: 1.5rem; }

/* ─── BUTTONS ────────────────────────────────────────────── */
.sa-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    border: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.9rem;
}
.sa-btn-primary {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: white;
}
.sa-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(64,153,255,0.3);
}
.sa-btn-small { padding: 6px 12px; font-size: 0.75rem; }
.sa-btn-ghost {
    background: #f0f0f0;
    color: #333;
    border: 1px solid #e0e0e0;
}
.sa-btn-ghost:hover { background: #e8e8e8; border-color: #d0d0d0; }
.sa-btn-info {
    background: linear-gradient(135deg, #11cdef 0%, #2dd4bf 100%);
    color: white;
}
.sa-btn-info:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(17,207,239,0.3);
}
.sa-btn-icon {
    width: 34px; height: 34px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: #f8f9fa;
    cursor: pointer;
    display: inline-flex;
    align-items: center; justify-content: center;
    transition: all 0.2s;
}
.sa-btn-icon:hover {
    background: rgba(64,153,255,0.1);
    border-color: #4099ff;
}
.sa-btn-icon svg { width: 16px; height: 16px; stroke: #666; }
.sa-btn-icon:hover svg { stroke: #4099ff; }

/* ─── SEARCH & FILTER ────────────────────────────────────── */
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
    transition: border-color 0.15s;
}
.sa-search-input:focus {
    outline: none;
    border-color: #4099ff;
    box-shadow: 0 0 0 0.2rem rgba(64,153,255,0.25);
}

/* ─── SECTION HEADER ─────────────────────────────────────── */
.sa-shdr {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.sa-shdr h2 { font-size: 1.5rem; font-weight: 600; margin: 0; color: #333; }
.sa-shdr p { margin: 4px 0 0 0; font-size: 0.75rem; color: var(--muted); }

/* ─── TABLE ──────────────────────────────────────────────── */
.sa-table-wrap {
    background: #fff;
    border-radius: 10px;
    border: 1px solid var(--border);
    overflow-x: auto;
}
.sa-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
    min-width: 700px;
}
.sa-table thead { background: #f8f9fa; }
.sa-table th {
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: #666;
    border-bottom: 2px solid var(--border);
    white-space: nowrap;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.sa-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}
.sa-table tbody tr { transition: background 0.15s; }
.sa-table tbody tr:hover { background: #f8f9fa; }
.sa-table tbody tr:last-child td { border-bottom: none; }

/* ─── TENANT AVATAR ──────────────────────────────────────── */
.sa-td-tenant {
    display: flex;
    align-items: center;
    gap: 10px;
}
.sa-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.85rem;
    color: #fff;
    flex-shrink: 0;
}
.sa-avatar-active { background: #10b981; }
.sa-avatar-pending { background: #f59e0b; }
.sa-avatar-default { background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%); }
.sa-tenant-name { font-weight: 600; color: var(--text); }
.sa-tenant-id { font-size: 0.75rem; color: var(--muted); }

/* ─── PILLS ──────────────────────────────────────────────── */
.pill {
    font-size: 0.62rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}
.pill-green { background: rgba(16,185,129,0.12); color: #10b981; }
.pill-amber { background: rgba(245,158,11,0.12); color: #f59e0b; }
.pill-red { background: rgba(239,68,68,0.12); color: #ef4444; }

/* ─── PAGINATION ─────────────────────────────────────────── */
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
    min-width: 36px; height: 36px;
    padding: 0 10px;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    background: #f5f5f5;
    color: #333;
    text-decoration: none;
    display: flex;
    align-items: center; justify-content: center;
    font-size: 0.8rem;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
}
.sa-pagination-item:hover:not(.active) {
    background: rgba(64,153,255,0.1);
    border-color: #4099ff;
    color: #4099ff;
}
.sa-pagination-item.active {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    border-color: #4099ff;
    color: white;
}
.sa-pagination-ellipsis { color: #999; font-size: 0.8rem; }
.sa-pagination-info { font-size: 0.8rem; color: #999; margin-left: auto; }
.sa-page-btn {
    background: none;
    border: 1px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    padding: 6px 10px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.8rem;
    color: #555;
    transition: all 0.2s;
}
.sa-page-btn:hover:not(:disabled) {
    background: rgba(64,153,255,0.1);
    border-color: #4099ff;
    color: #4099ff;
}
.sa-page-btn:disabled { opacity: 0.4; cursor: default; }
.sa-page-btn svg { width: 16px; height: 16px; }

/* ─── MODAL OVERLAY ──────────────────────────────────────── */
.sa-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    overflow-y: auto;
    padding: 20px;
}
.sa-modal-overlay.active { display: flex; }
.sa-modal {
    background: #fff;
    border-radius: 12px;
    max-width: 700px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: modalIn 0.2s ease;
    max-height: 90vh;
    overflow-y: auto;
}
@keyframes modalIn {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.sa-modal-header {
    padding: 16px 20px;
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-radius: 12px 12px 0 0;
    position: sticky;
    top: 0;
    z-index: 1;
}
.sa-modal-header h5 {
    margin: 0;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sa-modal-close {
    background: none; border: none; color: #fff;
    cursor: pointer; padding: 4px;
    opacity: 0.8; display: flex;
}
.sa-modal-close:hover { opacity: 1; }
.sa-modal-body { padding: 20px; }
.sa-modal-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

/* ─── ALERT ──────────────────────────────────────────────── */
.sa-alert {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 0.9rem;
}
.sa-alert-success {
    background: rgba(16,185,129,0.1);
    border: 1px solid rgba(16,185,129,0.2);
    color: #065f46;
}
.sa-alert-danger {
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.2);
    color: #991b1b;
}
.sa-alert-close {
    margin-left: auto;
    background: none; border: none;
    cursor: pointer; display: flex;
    padding: 2px; opacity: 0.5;
}
.sa-alert-close:hover { opacity: 0.8; }

/* ─── FORM ───────────────────────────────────────────────── */
.sa-form-group { margin-bottom: 16px; }
.sa-form-group label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: #555;
    margin-bottom: 6px;
}
.sa-form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.9rem;
    transition: border-color 0.2s;
    box-sizing: border-box;
}
.sa-form-control:focus {
    outline: none;
    border-color: #4099ff;
    box-shadow: 0 0 0 3px rgba(64,153,255,0.15);
}
.sa-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.sa-input-group { display: flex; align-items: center; }
.sa-input-group-text {
    padding: 10px 12px;
    background: #f0f0f0;
    border: 1px solid var(--border);
    border-right: none;
    border-radius: 8px 0 0 8px;
    font-size: 0.9rem;
    color: #666;
}
.sa-input-group .sa-form-control { border-radius: 0 8px 8px 0; }
.sa-select { appearance: auto; }

/* ─── SPINNER ────────────────────────────────────────────── */
@keyframes spin { to { transform: rotate(360deg); } }

/* ─── RESPONSIVE ─────────────────────────────────────────── */
@media (max-width: 768px) {
    .page-header.card { padding: 1.5rem; }
    .page-header-content h5 { font-size: 1.25rem; }
    .sa-table { font-size: 0.8rem; }
    .sa-table th, .sa-table td { padding: 8px 10px; }
}
@media (max-width: 600px) {
    .sa-form-row { grid-template-columns: 1fr; }
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
                                <h5>
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                                    Subscription Payments
                                </h5>
                                <p>Manage subscription payments and billing</p>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="page-header-actions">
                                <button type="button" class="sa-btn" onclick="showModal('recordPaymentModal')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    Record Payment
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
                        <div class="sa-alert sa-alert-success">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            Payment recorded successfully!
                            <button type="button" class="sa-alert-close" onclick="this.parentElement.remove()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($error_message)): ?>
                        <div class="sa-alert sa-alert-danger">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <?= htmlspecialchars($error_message) ?>
                            <button type="button" class="sa-alert-close" onclick="this.parentElement.remove()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                        <?php endif; ?>


                        <!-- Subscriptions Header -->
                        <div class="sa-shdr" style="margin-bottom: 16px;">
                            <div>
                                <h2>All Subscriptions</h2>
                                <p>Total: <?= $sub_total_items ?> subscriptions</p>
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

                        <!-- Subscriptions Table -->
                        <?php if (empty($subscriptions)): ?>
                        <div class="sa-card">
                            <div class="sa-card-body" style="text-align: center; padding: 40px 20px; color: var(--muted);">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 12px; opacity: 0.4;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                                <div style="font-weight: 600; margin-bottom: 4px;">No Subscriptions Found</div>
                                <div style="font-size: 0.8rem;"><?= !empty($sub_search_query) ? 'Try adjusting your search.' : 'No subscriptions available.' ?></div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="sa-table-wrap">
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Plan</th>
                                        <th>Cycle</th>
                                        <th>Amount</th>
                                        <th>Add-ons</th>
                                        <th>Next Billing</th>
                                        <th>Total Paid</th>
                                        <th>Status</th>
                                        <th style="width: 80px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscriptions as $sub): ?>
                                    <?php 
                                    $symbol = getCurrencySymbol($sub['currency'] ?? 'USD');
                                    $totalWithAddons = floatval($sub['amount']) + floatval($sub['total_addon_cost']);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="sa-td-tenant">
                                                <div class="sa-avatar sa-avatar-<?= $sub['status'] === 'active' ? 'active' : 'pending' ?>"><?= strtoupper(substr($sub['tenant_name'], 0, 1)) ?></div>
                                                <div>
                                                    <div class="sa-tenant-name"><?= htmlspecialchars($sub['tenant_name']) ?></div>
                                                    <div class="sa-tenant-id"><?= htmlspecialchars($sub['tenant_identifier']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($sub['plan_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars(ucfirst($sub['billing_cycle'])) ?></td>
                                        <td><strong><?= $symbol . number_format($sub['amount'], 2) ?></strong></td>
                                        <td style="font-size: 0.8rem;">
                                            <?php if ($sub['branch_addon_cost'] > 0 || $sub['user_addon_cost'] > 0 || $sub['communication_addon_cost'] > 0): ?>
                                                <?php if ($sub['branch_addon_cost'] > 0): ?>
                                                <div>+<?= $symbol . number_format($sub['branch_addon_cost'], 2) ?> branch</div>
                                                <?php endif; ?>
                                                <?php if ($sub['user_addon_cost'] > 0): ?>
                                                <div>+<?= $symbol . number_format($sub['user_addon_cost'], 2) ?> users</div>
                                                <?php endif; ?>
                                                <?php if ($sub['communication_addon_cost'] > 0): ?>
                                                <div>+<?= $symbol . number_format($sub['communication_addon_cost'], 2) ?> comm.</div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                            <span style="color: var(--muted);">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $sub['next_billing_date'] ? date('M d, Y', strtotime($sub['next_billing_date'])) : 'N/A' ?></td>
                                        <td><strong style="color: #2ed8b6;"><?= $symbol . number_format($sub['total_paid'] ?? 0, 2) ?></strong></td>
                                        <td><span class="pill <?= $sub['status'] === 'active' ? 'pill-green' : ($sub['status'] === 'pending' ? 'pill-amber' : 'pill-red') ?>"><?= htmlspecialchars(ucfirst($sub['status'])) ?></span></td>
                                        <td>
                                            <div class="sa-td-actions">
                                                <button type="button" class="sa-btn-icon" title="View Payments" onclick="viewSubscriptionPayments(<?= $sub['id'] ?>)">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
                            <button type="button" class="sa-page-btn" <?= $sub_current_page <= 1 ? 'disabled' : '' ?> onclick="window.location='?sub_page=1<?= $query_string ?>'">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg>
                            </button>
                            <button type="button" class="sa-page-btn" <?= $sub_current_page <= 1 ? 'disabled' : '' ?> onclick="window.location='?sub_page=<?= $sub_current_page - 1 ?><?= $query_string ?>'">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                            </button>
                            <?php if ($start_page > 1): ?>
                            <span class="sa-pagination-ellipsis">...</span>
                            <?php endif; ?>
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?sub_page=<?= $i ?><?= $query_string ?>" class="sa-pagination-item <?= $i === $sub_current_page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($end_page < $sub_total_pages): ?>
                            <span class="sa-pagination-ellipsis">...</span>
                            <?php endif; ?>
                            <button type="button" class="sa-page-btn" <?= $sub_current_page >= $sub_total_pages ? 'disabled' : '' ?> onclick="window.location='?sub_page=<?= $sub_current_page + 1 ?><?= $query_string ?>'">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>
                            <button type="button" class="sa-page-btn" <?= $sub_current_page >= $sub_total_pages ? 'disabled' : '' ?> onclick="window.location='?sub_page=<?= $sub_total_pages ?><?= $query_string ?>'">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg>
                            </button>
                            <span class="sa-pagination-info">Page <?= $sub_current_page ?> of <?= $sub_total_pages ?></span>
                        </div>
                        <?php endif; ?>

                        <!-- Recent Payments Header -->
                        <div class="sa-shdr" style="margin-top: 30px; margin-bottom: 16px;">
                            <div>
                                <h2>Recent Payments</h2>
                                <p>Total: <?= $pay_total_items ?> payments</p>
                            </div>
                        </div>

                        <!-- Payments Filter Bar -->
                        <div class="sa-card" style="margin-bottom: 20px;">
                            <div class="sa-card-body">
                                <form method="GET" action="subscription_payments.php" class="sa-search-filter">
                                    <div class="sa-search-group">
                                        <input type="text" class="sa-search-input" name="payment_search" placeholder="Search payments..." value="<?= htmlspecialchars($pay_search_query) ?>">
                                        <button type="submit" class="sa-btn sa-btn-primary">Search</button>
                                        <?php if (!empty($pay_search_query)): ?>
                                        <a href="subscription_payments.php" class="sa-btn sa-btn-ghost">Clear</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Payments Table -->
                        <?php if (empty($recent_payments)): ?>
                        <div class="sa-card">
                            <div class="sa-card-body" style="text-align: center; padding: 40px 20px; color: var(--muted);">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 12px; opacity: 0.4;"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
                                <div style="font-weight: 600; margin-bottom: 4px;">No Payments Found</div>
                                <div style="font-size: 0.8rem;"><?= !empty($pay_search_query) ? 'Try adjusting your search.' : 'No payments recorded yet.' ?></div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="sa-table-wrap">
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Receipt</th>
                                        <th>Processed By</th>
                                        <th style="width: 80px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_payments as $payment): ?>
                                    <?php $paymentSymbol = getCurrencySymbol($payment['currency'] ?? 'USD'); ?>
                                    <tr>
                                        <td>
                                            <div class="sa-td-tenant">
                                                <div class="sa-avatar sa-avatar-default"><?= strtoupper(substr($payment['tenant_name'] ?? '?', 0, 1)) ?></div>
                                                <div>
                                                    <div class="sa-tenant-name"><?= htmlspecialchars($payment['tenant_name'] ?? 'Deleted') ?></div>
                                                    <div class="sa-tenant-id"><?= htmlspecialchars($payment['tenant_identifier'] ?? '') ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                        <td><strong style="color: #2ed8b6;"><?= $paymentSymbol . number_format($payment['amount'], 2) ?></strong> <span style="color: var(--muted); font-size: 0.75rem;"><?= htmlspecialchars($payment['currency'] ?? 'USD') ?></span></td>
                                        <td><?= htmlspecialchars($payment['payment_method'] ?: 'N/A') ?></td>
                                        <td><?= htmlspecialchars($payment['receipt_number'] ?: 'N/A') ?></td>
                                        <td><?= htmlspecialchars($payment['processed_by_name'] ?: 'System') ?></td>
                                        <td>
                                            <div class="sa-td-actions">
                                                <button type="button" class="sa-btn-icon" title="Download Invoice" onclick="downloadInvoice(<?= $payment['id'] ?>)">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                        <!-- Pagination for Payments -->
                        <?php if ($pay_total_pages > 1): ?>
                        <div class="sa-pagination">
                            <?php 
                            $query_string = !empty($pay_search_query) ? '&payment_search=' . urlencode($pay_search_query) : '';
                            $start_page = max(1, $pay_current_page - 2);
                            $end_page = min($pay_total_pages, $pay_current_page + 2);
                            ?>
                            <button type="button" class="sa-page-btn" <?= $pay_current_page <= 1 ? 'disabled' : '' ?> onclick="window.location='?payment_page=1<?= $query_string ?>'">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg>
                            </button>
                            <button type="button" class="sa-page-btn" <?= $pay_current_page <= 1 ? 'disabled' : '' ?> onclick="window.location='?payment_page=<?= $pay_current_page - 1 ?><?= $query_string ?>'">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                            </button>
                            <?php if ($start_page > 1): ?>
                            <span class="sa-pagination-ellipsis">...</span>
                            <?php endif; ?>
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?payment_page=<?= $i ?><?= $query_string ?>" class="sa-pagination-item <?= $i === $pay_current_page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($end_page < $pay_total_pages): ?>
                            <span class="sa-pagination-ellipsis">...</span>
                            <?php endif; ?>
                            <button type="button" class="sa-page-btn" <?= $pay_current_page >= $pay_total_pages ? 'disabled' : '' ?> onclick="window.location='?payment_page=<?= $pay_current_page + 1 ?><?= $query_string ?>'">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>
                            <button type="button" class="sa-page-btn" <?= $pay_current_page >= $pay_total_pages ? 'disabled' : '' ?> onclick="window.location='?payment_page=<?= $pay_total_pages ?><?= $query_string ?>'">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg>
                            </button>
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
<div class="sa-modal-overlay" id="recordPaymentModal">
    <div class="sa-modal" style="max-width: 750px;">
        <div class="sa-modal-header">
            <h5>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                Record Subscription Payment
            </h5>
            <button type="button" class="sa-modal-close" onclick="closeModal('recordPaymentModal')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST">
            <div class="sa-modal-body">
                <input type="hidden" name="action" value="record_payment">

                <div class="sa-form-row">
                    <div class="sa-form-group">
                        <label for="subscription_id">Subscription *</label>
                        <select class="sa-form-control" id="subscription_id" name="subscription_id" required>
                            <option value="">Select Subscription</option>
                             <?php foreach ($subscriptions as $sub): ?>
                             <?php 
                             $subSymbol = getCurrencySymbol($sub['currency'] ?? 'USD');
                             $subTotal = floatval($sub['amount']) + floatval($sub['total_addon_cost']);
                             $addonsText = '';
                             if ($sub['branch_addon_cost'] > 0 || $sub['user_addon_cost'] > 0 || $sub['communication_addon_cost'] > 0) {
                                 $addons = [];
                                 if ($sub['branch_addon_cost'] > 0) {
                                     $addons[] = $subSymbol . number_format($sub['branch_addon_cost'], 2) . ' branch';
                                 }
                                 if ($sub['user_addon_cost'] > 0) {
                                     $addons[] = $subSymbol . number_format($sub['user_addon_cost'], 2) . ' users';
                                 }
                                 if ($sub['communication_addon_cost'] > 0) {
                                     $addons[] = $subSymbol . number_format($sub['communication_addon_cost'], 2) . ' communication';
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
                    <div class="sa-form-group">
                        <label for="amount">Amount *</label>
                        <div class="sa-input-group">
                            <span class="sa-input-group-text" id="amountSymbol">$</span>
                            <input type="number" class="sa-form-control" id="amount" name="amount" step="0.01" min="0" required>
                        </div>
                    </div>
                </div>

                <div class="sa-form-row">
                    <div class="sa-form-group">
                        <label for="currency">Currency *</label>
                        <select class="sa-form-control" id="currency" name="currency" required>
                            <option value="USD">USD ($)</option>
                            <option value="AFN">AFN (؋)</option>
                            <option value="EUR">EUR (€)</option>
                            <option value="GBP">GBP (£)</option>
                            <option value="AED">AED (د.إ)</option>
                            <option value="INR">INR (₹)</option>
                            <option value="PKR">PKR (₨)</option>
                        </select>
                    </div>
                    <div class="sa-form-group">
                        <label for="payment_date">Payment Date *</label>
                        <input type="date" class="sa-form-control" id="payment_date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="sa-form-row">
                    <div class="sa-form-group">
                        <label for="payment_method">Payment Method</label>
                        <select class="sa-form-control" id="payment_method" name="payment_method">
                            <option value="">Select Method</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="PayPal">PayPal</option>
                            <option value="Cash">Cash</option>
                            <option value="Check">Check</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="sa-form-group">
                        <label for="receipt_number">Receipt Number</label>
                        <input type="text" class="sa-form-control" id="receipt_number" name="receipt_number" placeholder="Enter receipt number">
                    </div>
                </div>

                <div class="sa-form-group">
                    <label for="transaction_id">Transaction ID</label>
                    <input type="text" class="sa-form-control" id="transaction_id" name="transaction_id" placeholder="Enter transaction ID">
                </div>

                <div class="sa-form-group">
                    <label for="notes">Notes</label>
                    <textarea class="sa-form-control" id="notes" name="notes" rows="3" placeholder="Additional notes"></textarea>
                </div>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-btn sa-btn-ghost" onclick="closeModal('recordPaymentModal')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    Cancel
                </button>
                <button type="submit" class="sa-btn sa-btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Record Payment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Subscription Payments Modal -->
<div class="sa-modal-overlay" id="subscriptionPaymentsModal">
    <div class="sa-modal" style="max-width: 900px;">
        <div class="sa-modal-header">
            <h5>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                Subscription Payment History
            </h5>
            <button type="button" class="sa-modal-close" onclick="closeModal('subscriptionPaymentsModal')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="sa-modal-body">
            <div id="subscriptionPaymentsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>
<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
const currencySymbols = {
    'USD': '$', 'EUR': '€', 'GBP': '£', 'JPY': '¥',
    'AFN': '؋', 'AED': 'د.إ', 'INR': '₹', 'PKR': '₨',
};

function showModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
}

document.querySelectorAll('.sa-modal-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.sa-modal-overlay.active').forEach(function(el) {
            el.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    var subSelect = document.getElementById('subscription_id');
    if (subSelect) {
        subSelect.addEventListener('change', function() {
            var option = this.options[this.selectedIndex];
            var currency = option.getAttribute('data-currency') || 'USD';
            var amount = option.getAttribute('data-amount') || '';
            var currencyEl = document.getElementById('currency');
            var amountEl = document.getElementById('amount');
            var symbolEl = document.getElementById('amountSymbol');
            if (currencyEl) currencyEl.value = currency;
            if (amountEl) amountEl.value = amount;
            if (symbolEl) symbolEl.textContent = currencySymbols[currency] || currency;
        });
    }
    var currencyEl = document.getElementById('currency');
    if (currencyEl) {
        currencyEl.addEventListener('change', function() {
            var symbolEl = document.getElementById('amountSymbol');
            if (symbolEl) symbolEl.textContent = currencySymbols[this.value] || this.value;
        });
    }
});

function generateInvoicePreview() {
    var subscriptionId = document.getElementById('subscription_id') ? document.getElementById('subscription_id').value : '';
    var amount = document.getElementById('amount') ? document.getElementById('amount').value : '';
    var currency = document.getElementById('currency') ? document.getElementById('currency').value : '';
    var paymentDate = document.getElementById('payment_date') ? document.getElementById('payment_date').value : '';
    var paymentMethod = document.getElementById('payment_method') ? document.getElementById('payment_method').value : '';
    var transactionId = document.getElementById('transaction_id') ? document.getElementById('transaction_id').value : '';
    var receiptNumber = document.getElementById('receipt_number') ? document.getElementById('receipt_number').value : '';
    var notes = document.getElementById('notes') ? document.getElementById('notes').value : '';

    if (!subscriptionId || !amount) {
        alert('Please select a subscription and enter an amount');
        return;
    }

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
    window.open('generate_invoice_pdf.php?payment_id=' + paymentId, 'invoice');
}

function viewSubscriptionPayments(subscriptionId) {
    var content = document.getElementById('subscriptionPaymentsContent');
    if (!content) return;
    content.innerHTML = '<div style="text-align: center; padding: 40px;"><div style="width: 3rem; height: 3rem; border: 0.25em solid #4099ff; border-right-color: transparent; border-radius: 50%; animation: spin 0.75s linear infinite; display: inline-block;"></div></div>';
    
    fetch('get_subscription_payments.php?subscription_id=' + subscriptionId)
        .then(function(response) { return response.text(); })
        .then(function(html) { content.innerHTML = html; })
        .catch(function() { content.innerHTML = '<div class="sa-alert sa-alert-danger">Error loading payment history.</div>'; });
    
    showModal('subscriptionPaymentsModal');
}
</script>

<!-- Include Super Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>
</body>
</html>
