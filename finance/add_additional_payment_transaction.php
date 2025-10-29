<?php
session_start();
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
require_once('../includes/db.php');


// Validate payment_time
$payment_time = isset($_POST['payment_time']) ? DbSecurity::validateInput($_POST['payment_time'], 'string', ['maxlength' => 255]) : null;

// Validate payment_date
$payment_date = isset($_POST['payment_date']) ? DbSecurity::validateInput($_POST['payment_date'], 'date') : null;

// Validate payment_description
$payment_description = isset($_POST['payment_description']) ? DbSecurity::validateInput($_POST['payment_description'], 'string', ['maxlength' => 255]) : null;

// Validate payment_amount
$payment_amount = isset($_POST['payment_amount']) ? DbSecurity::validateInput($_POST['payment_amount'], 'float', ['min' => 0]) : null;

// Validate main_account_id
$main_account_id = isset($_POST['main_account_id']) ? DbSecurity::validateInput($_POST['main_account_id'], 'int', ['min' => 0]) : null;

// Validate currency
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : null;

// Validate payment_type
$payment_type = isset($_POST['payment_type']) ? DbSecurity::validateInput($_POST['payment_type'], 'string', ['maxlength' => 255]) : null;

// Validate payment_id
$payment_id = isset($_POST['payment_id']) ? DbSecurity::validateInput($_POST['payment_id'], 'int', ['min' => 0]) : null;

// Validate exchange_rate
$exchange_rate = isset($_POST['exchange_rate']) ? DbSecurity::validateInput($_POST['exchange_rate'], 'float', ['min' => 0]) : null;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $paymentId = intval($_POST['payment_id']);
        $paymentType = $_POST['payment_type'];
        $currency = $_POST['currency'];
        $mainAccountId = intval($_POST['main_account_id']);
        $amount = floatval($_POST['payment_amount']);
        $description = $_POST['payment_description'];
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $payment_time = $_POST['payment_time'] ?? date('H:i:s');
        $payment_datetime = $payment_date . ' ' . $payment_time;
        $exchangeRate = isset($_POST['exchange_rate']) ? floatval($_POST['exchange_rate']) : null;

        // Begin transaction
        $pdo->beginTransaction();

        // Get current balance
        switch ($currency) {
            case 'USD':
                $balanceField = 'usd_balance';
                break;
            case 'AFS':
                $balanceField = 'afs_balance';
                break;
            case 'EUR':
                $balanceField = 'euro_balance';
                break;
            case 'DARHAM':
                $balanceField = 'darham_balance';
                break;
            default:
                throw new Exception("Unsupported currency: $currency");
        } 
        $stmt = $pdo->prepare("SELECT $balanceField as current_balance FROM main_account WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$mainAccountId, $tenant_id]);
        $balanceResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $newBalance = $balanceResult['current_balance'] + $amount;

        // Update main account balance
        $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$newBalance, $mainAccountId, $tenant_id]);

        // Insert transaction record
        $stmt = $pdo->prepare("INSERT INTO main_account_transactions
            (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, exchange_rate, created_at, tenant_id)
            VALUES (?, 'credit', ?, ?, ?, 'additional_payment', ?, ?, ?, ?, ?)");
        $stmt->execute([
            $mainAccountId,
            $amount,
            $currency,
            $description,
            $paymentId,
            $newBalance,
            $exchangeRate,
            $payment_datetime,
            $tenant_id
        ]);

        // Get the last inserted ID using PDO's lastInsertId()
        $transaction_id = $pdo->lastInsertId();

        // Create notification
        $notificationMessage = sprintf(
            "New additional payment received: Amount %s %.2f - %s",
            $currency,
            $amount,
            $description
        );

        $notifStmt = $pdo->prepare("
            INSERT INTO notifications 
            (transaction_id, transaction_type, message, status, created_at, tenant_id) 
            VALUES (?, 'additional_payment', ?, 'Unread', NOW(), ?)
        ");
        
        if (!$notifStmt->execute([$transaction_id, $notificationMessage, $tenant_id])) {
            throw new Exception("Failed to create notification");
        }

        // Commit transaction
        $pdo->commit();
        
        // Log the activity
        $old_values = json_encode([]);
        $new_values = json_encode([
            'main_account_id' => $mainAccountId,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'payment_id' => $paymentId,
            'balance' => $newBalance,
            'exchange_rate' => $exchangeRate,
            'payment_datetime' => $payment_datetime,
            'tenant_id' => $tenant_id
        ]);
        
        $user_id = $_SESSION['user_id'];
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        $activityStmt = $pdo->prepare("
            INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
            VALUES (?, 'add', 'main_account_transactions', ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $activityStmt->execute([$user_id, $transaction_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id]);
        
        echo json_encode(['success' => true, 'message' => 'Transaction added successfully']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in add_additional_payment_transaction.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 