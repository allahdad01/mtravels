<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once('../includes/db.php');

// Check if transaction ID is provided
if (!isset($_GET['transaction_id']) || empty($_GET['transaction_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Transaction ID is required'
    ]);
    exit;
}

$transaction_id = intval($_GET['transaction_id']);

try {
    // Query to get transaction details
    $stmt = $pdo->prepare("
        SELECT
            mat.id,
            mat.main_account_id,
            mat.type,
            mat.amount,
            mat.currency,
            mat.description,
            mat.transaction_of,
            mat.reference_id,
            mat.balance,
            mat.created_at as transaction_date,
            mat.receipt,
            mat.exchange_rate
        FROM
            main_account_transactions mat
        WHERE
        mat.id = ? AND mat.transaction_of = 'ticket_refund' AND mat.tenant_id = ?
    ");
    
    $stmt->execute([$transaction_id, $tenant_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode([
            'success' => false,
            'message' => 'Transaction not found'
        ]);
        exit;
    }
    
    // Format the date for the response
    if (isset($transaction['transaction_date'])) {
        // Keep the original format for proper parsing in JavaScript
        $transaction['payment_date'] = $transaction['transaction_date'];
    }
    
    echo json_encode([
        'success' => true,
        'transaction' => $transaction
    ]);
    
} catch (PDOException $e) {
    error_log("Database Error in get_refund_transaction_details.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 