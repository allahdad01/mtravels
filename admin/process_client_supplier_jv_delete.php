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


// Get the username
$username = $_SESSION['name'] ?? 'Unknown';
$user_id = $_SESSION['user_id'];

// Database connection
require_once('../includes/db.php');
require_once('../includes/conn.php');

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

// Enable error logging
$log_errors = true;
$error_log = [];

// Begin transaction
$pdo->beginTransaction();

try {
    // Get payment details first
    $paymentStmt = $pdo->prepare("SELECT * FROM jv_payments WHERE id = ? AND tenant_id = ?");
    $paymentStmt->execute([$paymentId, $tenant_id]);
    $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        throw new Exception("Payment not found.");
    }
    
    // First, get the related JV transaction - it's the key to finding client and supplier transactions
    $jvTransStmt = $pdo->prepare("SELECT id FROM jv_transactions WHERE jv_payment_id = ? AND tenant_id = ? LIMIT 1");
    $jvTransStmt->execute([$paymentId, $tenant_id]);
    $jvTrans = $jvTransStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$jvTrans) {
        if ($log_errors) $error_log[] = "No JV transaction found for payment ID {$paymentId}";
    } else {
        $jvTransactionId = $jvTrans['id'];
        if ($log_errors) $error_log[] = "Found JV transaction ID: {$jvTransactionId} for payment ID {$paymentId}";
    }
    
    // Verify this is a client-supplier payment or handle all payment types
    if (isset($payment['client_id']) && isset($payment['supplier_id']) && 
        $payment['client_id'] && $payment['supplier_id']) {
        
        $clientId = $payment['client_id'];
        $supplierId = $payment['supplier_id'];
        
        // Get client current balances and name
        $clientStmt = $pdo->prepare("SELECT name, usd_balance, afs_balance FROM clients WHERE id = ? AND tenant_id = ?");
        $clientStmt->execute([$clientId, $tenant_id]);
        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$client) {
            if ($log_errors) $error_log[] = "Client ID {$clientId} not found";
        }
        
        // Get supplier current balance, currency and name
        $supplierStmt = $pdo->prepare("SELECT name, balance, currency FROM suppliers WHERE id = ? AND tenant_id = ?");
        $supplierStmt->execute([$supplierId, $tenant_id]);
        $supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$supplier) {
            if ($log_errors) $error_log[] = "Supplier ID {$supplierId} not found";
        }
        
        // Calculate amounts for reversal
        $clientAmount = $payment['total_amount'];
        $clientCurrency = $payment['currency'];
        
        // Calculate the amount to revert from supplier based on currency conversion if needed
        $supplierAmount = $payment['total_amount'];
        if (isset($supplier['currency']) && $supplier['currency'] !== $payment['currency']) {
            if ($payment['currency'] === 'USD' && $supplier['currency'] === 'AFS') {
                // Convert USD to AFS
                $supplierAmount = $payment['total_amount'] * $payment['exchange_rate'];
            } else if ($payment['currency'] === 'AFS' && $supplier['currency'] === 'USD') {
                // Convert AFS to USD
                $supplierAmount = $payment['total_amount'] / $payment['exchange_rate'];
            }
        }
        
        // 1. UPDATE CLIENT TRANSACTIONS
        // Find the client transaction using the JV transaction ID as reference_id
        if (isset($jvTransactionId)) {
            $clientTransQuery = "SELECT id, created_at, balance FROM client_transactions 
                WHERE client_id = ? AND transaction_of = 'jv_payment' AND tenant_id = ?
                AND reference_id = ? 
                ORDER BY id DESC LIMIT 1";
            $clientTransStmt = $pdo->prepare($clientTransQuery);
            $clientTransStmt->execute([
                $clientId, 
                $tenant_id,
                $jvTransactionId
            ]);
            $clientTrans = $clientTransStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($clientTrans) {
                $clientTransId = $clientTrans['id'];
                $clientTransDate = $clientTrans['created_at'];
                
                // Get all subsequent client transactions in the same currency
                $laterClientTransQuery = "SELECT id, balance FROM client_transactions 
                    WHERE client_id = ? AND currency = ? AND 
                        (created_at > ? OR (created_at = ? AND id > ?)) 
                    ORDER BY created_at ASC, id ASC";
                $laterClientTransStmt = $pdo->prepare($laterClientTransQuery);
                $laterClientTransStmt->execute([
                    $clientId, 
                    $clientCurrency, 
                    $clientTransDate, 
                    $clientTransDate, 
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
                $deleteClientTransQuery = "DELETE FROM client_transactions WHERE id = ?";
                $deleteClientTransStmt = $pdo->prepare($deleteClientTransQuery);
                $deleteClientTransStmt->execute([$clientTransId]);
                
                if ($log_errors) $error_log[] = "Client transaction ID {$clientTransId} deleted successfully";
            } else {
                // Try a broader search if the specific search failed
                $altClientTransQuery = "SELECT id, description FROM client_transactions 
                    WHERE client_id = ? AND transaction_of = 'jv_payment' AND tenant_id = ?
                    ORDER BY id DESC LIMIT 5";
                $altClientTransStmt = $pdo->prepare($altClientTransQuery);
                $altClientTransStmt->execute([$clientId, $tenant_id]);
                $altClientResults = $altClientTransStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if ($log_errors) {
                    $error_log[] = "Failed to find client transaction for JV transaction ID {$jvTransactionId}";
                    $error_log[] = "Alternative search found " . count($altClientResults) . " potential client transactions";
                    foreach ($altClientResults as $index => $result) {
                        $error_log[] = "Potential client transaction {$index}: ID {$result['id']} - Description: " . 
                            (isset($result['description']) ? substr($result['description'], 0, 50) . "..." : "None");
                    }
                }
            }
        } else {
            // Fallback to searching by description (legacy method)
            $clientTransQuery = "SELECT id, created_at, balance FROM client_transactions 
                WHERE client_id = ? AND transaction_of = 'jv_payment' AND tenant_id = ?
                
                ORDER BY id DESC LIMIT 1";
            $clientTransStmt = $pdo->prepare($clientTransQuery);
            $clientTransStmt->execute([
                $clientId,
                $tenant_id
            ]);
            $clientTrans = $clientTransStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($clientTrans) {
                // Process as above
                $clientTransId = $clientTrans['id'];
                $clientTransDate = $clientTrans['created_at'];
                
                // Get all subsequent client transactions in the same currency
                $laterClientTransQuery = "SELECT id, balance FROM client_transactions 
                    WHERE client_id = ? AND currency = ? AND 
                        (created_at > ? OR (created_at = ? AND id > ?)) 
                    ORDER BY created_at ASC, id ASC";
                $laterClientTransStmt = $pdo->prepare($laterClientTransQuery);
                $laterClientTransStmt->execute([
                    $clientId, 
                    $clientCurrency, 
                    $clientTransDate, 
                    $clientTransDate, 
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
                $deleteClientTransQuery = "DELETE FROM client_transactions WHERE id = ?";
                $deleteClientTransStmt = $pdo->prepare($deleteClientTransQuery);
                $deleteClientTransStmt->execute([$clientTransId]);
                
                if ($log_errors) $error_log[] = "Client transaction ID {$clientTransId} deleted successfully using legacy method";
            } else {
                if ($log_errors) $error_log[] = "Failed to find any client transaction for this JV payment";
            }
        }
        
        // 2. UPDATE SUPPLIER TRANSACTIONS
        // Find the supplier transaction using the JV transaction ID as reference_id
        if (isset($jvTransactionId)) {
            $supplierTransQuery = "SELECT id, transaction_date, balance FROM supplier_transactions 
                WHERE supplier_id = ? AND transaction_of = 'jv_payment' AND tenant_id = ?
                AND reference_id = ? 
                ORDER BY id DESC LIMIT 1";
            $supplierTransStmt = $pdo->prepare($supplierTransQuery);
            $supplierTransStmt->execute([
                $supplierId, 
                $tenant_id,
                $jvTransactionId
            ]);
            $supplierTrans = $supplierTransStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($supplierTrans) {
                $supplierTransId = $supplierTrans['id'];
                $supplierTransDate = $supplierTrans['transaction_date'];
                
                // Get all subsequent supplier transactions
                $laterSupplierTransQuery = "SELECT id, balance FROM supplier_transactions 
                    WHERE supplier_id = ? AND 
                        (transaction_date > ? OR (transaction_date = ? AND id > ?)) AND tenant_id = ?
                    ORDER BY transaction_date ASC, id ASC";
                $laterSupplierTransStmt = $pdo->prepare($laterSupplierTransQuery);
                $laterSupplierTransStmt->execute([
                    $supplierId, 
                    $supplierTransDate, 
                    $supplierTransDate, 
                    $supplierTransId,
                    $tenant_id
                ]);
                
                // Update subsequent transactions by SUBTRACTING the amount
                // (since this was a credit to supplier, we're undoing it by reducing subsequent balances)
                while ($laterTrans = $laterSupplierTransStmt->fetch(PDO::FETCH_ASSOC)) {
                    $newBalance = $laterTrans['balance'] - $supplierAmount;
                    $updateLaterQuery = "UPDATE supplier_transactions SET balance = ? WHERE id = ?";
                    $updateLaterStmt = $pdo->prepare($updateLaterQuery);
                    $updateLaterStmt->execute([$newBalance, $laterTrans['id']]);
                }
                
                // Delete the specific supplier transaction for this JV payment
                $deleteSupplierTransQuery = "DELETE FROM supplier_transactions WHERE id = ?";
                $deleteSupplierTransStmt = $pdo->prepare($deleteSupplierTransQuery);
                $deleteSupplierTransStmt->execute([$supplierTransId]);
                
                if ($log_errors) $error_log[] = "Supplier transaction ID {$supplierTransId} deleted successfully";
            } else {
                // Try a broader search if the specific search failed
                $altSupplierTransQuery = "SELECT id, remarks FROM supplier_transactions 
                    WHERE supplier_id = ? AND transaction_of = 'jv_payment' AND tenant_id = ?
                    ORDER BY id DESC LIMIT 5";
                $altSupplierTransStmt = $pdo->prepare($altSupplierTransQuery);
                $altSupplierTransStmt->execute([$supplierId, $tenant_id]);
                $altSupplierResults = $altSupplierTransStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if ($log_errors) {
                    $error_log[] = "Failed to find supplier transaction for JV transaction ID {$jvTransactionId}";
                    $error_log[] = "Alternative search found " . count($altSupplierResults) . " potential supplier transactions";
                    foreach ($altSupplierResults as $index => $result) {
                        $error_log[] = "Potential supplier transaction {$index}: ID {$result['id']} - Remarks: " . 
                            (isset($result['remarks']) ? substr($result['remarks'], 0, 50) . "..." : "None");
                    }
                }
            }
        } else {
            // Fallback to searching by remarks (legacy method)
            $supplierTransQuery = "SELECT id, transaction_date, balance FROM supplier_transactions 
                WHERE supplier_id = ? AND transaction_of = 'jv_payment' AND tenant_id = ?
                
                ORDER BY id DESC LIMIT 1";
            $supplierTransStmt = $pdo->prepare($supplierTransQuery);
            $supplierTransStmt->execute([
                $supplierId,
                $tenant_id
            ]);
            $supplierTrans = $supplierTransStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($supplierTrans) {
                // Process as above
                $supplierTransId = $supplierTrans['id'];
                $supplierTransDate = $supplierTrans['transaction_date'];
                
                // Get all subsequent supplier transactions
                $laterSupplierTransQuery = "SELECT id, balance FROM supplier_transactions 
                    WHERE supplier_id = ? AND 
                        (transaction_date > ? OR (transaction_date = ? AND id > ?)) AND tenant_id = ?
                    ORDER BY transaction_date ASC, id ASC";
                $laterSupplierTransStmt = $pdo->prepare($laterSupplierTransQuery);
                $laterSupplierTransStmt->execute([
                    $supplierId, 
                    $supplierTransDate, 
                    $supplierTransDate, 
                    $supplierTransId,
                    $tenant_id
                ]);
                
                // Update subsequent transactions by SUBTRACTING the amount
                // (since this was a credit to supplier, we're undoing it by reducing subsequent balances)
                while ($laterTrans = $laterSupplierTransStmt->fetch(PDO::FETCH_ASSOC)) {
                    $newBalance = $laterTrans['balance'] - $supplierAmount;
                    $updateLaterQuery = "UPDATE supplier_transactions SET balance = ? WHERE id = ?";
                    $updateLaterStmt = $pdo->prepare($updateLaterQuery);
                    $updateLaterStmt->execute([$newBalance, $laterTrans['id']]);
                }
                
                // Delete the specific supplier transaction for this JV payment
                $deleteSupplierTransQuery = "DELETE FROM supplier_transactions WHERE id = ?";
                $deleteSupplierTransStmt = $pdo->prepare($deleteSupplierTransQuery);
                $deleteSupplierTransStmt->execute([$supplierTransId]);
                
                if ($log_errors) $error_log[] = "Supplier transaction ID {$supplierTransId} deleted successfully using legacy method";
            } else {
                if ($log_errors) $error_log[] = "Failed to find any supplier transaction for this JV payment";
            }
        }
        
        // 3. ADJUST MAIN BALANCES
        // Revert the client transaction - SUBTRACT the amount since it was a credit transaction
        if ($client && isset($payment['currency'])) {
            if ($payment['currency'] === 'USD') {
                $newUsdBalance = $client['usd_balance'] - $payment['total_amount'];
                
                // Update client balance
                $updateClientStmt = $pdo->prepare("UPDATE clients SET usd_balance = ? WHERE id = ? AND tenant_id = ?");
                $updateClientStmt->execute([$newUsdBalance, $clientId, $tenant_id]);
                
                if ($log_errors) $error_log[] = "Updated client USD balance to {$newUsdBalance}";
            } else {
                $newAfsBalance = $client['afs_balance'] - $payment['total_amount'];
                
                // Update client balance
                $updateClientStmt = $pdo->prepare("UPDATE clients SET afs_balance = ? WHERE id = ? AND tenant_id = ?");
                $updateClientStmt->execute([$newAfsBalance, $clientId, $tenant_id]);
                
                if ($log_errors) $error_log[] = "Updated client AFS balance to {$newAfsBalance}";
            }
        }
        
        // Revert the supplier transaction - SUBTRACT the amount since it was a credit
        if ($supplier) {
            $newSupplierBalance = $supplier['balance'] - $supplierAmount;
            
            // Update supplier balance
            $updateSupplierStmt = $pdo->prepare("UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?");
            $updateSupplierStmt->execute([$newSupplierBalance, $supplierId, $tenant_id]);
            
            if ($log_errors) $error_log[] = "Updated supplier balance to {$newSupplierBalance}";
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
            'total_amount' => $payment['total_amount'] ?? null,
            'exchange_rate' => $payment['exchange_rate'] ?? null
        ];
        
        // Insert activity log
        $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at) 
            VALUES (?, 'delete', 'jv_payments', ?, ?, '{}', ?, ?, NOW()) AND tenant_id = ?");
        
        $old_values_json = json_encode($old_values);
        $activity_log_stmt->execute([$user_id, $paymentId, $old_values_json, $ip_address, $user_agent, $tenant_id]);
        
        // 5. DELETE ANY ASSOCIATED JV TRANSACTION
        $deleteJvTransQuery = "DELETE FROM jv_transactions WHERE jv_payment_id = ? AND tenant_id = ?";
        $deleteJvTransStmt = $pdo->prepare($deleteJvTransQuery);
        $deleteJvTransStmt->execute([$paymentId, $tenant_id]);
        
        $jvTransCount = $deleteJvTransStmt->rowCount();
        if ($log_errors) $error_log[] = "Deleted {$jvTransCount} JV transactions associated with payment ID {$paymentId}";
    }
    
    // Delete the JV payment
    $deleteStmt = $pdo->prepare("DELETE FROM jv_payments WHERE id = ? AND tenant_id = ?");
    $deleteStmt->execute([$paymentId, $tenant_id]);
    
    // Commit any transaction if active
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    
    // Log any errors for debugging
    if ($log_errors && !empty($error_log)) {
        $logMessage = "JV Payment Deletion Log for ID {$paymentId}:\n" . implode("\n", $error_log);
        error_log($logMessage);
    }
    
    $_SESSION['success_message'] = "JV Payment deleted successfully!";
} catch (Exception $e) {
    // If there was an error, rollback any active transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log any collected errors along with the main exception
    if ($log_errors && !empty($error_log)) {
        $logMessage = "JV Payment Deletion Failed for ID {$paymentId}:\n" . implode("\n", $error_log);
        $logMessage .= "\nException: " . $e->getMessage();
        error_log($logMessage);
    } else {
        error_log("Error deleting JV payment: " . $e->getMessage());
    }
    
    $_SESSION['error_message'] = "Error deleting JV payment: " . $e->getMessage();
}

header('Location: jv_payments.php');
exit();
?>