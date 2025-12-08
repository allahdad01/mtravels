<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once __DIR__ . '/../../admin/includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Database connection
require_once('../../includes/db.php');

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
    $pdo->beginTransaction();
    
    try {
        // First, get the payment details
        $stmt = $pdo->prepare("SELECT * FROM additional_payments WHERE id = ? AND tenant_id = ? And branch_id = ?");
        $stmt->bindParam(1, $id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            throw new Exception("Payment not found");
        }

        // Handle supplier balance update if payment was from supplier
        if ($payment['is_from_supplier'] && $payment['supplier_id']) {
            // Get supplier's current balance
            $stmt = $pdo->prepare("SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ? And branch_id = ?");
            $stmt->bindParam(1, $payment['supplier_id'], PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

            // Calculate balance adjustment (add back the base amount since we're deleting the payment)
            $balanceAdjustment = $payment['base_amount'];
            $newSupplierBalance = $supplier['balance'] + $balanceAdjustment;

            // Update supplier balance
            $updateSupplierStmt = $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? And branch_id = ?");
            $updateSupplierStmt->bindParam(1, $newSupplierBalance, PDO::PARAM_STR);
            $updateSupplierStmt->bindParam(2, $payment['supplier_id'], PDO::PARAM_INT);
            $updateSupplierStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $updateSupplierStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $updateSupplierStmt->execute();

            // Update subsequent supplier transactions
            $updateSubsequentStmt = $pdo->prepare("
                UPDATE supplier_transactions
                SET balance = balance + ?
                WHERE supplier_id = ?
                AND transaction_date > ?
                AND tenant_id = ? And branch_id = ?
                ORDER BY transaction_date ASC
            ");
            $updateSubsequentStmt->bindParam(1, $balanceAdjustment, PDO::PARAM_STR);
            $updateSubsequentStmt->bindParam(2, $payment['supplier_id'], PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(3, $payment['created_at'], PDO::PARAM_STR);
            $updateSubsequentStmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(5, $branch_id, PDO::PARAM_INT);
            $updateSubsequentStmt->execute();
        }

        // Handle client balance update if payment was for client
        if ($payment['is_for_client'] && $payment['client_id']) {
            // Get client's current balances and type
            $stmt = $pdo->prepare("SELECT usd_balance, afs_balance, client_type FROM clients WHERE id = ? AND tenant_id = ? And branch_id = ?");
            $stmt->bindParam(1, $payment['client_id'], PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($client['client_type'] === 'regular') {
                // Add back the sold amount to client's balance since we're deleting the payment
                $balanceColumn = ($payment['currency'] === 'USD') ? 'usd_balance' : 'afs_balance';
                $balanceAdjustment = $payment['sold_amount'];
                $currentBalance = $client[$balanceColumn];
                $newClientBalance = $currentBalance + $balanceAdjustment;

                // Update client balance
                $updateClientStmt = $pdo->prepare("UPDATE clients SET $balanceColumn = ? WHERE id = ? AND tenant_id = ? And branch_id = ?");
                $updateClientStmt->bindParam(1, $newClientBalance, PDO::PARAM_STR);
                $updateClientStmt->bindParam(2, $payment['client_id'], PDO::PARAM_INT);
                $updateClientStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $updateClientStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                $updateClientStmt->execute();

                // Update subsequent client transactions
                $updateSubsequentStmt = $pdo->prepare("
                    UPDATE client_transactions
                    SET balance = balance + ?
                    WHERE client_id = ?
                    AND created_at > ?
                    AND currency = ?
                    AND tenant_id = ? And branch_id = ?
                    ORDER BY created_at ASC
                ");
                $updateSubsequentStmt->bindParam(1, $balanceAdjustment, PDO::PARAM_STR);
                $updateSubsequentStmt->bindParam(2, $payment['client_id'], PDO::PARAM_INT);
                $updateSubsequentStmt->bindParam(3, $payment['created_at'], PDO::PARAM_STR);
                $updateSubsequentStmt->bindParam(4, $payment['currency'], PDO::PARAM_STR);
                $updateSubsequentStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
                $updateSubsequentStmt->bindParam(6, $branch_id, PDO::PARAM_INT);
                $updateSubsequentStmt->execute();
            }
        }

        // Handle main account balance update
        if ($payment['main_account_id']) {
            // Get main account transactions related to this payment
            $stmt = $pdo->prepare("
                SELECT * FROM main_account_transactions
                WHERE reference_id = ?
                AND transaction_of = 'additional_payment'
                AND tenant_id = ? And branch_id = ?
            ");
            $stmt->bindParam(1, $id, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($transactions as $transaction) {
                // Update main account balance
                $balanceField = $transaction['currency'] === 'USD' ? 'usd_balance' : 'afs_balance';
                $updateMainAccStmt = $pdo->prepare("
                    UPDATE main_account
                    SET $balanceField = $balanceField - ?
                    WHERE id = ?
                    AND tenant_id = ? And branch_id = ?
                ");
                $updateMainAccStmt->bindParam(1, $transaction['amount'], PDO::PARAM_STR);
                $updateMainAccStmt->bindParam(2, $transaction['main_account_id'], PDO::PARAM_INT);
                $updateMainAccStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $updateMainAccStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                $updateMainAccStmt->execute();
                
                // Update subsequent main account transactions
                $updateSubsequentMainStmt = $pdo->prepare("
                    UPDATE main_account_transactions
                    SET balance = balance - ?
                    WHERE main_account_id = ?
                    AND currency = ?
                    AND created_at > ?
                    AND id != ?
                    AND tenant_id = ? And branch_id = ?
                ");
                $updateSubsequentMainStmt->bindParam(1, $transaction['amount'], PDO::PARAM_STR);
                $updateSubsequentMainStmt->bindParam(2, $transaction['main_account_id'], PDO::PARAM_INT);
                $updateSubsequentMainStmt->bindParam(3, $transaction['currency'], PDO::PARAM_STR);
                $updateSubsequentMainStmt->bindParam(4, $transaction['created_at'], PDO::PARAM_STR);
                $updateSubsequentMainStmt->bindParam(5, $transaction['id'], PDO::PARAM_INT);
                $updateSubsequentMainStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
                $updateSubsequentMainStmt->bindParam(7, $branch_id, PDO::PARAM_INT);
                $updateSubsequentMainStmt->execute();
            }
        }

        // Delete associated transactions
        $stmt = $pdo->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'additional_payment' AND tenant_id = ? And branch_id = ?");
        $stmt->bindParam(1, $id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("DELETE FROM supplier_transactions WHERE reference_id = ? AND transaction_of = 'additional_payment' AND tenant_id = ? And branch_id = ?");
        $stmt->bindParam(1, $id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("DELETE FROM client_transactions WHERE reference_id = ? AND transaction_of = 'additional_payment' AND tenant_id = ? And branch_id = ?");
        $stmt->bindParam(1, $id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        // Finally, delete the payment
        $stmt = $pdo->prepare("DELETE FROM additional_payments WHERE id = ? AND tenant_id = ? And branch_id = ?");
        $stmt->bindParam(1, $id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
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
        $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id, branch_id)
                                  VALUES (?, ?, ?, 'delete', 'additional_payments', ?, ?, NULL, NOW(), ?, ?)");
        $logStmt->bindParam(1, $userId, PDO::PARAM_INT);
        $logStmt->bindParam(2, $ipAddress, PDO::PARAM_STR);
        $logStmt->bindParam(3, $userAgent, PDO::PARAM_STR);
        $logStmt->bindParam(4, $id, PDO::PARAM_INT);
        $logStmt->bindParam(5, $oldValues, PDO::PARAM_STR);
        $logStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $logStmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        
        if (!$logStmt->execute()) {
            // Just log the error, don't affect the transaction success
            error_log("Failed to insert activity log: " . implode(", ", $logStmt->errorInfo()));
        }
        
        $pdo->commit();
        
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
        $pdo->rollBack();
        
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