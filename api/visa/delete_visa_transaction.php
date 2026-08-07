<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

require_once('../../includes/db.php');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if required parameters are present
if (!isset($_POST['transaction_id']) || !isset($_POST['visa_id']) || !isset($_POST['amount'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);

// Validate amount
$amount = isset($_POST['amount']) ? DbSecurity::validateInput($_POST['amount'], 'float', ['min' => 0]) : null;

// Validate visa_id
$visa_id = isset($_POST['visa_id']) ? DbSecurity::validateInput($_POST['visa_id'], 'int', ['min' => 0]) : null;

// Validate transaction_id
$transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;
    exit;
}

$transaction_id = intval($_POST['transaction_id']);
$visa_id = intval($_POST['visa_id']);
$amount = floatval($_POST['amount']);

try {
    // Start transaction
    $pdo->beginTransaction();

    // First get the transaction details to know the currency and main account
    $getTransactionStmt = $pdo->prepare("
        SELECT t.*, t.currency as transaction_currency,
               t.type as transaction_type, t.description
        FROM main_account_transactions t
        JOIN main_account m ON t.main_account_id = m.id
        WHERE t.id = ? AND t.reference_id = ? AND t.transaction_of = ? AND t.tenant_id = ? AND t.branch_id = ?
    ");
    $getTransactionStmt->execute([$transaction_id, $visa_id, 'visa_sale', $tenant_id, $branch_id]);
    $transaction = $getTransactionStmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception('Transaction not found');
    }

    // Get the stored amount from the transaction record
    $storedAmount = floatval($transaction['amount']);
    
    // Calculate the adjustment (treat as regular transaction)
    $adjustmentAmount = -$storedAmount;

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
        case 'SAR':
            $Column = 'sar_balance';
            break;
            break;
        case 'SAR':
            $balanceColumn = 'sar_balance';
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
        AND id > ?
        AND id != ?
        AND tenant_id = ?
        AND branch_id = ?
    ");
    $updateSubsequentResult = $updateSubsequentStmt->execute([
        $adjustmentAmount,
        $transaction['main_account_id'],
        $transaction['currency'],
        $transaction_id,
        $transaction_id,
        $tenant_id,
        $branch_id
    ]);

    if (!$updateSubsequentResult) {
        throw new Exception('Failed to update subsequent transaction balances');
    }

    // Delete the transaction
    $deleteStmt = $pdo->prepare("
        DELETE FROM main_account_transactions
        WHERE id = ? AND reference_id = ? AND transaction_of = ? AND tenant_id = ? AND branch_id = ?
    ");
    $deleteResult = $deleteStmt->execute([$transaction_id, $visa_id, 'visa_sale', $tenant_id, $branch_id]);

    if ($deleteResult && $deleteStmt->rowCount() > 0) {
        // Update the appropriate balance in the main_account table
        $updateStmt = $pdo->prepare("
            UPDATE main_account
            SET $balanceColumn = $balanceColumn + ?
            WHERE id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $updateResult = $updateStmt->execute([$adjustmentAmount, $transaction['main_account_id'], $tenant_id, $branch_id]);

        if ($updateResult) {
            // Delete related notifications
            $deleteNotifStmt = $pdo->prepare("DELETE FROM notifications WHERE transaction_id = ? AND transaction_type = 'visa' AND tenant_id = ? AND branch_id = ?");
            $deleteNotifStmt->execute([$transaction_id, $tenant_id, $branch_id]);

            $pdo->commit();
            $message = 'Transaction deleted successfully and balances adjusted';
            
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            throw new Exception('Failed to update main account balance');
        }
    } else {
        throw new Exception('Transaction not found or already deleted');
    }
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error deleting transaction: ' . $e->getMessage()]);
}
?> 