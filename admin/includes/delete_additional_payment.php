<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once '../includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];

// Database connection
require_once('../../includes/db.php');
require_once('../../includes/conn.php');

// Validate id
$id = isset($_POST['id']) ? DbSecurity::validateInput($_POST['id'], 'int', ['min' => 0]) : null;

// Validate action
$action = isset($_POST['action']) ? DbSecurity::validateInput($_POST['action'], 'string', ['maxlength' => 255]) : null;

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    // Check if the request expects JSON response
    $wantsJson = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    $id = $_POST['id'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // First, get the payment details
        $stmt = $conn->prepare("SELECT * FROM additional_payments WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();
        
        if (!$payment) {
            throw new Exception("Payment not found");
        }

        // Handle supplier balance update if payment was from supplier
        if ($payment['is_from_supplier'] && $payment['supplier_id']) {
            // Get supplier's current balance
            $stmt = $conn->prepare("SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ?");
            $stmt->bind_param("ii", $payment['supplier_id'], $tenant_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $supplier = $result->fetch_assoc();

            // Calculate balance adjustment (add back the base amount since we're deleting the payment)
            $balanceAdjustment = $payment['base_amount'];
            $newSupplierBalance = $supplier['balance'] + $balanceAdjustment;

            // Update supplier balance
            $updateSupplierStmt = $conn->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?");
            $updateSupplierStmt->bind_param("dii", $newSupplierBalance, $payment['supplier_id'], $tenant_id);
            $updateSupplierStmt->execute();

            // Update subsequent supplier transactions
            $updateSubsequentStmt = $conn->prepare("
                UPDATE supplier_transactions 
                SET balance = balance + ? 
                WHERE supplier_id = ? 
                AND transaction_date > ? 
                AND tenant_id = ?
                ORDER BY transaction_date ASC
            ");
            $updateSubsequentStmt->bind_param(
                "disi", 
                $balanceAdjustment,
                $payment['supplier_id'],
                $payment['created_at'],
                $tenant_id
            );
            $updateSubsequentStmt->execute();
        }

        // Handle client balance update if payment was for client
        if ($payment['is_for_client'] && $payment['client_id']) {
            // Get client's current balances and type
            $stmt = $conn->prepare("SELECT usd_balance, afs_balance, client_type FROM clients WHERE id = ? AND tenant_id = ?");
            $stmt->bind_param("ii", $payment['client_id'], $tenant_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $client = $result->fetch_assoc();

            if ($client['client_type'] === 'regular') {
                // Add back the sold amount to client's balance since we're deleting the payment
                $balanceColumn = ($payment['currency'] === 'USD') ? 'usd_balance' : 'afs_balance';
                $balanceAdjustment = $payment['sold_amount'];
                $currentBalance = $client[$balanceColumn];
                $newClientBalance = $currentBalance + $balanceAdjustment;

                // Update client balance
                $updateClientStmt = $conn->prepare("UPDATE clients SET $balanceColumn = ? WHERE id = ? AND tenant_id = ?");
                $updateClientStmt->bind_param("dii", $newClientBalance, $payment['client_id'], $tenant_id);
                $updateClientStmt->execute();

                // Update subsequent client transactions
                $updateSubsequentStmt = $conn->prepare("
                    UPDATE client_transactions 
                    SET balance = balance + ? 
                    WHERE client_id = ? 
                    AND created_at > ? 
                    AND currency = ?
                    AND tenant_id = ?
                    ORDER BY created_at ASC
                ");
                $updateSubsequentStmt->bind_param(
                    "dissi",
                    $balanceAdjustment,
                    $payment['client_id'],
                    $payment['created_at'],
                    $payment['currency'],
                    $tenant_id
                );
                $updateSubsequentStmt->execute();
            }
        }

        // Handle main account balance update
        if ($payment['main_account_id']) {
            // Get main account transactions related to this payment
            $stmt = $conn->prepare("
                SELECT * FROM main_account_transactions 
                WHERE reference_id = ? 
                AND transaction_of = 'additional_payment'
                AND tenant_id = ?
            ");
            $stmt->bind_param("ii", $id, $tenant_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($transaction = $result->fetch_assoc()) {
                // Update main account balance
                $balanceField = $transaction['currency'] === 'USD' ? 'usd_balance' : 'afs_balance';
                $updateMainAccStmt = $conn->prepare("
                    UPDATE main_account 
                    SET $balanceField = $balanceField - ? 
                    WHERE id = ?
                    AND tenant_id = ?
                ");
                $updateMainAccStmt->bind_param("di", $transaction['amount'], $transaction['main_account_id'], $tenant_id);
                $updateMainAccStmt->execute();
                
                // Update subsequent main account transactions
                $updateSubsequentMainStmt = $conn->prepare("
                    UPDATE main_account_transactions 
                    SET balance = balance - ? 
                    WHERE main_account_id = ? 
                    AND currency = ? 
                    AND created_at > ? 
                    AND id != ?
                    AND tenant_id = ?
                ");
                $updateSubsequentMainStmt->bind_param(
                    "dissi", 
                    $transaction['amount'],
                    $transaction['main_account_id'], 
                    $transaction['currency'],
                    $transaction['created_at'],
                    $transaction['id'],
                    $tenant_id
                );
                $updateSubsequentMainStmt->execute();
            }
        }

        // Delete associated transactions
        $stmt = $conn->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'additional_payment' AND tenant_id = ?");
        $stmt->bind_param("ii", $id, $tenant_id);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM supplier_transactions WHERE reference_id = ? AND transaction_of = 'additional_payment' AND tenant_id = ?");
        $stmt->bind_param("ii", $id, $tenant_id);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM client_transactions WHERE reference_id = ? AND transaction_of = 'additional_payment' AND tenant_id = ?");
        $stmt->bind_param("ii", $id, $tenant_id);
        $stmt->execute();

        // Finally, delete the payment
        $stmt = $conn->prepare("DELETE FROM additional_payments WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $id, $tenant_id);
        $stmt->execute();
        
        // Log activity
        $userId = $_SESSION['user_id'];
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        
        // Store old values for activity log
        $oldValues = json_encode([
            'id' => $payment['id'],
            'payment_type' => $payment['payment_type'],
            'description' => $payment['description'],
            'base_amount' => $payment['base_amount'],
            'profit' => $payment['profit'],
            'sold_amount' => $payment['sold_amount'],
            'currency' => $payment['currency'],
            'main_account_id' => $payment['main_account_id'],
            'supplier_id' => $payment['supplier_id'],
            'is_from_supplier' => $payment['is_from_supplier'],
            'client_id' => $payment['client_id'],
            'is_for_client' => $payment['is_for_client'],
            'created_at' => $payment['created_at']
        ]);
        
        // Insert activity log record
        $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id) 
                                  VALUES (?, ?, ?, 'delete', 'additional_payments', ?, ?, NULL, NOW(), ?)");
        $logStmt->bind_param("issisi", $userId, $ipAddress, $userAgent, $id, $oldValues, $tenant_id);
        
        if (!$logStmt->execute()) {
            // Just log the error, don't affect the transaction success
            error_log("Failed to insert activity log: " . $logStmt->error);
        }
        
        $conn->commit();
        
        if ($wantsJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Payment and associated transactions deleted successfully!']);
            exit();
        } else {
            $_SESSION['success'] = "Payment and associated transactions deleted successfully!";
            header("Location: ../additional_payments.php");
            exit();
        }
    } catch (Exception $e) {
        $conn->rollback();
        
        if ($wantsJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error deleting payment: ' . $e->getMessage()]);
            exit();
        } else {
            $_SESSION['error'] = "Error deleting payment: " . $e->getMessage();
            header("Location: ../additional_payments.php");
            exit();
        }
    }
}
?>