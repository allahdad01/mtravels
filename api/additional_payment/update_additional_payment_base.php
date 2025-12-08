<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Debug: Log the incoming request
error_log("update_additional_payment_base.php called with POST data: " . json_encode($_POST));

// Set the content type for all responses
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug: Check if user is logged in and has proper role
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            error_log("Unauthorized access attempt");
            echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
            exit();
        }
        
        // Debug: Check if CSRF token is present in the form data
        if (isset($_POST['csrf_token'])) {
            error_log("CSRF token found in POST data: " . $_POST['csrf_token']);
            
            // Check if session has a CSRF token
            if (!isset($_SESSION['csrf_token'])) {
                error_log("No CSRF token found in session");
                // For now, just log this instead of exiting
                // echo json_encode(['success' => false, 'message' => 'CSRF token missing in session']);
                // exit();
            } else if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                error_log("CSRF token mismatch: POST=" . $_POST['csrf_token'] . ", SESSION=" . $_SESSION['csrf_token']);
                // For now, just log this instead of exiting
                // echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
                // exit();
            } else {
                error_log("CSRF token validated successfully");
            }
        } else {
            // For now, allow requests without CSRF token for backward compatibility
            error_log("No CSRF token found in POST data - continuing for backward compatibility");
        }
        
        // Debug: Log that we're starting the process
        error_log("Starting payment update process");
        
        // Validate required fields
        if (!isset($_POST['id']) || empty($_POST['id'])) {
            throw new Exception("Payment ID is required");
        }
        
        if (!isset($_POST['base_amount']) || !isset($_POST['sold_amount']) || !isset($_POST['profit'])) {
            throw new Exception("Amount fields are required");
        }
        
        $paymentId = intval($_POST['id']);
        $newBaseAmount = floatval($_POST['base_amount']);
        $newSoldAmount = floatval($_POST['sold_amount']);
        $newProfit = floatval($_POST['profit']);
        $currency = $_POST['currency'];
        $mainAccountId = intval($_POST['main_account_id']);
        $supplierId = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;
        $isFromSupplier = isset($_POST['is_from_supplier']) && $_POST['is_from_supplier'] == '1' ? 1 : 0;
        $clientId = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
        $isForClient = isset($_POST['is_for_client']) && $_POST['is_for_client'] == '1' ? 1 : 0;

        // Debug: Log the parsed values
        error_log("Parsed values: paymentId=$paymentId, baseAmount=$newBaseAmount, soldAmount=$newSoldAmount, profit=$newProfit, supplierId=" . ($supplierId ?? 'null') . ", clientId=" . ($clientId ?? 'null') . ", isFromSupplier=$isFromSupplier, isForClient=$isForClient");

        // Begin transaction
        $pdo->beginTransaction();
        error_log("Transaction started");

        // Get the original payment details
        $stmt = $pdo->prepare("SELECT * FROM additional_payments WHERE id = ? AND tenant_id = ? And branch_id = ?");
        $stmt->bindParam(1, $paymentId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            error_log("Payment not found for ID: $paymentId");
            throw new Exception("Payment not found");
        }
        
        error_log("Found payment: " . json_encode($payment));

        // Calculate the differences
        $baseAmountDifference = $newBaseAmount - $payment['base_amount'];
        $soldAmountDifference = $newSoldAmount - $payment['sold_amount'];
        
        error_log("Calculated differences: baseAmountDiff=$baseAmountDifference, soldAmountDiff=$soldAmountDifference");

        // If this is from a supplier, update supplier balance and subsequent transactions
        if ($isFromSupplier && $supplierId) {
            error_log("Processing supplier updates for supplier ID: $supplierId");
            
            // Get supplier's current balance
            $stmt = $pdo->prepare("SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ? And branch_id = ?");
            $stmt->bindParam(1, $supplierId, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

            // Calculate new supplier balance
            $supplierBalanceDifference = -$baseAmountDifference; // Negative because we're paying the supplier
            $newSupplierBalance = $supplier['balance'] + $supplierBalanceDifference;

            error_log("Supplier balance: current={$supplier['balance']}, diff=$supplierBalanceDifference, new=$newSupplierBalance");

            // Update supplier balance
            $updateSupplierStmt = $pdo->prepare("
                UPDATE suppliers
                SET balance = ?
                WHERE id = ? AND tenant_id = ? And branch_id = ?
            ");
            $updateSupplierStmt->bindParam(1, $newSupplierBalance, PDO::PARAM_STR);
            $updateSupplierStmt->bindParam(2, $supplierId, PDO::PARAM_INT);
            $updateSupplierStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $updateSupplierStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $updateSupplierStmt->execute();
            error_log("Updated supplier balance");

            // Get the current payment's creation date
            $stmt = $pdo->prepare("SELECT created_at FROM additional_payments WHERE id = ? AND tenant_id = ? And branch_id = ?");
            $stmt->bindParam(1, $paymentId, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $paymentDate = $stmt->fetch(PDO::FETCH_ASSOC)['created_at'];

            // Update the current supplier transaction
            $updateCurrentStmt = $pdo->prepare("
                UPDATE supplier_transactions
                SET amount = ?,
                    balance = balance + ?
                WHERE supplier_id = ?
                AND reference_id = ?
                AND transaction_of = 'additional_payment'
                AND tenant_id = ? And branch_id = ?
            ");
            $updateCurrentStmt->bindParam(1, $newBaseAmount, PDO::PARAM_STR);
            $updateCurrentStmt->bindParam(2, $supplierBalanceDifference, PDO::PARAM_STR);
            $updateCurrentStmt->bindParam(3, $supplierId, PDO::PARAM_INT);
            $updateCurrentStmt->bindParam(4, $paymentId, PDO::PARAM_INT);
            $updateCurrentStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
            $updateCurrentStmt->bindParam(6, $branch_id, PDO::PARAM_INT);
            $updateCurrentStmt->execute();
            error_log("Updated supplier transaction");

            // Update subsequent supplier transactions' balances
            $updateSubsequentStmt = $pdo->prepare("
                UPDATE supplier_transactions
                SET balance = balance + ?
                WHERE supplier_id = ?
                AND transaction_date > ?
                AND tenant_id = ? And branch_id = ?
                ORDER BY transaction_date ASC
            ");
            $updateSubsequentStmt->bindParam(1, $supplierBalanceDifference, PDO::PARAM_STR);
            $updateSubsequentStmt->bindParam(2, $supplierId, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(3, $paymentDate, PDO::PARAM_STR);
            $updateSubsequentStmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
            $updateSubsequentStmt->bindParam(5, $branch_id, PDO::PARAM_INT);
            $updateSubsequentStmt->execute();
            error_log("Updated subsequent supplier transactions");
        }

        // If this is for a client, update client balance and subsequent transactions
        if ($isForClient && $clientId) {
            error_log("Processing client updates for client ID: $clientId");
            
            // Get client's current balances and type
            $stmt = $pdo->prepare("SELECT usd_balance, afs_balance, client_type FROM clients WHERE id = ? AND tenant_id = ? And branch_id = ?");
            $stmt->bindParam(1, $clientId, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($client['client_type'] === 'regular') {
                // Calculate client balance difference (negative because we're charging the client)
                $clientBalanceDifference = $soldAmountDifference;
                
                // Update the appropriate balance based on currency
                $balanceColumn = ($currency === 'USD') ? 'usd_balance' : 'afs_balance';
                $currentBalance = $client[$balanceColumn];
                $newClientBalance = $currentBalance - $clientBalanceDifference;

                error_log("Client balance: current=$currentBalance, diff=$clientBalanceDifference, new=$newClientBalance, currency=$currency");

                // Update client balance
                $updateClientStmt = $pdo->prepare("
                    UPDATE clients
                    SET $balanceColumn = ?
                    WHERE id = ? AND tenant_id = ? And branch_id = ?
                ");
                $updateClientStmt->bindParam(1, $newClientBalance, PDO::PARAM_STR);
                $updateClientStmt->bindParam(2, $clientId, PDO::PARAM_INT);
                $updateClientStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $updateClientStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                $updateClientStmt->execute();
                error_log("Updated client balance");

                // Get the current payment's creation date
                $stmt = $pdo->prepare("SELECT created_at FROM additional_payments WHERE id = ? AND tenant_id = ? And branch_id = ?");
                $stmt->bindParam(1, $paymentId, PDO::PARAM_INT);
                $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmt->execute();
                $paymentDate = $stmt->fetch(PDO::FETCH_ASSOC)['created_at'];

                // Update the current client transaction
                $updateCurrentStmt = $pdo->prepare("
                    UPDATE client_transactions
                    SET amount = ?,
                        balance = balance - ?
                    WHERE client_id = ?
                    AND reference_id = ?
                    AND transaction_of = 'additional_payment'
                    AND tenant_id = ? And branch_id = ?
                ");
                $updateCurrentStmt->bindParam(1, $newSoldAmount, PDO::PARAM_STR);
                $updateCurrentStmt->bindParam(2, $clientBalanceDifference, PDO::PARAM_STR);
                $updateCurrentStmt->bindParam(3, $clientId, PDO::PARAM_INT);
                $updateCurrentStmt->bindParam(4, $paymentId, PDO::PARAM_INT);
                $updateCurrentStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
                $updateCurrentStmt->bindParam(6, $branch_id, PDO::PARAM_INT);
                $updateCurrentStmt->execute();
                error_log("Updated client transaction");

                // Update subsequent client transactions' balances
                $updateSubsequentStmt = $pdo->prepare("
                    UPDATE client_transactions
                    SET balance = balance - ?
                    WHERE client_id = ?
                    AND created_at > ?
                    AND currency = ?
                    AND tenant_id = ? And branch_id = ?
                    ORDER BY created_at ASC
                ");
                $updateSubsequentStmt->bindParam(1, $clientBalanceDifference, PDO::PARAM_STR);
                $updateSubsequentStmt->bindParam(2, $clientId, PDO::PARAM_INT);
                $updateSubsequentStmt->bindParam(3, $paymentDate, PDO::PARAM_STR);
                $updateSubsequentStmt->bindParam(4, $currency, PDO::PARAM_STR);
                $updateSubsequentStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
                $updateSubsequentStmt->bindParam(6, $branch_id, PDO::PARAM_INT);
                $updateSubsequentStmt->execute();
                error_log("Updated subsequent client transactions");
            }
        }

        // Update the payment record
        error_log("Updating payment record");
        $updatePaymentStmt = $pdo->prepare("
            UPDATE additional_payments
            SET base_amount = ?, sold_amount = ?, profit = ?,
                payment_type = ?, description = ?, currency = ?,
                main_account_id = ?, supplier_id = ?, is_from_supplier = ?,
                client_id = ?, is_for_client = ?
            WHERE id = ? AND tenant_id = ? And branch_id = ?
        ");

        $paymentType = $_POST['payment_type'];
        $description = $_POST['description'];

        $updatePaymentStmt->bindParam(1, $newBaseAmount, PDO::PARAM_STR);
        $updatePaymentStmt->bindParam(2, $newSoldAmount, PDO::PARAM_STR);
        $updatePaymentStmt->bindParam(3, $newProfit, PDO::PARAM_STR);
        $updatePaymentStmt->bindParam(4, $paymentType, PDO::PARAM_STR);
        $updatePaymentStmt->bindParam(5, $description, PDO::PARAM_STR);
        $updatePaymentStmt->bindParam(6, $currency, PDO::PARAM_STR);
        $updatePaymentStmt->bindParam(7, $mainAccountId, PDO::PARAM_INT);
        $updatePaymentStmt->bindParam(8, $supplierId, PDO::PARAM_INT);
        $updatePaymentStmt->bindParam(9, $isFromSupplier, PDO::PARAM_INT);
        $updatePaymentStmt->bindParam(10, $clientId, PDO::PARAM_INT);
        $updatePaymentStmt->bindParam(11, $isForClient, PDO::PARAM_INT);
        $updatePaymentStmt->bindParam(12, $paymentId, PDO::PARAM_INT);
        $updatePaymentStmt->bindParam(13, $tenant_id, PDO::PARAM_INT);
        $updatePaymentStmt->bindParam(14, $branch_id, PDO::PARAM_INT);

        $result = $updatePaymentStmt->execute();

        if (!$result) {
            $errorInfo = $updatePaymentStmt->errorInfo();
            error_log("Error updating payment: " . $errorInfo[2]);
            throw new Exception("Error updating payment: " . $errorInfo[2]);
        }
        
        error_log("Payment record updated successfully");

        // Log activity if user is logged in
        if (isset($_SESSION['user_id'])) {
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
                'is_for_client' => $payment['is_for_client']
            ]);
            
            // Create new values JSON
            $newValues = json_encode([
                'id' => $paymentId,
                'payment_type' => $paymentType,
                'description' => $description,
                'base_amount' => $newBaseAmount,
                'profit' => $newProfit,
                'sold_amount' => $newSoldAmount,
                'currency' => $currency,
                'main_account_id' => $mainAccountId,
                'supplier_id' => $supplierId,
                'is_from_supplier' => $isFromSupplier,
                'client_id' => $clientId,
                'is_for_client' => $isForClient
            ]);
            
            // Insert activity log record
            $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id, branch_id)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
            $action = 'update';
            $tableName = 'additional_payments';
            $logStmt->bindParam(1, $userId, PDO::PARAM_INT);
            $logStmt->bindParam(2, $ipAddress, PDO::PARAM_STR);
            $logStmt->bindParam(3, $userAgent, PDO::PARAM_STR);
            $logStmt->bindParam(4, $action, PDO::PARAM_STR);
            $logStmt->bindParam(5, $tableName, PDO::PARAM_STR);
            $logStmt->bindParam(6, $paymentId, PDO::PARAM_INT);
            $logStmt->bindParam(7, $oldValues, PDO::PARAM_STR);
            $logStmt->bindParam(8, $newValues, PDO::PARAM_STR);
            $logStmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
            $logStmt->bindParam(10, $branch_id, PDO::PARAM_INT);

            if (!$logStmt->execute()) {
                // Just log the error, don't affect the transaction success
                $errorInfo = $logStmt->errorInfo();
                error_log("Failed to insert activity log: " . $errorInfo[2]);
            } else {
                error_log("Activity log created");
            }
        }

        // Commit transaction
        $pdo->commit();
        error_log("Transaction committed successfully");
        
        echo json_encode(['success' => true, 'message' => 'Payment updated successfully']);

    } catch (Exception $e) {
        // Check if transaction is active using a different method since inTransaction() might not be available
        try {
            // Try to start a transaction - if one is already active, this will fail
            $pdo->beginTransaction();
            // If we get here, no transaction was active, so roll it back
            $pdo->rollBack();
        } catch (Exception $ex) {
            // An exception means a transaction was already active, so roll it back
            $pdo->rollBack();
            error_log("Transaction rolled back due to error");
        }
        
        error_log("Error in update_additional_payment_base.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 