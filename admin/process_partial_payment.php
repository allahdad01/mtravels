<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once 'includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

require_once('../includes/db.php');
require_once('../includes/conn.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get POST data
$client_id = $_POST['client_id'] ?? null;
$usd_amount = floatval($_POST['usd_amount'] ?? 0);
$afs_amount = floatval($_POST['afs_amount'] ?? 0);
$main_account_id = $_POST['main_account'] ?? null;
$receipt_number = $_POST['receipt_number'] ?? '';
$remarks = $_POST['remarks'] ?? '';

// Validate required fields
if (!$client_id || !$main_account_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Validate remarks
$remarks = isset($_POST['remarks']) ? DbSecurity::validateInput($_POST['remarks'], 'string', ['maxlength' => 255]) : null;

// Validate receipt_number
$receipt_number = isset($_POST['receipt_number']) ? DbSecurity::validateInput($_POST['receipt_number'], 'string', ['maxlength' => 255]) : null;

// Validate main_account
$main_account = isset($_POST['main_account']) ? DbSecurity::validateInput($_POST['main_account'], 'string', ['maxlength' => 255]) : null;

// Validate afs_amount
$afs_amount = isset($_POST['afs_amount']) ? DbSecurity::validateInput($_POST['afs_amount'], 'float', ['min' => 0]) : null;

// Validate usd_amount
$usd_amount = isset($_POST['usd_amount']) ? DbSecurity::validateInput($_POST['usd_amount'], 'float', ['min' => 0]) : null;

// Validate client_id
$client_id = isset($_POST['client_id']) ? DbSecurity::validateInput($_POST['client_id'], 'int', ['min' => 0]) : null;

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Get current exchange rate
    $exchange_rate_query = "SELECT usd_to_afs_rate FROM settings WHERE tenant_id = ?";
    $exchange_result = $conn->query($exchange_rate_query, $tenant_id);
    $exchange_rate = $exchange_result->fetch_assoc()['usd_to_afs_rate'];
    
    // Get client's current balances
    $client_query = "SELECT usd_balance, afs_balance FROM clients WHERE id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($client_query);
    $stmt->bind_param("ii", $client_id, $tenant_id);
    $stmt->execute();
    $client_result = $stmt->get_result();
    $client = $client_result->fetch_assoc();
    
    // Get main account balances
    $main_account_query = "SELECT usd_balance, afs_balance FROM main_account WHERE id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($main_account_query);
    $stmt->bind_param("ii", $main_account_id, $tenant_id);
    $stmt->execute();
    $main_account_result = $stmt->get_result();
    $main_account = $main_account_result->fetch_assoc();
    
    // Calculate total payment in USD
    $afs_in_usd = $afs_amount / $exchange_rate;
    $total_usd_payment = $usd_amount + $afs_in_usd;
    
    // Update client balances
    $new_usd_balance = $client['usd_balance'] + $total_usd_payment;
    $new_afs_balance = $client['afs_balance'] + $afs_amount;
    
    $update_client_query = "UPDATE clients SET 
                           usd_balance = ?, 
                           afs_balance = ?,
                           updated_at = NOW() 
                           WHERE id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($update_client_query);
    $stmt->bind_param("ddii", $new_usd_balance, $new_afs_balance, $client_id, $tenant_id);
    $stmt->execute();
    
    // Update main account balances
    $new_main_usd_balance = $main_account['usd_balance'] - $usd_amount;
    $new_main_afs_balance = $main_account['afs_balance'] - $afs_amount;
    
    $update_main_query = "UPDATE main_account SET 
                         usd_balance = ?, 
                         afs_balance = ?,
                         updated_at = NOW() 
                         WHERE id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($update_main_query);
    $stmt->bind_param("ddii", $new_main_usd_balance, $new_main_afs_balance, $main_account_id, $tenant_id);
    $stmt->execute();
    
    // Record USD transaction
    $transaction_query = "INSERT INTO transactions (
        client_id, 
        main_account_id, 
        amount, 
        currency, 
        type, 
        transaction_of, 
        receipt_number, 
        remarks, 
        created_at,
        tenant_id
    ) VALUES (?, ?, ?, 'USD', 'credit', 'payment', ?, ?, NOW(), ?)";
    
    $stmt = $conn->prepare($transaction_query);
    $stmt->bind_param("iidssi", $client_id, $main_account_id, $usd_amount, $receipt_number, $remarks, $tenant_id);
    $stmt->execute();
    
    // Record AFS transaction if amount is greater than 0
    if ($afs_amount > 0) {
        $transaction_query = "INSERT INTO transactions (
            client_id, 
            main_account_id, 
            amount, 
            currency, 
            type, 
            transaction_of, 
            receipt_number, 
            remarks, 
            created_at,
            tenant_id
        ) VALUES (?, ?, ?, 'AFS', 'credit', 'payment', ?, ?, NOW(), ?)";
        
        $stmt = $conn->prepare($transaction_query);
        $stmt->bind_param("iidssi", $client_id, $main_account_id, $afs_amount, $receipt_number, $remarks, $tenant_id);
        $stmt->execute();
    }
    
    // Add activity logging
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Prepare new values data
    $new_values = [
        'client_id' => $client_id,
        'main_account_id' => $main_account_id,
        'usd_amount' => $usd_amount,
        'afs_amount' => $afs_amount,
        'receipt_number' => $receipt_number,
        'remarks' => $remarks,
        'new_usd_balance' => $new_usd_balance,
        'new_afs_balance' => $new_afs_balance
    ];
    
    // Insert activity log
    $activity_log_stmt = $conn->prepare("INSERT INTO activity_log 
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
        VALUES (?, 'add', 'transactions', ?, '{}', ?, ?, ?, NOW(), ?)");
    
    $new_values_json = json_encode($new_values);
    $transaction_id = $stmt->insert_id; // Get the last inserted transaction ID
    $activity_log_stmt->bind_param("iisssi", $user_id, $transaction_id, $new_values_json, $ip_address, $user_agent, $tenant_id);
    $activity_log_stmt->execute();
    $activity_log_stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'new_balances' => [
            'usd' => $new_usd_balance,
            'afs' => $new_afs_balance
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error processing payment: ' . $e->getMessage()
    ]);
}
?> 