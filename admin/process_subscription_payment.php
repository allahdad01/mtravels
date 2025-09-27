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

// Check if tenant_id is set (user must be associated with a tenant)
if (!isset($_SESSION['tenant_id'])) {
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to process subscription payment: no tenant_id - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("CSRF token validation failed.");
}

require_once '../config.php';
require_once '../includes/conn.php';
require_once '../includes/db.php';

// Check if $pdo is available
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

if ($subscription_id <= 0 || $amount <= 0) {
    die("Invalid payment data.");
}

// Fetch subscription details to verify
try {
    $stmt = $pdo->prepare("SELECT ts.*, p.name as plan_name FROM tenant_subscriptions ts LEFT JOIN plans p ON ts.plan_id = p.id WHERE ts.id = ? AND ts.tenant_id = ?");
    $stmt->execute([$subscription_id, $tenant_id]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$subscription) {
        die("Subscription not found.");
    }
} catch (PDOException $e) {
    error_log("Error fetching subscription: " . $e->getMessage());
    die("Error processing payment.");
}

// Convert amount to AFN if needed (assuming currency is USD, convert to AFN)
// For simplicity, assume amount is in AFN, or add conversion logic
// Here, assume $amount is in the currency specified, but Hesabpay uses AFN
// You may need to convert USD to AFN using exchange rate
$amount_afn = $amount; // Placeholder, add conversion if needed

// Prepare Hesabpay API call
$api_url = 'https://api.hesab.com/api/v1/payment/create-session';
$data = [
    'items' => [
        [
            'id' => $subscription_id,
            'name' => $subscription['plan_name'] ?? 'Subscription',
            'price' => $amount_afn
        ]
    ],
    'redirect_success_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/admin/subscription_payments.php?payment=success&subscription_id=' . $subscription_id,
    'redirect_failure_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/admin/subscription_payments.php?payment=failed&subscription_id=' . $subscription_id
];

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: API-KEY ' . HESABPAY_API_KEY,
    'accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    error_log("Hesabpay API error: " . $response);
    die("Payment initiation failed. Please try again.");
}

$response_data = json_decode($response, true);
if (!$response_data || !isset($response_data['url'])) {
    error_log("Invalid Hesabpay response: " . $response);
    die("Payment initiation failed.");
}

// Redirect to Hesabpay payment page
header('Location: ' . $response_data['url']);
exit();
?>