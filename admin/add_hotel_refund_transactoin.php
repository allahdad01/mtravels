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

// Validate refund_id
$refund_id = isset($_POST['refund_id']) ? DbSecurity::validateInput($_POST['refund_id'], 'int', ['min' => 0]) : null;

// Validate main_account_id
$main_account_id = isset($_POST['main_account_id']) ? DbSecurity::validateInput($_POST['main_account_id'], 'int', ['min' => 0]) : null;
$payment_exchange_rate = isset($_POST['exchange_rate']) ? DbSecurity::validateInput($_POST['exchange_rate'], 'float', ['min' => 0]) : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $refund_id = intval($_POST['refund_id']);
        $main_account_id = intval($_POST['main_account_id']);
        $payment_date = $_POST['payment_date'] . ' ' . ($_POST['payment_time'] ?? '00:00:00');
        $description = $_POST['payment_description'];
        $amount = floatval($_POST['payment_amount']);
        $currency = $_POST['payment_currency'];

        // Get refund details including visa application info
        $stmt = $pdo->prepare("
            SELECT r.*, h.first_name, h.last_name, h.order_id,
                   LOWER(c.client_type) as client_type, h.sold_to
            FROM hotel_refunds r
            JOIN hotel_bookings h ON r.booking_id = h.id
            LEFT JOIN clients c ON h.sold_to = c.id
            WHERE r.id = ?
        ");
        $stmt->execute([$refund_id]);
        $refund = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$refund) {
            throw new Exception("Refund record not found");
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Initialize newBalance
        $newBalance = 0;

        // Only deduct from main account if client is an agency
        if ($refund['client_type'] === 'agency') {
            // Get current balance
            $balanceField = $currency === 'USD' ? 'usd_balance' : 'afs_balance';
            $stmt = $pdo->prepare("SELECT $balanceField as current_balance FROM main_account WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$main_account_id, $tenant_id]);
            $balanceResult = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$balanceResult) {
                throw new Exception("Main account not found");
            }
            
            // Calculate new balance (deducting for agency)
            $newBalance = $balanceResult['current_balance'] - $amount;

            // Update main account balance
            $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$newBalance, $main_account_id, $tenant_id]);
        }

        // For refunds, amount should be negative in transaction record
        $transactionAmount = abs($amount);

        // Insert transaction record
        $stmt = $pdo->prepare("INSERT INTO main_account_transactions 
            (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, created_at, tenant_id, exchange_rate)
            VALUES (?, 'debit', ?, ?, ?, 'hotel_refund', ?, ?, ?, ?, ?)");
        $stmt->execute([
            $main_account_id,
            $transactionAmount,
            $currency,
            $description,
            $refund_id,
            $newBalance,
            $payment_date,
            $tenant_id,
            $payment_exchange_rate
        ]);

        // Get the last inserted ID
        $transaction_id = $pdo->lastInsertId();

        // Create notification with client type info
        $notificationMessage = sprintf(
            "Hotel refund payment for %s client - %s (%s) Amount %s %.2f",
            ucfirst($refund['client_type']),
            $refund['first_name'],
            $refund['last_name'],
            
            $currency,
            $amount
        );

        $notifStmt = $pdo->prepare("
            INSERT INTO notifications 
            (transaction_id, transaction_type, message, status, created_at, tenant_id) 
            VALUES (?, 'hotel_refund', ?, 'Unread', NOW(), ?)
        ");
        
        if (!$notifStmt->execute([$transaction_id, $notificationMessage, $tenant_id])) {
            throw new Exception("Failed to create notification");
        }

        // Commit transaction
        $pdo->commit();
        
        // Log the activity
        $old_values = json_encode([]);
        $new_values = json_encode([
            'refund_id' => $refund_id,
            'payment_date' => $payment_date,
            'description' => $description,
            'amount' => $amount,
            'currency' => $currency,
            'client_type' => $refund['client_type'],
            'main_account_id' => $main_account_id,
            'first_name' => $refund['first_name'],
            'last_name' => $refund['last_name'],
            'order_id' => $refund['order_id'],
            
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
        
        echo json_encode([
            'success' => true,
            'client_type' => $refund['client_type'],
            'message' => 'Refund processed successfully'
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in add_visa_transaction.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
