<?php


// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once __DIR__ . '/../../admin/includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

// Database connection
require_once __DIR__ . '/../../includes/db.php';

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
    $pdo->beginTransaction();

    // Insert the payment
    $stmt = $pdo->prepare("INSERT INTO additional_payments (payment_type, description, base_amount, profit, sold_amount, currency, main_account_id, supplier_id, is_from_supplier, client_id, is_for_client, created_by, created_at, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
    $stmt->bindParam(1, $payment_type, PDO::PARAM_STR);
    $stmt->bindParam(2, $description, PDO::PARAM_STR);
    $stmt->bindParam(3, $base_amount, PDO::PARAM_STR);
    $stmt->bindParam(4, $profit, PDO::PARAM_STR);
    $stmt->bindParam(5, $sold_amount, PDO::PARAM_STR);
    $stmt->bindParam(6, $currency, PDO::PARAM_STR);
    $stmt->bindParam(7, $main_account_id, PDO::PARAM_INT);
    $stmt->bindParam(8, $supplier_id, PDO::PARAM_INT);
    $stmt->bindParam(9, $is_from_supplier, PDO::PARAM_INT);
    $stmt->bindParam(10, $client_id, PDO::PARAM_INT);
    $stmt->bindParam(11, $is_for_client, PDO::PARAM_INT);
    $stmt->bindParam(12, $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindParam(13, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(14, $branch_id, PDO::PARAM_INT);
    
    if (!$stmt->execute()) {
        throw new Exception("Error inserting payment: " . $stmt->error);
    }
    
    $payment_id = $pdo->lastInsertId();
    
    // If payment is from supplier, deduct from supplier's balance
    if ($is_from_supplier && $supplier_id) {
        // Get supplier's current balance
        $supplierStmt = $pdo->prepare("SELECT balance, currency FROM suppliers WHERE id = ? AND tenant_id = ? And branch_id = ?");
        $supplierStmt->bindParam(1, $supplier_id, PDO::PARAM_INT);
        $supplierStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $supplierStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $supplierStmt->execute();
        $supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);
        
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
        $updateSupplierStmt = $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? And branch_id = ?");
        $updateSupplierStmt->bindParam(1, $newSupplierBalance, PDO::PARAM_STR);
        $updateSupplierStmt->bindParam(2, $supplier_id, PDO::PARAM_INT);
        $updateSupplierStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $updateSupplierStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        
        if (!$updateSupplierStmt->execute()) {
            throw new Exception("Error updating supplier balance: " . $updateSupplierStmt->error);
        }
        
        // Add transaction record for supplier deduction with new balance
        $transactionStmt = $pdo->prepare("INSERT INTO supplier_transactions (supplier_id, amount, transaction_type, remarks, reference_id, transaction_of, transaction_date, balance, tenant_id, branch_id) VALUES (?, ?, 'debit', ?, ?, 'additional_payment', NOW(), ?, ?, ?)");
        $transactionStmt->bindParam(1, $supplier_id, PDO::PARAM_INT);
        $transactionStmt->bindParam(2, $base_amount, PDO::PARAM_STR);
        $transactionStmt->bindParam(3, $description, PDO::PARAM_STR);
        $transactionStmt->bindParam(4, $payment_id, PDO::PARAM_INT);
        $transactionStmt->bindParam(5, $newSupplierBalance, PDO::PARAM_STR);
        $transactionStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $transactionStmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        
        if (!$transactionStmt->execute()) {
            throw new Exception("Error recording supplier transaction: " . $transactionStmt->error);
        }
    }

    // If payment is for client, add to client's balance
    if ($is_for_client && $client_id) {
        // Get client's current balance and type
        $clientStmt = $pdo->prepare("SELECT usd_balance, afs_balance, client_type, name FROM clients WHERE id = ? AND tenant_id = ? And branch_id = ?");
        $clientStmt->bindParam(1, $client_id, PDO::PARAM_INT);
        $clientStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $clientStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $clientStmt->execute();
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
        
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
            $updateClientStmt = $pdo->prepare("UPDATE clients SET $balance_column = ? WHERE id = ? AND tenant_id = ? And branch_id = ?");
            $updateClientStmt->bindParam(1, $new_balance, PDO::PARAM_STR);
            $updateClientStmt->bindParam(2, $client_id, PDO::PARAM_INT);
            $updateClientStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $updateClientStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            
            if (!$updateClientStmt->execute()) {
                throw new Exception("Error updating client balance: " . $updateClientStmt->error);
            }
        }
        
        // Add transaction record
        $transactionStmt = $pdo->prepare("INSERT INTO client_transactions (
            client_id, type, transaction_of, reference_id, amount, balance, currency, description, tenant_id, branch_id
        ) VALUES (?, 'debit', 'additional_payment', ?, ?, ?, ?, ?, ?, ?)");

        $transaction_description = "Additional payment: $payment_type - $description";
        $transactionStmt->bindParam(1, $client_id, PDO::PARAM_INT);
        $transactionStmt->bindParam(2, $payment_id, PDO::PARAM_INT);
        $transactionStmt->bindParam(3, $sold_amount, PDO::PARAM_STR);
        $transactionStmt->bindParam(4, $new_balance, PDO::PARAM_STR);
        $transactionStmt->bindParam(5, $currency, PDO::PARAM_STR);
        $transactionStmt->bindParam(6, $transaction_description, PDO::PARAM_STR);
        $transactionStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $transactionStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
        
        if (!$transactionStmt->execute()) {
            throw new Exception("Error recording client transaction: " . $transactionStmt->error);
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
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
    $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id, branch_id)
                              VALUES (?, ?, ?, 'add', 'additional_payments', ?, NULL, ?, NOW(), ?, ?)");
    $logStmt->bindParam(1, $userId, PDO::PARAM_INT);
    $logStmt->bindParam(2, $ipAddress, PDO::PARAM_STR);
    $logStmt->bindParam(3, $userAgent, PDO::PARAM_STR);
    $logStmt->bindParam(4, $payment_id, PDO::PARAM_INT);
    $logStmt->bindParam(5, $newValues, PDO::PARAM_STR);
    $logStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
    $logStmt->bindParam(7, $branch_id, PDO::PARAM_INT);
    
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
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}
?> 