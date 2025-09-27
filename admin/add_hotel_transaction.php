<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
require_once('../includes/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $booking_id = intval($_POST['booking_id']);
        $payment_date = $_POST['payment_date'];
        $description = $_POST['payment_description'];
        $amount = floatval($_POST['payment_amount']);
        $currency = $_POST['payment_currency'];
        $exchange_rate = floatval($_POST['exchange_rate']) ? intval($_POST['exchange_rate']) : null;
        

        // Get booking details with better error handling
        $stmt = $pdo->prepare("
            SELECT hb.paid_to, hb.title, hb.first_name, hb.last_name, hb.order_id 
            FROM hotel_bookings hb 
            WHERE hb.id = ?
        ");
        
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            throw new Exception("Booking not found or invalid booking ID");
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Get current balance with error handling
        $balanceField = $currency === 'USD' ? 'usd_balance' : 'afs_balance';
        $stmt = $pdo->prepare("
            SELECT $balanceField as current_balance 
            FROM main_account 
            WHERE id = ? AND tenant_id = ?
        ");
        
        $stmt->execute([$booking['paid_to'], $tenant_id]);
        $balanceResult = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$balanceResult) {
            throw new Exception("Main account not found");
        }

        $newBalance = $balanceResult['current_balance'] + $amount;

        // Update main account balance
        $stmt = $pdo->prepare("
            UPDATE main_account 
            SET $balanceField = ? 
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$newBalance, $booking['paid_to'], $tenant_id]);

            // For regular transactions, don't include original_transaction_id

// Validate original_transaction_id
$original_transaction_id = isset($_POST['original_transaction_id']) ? DbSecurity::validateInput($_POST['original_transaction_id'], 'int', ['min' => 0]) : null;

// Validate is_refund
$is_refund = isset($_POST['is_refund']) ? DbSecurity::validateInput($_POST['is_refund'], 'string', ['maxlength' => 255]) : null;

// Validate payment_currency
$payment_currency = isset($_POST['payment_currency']) ? DbSecurity::validateInput($_POST['payment_currency'], 'currency') : null;

// Validate payment_amount
$payment_amount = isset($_POST['payment_amount']) ? DbSecurity::validateInput($_POST['payment_amount'], 'float', ['min' => 0]) : null;
$exchange_rate = isset($_POST['exchange_rate']) ? DbSecurity::validateInput($_POST['exchange_rate'], 'float', ['min' => 0]) : null;
// Validate payment_description
$payment_description = isset($_POST['payment_description']) ? DbSecurity::validateInput($_POST['payment_description'], 'string', ['maxlength' => 255]) : null;

// Validate payment_date
$payment_date = isset($_POST['payment_date']) ? DbSecurity::validateInput($_POST['payment_date'], 'date') : null;

// Validate booking_id
$booking_id = isset($_POST['booking_id']) ? DbSecurity::validateInput($_POST['booking_id'], 'int', ['min' => 0]) : null;
            $stmt = $pdo->prepare("
                INSERT INTO main_account_transactions 
                (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, created_at, tenant_id, exchange_rate)
                VALUES (?, ?, ?, ?, ?, 'hotel', ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $booking['paid_to'],
                'credit',
                abs($amount), // Store absolute amount
                $currency,
                $description,
                $booking_id,
                $newBalance,
                $payment_date,
                $tenant_id,
                $exchange_rate
            ]);
        

        // Get the last inserted ID for notification
        $main_transaction_id = $pdo->lastInsertId();

        
            $notificationMessage = sprintf(
                "New payment received for hotel booking #%s - %s %s %s: Amount %s %.2f",
                htmlspecialchars($booking['order_id']),
                htmlspecialchars($booking['title']),
                htmlspecialchars($booking['first_name']),
                htmlspecialchars($booking['last_name']),
                $currency,
                abs($amount)
            );
        

        $notifStmt = $pdo->prepare("
            INSERT INTO notifications 
            (transaction_id, transaction_type, message, status, created_at, tenant_id) 
            VALUES (?, 'hotel', ?, 'Unread', NOW(), ?)
        ");
        
        if (!$notifStmt->execute([$main_transaction_id, $notificationMessage, $tenant_id])) {
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
            'exchange_rate' => $exchange_rate
        ]);
        
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $activityStmt = $pdo->prepare("
            INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
            VALUES (?, 'add', 'main_account_transactions', ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $activityStmt->execute([$user_id, $main_transaction_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Transaction added successfully',
            'transaction_id' => $main_transaction_id
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in add_hotel_transaction.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
} 