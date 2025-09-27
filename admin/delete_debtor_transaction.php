<?php
// Include database connection
require_once '../includes/conn.php';

// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Initialize response array
$response = ['success' => false, 'message' => 'Invalid request'];

// Check if it's a POST request and delete_transaction is set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_transaction'])) {
    // Get and validate input
    $transaction_id = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
    $debtor_id = isset($_POST['debtor_id']) ? intval($_POST['debtor_id']) : 0;
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $currency = isset($_POST['currency']) ? sanitize_input($_POST['currency']) : '';
    
    // Validate required fields
    if ($transaction_id <= 0 || $debtor_id <= 0) {
        $response = ['success' => false, 'message' => 'Invalid transaction or debtor ID'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    try {
        $conn->begin_transaction();
        
        // Get transaction details
        $stmt = $conn->prepare("SELECT * FROM debtor_transactions WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $transaction_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $transaction = $result->fetch_assoc();
        
        if (!$transaction) {
            throw new Exception("Transaction not found");
        }
        
        // Get the linked main account transaction
        $stmt = $conn->prepare("SELECT * FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'debtor' AND tenant_id = ?");
        $stmt->bind_param("ii", $transaction_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $main_transaction = $result->fetch_assoc();
        
        if (!$main_transaction) {
            throw new Exception("Main account transaction not found");
        }

        // Use main account transaction's amount and currency for all main account updates
        $main_amount = $main_transaction['amount'];
        $main_currency = $main_transaction['currency'];

        // Update balances of all subsequent transactions
        $updateSubsequentStmt = $conn->prepare("
            UPDATE main_account_transactions 
            SET balance = balance - ?
            WHERE main_account_id = ? 
            AND currency = ? 
            AND created_at > ? 
            AND id != ? AND tenant_id = ?
        ");
        $updateSubsequentStmt->bind_param("dsssis", $main_amount, $main_transaction['main_account_id'], $main_currency, $main_transaction['created_at'], $main_transaction['id'], $tenant_id);
        $updateSubsequentStmt->execute();
        
        // Get debtor information
        $stmt = $conn->prepare("SELECT balance FROM debtors WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $debtor_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $debtor = $result->fetch_assoc();
        
        // Update debtor balance (add amount back)
        $new_balance = $debtor['balance'] + $transaction['amount'];
        $stmt = $conn->prepare("UPDATE debtors SET balance = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("dii", $new_balance, $debtor_id, $tenant_id);
        $stmt->execute();
        
        // Get main account info and update the correct currency balance
        $balance_column = strtolower($main_currency) . '_balance';
        if ($main_currency == 'DARHAM') {
            $balance_column = 'darham_balance';
        } elseif ($main_currency == 'EUR') {
            $balance_column = 'euro_balance';
        } elseif ($main_currency == 'USD') {
            $balance_column = 'usd_balance';
        } elseif ($main_currency == 'AFS') {
            $balance_column = 'afs_balance';
        }
        
        // Get current main account balance
        $stmt = $conn->prepare("SELECT $balance_column FROM main_account WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $main_transaction['main_account_id'], $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $main_account = $result->fetch_assoc();
        
        if (!$main_account) {
            throw new Exception("Main account not found");
        }
        
        // Update main account balance (subtract main transaction amount)
        $new_main_balance = $main_account[$balance_column] - $main_amount;
        $stmt = $conn->prepare("UPDATE main_account SET $balance_column = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("dii", $new_main_balance, $main_transaction['main_account_id'], $tenant_id);
        $stmt->execute();
        
        // Delete the transactions
        $stmt = $conn->prepare("DELETE FROM debtor_transactions WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $transaction_id, $tenant_id);
        $stmt->execute();
        
        $stmt = $conn->prepare("DELETE FROM main_account_transactions WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $main_transaction['id'], $tenant_id);
        $stmt->execute();
        
       
        
        $conn->commit();
        $response = [
            'success' => true, 
            'message' => 'Transaction reversed and deleted successfully!',
            'transaction_id' => $transaction_id,
            'debtor_id' => $debtor_id
        ];
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();

// Helper function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?> 