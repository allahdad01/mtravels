<?php
session_start();
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();



require_once('../includes/db.php');

// Validate original_transaction_id
$exchange_rate = isset($_POST['exchange_rate']) ? DbSecurity::validateInput($_POST['exchange_rate'], 'float', ['min' => 0]) : null;

// Validate is_refund
$is_refund = isset($_POST['is_refund']) ? DbSecurity::validateInput($_POST['is_refund'], 'string', ['maxlength' => 255]) : null;

// Validate currency
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : null;

// Validate payment_currency
$payment_currency = isset($_POST['payment_currency']) ? DbSecurity::validateInput($_POST['payment_currency'], 'currency') : null;

// Validate payment_amount
$payment_amount = isset($_POST['payment_amount']) ? DbSecurity::validateInput($_POST['payment_amount'], 'float', ['min' => 0]) : null;

// Validate payment_description
$payment_description = isset($_POST['payment_description']) ? DbSecurity::validateInput($_POST['payment_description'], 'string', ['maxlength' => 255]) : null;

// Validate payment_time
$payment_time = isset($_POST['payment_time']) ? DbSecurity::validateInput($_POST['payment_time'], 'string', ['maxlength' => 255]) : null;

// Validate payment_date
$payment_date = isset($_POST['payment_date']) ? DbSecurity::validateInput($_POST['payment_date'], 'date') : null;

// Validate visa_id
$visa_id = isset($_POST['visa_id']) ? DbSecurity::validateInput($_POST['visa_id'], 'int', ['min' => 0]) : null;

$username = isset($_SESSION["name"]) ? $_SESSION["name"] : "Unknown User";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $visa_id = intval($_POST['visa_id']);
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $payment_time = $_POST['payment_time'] ?? date('H:i:s');
        $payment_datetime = $payment_date . ' ' . $payment_time;
        $payment_description = $_POST['payment_description'] ?? '';
        $payment_amount = floatval($_POST['payment_amount']);
        $currency = $_POST['payment_currency'] ?? $_POST['currency'];
        
        // Check if this is a refund transaction
        $is_refund = isset($_POST['is_refund']) && $_POST['is_refund'] === 'true';
        $exchange_rate = isset($_POST['exchange_rate']) ? floatval($_POST['exchange_rate']) : null;
    
        // Get visa and supplier details
        $visaStmt = $pdo->prepare("
            SELECT 
                va.id AS visa_id, 
                va.applicant_name,
                va.base,
                va.sold,
                va.paid_to,
                s.name AS supplier_name,
                s.id AS supplier_id 
            FROM visa_applications va
            LEFT JOIN suppliers s ON va.supplier = s.id
            WHERE va.id = ?
        ");
        
        if (!$visaStmt->execute([$visa_id])) {
            throw new Exception("Failed to fetch visa details");
        }

        $visa = $visaStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$visa) {
            throw new Exception("Visa application with ID $visa_id not found");
        }


        // Validate paid_to value
        if (!isset($visa['paid_to']) || empty($visa['paid_to'])) {
            throw new Exception("Invalid paid_to value for visa application");
        }

        // Get current balance
        $balanceField = $currency === 'USD' ? 'usd_balance' : ($currency === 'AFS' ? 'afs_balance' : 
                        ($currency === 'EUR' ? 'euro_balance' : 'darham_balance'));
                        
        $stmt = $pdo->prepare("SELECT $balanceField as current_balance FROM main_account WHERE id = ?");
        $stmt->execute([$visa['paid_to']]);
        $balanceResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$balanceResult) {
            throw new Exception("Main account not found");
        }
        
        $newBalance = $balanceResult['current_balance'] + $payment_amount;

        // Start transaction
        $pdo->beginTransaction();

        // Update main account balance
        $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ?");
        $stmt->execute([$newBalance, $visa['paid_to']]);

        // Determine transaction type based on refund status or amount sign
        $transaction_type = ($payment_amount < 0 || $is_refund) ? 'debit' : 'credit';

        // Insert transaction record in main_account_transactions
        $stmt = $pdo->prepare("INSERT INTO main_account_transactions 
            (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, exchange_rate, created_at, tenant_id)
            VALUES (?, ?, ?, ?, ?, 'visa_sale', ?, ?, ?, ?, ?)");
        $stmt->execute([
            $visa['paid_to'],
            $transaction_type,
            abs($payment_amount), // Store absolute amount
            $currency,
            $payment_description,
            $visa_id,
            $newBalance,
            $exchange_rate,
            $payment_datetime,
            $tenant_id
        ]);

        // Get the last inserted ID for main account transaction
        $main_transaction_id = $pdo->lastInsertId();

        // Create appropriate notification message based on transaction type
        if ($is_refund) {
            $notification_message = sprintf(
                "Refund processed for visa application #%s - %s: Amount %s %.2f",
                $visa_id,
                $visa['applicant_name'],
                $currency,
                abs($payment_amount)
            );
        } else {
            $notification_message = sprintf(
                "New payment received for visa application #%s - %s: Amount %s %.2f",
                $visa_id,
                $visa['applicant_name'],
                $currency,
                abs($payment_amount)
            );
        }

        $notifStmt = $pdo->prepare("
            INSERT INTO notifications 
            (transaction_id, transaction_type, message, recipient_role, status, created_at, tenant_id) 
            VALUES (?, 'visa', ?, 'admin', 'unread', NOW(), ?)
        ");
        
        if (!$notifStmt->execute([$main_transaction_id, $notification_message, $tenant_id])) {
            throw new Exception("Failed to create notification");
        }

        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => $is_refund ? 'Refund processed successfully' : 'Transaction added successfully',
            'transaction_id' => $main_transaction_id
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in add_visa_transaction.php: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
