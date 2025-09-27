<?php
session_start();
require_once '../includes/conn.php';

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

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: manage_subscriptions.php?error=invalid_csrf');
    exit();
}

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to create_subscription.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

$tenant_id = $_POST['tenant_id'] ?? '';
$plan_id = $_POST['plan_id'] ?? '';
$status = $_POST['status'] ?? 'active';
$billing_cycle = $_POST['billing_cycle'] ?? 'monthly';
$amount = $_POST['amount'] ?? 0;
$currency = $_POST['currency'] ?? 'USD';
$payment_method = $_POST['payment_method'] ?? '';
$start_date = $_POST['start_date'] ?? date('Y-m-d');
$next_billing_date = $_POST['next_billing_date'] ?? '';

$errors = [];

// Validate inputs
if (empty($tenant_id) || !is_numeric($tenant_id)) {
    $errors[] = "Invalid tenant selected.";
}

if (empty($plan_id)) {
    $errors[] = "Invalid plan selected.";
}

if (!is_numeric($amount) || $amount < 0) {
    $errors[] = "Amount must be a non-negative number.";
}

if (empty($start_date) || !strtotime($start_date)) {
    $errors[] = "Invalid start date.";
}

if (empty($amount) || !is_numeric($amount) || $amount < 0) {
    $errors[] = "Amount must be a non-negative number.";
}

if (empty($start_date)) {
    $errors[] = "Start date is required.";
}

// Calculate end date based on billing cycle
$end_date = date('Y-m-d', strtotime("+1 month", strtotime($start_date)));
if ($billing_cycle === 'quarterly') {
    $end_date = date('Y-m-d', strtotime("+3 months", strtotime($start_date)));
} elseif ($billing_cycle === 'yearly') {
    $end_date = date('Y-m-d', strtotime("+1 year", strtotime($start_date)));
}

// If next billing date is not provided, set it to end date
if (empty($next_billing_date)) {
    $next_billing_date = $end_date;
}

// Verify tenant exists
if (empty($errors)) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tenants WHERE id = ? AND status != 'deleted'");
    $stmt->bind_param('i', $tenant_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()['count'] == 0) {
        $errors[] = "Invalid or inactive tenant selected.";
    }
    $stmt->close();
}

// Verify plan exists
if (empty($errors)) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM plans WHERE name = ? AND status = 'active'");
    $stmt->bind_param('s', $plan_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()['count'] == 0) {
        $errors[] = "Invalid or inactive plan selected.";
    }
    $stmt->close();
}

// Check if tenant already has an active subscription
if (empty($errors)) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tenant_subscriptions WHERE tenant_id = ? AND status = 'active'");
    $stmt->bind_param('i', $tenant_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()['count'] > 0) {
        $errors[] = "Tenant already has an active subscription. Please edit the existing subscription instead.";
    }
    $stmt->close();
}

if (empty($errors)) {
    // Create subscription
    $stmt = $conn->prepare("INSERT INTO tenant_subscriptions 
                          (tenant_id, plan_id, status, billing_cycle, start_date, end_date, 
                           amount, currency, payment_method, next_billing_date, created_at, updated_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param('isssssdsss', $tenant_id, $plan_id, $status, $billing_cycle, $start_date, 
                      $end_date, $amount, $currency, $payment_method, $next_billing_date);
    $stmt->execute();
    $stmt->close();

    // Log action
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) 
                            VALUES (?, 'create_subscription', 'subscription', ?, ?, ?, NOW())");
    $details = json_encode([
        'tenant_id' => $tenant_id,
        'plan_id' => $plan_id,
        'status' => $status,
        'billing_cycle' => $billing_cycle,
        'amount' => $amount,
        'currency' => $currency
    ]);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param('iiss', $user_id, $tenant_id, $details, $ip_address);
    $stmt->execute();
    $stmt->close();

    header('Location: manage_subscriptions.php?success=subscription_created');
    exit();
} else {
    header('Location: manage_subscriptions.php?error=' . urlencode(implode(', ', $errors)));
    exit();
}
?>