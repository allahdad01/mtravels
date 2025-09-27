<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
require_once('../includes/db.php');

// Validate payment_currency
$payment_currency = isset($_POST['payment_currency']) ? DbSecurity::validateInput($_POST['payment_currency'], 'currency') : null;

// Validate payment_amount
$payment_amount = isset($_POST['payment_amount']) ? DbSecurity::validateInput($_POST['payment_amount'], 'float', ['min' => 0]) : null;

// Validate payment_description
$payment_description = isset($_POST['payment_description']) ? DbSecurity::validateInput($_POST['payment_description'], 'string', ['maxlength' => 255]) : null;

// Validate payment_date
$payment_date = isset($_POST['payment_date']) ? DbSecurity::validateInput($_POST['payment_date'], 'date') : null;

// Validate booking_id
$booking_id = isset($_POST['booking_id']) ? DbSecurity::validateInput($_POST['booking_id'], 'int', ['min' => 0]) : null;

// Validate payment_exchange_rate (optional)
$payment_exchange_rate = isset($_POST['payment_exchange_rate']) ? DbSecurity::validateInput($_POST['payment_exchange_rate'], 'float', ['min' => 0]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $booking_id = intval($_POST['booking_id']);
        $payment_date = $_POST['payment_date'];
        $description = $_POST['payment_description'];
        $amount = floatval($_POST['payment_amount']);
        $currency = $_POST['payment_currency'];
        $exchange_rate = isset($_POST['payment_exchange_rate']) ? floatval($_POST['payment_exchange_rate']) : null;

        // Append exchange rate to description if provided
        if ($exchange_rate) {
            $description .= " (Exchange Rate: {$exchange_rate})";
        }
        // Get booking details
        $stmt = $pdo->prepare("SELECT paid_to, title, passenger_name, pnr FROM ticket_reservations WHERE id = ?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            throw new Exception("Booking not found");
        }

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
        $stmt->execute([$booking['paid_to'], $tenant_id]);
        $balanceResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $newBalance = $balanceResult['current_balance'] + $amount;

        // Begin transaction
        $pdo->beginTransaction();

        // Update main account balance
        $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$newBalance, $booking['paid_to'], $tenant_id]);

        // Insert transaction record
        $stmt = $pdo->prepare("INSERT INTO main_account_transactions 
            (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, created_at, tenant_id, exchange_rate)
            VALUES (?, 'credit', ?, ?, ?, 'ticket_reserve', ?, ?, ?, ?, ?)");
        $stmt->execute([
            $booking['paid_to'],
            $amount,
            $currency,
            $description,
            $booking_id,
            $newBalance,
            $payment_date,
            $tenant_id,
            $exchange_rate
        ]);

        // Get the last inserted ID using PDO's lastInsertId()
        $transaction_id = $pdo->lastInsertId();

        // Create notification
        $notificationMessage = sprintf(
            "New payment received for ticket booking #%s - %s %s: Amount %s %.2f",
            $booking['pnr'],
            $booking['title'],
            $booking['passenger_name'],
            $currency,
            $amount
        );

        $notifStmt = $pdo->prepare("
            INSERT INTO notifications 
            (transaction_id, transaction_type, message, status, created_at, tenant_id) 
            VALUES (?, 'ticket_reserve', ?, 'Unread', NOW(), ?)
        ");
        
        if (!$notifStmt->execute([$transaction_id, $notificationMessage, $tenant_id])) {
            throw new Exception("Failed to create notification");
        }

        // Commit transaction
        $pdo->commit();
        
        // Log the activity
        $old_values = json_encode([]);
        $new_values = json_encode([
            'booking_id' => $booking_id,
            'payment_date' => $payment_date,
            'description' => $description,
            'amount' => $amount,
            'currency' => $currency,
            'exchange_rate' => $exchange_rate,
            'main_account_id' => $booking['paid_to']
        ]);
        
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $activityStmt = $pdo->prepare("
            INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
            VALUES (?, 'add', 'main_account_transactions', ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $activityStmt->execute([$user_id, $transaction_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id]);
        
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in add_ticket_reserve_payment.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
} 