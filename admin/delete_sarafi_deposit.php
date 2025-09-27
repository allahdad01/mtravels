<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
require_once('../includes/db.php');


// Check if required parameters are present
if (!isset($_POST['transaction_id']) || !isset($_POST['amount'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Validate amount
$amount = isset($_POST['amount']) ? DbSecurity::validateInput($_POST['amount'], 'float', ['min' => 0]) : null;

// Validate transaction_id
$transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;

try {
    // Start transaction
    $pdo->beginTransaction();

    // First get the transaction details to know the currency and main account
    $getTransactionStmt = $pdo->prepare("
        SELECT t.*, t.currency as transaction_currency, t.created_at as transaction_date,
               c.name as customer_name 
        FROM sarafi_transactions t
        JOIN customers c ON t.customer_id = c.id
        WHERE t.id = ? AND t.type = ? AND t.tenant_id = ?
    ");
    $getTransactionStmt->execute([$transaction_id, 'deposit', $tenant_id]);
    $transaction = $getTransactionStmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception('Transaction not found');
    }

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
        case 'AED':
            $balanceColumn = 'darham_balance';
            break;
        default:
            throw new Exception('Unsupported currency: ' . $transaction['currency']);
    }

    // Get the main account transaction
    $mainTransactionStmt = $pdo->prepare("
        SELECT * FROM main_account_transactions 
        WHERE reference_id = ? AND transaction_of = ? AND tenant_id = ?
    ");
    $mainTransactionStmt->execute([$transaction_id, 'deposit_sarafi', $tenant_id]);
    $mainTransaction = $mainTransactionStmt->fetch(PDO::FETCH_ASSOC);

    if (!$mainTransaction) {
        throw new Exception('Main account transaction not found');
    }

    // Update balances of all subsequent transactions
    $updateSubsequentStmt = $pdo->prepare("
        UPDATE main_account_transactions 
        SET balance = balance - ?
        WHERE main_account_id = ? 
        AND currency = ? 
        AND created_at > ? 
        AND id != ? AND tenant_id = ?
    ");
    $updateSubsequentResult = $updateSubsequentStmt->execute([
        $amount, 
        $mainTransaction['main_account_id'], 
        $transaction['currency'], 
        $mainTransaction['created_at'],
        $mainTransaction['id'],
        $tenant_id
    ]);

    if (!$updateSubsequentResult) {
        throw new Exception('Failed to update subsequent transaction balances');
    }

    // Update customer wallet balance
    $updateWalletStmt = $pdo->prepare("
        UPDATE customer_wallets 
        SET balance = balance - ? 
        WHERE customer_id = ? AND currency = ? AND tenant_id = ?
    ");
    $updateWalletResult = $updateWalletStmt->execute([
        $amount,
        $transaction['customer_id'],
        $transaction['currency'],
        $tenant_id
    ]);

    if (!$updateWalletResult) {
        throw new Exception('Failed to update customer wallet balance');
    }

    // Delete the main account transaction
    $deleteMainStmt = $pdo->prepare("
        DELETE FROM main_account_transactions 
        WHERE id = ? AND tenant_id = ?
    ");
    $deleteMainResult = $deleteMainStmt->execute([$mainTransaction['id'], $tenant_id]);

    if (!$deleteMainResult) {
        throw new Exception('Failed to delete main account transaction');
    }

    // Delete the sarafi transaction
    $deleteStmt = $pdo->prepare("
        DELETE FROM sarafi_transactions 
        WHERE id = ? AND tenant_id = ?
    ");
    $deleteResult = $deleteStmt->execute([$transaction_id, $tenant_id]);

    if ($deleteResult && $deleteStmt->rowCount() > 0) {
        // Update the appropriate balance in the main_account table
        $updateStmt = $pdo->prepare("
            UPDATE main_account 
            SET $balanceColumn = $balanceColumn - ?
            WHERE id = ? AND tenant_id = ?
        ");
        $updateResult = $updateStmt->execute([$amount, $mainTransaction['main_account_id'], $tenant_id]);

        if ($updateResult) {
            // Log the activity
            $old_values = json_encode([
                'transaction_id' => $transaction_id,
                'amount' => $amount,
                'currency' => $transaction['currency'],
                'customer_id' => $transaction['customer_id'],
                'customer_name' => $transaction['customer_name'],
                'main_account_id' => $mainTransaction['main_account_id'],
                'created_at' => $transaction['created_at']
            ]);
            $new_values = json_encode([]);
            
            $user_id = $_SESSION['user_id'] ?? 0;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $activityStmt = $pdo->prepare("
                INSERT INTO activity_log 
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
                VALUES (?, 'delete', 'sarafi_transactions', ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $activityStmt->execute([$user_id, $transaction_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Deposit transaction deleted successfully']);
        } else {
            throw new Exception('Failed to update main account balance');
        }
    } else {
        throw new Exception('Transaction not found or already deleted');
    }
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error deleting deposit transaction: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error deleting deposit transaction: ' . $e->getMessage()]);
}
?> 