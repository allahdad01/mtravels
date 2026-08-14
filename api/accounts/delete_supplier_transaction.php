<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// ✅ CSRF Token Validation
if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit();
}

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
    // Get transaction details first (to get supplier_id, amount, currency, type and created_at)
    $getQuery = "SELECT supplier_id, amount, transaction_type, transaction_date, balance FROM supplier_transactions WHERE id = ? AND transaction_of IN ('fund', 'fund_withdrawal', 'supplier_bonus') AND tenant_id = ? AND branch_id = ?";
    $getStmt = $pdo->prepare($getQuery);
    $getStmt->bindParam(1, $transactionId, PDO::PARAM_INT);
    $getStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $getStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $getStmt->execute();
    $transaction = $getStmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception("Transaction not found");
    }

    $supplierId = $transaction['supplier_id'];
    $amount = $transaction['amount'];
    $type = strtolower($transaction['transaction_type']); // credit or debit
    $transactionDate = $transaction['transaction_date'];
    $transactionBalance = $transaction['balance']; // Current balance of the transaction

    // Update balances of all subsequent supplier transactions
    if ($type === 'debit') {
        // For DEBIT transactions, we need to add the amount to subsequent balances
        $updateSubsequentQuery = "UPDATE supplier_transactions
                                SET balance = balance + ?
                                WHERE supplier_id = ?
                                AND id > ?
                                AND id != ? AND tenant_id = ? AND branch_id = ?";
    } else { // credit
        // For CREDIT transactions, we need to subtract the amount from subsequent balances
        $updateSubsequentQuery = "UPDATE supplier_transactions
                                SET balance = balance - ?
                                WHERE supplier_id = ?
                                AND id > ?
                                AND id != ? AND tenant_id = ? AND branch_id = ?";
    }

    $updateSubsequentStmt = $pdo->prepare($updateSubsequentQuery);
    $updateSubsequentStmt->bindParam(1, $amount, PDO::PARAM_STR);
    $updateSubsequentStmt->bindParam(2, $supplierId, PDO::PARAM_INT);
    $updateSubsequentStmt->bindParam(3, $transactionId, PDO::PARAM_INT);
    $updateSubsequentStmt->bindParam(4, $transactionId, PDO::PARAM_INT);
    $updateSubsequentStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
    $updateSubsequentStmt->bindParam(6, $branch_id, PDO::PARAM_INT);
    $updateSubsequentStmt->execute();
    
    // Reverse the transaction based on its type
    if ($type === 'credit') {
        // For CREDIT transactions, we need to subtract the amount
        $updateQuery = "UPDATE suppliers SET balance = balance - ? WHERE id = ? AND supplier_type = 'External' AND tenant_id = ? AND branch_id = ?";
    } else { // debit
        // For DEBIT transactions, we need to add the amount back
        $updateQuery = "UPDATE suppliers SET balance = balance + ? WHERE id = ? AND supplier_type = 'External' AND tenant_id = ? AND branch_id = ?";
    }

    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->bindParam(1, $amount, PDO::PARAM_STR);
    $updateStmt->bindParam(2, $supplierId, PDO::PARAM_INT);
    $updateStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $updateStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $updateStmt->execute();

    // Handle main account transaction if it exists
    if ($transactionId) {
        // Get main account transaction details
        $mainTxQuery = "SELECT id, main_account_id, amount, type, currency FROM main_account_transactions WHERE reference_id = ? AND transaction_of IN ('supplier_fund', 'supplier_fund_withdrawal') AND tenant_id = ? AND branch_id = ?";
        $mainTxStmt = $pdo->prepare($mainTxQuery);
        $mainTxStmt->bindParam(1, $transactionId, PDO::PARAM_INT);
        $mainTxStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $mainTxStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $mainTxStmt->execute();
        $mainTx = $mainTxStmt->fetch(PDO::FETCH_ASSOC);

        if ($mainTx) {
            $mainAmount = $mainTx['amount'];
            $mainType = strtolower($mainTx['type']); // credit or debit
            $mainAccountId = $mainTx['main_account_id'];
            $mainTxId = $mainTx['id'];
            $currency = strtoupper($mainTx['currency']) === 'EURO' ? 'EUR' : strtoupper($mainTx['currency']);

            // Map currency to the correct balance field
            $currencyFieldMap = [
                'USD' => 'usd_balance',
                'AFS' => 'afs_balance',
                'EUR' => 'euro_balance',
                'DARHAM' => 'darham_balance',
                'SAR' => 'sar_balance'
            ];
            
            // Check if the currency is in our map
            if (!isset($currencyFieldMap[$currency])) {
                throw new Exception("Unknown currency: " . $currency);
            }
            
            // Get the correct field name
            $updateField = $currencyFieldMap[$currency];
            
            // Update balances of all subsequent main account transactions
            if ($mainType === 'credit') {
                // For CREDIT transactions to main account, we need to subtract the amount from subsequent balances
                $updateMainSubsequentQuery = "UPDATE main_account_transactions
                                            SET balance = balance - ?
                                            WHERE main_account_id = ?
                                            AND currency = ?
                                            AND id > ?
                                            AND tenant_id = ? AND branch_id = ?";
            } else { // debit
                // For DEBIT transactions from main account, we need to add the amount to subsequent balances
                $updateMainSubsequentQuery = "UPDATE main_account_transactions
                                            SET balance = balance + ?
                                            WHERE main_account_id = ?
                                            AND currency = ?
                                            AND id > ?
                                            AND tenant_id = ? AND branch_id = ?";
            }

            $updateMainSubsequentStmt = $pdo->prepare($updateMainSubsequentQuery);
            $updateMainSubsequentStmt->bindParam(1, $mainAmount, PDO::PARAM_STR);
            $updateMainSubsequentStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $updateMainSubsequentStmt->bindParam(3, $currency, PDO::PARAM_STR);
            $updateMainSubsequentStmt->bindParam(4, $mainTxId, PDO::PARAM_INT);
            $updateMainSubsequentStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
            $updateMainSubsequentStmt->bindParam(6, $branch_id, PDO::PARAM_INT);
            $updateMainSubsequentStmt->execute();
            
            // Reverse the main account balance
            if ($mainType === 'credit') {
                // For CREDIT to main account, subtract the amount
                $mainUpdateQuery = "UPDATE main_account SET {$updateField} = {$updateField} - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            } else { // debit
                // For DEBIT from main account, add the amount back
                $mainUpdateQuery = "UPDATE main_account SET {$updateField} = {$updateField} + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            }

            $mainUpdateStmt = $pdo->prepare($mainUpdateQuery);
            $mainUpdateStmt->bindParam(1, $mainAmount, PDO::PARAM_STR);
            $mainUpdateStmt->bindParam(2, $mainAccountId, PDO::PARAM_INT);
            $mainUpdateStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $mainUpdateStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $mainUpdateStmt->execute();
            
            // Delete the main account transaction
            $mainDeleteQuery = "DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of IN ('supplier_fund', 'supplier_fund_withdrawal') AND tenant_id = ? AND branch_id = ?";
            $mainDeleteStmt = $pdo->prepare($mainDeleteQuery);
            $mainDeleteStmt->bindParam(1, $transactionId, PDO::PARAM_INT);
            $mainDeleteStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $mainDeleteStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $mainDeleteStmt->execute();
        }
    }
    
    // Delete the transaction
    $deleteQuery = "DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->bindParam(1, $transactionId, PDO::PARAM_INT);
    $deleteStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $deleteStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $deleteStmt->execute();
    
    // Commit the transaction
    $pdo->commit();
    
    // Log the activity
    $old_values = json_encode([
        'supplier_id' => $supplierId,
        'transaction_id' => $transactionId,
        'amount' => $amount,
        'type' => $type,
        'transaction_date' => $transactionDate,
        'balance' => $transactionBalance
    ]);
    $new_values = json_encode([]);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt_log = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, 'delete', 'supplier_transactions', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt_log->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt_log->bindParam(2, $transactionId, PDO::PARAM_INT);
    $stmt_log->bindParam(3, $old_values, PDO::PARAM_STR);
    $stmt_log->bindParam(4, $new_values, PDO::PARAM_STR);
    $stmt_log->bindParam(5, $ip_address, PDO::PARAM_STR);
    $stmt_log->bindParam(6, $user_agent, PDO::PARAM_STR);
    $stmt_log->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $stmt_log->bindParam(8, $branch_id, PDO::PARAM_INT);
    $stmt_log->execute();
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Transaction deleted successfully and subsequent balances adjusted',
        'supplier_id' => $supplierId
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

