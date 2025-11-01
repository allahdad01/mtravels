<?php
require_once '../../includes/conn.php';
require_once '../../includes/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];


// Validate input
if (!isset($_POST['name']) || empty(trim($_POST['name']))) {
    $_SESSION['error_message'] = 'Customer name is required';
    header('Location: ../sarafi.php');
    exit();
}

if (!isset($_POST['phone']) || empty(trim($_POST['phone']))) {
    $_SESSION['error_message'] = 'Phone number is required';
    header('Location: ../sarafi.php');
    exit();
}

try {
    $conn->begin_transaction();
    
    // Prepare customer data
    $name = $_POST['name'];
    $email = $_POST['email'] ?? null;
    $phone = $_POST['phone'];
    $address = $_POST['address'] ?? null;
    
    // Insert customer data
    $stmt = $conn->prepare("INSERT INTO customers (name, email, phone, address, tenant_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $name, $email, $phone, $address, $tenant_id);
    $stmt->execute();
    $customer_id = $conn->insert_id;
    
    // Handle initial balance if provided
    if (isset($_POST['initial_balance']) && is_numeric($_POST['initial_balance']) && $_POST['initial_balance'] > 0) {
        $initial_balance = floatval($_POST['initial_balance']);
        $currency = $_POST['initial_currency'];
        
        // Create wallet with initial balance
        $stmt = $conn->prepare("INSERT INTO customer_wallets (customer_id, currency, balance, tenant_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isdi", $customer_id, $currency, $initial_balance, $tenant_id);
        $stmt->execute();
        
        // Record initial balance transaction
        $stmt = $conn->prepare("INSERT INTO sarafi_transactions (customer_id, amount, currency, type, notes, tenant_id) VALUES (?, ?, ?, 'deposit', 'Initial balance', ?)");
        $stmt->bind_param("idsi", $customer_id, $initial_balance, $currency, $tenant_id);
        $stmt->execute();
        $transaction_id = $conn->insert_id;
        
        // Record in general ledger
        $stmt = $conn->prepare("INSERT INTO general_ledger (transaction_id, account_type, entry_type, amount, currency, balance, tenant_id) VALUES (?, 'asset', 'credit', ?, ?, ?, ?)");
        $stmt->bind_param("idsdi", $transaction_id, $initial_balance, $currency, $initial_balance, $tenant_id);
        $stmt->execute();
    }
    
    $conn->commit();
    $_SESSION['success_message'] = 'Customer created successfully!';
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = 'Error creating customer: ' . $e->getMessage();
}

header('Location: ../sarafi.php');
exit(); 