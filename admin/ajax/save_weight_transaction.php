<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and security
require_once '../../includes/conn.php';
require_once '../includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];

// Get and validate POST data
$weightId = isset($_POST['weight_id']) ? DbSecurity::validateInput($_POST['weight_id'], 'int', ['min' => 0]) : 0;
$amount = isset($_POST['amount']) ? DbSecurity::validateInput($_POST['amount'], 'float', ['min' => 0]) : 0;
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : '';
$exchangeRate = isset($_POST['exchange_rate']) ? DbSecurity::validateInput($_POST['exchange_rate'], 'float', ['min' => 0]) : null;
$transactionDate = isset($_POST['transaction_date']) ? DbSecurity::validateInput($_POST['transaction_date'], 'string', ['maxlength' => 19]) : '';
$remarks = isset($_POST['remarks']) ? DbSecurity::validateInput($_POST['remarks'], 'string', ['maxlength' => 255]) : '';

// Exchange rate is now stored in separate column, no need to append to remarks

// Validate input
if ($weightId <= 0) {
    die(json_encode([
        'success' => false,
        'message' => 'Invalid weight ID'
    ]));
}

if ($amount <= 0) {
    die(json_encode([
        'success' => false,
        'message' => 'Amount must be greater than zero'
    ]));
}


if (empty($transactionDate)) {
    die(json_encode([
        'success' => false,
        'message' => 'Transaction date is required'
    ]));
}

// Start transaction
$conn->begin_transaction();

try {
    // First get weight and ticket details
    $weightCheck = $conn->prepare("
        SELECT tw.*, t.passenger_name, t.pnr, t.paid_to, t.title
        FROM ticket_weights tw
        JOIN ticket_bookings t ON tw.ticket_id = t.id
        WHERE tw.id = ? AND tw.tenant_id = ?
    ");
    $weightCheck->bind_param('ii', $weightId, $tenant_id);
    $weightCheck->execute();
    $weightResult = $weightCheck->get_result();
    $weight = $weightResult->fetch_assoc();
    
    if (!$weight) {
        throw new Exception('Weight not found');
    }

    // Get current balance from main account
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
        case 'DARHAM': // if you keep this naming
            $balanceField = 'darham_balance';
            break;
        default:
            throw new Exception("Unsupported currency: $currency");
    }
    
    $balanceCheck = $conn->prepare("SELECT $balanceField as current_balance FROM main_account WHERE id = ? AND tenant_id = ?");
    $balanceCheck->bind_param('ii', $weight['paid_to'], $tenant_id);
    $balanceCheck->execute();
    $balanceResult = $balanceCheck->get_result();
    $balance = $balanceResult->fetch_assoc();
    $newBalance = $balance['current_balance'] + $amount;

    // Update main account balance
    $updateBalance = $conn->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ?");
    $updateBalance->bind_param('did', $newBalance, $weight['paid_to'], $tenant_id);
    if (!$updateBalance->execute()) {
        throw new Exception('Failed to update account balance');
    }

    // Insert main account transaction
    $mainTransaction = $conn->prepare("
        INSERT INTO main_account_transactions
        (main_account_id, type, amount, currency, exchange_rate, description, transaction_of, reference_id, balance, created_at, tenant_id)
        VALUES (?, 'credit', ?, ?, ?, ?, 'weight', ?, ?, ?, ?)
    ");
    $mainTransaction->bind_param(
        'idsssidsi',
        $weight['paid_to'],
        $amount,
        $currency,
        $exchangeRate,
        $remarks,
        $weightId,
        $newBalance,
        $transactionDate,
        $tenant_id
    );
    if (!$mainTransaction->execute()) {
        throw new Exception('Failed to save transaction');
    }
    $transactionId = $mainTransaction->insert_id;

    // Create notification
    $notificationMessage = sprintf(
        "New payment received for weight charge #%s - %s %s: Amount %s %.2f",
        $weight['pnr'],
        $weight['title'],
        $weight['passenger_name'],
        $currency,
        $amount
    );

    $notification = $conn->prepare("
        INSERT INTO notifications 
        (transaction_id, transaction_type, message, status, created_at, tenant_id) 
        VALUES (?, 'weight', ?, 'Unread', NOW(), ?)
    ");
    if (!$notification->execute([$transactionId, $notificationMessage, $tenant_id])) {

        throw new Exception('Failed to create notification');
    }

    // Log the activity
    $user_id = $_SESSION["user_id"] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Prepare activity log data
    $new_values = json_encode([
        'weight_id' => $weightId,
        'amount' => $amount,
        'currency' => $currency,
        'exchange_rate' => $exchangeRate,
        'transaction_date' => $transactionDate,
        'remarks' => $remarks,
        'main_account_id' => $weight['paid_to'],
        'balance' => $newBalance
    ]);
    
    // Insert activity log
    $activityLog = $conn->prepare("
        INSERT INTO activity_log 
        (user_id, tenant_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at) 
        VALUES (?, ?, 'create', 'main_account_transactions', ?, NULL, ?, ?, ?, NOW())
    ");
    
    $activityLog->bind_param("iisssi", $user_id, $tenant_id, $transactionId, $new_values, $ip_address, $user_agent);
    
    if (!$activityLog->execute()) {
        throw new Exception('Failed to log activity');
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Transaction saved successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    error_log("Error in save_weight_transaction.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close connections
if (isset($weightCheck)) $weightCheck->close();
if (isset($balanceCheck)) $balanceCheck->close();
if (isset($updateBalance)) $updateBalance->close();
if (isset($mainTransaction)) $mainTransaction->close();
if (isset($notification)) $notification->close();
if (isset($activityLog)) $activityLog->close();
$conn->close(); 