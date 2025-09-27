<?php
// Include necessary files
require_once('../includes/db.php');
require_once('../includes/conn.php');
require_once('security.php');

// Enforce authentication
enforce_auth();

// Set header for JSON response
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Check if refund ID is provided
if (!isset($_GET['refund_id'])) {
    echo json_encode(['success' => false, 'message' => 'Refund ID is required']);
    exit;
}

$refundId = intval($_GET['refund_id']);

try {
    // Get transactions with account information
    $query = "
        SELECT t.*, m.name as account_name
        FROM main_account_transactions t
        LEFT JOIN main_account m ON t.main_account_id = m.id
        WHERE t.transaction_of = 'visa_refund'
        AND t.reference_id = ?
        ORDER BY t.created_at DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$refundId]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'transactions' => $transactions
    ]);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching transactions'
    ]);
}