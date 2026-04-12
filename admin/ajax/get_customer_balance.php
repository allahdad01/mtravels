<?php
require_once '../../includes/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    // Log unauthorized access attempt
    header('Location: ../login.php');
    exit();
}

// Validate customer_id
if (!isset($_GET['customer_id']) || !is_numeric($_GET['customer_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid customer ID']);
    exit();
}

$customer_id = intval($_GET['customer_id']);
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

try {
    // Get customer balances for all currencies
    $stmt = $pdo->prepare("SELECT currency, balance FROM customer_wallets WHERE customer_id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$customer_id, $tenant_id, $branch_id]);
    $result = $stmt;
    
    $balances = [];
    while ($row = $result->fetch()) {
        $balances[$row['currency']] = $row['balance'];
    }
    
    echo json_encode($balances);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?> 