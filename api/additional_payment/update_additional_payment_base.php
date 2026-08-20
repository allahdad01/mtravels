<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

require_once '../../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Set the content type for all responses
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check permission
        require_permission('finance.additional_payments');
        // Verify CSRF token is present and valid
        if (!isset($_POST['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Security validation failed: CSRF token missing']);
            exit();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Security validation failed: Session invalid']);
            exit();
        }
        
        // Use hash_equals to prevent timing attacks
        if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Security validation failed: Invalid CSRF token']);
            exit();
        }
        
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

        // Begin transaction
        $pdo->beginTransaction();

        // Get the original payment details
        $stmt = $pdo->prepare("SELECT * FROM additional_payments WHERE id = ? AND tenant_id = ? And branch_id = ?");
        $stmt->bindParam(1, $paymentId, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            throw new Exception("Payment not found");
        }

        // Calculate the differences
         $baseAmountDifference = $newBaseAmount - $payment['base_amount'];
         $soldAmountDifference = $newSoldAmount - $payment['sold_amount'];

         // Store original supplier and client for change detection
         $originalSupplier = $payment['supplier_id'];
         $originalClient = $payment['client_id'];

         // Handle supplier changes
         if ($isFromSupplier && $originalSupplier != $supplierId) {
             
             // If original supplier exists, adjust their balance
             if ($originalSupplier > 0) {
                 $oldSupplierQuery = "SELECT * FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                 $stmtOldSupplier = $pdo->prepare($oldSupplierQuery);
                 $stmtOldSupplier->bindParam(1, $originalSupplier, PDO::PARAM_INT);
                 $stmtOldSupplier->bindParam(2, $tenant_id, PDO::PARAM_INT);
                 $stmtOldSupplier->bindParam(3, $branch_id, PDO::PARAM_INT);
                 $stmtOldSupplier->execute();
                 $oldSupplierData = $stmtOldSupplier->fetch(PDO::FETCH_ASSOC);
                 
                 $oldSupplierType = isset($oldSupplierData['supplier_type']) ? $oldSupplierData['supplier_type'] : '';
                 if (!$oldSupplierType) {
                     $oldSupplierType = isset($oldSupplierData['type']) ? $oldSupplierData['type'] : '';
                 }
                 $oldSupplierIsExternal = (strtolower(trim($oldSupplierType)) === 'external');
                 
                 if ($oldSupplierIsExternal) {
                     // Get all transactions for the old supplier related to this payment
                     $getOldSupplierTransactionsQuery = "SELECT * FROM supplier_transactions
                                                         WHERE supplier_id = ?
                                                         AND reference_id = ?
                                                         AND transaction_of = 'additional_payment'
                                                         AND tenant_id = ?
                                                         AND branch_id = ?";
                     $stmtGetOldSupplierTransactions = $pdo->prepare($getOldSupplierTransactionsQuery);
                     $stmtGetOldSupplierTransactions->bindParam(1, $originalSupplier, PDO::PARAM_INT);
                     $stmtGetOldSupplierTransactions->bindParam(2, $payment['id'], PDO::PARAM_INT);
                     $stmtGetOldSupplierTransactions->bindParam(3, $tenant_id, PDO::PARAM_INT);
                     $stmtGetOldSupplierTransactions->bindParam(4, $branch_id, PDO::PARAM_INT);
                     $stmtGetOldSupplierTransactions->execute();
                     $oldSupplierTransactions = $stmtGetOldSupplierTransactions->fetchAll(PDO::FETCH_ASSOC);
                     
                     // Calculate total amount from old supplier transactions and get transaction ID
                     $totalOldSupplierAmount = 0;
                     $oldSupplierTransactionId = null;
                     foreach ($oldSupplierTransactions as $transaction) {
                         $totalOldSupplierAmount += $transaction['amount'];
                         if (!$oldSupplierTransactionId) {
                             $oldSupplierTransactionId = $transaction['id'];
                         }
                     }
                     
                     // Update old supplier balance (add back the amount)
                     $updateOldSupplierQuery = "UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                     $stmtUpdateOldSupplier = $pdo->prepare($updateOldSupplierQuery);
                     $stmtUpdateOldSupplier->bindParam(1, $totalOldSupplierAmount, PDO::PARAM_STR);
                     $stmtUpdateOldSupplier->bindParam(2, $originalSupplier, PDO::PARAM_INT);
                     $stmtUpdateOldSupplier->bindParam(3, $tenant_id, PDO::PARAM_INT);
                     $stmtUpdateOldSupplier->bindParam(4, $branch_id, PDO::PARAM_INT);
                     $stmtUpdateOldSupplier->execute();
                     
                     // Update subsequent old supplier transactions' balances BEFORE deleting
                     if ($oldSupplierTransactionId) {
                         $updateOldSubsequentQuery = "UPDATE supplier_transactions
                                                      SET balance = balance + ?
                                                      WHERE supplier_id = ?
                                                      AND branch_id = ?
                                                      AND id > ?
                                                      AND tenant_id = ?";
                         $stmtUpdateOldSubsequent = $pdo->prepare($updateOldSubsequentQuery);
                         $stmtUpdateOldSubsequent->bindParam(1, $totalOldSupplierAmount, PDO::PARAM_STR);
                         $stmtUpdateOldSubsequent->bindParam(2, $originalSupplier, PDO::PARAM_INT);
                         $stmtUpdateOldSubsequent->bindParam(3, $branch_id, PDO::PARAM_INT);
                         $stmtUpdateOldSubsequent->bindParam(4, $oldSupplierTransactionId, PDO::PARAM_INT);
                         $stmtUpdateOldSubsequent->bindParam(5, $tenant_id, PDO::PARAM_INT);
                         $stmtUpdateOldSubsequent->execute();
                     }
                     
                     // Delete old supplier transactions
                     $deleteOldSupplierTransactionsQuery = "DELETE FROM supplier_transactions
                                                           WHERE supplier_id = ?
                                                           AND reference_id = ?
                                                           AND transaction_of = 'additional_payment'
                                                           AND tenant_id = ?
                                                           AND branch_id = ?";
                     $stmtDeleteOldSupplierTransactions = $pdo->prepare($deleteOldSupplierTransactionsQuery);
                     $stmtDeleteOldSupplierTransactions->bindParam(1, $originalSupplier, PDO::PARAM_INT);
                     $stmtDeleteOldSupplierTransactions->bindParam(2, $payment['id'], PDO::PARAM_INT);
                     $stmtDeleteOldSupplierTransactions->bindParam(3, $tenant_id, PDO::PARAM_INT);
                     $stmtDeleteOldSupplierTransactions->bindParam(4, $branch_id, PDO::PARAM_INT);
                     $stmtDeleteOldSupplierTransactions->execute();
                     
                 }
             }
             
             // If new supplier exists and is external, create new transactions
             if ($supplierId > 0) {
                 $newSupplierQuery = "SELECT * FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                 $stmtNewSupplier = $pdo->prepare($newSupplierQuery);
                 $stmtNewSupplier->bindParam(1, $supplierId, PDO::PARAM_INT);
                 $stmtNewSupplier->bindParam(2, $tenant_id, PDO::PARAM_INT);
                 $stmtNewSupplier->bindParam(3, $branch_id, PDO::PARAM_INT);
                 $stmtNewSupplier->execute();
                 $newSupplierData = $stmtNewSupplier->fetch(PDO::FETCH_ASSOC);
                 
                 $newSupplierType = isset($newSupplierData['supplier_type']) ? $newSupplierData['supplier_type'] : '';
                 if (!$newSupplierType) {
                     $newSupplierType = isset($newSupplierData['type']) ? $newSupplierData['type'] : '';
                 }
                 $newSupplierIsExternal = (strtolower(trim($newSupplierType)) === 'external');
                 
                 if ($newSupplierIsExternal) {
                     // Get new supplier's current balance
                     $stmtGetNewBalance = $pdo->prepare("SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                     $stmtGetNewBalance->bindParam(1, $supplierId, PDO::PARAM_INT);
                     $stmtGetNewBalance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                     $stmtGetNewBalance->bindParam(3, $branch_id, PDO::PARAM_INT);
                     $stmtGetNewBalance->execute();
                     $newSupplierBalance = $stmtGetNewBalance->fetch(PDO::FETCH_ASSOC)['balance'];
                     
                     // Calculate new supplier balance difference
                     $supplierBalanceDifference = -$newBaseAmount;
                     $newSupplierBalanceValue = $newSupplierBalance + $supplierBalanceDifference;
                     
                     // Update new supplier balance
                     $updateNewSupplierQuery = "UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                     $stmtUpdateNewSupplier = $pdo->prepare($updateNewSupplierQuery);
                     $stmtUpdateNewSupplier->bindParam(1, $newSupplierBalanceValue, PDO::PARAM_STR);
                     $stmtUpdateNewSupplier->bindParam(2, $supplierId, PDO::PARAM_INT);
                     $stmtUpdateNewSupplier->bindParam(3, $tenant_id, PDO::PARAM_INT);
                     $stmtUpdateNewSupplier->bindParam(4, $branch_id, PDO::PARAM_INT);
                     $stmtUpdateNewSupplier->execute();
                     
                     // Create supplier transaction for the new supplier
                     $paymentDate = $payment['created_at'];
                     $insertNewSupplierTransactionQuery = "INSERT INTO supplier_transactions (supplier_id, reference_id, transaction_of, amount, balance, remarks, transaction_date, tenant_id, branch_id)
                                                          VALUES (?, ?, 'additional_payment', ?, ?, ?, NOW(), ?, ?)";
                     $stmtInsertNewSupplierTransaction = $pdo->prepare($insertNewSupplierTransactionQuery);
                     $description = "Additional payment: " . $payment['description'];
                     $stmtInsertNewSupplierTransaction->bindParam(1, $supplierId, PDO::PARAM_INT);
                     $stmtInsertNewSupplierTransaction->bindParam(2, $paymentId, PDO::PARAM_INT);
                     $stmtInsertNewSupplierTransaction->bindParam(3, $newBaseAmount, PDO::PARAM_STR);
                     $stmtInsertNewSupplierTransaction->bindParam(4, $newSupplierBalanceValue, PDO::PARAM_STR);
                     $stmtInsertNewSupplierTransaction->bindParam(5, $description, PDO::PARAM_STR);
                     $stmtInsertNewSupplierTransaction->bindParam(6, $tenant_id, PDO::PARAM_INT);
                     $stmtInsertNewSupplierTransaction->bindParam(7, $branch_id, PDO::PARAM_INT);
                     $stmtInsertNewSupplierTransaction->execute();
                     
                     // Update subsequent new supplier transactions' balances
                     $updateNewSubsequentQuery = "UPDATE supplier_transactions
                                                  SET balance = balance + ?
                                                  WHERE supplier_id = ?
                                                  AND branch_id = ?
                                                 AND id > (
                                                       SELECT id FROM (SELECT id FROM supplier_transactions
                                                      WHERE supplier_id = ?
                                                      AND reference_id = ?
                                                      AND transaction_of = 'additional_payment'
                                                      AND tenant_id = ?
                                                      AND branch_id = ?
                                                       LIMIT 1
                                                   ) AS tmp)";
                     $stmtUpdateNewSubsequent = $pdo->prepare($updateNewSubsequentQuery);
                     $stmtUpdateNewSubsequent->bindParam(1, $supplierBalanceDifference, PDO::PARAM_STR);
                     $stmtUpdateNewSubsequent->bindParam(2, $supplierId, PDO::PARAM_INT);
                     $stmtUpdateNewSubsequent->bindParam(3, $branch_id, PDO::PARAM_INT);
                     $stmtUpdateNewSubsequent->bindParam(4, $supplierId, PDO::PARAM_INT);
                     $stmtUpdateNewSubsequent->bindParam(5, $paymentId, PDO::PARAM_INT);
                     $stmtUpdateNewSubsequent->bindParam(6, $tenant_id, PDO::PARAM_INT);
                     $stmtUpdateNewSubsequent->bindParam(7, $branch_id, PDO::PARAM_INT);
                     $stmtUpdateNewSubsequent->execute();
                     
                 }
             }
         }

         // Handle client changes
         if ($isForClient && $originalClient != $clientId) {
             
             // If original client exists, adjust their balance
             if ($originalClient > 0) {
                 $oldClientQuery = "SELECT client_type, usd_balance, afs_balance FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                 $stmtOldClient = $pdo->prepare($oldClientQuery);
                 $stmtOldClient->bindParam(1, $originalClient, PDO::PARAM_INT);
                 $stmtOldClient->bindParam(2, $tenant_id, PDO::PARAM_INT);
                 $stmtOldClient->bindParam(3, $branch_id, PDO::PARAM_INT);
                 $stmtOldClient->execute();
                 $oldClientData = $stmtOldClient->fetch(PDO::FETCH_ASSOC);
                 
                 if ($oldClientData) {
                      // Get all transactions for the old client related to this payment
                      $getOldClientTransactionsQuery = "SELECT * FROM client_transactions
                                                       WHERE client_id = ?
                                                       AND reference_id = ?
                                                       AND transaction_of = 'additional_payment'
                                                       AND tenant_id = ?
                                                       AND branch_id = ?";
                      $stmtGetOldClientTransactions = $pdo->prepare($getOldClientTransactionsQuery);
                      $stmtGetOldClientTransactions->bindParam(1, $originalClient, PDO::PARAM_INT);
                      $stmtGetOldClientTransactions->bindParam(2, $payment['id'], PDO::PARAM_INT);
                      $stmtGetOldClientTransactions->bindParam(3, $tenant_id, PDO::PARAM_INT);
                      $stmtGetOldClientTransactions->bindParam(4, $branch_id, PDO::PARAM_INT);
                      $stmtGetOldClientTransactions->execute();
                      $oldClientTransactions = $stmtGetOldClientTransactions->fetchAll(PDO::FETCH_ASSOC);
                      
                      // Calculate total amount from old client transactions and get transaction ID
                      $totalOldClientAmount = 0;
                      $oldClientTransactionId = null;
                      foreach ($oldClientTransactions as $transaction) {
                          $totalOldClientAmount += $transaction['amount'];
                          if (!$oldClientTransactionId) {
                              $oldClientTransactionId = $transaction['id'];
                          }
                      }
                      
                      // Update old client balance and subsequent transactions only for regular clients
                      if ($oldClientData['client_type'] === 'regular') {
                          $balanceColumn = ($currency === 'USD') ? 'usd_balance' : 'afs_balance';
                          $updateOldClientQuery = "UPDATE clients SET $balanceColumn = $balanceColumn + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                          $stmtUpdateOldClient = $pdo->prepare($updateOldClientQuery);
                          $stmtUpdateOldClient->bindParam(1, $totalOldClientAmount, PDO::PARAM_STR);
                          $stmtUpdateOldClient->bindParam(2, $originalClient, PDO::PARAM_INT);
                          $stmtUpdateOldClient->bindParam(3, $tenant_id, PDO::PARAM_INT);
                          $stmtUpdateOldClient->bindParam(4, $branch_id, PDO::PARAM_INT);
                          $stmtUpdateOldClient->execute();
                          
                          // Update subsequent old client transactions' balances BEFORE deleting
                          if ($oldClientTransactionId) {
                              $updateOldClientSubsequentQuery = "UPDATE client_transactions
                                                                 SET balance = balance + ?
                                                                 WHERE client_id = ?
                                                                 AND currency = ?
                                                                 AND branch_id = ?
                                                                 AND id > ?
                                                                 AND tenant_id = ?";
                              $stmtUpdateOldClientSubsequent = $pdo->prepare($updateOldClientSubsequentQuery);
                              $stmtUpdateOldClientSubsequent->bindParam(1, $totalOldClientAmount, PDO::PARAM_STR);
                              $stmtUpdateOldClientSubsequent->bindParam(2, $originalClient, PDO::PARAM_INT);
                              $stmtUpdateOldClientSubsequent->bindParam(3, $currency, PDO::PARAM_STR);
                              $stmtUpdateOldClientSubsequent->bindParam(4, $branch_id, PDO::PARAM_INT);
                              $stmtUpdateOldClientSubsequent->bindParam(5, $oldClientTransactionId, PDO::PARAM_INT);
                              $stmtUpdateOldClientSubsequent->bindParam(6, $tenant_id, PDO::PARAM_INT);
                              $stmtUpdateOldClientSubsequent->execute();
                          }
                      }
                      
                      // Delete old client transactions for all client types
                      $deleteOldClientTransactionsQuery = "DELETE FROM client_transactions
                                                          WHERE client_id = ?
                                                          AND reference_id = ?
                                                          AND transaction_of = 'additional_payment'
                                                          AND tenant_id = ?
                                                          AND branch_id = ?";
                      $stmtDeleteOldClientTransactions = $pdo->prepare($deleteOldClientTransactionsQuery);
                      $stmtDeleteOldClientTransactions->bindParam(1, $originalClient, PDO::PARAM_INT);
                      $stmtDeleteOldClientTransactions->bindParam(2, $payment['id'], PDO::PARAM_INT);
                      $stmtDeleteOldClientTransactions->bindParam(3, $tenant_id, PDO::PARAM_INT);
                      $stmtDeleteOldClientTransactions->bindParam(4, $branch_id, PDO::PARAM_INT);
                      $stmtDeleteOldClientTransactions->execute();
                      
                  }
             }
             
             // If new client exists, create new transactions
             if ($clientId > 0) {
                 $newClientQuery = "SELECT client_type, usd_balance, afs_balance FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                 $stmtNewClient = $pdo->prepare($newClientQuery);
                 $stmtNewClient->bindParam(1, $clientId, PDO::PARAM_INT);
                 $stmtNewClient->bindParam(2, $tenant_id, PDO::PARAM_INT);
                 $stmtNewClient->bindParam(3, $branch_id, PDO::PARAM_INT);
                 $stmtNewClient->execute();
                 $newClientData = $stmtNewClient->fetch(PDO::FETCH_ASSOC);
                 
                 if ($newClientData) {
                     // Get balance column for transactions
                     $balanceColumn = ($currency === 'USD') ? 'usd_balance' : 'afs_balance';
                     $newClientBalance = $newClientData[$balanceColumn];
                     
                     // Calculate new client balance difference
                     $clientBalanceDifference = $newSoldAmount;
                     $newClientBalanceValue = $newClientBalance - $clientBalanceDifference;
                     
                     // Update balance only for regular clients
                     if ($newClientData['client_type'] === 'regular') {
                         $updateNewClientQuery = "UPDATE clients SET $balanceColumn = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                         $stmtUpdateNewClient = $pdo->prepare($updateNewClientQuery);
                         $stmtUpdateNewClient->bindParam(1, $newClientBalanceValue, PDO::PARAM_STR);
                         $stmtUpdateNewClient->bindParam(2, $clientId, PDO::PARAM_INT);
                         $stmtUpdateNewClient->bindParam(3, $tenant_id, PDO::PARAM_INT);
                         $stmtUpdateNewClient->bindParam(4, $branch_id, PDO::PARAM_INT);
                         $stmtUpdateNewClient->execute();
                     }
                     
                     // Create client transaction for all client types
                     $paymentDate = $payment['created_at'];
                     $insertNewClientTransactionQuery = "INSERT INTO client_transactions (client_id, reference_id, transaction_of, type, amount, currency, balance, description, created_at, tenant_id, branch_id, receipt)
                                                        VALUES (?, ?, 'additional_payment', 'debit', ?, ?, ?, ?, NOW(), ?, ?, '')";
                     $stmtInsertNewClientTransaction = $pdo->prepare($insertNewClientTransactionQuery);
                     $description = "Additional payment: " . $payment['description'];
                     $stmtInsertNewClientTransaction->bindParam(1, $clientId, PDO::PARAM_INT);
                     $stmtInsertNewClientTransaction->bindParam(2, $paymentId, PDO::PARAM_INT);
                     $stmtInsertNewClientTransaction->bindParam(3, $newSoldAmount, PDO::PARAM_STR);
                     $stmtInsertNewClientTransaction->bindParam(4, $currency, PDO::PARAM_STR);
                     $stmtInsertNewClientTransaction->bindParam(5, $newClientBalanceValue, PDO::PARAM_STR);
                     $stmtInsertNewClientTransaction->bindParam(6, $description, PDO::PARAM_STR);
                     $stmtInsertNewClientTransaction->bindParam(7, $tenant_id, PDO::PARAM_INT);
                     $stmtInsertNewClientTransaction->bindParam(8, $branch_id, PDO::PARAM_INT);
                     $stmtInsertNewClientTransaction->execute();
                     
                     // Update subsequent new client transactions' balances only for regular clients
                     if ($newClientData['client_type'] === 'regular') {
                         $updateNewClientSubsequentQuery = "UPDATE client_transactions
                                                            SET balance = balance - ?
                                                            WHERE client_id = ?
                                                            AND currency = ?
                                                            AND branch_id = ?
                                                            AND id > (
                                                                 SELECT id FROM (SELECT id FROM client_transactions
                                                                WHERE client_id = ?
                                                                AND reference_id = ?
                                                                AND transaction_of = 'additional_payment'
                                                                AND tenant_id = ?
                                                                AND branch_id = ?
                                                                 LIMIT 1
                                                             ) AS tmp)";
                         $stmtUpdateNewClientSubsequent = $pdo->prepare($updateNewClientSubsequentQuery);
                         $stmtUpdateNewClientSubsequent->bindParam(1, $clientBalanceDifference, PDO::PARAM_STR);
                         $stmtUpdateNewClientSubsequent->bindParam(2, $clientId, PDO::PARAM_INT);
                         $stmtUpdateNewClientSubsequent->bindParam(3, $currency, PDO::PARAM_STR);
                         $stmtUpdateNewClientSubsequent->bindParam(4, $branch_id, PDO::PARAM_INT);
                         $stmtUpdateNewClientSubsequent->bindParam(5, $clientId, PDO::PARAM_INT);
                         $stmtUpdateNewClientSubsequent->bindParam(6, $paymentId, PDO::PARAM_INT);
                         $stmtUpdateNewClientSubsequent->bindParam(7, $tenant_id, PDO::PARAM_INT);
                         $stmtUpdateNewClientSubsequent->bindParam(8, $branch_id, PDO::PARAM_INT);
                         $stmtUpdateNewClientSubsequent->execute();
                     }
                     
                 }
             }
         }

         // If this is from a supplier, update supplier balance and subsequent transactions
         if ($isFromSupplier && $supplierId) {
            
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
        }

        // If this is for a client, update client balance and subsequent transactions
        if ($isForClient && $clientId) {
            
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
            }
        }

        // Update the payment record
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
            throw new Exception("Error updating payment: " . $errorInfo[2]);
        }
        

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
            } else {
                error_log("Activity log created");
            }
        }

        // Commit transaction
        $pdo->commit();
        
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
        }
        
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 