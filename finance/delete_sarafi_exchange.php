<?php
require_once '../includes/conn.php';
require_once 'includes/db_security.php';
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];


// Initialize response array
$response = [
    'success' => false,
    'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaction_id'])) {
    $transaction_id = intval($_POST['transaction_id']);
    
    try {
        $conn->begin_transaction();
        
        // Get exchange transaction details
        $stmt = $conn->prepare("
            SELECT st.*, et.from_amount, et.from_currency, et.to_amount, et.to_currency, et.rate 
            FROM sarafi_transactions st
            JOIN exchange_transactions et ON st.id = et.transaction_id
            WHERE st.id = ? AND st.type = 'exchange' AND st.tenant_id = ?
        ");
        $stmt->bind_param("ii", $transaction_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $transaction = $result->fetch_assoc();
        
        if (!$transaction) {
            throw new Exception("Exchange transaction not found");
        }
        
        // Reverse the exchange by:
        // 1. Add back the original amount to source currency wallet
        $stmt = $conn->prepare("
            UPDATE customer_wallets 
            SET balance = balance + ? 
            WHERE customer_id = ? AND currency = ? AND tenant_id = ?
        ");
        $stmt->bind_param("disi", $transaction['from_amount'], $transaction['customer_id'], $transaction['from_currency'], $tenant_id);
        $stmt->execute();
        
        // 2. Deduct the exchanged amount from destination currency wallet
        $stmt = $conn->prepare("
            UPDATE customer_wallets 
            SET balance = balance - ? 
            WHERE customer_id = ? AND currency = ? AND tenant_id = ?
        ");
        $stmt->bind_param("disi", $transaction['to_amount'], $transaction['customer_id'], $transaction['to_currency'], $tenant_id);
        $stmt->execute();
       
        // 0. Delete child exchange transaction first
        $stmt = $conn->prepare("DELETE FROM exchange_transactions WHERE transaction_id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $transaction_id, $tenant_id);
        $stmt->execute();
        // 5. Mark original transaction as reversed
        $stmt = $conn->prepare("
            DELETE FROM sarafi_transactions 
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->bind_param("ii", $transaction_id, $tenant_id);
        $stmt->execute();
        
       
        
        $conn->commit();
        $response['success'] = true;
        $response['message'] = "Exchange transaction successfully deleted";
        
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = "Error deleting exchange: " . $e->getMessage();
    }
} else {
    $response['message'] = "Invalid request";
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response); 