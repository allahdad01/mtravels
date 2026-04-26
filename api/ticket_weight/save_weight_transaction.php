<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and security
require_once '../../includes/db.php';
require_once '../../admin/includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Get and validate POST data
$weightId = isset($_POST['weight_id']) ? DbSecurity::validateInput($_POST['weight_id'], 'int', ['min' => 0]) : 0;
$amount = isset($_POST['amount']) ? DbSecurity::validateInput($_POST['amount'], 'float', ['min' => 0]) : 0;
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : '';
$exchangeRate = isset($_POST['exchange_rate']) ? DbSecurity::validateInput($_POST['exchange_rate'], 'float', ['min' => 0]) : null;
$transactionDate = isset($_POST['transaction_date']) ? DbSecurity::validateInput($_POST['transaction_date'], 'string', ['maxlength' => 19]) : '';
$remarks = isset($_POST['remarks']) ? DbSecurity::validateInput($_POST['remarks'], 'string', ['maxlength' => 255]) : '';
$receipt_number = isset($_POST['receipt_number']) ? DbSecurity::validateInput($_POST['receipt_number'], 'string', ['maxlength' => 100]) : '';
$receipt_number = trim((string) $receipt_number);

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
$pdo->beginTransaction();

try {
    // First get weight and ticket details
    $weightCheck = $pdo->prepare("
        SELECT tw.*, t.passenger_name, t.pnr, t.paid_to, t.title
        FROM ticket_weights tw
        JOIN ticket_bookings t ON tw.ticket_id = t.id
        WHERE tw.id = ? AND tw.tenant_id = ? And tw.branch_id = ?
    ");
    $weightCheck->bindParam(1, $weightId, PDO::PARAM_INT);
    $weightCheck->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $weightCheck->bindParam(3, $branch_id, PDO::PARAM_INT);
    $weightCheck->execute();
    $weight = $weightCheck->fetch(PDO::FETCH_ASSOC);

    if (!$weight) {
        throw new PDOException('Weight not found');
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
            throw new PDOException("Unsupported currency: $currency");
    }

    $balanceCheck = $pdo->prepare("SELECT $balanceField as current_balance FROM main_account WHERE id = ? AND tenant_id = ? And branch_id = ?");
    $balanceCheck->bindParam(1, $weight['paid_to'], PDO::PARAM_INT);
    $balanceCheck->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $balanceCheck->bindParam(3, $branch_id, PDO::PARAM_INT);
    $balanceCheck->execute();
    $balance = $balanceCheck->fetch(PDO::FETCH_ASSOC);
    $newBalance = $balance['current_balance'] + $amount;

    // Update main account balance
    $updateBalance = $pdo->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ? And branch_id = ?");
    $updateBalance->bindParam(1, $newBalance, PDO::PARAM_STR);
    $updateBalance->bindParam(2, $weight['paid_to'], PDO::PARAM_INT);
    $updateBalance->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $updateBalance->bindParam(4, $branch_id, PDO::PARAM_INT);
    if (!$updateBalance->execute()) {
        throw new PDOException('Failed to update account balance');
    }

    // Insert main account transaction
    $mainTransaction = $pdo->prepare("
        INSERT INTO main_account_transactions
        (main_account_id, type, amount, currency, exchange_rate, description, transaction_of, reference_id, balance, created_at, tenant_id, branch_id, receipt)
        VALUES (?, 'credit', ?, ?, ?, ?, 'weight', ?, ?, ?, ?, ?, ?)
    ");
    $mainTransaction->bindParam(1, $weight['paid_to'], PDO::PARAM_INT);
    $mainTransaction->bindParam(2, $amount, PDO::PARAM_STR);
    $mainTransaction->bindParam(3, $currency, PDO::PARAM_STR);
    $mainTransaction->bindParam(4, $exchangeRate, PDO::PARAM_STR);
    $mainTransaction->bindParam(5, $remarks, PDO::PARAM_STR);
    $mainTransaction->bindParam(6, $weightId, PDO::PARAM_INT);
    $mainTransaction->bindParam(7, $newBalance, PDO::PARAM_STR);
    $mainTransaction->bindParam(8, $transactionDate, PDO::PARAM_STR);
    $mainTransaction->bindParam(9, $tenant_id, PDO::PARAM_INT);
    $mainTransaction->bindParam(10, $branch_id, PDO::PARAM_INT);
    $mainTransaction->bindParam(11, $receipt_number, PDO::PARAM_STR);
    if (!$mainTransaction->execute()) {
        throw new PDOException('Failed to save transaction');
    }
    $transactionId = $pdo->lastInsertId();

    // Create notification
    $notificationMessage = sprintf(
        "New payment received for weight charge #%s - %s %s: Amount %s %.2f%s",
        $weight['pnr'],
        $weight['title'],
        $weight['passenger_name'],
        $currency,
        $amount,
        $receipt_number !== '' ? " | Receipt: {$receipt_number}" : ''
    );

    $notification = $pdo->prepare("
        INSERT INTO notifications
        (transaction_id, transaction_type, message, status, created_at, tenant_id, branch_id)
        VALUES (?, 'weight', ?, 'Unread', NOW(), ?, ?)
    ");
    $notification->bindParam(1, $transactionId, PDO::PARAM_INT);
    $notification->bindParam(2, $notificationMessage, PDO::PARAM_STR);
    $notification->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $notification->bindParam(4, $branch_id, PDO::PARAM_INT);
    if (!$notification->execute()) {
        throw new PDOException('Failed to create notification');
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
        'receipt_number' => $receipt_number,
        'main_account_id' => $weight['paid_to'],
        'balance' => $newBalance
    ]);

    // Insert activity log
    $activityLog = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, tenant_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, branch_id)
        VALUES (?, ?, 'create', 'main_account_transactions', ?, NULL, ?, ?, ?, NOW(), ?)
    ");

    $activityLog->bindParam(1, $user_id, PDO::PARAM_INT);
    $activityLog->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $activityLog->bindParam(3, $transactionId, PDO::PARAM_INT);
    $activityLog->bindParam(4, $new_values, PDO::PARAM_STR);
    $activityLog->bindParam(5, $ip_address, PDO::PARAM_STR);
    $activityLog->bindParam(6, $user_agent, PDO::PARAM_STR);
    $activityLog->bindParam(7, $branch_id, PDO::PARAM_INT);

    if (!$activityLog->execute()) {
        throw new PDOException('Failed to log activity');
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Transaction saved successfully'
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>