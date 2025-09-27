<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];

// Database connection
require_once '../includes/conn.php';

// Validate paidTo
$paidTo = isset($_POST['paidTo']) ? DbSecurity::validateInput($_POST['paidTo'], 'int', ['min' => 0]) : null;

// Validate description
$description = isset($_POST['description']) ? DbSecurity::validateInput($_POST['description'], 'string', ['maxlength' => 255]) : null;

// Validate curr
$curr = isset($_POST['curr']) ? DbSecurity::validateInput($_POST['curr'], 'string', ['maxlength' => 255]) : null;

// Validate pro
$pro = isset($_POST['pro']) ? DbSecurity::validateInput($_POST['pro'], 'float', ['min' => 0]) : null;

// Validate sold
$sold = isset($_POST['sold']) ? DbSecurity::validateInput($_POST['sold'], 'float', ['min' => 0]) : null;

// Validate base
$base = isset($_POST['base']) ? DbSecurity::validateInput($_POST['base'], 'float', ['min' => 0]) : null;

// Validate returnDate
$returnDate = isset($_POST['returnDate']) ? DbSecurity::validateInput($_POST['returnDate'], 'date') : null;

// Validate departureDate
$departureDate = isset($_POST['departureDate']) ? DbSecurity::validateInput($_POST['departureDate'], 'date') : null;

// Validate issueDate
$issueDate = isset($_POST['issueDate']) ? DbSecurity::validateInput($_POST['issueDate'], 'date') : null;

// Validate airline
$airline = isset($_POST['airline']) ? DbSecurity::validateInput($_POST['airline'], 'string', ['maxlength' => 255]) : null;

// Validate returnDestination
$returnDestination = isset($_POST['returnDestination']) ? DbSecurity::validateInput($_POST['returnDestination'], 'string', ['maxlength' => 255]) : null;

// Validate returnOrigin
$returnOrigin = isset($_POST['returnOrigin']) ? DbSecurity::validateInput($_POST['returnOrigin'], 'string', ['maxlength' => 255]) : null;

// Validate destination
$destination = isset($_POST['destination']) ? DbSecurity::validateInput($_POST['destination'], 'string', ['maxlength' => 255]) : null;

// Validate origin
$origin = isset($_POST['origin']) ? DbSecurity::validateInput($_POST['origin'], 'string', ['maxlength' => 255]) : null;

// Validate phone
$phone = isset($_POST['phone']) ? DbSecurity::validateInput($_POST['phone'], 'string', ['maxlength' => 255]) : null;

// Validate pnr
$pnr = isset($_POST['pnr']) ? DbSecurity::validateInput($_POST['pnr'], 'string', ['maxlength' => 255]) : null;

// Validate passengerName
$passengerName = isset($_POST['passengerName']) ? DbSecurity::validateInput($_POST['passengerName'], 'string', ['maxlength' => 255]) : null;

// Validate gender
$gender = isset($_POST['gender']) ? DbSecurity::validateInput($_POST['gender'], 'string', ['maxlength' => 255]) : null;

// Validate title
$title = isset($_POST['title']) ? DbSecurity::validateInput($_POST['title'], 'string', ['maxlength' => 255]) : null;

// Validate tripType
$tripType = isset($_POST['tripType']) ? DbSecurity::validateInput($_POST['tripType'], 'string', ['maxlength' => 255]) : null;

// Validate soldTo
$soldTo = isset($_POST['soldTo']) ? DbSecurity::validateInput($_POST['soldTo'], 'int', ['min' => 0]) : null;

// Validate supplier
$supplier = isset($_POST['supplier']) ? DbSecurity::validateInput($_POST['supplier'], 'int', ['min' => 0]) : null;

