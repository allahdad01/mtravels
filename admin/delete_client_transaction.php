<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

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
    // Get transaction details first
    $getQuery = "SELECT client_id, amount, currency, type, reference_id, created_at, balance FROM client_transactions WHERE id = ? AND tenant_id = ?";
    $getStmt = $conn->prepare($getQuery);
    $getStmt->bind_param("ii", $transactionId, $tenant_id);
    $getStmt->execute();
    $result = $getStmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Transaction not found");
    }
    
    $transaction = $result->fetch_assoc();
    $clientId = $transaction['client_id'];
    $amount = $transaction['amount'];
    $currency = $transaction['currency'];
    $type = strtolower($transaction['type']); // credit or debit
    $referenceId = $transaction['reference_id']; // Reference to main account transaction
    $transactionDate = $transaction['created_at'];
    $transactionBalance = $transaction['balance']; // Current balance of the transaction
    $getStmt->close();
    
    // Map currency to the correct balance field
    $currencyFieldMap = [
        'USD' => 'usd_balance',
        'AFS' => 'afs_balance'
    ];
    
    // Check if the currency is in our map
    if (!isset($currencyFieldMap[$currency])) {
        throw new Exception("Unknown currency: " . $currency);
    }
    
    // Get the correct field name
    $updateField = $currencyFieldMap[$currency];
    
    // Update balances of all subsequent client transactions
    if ($type === 'debit') {
        // For DEBIT transactions, we need to add the amount to subsequent balances
        $updateSubsequentQuery = "UPDATE client_transactions 
                                SET balance = balance + ? 
                                WHERE client_id = ? 
                                AND currency = ? 
                                AND created_at > ? 
                                AND id != ? AND tenant_id = ?";
    } else { // credit
        // For CREDIT transactions, we need to subtract the amount from subsequent balances
        $updateSubsequentQuery = "UPDATE client_transactions 
                                SET balance = balance - ? 
                                WHERE client_id = ? 
                                AND currency = ? 
                                AND created_at > ? 
                                AND id != ? AND tenant_id = ?";
    }
    
    $updateSubsequentStmt = $conn->prepare($updateSubsequentQuery);
    $updateSubsequentStmt->bind_param("dissi", $amount, $clientId, $currency, $transactionDate, $transactionId, $tenant_id);
    $updateSubsequentStmt->execute();
    $updateSubsequentStmt->close();
    
    // Reverse the transaction based on its type
    if ($type === 'credit') {
        // For CREDIT transactions, we need to subtract the amount
        $updateQuery = "UPDATE clients SET {$updateField} = {$updateField} - ? WHERE id = ? and client_type = 'regular'";
    } else if ($type === 'debit') {
        // For DEBIT transactions, we need to add the amount back
        $updateQuery = "UPDATE clients SET {$updateField} = {$updateField} + ? WHERE id = ? and client_type = 'regular'";
    } else {
        throw new Exception("Unknown transaction type: " . $type);
    }
    
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("di", $amount, $clientId, $tenant_id);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Handle main account transaction if it exists
    if ($referenceId) {
        // Get main account transaction details
        $mainTxQuery = "SELECT main_account_id, amount, type, created_at FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'client_fund' AND tenant_id = ?";
        $mainTxStmt = $conn->prepare($mainTxQuery);
        $mainTxStmt->bind_param("ii", $transactionId, $tenant_id);
        $mainTxStmt->execute();
        $mainTxResult = $mainTxStmt->get_result();
        
        if ($mainTxResult->num_rows > 0) {
            $mainTx = $mainTxResult->fetch_assoc();
            $mainAmount = $mainTx['amount'];
            $mainType = strtolower($mainTx['type']); // credit or debit
            $mainAccountId = $mainTx['main_account_id'];
            $mainTxDate = $mainTx['created_at'];
            
            // Update balances of all subsequent main account transactions
            // For credit transactions to main account, we need to subtract the amount from subsequent balances
            // For debit transactions from main account, we need to add the amount to subsequent balances
            if ($mainType === 'credit') {
                $updateMainSubsequentQuery = "UPDATE main_account_transactions 
                                            SET balance = balance - ? 
                                            WHERE main_account_id = ? 
                                            AND currency = ? 
                                            AND created_at > ? 
                                            AND reference_id != ? AND tenant_id = ?";
            } else {
                $updateMainSubsequentQuery = "UPDATE main_account_transactions 
                                            SET balance = balance + ? 
                                            WHERE main_account_id = ? 
                                            AND currency = ? 
                                            AND created_at > ? 
                                            AND reference_id != ? AND tenant_id = ?";
            }
            
            $updateMainSubsequentStmt = $conn->prepare($updateMainSubsequentQuery);
            $updateMainSubsequentStmt->bind_param("dissi", $mainAmount, $mainAccountId, $currency, $mainTxDate, $transactionId, $tenant_id);
            $updateMainSubsequentStmt->execute();
            $updateMainSubsequentStmt->close();
            
            // Reverse the main account balance
            if ($mainType === 'credit') {
                // For CREDIT to main account, subtract the amount
                $mainUpdateQuery = "UPDATE main_account SET {$updateField} = {$updateField} - ? WHERE id = ? AND tenant_id = ?";
            } else {
                // For DEBIT from main account, add the amount back
                $mainUpdateQuery = "UPDATE main_account SET {$updateField} = {$updateField} + ? WHERE id = ? AND tenant_id = ?";
            }
            
            $mainUpdateStmt = $conn->prepare($mainUpdateQuery);
            $mainUpdateStmt->bind_param("di", $mainAmount, $mainAccountId, $tenant_id);
            $mainUpdateStmt->execute();
            $mainUpdateStmt->close();
            
            // Delete the main account transaction
            $mainDeleteQuery = "DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'client_fund' AND tenant_id = ?";
            $mainDeleteStmt = $conn->prepare($mainDeleteQuery);
            $mainDeleteStmt->bind_param("ii", $transactionId, $tenant_id);
            $mainDeleteStmt->execute();
            $mainDeleteStmt->close();
        }
        $mainTxStmt->close();
    }
    
    // Delete the client transaction
    $deleteQuery = "DELETE FROM client_transactions WHERE id = ? AND tenant_id = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->bind_param("ii", $transactionId, $tenant_id);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    // Commit the transaction
    $conn->commit();
    
    // Log the activity
    $old_values = json_encode([
        'client_id' => $clientId,
        'transaction_id' => $transactionId,
        'amount' => $amount,
        'currency' => $currency,
        'type' => $type,
        'balance' => $transactionBalance,
        'created_at' => $transactionDate
    ]);
    $new_values = json_encode([]);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt_log = $conn->prepare("
        INSERT INTO activity_log 
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
        VALUES (?, 'delete', 'client_transactions', ?, ?, ?, ?, ?, NOW(), ?)
    ");
    $stmt_log->bind_param("iissssi", $user_id, $transactionId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
    $stmt_log->execute();
    $stmt_log->close();
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Transaction deleted successfully and subsequent balances adjusted',
        'client_id' => $clientId
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
