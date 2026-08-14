<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once 'includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();


// Get the username
$username = $_SESSION['name'] ?? 'Unknown';
$user_id = $_SESSION['user_id'];

// Database connection
require_once('../includes/db.php');

// Validate id
$id = isset($_POST['id']) ? DbSecurity::validateInput($_POST['id'], 'int', ['min' => 0]) : null;

// Validate action
$action = isset($_POST['action']) ? DbSecurity::validateInput($_POST['action'], 'string', ['maxlength' => 255]) : null;

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method';
    header('Location: jv_payments.php');
    exit();
}

// Check if action is delete
if (!isset($_POST['action']) || $_POST['action'] !== 'delete') {
    $_SESSION['error_message'] = 'Invalid action';
    header('Location: jv_payments.php');
    exit();
}

// Get the payment ID to delete
$paymentId = intval($_POST['id'] ?? 0);

if ($paymentId <= 0) {
    $_SESSION['error_message'] = 'Invalid payment ID';
    header('Location: jv_payments.php');
    exit();
}

// Begin transaction
$pdo->beginTransaction();

try {
    // Get payment details first
    $paymentStmt = $pdo->prepare("SELECT * FROM jv_payments WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $paymentStmt->execute([$paymentId, $tenant_id, $branch_id]);
    $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        throw new Exception("Payment not found.");
    }
    
    // First, get the related JV transaction - it's the key to finding client and supplier transactions
    $jvTransStmt = $pdo->prepare("SELECT id FROM jv_transactions WHERE jv_payment_id = ? AND tenant_id = ? AND branch_id = ? LIMIT 1");
    $jvTransStmt->execute([$paymentId, $tenant_id, $branch_id]);
    $jvTrans = $jvTransStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$jvTrans) {
        // No JV transaction found
    } else {
        $jvTransactionId = $jvTrans['id'];
    }
    
    // Verify this is a client-supplier payment or handle all payment types
    if (isset($payment['client_id']) && isset($payment['supplier_id']) && 
        $payment['client_id'] && $payment['supplier_id']) {
        
        $clientId = $payment['client_id'];
        $supplierId = $payment['supplier_id'];
        
        // Get client current balances and name
        $clientStmt = $pdo->prepare("SELECT name, usd_balance, afs_balance FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $clientStmt->execute([$clientId, $tenant_id, $branch_id]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$client) {
            // Client not found
        }
        
        // Get supplier current balance, currency and name
        $supplierStmt = $pdo->prepare("SELECT name, balance, currency FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $supplierStmt->execute([$supplierId, $tenant_id, $branch_id]);
        $supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$supplier) {
            // Supplier not found
        }
        
        // Calculate amounts for reversal
        // Client was credited in the balance currency (fallback: payment currency for legacy rows)
        $clientCurrency = $payment['balance_currency'] ?: $payment['currency'];
        $oldExchangeRate = floatval($payment['exchange_rate'] ?? 0);
        $clientAmount = floatval($payment['total_amount']);
        if ($clientCurrency === $payment['currency']) {
            $clientAmount = floatval($payment['total_amount']);                      // same currency
        } elseif ($clientCurrency === 'USD') {
            $clientAmount = floatval($payment['total_amount']) / ($oldExchangeRate ?: 1);      // 1 USD = X <payment>
        } elseif ($payment['currency'] === 'USD') {
            $clientAmount = floatval($payment['total_amount']) * ($oldExchangeRate ?: 1);      // 1 USD = X AFS
        } else {
            $clientAmount = floatval($payment['total_amount']) / ($oldExchangeRate ?: 1);      // 1 AFS = X <payment>
        }

        // Calculate the amount to revert from supplier (payment → supplier currency conversion)
        $supplierAmount = floatval($payment['total_amount']);
        if (isset($supplier['currency'])) {
            $supplierRate = (isset($payment['supplier_rate']) && $payment['supplier_rate'] > 0) ? floatval($payment['supplier_rate']) : floatval($payment['exchange_rate'] ?? 0);
            if ($payment['currency'] !== $supplier['currency'] && $supplierRate > 0) {
                $pairFrom = ($payment['currency'] === 'DARHAM') ? 'AED' : $payment['currency'];
                $pairTo = ($supplier['currency'] === 'DARHAM') ? 'AED' : $supplier['currency'];
                $dividePairs = ['AFS->AED', 'AFS->EUR', 'AFS->USD', 'AED->EUR', 'AED->USD', 'EUR->USD', 'AFS->SAR', 'SAR->USD', 'SAR->EUR'];
                $supplierAmount = in_array("{$pairFrom}->{$pairTo}", $dividePairs) ? $supplierAmount / $supplierRate : $supplierAmount * $supplierRate;
            }
        }
        
        // 1. UPDATE CLIENT TRANSACTIONS
        // Find the client transaction using the JV transaction ID as reference_id
        if (isset($jvTransactionId)) {
            $clientTransQuery = "SELECT id, created_at, balance FROM client_transactions
                WHERE client_id = ? AND transaction_of = 'jv_payment' AND tenant_id = ? AND branch_id = ?
                AND reference_id = ?
                ORDER BY id DESC LIMIT 1";
            $clientTransStmt = $pdo->prepare($clientTransQuery);
            $clientTransStmt->execute([
                $clientId,
                $tenant_id,
                $branch_id,
                $jvTransactionId
            ]);
            $clientTrans = $clientTransStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($clientTrans) {
                $clientTransId = $clientTrans['id'];
                $clientTransDate = $clientTrans['created_at'];
                
                // Get all subsequent client transactions in the same currency
                $laterClientTransQuery = "SELECT id, balance FROM client_transactions
                    WHERE client_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ? AND
                        id > ?
                    ORDER BY id ASC";
                $laterClientTransStmt = $pdo->prepare($laterClientTransQuery);
                $laterClientTransStmt->execute([
                    $clientId,
                    $clientCurrency,
                    $tenant_id,
                    $branch_id,
                    $clientTransId
                ]);
                
                // Update subsequent transactions by SUBTRACTING the amount
                // (since this was a credit transaction, we need to reduce next balances)
                while ($laterTrans = $laterClientTransStmt->fetch(PDO::FETCH_ASSOC)) {
                    $newBalance = $laterTrans['balance'] - $clientAmount;
                    $updateLaterQuery = "UPDATE client_transactions SET balance = ? WHERE id = ?";
                    $updateLaterStmt = $pdo->prepare($updateLaterQuery);
                    $updateLaterStmt->execute([$newBalance, $laterTrans['id']]);
                }
                
                // Delete the specific client transaction for this JV payment
                $deleteClientTransQuery = "DELETE FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $deleteClientTransStmt = $pdo->prepare($deleteClientTransQuery);
                $deleteClientTransStmt->execute([$clientTransId, $tenant_id, $branch_id]);
            } else {
                // Try a broader search if the specific search failed
                $altClientTransQuery = "SELECT id, description FROM client_transactions
                    WHERE client_id = ? AND transaction_of = 'jv_payment' AND tenant_id = ? AND branch_id = ?
                    ORDER BY id DESC LIMIT 5";
                $altClientTransStmt = $pdo->prepare($altClientTransQuery);
                $altClientTransStmt->execute([$clientId, $tenant_id, $branch_id]);
                $altClientResults = $altClientTransStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            // Fallback to searching by description (legacy method)
            $clientTransQuery = "SELECT id, created_at, balance FROM client_transactions
                WHERE client_id = ? AND transaction_of = 'jv_payment' AND tenant_id = ? AND branch_id = ?

                ORDER BY id DESC LIMIT 1";
            $clientTransStmt = $pdo->prepare($clientTransQuery);
            $clientTransStmt->execute([
                $clientId,
                $tenant_id,
                $branch_id
            ]);
            $clientTrans = $clientTransStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($clientTrans) {
                // Process as above
                $clientTransId = $clientTrans['id'];
                $clientTransDate = $clientTrans['created_at'];
                
                // Get all subsequent client transactions in the same currency
                $laterClientTransQuery = "SELECT id, balance FROM client_transactions
                    WHERE client_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ? AND
                          id > ?
                    ORDER BY id ASC";
                $laterClientTransStmt = $pdo->prepare($laterClientTransQuery);
                $laterClientTransStmt->execute([
                    $clientId,
                    $clientCurrency,
                    $tenant_id,
                    $branch_id,
                    $clientTransId
                ]);
                
                // Update subsequent transactions by SUBTRACTING the amount
                // (since this was a credit transaction, we need to reduce next balances)
                while ($laterTrans = $laterClientTransStmt->fetch(PDO::FETCH_ASSOC)) {
                    $newBalance = $laterTrans['balance'] - $clientAmount;
                    $updateLaterQuery = "UPDATE client_transactions SET balance = ? WHERE id = ? And tenant_id = ? And branch_id = ?";
                    $updateLaterStmt = $pdo->prepare($updateLaterQuery);
                    $updateLaterStmt->execute([$newBalance, $laterTrans['id'], $tenant_id, $branch_id]);
                }
                
                // Delete the specific client transaction for this JV payment
                $deleteClientTransQuery = "DELETE FROM client_transactions WHERE id = ? And tenant_id = ? And branch_id = ?";
                $deleteClientTransStmt = $pdo->prepare($deleteClientTransQuery);
                $deleteClientTransStmt->execute([$clientTransId, $tenant_id, $branch_id]);
                } else {
                // No client transaction found
                }
        }
        
        // 2. UPDATE SUPPLIER TRANSACTIONS
        // Find the supplier transaction using the JV transaction ID as reference_id
        if (isset($jvTransactionId)) {
            $supplierTransQuery = "SELECT id, transaction_date, balance FROM supplier_transactions
                WHERE supplier_id = ? AND transaction_of = 'jv_payment' AND tenant_id = ? AND branch_id = ?
                AND reference_id = ?
                ORDER BY id DESC LIMIT 1";
            $supplierTransStmt = $pdo->prepare($supplierTransQuery);
            $supplierTransStmt->execute([
                $supplierId,
                $tenant_id,
                $branch_id,
                $jvTransactionId
            ]);
            $supplierTrans = $supplierTransStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($supplierTrans) {
                $supplierTransId = $supplierTrans['id'];
                $supplierTransDate = $supplierTrans['transaction_date'];
                
                // Get all subsequent supplier transactions
                $laterSupplierTransQuery = "SELECT id, balance FROM supplier_transactions
                    WHERE supplier_id = ? AND tenant_id = ? AND branch_id = ? AND
                        id > ?
                    ORDER BY id ASC";
                $laterSupplierTransStmt = $pdo->prepare($laterSupplierTransQuery);
                $laterSupplierTransStmt->execute([
                    $supplierId,
                    $tenant_id,
                    $branch_id,
                    $supplierTransId
                ]);
                
                // Update subsequent transactions by SUBTRACTING the amount
                // (since this was a credit to supplier, we're undoing it by reducing subsequent balances)
                while ($laterTrans = $laterSupplierTransStmt->fetch(PDO::FETCH_ASSOC)) {
                    $newBalance = $laterTrans['balance'] - $supplierAmount;
                    $updateLaterQuery = "UPDATE supplier_transactions SET balance = ? WHERE id = ? And tenant_id = ? And branch_id = ?";
                    $updateLaterStmt = $pdo->prepare($updateLaterQuery);
                    $updateLaterStmt->execute([$newBalance, $laterTrans['id'], $tenant_id, $branch_id]);
                }
                
                // Delete the specific supplier transaction for this JV payment
                $deleteSupplierTransQuery = "DELETE FROM supplier_transactions WHERE id = ? And tenant_id = ? And branch_id = ?";
                $deleteSupplierTransStmt = $pdo->prepare($deleteSupplierTransQuery);
                $deleteSupplierTransStmt->execute([$supplierTransId, $tenant_id, $branch_id]);
            } else {
                // Try a broader search if the specific search failed
                $altSupplierTransQuery = "SELECT id, remarks FROM supplier_transactions
                    WHERE supplier_id = ? AND transaction_of = 'jv_payment' AND tenant_id = ? AND branch_id = ?
                    ORDER BY id DESC LIMIT 5";
                $altSupplierTransStmt = $pdo->prepare($altSupplierTransQuery);
                $altSupplierTransStmt->execute([$supplierId, $tenant_id, $branch_id]);
                $altSupplierResults = $altSupplierTransStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            // Fallback to searching by remarks (legacy method)
            $supplierTransQuery = "SELECT id, transaction_date, balance FROM supplier_transactions
                WHERE supplier_id = ? AND transaction_of = 'jv_payment' AND tenant_id = ? AND branch_id = ?

                ORDER BY id DESC LIMIT 1";
            $supplierTransStmt = $pdo->prepare($supplierTransQuery);
            $supplierTransStmt->execute([
                $supplierId,
                $tenant_id,
                $branch_id
            ]);
            $supplierTrans = $supplierTransStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($supplierTrans) {
                // Process as above
                $supplierTransId = $supplierTrans['id'];
                $supplierTransDate = $supplierTrans['transaction_date'];
                
                // Get all subsequent supplier transactions
                $laterSupplierTransQuery = "SELECT id, balance FROM supplier_transactions
                    WHERE supplier_id = ? AND tenant_id = ? AND branch_id = ? AND
                        id > ?
                    ORDER BY id ASC";
                $laterSupplierTransStmt = $pdo->prepare($laterSupplierTransQuery);
                $laterSupplierTransStmt->execute([
                    $supplierId,
                    $tenant_id,
                    $branch_id,
                    $supplierTransId
                ]);
                
                // Update subsequent transactions by SUBTRACTING the amount
                // (since this was a credit to supplier, we're undoing it by reducing subsequent balances)
                while ($laterTrans = $laterSupplierTransStmt->fetch(PDO::FETCH_ASSOC)) {
                    $newBalance = $laterTrans['balance'] - $supplierAmount;
                    $updateLaterQuery = "UPDATE supplier_transactions SET balance = ? WHERE id = ? And tenant_id = ? And branch_id = ?";
                    $updateLaterStmt = $pdo->prepare($updateLaterQuery);
                    $updateLaterStmt->execute([$newBalance, $laterTrans['id'], $tenant_id, $branch_id]);
                }
                
                // Delete the specific supplier transaction for this JV payment
                $deleteSupplierTransQuery = "DELETE FROM supplier_transactions WHERE id = ? And tenant_id = ? And branch_id = ?";
                $deleteSupplierTransStmt = $pdo->prepare($deleteSupplierTransQuery);
                $deleteSupplierTransStmt->execute([$supplierTransId, $tenant_id, $branch_id]);
                } else {
                // No supplier transaction found
                }
        }
        
        // 3. ADJUST MAIN BALANCES
        // Revert the client transaction - SUBTRACT the credit from the balance currency column
        if ($client && $clientCurrency) {
            $clientField = ($clientCurrency === 'USD') ? 'usd_balance' : 'afs_balance';
            $newClientBalance = $client[$clientField] - $clientAmount;
            
            // Update client balance
            $updateClientStmt = $pdo->prepare("UPDATE clients SET {$clientField} = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $updateClientStmt->execute([$newClientBalance, $clientId, $tenant_id, $branch_id]);
        }
        
        // Revert the supplier transaction - SUBTRACT the amount since it was a credit
        if ($supplier) {
            $newSupplierBalance = $supplier['balance'] - $supplierAmount;
            
            // Update supplier balance
            $updateSupplierStmt = $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $updateSupplierStmt->execute([$newSupplierBalance, $supplierId, $tenant_id, $branch_id]);
        }
        
        // 4. RECORD DELETION AUDIT TRAIL
        // Get username for logging
        $username = $_SESSION['name'] ?? 'Unknown User';
        
        // Add activity logging
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Prepare old values data
        $old_values = [
            'jv_payment_id' => $paymentId,
            'client_id' => $clientId ?? null,
            'client_name' => $client['name'] ?? null,
            'supplier_id' => $supplierId ?? null,
            'supplier_name' => $supplier['name'] ?? null,
            'currency' => $payment['currency'] ?? null,
            'balance_currency' => $clientCurrency ?? null,
            'total_amount' => $payment['total_amount'] ?? null,
            'exchange_rate' => $payment['exchange_rate'] ?? null,
            'supplier_rate' => $payment['supplier_rate'] ?? null
        ];
        
        // Insert activity log
        $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'delete', 'jv_payments', ?, ?, '{}', ?, ?, NOW(), ?, ?)");

        $old_values_json = json_encode($old_values);
        $activity_log_stmt->execute([$user_id, $paymentId, $old_values_json, $ip_address, $user_agent, $tenant_id, $branch_id]);
        
        // 5. DELETE ANY ASSOCIATED JV TRANSACTION
        $deleteJvTransQuery = "DELETE FROM jv_transactions WHERE jv_payment_id = ? AND tenant_id = ? AND branch_id = ?";
        $deleteJvTransStmt = $pdo->prepare($deleteJvTransQuery);
        $deleteJvTransStmt->execute([$paymentId, $tenant_id, $branch_id]);
    }
    
    // Delete the JV payment
    $deleteStmt = $pdo->prepare("DELETE FROM jv_payments WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $deleteStmt->execute([$paymentId, $tenant_id, $branch_id]);
    
    // Commit any transaction if active
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    
    $_SESSION['success_message'] = "JV Payment deleted successfully!";
} catch (Exception $e) {
     // If there was an error, rollback any active transaction
     if ($pdo->inTransaction()) {
         $pdo->rollBack();
     }
     
     $_SESSION['error_message'] = "Error deleting JV payment: " . $e->getMessage();
 }

header('Location: jv_payments.php');
exit();
?>