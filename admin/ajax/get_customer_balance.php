<?php
require_once '../../includes/conn.php';
require_once '../../includes/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Validate customer_id
if (!isset($_GET['customer_id']) || !is_numeric($_GET['customer_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid customer ID']);
    exit();
}

$customer_id = intval($_GET['customer_id']);

try {
    // Get customer balances for all currencies
    $stmt = $conn->prepare("SELECT currency, balance FROM customer_wallets WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $balances = [];
    while ($row = $result->fetch_assoc()) {
        $balances[$row['currency']] = $row['balance'];
    }
    
    echo json_encode($balances);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    error_log("Error in get_customer_balance.php: " . $e->getMessage());
}
?> 