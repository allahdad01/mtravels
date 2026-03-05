<?php
require_once '../../includes/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id']; 


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
    $pdo->beginTransaction();
    
    // Prepare customer data
    $name = $_POST['name'];
    $email = $_POST['email'] ?? null;
    $phone = $_POST['phone'];
    $address = $_POST['address'] ?? null;
    
    // Insert customer data
    $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone, address, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $phone, $address, $tenant_id, $branch_id]);
    $customer_id = $pdo->lastInsertId();
    
    // Handle initial balance if provided
    if (isset($_POST['initial_balance']) && is_numeric($_POST['initial_balance']) && $_POST['initial_balance'] > 0) {
        $initial_balance = floatval($_POST['initial_balance']);
        $currency = $_POST['initial_currency'];
        
        // Create wallet with initial balance
        $stmt = $pdo->prepare("INSERT INTO customer_wallets (customer_id, currency, balance, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$customer_id, $currency, $initial_balance, $tenant_id, $branch_id]);
        
        // Record initial balance transaction
        $stmt = $pdo->prepare("INSERT INTO sarafi_transactions (customer_id, amount, currency, type, notes, tenant_id, branch_id) VALUES (?, ?, ?, 'deposit', 'Initial balance', ?, ?)");
        $stmt->execute([$customer_id, $initial_balance, $currency, $tenant_id, $branch_id]);
        $transaction_id = $pdo->lastInsertId();
        
        // Record in general ledger
        $stmt = $pdo->prepare("INSERT INTO general_ledger (transaction_id, account_type, entry_type, amount, currency, balance, tenant_id, branch_id) VALUES (?, 'asset', 'credit', ?, ?, ?, ?, ?)");
        $stmt->execute([$transaction_id, $initial_balance, $currency, $initial_balance, $tenant_id, $branch_id]);
    }
    
    $pdo->commit();
    $_SESSION['success_message'] = 'Customer created successfully!';
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = 'Error creating customer: ' . $e->getMessage();
}

header('Location: ../sarafi.php');
exit(); 