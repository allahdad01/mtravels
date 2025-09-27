<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
require_once '../includes/conn.php';

// Validate phone
$phone = isset($_POST['phone']) ? DbSecurity::validateInput($_POST['phone'], 'string', ['maxlength' => 255]) : null;

// Validate remarks
$remarks = isset($_POST['remarks']) ? DbSecurity::validateInput($_POST['remarks'], 'string', ['maxlength' => 255]) : null;

// Validate status
$status = isset($_POST['status']) ? DbSecurity::validateInput($_POST['status'], 'string', ['maxlength' => 255]) : null;

// Validate currency
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : null;

// Validate profit
$profit = isset($_POST['profit']) ? DbSecurity::validateInput($_POST['profit'], 'float', ['min' => 0]) : null;

// Validate sold
$sold = isset($_POST['sold']) ? DbSecurity::validateInput($_POST['sold'], 'float', ['min' => 0]) : null;

// Validate base
$base = isset($_POST['base']) ? DbSecurity::validateInput($_POST['base'], 'float', ['min' => 0]) : null;

// Validate issued_date
$issued_date = isset($_POST['issued_date']) ? DbSecurity::validateInput($_POST['issued_date'], 'date') : null;

// Validate applied_date
$applied_date = isset($_POST['applied_date']) ? DbSecurity::validateInput($_POST['applied_date'], 'date') : null;

// Validate receive_date
$receive_date = isset($_POST['receive_date']) ? DbSecurity::validateInput($_POST['receive_date'], 'date') : null;

// Validate visa_type
$visa_type = isset($_POST['visa_type']) ? DbSecurity::validateInput($_POST['visa_type'], 'string', ['maxlength' => 255]) : null;

// Validate country
$country = isset($_POST['country']) ? DbSecurity::validateInput($_POST['country'], 'string', ['maxlength' => 255]) : null;

// Validate passport_number
$passport_number = isset($_POST['passport_number']) ? DbSecurity::validateInput($_POST['passport_number'], 'string', ['maxlength' => 255]) : null;

// Validate applicant_name
$applicant_name = isset($_POST['applicant_name']) ? DbSecurity::validateInput($_POST['applicant_name'], 'string', ['maxlength' => 255]) : null;

// Validate gender
$gender = isset($_POST['gender']) ? DbSecurity::validateInput($_POST['gender'], 'string', ['maxlength' => 255]) : null;

// Validate title
$title = isset($_POST['title']) ? DbSecurity::validateInput($_POST['title'], 'string', ['maxlength' => 255]) : null;

// Validate sold_to
$sold_to = isset($_POST['sold_to']) ? DbSecurity::validateInput($_POST['sold_to'], 'int', ['min' => 0]) : null;

// Validate supplier
$supplier = isset($_POST['supplier']) ? DbSecurity::validateInput($_POST['supplier'], 'int', ['min' => 0]) : null;

