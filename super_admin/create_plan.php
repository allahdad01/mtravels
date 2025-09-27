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
    header('Location: manage_plans.php?error=invalid_csrf');
    exit();
}

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to create_plan.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

$name = $_POST['name'] ?? '';
$description = $_POST['description'] ?? '';
$features = $_POST['features'] ?? '';
$price = $_POST['price'] ?? 0;
$max_users = $_POST['max_users'] ?? 0;
$trial_days = $_POST['trial_days'] ?? 0;
$errors = [];

// Validate input
if (empty($name) || empty($description) || empty($features)) {
    $errors[] = "Name, description, and features are required.";
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
if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
    $errors[] = "Plan name can only contain letters, numbers, and underscores.";
}
// Validate JSON features
if (!json_decode($features, true) || json_last_error() !== JSON_ERROR_NONE) {
    $errors[] = "Features must be a valid JSON array.";
}

// Check if plan name exists
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM plans WHERE name = ?");
$stmt->bind_param('s', $name);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()['count'] > 0) {
    $errors[] = "Plan name already exists.";
}
$stmt->close();

if (empty($errors)) {
    // Insert new plan
    $stmt = $conn->prepare("INSERT INTO plans (name, description, features, price, max_users, trial_days, status, created_at, updated_at) 
                            VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())");
    $stmt->bind_param('sssdii', $name, $description, $features, $price, $max_users, $trial_days);
    $stmt->execute();
    $stmt->close();

    // Log action
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) 
                            VALUES (?, 'create_plan', 'plan', ?, ?, ?, NOW())");
    $details = json_encode(['name' => $name, 'description' => $description, 'price' => $price, 'max_users' => $max_users, 'trial_days' => $trial_days]);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param('iss', $user_id, $name, $details, $ip_address);
    $stmt->execute();
    $stmt->close();

    header('Location: manage_plans.php?success=plan_created');
} else {
    header('Location: manage_plans.php?error=' . urlencode(implode(', ', $errors)));
}
exit();
?>