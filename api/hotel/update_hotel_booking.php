<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// ✅ CSRF Token Validation
if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once('../../includes/db.php');

// Note: $pdo is the PDO database connection from db.php

$response = ['success' => false, 'message' => ''];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and validate booking ID
    if (!isset($_POST['booking_id']) || empty($_POST['booking_id'])) {
        $response['message'] = 'Booking ID is required';
        echo json_encode($response);
        exit;
    }

    // Validate remarks
    $remarks = isset($_POST['remarks']) ? DbSecurity::validateInput($_POST['remarks'], 'string', ['maxlength' => 255]) : null;

    // Validate paid_to
    $paid_to = isset($_POST['paid_to']) ? DbSecurity::validateInput($_POST['paid_to'], 'string', ['maxlength' => 255]) : null;

    // Validate profit
    $profit = isset($_POST['profit']) ? DbSecurity::validateInput($_POST['profit'], 'float', ['min' => 0]) : null;

    // Validate accommodation_details
    $accommodation_details = isset($_POST['accommodation_details']) ? DbSecurity::validateInput($_POST['accommodation_details'], 'string', ['maxlength' => 255]) : null;

    // Validate check_out_date
    $check_out_date = isset($_POST['check_out_date']) ? DbSecurity::validateInput($_POST['check_out_date'], 'date') : null;

    // Validate contact_no
    $contact_no = isset($_POST['contact_no']) ? DbSecurity::validateInput($_POST['contact_no'], 'string', ['maxlength' => 255]) : null;

    // Validate gender
    $gender = isset($_POST['gender']) ? DbSecurity::validateInput($_POST['gender'], 'string', ['maxlength' => 255]) : null;

    // Validate title
    $title = isset($_POST['title']) ? DbSecurity::validateInput($_POST['title'], 'string', ['maxlength' => 255]) : null;

    // Validate currency
    $currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : null;

    // Validate sold_to
    $sold_to = isset($_POST['sold_to']) ? DbSecurity::validateInput($_POST['sold_to'], 'int', ['min' => 0]) : null;

    // Validate check_in_date
    $check_in_date = isset($_POST['check_in_date']) ? DbSecurity::validateInput($_POST['check_in_date'], 'date') : null;

    // Validate last_name
    $last_name = isset($_POST['last_name']) ? DbSecurity::validateInput($_POST['last_name'], 'string', ['maxlength' => 255]) : null;

    // Validate first_name
    $first_name = isset($_POST['first_name']) ? DbSecurity::validateInput($_POST['first_name'], 'string', ['maxlength' => 255]) : null;

    // Validate supplier_id
    $supplier_id = isset($_POST['supplier_id']) ? DbSecurity::validateInput($_POST['supplier_id'], 'int', ['min' => 0]) : null;

    // Validate sold_amount
    $sold_amount = isset($_POST['sold_amount']) ? DbSecurity::validateInput($_POST['sold_amount'], 'float', ['min' => 0]) : null;

    // Validate base_amount
    $base_amount = isset($_POST['base_amount']) ? DbSecurity::validateInput($_POST['base_amount'], 'float', ['min' => 0]) : null;


    // Validate booking_id
    $booking_id = isset($_POST['booking_id']) ? DbSecurity::validateInput($_POST['booking_id'], 'int', ['min' => 0]) : null;

    $booking_id = intval($_POST['booking_id']);

    try {
        // Get original values to calculate differences
        $originalQuery = "SELECT base_amount, sold_amount, supplier_id, sold_to, currency FROM hotel_bookings WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $stmtOriginal = $pdo->prepare($originalQuery);
        $stmtOriginal->execute([$booking_id, $tenant_id, $branch_id]);
        $originalData = $stmtOriginal->fetch(PDO::FETCH_ASSOC);

        if (!$originalData) {
            $response['message'] = 'Original booking data not found.';
            echo json_encode($response);
            exit;
        }

        // Calculate differences
        $priceDifference = $originalData['base_amount'] - floatval($_POST['base_amount']);
        $soldDifference = $originalData['sold_amount'] - floatval($_POST['sold_amount']);
        $originalCurrency = $originalData['currency'];
        $originalSupplier = $originalData['supplier_id'];
        $originalClient = $originalData['sold_to'];
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Handle supplier changes or price differences
        if ($_POST['supplier_id'] != $originalSupplier || $priceDifference != 0) {
            // Check if original supplier exists and is external
            if ($originalSupplier > 0) {
                $oldSupplierQuery = "SELECT * FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmtOldSupplier = $pdo->prepare($oldSupplierQuery);
                $stmtOldSupplier->execute([$originalSupplier, $tenant_id, $branch_id]);
                $oldSupplierData = $stmtOldSupplier->fetch(PDO::FETCH_ASSOC);
                
                $oldSupplierType = isset($oldSupplierData['supplier_type']) ? $oldSupplierData['supplier_type'] : '';
                if (!$oldSupplierType) {
                    $oldSupplierType = isset($oldSupplierData['type']) ? $oldSupplierData['type'] : '';
                }
                $oldSupplierIsExternal = (strtolower(trim($oldSupplierType)) === 'external');
                
                // If supplier changed and old supplier was external
                if ($_POST['supplier_id'] != $originalSupplier && $oldSupplierIsExternal) {
                    // Get all transactions for the old supplier related to this ticket
                    $getOldSupplierTransactionsQuery = "SELECT * FROM supplier_transactions
                                                        WHERE supplier_id = ?
                                                        AND reference_id = ?
                                                        AND transaction_of = 'hotel'
                                                        AND tenant_id = ?
                                                        AND branch_id = ?
                                                        ORDER BY transaction_date ASC";
                    $stmtGetOldSupplierTransactions = $pdo->prepare($getOldSupplierTransactionsQuery);
                    $stmtGetOldSupplierTransactions->execute([$originalSupplier, $booking_id, $tenant_id, $branch_id]);
                    $oldSupplierTransactions = $stmtGetOldSupplierTransactions->fetchAll(PDO::FETCH_ASSOC);

                    // Calculate total amount from old supplier transactions
                    $totalAmount = 0;
                    foreach ($oldSupplierTransactions as $transaction) {
                        $totalAmount += $transaction['amount'];
                    }

                    // Get the transaction we're transferring to get its date
                    $getTransferTransactionQuery = "SELECT transaction_date FROM supplier_transactions
                                                   WHERE supplier_id = ?
                                                   AND reference_id = ?
                                                   AND transaction_of = 'hotel'
                                                   AND tenant_id = ?
                                                   AND branch_id = ?
                                                   LIMIT 1";
                    $stmtGetTransferTransaction = $pdo->prepare($getTransferTransactionQuery);
                    $stmtGetTransferTransaction->execute([$originalSupplier, $booking_id, $tenant_id, $branch_id]);
                    $transferData = $stmtGetTransferTransaction->fetch(PDO::FETCH_ASSOC);
                    $transferDate = $transferData['transaction_date'] ?? null;

                    // Update old supplier balance - ADDING back the amount since we're removing the ticket
                     // Example: If balance was -5000 and removing amount 200, new balance becomes -4800 (supplier owes less)
                     $updateOldSupplierQuery = "UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                     $stmtUpdateOldSupplier = $pdo->prepare($updateOldSupplierQuery);
                     $stmtUpdateOldSupplier->execute([$totalAmount, $originalSupplier, $tenant_id, $branch_id]);

                     // Update subsequent transactions for old supplier - ADDING back the amount
                     // But only for transactions that occurred after this specific transaction
                     $updateOldSupplierSubsequentQuery = "UPDATE supplier_transactions
                                                          SET balance = balance + ?
                                                          WHERE supplier_id = ?
                                                          AND branch_id = ?
                                                          AND id > (
                                                              SELECT id
                                                              FROM supplier_transactions
                                                              WHERE supplier_id = ?
                                                              AND reference_id = ?
                                                              AND transaction_of = 'hotel'
                                                              AND tenant_id = ?
                                                              AND branch_id = ?
                                                              LIMIT 1
                                                          )
                                                          ORDER BY transaction_date ASC, id ASC";
                     $stmtUpdateOldSupplierSubsequent = $pdo->prepare($updateOldSupplierSubsequentQuery);
                     $stmtUpdateOldSupplierSubsequent->execute([$totalAmount, $originalSupplier, $branch_id, $originalSupplier, $booking_id, $tenant_id, $branch_id]);

                     // Check if new supplier is external
                     $supplierQuery = "SELECT * FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                     $stmtSupplier = $pdo->prepare($supplierQuery);
                     $stmtSupplier->execute([$_POST['supplier_id'], $tenant_id, $branch_id]);
                     $supplierData = $stmtSupplier->fetch(PDO::FETCH_ASSOC);

                    $supplierType = isset($supplierData['supplier_type']) ? $supplierData['supplier_type'] : '';
                    if (!$supplierType) {
                        $supplierType = isset($supplierData['type']) ? $supplierData['type'] : '';
                    }
                    $isExternal = (strtolower(trim($supplierType)) === 'external');

                    // Only update balances if new supplier is external
                     if ($isExternal) {
                         // Get current balance of new supplier
                         $getCurrentSupplierBalanceQuery = "SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                         $stmtGetCurrentSupplierBalance = $pdo->prepare($getCurrentSupplierBalanceQuery);
                         $stmtGetCurrentSupplierBalance->execute([$_POST['supplier_id'], $tenant_id, $branch_id]);
                         $balanceData = $stmtGetCurrentSupplierBalance->fetch(PDO::FETCH_ASSOC);
                         $currentSupplierBalance = $balanceData['balance'] ?? 0;

                         // Update new supplier balance - SUBTRACTING the amount since we're adding a ticket
                         // Example: If balance was -4800 and adding amount 200, new balance becomes -5000 (supplier owes more)
                         $newBalance = $currentSupplierBalance - $base_amount;
                         $updateSupplierQuery = "UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                         $stmtUpdateSupplier = $pdo->prepare($updateSupplierQuery);
                         $stmtUpdateSupplier->execute([$newBalance, $_POST['supplier_id'], $tenant_id, $branch_id]);

                         // Delete old supplier transactions and create new ones for the new supplier
                         $deleteOldTransactionsQuery = "DELETE FROM supplier_transactions
                                                       WHERE supplier_id = ?
                                                       AND reference_id = ?
                                                       AND transaction_of = 'hotel'
                                                       AND tenant_id = ?
                                                       AND branch_id = ?";
                         $stmtDeleteOldTransactions = $pdo->prepare($deleteOldTransactionsQuery);
                         $stmtDeleteOldTransactions->execute([$originalSupplier, $booking_id, $tenant_id, $branch_id]);

                         // Create new transaction record for the new supplier
                         $insertSupplierTransactionQuery = "INSERT INTO supplier_transactions (supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_of, transaction_date, tenant_id, branch_id)
                                                           VALUES (?, ?, 'debit', ?, ?, ?, 'hotel', NOW(), ?, ?)";
                         $stmtInsertSupplierTransaction = $pdo->prepare($insertSupplierTransactionQuery);
                         $description = "Purchase for hotel booking: {$_POST['first_name']} {$_POST['last_name']} (Check-in: {$_POST['check_in_date']}) (Transferred from supplier {$originalSupplier})";
                         $stmtInsertSupplierTransaction->execute([$_POST['supplier_id'], $booking_id, $base_amount, $newBalance, $description, $tenant_id, $branch_id]);
                        $stmtInsertSupplierTransaction->close();
                        }
   
                        // Delete old supplier transactions regardless of type
                        $deleteOldTransactionsQuery = "DELETE FROM supplier_transactions
                                                      WHERE supplier_id = ?
                                                      AND reference_id = ?
                                                      AND transaction_of = 'hotel'
                                                      AND tenant_id = ?
                                                      AND branch_id = ?";
                        $stmtDeleteOldTransactions = $conn->prepare($deleteOldTransactionsQuery);
                        $stmtDeleteOldTransactions->bind_param('iiii', $originalSupplier, $booking_id, $tenant_id, $branch_id);
                        $stmtDeleteOldTransactions->execute();
                        $stmtDeleteOldTransactions->close();
                    }
                // Handle case where supplier remains the same but price changes
                else if ($_POST['supplier_id'] == $originalSupplier && $priceDifference != 0 && $oldSupplierIsExternal) {
                    // Get current supplier balance
                    $getCurrentSupplierBalanceQuery = "SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                    $stmtGetCurrentSupplierBalance = $conn->prepare($getCurrentSupplierBalanceQuery);
                    $stmtGetCurrentSupplierBalance->bind_param('iii', $_POST['supplier_id'], $tenant_id, $branch_id);
                    $stmtGetCurrentSupplierBalance->execute();
                    $stmtGetCurrentSupplierBalance->bind_result($currentSupplierBalance);
                    $stmtGetCurrentSupplierBalance->fetch();
                    $stmtGetCurrentSupplierBalance->close();

                    // Update supplier balance based on price difference
                    // If priceDifference is positive: base decreased, add to balance (supplier gets money back)
                    // If priceDifference is negative: base increased, subtract from balance (supplier pays more)
                    $newBalance = $currentSupplierBalance + $priceDifference;
                    $updateSupplierQuery = "UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                    $stmtUpdateSupplier = $conn->prepare($updateSupplierQuery);
                    $stmtUpdateSupplier->bind_param('diii', $newBalance, $_POST['supplier_id'], $tenant_id, $branch_id);
                    $stmtUpdateSupplier->execute();
                    $stmtUpdateSupplier->close();

                    // Check if transaction record exists for this supplier
                    $checkSupplierTransactionQuery = "SELECT id, transaction_date, balance, amount FROM supplier_transactions
                                                      WHERE supplier_id = ?
                                                      AND reference_id = ?
                                                      AND transaction_of = 'hotel'
                                                      AND tenant_id = ?
                                                      AND branch_id = ?
                                                      LIMIT 1";
                    $stmtCheckSupplierTransaction = $conn->prepare($checkSupplierTransactionQuery);
                    $stmtCheckSupplierTransaction->bind_param('iiii', $_POST['supplier_id'], $booking_id, $tenant_id, $branch_id);
                    $stmtCheckSupplierTransaction->execute();
                    $supplierTransactionResult = $stmtCheckSupplierTransaction->get_result();

                    if ($supplierTransactionResult->num_rows > 0) {
                        $transactionRow = $supplierTransactionResult->fetch_assoc();
                        $transactionId = $transactionRow['id'];
                        $transactionDate = $transactionRow['transaction_date'];
                        $currentTransactionAmount = $transactionRow['amount'];

                        // Get the current transaction's date and balance
                        $getCurrentTransactionQuery = "SELECT transaction_date, balance FROM supplier_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ? LIMIT 1";
                        $stmtGetCurrentTransaction = $conn->prepare($getCurrentTransactionQuery);
                        $stmtGetCurrentTransaction->bind_param('iii', $transactionId, $tenant_id, $branch_id);
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
                                                          WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                        $stmtUpdateSupplierTransaction = $conn->prepare($updateSupplierTransactionQuery);
                        $stmtUpdateSupplierTransaction->bind_param('ddiii', $base_amount, $newTransactionBalance, $transactionId, $tenant_id, $branch_id);
                        $stmtUpdateSupplierTransaction->execute();
                        $stmtUpdateSupplierTransaction->close();

                        // Update all subsequent transactions' balances
                        $updateSubsequentQuery = "UPDATE supplier_transactions
                                                  SET balance = balance + ?
                                                  WHERE supplier_id = ?
                                                  AND branch_id = ?
                                                  AND id > ?
                                                  AND tenant_id = ?
                                                  ORDER BY transaction_date ASC";

                        $stmtUpdateSubsequent = $conn->prepare($updateSubsequentQuery);
                        $stmtUpdateSubsequent->bind_param('diiii', $priceDifference, $_POST['supplier_id'], $branch_id, $transactionId, $tenant_id);
                        $stmtUpdateSubsequent->execute();
                        $stmtUpdateSubsequent->close();
                    } else {
                        // For a new transaction record, the balance should equal the current supplier balance
                        // Create new transaction record
                        $insertSupplierTransactionQuery = "INSERT INTO supplier_transactions (supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_of, transaction_date, tenant_id, branch_id)
                                                          VALUES (?, ?, 'debit', ?, ?, ?, 'hotel', NOW(), ?, ?)";
                        $stmtInsertSupplierTransaction = $conn->prepare($insertSupplierTransactionQuery);
                        $description = "Purchase for hotel booking: {$_POST['first_name']} {$_POST['last_name']} (Check-in: {$_POST['check_in_date']})";
                        $stmtInsertSupplierTransaction->bind_param('iiddssii', $_POST['supplier_id'], $booking_id, $base_amount, $newBalance, $description, $tenant_id, $branch_id);
                        $stmtInsertSupplierTransaction->execute();
                        $stmtInsertSupplierTransaction->close();
                    }
                    $stmtCheckSupplierTransaction->close();
                }
            }
        }
        
        // Handle client changes or sold price differences
        if ($_POST['sold_to'] != $originalClient || $soldDifference != 0) {
            // Check if original client exists and is regular
            if ($originalClient > 0) {
                $oldClientQuery = "SELECT * FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmtOldClient = $pdo->prepare($oldClientQuery);
                $stmtOldClient->execute([$originalClient, $tenant_id, $branch_id]);
                $oldClientData = $stmtOldClient->fetch(PDO::FETCH_ASSOC);
                 
                 $oldClientType = isset($oldClientData['client_type']) ? $oldClientData['client_type'] : '';
                 if (!$oldClientType) {
                     $oldClientType = isset($oldClientData['type']) ? $oldClientData['type'] : '';
                 }
                 $oldClientIsRegular = (strtolower(trim($oldClientType)) === 'regular');
                 
                 // If client changed and old client was regular
                 if ($_POST['sold_to'] != $originalClient && $oldClientIsRegular) {
                     // Get all transactions for the old client related to this ticket
                     $getOldClientTransactionsQuery = "SELECT * FROM client_transactions
                                                      WHERE client_id = ?
                                                      AND reference_id = ?
                                                      AND transaction_of = 'hotel'
                                                      AND tenant_id = ?
                                                      AND branch_id = ?
                                                      ORDER BY created_at ASC";
                     $stmtGetOldClientTransactions = $pdo->prepare($getOldClientTransactionsQuery);
                     $stmtGetOldClientTransactions->execute([$originalClient, $booking_id, $tenant_id, $branch_id]);
                     $oldClientTransactions = $stmtGetOldClientTransactions->fetchAll(PDO::FETCH_ASSOC);

                    // Get the earliest transaction date for this ticket
                    $earliestTransactionDate = null;
                    if (!empty($oldClientTransactions)) {
                        $earliestTransactionDate = $oldClientTransactions[0]['created_at'];
                    }

                    // Get the transaction we're transferring to get its date
                    $getTransferTransactionQuery = "SELECT created_at FROM client_transactions
                                                   WHERE client_id = ?
                                                   AND reference_id = ?
                                                   AND transaction_of = 'hotel'
                                                   AND tenant_id = ?
                                                   AND branch_id = ?
                                                   LIMIT 1";
                    $stmtGetTransferTransaction = $pdo->prepare($getTransferTransactionQuery);
                     $stmtGetTransferTransaction->execute([$originalClient, $booking_id, $tenant_id, $branch_id]);
                     $transferData = $stmtGetTransferTransaction->fetch(PDO::FETCH_ASSOC);
                     $transferTransactionDate = $transferData['created_at'] ?? null;

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
                         $updateOldClientUsdQuery = "UPDATE clients SET usd_balance = usd_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                         $stmtUpdateOldClientUsd = $pdo->prepare($updateOldClientUsdQuery);
                         $stmtUpdateOldClientUsd->execute([$totalUsdAmount, $originalClient, $tenant_id, $branch_id]);

                         // Update subsequent USD transactions for old client
                         $updateOldClientUsdSubsequentQuery = "UPDATE client_transactions
                                                               SET balance = balance + ?
                                                               WHERE client_id = ?
                                                               AND branch_id = ?
                                                               AND id > (SELECT id FROM client_transactions
                                                                        WHERE client_id = ?
                                                                        AND reference_id = ?
                                                                        AND transaction_of = 'hotel'
                                                                        AND branch_id = ?
                                                                        LIMIT 1)
                                                               AND currency = 'USD'
                                                               AND tenant_id = ?
                                                               ORDER BY created_at ASC, id ASC";
                         $stmtUpdateOldClientUsdSubsequent = $pdo->prepare($updateOldClientUsdSubsequentQuery);
                         $stmtUpdateOldClientUsdSubsequent->execute([$totalUsdAmount, $originalClient, $branch_id, $originalClient, $booking_id, $branch_id, $tenant_id]);
                     }
                     
                     if ($totalAfsAmount > 0) {
                         // When removing a ticket from a client, we need to add the amount back
                         $updateOldClientAfsQuery = "UPDATE clients SET afs_balance = afs_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                         $stmtUpdateOldClientAfs = $pdo->prepare($updateOldClientAfsQuery);
                         $stmtUpdateOldClientAfs->execute([$totalAfsAmount, $originalClient, $tenant_id, $branch_id]);

                         // Update subsequent AFS transactions for old client
                         $updateOldClientAfsSubsequentQuery = "UPDATE client_transactions
                                                               SET balance = balance + ?
                                                               WHERE client_id = ?
                                                               AND branch_id = ?
                                                               AND id > (SELECT id FROM client_transactions
                                                                        WHERE client_id = ?
                                                                        AND reference_id = ?
                                                                        AND transaction_of = 'hotel'
                                                                        AND branch_id = ?
                                                                        LIMIT 1)
                                                               AND currency = 'AFS'
                                                              AND tenant_id = ?
                                                              ORDER BY created_at ASC, id ASC";
                        $stmtUpdateOldClientAfsSubsequent = $conn->prepare($updateOldClientAfsSubsequentQuery);
                        $stmtUpdateOldClientAfsSubsequent->bind_param('diiiiii', $totalAfsAmount, $originalClient, $branch_id, $originalClient, $booking_id, $branch_id, $tenant_id);
                        $stmtUpdateOldClientAfsSubsequent->execute();
                        $stmtUpdateOldClientAfsSubsequent->close();
                    }

                    // Check if new client is regular
                     $newClientQuery = "SELECT * FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                     $stmtNewClient = $pdo->prepare($newClientQuery);
                     $stmtNewClient->execute([$_POST['sold_to'], $tenant_id, $branch_id]);
                     $newClientData = $stmtNewClient->fetch(PDO::FETCH_ASSOC);

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
                         
                         $getNewClientUsdBalanceQuery = "SELECT usd_balance FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                         $stmtGetNewClientUsdBalance = $pdo->prepare($getNewClientUsdBalanceQuery);
                         $stmtGetNewClientUsdBalance->execute([$_POST['sold_to'], $tenant_id, $branch_id]);
                         $usdData = $stmtGetNewClientUsdBalance->fetch(PDO::FETCH_ASSOC);
                         $newClientUsdBalance = $usdData['usd_balance'] ?? 0;

                         $getNewClientAfsBalanceQuery = "SELECT afs_balance FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                         $stmtGetNewClientAfsBalance = $pdo->prepare($getNewClientAfsBalanceQuery);
                         $stmtGetNewClientAfsBalance->execute([$_POST['sold_to'], $tenant_id, $branch_id]);
                         $afsData = $stmtGetNewClientAfsBalance->fetch(PDO::FETCH_ASSOC);
                         $newClientAfsBalance = $afsData['afs_balance'] ?? 0;

                        // Update new client balances
                        if ($totalUsdAmount > 0) {
                            // When adding a ticket to a client with balance:
                            // Example: If balance is -4930.600 and adding amount 215
                            // We want final balance to be -5145.600 (client owes more)
                            // Simply add the negative of the amount to make balance more negative
                            $negativeAmount = abs($totalUsdAmount);
                            $ClientUsdBalance = $newClientUsdBalance - $negativeAmount;
                            $updateNewClientUsdQuery = "UPDATE clients SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                            $stmtUpdateNewClientUsd = $pdo->prepare($updateNewClientUsdQuery);
                            $stmtUpdateNewClientUsd->execute([$ClientUsdBalance, $_POST['sold_to'], $tenant_id, $branch_id]);

                            // Update subsequent USD transactions for new client
                             if ($earliestTransactionDate) {
                                 $updateNewClientUsdSubsequentQuery = "UPDATE client_transactions
                                                                     SET balance = balance - ?
                                                                     WHERE client_id = ?
                                                                     AND branch_id = ?
                                                                     AND id > (SELECT id FROM client_transactions
                                                                               WHERE client_id = ?
                                                                               AND reference_id = ?
                                                                               AND transaction_of = 'hotel'
                                                                               AND branch_id = ?
                                                                               LIMIT 1)
                                                                     AND currency = 'USD'
                                                                     AND tenant_id = ?
                                                                     ORDER BY created_at ASC, id ASC";
                                 $stmtUpdateNewClientUsdSubsequent = $pdo->prepare($updateNewClientUsdSubsequentQuery);
                                 $stmtUpdateNewClientUsdSubsequent->execute([$negativeAmount, $_POST['sold_to'], $branch_id, $_POST['sold_to'], $booking_id, $branch_id, $tenant_id]);
                             }
                            }
                            
                            if ($totalAfsAmount > 0) {
                             // When adding a ticket to a client with negative balance, we need to subtract the amount
                             $updateNewClientAfsQuery = "UPDATE clients SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                             $stmtUpdateNewClientAfs = $pdo->prepare($updateNewClientAfsQuery);
                             $stmtUpdateNewClientAfs->execute([$totalAfsAmount, $_POST['sold_to'], $tenant_id, $branch_id]);

                             // Update subsequent AFS transactions for new client
                             if ($earliestTransactionDate) {
                                 $updateNewClientAfsSubsequentQuery = "UPDATE client_transactions
                                                                     SET balance = balance - ?
                                                                     WHERE client_id = ?
                                                                     AND branch_id = ?
                                                                     AND id > (SELECT id FROM client_transactions
                                                                               WHERE client_id = ?
                                                                               AND reference_id = ?
                                                                               AND transaction_of = 'hotel'
                                                                               AND branch_id = ?
                                                                               LIMIT 1)
                                                                     AND currency = 'AFS'
                                                                     AND tenant_id = ?
                                                                     ORDER BY created_at ASC, id ASC";
                                 $stmtUpdateNewClientAfsSubsequent = $pdo->prepare($updateNewClientAfsSubsequentQuery);
                                 $stmtUpdateNewClientAfsSubsequent->execute([$totalAfsAmount, $_POST['sold_to'], $branch_id, $_POST['sold_to'], $booking_id, $branch_id, $tenant_id]);
                             }
                            }
                    }

                    // Delete old client transactions and create new ones for the new client
                     $deleteOldClientTransactionsQuery = "DELETE FROM client_transactions
                                                         WHERE client_id = ?
                                                         AND reference_id = ?
                                                         AND transaction_of = 'hotel'
                                                         AND tenant_id = ?
                                                         AND branch_id = ?";
                     $stmtDeleteOldClientTransactions = $pdo->prepare($deleteOldClientTransactionsQuery);
                     $stmtDeleteOldClientTransactions->execute([$originalClient, $booking_id, $tenant_id, $branch_id]);

                    // Insert new transactions for the new client
                     // Since we have USD and AFS amounts, we need to insert separate transactions
                     if ($totalUsdAmount > 0) {
                         $insertClientTransactionUsdQuery = "INSERT INTO client_transactions (client_id, reference_id, type, amount, currency, balance, description, transaction_of, tenant_id, branch_id) VALUES (?, ?, 'debit', ?, 'USD', ?, ?, 'hotel', ?, ?)";
                         $stmtInsertClientTransactionUsd = $pdo->prepare($insertClientTransactionUsdQuery);
                         $descriptionUsd = "Sale for hotel booking: {$_POST['first_name']} {$_POST['last_name']} (Check-in: {$_POST['check_in_date']}) (Transferred from client {$originalClient})";
                         // Need to get the current balance for USD
                         $getUsdBalanceQuery = "SELECT usd_balance FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                         $stmtGetUsdBalance = $pdo->prepare($getUsdBalanceQuery);
                         $stmtGetUsdBalance->execute([$_POST['sold_to'], $tenant_id, $branch_id]);
                         $usdBalData = $stmtGetUsdBalance->fetch(PDO::FETCH_ASSOC);
                         $currentUsdBalance = $usdBalData['usd_balance'] ?? 0;
                         $stmtInsertClientTransactionUsd->execute([$_POST['sold_to'], $booking_id, $totalUsdAmount, $currentUsdBalance, $descriptionUsd, $tenant_id, $branch_id]);
                     }

                     if ($totalAfsAmount > 0) {
                         $insertClientTransactionAfsQuery = "INSERT INTO client_transactions (client_id, reference_id, type, amount, currency, balance, description, transaction_of, tenant_id, branch_id) VALUES (?, ?, 'debit', ?, 'AFS', ?, ?, 'hotel', ?, ?)";
                         $stmtInsertClientTransactionAfs = $pdo->prepare($insertClientTransactionAfsQuery);
                         $descriptionAfs = "Sale for hotel booking: {$_POST['first_name']} {$_POST['last_name']} (Check-in: {$_POST['check_in_date']}) (Transferred from client {$originalClient})";
                         // Need to get the current balance for AFS
                         $getAfsBalanceQuery = "SELECT afs_balance FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                         $stmtGetAfsBalance = $pdo->prepare($getAfsBalanceQuery);
                         $stmtGetAfsBalance->execute([$_POST['sold_to'], $tenant_id, $branch_id]);
                         $afsBalData = $stmtGetAfsBalance->fetch(PDO::FETCH_ASSOC);
                         $currentAfsBalance = $afsBalData['afs_balance'] ?? 0;
                         $stmtInsertClientTransactionAfs->execute([$_POST['sold_to'], $booking_id, $totalAfsAmount, $currentAfsBalance, $descriptionAfs, $tenant_id, $branch_id]);
                     }
                }
            }
            
            // Check if new client exists and is regular
             if ($_POST['sold_to'] > 0) {
                 $clientQuery = "SELECT * FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                 $stmtClient = $pdo->prepare($clientQuery);
                 $stmtClient->execute([$_POST['sold_to'], $tenant_id, $branch_id]);
                 $clientData = $stmtClient->fetch(PDO::FETCH_ASSOC);
                 
                 $clientType = isset($clientData['client_type']) ? $clientData['client_type'] : '';
                 if (!$clientType) {
                     $clientType = isset($clientData['type']) ? $clientData['type'] : '';
                 }
                 $isRegular = (strtolower(trim($clientType)) === 'regular');
                 
                 if ($isRegular) {
                     // If client changed
                     if ($_POST['sold_to'] != $originalClient) {
                         // Update new client balance - SUBTRACTING the new sold amount
                         // This DECREASES the balance (client owes more)
                         $balanceField = strtolower($_POST['currency']) === 'usd' ? 'usd_balance' : 'afs_balance';
                         $updateClientQuery = "UPDATE clients SET $balanceField = $balanceField - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                         $stmtUpdateClient = $pdo->prepare($updateClientQuery);
                         $stmtUpdateClient->execute([$sold_amount, $_POST['sold_to'], $tenant_id, $branch_id]);

                         // Get current client balance for the transaction record
                         $getCurrentBalanceQuery = "SELECT $balanceField FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                         $stmtGetCurrentBalance = $pdo->prepare($getCurrentBalanceQuery);
                         $stmtGetCurrentBalance->execute([$_POST['sold_to'], $tenant_id, $branch_id]);
                         $balData = $stmtGetCurrentBalance->fetch(PDO::FETCH_ASSOC);
                         $currentBalance = $balData[$balanceField] ?? 0;

                         // Create new transaction record for new client
                         $insertClientTransactionQuery = "INSERT INTO client_transactions (client_id, reference_id, type, amount, currency, balance, description, transaction_of, tenant_id, branch_id) VALUES (?, ?, 'debit', ?, ?, ?, ?, 'hotel', ?, ?)";
                         $stmtInsertClientTransaction = $pdo->prepare($insertClientTransactionQuery);
                         $description = "Sale for hotel booking: {$_POST['first_name']} {$_POST['last_name']} (Check-in: {$_POST['check_in_date']})";
                         $stmtInsertClientTransaction->execute([$_POST['sold_to'], $booking_id, $sold_amount, $_POST['currency'], $currentBalance, $description, $tenant_id, $branch_id]);
                    }
                    // Same client but sold price changed
                     else if ($soldDifference != 0) {
                         $balanceField = strtolower($_POST['currency']) === 'usd' ? 'usd_balance' : 'afs_balance';

                         // Get current client balance before update
                         $getCurrentBalanceQuery = "SELECT $balanceField FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                         $stmtGetCurrentBalance = $pdo->prepare($getCurrentBalanceQuery);
                         $stmtGetCurrentBalance->execute([$_POST['sold_to'], $tenant_id, $branch_id]);
                         $balData = $stmtGetCurrentBalance->fetch(PDO::FETCH_ASSOC);
                         $currentBalance = $balData[$balanceField] ?? 0;

                        // Calculate new balance
                        $newBalance = 0;
                        if ($soldDifference > 0) {
                            // Sold price decreased, client owes less, balance increases
                            $updateClientQuery = "UPDATE clients SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                            $newBalance = $currentBalance + $soldDifference;
                        } else {
                            // Sold price increased, client owes more, balance decreases
                            $updateClientQuery = "UPDATE clients SET $balanceField = $balanceField - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                            // Make the difference positive for the query
                            $soldDifference = abs($soldDifference);
                            $newBalance = $currentBalance - $soldDifference;
                        }

                        $stmtUpdateClient = $pdo->prepare($updateClientQuery);
                        $stmtUpdateClient->execute([$soldDifference, $_POST['sold_to'], $tenant_id, $branch_id]);

                        // Check if transaction record exists for this client
                        $checkClientTransactionQuery = "SELECT id, created_at, balance, amount FROM client_transactions WHERE client_id = ? AND reference_id = ? AND transaction_of = 'hotel' AND tenant_id = ? AND branch_id = ? LIMIT 1";
                        $stmtCheckClientTransaction = $pdo->prepare($checkClientTransactionQuery);
                        $stmtCheckClientTransaction->execute([$_POST['sold_to'], $booking_id, $tenant_id, $branch_id]);
                        $clientTransactionResult = $stmtCheckClientTransaction->fetchAll(PDO::FETCH_ASSOC);

                        if (!empty($clientTransactionResult)) {
                            $transactionRow = $clientTransactionResult[0];
                            $transactionId = $transactionRow['id'];
                            $transactionDate = $transactionRow['created_at'];
                            $currentTransactionBalance = $transactionRow['balance'];
                            $currentTransactionAmount = $transactionRow['amount'];

                            // Calculate the difference between the new sold amount and the current transaction amount
                            $amountDifference = $sold_amount - $currentTransactionAmount;

                            // For client transactions, subsequent balances should:
                            // - Increase (add) when amount decreases
                            // - Decrease (subtract) when amount increases
                            $balanceAdjustment = -$amountDifference;

                            // Get the current transaction's date
                            $getCurrentTransactionQuery = "SELECT created_at FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ? LIMIT 1";
                            $stmtGetCurrentTransaction = $pdo->prepare($getCurrentTransactionQuery);
                            $stmtGetCurrentTransaction->execute([$transactionId, $tenant_id, $branch_id]);
                            $transData = $stmtGetCurrentTransaction->fetch(PDO::FETCH_ASSOC);
                            $currentTransactionDate = $transData['created_at'] ?? null;

                            // Update existing transaction record with adjusted balance
                            $updateClientTransactionQuery = "UPDATE client_transactions
                                                            SET amount = ?,
                                                                balance = balance + ?,
                                                                description = CONCAT('Updated: ', description)
                                                            WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                            $stmtUpdateClientTransaction = $pdo->prepare($updateClientTransactionQuery);
                            $stmtUpdateClientTransaction->execute([$sold_amount, $balanceAdjustment, $transactionId, $tenant_id, $branch_id]);

                            // Update all subsequent transactions' balances
                            $updateSubsequentQuery = "UPDATE client_transactions
                                                     SET balance = balance + ?
                                                     WHERE client_id = ?
                                                     AND branch_id = ?
                                                     AND currency = ?
                                                     AND id > ?
                                                     AND tenant_id = ?
                                                     ORDER BY created_at ASC";

                            $stmtUpdateSubsequent = $pdo->prepare($updateSubsequentQuery);
                            $stmtUpdateSubsequent->execute([$balanceAdjustment, $_POST['sold_to'], $branch_id, $_POST['currency'], $transactionId, $tenant_id]);
                        } else {
                            // Create new transaction record if one doesn't exist
                            $insertClientTransactionQuery = "INSERT INTO client_transactions (client_id, reference_id, type, amount, currency, balance, description, transaction_of, tenant_id, branch_id) VALUES (?, ?, 'debit', ?, ?, ?, ?, 'hotel', ?, ?)";
                            $stmtInsertClientTransaction = $pdo->prepare($insertClientTransactionQuery);
                            $description = "Sale for hotel booking: {$_POST['first_name']} {$_POST['last_name']} (Check-in: {$_POST['check_in_date']})";
                            $stmtInsertClientTransaction->execute([$_POST['sold_to'], $booking_id, $sold_amount, $_POST['currency'], $newBalance, $description, $tenant_id, $branch_id]);
                        }
                    }
                }
            }
        }

        // Prepare the update query for hotel booking
        $sql = "UPDATE hotel_bookings SET
            title = :title,
            first_name = :first_name,
            last_name = :last_name,
            gender = :gender,
            contact_no = :contact_no,
            check_in_date = :check_in_date,
            check_out_date = :check_out_date,
            accommodation_details = :accommodation_details,
            base_amount = :base_amount,
            sold_amount = :sold_amount,
            profit = :profit,
            currency = :currency,
            supplier_id = :supplier_id,
            sold_to = :sold_to,
            paid_to = :paid_to,
            remarks = :remarks,
            updated_at = NOW()
            WHERE id = :booking_id AND tenant_id = :tenant_id AND branch_id = :branch_id";

        $stmt = $pdo->prepare($sql);

        // Bind parameters
        $params = [
            ':booking_id' => $booking_id,
            ':title' => $_POST['title'],
            ':first_name' => $_POST['first_name'],
            ':last_name' => $_POST['last_name'],
            ':gender' => $_POST['gender'],
            ':contact_no' => $_POST['contact_no'],
            ':check_in_date' => $_POST['check_in_date'],
            ':check_out_date' => $_POST['check_out_date'],
            ':accommodation_details' => $_POST['accommodation_details'],
            ':base_amount' => floatval($_POST['base_amount']),
            ':sold_amount' => floatval($_POST['sold_amount']),
            ':profit' => floatval($_POST['profit']),
            ':currency' => $_POST['currency'],
            ':supplier_id' => $_POST['supplier_id'],
            ':sold_to' => $_POST['sold_to'],
            ':paid_to' => $_POST['paid_to'],
            ':remarks' => $_POST['remarks'],
            ':tenant_id' => $tenant_id,
            ':branch_id' => $branch_id
        ];

        // Execute the update
        $stmt->execute($params);

        if ($stmt->rowCount() > 0) {
            // Add activity logging
            $user_id = $_SESSION['user_id'] ?? 0;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // Prepare old values
            $old_values = [
                'booking_id' => $booking_id,
                'title' => $originalData['title'] ?? '',
                'first_name' => $originalData['first_name'] ?? '',
                'last_name' => $originalData['last_name'] ?? '',
                'base_amount' => $originalData['base_amount'] ?? 0,
                'sold_amount' => $originalData['sold_amount'] ?? 0,
                'currency' => $originalData['currency'] ?? '',
                'supplier_id' => $originalData['supplier_id'] ?? 0,
                'sold_to' => $originalData['sold_to'] ?? 0,

            ];
            
            // Prepare new values
            $new_values = [
                'title' => $_POST['title'],
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'gender' => $_POST['gender'],
                'contact_no' => $_POST['contact_no'],
                'check_in_date' => $_POST['check_in_date'],
                'check_out_date' => $_POST['check_out_date'],
                'accommodation_details' => $_POST['accommodation_details'],
                'base_amount' => floatval($_POST['base_amount']),
                'sold_amount' => floatval($_POST['sold_amount']),
                'profit' => floatval($_POST['profit']),
                'currency' => $_POST['currency'],
                'supplier_id' => $_POST['supplier_id'],
                'sold_to' => $_POST['sold_to'],
                'paid_to' => $_POST['paid_to'],
                'remarks' => $_POST['remarks'],
            ];
            
            // Insert activity log
            $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id, branch_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $activity_log_stmt->execute([
                $user_id,
                'update',
                'hotel_bookings',
                $booking_id,
                json_encode($old_values),
                json_encode($new_values),
                $ip_address,
                $user_agent,
                $tenant_id,
                $branch_id
            ]);
            
            // Commit transaction
            $pdo->commit();
            $response['success'] = true;
            $response['message'] = 'Booking updated successfully';
        } else {
            // Rollback transaction on error
            $pdo->rollBack();
            $response['message'] = 'No changes made or booking not found';
        }

    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error updating booking: " . $e->getMessage());
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);
?> 