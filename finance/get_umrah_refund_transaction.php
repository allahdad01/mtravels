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

// Check if transaction ID is provided
if (!isset($_GET['transaction_id'])) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID is required']);
    exit;
}

$transactionId = intval($_GET['transaction_id']);

try {
    // Get transaction details with account information
    $query = "
        SELECT t.*, m.name as account_name
        FROM main_account_transactions t
        LEFT JOIN main_account m ON t.main_account_id = m.id
        WHERE t.id = ? AND t.tenant_id = ?
        AND t.transaction_of = 'umrah_refund'
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$transactionId, $tenant_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($transaction) {
        echo json_encode([
            'success' => true,
            'transaction' => $transaction
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Transaction not found'
        ]);
    }
} catch (PDOException $e) {
    error_log("Error fetching umrah refund transaction: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching transaction details'
    ]);
} 