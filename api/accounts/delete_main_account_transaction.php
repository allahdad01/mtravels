<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
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
require_once('../../includes/db.php');

// Start transaction
$pdo->beginTransaction();

try {
    // Get transaction details first (to get account_id, amount, currency, type and created_at)
    $getQuery = "SELECT main_account_id, amount, currency, type, created_at FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $getStmt = $pdo->prepare($getQuery);
    $getStmt->bindParam(1, $transactionId, PDO::PARAM_INT);
    $getStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $getStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $getStmt->execute();
    $transaction = $getStmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception("Transaction not found");
    }

    $accountId = $transaction['main_account_id'];
    $amount = $transaction['amount'];
    $currency = $transaction['currency'];
    $type = $transaction['type']; // CREDIT or DEBIT
    $transactionDate = $transaction['created_at'];
    
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
                              AND id > ?
                              AND id != ? AND tenant_id = ? AND branch_id = ?";
    $updateSubsequentStmt = $pdo->prepare($updateSubsequentQuery);
    $updateSubsequentStmt->bindParam(1, $amount, PDO::PARAM_STR);
    $updateSubsequentStmt->bindParam(2, $accountId, PDO::PARAM_INT);
    $updateSubsequentStmt->bindParam(3, $currency, PDO::PARAM_STR);
    $updateSubsequentStmt->bindParam(4, $transactionId, PDO::PARAM_INT);
    $updateSubsequentStmt->bindParam(5, $transactionId, PDO::PARAM_INT);
    $updateSubsequentStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
    $updateSubsequentStmt->bindParam(7, $branch_id, PDO::PARAM_INT);
    $updateSubsequentStmt->execute();
    
    // Reverse the transaction based on its type
    if ($type === 'credit') {
        // For CREDIT transactions, we need to subtract the amount (reverse the addition)
        $updateQuery = "UPDATE main_account SET {$updateField} = {$updateField} - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    } else {
        // For DEBIT transactions, we need to add the amount back (reverse the subtraction)
        $updateQuery = "UPDATE main_account SET {$updateField} = {$updateField} + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    }

    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->bindParam(1, $amount, PDO::PARAM_STR);
    $updateStmt->bindParam(2, $accountId, PDO::PARAM_INT);
    $updateStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $updateStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $updateStmt->execute();
    
    // Delete the transaction
    $deleteQuery = "DELETE FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->bindParam(1, $transactionId, PDO::PARAM_INT);
    $deleteStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $deleteStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $deleteStmt->execute();
    
    // Commit the transaction
    $pdo->commit();
    
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
    
    $stmt_log = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id)
        VALUES (?, 'delete', 'main_account_transactions', ?, ?, ?, ?, ?, NOW(), ?)
    ");
    $stmt_log->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt_log->bindParam(2, $transactionId, PDO::PARAM_INT);
    $stmt_log->bindParam(3, $old_values, PDO::PARAM_STR);
    $stmt_log->bindParam(4, $new_values, PDO::PARAM_STR);
    $stmt_log->bindParam(5, $ip_address, PDO::PARAM_STR);
    $stmt_log->bindParam(6, $user_agent, PDO::PARAM_STR);
    $stmt_log->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $stmt_log->execute();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Transaction deleted successfully and subsequent balances adjusted'
    ]);
    
} catch (Exception $e) {
    // Rollback the transaction
    $pdo->rollBack();
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

