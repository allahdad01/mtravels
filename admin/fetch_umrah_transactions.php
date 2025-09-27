<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

// Connect using PDO
try {
    require_once '../includes/db.php';
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Connection failed: ' . $e->getMessage()]);
    exit();
}

// Check if we're fetching a single transaction or all transactions
if (isset($_GET['transaction_id'])) {
    // Fetch a single transaction
    $transaction_id = intval($_GET['transaction_id']);
    
    try {
        // Prepare a query to fetch the specific transaction
        $stmt = $pdo->prepare("SELECT id, umrah_booking_id, transaction_type, transaction_to, 
                              payment_date, payment_description, payment_amount, 
                              receipt, currency as payment_currency, created_at, exchange_rate,
                              DATE(payment_date) as payment_date_only, 
                              TIME(created_at) as payment_time 
                              FROM umrah_transactions WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$transaction_id, $tenant_id]);
        
        // Fetch the transaction
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($transaction) {
            echo json_encode(['success' => true, 'transaction' => $transaction]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching transaction: ' . $e->getMessage()]);
    }
} 
// Get Umrah booking ID from the request (ensure that the ID is properly sanitized)
elseif (isset($_GET['umrah_id'])) {
    $umrah_id = intval($_GET['umrah_id']); // Ensure that the umrah_id is an integer

    try {
        // Prepare a query to fetch all transactions for the given Umrah booking ID
        $stmt = $pdo->prepare("SELECT 
                                id, 
                                umrah_booking_id, 
                                payment_date, 
                                COALESCE(TIME(created_at), CURRENT_TIME()) as payment_time, 
                                payment_amount, 
                                currency as payment_currency, 
                                payment_description, 
                                transaction_to, exchange_rate 
                              FROM umrah_transactions 
                              WHERE umrah_booking_id = ? AND tenant_id = ?
                              ORDER BY payment_date DESC, payment_time DESC");
        $stmt->execute([$umrah_id, $tenant_id]);

        // Fetch all the results
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Return the transactions as JSON
        echo json_encode($transactions);
    } catch (PDOException $e) {
        // If an error occurs, return an error message
        echo json_encode(['error' => 'Error fetching transactions: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'No Umrah booking ID or transaction ID provided']);
}
?>