// Validate id
$id = isset($_POST['id']) ? DbSecurity::validateInput($_POST['id'], 'int', ['min' => 0]) : null;

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize POST data
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;
    $supplier = isset($_POST['supplier']) ? intval($_POST['supplier']) : null;
    $sold_to = isset($_POST['soldTo']) ? intval($_POST['soldTo']) : null;
    $trip_type = isset($_POST['tripType']) ? htmlspecialchars($_POST['tripType']) : '';
    $title = isset($_POST['title']) ? htmlspecialchars($_POST['title']) : '';
    $gender = isset($_POST['gender']) ? htmlspecialchars($_POST['gender']) : '';
    $passenger_name = isset($_POST['passengerName']) ? htmlspecialchars($_POST['passengerName']) : '';
    $pnr = isset($_POST['pnr']) ? htmlspecialchars($_POST['pnr']) : '';
    $phone = isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '';
    $origin = isset($_POST['origin']) ? htmlspecialchars($_POST['origin']) : '';
    $destination = isset($_POST['destination']) ? htmlspecialchars($_POST['destination']) : '';
    $return_origin = isset($_POST['returnOrigin']) ? htmlspecialchars($_POST['returnOrigin']) : '';
    $return_destination = isset($_POST['returnDestination']) ? htmlspecialchars($_POST['returnDestination']) : '';
    $airline = isset($_POST['airline']) ? htmlspecialchars($_POST['airline']) : '';
    $issue_date = isset($_POST['issueDate']) ? $_POST['issueDate'] : null;
    $departure_date = isset($_POST['departureDate']) ? $_POST['departureDate'] : null;
    $return_date = isset($_POST['returnDate']) ? $_POST['returnDate'] : null;
    $base = isset($_POST['base']) ? floatval($_POST['base']) : 0.0;
    $sold = isset($_POST['sold']) ? floatval($_POST['sold']) : 0.0;
    $profit = isset($_POST['pro']) ? floatval($_POST['pro']) : 0.0;
    $currency = isset($_POST['curr']) ? htmlspecialchars($_POST['curr']) : 'USD';
    $description = isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '';
    $paid_to = isset($_POST['paidTo']) ? intval($_POST['paidTo']) : null;
   

    // Get original values to calculate differences
    $originalQuery = "SELECT price, sold, supplier, sold_to, currency FROM ticket_reservations WHERE id = ? AND tenant_id = ?";
    $stmtOriginal = $conn->prepare($originalQuery);
    $stmtOriginal->bind_param('ii', $id, $tenant_id);
    $stmtOriginal->execute();
    $resultOriginal = $stmtOriginal->get_result();
    $originalData = $resultOriginal->fetch_assoc();
    $stmtOriginal->close();

    if (!$originalData) {
        $response['message'] = 'Original ticket data not found.';
        echo json_encode($response);
        exit;
    }

    // Calculate differences
    $priceDifference = $originalData['price'] - $base;
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
                    $stmtUpdateOldSupplier->bind_param('dii', $originalData['price'], $originalSupplier, $tenant_id);
                    $stmtUpdateOldSupplier->execute();
                    $stmtUpdateOldSupplier->close();
                    
                    // Check if transaction record exists for old supplier
                    $checkOldSupplierTransactionQuery = "SELECT id FROM supplier_transactions WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'ticket_reserve' AND tenant_id = ? LIMIT 1";
                    $stmtCheckOldSupplierTransaction = $conn->prepare($checkOldSupplierTransactionQuery);
                    $stmtCheckOldSupplierTransaction->bind_param('iii', $originalSupplier, $id, $tenant_id);
                    $stmtCheckOldSupplierTransaction->execute();
                    $oldSupplierTransactionResult = $stmtCheckOldSupplierTransaction->get_result();
                    $oldSupplierTransactionExists = $oldSupplierTransactionResult->num_rows > 0;
                    $stmtCheckOldSupplierTransaction->close();
                    
                    if ($oldSupplierTransactionExists) {
                        // Update existing transaction record
                        $updateOldSupplierTransactionQuery = "UPDATE supplier_transactions SET transaction_type = 'cancelled', remarks = CONCAT(remarks, ' (Supplier changed)') WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'ticket_reserve' AND tenant_id = ?";
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
                        
                        // Get current supplier balance for the transaction record
                        $getCurrentSupplierBalanceQuery = "SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ?";
                        $stmtGetCurrentSupplierBalance = $conn->prepare($getCurrentSupplierBalanceQuery);
                        $stmtGetCurrentSupplierBalance->bind_param('ii', $supplier, $tenant_id);
                        $stmtGetCurrentSupplierBalance->execute();
                        $stmtGetCurrentSupplierBalance->bind_result($currentSupplierBalance);
                        $stmtGetCurrentSupplierBalance->fetch();
                        $stmtGetCurrentSupplierBalance->close();
                        
                        // Create new transaction record for new supplier
                        $insertSupplierTransactionQuery = "INSERT INTO supplier_transactions (supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_of, tenant_id) VALUES (?, ?, 'debit', ?, ?, ?, 'ticket_reserve', ?)";
                        $stmtInsertSupplierTransaction = $conn->prepare($insertSupplierTransactionQuery);
                        $description = "Purchase for ticket: $passenger_name ($origin to $destination)";
                        $stmtInsertSupplierTransaction->bind_param('iiddsii', $supplier, $id, $base, $currentSupplierBalance, $description, $tenant_id);
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
                        $stmtGetCurrentSupplierBalance->bind_result($currentSupplierBalance);
                        $stmtGetCurrentSupplierBalance->fetch();
                        $stmtGetCurrentSupplierBalance->close();
                        
                        // Calculate new balance
                        $newSupplierBalance = 0;
                        if ($priceDifference > 0) {
                            // Base price decreased (e.g., from 200 to 180)
                            // Supplier was charged 200 before, now only 180
                            // So supplier gets 20 back, balance increases
                            $updateSupplierQuery = "UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ?";
                            $newSupplierBalance = $currentSupplierBalance + $priceDifference;
                        } else {
                            // Base price increased (e.g., from 200 to 220)
                            // Supplier was charged 200 before, now 220
                            // So supplier pays 20 more, balance decreases
                            $updateSupplierQuery = "UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ?";
                            // Make the difference positive for the query
                            $priceDifference = abs($priceDifference);
                            $newSupplierBalance = $currentSupplierBalance - $priceDifference;
                        }
                        
                        // Update supplier balance
                        $stmtUpdateSupplier = $conn->prepare($updateSupplierQuery);
                        $stmtUpdateSupplier->bind_param('dii', $priceDifference, $supplier, $tenant_id);
                        $stmtUpdateSupplier->execute();
                        $stmtUpdateSupplier->close();
                        
                        // Check if transaction record exists for this supplier
                        $checkSupplierTransactionQuery = "SELECT id, transaction_date, balance, amount FROM supplier_transactions WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'ticket_reserve' AND tenant_id = ? LIMIT 1";
                        $stmtCheckSupplierTransaction = $conn->prepare($checkSupplierTransactionQuery);
                        $stmtCheckSupplierTransaction->bind_param('iii', $supplier, $id, $tenant_id);
                        $stmtCheckSupplierTransaction->execute();
                        $supplierTransactionResult = $stmtCheckSupplierTransaction->get_result();
                        
                        if ($supplierTransactionResult->num_rows > 0) {
                            $supplierTransactionRow = $supplierTransactionResult->fetch_assoc();
                            $supplierTransactionId = $supplierTransactionRow['id'];
                            $supplierTransactionDate = $supplierTransactionRow['transaction_date'];
                            $currentTransactionBalance = $supplierTransactionRow['balance'];
                            $currentTransactionAmount = $supplierTransactionRow['amount'];
                            
                            // Calculate the difference between the new base amount and the current transaction amount
                            $amountDifference = $base - $currentTransactionAmount;
                            
                            // Calculate the new balance for this transaction
                            // If base increased, balance should decrease by the difference
                            // If base decreased, balance should increase by the difference
                            $newTransactionBalance = $currentTransactionBalance - $amountDifference;
                            
                            // Update amount field to new base price
                            $updateSupplierAmountQuery = "UPDATE supplier_transactions SET amount = ? WHERE id = ? AND tenant_id = ?";
                            $stmtUpdateSupplierAmount = $conn->prepare($updateSupplierAmountQuery);
                            $stmtUpdateSupplierAmount->bind_param('dii', $base, $supplierTransactionId, $tenant_id);
                            $stmtUpdateSupplierAmount->execute();
                            $stmtUpdateSupplierAmount->close();
                            
                            // Update existing transaction record with adjusted balance
                            $updateSupplierTransactionQuery = "UPDATE supplier_transactions SET balance = ?, remarks = CONCAT('Updated: ', remarks) WHERE id = ? AND tenant_id = ?";
                            $stmtUpdateSupplierTransaction = $conn->prepare($updateSupplierTransactionQuery);
                            $stmtUpdateSupplierTransaction->bind_param('dii', $newTransactionBalance, $supplierTransactionId, $tenant_id);
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
                                                                AND id != ?
                                                                AND tenant_id = ?";
                            } else {
                                // Base amount decreased, increase subsequent balances
                                $updateSubsequentSupplierQuery = "UPDATE supplier_transactions 
                                                                SET balance = balance + ? 
                                                                WHERE supplier_id = ? 
                                                                AND transaction_date > ? 
                                                                AND id != ?
                                                                AND tenant_id = ?";
                            }
                            
                            $stmtUpdateSubsequentSupplier = $conn->prepare($updateSubsequentSupplierQuery);
                            $absAmountDifference = abs($amountDifference);
                            $stmtUpdateSubsequentSupplier->bind_param('disii', $absAmountDifference, $supplier, $supplierTransactionDate, $supplierTransactionId, $tenant_id);
                            $stmtUpdateSubsequentSupplier->execute();
                            $stmtUpdateSubsequentSupplier->close();
                        } else {
                            // Create new transaction record if one doesn't exist
                            $insertSupplierTransactionQuery = "INSERT INTO supplier_transactions (supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_of, tenant_id) VALUES (?, ?, 'debit', ?, ?, ?, 'ticket_reserve', ?)";
                            $stmtInsertSupplierTransaction = $conn->prepare($insertSupplierTransactionQuery);
                            $description = "Purchase for ticket: $passenger_name ($origin to $destination)";
                            $stmtInsertSupplierTransaction->bind_param('iiddsii', $supplier, $id, $base, $newSupplierBalance, $description, $tenant_id);
                            $stmtInsertSupplierTransaction->execute();
                            $stmtInsertSupplierTransaction->close();
                        }
                        $stmtCheckSupplierTransaction->close();
                    }
                }
            }
        }
        
        // Handle client changes or sold price differences
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
                    $updateOldClientQuery = "UPDATE clients SET $balanceField = $balanceField - ? WHERE id = ? AND tenant_id = ?";
                    $stmtUpdateOldClient = $conn->prepare($updateOldClientQuery);
                    $stmtUpdateOldClient->bind_param('dii', $originalData['sold'], $originalClient, $tenant_id);
                    $stmtUpdateOldClient->execute();
                    $stmtUpdateOldClient->close();
                    
                    // Check if transaction record exists for old client
                    $checkOldClientTransactionQuery = "SELECT id FROM client_transactions WHERE client_id = ? AND reference_id = ? AND transaction_of = 'ticket_reserve' AND tenant_id = ? LIMIT 1";
                    $stmtCheckOldClientTransaction = $conn->prepare($checkOldClientTransactionQuery);
                    $stmtCheckOldClientTransaction->bind_param('iii', $originalClient, $id, $tenant_id);
                    $stmtCheckOldClientTransaction->execute();
                    $oldClientTransactionResult = $stmtCheckOldClientTransaction->get_result();
                    $oldClientTransactionExists = $oldClientTransactionResult->num_rows > 0;
                    $stmtCheckOldClientTransaction->close();
                    
                    if ($oldClientTransactionExists) {
                        // Update existing transaction record
                        $updateOldClientTransactionQuery = "UPDATE client_transactions SET type = 'cancelled', description = CONCAT(description, ' (Client changed)') WHERE client_id = ? AND reference_id = ? AND transaction_of = 'ticket_reserve' AND tenant_id = ?";
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
                        $updateClientQuery = "UPDATE clients SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ?";
                        $stmtUpdateClient = $conn->prepare($updateClientQuery);
                        $stmtUpdateClient->bind_param('dii', $sold, $sold_to, $tenant_id);
                        $stmtUpdateClient->execute();
                        $stmtUpdateClient->close();
                        
                        // Get current client balance for the transaction record
                        $getCurrentBalanceQuery = "SELECT $balanceField FROM clients WHERE id = ? AND tenant_id = ?";
                        $stmtGetCurrentBalance = $conn->prepare($getCurrentBalanceQuery);
                        $stmtGetCurrentBalance->bind_param('ii', $sold_to, $tenant_id);
                        $stmtGetCurrentBalance->execute();
                        $stmtGetCurrentBalance->bind_result($currentBalance);
                        $stmtGetCurrentBalance->fetch();
                        $stmtGetCurrentBalance->close();
                        
                        // Create new transaction record for new client
                        $insertClientTransactionQuery = "INSERT INTO client_transactions (client_id, reference_id, type, amount, currency, balance, description, transaction_of, tenant_id) VALUES (?, ?, 'debit', ?, ?, ?, ?, 'ticket_reserve', ?)";
                        $stmtInsertClientTransaction = $conn->prepare($insertClientTransactionQuery);
                        $description = "Sale for ticket: $passenger_name ($origin to $destination)";
                        $stmtInsertClientTransaction->bind_param('iidsdsii', $sold_to, $id, $sold, $currency, $currentBalance, $description, $tenant_id);
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
                        $stmtGetCurrentBalance->bind_result($currentBalance);
                        $stmtGetCurrentBalance->fetch();
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
                        $checkClientTransactionQuery = "SELECT id, created_at, balance, amount FROM client_transactions WHERE client_id = ? AND reference_id = ? AND transaction_of = 'ticket_reserve' AND tenant_id = ? LIMIT 1";
                        $stmtCheckClientTransaction = $conn->prepare($checkClientTransactionQuery);
                        $stmtCheckClientTransaction->bind_param('iii', $sold_to, $id, $tenant_id);
                        $stmtCheckClientTransaction->execute();
                        $clientTransactionResult = $stmtCheckClientTransaction->get_result();
                        
                        if ($clientTransactionResult->num_rows > 0) {
                            $transactionRow = $clientTransactionResult->fetch_assoc();
                            $transactionId = $transactionRow['id'];
                            $transactionDate = $transactionRow['created_at'];
                            $currentTransactionBalance = $transactionRow['balance'];
                            $currentTransactionAmount = $transactionRow['amount'];
                            
                            // Calculate the difference between the new sold amount and the current transaction amount
                            $amountDifference = $sold - $currentTransactionAmount;
                            
                            // Calculate the new balance for this transaction
                            // If amount increased, balance should decrease by the difference
                            // If amount decreased, balance should increase by the difference
                            $newTransactionBalance = $currentTransactionBalance - $amountDifference;
                            
                            // Update amount field to new sold price
                            $updateClientAmountQuery = "UPDATE client_transactions SET amount = ? WHERE id = ? AND tenant_id = ?";
                            $stmtUpdateClientAmount = $conn->prepare($updateClientAmountQuery);
                            $stmtUpdateClientAmount->bind_param('dii', $sold, $transactionId, $tenant_id);
                            $stmtUpdateClientAmount->execute();
                            $stmtUpdateClientAmount->close();
                            
                            // Update existing transaction record with adjusted balance
                            $updateClientTransactionQuery = "UPDATE client_transactions SET balance = ?, description = CONCAT('Updated: ', description) WHERE id = ? AND tenant_id = ?";
                            $stmtUpdateClientTransaction = $conn->prepare($updateClientTransactionQuery);
                            $stmtUpdateClientTransaction->bind_param('dii', $newTransactionBalance, $transactionId, $tenant_id);
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
                                                         AND id != ?
                                                         AND tenant_id = ?";
                            } else {
                                // Amount decreased, increase subsequent balances
                                $updateSubsequentQuery = "UPDATE client_transactions 
                                                         SET balance = balance + ? 
                                                         WHERE client_id = ? 
                                                         AND created_at > ? 
                                                         AND currency = ? 
                                                         AND id != ?
                                                         AND tenant_id = ?";
                            }
                            
                            $stmtUpdateSubsequent = $conn->prepare($updateSubsequentQuery);
                            $absAmountDifference = abs($amountDifference);
                            $stmtUpdateSubsequent->bind_param('dissi', $absAmountDifference, $sold_to, $transactionDate, $currency, $transactionId, $tenant_id);
                            $stmtUpdateSubsequent->execute();
                            $stmtUpdateSubsequent->close();
                        } else {
                            // Create new transaction record if one doesn't exist
                            $insertClientTransactionQuery = "INSERT INTO client_transactions (client_id, reference_id, type, amount, currency, balance, description, transaction_of, tenant_id) VALUES (?, ?, 'debit', ?, ?, ?, ?, 'ticket_reserve', ?)";
                            $stmtInsertClientTransaction = $conn->prepare($insertClientTransactionQuery);
                            $description = "Sale for ticket: $passenger_name ($origin to $destination)";
                            $stmtInsertClientTransaction->bind_param('iidsdsii', $sold_to, $id, $sold, $currency, $newBalance, $description, $tenant_id);
                            $stmtInsertClientTransaction->execute();
                            $stmtInsertClientTransaction->close();
                        }
                        $stmtCheckClientTransaction->close();
                    }
                }
            }
        }

        // Update the ticket with all fields
        $updateTicketQuery = "UPDATE ticket_reservations SET 
            supplier = ?, 
            sold_to = ?, 
            trip_type = ?, 
            title = ?, 
            gender = ?, 
            passenger_name = ?, 
            pnr = ?, 
            phone = ?, 
            origin = ?, 
            destination = ?, 
            return_origin = ?, 
            return_destination = ?, 
            airline = ?, 
            issue_date = ?, 
            departure_date = ?, 
            return_date = ?, 
            price = ?, 
            sold = ?, 
            profit = ?, 
            currency = ?, 
            description = ?, 
            paid_to = ?
   
            WHERE id = ? AND tenant_id = ?";
        
        $stmtTicket = $conn->prepare($updateTicketQuery);
        $stmtTicket->bind_param(
            'iissssssssssssssdddssisi', 
            $supplier, 
            $sold_to, 
            $trip_type, 
            $title, 
            $gender, 
            $passenger_name, 
            $pnr, 
            $phone, 
            $origin, 
            $destination, 
            $return_origin, 
            $return_destination, 
            $airline, 
            $issue_date, 
            $departure_date, 
            $return_date, 
            $base,  // This maps to the 'price' field in the database
            $sold, 
            $profit, 
            $currency, 
            $description, 
            $paid_to, 

            $id,
            $tenant_id
        );
        
        $stmtTicket->execute();
        
        // Add activity logging
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Prepare old values
        $old_values = [
            'ticket_id' => $id,
            'supplier' => $originalData['supplier'],
            'sold_to' => $originalData['sold_to'],
            'price' => $originalData['price'],
            'sold' => $originalData['sold'],
            'currency' => $originalData['currency']
        ];
        
        // Prepare new values
        $new_values = [
            'supplier' => $supplier,
            'sold_to' => $sold_to,
            'trip_type' => $trip_type,
            'title' => $title,
            'gender' => $gender,
            'passenger_name' => $passenger_name,
            'pnr' => $pnr,
            'phone' => $phone,
            'origin' => $origin,
            'destination' => $destination,
            'return_origin' => $return_origin,
            'return_destination' => $return_destination,
            'airline' => $airline,
            'issue_date' => $issue_date,
            'departure_date' => $departure_date,
            'return_date' => $return_date,
            'price' => $base,
            'sold' => $sold,
            'profit' => $profit,
            'currency' => $currency,
            'description' => $description,
            'paid_to' => $paid_to
        ];
        
        $old_values = json_encode($old_values);
        $new_values = json_encode($new_values);
        $action = 'update';
        $table_name = 'ticket_reservations';
        // Insert activity log
        $activity_log_stmt = $conn->prepare("INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $activity_log_stmt->bind_param("isisssssi", 
            $user_id, 
            $action, 
            $table_name, 
            $id, 
            $old_values, 
            $new_values, 
            $ip_address, 
            $user_agent,
            $tenant_id
        );
        $activity_log_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $response['success'] = true;
        $response['message'] = 'Ticket updated successfully';
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $response['message'] = 'Error updating ticket: ' . $e->getMessage();
    }
}

echo json_encode($response);
$conn->close();
?>