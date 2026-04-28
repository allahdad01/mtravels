<?php
session_start();
require_once '../includes/db.php';

// Set secure headers
header('Content-Type: application/json');
header("X-Content-Type-Options: nosniff");

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get plan name from query parameter
$plan_name = $_GET['name'] ?? '';

if (empty($plan_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Plan name is required']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, name, price, currency, description, max_users, max_branches, trial_days, status, features FROM plans WHERE name = ?");
    $stmt->execute([$plan_name]);
    $plan = $stmt->fetch();

    if ($plan) {
        echo json_encode(['success' => true, 'plan' => $plan]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Plan not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
