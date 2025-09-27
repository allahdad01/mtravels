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

// Validate remarks
$remarks = isset($_POST['remarks']) ? DbSecurity::validateInput($_POST['remarks'], 'string', ['maxlength' => 255]) : null;

// Validate receipt
$receipt = isset($_POST['receipt']) ? DbSecurity::validateInput($_POST['receipt'], 'string', ['maxlength' => 255]) : null;

// Validate exchange_rate
$exchange_rate = isset($_POST['exchange_rate']) ? DbSecurity::validateInput($_POST['exchange_rate'], 'float', ['min' => 0]) : null;

// Validate total_amount
$total_amount = isset($_POST['total_amount']) ? DbSecurity::validateInput($_POST['total_amount'], 'float', ['min' => 0]) : null;

// Validate currency
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : null;

// Validate jv_name
$jv_name = isset($_POST['jv_name']) ? DbSecurity::validateInput($_POST['jv_name'], 'string', ['maxlength' => 255]) : null;

// Validate id
$id = isset($_POST['id']) ? DbSecurity::validateInput($_POST['id'], 'int', ['min' => 0]) : null;

// Define redirect URL
$redirect_url = 'jv-payments.php';

$paymentId = $_POST['id'];
                
                // Get the original payment details first
                $origStmt = $pdo->prepare("SELECT * FROM jv_payments WHERE id = ? AND tenant_id = ?");
                $origStmt->execute([$paymentId, $tenant_id]);
                $originalPayment = $origStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$originalPayment) {
                    $_SESSION['error_message'] = "Payment not found.";
                    header("Location: {$redirect_url}");
                    exit();
                }
                
                // For client-to-supplier payments, we need special handling
                if (isset($originalPayment['client_id']) && isset($originalPayment['supplier_id']) && 
                    $originalPayment['client_id'] && $originalPayment['supplier_id']) {
                    try {
                        // Begin transaction
                        $pdo->beginTransaction();
                        
                        // Get client and supplier IDs
                        $clientId = $originalPayment['client_id'];
                        $supplierId = $originalPayment['supplier_id'];
                        
                        // Get the updated values from the form
                        $jvName = $_POST['jv_name'];
                        $currency = $_POST['currency'];
                        $totalAmount = $_POST['total_amount'];
                        $exchangeRate = $_POST['exchange_rate'];
                        $receipt = $_POST['receipt'];
                        $remarks = $_POST['remarks'];
                        
                        // Get client information
                        $clientQuery = "SELECT name, usd_balance, afs_balance FROM clients WHERE id = ? AND tenant_id = ?";
                        $clientStmt = $pdo->prepare($clientQuery);
                        $clientStmt->execute([$clientId, $tenant_id]);
                        $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$client) {
                            throw new Exception('Client not found');
                        }
                        
                        // Get supplier information
                        $supplierQuery = "SELECT name, balance, currency as supplier_currency FROM suppliers WHERE id = ? AND tenant_id = ?";
                        $supplierStmt = $pdo->prepare($supplierQuery);
                        $supplierStmt->execute([$supplierId, $tenant_id]);
                        $supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$supplier) {
                            throw new Exception('Supplier not found');
                        }
                        
                        // Process client balance change
                        // Get client information based on currency
                        if ($currency === 'USD') {
                            $clientBalanceField = 'usd_balance';
                        } else {
                            $clientBalanceField = 'afs_balance';
                        }
                        
                        // Calculate the difference in amount
                        $originalClientAmount = $originalPayment['total_amount'];
                        $amountDifference = $totalAmount - $originalClientAmount;
                        
                        // Update client balance based on the difference
                        $updateClientQuery = "UPDATE clients SET {$clientBalanceField} = {$clientBalanceField} + ? WHERE id = ?";
                        $updateClientStmt = $pdo->prepare($updateClientQuery);
                        $updateClientStmt->execute([$amountDifference, $clientId]);
                        
                        // Process supplier balance change
                        // First, determine the original supplier amount with currency conversion if needed
                        $originalSupplierAmount = $originalPayment['total_amount'];
                        if ($supplier['supplier_currency'] !== $originalPayment['currency']) {
                            if ($originalPayment['currency'] === 'USD' && $supplier['supplier_currency'] === 'AFS') {
                                $originalSupplierAmount = $originalPayment['total_amount'] * $originalPayment['exchange_rate'];
                            } else if ($originalPayment['currency'] === 'AFS' && $supplier['supplier_currency'] === 'USD') {
                                $originalSupplierAmount = $originalPayment['total_amount'] / $originalPayment['exchange_rate'];
                            }
                        }
                        
                        // Calculate new supplier amount with currency conversion if needed
                        $newSupplierAmount = $totalAmount;
                        if ($supplier['supplier_currency'] !== $currency) {
                            if ($currency === 'USD' && $supplier['supplier_currency'] === 'AFS') {
                                $newSupplierAmount = $totalAmount * $exchangeRate;
                            } else if ($currency === 'AFS' && $supplier['supplier_currency'] === 'USD') {
                                $newSupplierAmount = $totalAmount / $exchangeRate;
                            }
                        }
                        
                        // Calculate the difference for supplier
                        $supplierAmountDiff = $newSupplierAmount - $originalSupplierAmount;
                        
                        // Update supplier balance directly
                        $updateSupplierQuery = "UPDATE suppliers SET balance = balance + ? WHERE id = ?";
                        $updateSupplierStmt = $pdo->prepare($updateSupplierQuery);
                        $updateSupplierStmt->execute([$supplierAmountDiff, $supplierId]);
                        
                        // Calculate the difference for client transactions
                        // If amounts have the same currency, straightforward difference
                        if ($originalPayment['currency'] === $currency) {
                            $clientAmountDiff = $totalAmount - $originalPayment['total_amount'];
                        } else {
                            // For currency changes, we'll handle each currency separately
                            // by determining sign based on credit/debit type
                            if ($originalPayment['currency'] === 'USD') {
                                $clientUsdDiff = $originalPayment['total_amount']; // Positive (adding back to USD)
                                $clientAfsDiff = -$totalAmount; // Negative (deducting from AFS)
                            } else {
                                $clientAfsDiff = $originalPayment['total_amount']; // Positive (adding back to AFS)
                                $clientUsdDiff = -$totalAmount; // Negative (deducting from USD)
                            }
                        }
                        
                        // 1. UPDATE CLIENT TRANSACTIONS
                        // Find the specific client transaction record for this JV payment
                        $clientTransQuery = "SELECT id, created_at, balance, currency FROM client_transactions 
                            WHERE client_id = ? AND transaction_of = 'jv_payment' AND tenant_id = ?
                           
                            ORDER BY id DESC LIMIT 1";
                        $clientTransStmt = $pdo->prepare($clientTransQuery);
                        $clientTransStmt->execute([
                            $clientId,
                            $tenant_id
                        ]);
                        $clientTrans = $clientTransStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($clientTrans) {
                            $clientTransId = $clientTrans['id'];
                            $clientTransDate = $clientTrans['created_at'];
                            
                            // First, update the transaction amount
                            $updateClientTransQuery = "UPDATE client_transactions SET amount = ? WHERE id = ? AND tenant_id = ?";
                            $updateClientTransStmt = $pdo->prepare($updateClientTransQuery);
                            $updateClientTransStmt->execute([$totalAmount, $clientTransId, $tenant_id]);
                            
                            // Now update the balance separately - exactly like in update_transaction.php
                            $currentClientTransBalance = $clientTrans['balance']; // Use the original balance from fetch
                            // Calculate new balance for this transaction
                            if ($originalPayment['currency'] === $currency) {
                                // Update the transaction balance
                                $newBalance = $currentClientTransBalance + $amountDifference;
                                $updateClientTransBalanceQuery = "UPDATE client_transactions SET balance = ? WHERE id = ? AND tenant_id = ?";
                                $updateClientTransBalanceStmt = $pdo->prepare($updateClientTransBalanceQuery);
                                $updateClientTransBalanceStmt->execute([$newBalance, $clientTransId, $tenant_id]);
                                
                                // Store the calculated balance to reuse it later if needed
                                $clientFinalBalance = $newBalance;
                                
                                // Get all subsequent transactions (same currency) to update their balances
                                $laterClientTransQuery = "SELECT id, balance FROM client_transactions 
                                    WHERE client_id = ? AND currency = ? AND tenant_id = ?
                                          (created_at > ? OR (created_at = ? AND id > ?)) 
                                          AND tenant_id = ?
                                    ORDER BY created_at ASC, id ASC";
                                $laterClientTransStmt = $pdo->prepare($laterClientTransQuery);
                                $laterClientTransStmt->execute([
                                    $clientId, 
                                    $currency, 
                                    $clientTransDate, 
                                    $clientTransDate, 
                                    $clientTransId,
                                    $tenant_id
                                ]);
                                
                                // Update all subsequent transactions
                                while ($laterTrans = $laterClientTransStmt->fetch(PDO::FETCH_ASSOC)) {
                                    $newBalance = $laterTrans['balance'] + $amountDifference;
                                    $updateLaterTransQuery = "UPDATE client_transactions SET balance = ? WHERE id = ? AND tenant_id = ?";
                                    $updateLaterTransStmt = $pdo->prepare($updateLaterTransQuery);
                                    $updateLaterTransStmt->execute([$newBalance, $laterTrans['id'], $tenant_id]);
                                }
                            } else {
                                // For currency changes, we need to update both USD and AFS transactions
                                
                                // 1. First, revert the impact on original currency transactions
                                if ($originalPayment['currency'] === 'USD') {
                                    // Update this transaction if it's in USD
                                    if ($clientTrans['currency'] === 'USD') {
                                        // First update amount
                                        $updateUsdAmountQuery = "UPDATE client_transactions SET amount = ? WHERE id = ? AND tenant_id = ?";
                                        $updateUsdAmountStmt = $pdo->prepare($updateUsdAmountQuery);
                                        $updateUsdAmountStmt->execute([$totalAmount, $clientTransId, $tenant_id]);
                                        
                                        // Then update balance - using balance from the fetched transaction
                                        $newUsdBalance = $clientTrans['balance'] + $clientUsdDiff;
                                        $updateUsdTransQuery = "UPDATE client_transactions SET balance = ? WHERE id = ? AND tenant_id = ?";
                                        $updateUsdTransStmt = $pdo->prepare($updateUsdTransQuery);
                                        $updateUsdTransStmt->execute([$newUsdBalance, $clientTransId, $tenant_id]);
                                        
                                        // Store for later use
                                        if ($currency === 'USD') {
                                            $clientFinalBalance = $newUsdBalance;
                                        }
                                    }
                                    
                                    // Update all subsequent USD transactions
                                    $laterUsdTransQuery = "SELECT id, balance FROM client_transactions 
                                        WHERE client_id = ? AND currency = 'USD' AND tenant_id = ?
                                              (created_at > ? OR (created_at = ? AND id > ?)) 
                                        ORDER BY created_at ASC, id ASC";
                                    $laterUsdTransStmt = $pdo->prepare($laterUsdTransQuery);
                                    $laterUsdTransStmt->execute([
                                        $clientId, 
                                        $clientTransDate, 
                                        $clientTransDate, 
                                        $clientTransId,
                                        $tenant_id
                                    ]);
                                    
                                    while ($laterUsdTrans = $laterUsdTransStmt->fetch(PDO::FETCH_ASSOC)) {
                                        $newUsdBalance = $laterUsdTrans['balance'] + $clientUsdDiff;
                                        $updateLaterUsdQuery = "UPDATE client_transactions SET balance = ? WHERE id = ? AND tenant_id = ?";
                                        $updateLaterUsdStmt = $pdo->prepare($updateLaterUsdQuery);
                                        $updateLaterUsdStmt->execute([$newUsdBalance, $laterUsdTrans['id'], $tenant_id]);
                                    }
                                    
                                    // 2. Now impact the new currency (AFS) transactions
                                    // Find the earliest AFS transaction after this one to start updating
                                    $earliestAfsTransQuery = "SELECT id, balance FROM client_transactions 
                                        WHERE client_id = ? AND currency = 'AFS' AND tenant_id = ? 
                                              created_at >= ?
                                        ORDER BY created_at ASC, id ASC LIMIT 1";
                                    $earliestAfsTransStmt = $pdo->prepare($earliestAfsTransQuery);
                                    $earliestAfsTransStmt->execute([$clientId, $clientTransDate, $tenant_id]);
                                    $earliestAfsTrans = $earliestAfsTransStmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($earliestAfsTrans) {
                                        // Update all AFS transactions from this point forward
                                        $laterAfsTransQuery = "SELECT id, balance FROM client_transactions 
                                            WHERE client_id = ? AND currency = 'AFS' AND tenant_id = ?
                                                  created_at >= ?
                                            ORDER BY created_at ASC, id ASC";

                                        $laterAfsTransStmt = $pdo->prepare($laterAfsTransQuery);
                                        $laterAfsTransStmt->execute([$clientId, $clientTransDate, $tenant_id]);
                                        
                                        while ($laterAfsTrans = $laterAfsTransStmt->fetch(PDO::FETCH_ASSOC)) {
                                            $newAfsBalance = $laterAfsTrans['balance'] + $clientAfsDiff;
                                            $updateLaterAfsQuery = "UPDATE client_transactions SET balance = ? WHERE id = ? AND tenant_id = ?";
                                            $updateLaterAfsStmt = $pdo->prepare($updateLaterAfsQuery);
                                            $updateLaterAfsStmt->execute([$newAfsBalance, $laterAfsTrans['id'], $tenant_id]);
                                        }
                                    }
                                } else {
                                    // Original currency was AFS, new is USD
                                    // Same pattern but reversed
                                    
                                    // Update this transaction if it's in AFS
                                    if ($clientTrans['currency'] === 'AFS') {
                                        // First update amount
                                        $updateAfsAmountQuery = "UPDATE client_transactions SET amount = ? WHERE id = ? AND tenant_id = ?";
                                        $updateAfsAmountStmt = $pdo->prepare($updateAfsAmountQuery);
                                        $updateAfsAmountStmt->execute([$totalAmount, $clientTransId, $tenant_id]);
                                        
                                        // Then update balance - using balance from the fetched transaction
                                        $newAfsBalance = $clientTrans['balance'] + $clientAfsDiff;
                                        $updateAfsTransQuery = "UPDATE client_transactions SET balance = ? WHERE id = ? AND tenant_id = ?";
                                        $updateAfsTransStmt = $pdo->prepare($updateAfsTransQuery);
                                        $updateAfsTransStmt->execute([$newAfsBalance, $clientTransId, $tenant_id]);
                                        
                                        // Store for later use
                                        if ($currency === 'AFS') {
                                            $clientFinalBalance = $newAfsBalance;
                                        }
                                    }
                                    
                                    // Update all subsequent AFS transactions
                                    $laterAfsTransQuery = "SELECT id, balance FROM client_transactions 
                                        WHERE client_id = ? AND currency = 'AFS' AND tenant_id = ?
                                              (created_at > ? OR (created_at = ? AND id > ?)) 
                                        ORDER BY created_at ASC, id ASC";
                                    $laterAfsTransStmt = $pdo->prepare($laterAfsTransQuery);
                                    $laterAfsTransStmt->execute([
                                        $clientId, 
                                        $clientTransDate, 
                                        $clientTransDate, 
                                        $clientTransId,
                                        $tenant_id
                                    ]);
                                    
                                    while ($laterAfsTrans = $laterAfsTransStmt->fetch(PDO::FETCH_ASSOC)) {
                                        $newAfsBalance = $laterAfsTrans['balance'] + $clientAfsDiff;
                                        $updateLaterAfsQuery = "UPDATE client_transactions SET balance = ? WHERE id = ? AND tenant_id = ?";
                                        $updateLaterAfsStmt = $pdo->prepare($updateLaterAfsQuery);
                                        $updateLaterAfsStmt->execute([$newAfsBalance, $laterAfsTrans['id'], $tenant_id]);
                                    }
                                    
                                    // Update USD transactions
                                    $earliestUsdTransQuery = "SELECT id, balance FROM client_transactions 
                                        WHERE client_id = ? AND currency = 'USD' AND tenant_id = ?
                                              created_at >= ?
                                        ORDER BY created_at ASC, id ASC LIMIT 1";
                                    $earliestUsdTransStmt = $pdo->prepare($earliestUsdTransQuery);
                                    $earliestUsdTransStmt->execute([$clientId, $clientTransDate, $tenant_id]);
                                    $earliestUsdTrans = $earliestUsdTransStmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($earliestUsdTrans) {
                                        // Update all USD transactions from this point forward
                                        $laterUsdTransQuery = "SELECT id, balance FROM client_transactions 
                                            WHERE client_id = ? AND currency = 'USD' AND tenant_id = ?
                                                  created_at >= ?
                                            ORDER BY created_at ASC, id ASC";
                                        $laterUsdTransStmt = $pdo->prepare($laterUsdTransQuery);
                                        $laterUsdTransStmt->execute([$clientId, $clientTransDate, $tenant_id]);
                                        
                                        while ($laterUsdTrans = $laterUsdTransStmt->fetch(PDO::FETCH_ASSOC)) {
                                            $newUsdBalance = $laterUsdTrans['balance'] + $clientUsdDiff;
                                            $updateLaterUsdQuery = "UPDATE client_transactions SET balance = ? WHERE id = ? AND tenant_id = ?";
                                            $updateLaterUsdStmt = $pdo->prepare($updateLaterUsdQuery);
                                            $updateLaterUsdStmt->execute([$newUsdBalance, $laterUsdTrans['id'], $tenant_id]);
                                        }
                                    }
                                }
                            }
                        }
                        
                        // 2. UPDATE SUPPLIER TRANSACTIONS
                        // Find the specific supplier transaction record for this JV payment
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
                            $supplierTransId = $supplierTrans['id'];
                            $supplierTransDate = $supplierTrans['transaction_date'];
                            
                            // First, update the transaction amount
                            $updateSupplierAmountQuery = "UPDATE supplier_transactions SET amount = ? WHERE id = ? AND tenant_id = ?";
                            $updateSupplierAmountStmt = $pdo->prepare($updateSupplierAmountQuery);
                            $updateSupplierAmountStmt->execute([$newSupplierAmount, $supplierTransId, $tenant_id]);
                            
                            // Now update the balance separately - exactly like in update_transaction.php
                            // Use original balance from the fetched transaction
                            $currentSupplierBalance = $supplierTrans['balance'];
                            $newSupplierBalance = $currentSupplierBalance + $supplierAmountDiff;
                            
                            // Update the transaction balance
                            $updateSupplierBalanceQuery = "UPDATE supplier_transactions SET balance = ? WHERE id = ? AND tenant_id = ?";
                            $updateSupplierBalanceStmt = $pdo->prepare($updateSupplierBalanceQuery);
                            $updateSupplierBalanceStmt->execute([$newSupplierBalance, $supplierTransId, $tenant_id]);
                            
                            // Store the calculated balance to reuse it later if needed
                            $supplierFinalBalance = $newSupplierBalance;
                            
                            // Get all subsequent transactions to update their balances
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
                            
                            // Update all subsequent transactions
                            while ($laterTrans = $laterSupplierTransStmt->fetch(PDO::FETCH_ASSOC)) {
                                $newBalance = $laterTrans['balance'] + $supplierAmountDiff;
                                $updateLaterTransQuery = "UPDATE supplier_transactions SET balance = ? WHERE id = ? AND tenant_id = ?";
                                $updateLaterTransStmt = $pdo->prepare($updateLaterTransQuery);
                                $updateLaterTransStmt->execute([$newBalance, $laterTrans['id'], $tenant_id]);
                            }
                        }
                        
                        // Update the JV payment record
                        // Set USD/AFS amounts based on currency
                        $usdAmount = ($currency === 'USD') ? $totalAmount : 0;
                        $afsAmount = ($currency === 'AFS') ? $totalAmount : 0;
                        
                        $updateJvQuery = "UPDATE jv_payments SET 
                            jv_name = ?, exchange_rate = ?, 
                            total_amount = ?, currency = ?, 
                            receipt = ?, remarks = ? WHERE id = ? AND tenant_id = ?";
                            
                        $updateJvStmt = $pdo->prepare($updateJvQuery);
                        $updateJvStmt->execute([
                            $jvName, $exchangeRate, $totalAmount, $currency, 
                            $receipt, $remarks, $paymentId, $tenant_id
                        ]);
                        
                        // Get the username
                        $username = $_SESSION['name'] ?? 'Unknown';
                        
                        // Create transaction remarks
                        $clientRemark = "Updated JV Payment: Client {$client['name']} paid {$totalAmount} {$currency} to supplier {$supplier['name']}. Updated by: {$username}. {$remarks}";
                        $supplierRemark = "Updated JV Payment: Received {$newSupplierAmount} {$supplier['supplier_currency']} from client {$client['name']}. Updated by: {$username}. {$remarks}";
                        
                        // Check for existing JV transaction
                        $jvTransQuery = "SELECT id FROM jv_transactions WHERE jv_payment_id = ? AND tenant_id = ? LIMIT 1";
                        $jvTransStmt = $pdo->prepare($jvTransQuery);
                        $jvTransStmt->execute([$paymentId, $tenant_id]);
                        $jvTrans = $jvTransStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($jvTrans) {
                            // Update existing JV transaction
                            $updateJvTransQuery = "UPDATE jv_transactions SET 
                                transaction_type = 'Transfer', amount = ?, balance = ?, 
                                currency = ?, description = ?, receipt = ? 
                                WHERE id = ? AND tenant_id = ?";
                                
                            $updateJvTransStmt = $pdo->prepare($updateJvTransQuery);
                            $updateJvTransStmt->execute([
                                $totalAmount, $totalAmount, $currency, 
                                $description, $receipt, $jvTrans['id'], $tenant_id
                            ]);
                            
                            $jvTransactionId = $jvTrans['id'];
                        } else {
                            // Create new JV transaction
                            $insertJvTransQuery = "INSERT INTO jv_transactions (
                                jv_payment_id, transaction_type, amount, balance, 
                                currency, description, receipt, reference_id
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            
                            $insertJvTransStmt = $pdo->prepare($insertJvTransQuery);
                            $insertJvTransStmt->execute([
                                $paymentId, 'Transfer', $totalAmount, $totalAmount, 
                                $currency, $description, $receipt, $clientId, $tenant_id
                            ]);
                            
                            $jvTransactionId = $pdo->lastInsertId();
                        }
                        
                        // Check for existing client transaction
                        $clientTransQuery = "SELECT id, balance FROM client_transactions 
                            WHERE transaction_of = 'jv_payment' AND reference_id = ? AND tenant_id = ?
                            ORDER BY id DESC LIMIT 1";
                            
                        $clientTransStmt = $pdo->prepare($clientTransQuery);
                        $clientTransStmt->execute([$jvTransactionId, $tenant_id]);
                        $clientTrans = $clientTransStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($clientTrans) {
                            // Update existing client transaction
                            $updateClientTransQuery = "UPDATE client_transactions SET 
                                type = 'credit', amount = ?, currency = ?, 
                                description = ?, receipt = ? 
                                WHERE id = ? AND tenant_id = ?";
                                
                            $updateClientTransStmt = $pdo->prepare($updateClientTransQuery);
                            $updateClientTransStmt->execute([
                                $totalAmount, $currency, 
                                $clientRemark, $receipt, $clientTrans['id'], $tenant_id
                            ]);
                            
                            // Now update the balance separately
                            // We use the previously calculated balance that was correctly updated in the first section
                            $updateClientBalanceQuery = "UPDATE client_transactions SET balance = ? WHERE id = ? AND tenant_id = ?";
                            $updateClientBalanceStmt = $pdo->prepare($updateClientBalanceQuery);
                            $updateClientBalanceStmt->execute([$clientFinalBalance, $clientTrans['id'], $tenant_id]);
                        } else {
                            // Create new client transaction
                            $insertClientTransQuery = "INSERT INTO client_transactions (
                                client_id, type, amount, balance, currency, 
                                description, transaction_of, reference_id, receipt
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            
                            $insertClientTransStmt = $pdo->prepare($insertClientTransQuery);
                            $insertClientTransStmt->execute([
                                $clientId, 'credit', $totalAmount, $clientFinalBalance, 
                                $currency, $clientRemark, 'jv_payment', $jvTransactionId, $receipt, $tenant_id
                            ]);
                        }
                        
                        // Check for existing supplier transaction
                        $supplierTransQuery = "SELECT id, balance FROM supplier_transactions 
                            WHERE transaction_of = 'jv_payment' AND reference_id = ? AND tenant_id = ?
                            ORDER BY id DESC LIMIT 1";
                            
                        $supplierTransStmt = $pdo->prepare($supplierTransQuery);
                        $supplierTransStmt->execute([$jvTransactionId, $tenant_id]);
                        $supplierTrans = $supplierTransStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($supplierTrans) {
                            // Update existing supplier transaction
                            $updateSupplierTransQuery = "UPDATE supplier_transactions SET 
                                transaction_type = 'Credit', amount = ?, 
                                remarks = ?, receipt = ? 
                                WHERE id = ? AND tenant_id = ?";
                                
                            $updateSupplierTransStmt = $pdo->prepare($updateSupplierTransQuery);
                            $updateSupplierTransStmt->execute([
                                $newSupplierAmount, 
                                $supplierRemark, $receipt, $supplierTrans['id'], $tenant_id
                            ]);
                            
                            // Now update the balance separately
                            // We use the previously calculated balance that was correctly updated in the first section
                            $updateSupplierBalanceQuery = "UPDATE supplier_transactions SET balance = ? WHERE id = ? AND tenant_id = ?";
                            $updateSupplierBalanceStmt = $pdo->prepare($updateSupplierBalanceQuery);
                            $updateSupplierBalanceStmt->execute([$supplierFinalBalance, $supplierTrans['id'], $tenant_id]);
                        } else {
                            // Create new supplier transaction
                            $insertSupplierTransQuery = "INSERT INTO supplier_transactions (
                                supplier_id, transaction_type, amount, balance, 
                                remarks, transaction_of, reference_id, receipt
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            
                            $insertSupplierTransStmt = $pdo->prepare($insertSupplierTransQuery);
                            $insertSupplierTransStmt->execute([
                                $supplierId, 'Credit', $newSupplierAmount, $supplierFinalBalance, 
                                $supplierRemark, 'jv_payment', $jvTransactionId, $receipt, $tenant_id
                            ]);
                        }
                        
                        // Add activity logging
                        $user_id = $_SESSION['user_id'] ?? 0;
                        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        
                        // Prepare old values data
                        $old_values = $originalPayment;
                        
                        // Prepare new values data
                        $new_values = [
                            'jv_name' => $jvName,
                            'currency' => $currency,
                            'total_amount' => $totalAmount,
                            'exchange_rate' => $exchangeRate,
                            'receipt' => $receipt,
                            'remarks' => $remarks,
                            'client_id' => $clientId,
                            'supplier_id' => $supplierId,
                            'client_name' => $client['name'],
                            'supplier_name' => $supplier['name']
                        ];
                        
                        // Insert activity log
                        $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log 
                            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at) 
                            VALUES (?, 'update', 'jv_payments', ?, ?, ?, ?, ?, NOW()) AND tenant_id = ?");
                        
                        $old_values_json = json_encode($old_values);
                        $new_values_json = json_encode($new_values);
                        $activity_log_stmt->execute([$user_id, $paymentId, $old_values_json, $new_values_json, $ip_address, $user_agent, $tenant_id]);
                        
                        // Commit transaction
                        $pdo->commit();
                        
                        $_SESSION['success_message'] = "Client-Supplier JV Payment updated successfully!";
                    } catch (Exception $e) {
                        // Rollback on error
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        
                        error_log("Error updating JV payment: " . $e->getMessage());
                        $_SESSION['error_message'] = "Error updating JV payment: " . $e->getMessage();
                    }
                }
                
// Redirect back to the JV payments page
header('Location: jv_payments.php');
exit();