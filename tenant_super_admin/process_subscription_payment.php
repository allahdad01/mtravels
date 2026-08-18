<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/CsrfProtection.php';

// Validate CSRF token for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? null)) {
        die("Security token validation failed. Please try again.");
    }
}

// Check DB connection
if (!isset($pdo) || !$pdo) {
    die("Database connection failed. Please contact administrator.");
}

// Get tenant_id from session
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    header('Location: dashboard.php');
    exit();
}

// Get POST data
$subscription_id = intval($_POST['subscription_id'] ?? 0);
$amount = floatval($_POST['amount'] ?? 0);
$currency = $_POST['currency'] ?? 'USD';
$addon_cost = floatval($_POST['addon_cost'] ?? 0);
$clean_start = isset($_POST['clean_start']) && $_POST['clean_start'] === '1';

if ($subscription_id <= 0 || $amount <= 0) {
    die("Invalid payment data.");
}

// Fetch subscription details
try {
    $stmt = $pdo->prepare("SELECT ts.*, p.name AS plan_name FROM tenant_subscriptions ts LEFT JOIN plans p ON ts.plan_id = p.id WHERE ts.id = ? AND ts.tenant_id = ?");
    $stmt->execute([$subscription_id, $tenant_id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        die("Subscription not found.");
    }
} catch (PDOException $e) {
    die("Error processing payment.");
}

// Amount in AFN (or same currency for simplicity)
$amount_afn = $amount;

// Build redirect URLs properly
// Using tenant_super_admin path - the correct subscription payments page
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . '/tenant_super_admin/subscription_payments.php';
$success_params = http_build_query([
    'payment' => 'success',
    'subscription_id' => $subscription_id,
    'clean_start' => $clean_start ? '1' : '0'
]);
$failure_params = http_build_query([
    'payment' => 'failed',
    'subscription_id' => $subscription_id
]);
$redirect_success_url = $base_url . '?' . $success_params;
$redirect_failure_url = $base_url . '?' . $failure_params;

// Prepare HesabPay API request
$api_url = HESABPAY_BASE_URL . '/payment/create-session';
$items = [
    [
        'id' => 'sub_' . $subscription_id,
        'name' => $subscription['plan_name'] ?? 'Subscription',
        'price' => $amount_afn - $addon_cost
    ]
];

// Add addon cost as separate item if present
if ($addon_cost > 0) {
    $items[] = [
        'id' => 'addon_' . $subscription_id,
        'name' => 'Add-ons',
        'price' => $addon_cost
    ];
}

$request_payload = [
    'items' => $items,
    'redirect_success_url' => $redirect_success_url,
    'redirect_failure_url' => $redirect_failure_url
];


// Initialize cURL
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: API-KEY ' . HESABPAY_API_KEY,
    'accept: application/json',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Check response
if ($http_code !== 200) {
    die("Payment initiation failed. Please try again.");
}

$response_data = json_decode($response, true);
if (!$response_data || !isset($response_data['url'])) {
    die("Payment initiation failed.");
}

// Store session_id for callback tracking
$session_id = $response_data['session_id'] ?? null;
if ($session_id) {
    try {
        $stmt = $pdo->prepare("INSERT INTO payment_sessions (session_id, subscription_id, tenant_id, amount, currency, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW()) ON DUPLICATE KEY UPDATE status='pending'");
        $stmt->execute([$session_id, $subscription_id, $tenant_id, $amount_afn, $currency]);
    } catch (PDOException $e) {
        error_log("Error storing session: " . $e->getMessage());
    }
}

// Redirect user to HesabPay payment page
header('Location: ' . $response_data['url']);
exit();
?>
