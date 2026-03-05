<?php
require_once '../includes/db.php';
require_once 'includes/db_security.php';
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Initialize response array
$response = [
    'success' => false,
    'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaction_id'])) {
    $transaction_id = intval($_POST['transaction_id']);
    
    try {
        $pdo->beginTransaction();
        
        // Get exchange transaction details
        $stmt = $pdo->prepare("
            SELECT st.*, et.from_amount, et.from_currency, et.to_amount, et.to_currency, et.rate 
            FROM sarafi_transactions st
            JOIN exchange_transactions et ON st.id = et.transaction_id
            WHERE st.id = ? AND st.type = 'exchange' AND st.tenant_id = ? AND st.branch_id = ?
        ");
        $stmt->execute([$transaction_id, $tenant_id, $branch_id]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            throw new Exception("Exchange transaction not found");
        }
        
        // Reverse the exchange by:
        // 1. Add back the original amount to source currency wallet
        $stmt = $pdo->prepare("
            UPDATE customer_wallets 
            SET balance = balance + ? 
            WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?
        ");
        $stmt->execute([$transaction['from_amount'], $transaction['customer_id'], $transaction['from_currency'], $tenant_id, $branch_id]);
        
        // 2. Deduct the exchanged amount from destination currency wallet
        $stmt = $pdo->prepare("
            UPDATE customer_wallets 
            SET balance = balance - ? 
            WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?
        ");
        $stmt->execute([$transaction['to_amount'], $transaction['customer_id'], $transaction['to_currency'], $tenant_id, $branch_id]);
       
        // 0. Delete child exchange transaction first
        $stmt = $pdo->prepare("DELETE FROM exchange_transactions WHERE transaction_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$transaction_id, $tenant_id, $branch_id]);
        
        // 5. Mark original transaction as reversed
        $stmt = $pdo->prepare("
            DELETE FROM sarafi_transactions 
            WHERE id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $stmt->execute([$transaction_id, $tenant_id, $branch_id]);
        
        $pdo->commit();
        $response['success'] = true;
        $response['message'] = "Exchange transaction successfully deleted";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $response['message'] = "Error deleting exchange: " . $e->getMessage();
    }
} else {
    $response['message'] = "Invalid request";
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response); 