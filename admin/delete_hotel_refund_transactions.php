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
require_once('../includes/db.php');


// Check if required parameters are present
if (!isset($_POST['transaction_id']) || !isset($_POST['refund_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$transaction_id = intval($_POST['transaction_id']);
$refund_id = intval($_POST['refund_id']);

try {
    // Start transaction
    $pdo->beginTransaction();

    // First get the transaction details to know the currency and main account
    $getTransactionStmt = $pdo->prepare("
        SELECT t.*, t.currency as transaction_currency, t.created_at as transaction_date 
        FROM main_account_transactions t
        JOIN main_account m ON t.main_account_id = m.id
        WHERE t.id = ? AND t.reference_id = ? AND t.transaction_of = ? AND t.tenant_id = ?
    ");
    $getTransactionStmt->execute([$transaction_id, $refund_id, 'hotel_refund', $tenant_id]);
    $transaction = $getTransactionStmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception('Transaction not found');
    }

    // Get the amount from the transaction (stored as negative for refunds)
    // When deleting a refund, we need to add this amount back to the account
    $amount = abs(floatval($transaction['amount']));

    // Determine which balance to update based on currency
    $balanceColumn = '';
    switch(strtoupper($transaction['currency'])) {
        case 'USD':
            $balanceColumn = 'usd_balance';
            break;
        case 'AFS':
            $balanceColumn = 'afs_balance';
            break;
        default:
            throw new Exception('Unsupported currency: ' . $transaction['currency']);
    }

    // Update balances of all subsequent transactions
    // Since we're adding the money back, we add the amount
    $updateSubsequentStmt = $pdo->prepare("
        UPDATE main_account_transactions 
        SET balance = balance + ?
        WHERE main_account_id = ? 
        AND currency = ? 
        AND created_at > ? 
        AND id != ? AND tenant_id = ?
    ");
    $updateSubsequentResult = $updateSubsequentStmt->execute([
        $amount, 
        $transaction['main_account_id'], 
        $transaction['currency'], 
        $transaction['transaction_date'],
        $transaction_id,
        $tenant_id
    ]);

    if (!$updateSubsequentResult) {
        throw new Exception('Failed to update subsequent transaction balances');
    }

    // Delete the transaction
    $deleteStmt = $pdo->prepare("
        DELETE FROM main_account_transactions 
        WHERE id = ? AND reference_id = ? AND transaction_of = ? AND tenant_id = ?
    ");
    $deleteResult = $deleteStmt->execute([$transaction_id, $refund_id, 'hotel_refund', $tenant_id]);

    if ($deleteResult && $deleteStmt->rowCount() > 0) {
        // Update the appropriate balance in the main_account table
        // Add the amount back to the account since we're deleting a refund
        $updateStmt = $pdo->prepare("
            UPDATE main_account 
            SET $balanceColumn = $balanceColumn + ?
            WHERE id = ? AND tenant_id = ?
        ");
        $updateResult = $updateStmt->execute([$amount, $transaction['main_account_id'], $tenant_id]);

        if ($updateResult) {
            // Check if this was the last transaction and update the visa_refunds processed status if needed
            $checkTransactionsStmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM main_account_transactions 
                WHERE reference_id = ? AND transaction_of = 'hotel_refund'
                AND tenant_id = ?
            ");
            $checkTransactionsStmt->execute([$refund_id, $tenant_id]);
            $transactionCount = $checkTransactionsStmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($transactionCount === 0) {
                // No more transactions, update visa_refunds status
                $updateRefundStmt = $pdo->prepare("
                    UPDATE hotel_refunds 
                    SET processed = 0, processed_by = NULL 
                    WHERE id = ? AND tenant_id = ?
                ");
                $updateRefundStmt->execute([$refund_id, $tenant_id]);
            }

            $pdo->commit();
            
            // Log the activity
            $old_values = json_encode([
                'transaction_id' => $transaction_id,
                'refund_id' => $refund_id,
                'amount' => $amount,
                'currency' => $transaction['currency'],
                'main_account_id' => $transaction['main_account_id'],
                'created_at' => $transaction['transaction_date']
            ]);
            $new_values = json_encode([]);
            
            $user_id = $_SESSION['user_id'] ?? 0;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $activityStmt = $pdo->prepare("
                INSERT INTO activity_log 
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
                VALUES (?, 'delete', 'main_account_transactions', ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $activityStmt->execute([$user_id, $transaction_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id]);
            
            echo json_encode(['success' => true, 'message' => 'Transaction deleted successfully and funds returned to account']);
        } else {
            throw new Exception('Failed to update main account balance');
        }
    } else {
        throw new Exception('Transaction not found or already deleted');
    }
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error deleting transaction: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error deleting transaction: ' . $e->getMessage()]);
}
?> 