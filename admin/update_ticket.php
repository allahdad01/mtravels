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

// Validate editExchangeRate
$editExchangeRate = isset($_POST['editExchangeRate']) ? DbSecurity::validateInput($_POST['editExchangeRate'], 'float', ['min' => 0]) : null;

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
    $exchangeRate = isset($_POST['editExchangeRate']) ? floatval($_POST['editExchangeRate']) : 1.0;
    $marketExchangeRate = isset($_POST['marketExchangeRate']) ? floatval($_POST['marketExchangeRate']) : 1.0;

    // Get original values to calculate differences
    $originalQuery = "SELECT price, sold, supplier, sold_to, currency FROM ticket_bookings WHERE id = ?";
    $stmtOriginal = $conn->prepare($originalQuery);
    $stmtOriginal->bind_param('i', $id);
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
                    // Get all transactions for the old supplier related to this ticket
                    $getOldSupplierTransactionsQuery = "SELECT * FROM supplier_transactions 
                                                       WHERE supplier_id = ? 
                                                       AND reference_id = ? 
                                                       AND transaction_of = 'ticket_sale'
                                                       ORDER BY transaction_date ASC";
                    $stmtGetOldSupplierTransactions = $conn->prepare($getOldSupplierTransactionsQuery);
                    $stmtGetOldSupplierTransactions->bind_param('iii', $originalSupplier, $id, $tenant_id);
                    $stmtGetOldSupplierTransactions->execute();
                    $oldSupplierTransactions = $stmtGetOldSupplierTransactions->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmtGetOldSupplierTransactions->close();

                    // Calculate total amount from old supplier transactions
                    $totalAmount = 0;
                    foreach ($oldSupplierTransactions as $transaction) {
                        $totalAmount += $transaction['amount'];
                    }

                    // Get the transaction we're transferring to get its date
                    $getTransferTransactionQuery = "SELECT transaction_date FROM supplier_transactions 
                                                  WHERE supplier_id = ? 
                                                  AND reference_id = ? 
                                                  AND transaction_of = 'ticket_sale'
                                                  AND tenant_id = ?
                                                  LIMIT 1";
                    $stmtGetTransferTransaction = $conn->prepare($getTransferTransactionQuery);
                    $stmtGetTransferTransaction->bind_param('iii', $originalSupplier, $id, $tenant_id);
                    $stmtGetTransferTransaction->execute();
                    $transferResult = $stmtGetTransferTransaction->get_result();
                    $transferDate = $transferResult->fetch_assoc()['transaction_date'];
                    $stmtGetTransferTransaction->close();

                    // Update old supplier balance - ADDING back the amount since we're removing the ticket
                    // Example: If balance was -5000 and removing amount 200, new balance becomes -4800 (supplier owes less)
                    $updateOldSupplierQuery = "UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ?";
                    $stmtUpdateOldSupplier = $conn->prepare($updateOldSupplierQuery);
                    $stmtUpdateOldSupplier->bind_param('dii', $totalAmount, $originalSupplier, $tenant_id);
                    $stmtUpdateOldSupplier->execute();
                    $stmtUpdateOldSupplier->close();

                    // Update subsequent transactions for old supplier - ADDING back the amount
                    // But only for transactions that occurred after this specific transaction
                    $updateOldSupplierSubsequentQuery = "UPDATE supplier_transactions 
                                                       SET balance = balance + ?
                                                       WHERE supplier_id = ? 
                                                       AND transaction_date > (
                                                           SELECT transaction_date 
                                                           FROM supplier_transactions 
                                                           WHERE supplier_id = ? 
                                                           AND reference_id = ? 
                                                           AND transaction_of = 'ticket_sale'
                                                           AND tenant_id = ?
                                                           LIMIT 1
                                                       )
                                                       ORDER BY transaction_date ASC";
                    $stmtUpdateOldSupplierSubsequent = $conn->prepare($updateOldSupplierSubsequentQuery);
                    $stmtUpdateOldSupplierSubsequent->bind_param('diiii', $totalAmount, $originalSupplier, $originalSupplier, $id, $tenant_id);
                    $stmtUpdateOldSupplierSubsequent->execute();
                    $stmtUpdateOldSupplierSubsequent->close();

                    // Check if new supplier is external
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

                    // Only update balances if new supplier is external
                    if ($isExternal) {
                        // Get current balance of new supplier
                        $getCurrentSupplierBalanceQuery = "SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ?";
                        $stmtGetCurrentSupplierBalance = $conn->prepare($getCurrentSupplierBalanceQuery);
                        $stmtGetCurrentSupplierBalance->bind_param('ii', $supplier, $tenant_id);
                        $stmtGetCurrentSupplierBalance->execute();
                        $stmtGetCurrentSupplierBalance->bind_result($currentSupplierBalance);
                        $stmtGetCurrentSupplierBalance->fetch();
                        $stmtGetCurrentSupplierBalance->close();

                        // Update new supplier balance - SUBTRACTING the amount since we're adding a ticket
                        // Example: If balance was -4800 and adding amount 200, new balance becomes -5000 (supplier owes more)
                        $newBalance = $currentSupplierBalance - $base;
                        $updateSupplierQuery = "UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?";
                        $stmtUpdateSupplier = $conn->prepare($updateSupplierQuery);
                        $stmtUpdateSupplier->bind_param('dii', $newBalance, $supplier, $tenant_id);
                        $stmtUpdateSupplier->execute();
                        $stmtUpdateSupplier->close();

                        // Check if there are any existing transactions to transfer
                        $checkExistingTransactionsQuery = "SELECT COUNT(*) FROM supplier_transactions 
                                                         WHERE supplier_id = ? 
                                                         AND reference_id = ? 
                                                         AND transaction_of = 'ticket_sale'
                                                         AND tenant_id = ?";
                        $stmtCheckExisting = $conn->prepare($checkExistingTransactionsQuery);
                        $stmtCheckExisting->bind_param('iiii', $originalSupplier, $id, $tenant_id);
                        $stmtCheckExisting->execute();
                        $stmtCheckExisting->bind_result($existingCount);
                        $stmtCheckExisting->fetch();
                        $stmtCheckExisting->close();

                        if ($existingCount > 0) {
                            // Update supplier_id in transactions and add note about transfer
                            $updateTransactionsQuery = "UPDATE supplier_transactions 
                                                      SET supplier_id = ?,
                                                          amount = ?,
                                                          transaction_date = NOW(),
                                                          balance = ?,
                                                          remarks = CONCAT(remarks, ' (Transferred from supplier ', ?, ')')
                                                      WHERE supplier_id = ? 
                                                      AND reference_id = ? 
                                                      AND transaction_of = 'ticket_sale'
                                                      AND tenant_id = ?";
                            $stmtUpdateTransactions = $conn->prepare($updateTransactionsQuery);
                            $stmtUpdateTransactions->bind_param('iddiisii', $supplier, $base, $newBalance, $originalSupplier, $originalSupplier, $id, $tenant_id);
                            $stmtUpdateTransactions->execute();
                            $stmtUpdateTransactions->close();
                        } else {
                            // For a new transaction record, the balance should equal the current supplier balance
                            // Create new transaction record
                            $insertSupplierTransactionQuery = "INSERT INTO supplier_transactions (supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_of, transaction_date, tenant_id) 
                                                             VALUES (?, ?, 'debit', ?, ?, ?, 'ticket_sale', NOW(), ?)";
                            $stmtInsertSupplierTransaction = $conn->prepare($insertSupplierTransactionQuery);
                            $description = "Purchase for ticket: $passenger_name ($origin to $destination)";
                            $stmtInsertSupplierTransaction->bind_param('iiddsii', $supplier, $id, $base, $newBalance, $description, $tenant_id);
                            $stmtInsertSupplierTransaction->execute();
                            $stmtInsertSupplierTransaction->close();
                        }
                    }

                    // Delete old supplier transactions for this ticket
                    $deleteOldTransactionsQuery = "DELETE FROM supplier_transactions 
                                                 WHERE supplier_id = ? 
                                                 AND reference_id = ? 
                                                 AND transaction_of = 'ticket_sale'
                                                 AND tenant_id = ?";
                    $stmtDeleteOldTransactions = $conn->prepare($deleteOldTransactionsQuery);
                    $stmtDeleteOldTransactions->bind_param('iii', $originalSupplier, $id, $tenant_id);
                    $stmtDeleteOldTransactions->execute();
                    $stmtDeleteOldTransactions->close();
                }
                // Handle case where supplier remains the same but price changes
                else if ($supplier == $originalSupplier && $priceDifference != 0 && $oldSupplierIsExternal) {
                    // Get current supplier balance
                    $getCurrentSupplierBalanceQuery = "SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ?";
                    $stmtGetCurrentSupplierBalance = $conn->prepare($getCurrentSupplierBalanceQuery);
                    $stmtGetCurrentSupplierBalance->bind_param('ii', $supplier, $tenant_id);
                    $stmtGetCurrentSupplierBalance->execute();
                    $stmtGetCurrentSupplierBalance->bind_result($currentSupplierBalance);
                    $stmtGetCurrentSupplierBalance->fetch();
                    $stmtGetCurrentSupplierBalance->close();
                    
                    // Update supplier balance based on price difference
                    // If priceDifference is positive: base decreased, add to balance (supplier gets money back)
                    // If priceDifference is negative: base increased, subtract from balance (supplier pays more)
                    $newBalance = $currentSupplierBalance + $priceDifference;
                    $updateSupplierQuery = "UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ?";
                    $stmtUpdateSupplier = $conn->prepare($updateSupplierQuery);
                    $stmtUpdateSupplier->bind_param('dii', $newBalance, $supplier, $tenant_id);
                    $stmtUpdateSupplier->execute();
                    $stmtUpdateSupplier->close();
                    
                    // Check if transaction record exists for this supplier
                    $checkSupplierTransactionQuery = "SELECT id, transaction_date, balance, amount FROM supplier_transactions 
                                                     WHERE supplier_id = ? 
                                                     AND reference_id = ? 
                                                     AND transaction_of = 'ticket_sale' 
                                                     AND tenant_id = ?
                                                     LIMIT 1";
                    $stmtCheckSupplierTransaction = $conn->prepare($checkSupplierTransactionQuery);
                    $stmtCheckSupplierTransaction->bind_param('iii', $supplier, $id, $tenant_id);
                    $stmtCheckSupplierTransaction->execute();
                    $supplierTransactionResult = $stmtCheckSupplierTransaction->get_result();
                    
                    if ($supplierTransactionResult->num_rows > 0) {
                        $transactionRow = $supplierTransactionResult->fetch_assoc();
                        $transactionId = $transactionRow['id'];
                        $transactionDate = $transactionRow['transaction_date'];
                        $currentTransactionAmount = $transactionRow['amount'];
                        
                        // Get the current transaction's date and balance
                        $getCurrentTransactionQuery = "SELECT transaction_date, balance FROM supplier_transactions WHERE id = ? AND tenant_id = ? LIMIT 1";
                        $stmtGetCurrentTransaction = $conn->prepare($getCurrentTransactionQuery);
                        $stmtGetCurrentTransaction->bind_param('ii', $transactionId, $tenant_id);
                        $stmtGetCurrentTransaction->execute();
                        $currentTransactionResult = $stmtGetCurrentTransaction->get_result();
                        $currentTransactionData = $currentTransactionResult->fetch_assoc();
                        $currentTransactionDate = $currentTransactionData['transaction_date'];
                        $currentTransactionBalance = $currentTransactionData['balance'];
                        $stmtGetCurrentTransaction->close();
                        
                        // Calculate the new transaction balance by applying the price difference
                        $newTransactionBalance = $currentTransactionBalance + $priceDifference;
                        
                        // Update existing transaction record with new amount and balance
                        $updateSupplierTransactionQuery = "UPDATE supplier_transactions 
                                                         SET amount = ?,
                                                             balance = ?,
                                                             remarks = CONCAT('Updated: ', remarks) 
                                                         WHERE id = ? AND tenant_id = ?";
                        $stmtUpdateSupplierTransaction = $conn->prepare($updateSupplierTransactionQuery);
                        $stmtUpdateSupplierTransaction->bind_param('ddii', $base, $newTransactionBalance, $transactionId, $tenant_id);
                        $stmtUpdateSupplierTransaction->execute();
                        $stmtUpdateSupplierTransaction->close();

                        // Update all subsequent transactions' balances
                        $updateSubsequentQuery = "UPDATE supplier_transactions 
                                                 SET balance = balance + ? 
                                                 WHERE supplier_id = ?   
                                                 AND id > ?
                                                 AND tenant_id = ?
                                                 ORDER BY transaction_date ASC";
                        
                        $stmtUpdateSubsequent = $conn->prepare($updateSubsequentQuery);
                        $stmtUpdateSubsequent->bind_param('diii', $priceDifference, $supplier, $transactionId, $tenant_id);
                        $stmtUpdateSubsequent->execute();
                        $stmtUpdateSubsequent->close();
                    } else {
                        // For a new transaction record, the balance should equal the current supplier balance
                        // Create new transaction record
                        $insertSupplierTransactionQuery = "INSERT INTO supplier_transactions (supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_of, transaction_date, tenant_id) 
                                                         VALUES (?, ?, 'debit', ?, ?, ?, 'ticket_sale', NOW(), ?)";
                        $stmtInsertSupplierTransaction = $conn->prepare($insertSupplierTransactionQuery);
                        $description = "Purchase for ticket: $passenger_name ($origin to $destination)";
                        $stmtInsertSupplierTransaction->bind_param('iiddsii', $supplier, $id, $base, $newBalance, $description, $tenant_id);
                        $stmtInsertSupplierTransaction->execute();
                        $stmtInsertSupplierTransaction->close();
                    }
                    $stmtCheckSupplierTransaction->close();
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
                    // Get all transactions for the old client related to this ticket
                    $getOldClientTransactionsQuery = "SELECT * FROM client_transactions 
                                                    WHERE client_id = ? 
                                                    AND reference_id = ? 
                                                    AND transaction_of = 'ticket_sale'
                                                    AND tenant_id = ?
                                                    ORDER BY created_at ASC";
                    $stmtGetOldClientTransactions = $conn->prepare($getOldClientTransactionsQuery);
                    $stmtGetOldClientTransactions->bind_param('iii', $originalClient, $id, $tenant_id);
                    $stmtGetOldClientTransactions->execute();
                    $oldClientTransactions = $stmtGetOldClientTransactions->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmtGetOldClientTransactions->close();

                    // Get the earliest transaction date for this ticket
                    $earliestTransactionDate = null;
                    if (!empty($oldClientTransactions)) {
                        $earliestTransactionDate = $oldClientTransactions[0]['created_at'];
                    }

                    // Get the transaction we're transferring to get its date
                    $getTransferTransactionQuery = "SELECT created_at FROM client_transactions 
                                                  WHERE client_id = ? 
                                                  AND reference_id = ? 
                                                  AND transaction_of = 'ticket_sale'
                                                  AND tenant_id = ?
                                                  LIMIT 1";
                    $stmtGetTransferTransaction = $conn->prepare($getTransferTransactionQuery);
                    $stmtGetTransferTransaction->bind_param('iii', $originalClient, $id, $tenant_id);
                    $stmtGetTransferTransaction->execute();
                    $transferTransactionResult = $stmtGetTransferTransaction->get_result();
                    $transferTransactionDate = $transferTransactionResult->fetch_assoc()['created_at'];
                    $stmtGetTransferTransaction->close();

                    // Calculate total amounts for USD and AFS
                    $totalUsdAmount = 0;
                    $totalAfsAmount = 0;
                    foreach ($oldClientTransactions as $transaction) {
                        if (strtolower($transaction['currency']) === 'usd') {
                            $totalUsdAmount += $transaction['amount'];
                        } else {
                            $totalAfsAmount += $transaction['amount'];
                        }
                    }

                    // Update old client balances - ADDING the amounts since we're removing these transactions
                    if ($totalUsdAmount > 0) {
                        // When removing a ticket from a client, we need to add the amount back
                        // For example: if balance is -6095.600 and removing amount 265
                        // New balance should be -5830.600 (client owes less)
                        $updateOldClientUsdQuery = "UPDATE clients SET usd_balance = usd_balance + ? WHERE id = ? AND tenant_id = ?";
                        $stmtUpdateOldClientUsd = $conn->prepare($updateOldClientUsdQuery);
                        $stmtUpdateOldClientUsd->bind_param('dii', $totalUsdAmount, $originalClient, $tenant_id);
                        $stmtUpdateOldClientUsd->execute();
                        $stmtUpdateOldClientUsd->close();

                        // Update subsequent USD transactions for old client
                        $updateOldClientUsdSubsequentQuery = "UPDATE client_transactions 
                                                            SET balance = balance + ?
                                                            WHERE client_id = ? 
                                                            AND created_at > ? 
                                                            AND id != (SELECT id FROM client_transactions 
                                                                     WHERE client_id = ? 
                                                                     AND reference_id = ? 
                                                                     AND transaction_of = 'ticket_sale' 
                                                                     LIMIT 1)
                                                            AND currency = 'USD'
                                                            AND tenant_id = ?
                                                            ORDER BY created_at ASC";
                        $stmtUpdateOldClientUsdSubsequent = $conn->prepare($updateOldClientUsdSubsequentQuery);
                        $stmtUpdateOldClientUsdSubsequent->bind_param('disiii', $totalUsdAmount, $originalClient, $transferTransactionDate, $originalClient, $id, $tenant_id);
                        $stmtUpdateOldClientUsdSubsequent->execute();
                        $stmtUpdateOldClientUsdSubsequent->close();
                    }
                    
                    if ($totalAfsAmount > 0) {
                        // When removing a ticket from a client, we need to add the amount back
                        $updateOldClientAfsQuery = "UPDATE clients SET afs_balance = afs_balance + ? WHERE id = ? AND tenant_id = ?";
                        $stmtUpdateOldClientAfs = $conn->prepare($updateOldClientAfsQuery);
                        $stmtUpdateOldClientAfs->bind_param('dii', $totalAfsAmount, $originalClient, $tenant_id);
                        $stmtUpdateOldClientAfs->execute();
                        $stmtUpdateOldClientAfs->close();

                        // Update subsequent AFS transactions for old client
                        $updateOldClientAfsSubsequentQuery = "UPDATE client_transactions 
                                                            SET balance = balance + ?
                                                            WHERE client_id = ? 
                                                            AND created_at > ? 
                                                            AND id != (SELECT id FROM client_transactions 
                                                                     WHERE client_id = ? 
                                                                     AND reference_id = ? 
                                                                     AND transaction_of = 'ticket_sale' 
                                                                     LIMIT 1)
                                                            AND currency = 'AFS'
                                                            AND tenant_id = ?
                                                            ORDER BY created_at ASC";
                        $stmtUpdateOldClientAfsSubsequent = $conn->prepare($updateOldClientAfsSubsequentQuery);
                        $stmtUpdateOldClientAfsSubsequent->bind_param('disiii', $totalAfsAmount, $originalClient, $transferTransactionDate, $originalClient, $id, $tenant_id);
                        $stmtUpdateOldClientAfsSubsequent->execute();
                        $stmtUpdateOldClientAfsSubsequent->close();
                    }

                    // Check if new client is regular
                    $newClientQuery = "SELECT * FROM clients WHERE id = ? AND tenant_id = ?";
                    $stmtNewClient = $conn->prepare($newClientQuery);
                    $stmtNewClient->bind_param('ii', $sold_to, $tenant_id);
                    $stmtNewClient->execute();
                    $newClientResult = $stmtNewClient->get_result();
                    $newClientData = $newClientResult->fetch_assoc();
                    $stmtNewClient->close();

                    $newClientType = isset($newClientData['client_type']) ? $newClientData['client_type'] : '';
                    if (!$newClientType) {
                        $newClientType = isset($newClientData['type']) ? $newClientData['type'] : '';
                    }
                    $newClientIsRegular = (strtolower(trim($newClientType)) === 'regular');

                    // Only update balances if new client is regular
                    if ($newClientIsRegular) {
                        // Get current balances of new client
                        $newClientUsdBalance = 0;
                        $newClientAfsBalance = 0;
                        
                        $getNewClientUsdBalanceQuery = "SELECT usd_balance FROM clients WHERE id = ? AND tenant_id = ?";
                        $stmtGetNewClientUsdBalance = $conn->prepare($getNewClientUsdBalanceQuery);
                        $stmtGetNewClientUsdBalance->bind_param('ii', $sold_to, $tenant_id);
                        $stmtGetNewClientUsdBalance->execute();
                        $stmtGetNewClientUsdBalance->bind_result($newClientUsdBalance);
                        $stmtGetNewClientUsdBalance->fetch();
                        $stmtGetNewClientUsdBalance->close();

                        $getNewClientAfsBalanceQuery = "SELECT afs_balance FROM clients WHERE id = ? AND tenant_id = ?";
                        $stmtGetNewClientAfsBalance = $conn->prepare($getNewClientAfsBalanceQuery);
                        $stmtGetNewClientAfsBalance->bind_param('ii', $sold_to, $tenant_id);
                        $stmtGetNewClientAfsBalance->execute();
                        $stmtGetNewClientAfsBalance->bind_result($newClientAfsBalance);
                        $stmtGetNewClientAfsBalance->fetch();
                        $stmtGetNewClientAfsBalance->close();

                        // Update new client balances
                        if ($totalUsdAmount > 0) {
                            // When adding a ticket to a client with balance:
                            // Example: If balance is -4930.600 and adding amount 215
                            // We want final balance to be -5145.600 (client owes more)
                            // Simply add the negative of the amount to make balance more negative
                            $negativeAmount = abs($totalUsdAmount);
                            $ClientUsdBalance = $newClientUsdBalance - $negativeAmount;
                            $updateNewClientUsdQuery = "UPDATE clients SET usd_balance = ? WHERE id = ? AND tenant_id = ?";
                            $stmtUpdateNewClientUsd = $conn->prepare($updateNewClientUsdQuery);
                            $stmtUpdateNewClientUsd->bind_param('dii', $ClientUsdBalance, $sold_to, $tenant_id);
                            $stmtUpdateNewClientUsd->execute();
                            $stmtUpdateNewClientUsd->close();

                            // Update subsequent USD transactions for new client
                            if ($earliestTransactionDate) {
                                $updateNewClientUsdSubsequentQuery = "UPDATE client_transactions 
                                                                    SET balance = balance - ?
                                                                    WHERE client_id = ? 
                                                                    AND created_at > ? 
                                                                    AND id != (SELECT id FROM client_transactions 
                                                                             WHERE client_id = ? 
                                                                             AND reference_id = ? 
                                                                             AND transaction_of = 'ticket_sale' 
                                                                             LIMIT 1)
                                                                    AND currency = 'USD'
                                                                    AND tenant_id = ?
                                                                    ORDER BY created_at ASC";
                                $stmtUpdateNewClientUsdSubsequent = $conn->prepare($updateNewClientUsdSubsequentQuery);
                                $stmtUpdateNewClientUsdSubsequent->bind_param('disiiii', $negativeAmount, $sold_to, $earliestTransactionDate, $sold_to, $id, $tenant_id);
                                $stmtUpdateNewClientUsdSubsequent->execute();
                                $stmtUpdateNewClientUsdSubsequent->close();
                            }
                        }
                        
                        if ($totalAfsAmount > 0) {
                            // When adding a ticket to a client with negative balance, we need to subtract the amount
                            $updateNewClientAfsQuery = "UPDATE clients SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ?";
                            $stmtUpdateNewClientAfs = $conn->prepare($updateNewClientAfsQuery);
                            $stmtUpdateNewClientAfs->bind_param('dii', $totalAfsAmount, $sold_to, $tenant_id);
                            $stmtUpdateNewClientAfs->execute();
                            $stmtUpdateNewClientAfs->close();

                            // Update subsequent AFS transactions for new client
                            if ($earliestTransactionDate) {
                                $updateNewClientAfsSubsequentQuery = "UPDATE client_transactions 
                                                                    SET balance = balance - ?
                                                                    WHERE client_id = ? 
                                                                    AND created_at > ? 
                                                                    AND id != (SELECT id FROM client_transactions 
                                                                             WHERE client_id = ? 
                                                                             AND reference_id = ? 
                                                                             AND transaction_of = 'ticket_sale' 
                                                                             LIMIT 1)
                                                                    AND currency = 'AFS'
                                                                    AND tenant_id = ?
                                                                    ORDER BY created_at ASC";
                                $stmtUpdateNewClientAfsSubsequent = $conn->prepare($updateNewClientAfsSubsequentQuery);
                                $stmtUpdateNewClientAfsSubsequent->bind_param('disiiii', $totalAfsAmount, $sold_to, $earliestTransactionDate, $sold_to, $id, $tenant_id);
                                $stmtUpdateNewClientAfsSubsequent->execute();
                                $stmtUpdateNewClientAfsSubsequent->close();
                            }
                        }
                    }

                    // Update client_id in transactions and add note about transfer
                    $updateTransactionsQuery = "UPDATE client_transactions 
                                              SET client_id = ?,
                                                  description = CONCAT(description, ' (Transferred from client ', ?, ')')
                                              WHERE client_id = ? 
                                              AND reference_id = ? 
                                              AND transaction_of = 'ticket_sale'
                                              AND tenant_id = ?";
                    $stmtUpdateTransactions = $conn->prepare($updateTransactionsQuery);
                    $stmtUpdateTransactions->bind_param('iiiii', $sold_to, $originalClient, $originalClient, $id, $tenant_id);
                    $stmtUpdateTransactions->execute();
                    $stmtUpdateTransactions->close();
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
                        // Update new client balance - SUBTRACTING the new sold amount
                        // This DECREASES the balance (client owes more)
                        $balanceField = strtolower($currency) === 'usd' ? 'usd_balance' : 'afs_balance';
                        $updateClientQuery = "UPDATE clients SET $balanceField = $balanceField - ? WHERE id = ? AND tenant_id = ?";
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
                        $insertClientTransactionQuery = "INSERT INTO client_transactions (client_id, reference_id, type, amount, currency, balance, description, transaction_of, tenant_id) VALUES (?, ?, 'debit', ?, ?, ?, ?, 'ticket_sale', ?)";
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
                        $checkClientTransactionQuery = "SELECT id, created_at, balance, amount FROM client_transactions WHERE client_id = ? AND reference_id = ? AND transaction_of = 'ticket_sale' AND tenant_id = ? LIMIT 1";
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
                            
                            // For client transactions, subsequent balances should:
                            // - Increase (add) when amount decreases
                            // - Decrease (subtract) when amount increases
                            $balanceAdjustment = -$amountDifference;
                            
                            // Get the current transaction's date
                            $getCurrentTransactionQuery = "SELECT created_at FROM client_transactions WHERE id = ? AND tenant_id = ? LIMIT 1";
                            $stmtGetCurrentTransaction = $conn->prepare($getCurrentTransactionQuery);
                            $stmtGetCurrentTransaction->bind_param('ii', $transactionId, $tenant_id);
                            $stmtGetCurrentTransaction->execute();
                            $currentTransactionResult = $stmtGetCurrentTransaction->get_result();
                            $currentTransactionDate = $currentTransactionResult->fetch_assoc()['created_at'];
                            $stmtGetCurrentTransaction->close();
                            
                            // Update existing transaction record with adjusted balance
                            $updateClientTransactionQuery = "UPDATE client_transactions 
                                                           SET amount = ?,
                                                               balance = balance + ?,
                                                               description = CONCAT('Updated: ', description) 
                                                           WHERE id = ? AND tenant_id = ?";
                            $stmtUpdateClientTransaction = $conn->prepare($updateClientTransactionQuery);
                            $stmtUpdateClientTransaction->bind_param('ddii', $sold, $balanceAdjustment, $transactionId, $tenant_id);
                            $stmtUpdateClientTransaction->execute();
                            $stmtUpdateClientTransaction->close();
                            
                            // Update all subsequent transactions' balances
                            $updateSubsequentQuery = "UPDATE client_transactions 
                                                    SET balance = balance + ? 
                                                    WHERE client_id = ? 
                                                    AND currency = ? 
                                                    AND id > ?
                                                    AND tenant_id = ?
                                                    ORDER BY created_at ASC";
                            
                            $stmtUpdateSubsequent = $conn->prepare($updateSubsequentQuery);
                            $stmtUpdateSubsequent->bind_param('dissi', $balanceAdjustment, $sold_to, $currency, $transactionId, $tenant_id);
                            $stmtUpdateSubsequent->execute();
                            $stmtUpdateSubsequent->close();
                        } else {
                            // Create new transaction record if one doesn't exist
                            $insertClientTransactionQuery = "INSERT INTO client_transactions (client_id, reference_id, type, amount, currency, balance, description, transaction_of, tenant_id) VALUES (?, ?, 'debit', ?, ?, ?, ?, 'ticket_sale', ?)";
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
        $updateTicketQuery = "UPDATE ticket_bookings SET 
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
            paid_to = ?,
            exchange_rate = ?,
            market_exchange_rate = ?
            WHERE id = ? AND tenant_id = ?";
        
        $stmtTicket = $conn->prepare($updateTicketQuery);
        $stmtTicket->bind_param(
            'iissssssssssssssdddssisssi', 
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
            $exchangeRate,
            $marketExchangeRate,
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
            'paid_to' => $paid_to,
            'exchange_rate' => $exchangeRate,
            'market_exchange_rate' => $marketExchangeRate
        ];
        $action = 'update';
        $table_name = 'ticket_bookings';
        // Insert activity log
        $activity_log_stmt = $conn->prepare("INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // Store JSON encoded values in variables first
        $old_values_json = json_encode($old_values);
        $new_values_json = json_encode($new_values);
        
        $activity_log_stmt->bind_param("isisssssi", 
            $user_id, 
            $action, 
            $table_name, 
            $id, 
            $old_values_json,  // Use the stored JSON string
            $new_values_json,  // Use the stored JSON string
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