<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once('../includes/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if required parameters are present
if (!isset($_POST['transaction_id']) || !isset($_POST['booking_id']) || !isset($_POST['amount'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);

// Validate amount
$amount = isset($_POST['amount']) ? DbSecurity::validateInput($_POST['amount'], 'float', ['min' => 0]) : null;

// Validate booking_id
$booking_id = isset($_POST['booking_id']) ? DbSecurity::validateInput($_POST['booking_id'], 'int', ['min' => 0]) : null;

// Validate transaction_id
$transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;
    exit;
}

$transaction_id = intval($_POST['transaction_id']);
$booking_id = intval($_POST['booking_id']);
$amount = floatval($_POST['amount']);

try {
    // Start transaction
    $pdo->beginTransaction();

    // First get the transaction details to know the currency and main account
    $getTransactionStmt = $pdo->prepare("
        SELECT t.*, t.currency as transaction_currency, t.created_at as transaction_date, 
               t.type as transaction_type, t.description
        FROM main_account_transactions t
        JOIN main_account m ON t.main_account_id = m.id
        WHERE t.id = ? AND t.reference_id = ? AND t.transaction_of = ? AND t.tenant_id = ?
    ");
    $getTransactionStmt->execute([$transaction_id, $booking_id, 'hotel', $tenant_id]);
    $transaction = $getTransactionStmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception('Transaction not found');
    }

    // Determine if this is a refund transaction
    $isRefund = ($transaction['transaction_type'] === 'debit' || 
                 strpos($transaction['description'], 'Refund for:') === 0);

    // Get the stored amount from the transaction record
    $storedAmount = floatval($transaction['amount']);
    
    // Calculate the correct adjustment based on transaction type
    if ($isRefund) {
        // For refunds (type 'debit'), the original effect was to DECREASE the balance
        // So we need to INCREASE the balance when deleting (add the amount back)
        $adjustmentAmount = $storedAmount;
    } else {
        // For regular payments (type 'credit'), the original effect was to INCREASE the balance
        // So we need to DECREASE the balance when deleting (subtract the amount)
        $adjustmentAmount = -$storedAmount;
    }

    // Debug logging to track the calculations
    error_log("Delete Transaction - ID: {$transaction_id}, Type: " . ($isRefund ? 'Refund' : 'Regular') . 
              ", Transaction Type: {$transaction['transaction_type']}, Amount: {$amount}, " .
              "Stored Amount: {$storedAmount}, Adjustment: {$adjustmentAmount}");

    // Determine which balance to update based on currency
    $balanceColumn = '';
    switch(strtoupper($transaction['currency'])) {
        case 'USD':
            $balanceColumn = 'usd_balance';
            break;
        case 'AFS':
            $balanceColumn = 'afs_balance';
            break;
            case 'EUR':
                $balanceColumn = 'euro_balance';
                break;
                case 'DARHAM':
                    $balanceColumn = 'darham_balance';
                    break;
        default:
            throw new Exception('Unsupported currency: ' . $transaction['currency']);
    }

    // Update balances of all subsequent transactions with the correct adjustment
    $updateSubsequentStmt = $pdo->prepare("
        UPDATE main_account_transactions 
        SET balance = balance + ?
        WHERE main_account_id = ? 
        AND currency = ? 
        AND created_at > ? 
        AND id != ? AND tenant_id = ?
    ");
    $updateSubsequentResult = $updateSubsequentStmt->execute([
        $adjustmentAmount, 
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
    $deleteResult = $deleteStmt->execute([$transaction_id, $booking_id, 'hotel', $tenant_id]);

    if ($deleteResult && $deleteStmt->rowCount() > 0) {
        // Update the appropriate balance in the main_account table
        $updateStmt = $pdo->prepare("
            UPDATE main_account 
            SET $balanceColumn = $balanceColumn + ?
            WHERE id = ? AND tenant_id = ?
        ");
        $updateResult = $updateStmt->execute([$adjustmentAmount, $transaction['main_account_id'], $tenant_id]);

        if ($updateResult) {
            $pdo->commit();
            $message = $isRefund ? 
                'Refund transaction deleted successfully and balances adjusted' : 
                'Transaction deleted successfully and balances adjusted';
            
            // Log the activity
            $old_values = json_encode([
                'transaction_id' => $transaction_id,
                'booking_id' => $booking_id,
                'amount' => $storedAmount,
                'currency' => $transaction['currency'],
                'transaction_type' => $transaction['transaction_type'],
                'is_refund' => $isRefund,
                'main_account_id' => $transaction['main_account_id'],
                'description' => $transaction['description'],
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
            
            echo json_encode(['success' => true, 'message' => $message]);
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