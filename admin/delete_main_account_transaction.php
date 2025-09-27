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

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate transaction_id
if (!isset($data['transaction_id']) || !is_numeric($data['transaction_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Invalid transaction ID']);
    exit();
}

$transactionId = intval($data['transaction_id']);

// Database connection
require_once('../includes/conn.php');

// Start transaction
$conn->begin_transaction();

try {
    // Get transaction details first (to get account_id, amount, currency, type and created_at)
    $getQuery = "SELECT main_account_id, amount, currency, type, created_at FROM main_account_transactions WHERE id = ? AND tenant_id = ?";
    $getStmt = $conn->prepare($getQuery);
    $getStmt->bind_param("ii", $transactionId, $tenant_id);
    $getStmt->execute();
    $result = $getStmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Transaction not found");
    }
    
    $transaction = $result->fetch_assoc();
    $accountId = $transaction['main_account_id'];
    $amount = $transaction['amount'];
    $currency = $transaction['currency'];
    $type = $transaction['type']; // CREDIT or DEBIT
    $transactionDate = $transaction['created_at'];
    $getStmt->close();
    
    // Map currency codes to the correct database field names
    $currencyFieldMap = [
        'USD' => 'usd_balance',
        'AFS' => 'afs_balance',
        'EUR' => 'euro_balance',
        'DARHAM' => 'darham_balance'
    ];
    
    // Check if the currency is in our map
    if (!isset($currencyFieldMap[$currency])) {
        throw new Exception("Unknown currency: " . $currency);
    }
    
    // Get the correct field name
    $updateField = $currencyFieldMap[$currency];
    
    // Update balances of all subsequent transactions
    $updateSubsequentQuery = "UPDATE main_account_transactions 
                             SET balance = balance - ? 
                             WHERE main_account_id = ? 
                             AND currency = ? 
                             AND created_at > ? 
                             AND id != ? AND tenant_id = ?";
    $updateSubsequentStmt = $conn->prepare($updateSubsequentQuery);
    $updateSubsequentStmt->bind_param("dissis", $amount, $accountId, $currency, $transactionDate, $transactionId, $tenant_id);
    $updateSubsequentStmt->execute();
    $updateSubsequentStmt->close();
    
    // Reverse the transaction based on its type
    if ($type === 'credit') {
        // For CREDIT transactions, we need to subtract the amount (reverse the addition)
        $updateQuery = "UPDATE main_account SET {$updateField} = {$updateField} - ? WHERE id = ? AND tenant_id = ?";
    } else {
        // For DEBIT transactions, we need to add the amount back (reverse the subtraction)
        $updateQuery = "UPDATE main_account SET {$updateField} = {$updateField} + ? WHERE id = ? AND tenant_id = ?";
    }
    
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("dis", $amount, $accountId, $tenant_id);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Delete the transaction
    $deleteQuery = "DELETE FROM main_account_transactions WHERE id = ? AND tenant_id = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->bind_param("ii", $transactionId, $tenant_id);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    // Commit the transaction
    $conn->commit();
    
    // Log the activity
    $old_values = json_encode([
        'main_account_id' => $accountId,
        'transaction_id' => $transactionId,
        'amount' => $amount,
        'currency' => $currency,
        'type' => $type,
        'created_at' => $transactionDate
    ]);
    $new_values = json_encode([]);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt_log = $conn->prepare("
        INSERT INTO activity_log 
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
        VALUES (?, 'delete', 'main_account_transactions', ?, ?, ?, ?, ?, NOW(), ?)
    ");
    $stmt_log->bind_param("iisssss", $user_id, $transactionId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
    $stmt_log->execute();
    $stmt_log->close();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Transaction deleted successfully and subsequent balances adjusted'
    ]);
    
} catch (Exception $e) {
    // Rollback the transaction
    $conn->rollback();
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

// Close connection
$conn->close();
