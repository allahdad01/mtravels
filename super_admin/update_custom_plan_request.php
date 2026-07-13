<?php
session_start();
require_once '../includes/db.php';

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset(); session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: manage_custom_plan_requests.php?error=invalid_csrf');
    exit();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

$request_id = intval($_POST['request_id'] ?? 0);
$status = $_POST['status'] ?? '';
$negotiated_price = $_POST['negotiated_price'] ?? null;
$currency = $_POST['currency'] ?? 'AFN';
$admin_notes = trim($_POST['admin_notes'] ?? '');

$allowed_statuses = ['pending', 'contacted', 'negotiating', 'approved', 'rejected'];
if (!in_array($status, $allowed_statuses)) {
    header('Location: view_custom_plan_request.php?id=' . $request_id . '&error=invalid_status');
    exit();
}

if ($negotiated_price !== '' && $negotiated_price !== null) {
    if (!is_numeric($negotiated_price) || floatval($negotiated_price) < 0) {
        header('Location: view_custom_plan_request.php?id=' . $request_id . '&error=invalid_price');
        exit();
    }
    $negotiated_price = floatval($negotiated_price);
} else {
    $negotiated_price = null;
}

try {
    $stmt = $pdo->prepare("UPDATE custom_plan_requests SET status = ?, negotiated_price = ?, currency = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $negotiated_price, $currency, $admin_notes, $request_id]);

    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES (?, 'update_custom_plan_request', 'custom_plan_request', ?, ?, ?, NOW())");
    $details = json_encode(['request_id' => $request_id, 'status' => $status, 'negotiated_price' => $negotiated_price, 'currency' => $currency]);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->execute([$user_id, $request_id, $details, $ip_address]);

    header('Location: view_custom_plan_request.php?id=' . $request_id . '&success=Request+updated+successfully');
} catch (Exception $e) {
    error_log("Update Custom Plan Request Error: " . $e->getMessage());
    header('Location: view_custom_plan_request.php?id=' . $request_id . '&error=database_error');
}
exit();
