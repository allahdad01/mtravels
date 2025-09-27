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

// Validate exchange_rate
$exchange_rate = isset($_POST['exchange_rate']) ? DbSecurity::validateInput($_POST['exchange_rate'], 'float', ['min' => 0]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $refund_id = intval($_POST['refund_id']);
        $main_account_id = intval($_POST['main_account_id']);
        $payment_date = $_POST['payment_date'] . ' ' . ($_POST['payment_time'] ?? '00:00:00');
        $description = $_POST['payment_description'];
        $amount = floatval($_POST['payment_amount']);
        $currency = $_POST['payment_currency'];
        $exchange_rate = isset($_POST['exchange_rate']) ? floatval($_POST['exchange_rate']) : null;

        // Get refund details including visa application info
        $stmt = $pdo->prepare("
            SELECT r.*, v.applicant_name, v.passport_number, v.country,
                   LOWER(c.client_type) as client_type, v.sold_to
            FROM visa_refunds r
            JOIN visa_applications v ON r.visa_id = v.id
            LEFT JOIN clients c ON v.sold_to = c.id
            WHERE r.id = ? AND r.tenant_id = ?
        ");
        $stmt->execute([$refund_id, $tenant_id]);
        $refund = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$refund) {
            throw new Exception("Refund record not found");
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Initialize newBalance
        $newBalance = null;

        // Always get current balance for transaction record, but only deduct if client is an agency
        $balanceField = $currency === 'USD' ? 'usd_balance' : 'afs_balance';
        $stmt = $pdo->prepare("SELECT $balanceField as current_balance FROM main_account WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$main_account_id, $tenant_id]);
        $balanceResult = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$balanceResult) {
            throw new Exception("Main account not found");
        }

        // Only deduct from main account if client is an agency
        if ($refund['client_type'] === 'agency') {
            // Calculate new balance (deducting for agency)
            $newBalance = $balanceResult['current_balance'] - $amount;

            // Update main account balance
            $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$newBalance, $main_account_id, $tenant_id]);
        } else {
            // For non-agency clients, just store the current balance in transaction record
            $newBalance = $balanceResult['current_balance'];
        }

        // For refunds, amount should be negative in transaction record
        $transactionAmount = abs($amount);

        // Insert transaction record
        $stmt = $pdo->prepare("INSERT INTO main_account_transactions
            (main_account_id, type, amount, currency, exchange_rate, description, transaction_of, reference_id, balance, created_at, tenant_id)
            VALUES (?, 'debit', ?, ?, ?, ?, 'visa_refund', ?, ?, ?, ?)");
        $stmt->execute([
            $main_account_id,
            $transactionAmount,
            $currency,
            $exchange_rate,
            $description,
            $refund_id,
            $newBalance,
            $payment_date,
            $tenant_id
        ]);

        // Get the last inserted ID
        $transaction_id = $pdo->lastInsertId();

        // Create notification with client type info
        $notificationMessage = sprintf(
            "Visa refund payment for %s client - %s (%s) from %s: Amount %s %.2f",
            ucfirst($refund['client_type']),
            $refund['applicant_name'],
            $refund['passport_number'],
            $refund['country'],
            $currency,
            $amount
        );

        $notifStmt = $pdo->prepare("
            INSERT INTO notifications 
            (tenant_id, transaction_id, transaction_type, message, recipient_role, status, created_at) 
            VALUES (?, ?, 'visa_refund', ?, 'Admin', 'Unread', NOW())
        ");
       
        if (!$notifStmt->execute([$tenant_id, $transaction_id, $notificationMessage])) {
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
            'exchange_rate' => $exchange_rate,
            'client_type' => $refund['client_type'],
            'main_account_id' => $main_account_id,
            'applicant_name' => $refund['applicant_name'],
            'passport_number' => $refund['passport_number'],
            'country' => $refund['country']
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
        error_log("Error in process_visa_refund_transaction.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
