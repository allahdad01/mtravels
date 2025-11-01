<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once('../includes/db.php');


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in'
    ]);
    exit;
}

// Check if transaction ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Transaction ID is required'
    ]);
    exit;
}

$transaction_id = intval($_GET['id']);

try {
    // Fetch transaction details
    $stmt = $pdo->prepare("
        SELECT t.id, t.reference_id, t.amount, t.type, t.description,
               t.created_at as transaction_date, t.balance, t.main_account_id,
               t.currency, t.exchange_rate
        FROM main_account_transactions t
        LEFT JOIN main_account m ON t.main_account_id = m.id
        LEFT JOIN date_change_tickets dct ON t.reference_id = dct.id
        WHERE t.id = ? AND t.transaction_of = 'date_change' AND t.tenant_id = ?
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
    
    // Format transaction data for the response
    $formattedTransaction = [
        'id' => $transaction['id'],
        'reference_id' => $transaction['reference_id'],
        'amount' => $transaction['amount'],
        'type' => $transaction['type'],
        'description' => $transaction['description'],
        'transaction_date' => $transaction['transaction_date'],
        'balance' => $transaction['balance'],
        'main_account_id' => $transaction['main_account_id'],
        'currency' => $transaction['currency'],
        'exchange_rate' => $transaction['exchange_rate']
    ];
    
    // Return success response with transaction data
    echo json_encode([
        'success' => true,
        'transaction' => $formattedTransaction
    ]);
    
} catch (PDOException $e) {
    error_log("Error in get_date_change_transaction.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching transaction: ' . $e->getMessage()
    ]);
}
?> 