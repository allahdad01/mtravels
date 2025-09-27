<?php
// Include necessary files
require_once('../includes/db.php');
require_once('../includes/conn.php');
require_once('security.php');

// Enforce authentication
enforce_auth();

// Set header for JSON response
header('Content-Type: application/json');
$tenant_id = $_SESSION['tenant_id'];

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
        WHERE t.transaction_of = 'umrah_refund'
        AND t.reference_id = ? AND t.tenant_id = ?
        ORDER BY t.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$refundId, $tenant_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions
    ]);
} catch (PDOException $e) {
    error_log("Error fetching umrah refund transactions: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching transactions'
    ]);
} 