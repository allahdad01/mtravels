<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
require_once('../includes/db.php');

$response = ['success' => false, 'message' => ''];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and validate booking ID
    if (!isset($_POST['booking_id']) || empty($_POST['booking_id'])) {
        $response['message'] = 'Booking ID is required';

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

// Validate exchange_rate
$exchange_rate = isset($_POST['exchangeRate']) ? DbSecurity::validateInput($_POST['exchangeRate'], 'float', ['min' => 0]) : null;

// Validate booking_id
$booking_id = isset($_POST['booking_id']) ? DbSecurity::validateInput($_POST['booking_id'], 'int', ['min' => 0]) : null;
        echo json_encode($response);
        exit;
    }

    $booking_id = intval($_POST['booking_id']);
    $exchange_rate = floatval($_POST['exchangeRate']);

    try {
        // Get original values to calculate differences
        $originalQuery = "SELECT base_amount, sold_amount, supplier_id, sold_to, currency FROM hotel_bookings WHERE id = ? AND tenant_id = ?";
        $stmtOriginal = $pdo->prepare($originalQuery);
        $stmtOriginal->execute([$booking_id, $tenant_id]);
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
                $oldSupplierQuery = "SELECT * FROM suppliers WHERE id = ? AND tenant_id = ?";
                $stmtOldSupplier = $pdo->prepare($oldSupplierQuery);
                $stmtOldSupplier->execute([$originalSupplier, $tenant_id]);
                $oldSupplierData = $stmtOldSupplier->fetch(PDO::FETCH_ASSOC);
                
                $oldSupplierType = isset($oldSupplierData['supplier_type']) ? $oldSupplierData['supplier_type'] : '';
                if (!$oldSupplierType) {
                    $oldSupplierType = isset($oldSupplierData['type']) ? $oldSupplierData['type'] : '';
                }
                $oldSupplierIsExternal = (strtolower(trim($oldSupplierType)) === 'external');
                
                // If supplier changed and old supplier was external
                if ($_POST['supplier_id'] != $originalSupplier && $oldSupplierIsExternal) {
                    // Update old supplier balance - ADDING the original base amount
                    // This INCREASES the balance (supplier gets money back)
                    $updateOldSupplierQuery = "UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ?";
                    $stmtUpdateOldSupplier = $pdo->prepare($updateOldSupplierQuery);
                    $stmtUpdateOldSupplier->execute([$originalData['base_amount'], $originalSupplier, $tenant_id]);

                    // Check if transaction record exists for old supplier
                    $checkOldSupplierTransactionQuery = "SELECT id FROM supplier_transactions WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'hotel' AND tenant_id = ? LIMIT 1";
                    $stmtCheckOldSupplierTransaction = $pdo->prepare($checkOldSupplierTransactionQuery);
                    $stmtCheckOldSupplierTransaction->execute([$originalSupplier, $booking_id, $tenant_id]);
                    $oldSupplierTransactionExists = $stmtCheckOldSupplierTransaction->rowCount() > 0;

                
                    
                    if ($oldSupplierTransactionExists) {
                        // Get transaction details before deleting
                        $getOldSupplierTransactionStmt = $pdo->prepare("
                            SELECT id, transaction_date, amount 
                            FROM supplier_transactions 
                            WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'hotel' AND tenant_id = ?
                            LIMIT 1
                        ");
                        $getOldSupplierTransactionStmt->execute([$originalSupplier, $booking_id, $tenant_id]);
                        $oldSupplierTransactionData = $getOldSupplierTransactionStmt->fetch(PDO::FETCH_ASSOC);

                        if ($oldSupplierTransactionData) {
                            // Update all subsequent transactions' balances
                            // Since we're removing a debit transaction, we need to increase subsequent balances
                            $updateSubsequentSupplierStmt = $pdo->prepare("
                                UPDATE supplier_transactions 
                                SET balance = balance + ? 
                                WHERE supplier_id = ? 
                                AND transaction_date > ? 
                                AND id != ?
                                AND tenant_id = ?
                            ");
                            $transactionAmount = abs($oldSupplierTransactionData['amount']); // Make sure it's positive
                            $updateSubsequentSupplierStmt->execute([
                                $transactionAmount,
                                $originalSupplier,
                                $oldSupplierTransactionData['transaction_date'],
                                $oldSupplierTransactionData['id'],
                                $tenant_id
                            ]);
                        }

                        // Update existing transaction record
                        $updateOldSupplierTransactionQuery = "DELETE FROM supplier_transactions WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'hotel' AND tenant_id = ?";
                        $stmtUpdateOldSupplierTransaction = $pdo->prepare($updateOldSupplierTransactionQuery);
                        $stmtUpdateOldSupplierTransaction->execute([$originalSupplier, $booking_id, $tenant_id]);
                    }
                }
            }
            
            // Check if new supplier exists and is external
            if ($_POST['supplier_id'] > 0) {
                $supplierQuery = "SELECT * FROM suppliers WHERE id = ? AND tenant_id = ?";
                $stmtSupplier = $pdo->prepare($supplierQuery);
                $stmtSupplier->execute([$_POST['supplier_id'], $tenant_id]);
                $supplierData = $stmtSupplier->fetch(PDO::FETCH_ASSOC);
                
                $supplierType = isset($supplierData['supplier_type']) ? $supplierData['supplier_type'] : '';
                if (!$supplierType) {
                    $supplierType = isset($supplierData['type']) ? $supplierData['type'] : '';
                }
                $isExternal = (strtolower(trim($supplierType)) === 'external');
                
                if ($isExternal) {
                    // If supplier changed
                    if ($_POST['supplier_id'] != $originalSupplier) {
                        // Update new supplier balance - SUBTRACTING the new base amount
                        // This DECREASES the balance (supplier pays more)
                        $updateSupplierQuery = "UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ?";
                        $stmtUpdateSupplier = $pdo->prepare($updateSupplierQuery);
                        $stmtUpdateSupplier->execute([floatval($_POST['base_amount']), $_POST['supplier_id'], $tenant_id]);
                        
                        // Get the updated balance after the update
                        $getNewSupplierBalanceStmt = $pdo->prepare("
                            SELECT balance as current_balance 
                            FROM suppliers 
                            WHERE id = ? AND tenant_id = ?
                        ");
                        $getNewSupplierBalanceStmt->execute([$_POST['supplier_id'], $tenant_id]);
                        $newSupplierBalance = $getNewSupplierBalanceStmt->fetchColumn();
                        
                        // Create new transaction record for new supplier
                        $insertSupplierTransactionQuery = "INSERT INTO supplier_transactions (supplier_id, reference_id, transaction_type, amount, remarks, balance, transaction_of, tenant_id) VALUES (?, ?, 'Debit', ?, ?, ?, 'hotel', ?)";
                        $stmtInsertSupplierTransaction = $pdo->prepare($insertSupplierTransactionQuery);
                        $description = "Purchase for hotel booking: {$_POST['first_name']} {$_POST['last_name']} (Check-in: {$_POST['check_in_date']})";
                        $stmtInsertSupplierTransaction->execute([
                            $_POST['supplier_id'], 
                            $booking_id, 
                            floatval($_POST['base_amount']), 
                            $description,
                            $newSupplierBalance,
                            $tenant_id
                        ]);
                    } 
                    // Same supplier but price changed
                    else if ($priceDifference != 0) {
                        // Get current supplier balance before update
                        $getCurrentSupplierBalanceQuery = "SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ?";
                        $stmtGetCurrentSupplierBalance = $pdo->prepare($getCurrentSupplierBalanceQuery);
                        $stmtGetCurrentSupplierBalance->execute([$_POST['supplier_id'], $tenant_id]);
                        $currentSupplierBalance = $stmtGetCurrentSupplierBalance->fetchColumn();
                        
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
                        
                        $stmtUpdateSupplier = $pdo->prepare($updateSupplierQuery);
                        $stmtUpdateSupplier->execute([$priceDifference, $_POST['supplier_id'], $tenant_id]);
                        
                        // Check if transaction record exists for this supplier
                        $checkSupplierTransactionQuery = "SELECT id, transaction_date, balance, amount FROM supplier_transactions WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'hotel' AND tenant_id = ? LIMIT 1";
                        $stmtCheckSupplierTransaction = $pdo->prepare($checkSupplierTransactionQuery);
                        $stmtCheckSupplierTransaction->execute([$_POST['supplier_id'], $booking_id, $tenant_id]);
                        
                        if ($stmtCheckSupplierTransaction->rowCount() > 0) {
                            $supplierTransactionRow = $stmtCheckSupplierTransaction->fetch(PDO::FETCH_ASSOC);
                            $supplierTransactionId = $supplierTransactionRow['id'];
                            $supplierTransactionDate = $supplierTransactionRow['transaction_date'];
                            $currentTransactionBalance = floatval($supplierTransactionRow['balance']);
                            $currentTransactionAmount = abs(floatval($supplierTransactionRow['amount'])); // Ensure positive value
                            
                            // Calculate the difference between the new base amount and the current transaction amount
                            $amountDifference = floatval($_POST['base_amount']) - $currentTransactionAmount;
                            
                            // Calculate the new balance for this transaction
                            // If base increased, balance should decrease by the difference
                            // If base decreased, balance should increase by the difference
                            $newTransactionBalance = $currentTransactionBalance - $amountDifference;
                            
                            // Update amount field to new base price
                            $updateSupplierAmountQuery = "UPDATE supplier_transactions SET amount = ? WHERE id = ? AND tenant_id = ?";
                            $stmtUpdateSupplierAmount = $pdo->prepare($updateSupplierAmountQuery);
                            $stmtUpdateSupplierAmount->execute([floatval($_POST['base_amount']), $supplierTransactionId, $tenant_id]);
                            
                            // Update existing transaction record with adjusted balance
                            $updateSupplierTransactionQuery = "UPDATE supplier_transactions SET balance = ?, remarks = CONCAT('Updated: ', remarks) WHERE id = ? AND tenant_id = ?";
                            $stmtUpdateSupplierTransaction = $pdo->prepare($updateSupplierTransactionQuery);
                            $stmtUpdateSupplierTransaction->execute([$newTransactionBalance, $supplierTransactionId, $tenant_id]);
                            
                            // Update all subsequent transactions' balances
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
                            
                            $stmtUpdateSubsequentSupplier = $pdo->prepare($updateSubsequentSupplierQuery);
                            $absAmountDifference = abs($amountDifference);
                            $stmtUpdateSubsequentSupplier->execute([$absAmountDifference, $_POST['supplier_id'], $supplierTransactionDate, $supplierTransactionId, $tenant_id]);
                        }
                    }
                }
            }
        }
        
        // Handle client changes or price differences
        if ($_POST['sold_to'] != $originalClient || $soldDifference != 0) {
            // Check if original client exists and is regular
            if ($originalClient > 0) {
                $oldClientQuery = "SELECT * FROM clients WHERE id = ? AND tenant_id = ?";
                $stmtOldClient = $pdo->prepare($oldClientQuery);
                $stmtOldClient->execute([$originalClient, $tenant_id]);
                $oldClientData = $stmtOldClient->fetch(PDO::FETCH_ASSOC);
                
                $oldClientType = isset($oldClientData['client_type']) ? $oldClientData['client_type'] : '';
                if (!$oldClientType) {
                    $oldClientType = isset($oldClientData['type']) ? $oldClientData['type'] : '';
                }
                $oldClientIsRegular = (strtolower(trim($oldClientType)) === 'regular');
                
                // If client changed and old client was regular
                if ($_POST['sold_to'] != $originalClient && $oldClientIsRegular) {
                    // Update old client balance - SUBTRACTING the original sold amount
                    $balanceField = strtolower($originalCurrency) === 'usd' ? 'usd_balance' : 'afs_balance';
                    $updateOldClientQuery = "UPDATE clients SET $balanceField = $balanceField - ? WHERE id = ?";
                    $stmtUpdateOldClient = $pdo->prepare($updateOldClientQuery);
                    $stmtUpdateOldClient->execute([$originalData['sold_amount'], $originalClient]);
                    
                    // Check if transaction record exists for old client
                    $checkOldClientTransactionQuery = "SELECT id FROM client_transactions WHERE client_id = ? AND reference_id = ? AND transaction_of = 'hotel' AND tenant_id = ? LIMIT 1";
                    $stmtCheckOldClientTransaction = $pdo->prepare($checkOldClientTransactionQuery);
                    $stmtCheckOldClientTransaction->execute([$originalClient, $booking_id, $tenant_id]);
                    $oldClientTransactionExists = $stmtCheckOldClientTransaction->rowCount() > 0;
                    
                    if ($oldClientTransactionExists) {
                        // Get transaction details before deleting
                        $getOldClientTransactionStmt = $pdo->prepare("
                            SELECT id, created_at, amount, currency 
                            FROM client_transactions 
                            WHERE client_id = ? AND reference_id = ? AND transaction_of = 'hotel' AND tenant_id = ?
                            LIMIT 1
                        ");
                        $getOldClientTransactionStmt->execute([$originalClient, $booking_id, $tenant_id]);
                        $oldTransactionData = $getOldClientTransactionStmt->fetch(PDO::FETCH_ASSOC);

                        if ($oldTransactionData) {
                            // Update all subsequent transactions' balances
                            // Since we're removing a debit transaction, we need to increase subsequent balances
                            $updateSubsequentStmt = $pdo->prepare("
                                UPDATE client_transactions 
                                SET balance = balance + ? 
                                WHERE client_id = ? 
                                AND created_at > ? 
                                AND currency = ? 
                                AND id != ?
                                AND tenant_id = ?
                            ");
                            $transactionAmount = abs($oldTransactionData['amount']); // Make sure it's positive
                            $updateSubsequentStmt->execute([
                                $transactionAmount,
                                $originalClient,
                                $oldTransactionData['created_at'],
                                $oldTransactionData['currency'],
                                $oldTransactionData['id'],
                                $tenant_id
                            ]);
                        }

                        // Update existing transaction record
                        $updateOldClientTransactionQuery = "DELETE FROM client_transactions WHERE client_id = ? AND reference_id = ? AND transaction_of = 'hotel' AND tenant_id = ?";
                        $stmtUpdateOldClientTransaction = $pdo->prepare($updateOldClientTransactionQuery);
                        $stmtUpdateOldClientTransaction->execute([$originalClient, $booking_id, $tenant_id]);
                    }
                }
            }
            
            // Check if new client exists and is regular
            if ($_POST['sold_to'] > 0) {
                $clientQuery = "SELECT * FROM clients WHERE id = ? AND tenant_id = ?";
                $stmtClient = $pdo->prepare($clientQuery);
                $stmtClient->execute([$_POST['sold_to'], $tenant_id]);
                $clientData = $stmtClient->fetch(PDO::FETCH_ASSOC);
                
                $clientType = isset($clientData['client_type']) ? $clientData['client_type'] : '';
                if (!$clientType) {
                    $clientType = isset($clientData['type']) ? $clientData['type'] : '';
                }
                $isRegular = (strtolower(trim($clientType)) === 'regular');
                
                if ($isRegular) {
                    // If client changed
                    if ($_POST['sold_to'] != $originalClient) {
                        // Update new client balance - ADDING the new sold amount
                        $balanceField = strtolower($_POST['currency']) === 'usd' ? 'usd_balance' : 'afs_balance';
                        $updateClientQuery = "UPDATE clients SET $balanceField = $balanceField - ? WHERE id = ? AND tenant_id = ?";
                        $stmtUpdateClient = $pdo->prepare($updateClientQuery);
                        $stmtUpdateClient->execute([floatval($_POST['sold_amount']), $_POST['sold_to'], $tenant_id]);

                        // Get the updated balance after the update
                        $getNewBalanceStmt = $pdo->prepare("
                            SELECT $balanceField as current_balance 
                            FROM clients 
                            WHERE id = ? AND tenant_id = ?
                        ");
                        $getNewBalanceStmt->execute([$_POST['sold_to'], $tenant_id]);
                        $newBalance = $getNewBalanceStmt->fetchColumn();
                        
                        // Create new transaction record for new client
                        $insertNewClientTransactionStmt = $pdo->prepare("
                            INSERT INTO client_transactions (client_id, reference_id, type, amount, currency, description, balance, transaction_of, tenant_id)
                            VALUES (?, ?, 'debit', ?, ?, ?, ?, 'hotel', ?)
                        ");
                        $insertNewClientTransactionStmt->execute([
                            $_POST['sold_to'], 
                            $booking_id, 
                            floatval($_POST['sold_amount']), 
                            $_POST['currency'],
                            "Hotel booking: " . $_POST['first_name'] . " " . $_POST['last_name'],
                            $newBalance,
                            $tenant_id
                        ]);
                    } 
                    // Same client but sold price changed
                    else if ($soldDifference != 0) {
                        $balanceField = strtolower($_POST['currency']) === 'usd' ? 'usd_balance' : 'afs_balance';
                        
                        // Get current client balance before update
                        $getCurrentBalanceQuery = "SELECT $balanceField FROM clients WHERE id = ? AND tenant_id = ?";
                        $stmtGetCurrentBalance = $pdo->prepare($getCurrentBalanceQuery);
                        $stmtGetCurrentBalance->execute([$_POST['sold_to'], $tenant_id]);
                        $currentBalance = $stmtGetCurrentBalance->fetchColumn();
                        
                        // Calculate new balance
                        if ($soldDifference > 0) {
                            // Sold price decreased (original price was higher)
                            // Client owes less, so balance should increase (become less negative if negative)
                            $updateClientQuery = "UPDATE clients SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ?";
                            $newBalance = $currentBalance + $soldDifference;
                        } else {
                            // Sold price increased (original price was lower)
                            // Client owes more, so balance should decrease (become more negative if negative)
                            $updateClientQuery = "UPDATE clients SET $balanceField = $balanceField - ? WHERE id = ? AND tenant_id = ?";
                            // Make the difference positive for the query
                            $soldDifference = abs($soldDifference);
                            $newBalance = $currentBalance - $soldDifference;
                        }
                        
                        $stmtUpdateClient = $pdo->prepare($updateClientQuery);
                        $stmtUpdateClient->execute([$soldDifference, $_POST['sold_to'], $tenant_id]);
                        
                        // Check if transaction record exists for this client
                        $checkClientTransactionQuery = "SELECT id, created_at, balance, amount FROM client_transactions WHERE client_id = ? AND reference_id = ? AND transaction_of = 'hotel' AND tenant_id = ? LIMIT 1";
                        $stmtCheckClientTransaction = $pdo->prepare($checkClientTransactionQuery);
                        $stmtCheckClientTransaction->execute([$_POST['sold_to'], $booking_id, $tenant_id]);
                        
                        if ($stmtCheckClientTransaction->rowCount() > 0) {
                            $transactionRow = $stmtCheckClientTransaction->fetch(PDO::FETCH_ASSOC);
                            $transactionId = $transactionRow['id'];
                            $transactionDate = $transactionRow['created_at'];
                            $currentTransactionBalance = floatval($transactionRow['balance']);
                            $currentTransactionAmount = abs(floatval($transactionRow['amount'])); // Ensure positive value
                            
                            // Calculate the difference between the new sold amount and the current transaction amount
                            $amountDifference = floatval($_POST['sold_amount']) - $currentTransactionAmount;
                            
                            // Calculate the new balance for this transaction
                            // If amount increased, balance should decrease by the difference
                            // If amount decreased, balance should increase by the difference
                            $newTransactionBalance = $currentTransactionBalance - $amountDifference;
                            
                            // Update amount field to new sold price (as negative value for debit)
                            $negativeAmount = -1 * abs(floatval($_POST['sold_amount']));
                            $updateClientAmountQuery = "UPDATE client_transactions SET amount = ? WHERE id = ? AND tenant_id = ?";
                            $stmtUpdateClientAmount = $pdo->prepare($updateClientAmountQuery);
                            $stmtUpdateClientAmount->execute([$negativeAmount, $transactionId, $tenant_id]);
                            
                            // Update existing transaction record with adjusted balance
                            $updateClientTransactionQuery = "UPDATE client_transactions SET balance = ?, description = CONCAT('Updated: ', description) WHERE id = ? AND tenant_id = ?";
                            $stmtUpdateClientTransaction = $pdo->prepare($updateClientTransactionQuery);
                            $stmtUpdateClientTransaction->execute([$newTransactionBalance, $transactionId, $tenant_id]);
                            
                            // Update all subsequent transactions' balances
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
                            
                            $stmtUpdateSubsequent = $pdo->prepare($updateSubsequentQuery);
                            $absAmountDifference = abs($amountDifference);
                            $stmtUpdateSubsequent->execute([$absAmountDifference, $_POST['sold_to'], $transactionDate, $_POST['currency'], $transactionId, $tenant_id]);
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
            exchange_rate = :exchange_rate,
            updated_at = NOW()
            WHERE id = :booking_id AND tenant_id = :tenant_id";

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
            ':exchange_rate' => $_POST['exchangeRate'],
            ':tenant_id' => $tenant_id
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
                'exchange_rate' => $_POST['exchangeRate']
            ];
            
            // Insert activity log
            $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log 
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $activity_log_stmt->execute([
                $user_id,
                'update',
                'hotel_bookings',
                $booking_id,
                json_encode($old_values),
                json_encode($new_values),
                $ip_address,
                $user_agent,
                $tenant_id
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