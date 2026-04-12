<?php
session_start();
require_once '../includes/db.php';

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
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
    header('Location: manage_plans.php?error=invalid_csrf');
    exit();
}

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

$original_name = $_POST['original_name'] ?? '';
$name = $_POST['name'] ?? '';
$description = $_POST['description'] ?? '';
$features = $_POST['features'] ?? '';
$price = $_POST['price'] ?? 0;
$currency = $_POST['currency'] ?? 'USD';
$max_users = $_POST['max_users'] ?? 0;
$trial_days = $_POST['trial_days'] ?? 0;
$status = $_POST['status'] ?? 'active';
$errors = [];

// Validate input
if (empty($original_name) || empty($description) || empty($features)) {
    $errors[] = "All required fields must be filled.";
}

// Validate price
if (!is_numeric($price) || $price < 0) {
    $errors[] = "Price must be a non-negative number.";
}

// Validate max_users
if (!is_numeric($max_users) || $max_users < 0) {
    $errors[] = "Max users must be a non-negative number.";
}

// Validate trial_days
if (!is_numeric($trial_days) || $trial_days < 0) {
    $errors[] = "Trial days must be a non-negative number.";
}

// Validate currency
$allowed_currencies = ['USD', 'AFN', 'EUR', 'GBP', 'INR', 'JPY', 'CNY', 'AUD', 'CAD', 'CHF', 'SEK', 'NZD'];
if (!in_array($currency, $allowed_currencies)) {
    $errors[] = "Invalid currency selected.";
}

// Validate status
if (!in_array($status, ['active', 'inactive'])) {
    $errors[] = "Invalid status.";
}

// Validate JSON features
if (!json_decode($features, true) || json_last_error() !== JSON_ERROR_NONE) {
    $errors[] = "Features must be a valid JSON array.";
}

// Check if plan exists
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM plans WHERE name = ?");
$stmt->execute([$original_name]);
if ($stmt->fetch()['count'] == 0) {
    $errors[] = "Plan not found.";
}

if (empty($errors)) {
    try {
        // Get plan ID first
        $stmt = $pdo->prepare("SELECT id FROM plans WHERE name = ?");
        $stmt->execute([$original_name]);
        $plan = $stmt->fetch();
        
        if (!$plan) {
            throw new Exception("Plan not found");
        }
        
        $plan_id = $plan['id'];

        // Update plan
        $stmt = $pdo->prepare("UPDATE plans SET description = ?, features = ?, price = ?, currency = ?, max_users = ?, trial_days = ?, status = ?, updated_at = NOW() WHERE name = ?");
        $stmt->execute([$description, $features, $price, $currency, $max_users, $trial_days, $status, $original_name]);

        // Log action
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) 
                                VALUES (?, 'update_plan', 'plan', ?, ?, ?, NOW())");
        $details = json_encode(['name' => $original_name, 'description' => $description, 'price' => $price, 'currency' => $currency, 'max_users' => $max_users, 'trial_days' => $trial_days, 'status' => $status]);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt->execute([$user_id, $plan_id, $details, $ip_address]);

        header('Location: manage_plans.php?success=plan_updated');
    } catch (Exception $e) {
        header('Location: manage_plans.php?error=database_error');
    }
} else {
    header('Location: manage_plans.php?error=' . urlencode(implode(', ', $errors)));
}
exit();