// Validate id
$id = isset($_POST['visa_id']) ? DbSecurity::validateInput($_POST['visa_id'], 'int', ['min' => 0]) : null;

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize POST data
    $id = isset($_POST['visa_id']) ? intval($_POST['visa_id']) : null;
    $supplier = isset($_POST['supplier']) ? intval($_POST['supplier']) : null;
    $sold_to = isset($_POST['sold_to']) ? intval($_POST['sold_to']) : null;
    $title = isset($_POST['title']) ? htmlspecialchars($_POST['title']) : '';
    $gender = isset($_POST['gender']) ? htmlspecialchars($_POST['gender']) : '';
    $applicant_name = isset($_POST['applicant_name']) ? htmlspecialchars($_POST['applicant_name']) : '';
    $passport_number = isset($_POST['passport_number']) ? htmlspecialchars($_POST['passport_number']) : '';
    $country = isset($_POST['country']) ? htmlspecialchars($_POST['country']) : '';
    $visa_type = isset($_POST['visa_type']) ? htmlspecialchars($_POST['visa_type']) : '';
    $receive_date = isset($_POST['receive_date']) ? $_POST['receive_date'] : null;
    $applied_date = isset($_POST['applied_date']) ? $_POST['applied_date'] : null;
    $issued_date = isset($_POST['issued_date']) ? $_POST['issued_date'] : null;
    $base = isset($_POST['base']) ? floatval($_POST['base']) : 0.0;
    $sold = isset($_POST['sold']) ? floatval($_POST['sold']) : 0.0;
    $profit = isset($_POST['profit']) ? floatval($_POST['profit']) : 0.0;
    $currency = isset($_POST['currency']) ? htmlspecialchars($_POST['currency']) : 'USD';
    $status = isset($_POST['status']) ? htmlspecialchars($_POST['status']) : '';
    $remarks = isset($_POST['remarks']) ? htmlspecialchars($_POST['remarks']) : '';
    $phone = isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '';

    // Get original values to calculate differences
    $originalQuery = "SELECT base, sold, supplier, sold_to, currency FROM visa_applications WHERE id = ? AND tenant_id = ?";
    $stmtOriginal = $conn->prepare($originalQuery);
    $stmtOriginal->bind_param('ii', $id, $tenant_id);
    $stmtOriginal->execute();
    $resultOriginal = $stmtOriginal->get_result();
    $originalData = $resultOriginal->fetch_assoc();
    $stmtOriginal->close();

    if (!$originalData) {
        $response['message'] = 'Original visa data not found.';
        echo json_encode($response);
        exit;
    }

    // Calculate differences
    $priceDifference = $originalData['base'] - $base;
    // If priceDifference is positive: base decreased (supplier gets money back)
    // If priceDifference is negative: base increased (supplier pays more)
    $soldDifference = $originalData['sold'] - $sold;
    $originalCurrency = $originalData['currency'];
    $originalSupplier = $originalData['supplier'];
    $originalClient = $originalData['sold_to'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Handle supplier changes or price differences
        if ($supplier != $originalSupplier || $priceDifference != 0) {
            // Check if original supplier exists and is external
            if ($originalSupplier > 0) {
                $oldSupplierQuery = "SELECT * FROM suppliers WHERE id = ? AND tenant_id = ?";
                $stmtOldSupplier = $conn->prepare($oldSupplierQuery);
                $stmtOldSupplier->bind_param('ii', $originalSupplier, $tenant_id);
                $stmtOldSupplier->execute();
                $oldSupplierResult = $stmtOldSupplier->get_result();
                $oldSupplierData = $oldSupplierResult->fetch_assoc();
                $stmtOldSupplier->close();
                
                $oldSupplierType = isset($oldSupplierData['supplier_type']) ? $oldSupplierData['supplier_type'] : '';
                if (!$oldSupplierType) {
                    $oldSupplierType = isset($oldSupplierData['type']) ? $oldSupplierData['type'] : '';
                }
                $oldSupplierIsExternal = (strtolower(trim($oldSupplierType)) === 'external');
                
                // If supplier changed and old supplier was external
                if ($supplier != $originalSupplier && $oldSupplierIsExternal) {
                    // Update old supplier balance - ADDING the original base amount
                    // This INCREASES the balance (supplier gets money back)
                    $updateOldSupplierQuery = "UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ?";
                    $stmtUpdateOldSupplier = $conn->prepare($updateOldSupplierQuery);
                    $stmtUpdateOldSupplier->bind_param('di', $originalData['base'], $originalSupplier, $tenant_id);
                    $stmtUpdateOldSupplier->execute();
                    $stmtUpdateOldSupplier->close();
                    
                    // Check if transaction record exists for old supplier
                    $checkOldSupplierTransactionQuery = "SELECT id FROM supplier_transactions WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'visa_sale' AND tenant_id = ? LIMIT 1";
                    $stmtCheckOldSupplierTransaction = $conn->prepare($checkOldSupplierTransactionQuery);
                    $stmtCheckOldSupplierTransaction->bind_param('iii', $originalSupplier, $id, $tenant_id);
                    $stmtCheckOldSupplierTransaction->execute();
                    $oldSupplierTransactionResult = $stmtCheckOldSupplierTransaction->get_result();
                    $oldSupplierTransactionExists = $oldSupplierTransactionResult->num_rows > 0;
                    $stmtCheckOldSupplierTransaction->close();
                    
                    if ($oldSupplierTransactionExists) {
                        // Get transaction details before deleting
                        $getOldSupplierTransactionQuery = "SELECT id, transaction_date, amount FROM supplier_transactions WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'visa_sale' AND tenant_id = ? LIMIT 1";
                        $stmtGetOldSupplierTransaction = $conn->prepare($getOldSupplierTransactionQuery);
                        $stmtGetOldSupplierTransaction->bind_param('iii', $originalSupplier, $id, $tenant_id);
                        $stmtGetOldSupplierTransaction->execute();
                        $oldSupplierTransactionResult = $stmtGetOldSupplierTransaction->get_result();
                        $oldSupplierTransactionData = $oldSupplierTransactionResult->fetch_assoc();
                        $stmtGetOldSupplierTransaction->close();
                        
                        if ($oldSupplierTransactionData) {
                            // Update all subsequent transactions' balances
                            // Since we're removing a debit transaction, we need to decrease subsequent balances
                            $updateSubsequentSupplierQuery = "UPDATE supplier_transactions 
                                                            SET balance = balance + ? 
                                                            WHERE supplier_id = ? 
                                                            AND transaction_date > ? 
                                                            AND id != ? AND tenant_id = ?";
                            $stmtUpdateSubsequentSupplier = $conn->prepare($updateSubsequentSupplierQuery);
                            $transactionAmount = abs($oldSupplierTransactionData['amount']); // Make sure it's positive
                            $stmtUpdateSubsequentSupplier->bind_param('disi', 
                                $transactionAmount, 
                                $originalSupplier, 
                                $oldSupplierTransactionData['transaction_date'], 
                                $oldSupplierTransactionData['id'],
                                $tenant_id
                            );
                            $stmtUpdateSubsequentSupplier->execute();
                            $stmtUpdateSubsequentSupplier->close();
                        }

                        // Update existing transaction record
                        $updateOldSupplierTransactionQuery = "DELETE FROM supplier_transactions WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'visa_sale' AND tenant_id = ?";
                        $stmtUpdateOldSupplierTransaction = $conn->prepare($updateOldSupplierTransactionQuery);
                        $stmtUpdateOldSupplierTransaction->bind_param('iii', $originalSupplier, $id, $tenant_id);
                        $stmtUpdateOldSupplierTransaction->execute();
                        $stmtUpdateOldSupplierTransaction->close();
                    }
                }
            }
            
            // Check if new supplier exists and is external
            if ($supplier > 0) {
                $supplierQuery = "SELECT * FROM suppliers WHERE id = ? AND tenant_id = ?";
                $stmtSupplier = $conn->prepare($supplierQuery);
                $stmtSupplier->bind_param('ii', $supplier, $tenant_id);
                $stmtSupplier->execute();
                $supplierResult = $stmtSupplier->get_result();
                $supplierData = $supplierResult->fetch_assoc();
                $stmtSupplier->close();
                
                $supplierType = isset($supplierData['supplier_type']) ? $supplierData['supplier_type'] : '';
                if (!$supplierType) {
                    $supplierType = isset($supplierData['type']) ? $supplierData['type'] : '';
                }
                $isExternal = (strtolower(trim($supplierType)) === 'external');
                
                if ($isExternal) {
                    // If supplier changed
                    if ($supplier != $originalSupplier) {
                        // Update new supplier balance - SUBTRACTING the new base amount
                        // This DECREASES the balance (supplier pays more)
                        $updateSupplierQuery = "UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ?";
                        $stmtUpdateSupplier = $conn->prepare($updateSupplierQuery);
                        $stmtUpdateSupplier->bind_param('dii', $base, $supplier, $tenant_id);
                        $stmtUpdateSupplier->execute();
                        $stmtUpdateSupplier->close();

                        // Get the updated balance
                        $getBalanceQuery = "SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ?";
                        $stmtGetBalance = $conn->prepare($getBalanceQuery);
                        $stmtGetBalance->bind_param('ii', $supplier, $tenant_id);
                        $stmtGetBalance->execute();
                        $balanceResult = $stmtGetBalance->get_result();
                        $newBalance = $balanceResult->fetch_assoc()['balance'];
                        $stmtGetBalance->close();
                        
                        // Create new transaction record for new supplier
                        $insertSupplierTransactionQuery = "INSERT INTO supplier_transactions (supplier_id, reference_id, transaction_type, amount, remarks, balance, transaction_of, tenant_id) VALUES (?, ?, 'Debit', ?, ?, ?, 'visa_sale', ?)";
                        $stmtInsertSupplierTransaction = $conn->prepare($insertSupplierTransactionQuery);
                        $description = "Purchase for visa: $applicant_name (Passport: $passport_number)";
                        $stmtInsertSupplierTransaction->bind_param('iidsdi', $supplier, $id, $base, $description, $newBalance, $tenant_id);
                        $stmtInsertSupplierTransaction->execute();
                        $stmtInsertSupplierTransaction->close();
                    } 
                    // Same supplier but price changed
                    else if ($priceDifference != 0) {
                        // Get current supplier balance before update
                        $getCurrentSupplierBalanceQuery = "SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ?";
                        $stmtGetCurrentSupplierBalance = $conn->prepare($getCurrentSupplierBalanceQuery);
                        $stmtGetCurrentSupplierBalance->bind_param('ii', $supplier, $tenant_id);
                        $stmtGetCurrentSupplierBalance->execute();
                        $currentSupplierBalanceResult = $stmtGetCurrentSupplierBalance->get_result();
                        $currentSupplierBalance = $currentSupplierBalanceResult->fetch_assoc()['balance'];
                        $stmtGetCurrentSupplierBalance->close();
                        
                        // Calculate new balance
                        $newSupplierBalance = 0;
                        if ($priceDifference > 0) {
                            // Base price decreased, supplier gets money back
                            $updateSupplierQuery = "UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ?";
                            $newSupplierBalance = $currentSupplierBalance + $priceDifference;
                        } else {
                            // Base price increased, supplier pays more
                            $updateSupplierQuery = "UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ?";
                            // Make the difference positive for the query
                            $priceDifference = abs($priceDifference);
                            $newSupplierBalance = $currentSupplierBalance - $priceDifference;
                        }
                        
                        $stmtUpdateSupplier = $conn->prepare($updateSupplierQuery);
                        $stmtUpdateSupplier->bind_param('dii', $priceDifference, $supplier, $tenant_id);
                        $stmtUpdateSupplier->execute();
                        $stmtUpdateSupplier->close();
                        
                        // Check if transaction record exists for this supplier
                        $checkSupplierTransactionQuery = "SELECT id, transaction_date, balance, amount FROM supplier_transactions WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'visa_sale' AND tenant_id = ? LIMIT 1";
                        $stmtCheckSupplierTransaction = $conn->prepare($checkSupplierTransactionQuery);
                        $stmtCheckSupplierTransaction->bind_param('iii', $supplier, $id, $tenant_id);
                        $stmtCheckSupplierTransaction->execute();
                        $supplierTransactionResult = $stmtCheckSupplierTransaction->get_result();
                        $supplierTransactionRow = null;
                        if ($supplierTransactionResult->num_rows > 0) {
                            $supplierTransactionRow = $supplierTransactionResult->fetch_assoc();
                        }
                        $stmtCheckSupplierTransaction->close();
                        
                        if ($supplierTransactionRow) {
                            $supplierTransactionId = $supplierTransactionRow['id'];
                            $supplierTransactionDate = $supplierTransactionRow['transaction_date'];
                            $currentTransactionBalance = $supplierTransactionRow['balance'];
                            $currentTransactionAmount = abs($supplierTransactionRow['amount']); // Ensure positive value
                            
                            // Calculate the difference between the new base amount and the current transaction amount
                            $amountDifference = $base - $currentTransactionAmount;
                            
                            // Calculate the new balance for this transaction
                            // If base increased, balance should decrease by the difference
                            // If base decreased, balance should increase by the difference
                            $newTransactionBalance = $currentTransactionBalance - $amountDifference;
                            
                            // Update amount field to new base price
                            $updateSupplierAmountQuery = "UPDATE supplier_transactions SET amount = ? WHERE id = ? AND tenant_id = ?";
                            $stmtUpdateSupplierAmount = $conn->prepare($updateSupplierAmountQuery);
                            $stmtUpdateSupplierAmount->bind_param('di', $base, $supplierTransactionId, $tenant_id);
                            $stmtUpdateSupplierAmount->execute();
                            $stmtUpdateSupplierAmount->close();
                            
                            // Update existing transaction record with adjusted balance
                            $updateSupplierTransactionQuery = "UPDATE supplier_transactions SET balance = ?, remarks = CONCAT('Updated: ', remarks) WHERE id = ? AND tenant_id = ?";
                            $stmtUpdateSupplierTransaction = $conn->prepare($updateSupplierTransactionQuery);
                            $stmtUpdateSupplierTransaction->bind_param('di', $newTransactionBalance, $supplierTransactionId, $tenant_id);
                            $stmtUpdateSupplierTransaction->execute();
                            $stmtUpdateSupplierTransaction->close();
                            
                            // Update all subsequent transactions' balances
                            // If base amount increased, decrease subsequent balances
                            // If base amount decreased, increase subsequent balances
                            if ($amountDifference > 0) {
                                // Base amount increased, decrease subsequent balances
                                $updateSubsequentSupplierQuery = "UPDATE supplier_transactions 
                                                                SET balance = balance - ? 
                                                                WHERE supplier_id = ? 
                                                                AND transaction_date > ? 
                                                                AND id != ? AND tenant_id = ?";
                            } else {
                                // Base amount decreased, increase subsequent balances
                                $updateSubsequentSupplierQuery = "UPDATE supplier_transactions 
                                                                SET balance = balance + ? 
                                                                WHERE supplier_id = ? 
                                                                AND transaction_date > ? 
                                                                AND id != ? AND tenant_id = ?";
                            }
                            
                            $stmtUpdateSubsequentSupplier = $conn->prepare($updateSubsequentSupplierQuery);
                            $absAmountDifference = abs($amountDifference);
                            $stmtUpdateSubsequentSupplier->bind_param('disii', $absAmountDifference, $supplier, $supplierTransactionDate, $supplierTransactionId, $tenant_id);
                            $stmtUpdateSubsequentSupplier->execute();
                            $stmtUpdateSubsequentSupplier->close();
                        }
                    }
                }
            }
        }
        
        // Handle client changes or price differences
        if ($sold_to != $originalClient || $soldDifference != 0) {
            // Check if original client exists and is regular
            if ($originalClient > 0) {
                $oldClientQuery = "SELECT * FROM clients WHERE id = ? AND tenant_id = ?";
                $stmtOldClient = $conn->prepare($oldClientQuery);
                $stmtOldClient->bind_param('ii', $originalClient, $tenant_id);
                $stmtOldClient->execute();
                $oldClientResult = $stmtOldClient->get_result();
                $oldClientData = $oldClientResult->fetch_assoc();
                $stmtOldClient->close();
                
                $oldClientType = isset($oldClientData['client_type']) ? $oldClientData['client_type'] : '';
                if (!$oldClientType) {
                    $oldClientType = isset($oldClientData['type']) ? $oldClientData['type'] : '';
                }
                $oldClientIsRegular = (strtolower(trim($oldClientType)) === 'regular');
                
                // If client changed and old client was regular
                if ($sold_to != $originalClient && $oldClientIsRegular) {
                    // Update old client balance - SUBTRACTING the original sold amount
                    // This DECREASES the balance (client gets charged less)
                    $balanceField = strtolower($originalCurrency) === 'usd' ? 'usd_balance' : 'afs_balance';
                    $updateOldClientQuery = "UPDATE clients SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ?";
                    $stmtUpdateOldClient = $conn->prepare($updateOldClientQuery);
                    $stmtUpdateOldClient->bind_param('di', $originalData['sold'], $originalClient, $tenant_id);
                    $stmtUpdateOldClient->execute();
                    $stmtUpdateOldClient->close();
                    
                    // Check if transaction record exists for old client
                    $checkOldClientTransactionQuery = "SELECT id FROM client_transactions WHERE client_id = ? AND reference_id = ? AND transaction_of = 'visa_sale' AND tenant_id = ? LIMIT 1";
                    $stmtCheckOldClientTransaction = $conn->prepare($checkOldClientTransactionQuery);
                    $stmtCheckOldClientTransaction->bind_param('iii', $originalClient, $id, $tenant_id);
                    $stmtCheckOldClientTransaction->execute();
                    $oldClientTransactionResult = $stmtCheckOldClientTransaction->get_result();
                    $oldClientTransactionExists = $oldClientTransactionResult->num_rows > 0;
                    $stmtCheckOldClientTransaction->close();
                    
                    if ($oldClientTransactionExists) {
                        // Get transaction details before deleting
                        $getOldClientTransactionQuery = "SELECT id, created_at, amount FROM client_transactions WHERE client_id = ? AND reference_id = ? AND transaction_of = 'visa_sale' AND tenant_id = ? LIMIT 1";
                        $stmtGetOldClientTransaction = $conn->prepare($getOldClientTransactionQuery);
                        $stmtGetOldClientTransaction->bind_param('iii', $originalClient, $id, $tenant_id);
                        $stmtGetOldClientTransaction->execute();
                        $oldClientTransactionResult = $stmtGetOldClientTransaction->get_result();
                        $oldClientTransactionData = $oldClientTransactionResult->fetch_assoc();
                        $stmtGetOldClientTransaction->close();
                        
                        if ($oldClientTransactionData) {
                            // Update all subsequent transactions' balances
                            // Since we're removing a debit transaction, we need to decrease subsequent balances
                            $updateSubsequentQuery = "UPDATE client_transactions 
                                                    SET balance = balance + ? 
                                                    WHERE client_id = ? 
                                                    AND created_at > ? 
                                                    AND currency = ? 
                                                    AND id != ? AND tenant_id = ?";
                            $stmtUpdateSubsequent = $conn->prepare($updateSubsequentQuery);
                            $transactionAmount = abs($oldClientTransactionData['amount']); // Make sure it's positive
                            $stmtUpdateSubsequent->bind_param('dissi', 
                                $transactionAmount, 
                                $originalClient, 
                                $oldClientTransactionData['created_at'], 
                                $originalCurrency, 
                                $oldClientTransactionData['id'],
                                $tenant_id
                            );
                            $stmtUpdateSubsequent->execute();
                            $stmtUpdateSubsequent->close();
                        }

                        // Delete the transaction record
                        $updateOldClientTransactionQuery = "DELETE FROM client_transactions WHERE client_id = ? AND reference_id = ? AND transaction_of = 'visa_sale' AND tenant_id = ?";
                        $stmtUpdateOldClientTransaction = $conn->prepare($updateOldClientTransactionQuery);
                        $stmtUpdateOldClientTransaction->bind_param('iii', $originalClient, $id, $tenant_id);
                        $stmtUpdateOldClientTransaction->execute();
                        $stmtUpdateOldClientTransaction->close();
                    }
                }
            }
            
            // Check if new client exists and is regular
            if ($sold_to > 0) {
                $clientQuery = "SELECT * FROM clients WHERE id = ? AND tenant_id = ?";
                $stmtClient = $conn->prepare($clientQuery);
                $stmtClient->bind_param('ii', $sold_to, $tenant_id);
                $stmtClient->execute();
                $clientResult = $stmtClient->get_result();
                $clientData = $clientResult->fetch_assoc();
                $stmtClient->close();
                
                $clientType = isset($clientData['client_type']) ? $clientData['client_type'] : '';
                if (!$clientType) {
                    $clientType = isset($clientData['type']) ? $clientData['type'] : '';
                }
                $isRegular = (strtolower(trim($clientType)) === 'regular');
                
                if ($isRegular) {
                    // If client changed
                    if ($sold_to != $originalClient) {
                        // Update new client balance - ADDING the new sold amount
                        // This INCREASES the balance (client gets charged more)
                        $balanceField = strtolower($currency) === 'usd' ? 'usd_balance' : 'afs_balance';
                        $updateClientQuery = "UPDATE clients SET $balanceField = $balanceField - ? WHERE id = ? AND tenant_id = ?";
                        $stmtUpdateClient = $conn->prepare($updateClientQuery);
                        $stmtUpdateClient->bind_param('dii', $sold, $sold_to, $tenant_id);
                        $stmtUpdateClient->execute();
                        $stmtUpdateClient->close();

                        // Get the updated balance
                        $getBalanceQuery = "SELECT $balanceField FROM clients WHERE id = ?";
                        $stmtGetBalance = $conn->prepare($getBalanceQuery);
                        $stmtGetBalance->bind_param('ii', $sold_to, $tenant_id);
                        $stmtGetBalance->execute();
                        $newBalance = null;
                        $stmtGetBalance->bind_result($newBalance);
                        $stmtGetBalance->fetch();
                        $stmtGetBalance->close();
                        
                        // Create new transaction record for new client
                        $insertClientTransactionQuery = "INSERT INTO client_transactions (client_id, reference_id, type, amount, currency, balance, description, transaction_of, tenant_id) VALUES (?, ?, 'debit', ?, ?, ?, ?, 'visa_sale', ?)";
                        $stmtInsertClientTransaction = $conn->prepare($insertClientTransactionQuery);
                        $description = "Sale for visa: $applicant_name (Passport: $passport_number)";
                        $stmtInsertClientTransaction->bind_param('iidsssi', $sold_to, $id, $sold, $currency, $newBalance, $description, $tenant_id);
                        $stmtInsertClientTransaction->execute();
                        $stmtInsertClientTransaction->close();
                    } 
                    // Same client but sold price changed
                    else if ($soldDifference != 0) {
                        $balanceField = strtolower($currency) === 'usd' ? 'usd_balance' : 'afs_balance';
                        
                        // Get current client balance before update
                        $getCurrentBalanceQuery = "SELECT $balanceField FROM clients WHERE id = ? AND tenant_id = ?";
                        $stmtGetCurrentBalance = $conn->prepare($getCurrentBalanceQuery);
                        $stmtGetCurrentBalance->bind_param('ii', $sold_to, $tenant_id);
                        $stmtGetCurrentBalance->execute();
                        $currentBalanceResult = $stmtGetCurrentBalance->get_result();
                        $currentBalance = $currentBalanceResult->fetch_assoc()[$balanceField];
                        $stmtGetCurrentBalance->close();
                        
                        // Calculate new balance
                        $newBalance = 0;
                        if ($soldDifference > 0) {
                            // Sold price decreased, client owes less, balance increases
                            $updateClientQuery = "UPDATE clients SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ?";
                            $newBalance = $currentBalance + $soldDifference;
                        } else {
                            // Sold price increased, client owes more, balance decreases
                            $updateClientQuery = "UPDATE clients SET $balanceField = $balanceField - ? WHERE id = ? AND tenant_id = ?";
                            // Make the difference positive for the query
                            $soldDifference = abs($soldDifference);
                            $newBalance = $currentBalance - $soldDifference;
                        }
                        
                        $stmtUpdateClient = $conn->prepare($updateClientQuery);
                        $stmtUpdateClient->bind_param('dii', $soldDifference, $sold_to, $tenant_id);
                        $stmtUpdateClient->execute();
                        $stmtUpdateClient->close();
                        
                        // Check if transaction record exists for this client
                        $checkClientTransactionQuery = "SELECT id, created_at, balance, amount FROM client_transactions WHERE client_id = ? AND reference_id = ? AND transaction_of = 'visa_sale' AND tenant_id = ? LIMIT 1";
                        $stmtCheckClientTransaction = $conn->prepare($checkClientTransactionQuery);
                        $stmtCheckClientTransaction->bind_param('iii', $sold_to, $id, $tenant_id);
                        $stmtCheckClientTransaction->execute();
                        $clientTransactionResult = $stmtCheckClientTransaction->get_result();
                        $transactionRow = null;
                        if ($clientTransactionResult->num_rows > 0) {
                            $transactionRow = $clientTransactionResult->fetch_assoc();
                        }
                        $stmtCheckClientTransaction->close();
                        
                        if ($transactionRow) {
                            $transactionId = $transactionRow['id'];
                            $transactionDate = $transactionRow['created_at'];
                            $currentTransactionBalance = $transactionRow['balance'];
                            $currentTransactionAmount = abs($transactionRow['amount']); // Ensure positive value
                            
                            // Calculate the difference between the new sold amount and the current transaction amount
                            $amountDifference = $sold - $currentTransactionAmount;
                            
                            // Calculate the new balance for this transaction
                            // If amount increased, balance should decrease by the difference
                            // If amount decreased, balance should increase by the difference
                            $newTransactionBalance = $currentTransactionBalance - $amountDifference;
                            
                            // Update amount field to new sold price (as negative value for debit)
                            $negativeAmount = -1 * abs($sold);
                            $updateClientAmountQuery = "UPDATE client_transactions SET amount = ? WHERE id = ? AND tenant_id = ?";
                            $stmtUpdateClientAmount = $conn->prepare($updateClientAmountQuery);
                            $stmtUpdateClientAmount->bind_param('di', $negativeAmount, $transactionId, $tenant_id);
                            $stmtUpdateClientAmount->execute();
                            $stmtUpdateClientAmount->close();
                            
                            // Update existing transaction record with adjusted balance
                            $updateClientTransactionQuery = "UPDATE client_transactions SET balance = ?, description = CONCAT('Updated: ', description) WHERE id = ? AND tenant_id = ?";
                            $stmtUpdateClientTransaction = $conn->prepare($updateClientTransactionQuery);
                            $stmtUpdateClientTransaction->bind_param('di', $newTransactionBalance, $transactionId, $tenant_id);
                            $stmtUpdateClientTransaction->execute();
                            $stmtUpdateClientTransaction->close();
                            
                            // Update all subsequent transactions' balances
                            // If amount increased, decrease subsequent balances
                            // If amount decreased, increase subsequent balances
                            if ($amountDifference > 0) {
                                // Amount increased, decrease subsequent balances
                                $updateSubsequentQuery = "UPDATE client_transactions 
                                                         SET balance = balance - ? 
                                                         WHERE client_id = ? 
                                                         AND created_at > ? 
                                                         AND currency = ? 
                                                         AND id != ? AND tenant_id = ?";
                            } else {
                                // Amount decreased, increase subsequent balances
                                $updateSubsequentQuery = "UPDATE client_transactions 
                                                         SET balance = balance + ? 
                                                         WHERE client_id = ? 
                                                         AND created_at > ? 
                                                         AND currency = ? 
                                                         AND id != ? AND tenant_id = ?";
                            }
                            
                            $stmtUpdateSubsequent = $conn->prepare($updateSubsequentQuery);
                            $absAmountDifference = abs($amountDifference);
                            $stmtUpdateSubsequent->bind_param('dissi', $absAmountDifference, $sold_to, $transactionDate, $currency, $transactionId, $tenant_id);
                            $stmtUpdateSubsequent->execute();
                            $stmtUpdateSubsequent->close();
                        }
                    }
                }
            }
        }

        // Prepare the SQL update statement for the visa application
        $sql = "UPDATE visa_applications 
                SET supplier = ?, sold_to = ?, title = ?, gender = ?, applicant_name = ?, 
                    passport_number = ?, country = ?, visa_type = ?, receive_date = ?, 
                    applied_date = ?, issued_date = ?, base = ?, sold = ?, profit = ?, 
                    currency = ?, status = ?, remarks = ?, phone = ?, updated_at = NOW()
                WHERE id = ? AND tenant_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "iisssssssssddsssssii",
            $supplier, $sold_to, $title, $gender, $applicant_name, 
            $passport_number, $country, $visa_type, $receive_date, 
            $applied_date, $issued_date, $base, $sold, $profit, 
            $currency, $status, $remarks, $phone, $id, $tenant_id
        );

        if ($stmt->execute()) {
            // Add activity log
            $user_id = $_SESSION['user_id'];
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            
            // Construct old_values from originalData
            $old_values = json_encode([
                'id' => $id,
                'supplier' => $originalData['supplier'],
                'sold_to' => $originalData['sold_to'],
                'base' => $originalData['base'],
                'sold' => $originalData['sold'],
                'currency' => $originalData['currency']
            ]);
            
            // Construct new_values
            $new_values = json_encode([
                'id' => $id,
                'supplier' => $supplier,
                'sold_to' => $sold_to,
                'title' => $title,
                'gender' => $gender,
                'applicant_name' => $applicant_name,
                'passport_number' => $passport_number,
                'country' => $country,
                'visa_type' => $visa_type,
                'receive_date' => $receive_date,
                'applied_date' => $applied_date,
                'issued_date' => $issued_date,
                'base' => $base,
                'sold' => $sold,
                'profit' => $profit,
                'currency' => $currency,
                'status' => $status,
                'remarks' => $remarks,
                'phone' => $phone
            ]);
            
            // Insert into activity_log table
            $log_sql = "INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id) 
                        VALUES (?, ?, ?, 'update', 'visa_applications', ?, ?, ?, NOW(), ?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("ississi", $user_id, $ip_address, $user_agent, $id, $old_values, $new_values, $tenant_id);
            
            if (!$log_stmt->execute()) {
                // Just log the error, don't affect the transaction success
                error_log("Failed to insert activity log: " . $log_stmt->error);
            }
            $log_stmt->close();
            
            // Commit transaction
            $conn->commit();
            $response['success'] = true;
            $response['message'] = 'Visa updated successfully.';
        } else {
            // Rollback transaction on error
            $conn->rollback();
            $response['message'] = 'Error updating visa: ' . $stmt->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        $response['message'] = 'An error occurred: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>
