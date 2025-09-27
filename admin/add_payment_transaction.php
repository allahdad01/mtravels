<?php
// Include database security module for input validation
require_once 'includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/conn.php';

// Validate receipt
$receipt = isset($_POST['receipt']) ? DbSecurity::validateInput($_POST['receipt'], 'string', ['maxlength' => 255]) : null;

// Validate description
$description = isset($_POST['description']) ? DbSecurity::validateInput($_POST['description'], 'string', ['maxlength' => 255]) : null;

// Validate currency
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : null;

// Validate amount
$amount = isset($_POST['amount']) ? DbSecurity::validateInput($_POST['amount'], 'float', ['min' => 0]) : null;

// Validate type
$type = isset($_POST['type']) ? DbSecurity::validateInput($_POST['type'], 'string', ['maxlength' => 255]) : null;

// Validate main_account_id
$main_account_id = isset($_POST['main_account_id']) ? DbSecurity::validateInput($_POST['main_account_id'], 'int', ['min' => 0]) : null;

// Validate payment_id
$payment_id = isset($_POST['payment_id']) ? DbSecurity::validateInput($_POST['payment_id'], 'int', ['min' => 0]) : null;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_id = $_POST['payment_id'];
    $main_account_id = $_POST['main_account_id'];
    $type = $_POST['type'];
    $amount = $_POST['amount'];
    $currency = $_POST['currency'];
    $description = $_POST['description'];
    $receipt = $_POST['receipt'];
    
    // Begin transaction
    $conn->begin_transaction();
    try {
        // Insert into main_account_transactions
        $stmt = $conn->prepare("INSERT INTO main_account_transactions (type, amount, currency, description, main_account_id, reference_id, transaction_type, receipt_number, created_by) VALUES (?, ?, ?, ?, ?, ?, 'additional_payment_transaction', ?, ?)");
        $stmt->bind_param("sdssissi", $type, $amount, $currency, $description, $main_account_id, $payment_id, $receipt, $_SESSION['user_id']);
        $stmt->execute();
        
        // Update main account balance
        if ($type === 'credit') {
            if ($currency === 'USD') {
                $sql = "UPDATE main_account SET usd_balance = usd_balance + ? WHERE id = ?";
            } else {
                $sql = "UPDATE main_account SET afs_balance = afs_balance + ? WHERE id = ?";
            }
        } else {
            if ($currency === 'USD') {
                $sql = "UPDATE main_account SET usd_balance = usd_balance - ? WHERE id = ?";
            } else {
                $sql = "UPDATE main_account SET afs_balance = afs_balance - ? WHERE id = ?";
            }
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("di", $amount, $main_account_id);
        $stmt->execute();
        
        $conn->commit();
        
        // Log the activity
        $old_values = json_encode([]);
        $new_values = json_encode([
            'payment_id' => $payment_id,
            'main_account_id' => $main_account_id,
            'type' => $type,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'receipt' => $receipt
        ]);
        
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt_log = $conn->prepare("
            INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
            VALUES (?, 'add', 'main_account_transactions', ?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        // Get transaction ID
        $transaction_id = $stmt->insert_id ?? 0;
        
        $stmt_log->bind_param("iissssi", $user_id, $transaction_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
        $stmt_log->execute();
        $stmt_log->close();
        
        $_SESSION['success'] = "Transaction added successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error adding transaction: " . $e->getMessage();
    }
    
    header("Location: additional_payments.php");
    exit();
}
?> 