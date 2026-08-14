<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once('../../includes/db.php');

// Set proper headers for JSON response
header('Content-Type: application/json');

// Turn off error reporting to prevent HTML/text from mixing with JSON
error_reporting(0);

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

// Validate exchange_rate (optional)
$exchange_rate = isset($_POST['exchange_rate']) ? DbSecurity::validateInput($_POST['exchange_rate'], 'float', ['min' => 0]) : null;

// Validate receipt number (optional)
$receipt_number = isset($_POST['receipt_number']) ? DbSecurity::validateInput($_POST['receipt_number'], 'string', ['maxlength' => 100]) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $booking_id = intval($_POST['booking_id']);
        $payment_date = $_POST['payment_date'];
        $description = $_POST['payment_description'];
        $amount = floatval($_POST['payment_amount']);
        $currency = $_POST['payment_currency'];
        $exchange_rate = isset($_POST['exchange_rate']) ? floatval($_POST['exchange_rate']) : null;
        $receipt_number = trim((string) $receipt_number);

        // Get booking details including client type
        $stmt = $pdo->prepare("
            SELECT rt.paid_to, rt.sold_to, rt.title, rt.passenger_name, rt.pnr,
                   LOWER(c.client_type) as client_type
            FROM refunded_tickets rt
            LEFT JOIN clients c ON rt.sold_to = c.id
            WHERE rt.id = ? AND rt.tenant_id = ? AND rt.branch_id = ?
        ");
        $stmt->execute([$booking_id, $tenant_id, $branch_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            throw new Exception("Booking not found");
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Initialize newBalance
        $newBalance = 0;

        // Only deduct from main account if client is an agency
        if ($booking['client_type'] === 'agency') {
            // Get current balance
            $balanceFields = [
                'USD' => 'usd_balance',
                'AFS' => 'afs_balance',
                'EUR' => 'euro_balance',
                'DARHAM' => 'darham_balance',
                'SAR' => 'sar_balance',
            ];
            $balanceField = $balanceFields[$currency] ?? null;
            if (!$balanceField) {
                throw new Exception("Unsupported currency: $currency");
            }
            $stmt = $pdo->prepare("SELECT $balanceField as current_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->execute([$booking['paid_to'], $tenant_id, $branch_id]);
            $balanceResult = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculate new balance (deducting for agency)
            $newBalance = $balanceResult['current_balance'] - $amount;

            // Update main account balance
            $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->execute([$newBalance, $booking['paid_to'], $tenant_id, $branch_id]);
        }

        // Insert transaction record (for both types, but only agency affects balance)
        $stmt = $pdo->prepare("INSERT INTO main_account_transactions
            (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, created_at, tenant_id, branch_id, created_by, exchange_rate, receipt)
            VALUES (?, 'debit', ?, ?, ?, 'ticket_refund', ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $booking['paid_to'],
            $amount,
            $currency,
            $description,
            $booking_id,
            $newBalance, // Will be 0 for regular clients
            $payment_date,
            $tenant_id,
            $branch_id,
            $_SESSION['user_id'] ?? null,
            $exchange_rate,
            $receipt_number
        ]);

        // Get the last inserted ID using PDO's lastInsertId()
        $transaction_id = $pdo->lastInsertId();

        // Create notification with client type info
        $notificationMessage = sprintf(
            "Refund payment for %s client ticket #%s - %s %s: Amount %s %.2f%s",
            ucfirst($booking['client_type']),
            $booking['pnr'],
            $booking['title'],
            $booking['passenger_name'],
            $currency,
            $amount,
            $receipt_number !== '' ? " | Receipt: {$receipt_number}" : ''
        );

        $notifStmt = $pdo->prepare("
            INSERT INTO notifications
            (transaction_id, transaction_type, message, status, created_at, tenant_id, branch_id)
            VALUES (?, 'ticket_refund', ?, 'Unread', NOW(), ?, ?)
        ");

        if (!$notifStmt->execute([$transaction_id, $notificationMessage, $tenant_id, $branch_id])) {
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
            'receipt_number' => $receipt_number,
            'client_type' => $booking['client_type'],
            'main_account_id' => $booking['paid_to']
        ]);
        
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $activityStmt = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'add', 'main_account_transactions', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $activityStmt->execute([$user_id, $transaction_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);
        
        // Clean output buffer before sending JSON
        if (ob_get_level()) {
            ob_clean();
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Transaction added successfully',
            'transaction_id' => $transaction_id
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Clean output buffer before sending JSON
        if (ob_get_level()) {
            ob_clean();
        }
        
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    // Clean output buffer before sending JSON
    if (ob_get_level()) {
        ob_clean();
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Ensure no additional output after JSON
exit();
?>