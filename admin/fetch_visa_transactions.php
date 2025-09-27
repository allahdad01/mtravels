<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();


// Include database connection
require_once('../includes/conn.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate input
if (!isset($_GET['visa_id']) || !is_numeric($_GET['visa_id'])) {
    echo json_encode([]);
    exit;
}

$visaId = intval($_GET['visa_id']);
$tenant_id = $_SESSION['tenant_id'];
try {
    // Prepare a query to fetch all transactions for the given visa ID
    $query = "SELECT t.*, 
                    DATE_FORMAT(t.created_at, '%Y-%m-%d') AS payment_date,
                    TIME_FORMAT(t.created_at, '%H:%i:%s') AS payment_time,
                    DATE_FORMAT(t.created_at, '%b %d, %Y %h:%i %p') AS formatted_date
               FROM main_account_transactions t
               WHERE t.reference_id = ? AND t.tenant_id = ?
               AND t.transaction_of = 'visa_sale'
               ORDER BY t.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $visaId, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Fetch all results
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        // Convert amount to float for proper JSON encoding
        $row['amount'] = floatval($row['amount']);
        
        // Ensure payment description exists for older records
        if (!isset($row['description']) && isset($row['remarks'])) {
            $row['description'] = $row['remarks'];
        }
        
        $transactions[] = $row;
    }
    
    // Return the transactions as JSON
    echo json_encode($transactions);
    
} catch (Exception $e) {
    // Log error
    error_log('Error fetching visa transactions: ' . $e->getMessage());
    
    // Return empty array to avoid breaking the client-side code
    echo json_encode([]);
}
?>
