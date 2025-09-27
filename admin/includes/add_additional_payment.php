<?php


// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once '../includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

// Database connection
require_once('../../includes/db.php');
require_once('../../includes/conn.php');

try {
    // Validate and sanitize input
    $payment_type = DbSecurity::validateInput($_POST['payment_type'], 'string', ['maxlength' => 255]);
    $description = DbSecurity::validateInput($_POST['description'], 'string', ['maxlength' => 255]);
    $base_amount = DbSecurity::validateInput($_POST['base_amount'], 'float', ['min' => 0]);
    $profit = DbSecurity::validateInput($_POST['profit'], 'float');
    $sold_amount = DbSecurity::validateInput($_POST['sold_amount'], 'float', ['min' => 0]);
    $currency = DbSecurity::validateInput($_POST['currency'], 'currency');
    $main_account_id = DbSecurity::validateInput($_POST['main_account_id'], 'int', ['min' => 0]);
    $is_from_supplier = isset($_POST['is_from_supplier']) ? 1 : 0;
    $supplier_id = $is_from_supplier ? DbSecurity::validateInput($_POST['supplier_id'], 'int', ['min' => 0]) : null;
    $is_for_client = isset($_POST['is_for_client']) ? 1 : 0;
    $client_id = $is_for_client ? DbSecurity::validateInput($_POST['client_id'], 'int', ['min' => 0]) : null;

    // Begin transaction
    $conn->begin_transaction();

    // Insert the payment
    $stmt = $conn->prepare("INSERT INTO additional_payments (payment_type, description, base_amount, profit, sold_amount, currency, main_account_id, supplier_id, is_from_supplier, client_id, is_for_client, created_by, created_at, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
    $stmt->bind_param("ssdddsiiiiisi", $payment_type, $description, $base_amount, $profit, $sold_amount, $currency, $main_account_id, $supplier_id, $is_from_supplier, $client_id, $is_for_client, $_SESSION['user_id'], $tenant_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Error inserting payment: " . $stmt->error);
    }
    
    $payment_id = $conn->insert_id;
    
    // If payment is from supplier, deduct from supplier's balance
    if ($is_from_supplier && $supplier_id) {
        // Get supplier's current balance
        $supplierStmt = $conn->prepare("SELECT balance, currency FROM suppliers WHERE id = ? AND tenant_id = ?");
        $supplierStmt->bind_param("ii", $supplier_id, $tenant_id);
        $supplierStmt->execute();
        $supplierResult = $supplierStmt->get_result();
        $supplier = $supplierResult->fetch_assoc();
        
        if (!$supplier) {
            throw new Exception("Supplier not found");
        }
        
        // Check if currencies match
        if ($supplier['currency'] !== $currency) {
            throw new Exception("Supplier currency does not match payment currency");
        }
        
        // Calculate new supplier balance
        $newSupplierBalance = $supplier['balance'] - $base_amount;
        
        // Update supplier balance
        $updateSupplierStmt = $conn->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?");
        $updateSupplierStmt->bind_param("dii", $newSupplierBalance, $supplier_id, $tenant_id);
        
        if (!$updateSupplierStmt->execute()) {
            throw new Exception("Error updating supplier balance: " . $updateSupplierStmt->error);
        }
        
        // Add transaction record for supplier deduction with new balance
        $transactionStmt = $conn->prepare("INSERT INTO supplier_transactions (supplier_id, amount, transaction_type, remarks, reference_id, transaction_of, transaction_date, balance, tenant_id) VALUES (?, ?, 'debit', ?, ?, 'additional_payment', NOW(), ?, ?)");
        $transactionStmt->bind_param("idsssi", $supplier_id, $base_amount, $description, $payment_id, $newSupplierBalance, $tenant_id);
        
        if (!$transactionStmt->execute()) {
            throw new Exception("Error recording supplier transaction: " . $transactionStmt->error);
        }
    }

    // If payment is for client, add to client's balance
    if ($is_for_client && $client_id) {
        // Get client's current balance and type
        $clientStmt = $conn->prepare("SELECT usd_balance, afs_balance, client_type, name FROM clients WHERE id = ? AND tenant_id = ?");
        $clientStmt->bind_param("ii", $client_id, $tenant_id);
        $clientStmt->execute();
        $clientResult = $clientStmt->get_result();
        $client = $clientResult->fetch_assoc();
        
        if (!$client) {
            throw new Exception("Client not found");
        }
        
        // Determine which balance to use based on currency
        $current_balance = ($currency === 'USD') ? $client['usd_balance'] : $client['afs_balance'];
        $new_balance = $current_balance - $sold_amount; // Deduct sold amount from client balance
        
        // Only update balance for regular clients
        if ($client['client_type'] === 'regular') {
            // Update the appropriate balance column based on currency
            $balance_column = ($currency === 'USD') ? 'usd_balance' : 'afs_balance';
            $updateClientStmt = $conn->prepare("UPDATE clients SET $balance_column = ? WHERE id = ? AND tenant_id = ?");
            $updateClientStmt->bind_param("dii", $new_balance, $client_id, $tenant_id);
            
            if (!$updateClientStmt->execute()) {
                throw new Exception("Error updating client balance: " . $updateClientStmt->error);
            }
        }
        
        // Add transaction record
        $transactionStmt = $conn->prepare("INSERT INTO client_transactions (
            client_id, type, transaction_of, reference_id, amount, balance, currency, description, tenant_id
        ) VALUES (?, 'debit', 'additional_payment', ?, ?, ?, ?, ?, ?)");
        
        $transaction_description = "Additional payment: $payment_type - $description";
        $transactionStmt->bind_param("iiddssi", $client_id, $payment_id, $sold_amount, $new_balance, $currency, $transaction_description, $tenant_id);
        
        if (!$transactionStmt->execute()) {
            throw new Exception("Error recording client transaction: " . $transactionStmt->error);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Log activity
    $userId = $_SESSION['user_id'];
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    // Create new values JSON
    $newValues = json_encode([
        'id' => $payment_id,
        'payment_type' => $payment_type,
        'description' => $description,
        'base_amount' => $base_amount,
        'profit' => $profit,
        'sold_amount' => $sold_amount,
        'currency' => $currency,
        'main_account_id' => $main_account_id,
        'supplier_id' => $supplier_id,
        'is_from_supplier' => $is_from_supplier,
        'client_id' => $client_id,
        'is_for_client' => $is_for_client
    ]);
    
    // Insert activity log record
    $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id) 
                              VALUES (?, ?, ?, 'add', 'additional_payments', ?, NULL, ?, NOW(), ?)");
    $logStmt->bind_param("issisi", $userId, $ipAddress, $userAgent, $payment_id, $newValues, $tenant_id);
    
    if (!$logStmt->execute()) {
        // Just log the error, don't affect the transaction success
        error_log("Failed to insert activity log: " . $logStmt->error);
    }
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Payment added successfully']);
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}
?> 