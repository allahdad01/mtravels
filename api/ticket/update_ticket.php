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
// Database connection
require_once '../../includes/db.php';

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

// Validate departureTime
$departureTime = isset($_POST['departureTime']) ? DbSecurity::validateInput($_POST['departureTime'], 'string', ['maxlength' => 255]) : null;

// Validate returnDepartureTime
$returnDepartureTime = isset($_POST['returnDepartureTime']) ? DbSecurity::validateInput($_POST['returnDepartureTime'], 'string', ['maxlength' => 255]) : null;

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
    $departure_time = isset($_POST['departureTime']) ? htmlspecialchars($_POST['departureTime']) : '';
    $return_date = isset($_POST['returnDate']) ? $_POST['returnDate'] : null;
    $return_departure_time = isset($_POST['returnDepartureTime']) ? htmlspecialchars($_POST['returnDepartureTime']) : '';
    $base = isset($_POST['base']) ? floatval($_POST['base']) : 0.0;
    $sold = isset($_POST['sold']) ? floatval($_POST['sold']) : 0.0;
    $profit = isset($_POST['pro']) ? floatval($_POST['pro']) : 0.0;
    $currency = isset($_POST['curr']) ? htmlspecialchars($_POST['curr']) : 'USD';
    $description = isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '';
    $paid_to = isset($_POST['paidTo']) ? intval($_POST['paidTo']) : null;
    $exchangeRate = isset($_POST['editExchangeRate']) ? floatval($_POST['editExchangeRate']) : 1.0;
    $marketExchangeRate = isset($_POST['marketExchangeRate']) ? floatval($_POST['marketExchangeRate']) : 1.0;

    // Get original values to calculate differences
    $originalQuery = "SELECT price, sold, supplier, sold_to, currency FROM ticket_bookings WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmtOriginal = $pdo->prepare($originalQuery);
    $stmtOriginal->bindParam(1, $id, PDO::PARAM_INT);
    $stmtOriginal->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmtOriginal->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmtOriginal->execute();
    $originalData = $stmtOriginal->fetch(PDO::FETCH_ASSOC);

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
    $pdo->beginTransaction();

    try {
        // Handle supplier changes or price differences
        if ($supplier != $originalSupplier || $priceDifference != 0) {
            // Check if original supplier exists and is external
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
                
// If supplier changed
if ($supplier != $originalSupplier) {

    // If old supplier was external, adjust balances
    if ($oldSupplierIsExternal) {
        // Get all transactions for the old supplier related to this ticket
        $getOldSupplierTransactionsQuery = "SELECT * FROM supplier_transactions
                                            WHERE supplier_id = ?
                                            AND reference_id = ?
                                            AND transaction_of = 'ticket_sale'
                                            AND tenant_id = ?
                                            AND branch_id = ?
                                            ORDER BY transaction_date ASC";
        $stmtGetOldSupplierTransactions = $pdo->prepare($getOldSupplierTransactionsQuery);
        $stmtGetOldSupplierTransactions->bindParam(1, $originalSupplier, PDO::PARAM_INT);
        $stmtGetOldSupplierTransactions->bindParam(2, $id, PDO::PARAM_INT);
        $stmtGetOldSupplierTransactions->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmtGetOldSupplierTransactions->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmtGetOldSupplierTransactions->execute();
        $oldSupplierTransactions = $stmtGetOldSupplierTransactions->fetchAll(PDO::FETCH_ASSOC);

        // Calculate total amount from old supplier transactions
        $totalAmount = 0;
        foreach ($oldSupplierTransactions as $transaction) {
            $totalAmount += $transaction['amount'];
        }

        // Update old supplier balance (adding back amount)
        $updateOldSupplierQuery = "UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $stmtUpdateOldSupplier = $pdo->prepare($updateOldSupplierQuery);
        $stmtUpdateOldSupplier->bindParam(1, $totalAmount, PDO::PARAM_STR);
        $stmtUpdateOldSupplier->bindParam(2, $originalSupplier, PDO::PARAM_INT);
        $stmtUpdateOldSupplier->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmtUpdateOldSupplier->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmtUpdateOldSupplier->execute();

        // Update subsequent transactions for old supplier (adding back amount)
        $updateOldSupplierSubsequentQuery = "UPDATE supplier_transactions
                                              SET balance = balance + ?
                                              WHERE supplier_id = ?
                                              AND branch_id = ?
                                              AND id > (
                                                  SELECT id FROM supplier_transactions
                                                  WHERE supplier_id = ?
                                                  AND reference_id = ?
                                                  AND transaction_of = 'ticket_sale'
                                                  AND tenant_id = ?
                                                  AND branch_id = ?
                                                  LIMIT 1
                                              )";
        $stmtUpdateOldSupplierSubsequent = $pdo->prepare($updateOldSupplierSubsequentQuery);
        $stmtUpdateOldSupplierSubsequent->bindParam(1, $totalAmount, PDO::PARAM_STR);
        $stmtUpdateOldSupplierSubsequent->bindParam(2, $originalSupplier, PDO::PARAM_INT);
        $stmtUpdateOldSupplierSubsequent->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmtUpdateOldSupplierSubsequent->bindParam(4, $originalSupplier, PDO::PARAM_INT);
        $stmtUpdateOldSupplierSubsequent->bindParam(5, $id, PDO::PARAM_INT);
        $stmtUpdateOldSupplierSubsequent->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmtUpdateOldSupplierSubsequent->bindParam(7, $branch_id, PDO::PARAM_INT);
        $stmtUpdateOldSupplierSubsequent->execute();
    }

    // Check if new supplier is external
    $supplierQuery = "SELECT * FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmtSupplier = $pdo->prepare($supplierQuery);
    $stmtSupplier->bindParam(1, $supplier, PDO::PARAM_INT);
    $stmtSupplier->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmtSupplier->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmtSupplier->execute();
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
        $stmtGetCurrentSupplierBalance->bindParam(1, $supplier, PDO::PARAM_INT);
        $stmtGetCurrentSupplierBalance->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmtGetCurrentSupplierBalance->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmtGetCurrentSupplierBalance->execute();
        $currentSupplierBalance = $stmtGetCurrentSupplierBalance->fetch(PDO::FETCH_ASSOC)['balance'];

        // Update new supplier balance
        $newBalance = $currentSupplierBalance - $base;
        $updateSupplierQuery = "UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $stmtUpdateSupplier = $pdo->prepare($updateSupplierQuery);
        $stmtUpdateSupplier->bindParam(1, $newBalance, PDO::PARAM_STR);
        $stmtUpdateSupplier->bindParam(2, $supplier, PDO::PARAM_INT);
        $stmtUpdateSupplier->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmtUpdateSupplier->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmtUpdateSupplier->execute();

        // Insert new supplier transaction for external supplier
        $insertSupplierTransactionQuery = "INSERT INTO supplier_transactions (supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_of, transaction_date, tenant_id, branch_id, receipt)
                                           VALUES (?, ?, 'debit', ?, ?, ?, 'ticket_sale', NOW(), ?, ?, '')";
        $stmtInsertSupplierTransaction = $pdo->prepare($insertSupplierTransactionQuery);
        $description = "Purchase for ticket: $passenger_name ($origin to $destination)";
        $stmtInsertSupplierTransaction->bindParam(1, $supplier, PDO::PARAM_INT);
        $stmtInsertSupplierTransaction->bindParam(2, $id, PDO::PARAM_INT);
        $stmtInsertSupplierTransaction->bindParam(3, $base, PDO::PARAM_STR);
        $stmtInsertSupplierTransaction->bindParam(4, $newBalance, PDO::PARAM_STR);
        $stmtInsertSupplierTransaction->bindParam(5, $description, PDO::PARAM_STR);
        $stmtInsertSupplierTransaction->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmtInsertSupplierTransaction->bindParam(7, $branch_id, PDO::PARAM_INT);
        $stmtInsertSupplierTransaction->execute();
    }

    // ✅ Always delete old supplier transactions (for all suppliers)
    $deleteOldTransactionsQuery = "DELETE FROM supplier_transactions
                                   WHERE supplier_id = ?
                                   AND reference_id = ?
                                   AND transaction_of = 'ticket_sale'
                                   AND tenant_id = ?
                                   AND branch_id = ?";
    $stmtDeleteOldTransactions = $pdo->prepare($deleteOldTransactionsQuery);
    $stmtDeleteOldTransactions->bindParam(1, $originalSupplier, PDO::PARAM_INT);
    $stmtDeleteOldTransactions->bindParam(2, $id, PDO::PARAM_INT);
    $stmtDeleteOldTransactions->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $stmtDeleteOldTransactions->bindParam(4, $branch_id, PDO::PARAM_INT);
    $stmtDeleteOldTransactions->execute();
}

                // Handle case where supplier remains the same but price changes
                else if ($supplier == $originalSupplier && $priceDifference != 0 && $oldSupplierIsExternal) {
                    // Get current supplier balance
                    $getCurrentSupplierBalanceQuery = "SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                    $stmtGetCurrentSupplierBalance = $pdo->prepare($getCurrentSupplierBalanceQuery);
                    $stmtGetCurrentSupplierBalance->bindParam(1, $supplier, PDO::PARAM_INT);
                    $stmtGetCurrentSupplierBalance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                    $stmtGetCurrentSupplierBalance->bindParam(3, $branch_id, PDO::PARAM_INT);
                    $stmtGetCurrentSupplierBalance->execute();
                    $currentSupplierBalance = $stmtGetCurrentSupplierBalance->fetch(PDO::FETCH_ASSOC)['balance'];
                    
                    // Update supplier balance based on price difference
                    // If priceDifference is positive: base decreased, add to balance (supplier gets money back)
                    // If priceDifference is negative: base increased, subtract from balance (supplier pays more)
                    $newBalance = $currentSupplierBalance + $priceDifference;
                    $updateSupplierQuery = "UPDATE suppliers SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                    $stmtUpdateSupplier = $pdo->prepare($updateSupplierQuery);
                    $stmtUpdateSupplier->bindParam(1, $newBalance, PDO::PARAM_STR);
                    $stmtUpdateSupplier->bindParam(2, $supplier, PDO::PARAM_INT);
                    $stmtUpdateSupplier->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $stmtUpdateSupplier->bindParam(4, $branch_id, PDO::PARAM_INT);
                    $stmtUpdateSupplier->execute();
                    
                    // Check if transaction record exists for this supplier
                    $checkSupplierTransactionQuery = "SELECT id, transaction_date, balance, amount FROM supplier_transactions
                                                      WHERE supplier_id = ?
                                                      AND reference_id = ?
                                                      AND transaction_of = 'ticket_sale'
                                                      AND tenant_id = ?
                                                      AND branch_id = ?
                                                      LIMIT 1";
                    $stmtCheckSupplierTransaction = $pdo->prepare($checkSupplierTransactionQuery);
                    $stmtCheckSupplierTransaction->bindParam(1, $supplier, PDO::PARAM_INT);
                    $stmtCheckSupplierTransaction->bindParam(2, $id, PDO::PARAM_INT);
                    $stmtCheckSupplierTransaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $stmtCheckSupplierTransaction->bindParam(4, $branch_id, PDO::PARAM_INT);
                    $stmtCheckSupplierTransaction->execute();
                    $supplierTransaction = $stmtCheckSupplierTransaction->fetch(PDO::FETCH_ASSOC);
                    
                    if ($supplierTransaction) {
                        $transactionId = $supplierTransaction['id'];
                        $transactionDate = $supplierTransaction['transaction_date'];
                        $currentTransactionAmount = $supplierTransaction['amount'];
                        
                        // Get the current transaction's date and balance
                        $getCurrentTransactionQuery = "SELECT transaction_date, balance FROM supplier_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ? LIMIT 1";
                        $stmtGetCurrentTransaction = $pdo->prepare($getCurrentTransactionQuery);
                        $stmtGetCurrentTransaction->bindParam(1, $transactionId, PDO::PARAM_INT);
                        $stmtGetCurrentTransaction->bindParam(2, $tenant_id, PDO::PARAM_INT);
                        $stmtGetCurrentTransaction->bindParam(3, $branch_id, PDO::PARAM_INT);
                        $stmtGetCurrentTransaction->execute();
                        $currentTransactionData = $stmtGetCurrentTransaction->fetch(PDO::FETCH_ASSOC);
                        $currentTransactionDate = $currentTransactionData['transaction_date'];
                        $currentTransactionBalance = $currentTransactionData['balance'];
                        
                        // Calculate the new transaction balance by applying the price difference
                        $newTransactionBalance = $currentTransactionBalance + $priceDifference;
                        
                        // Update existing transaction record with new amount and balance
                        $updateSupplierTransactionQuery = "UPDATE supplier_transactions
                                                          SET amount = ?,
                                                              balance = ?,
                                                              remarks = CONCAT('Updated: ', remarks)
                                                          WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                        $stmtUpdateSupplierTransaction = $pdo->prepare($updateSupplierTransactionQuery);
                        $stmtUpdateSupplierTransaction->bindParam(1, $base, PDO::PARAM_STR);
                        $stmtUpdateSupplierTransaction->bindParam(2, $newTransactionBalance, PDO::PARAM_STR);
                        $stmtUpdateSupplierTransaction->bindParam(3, $transactionId, PDO::PARAM_INT);
                        $stmtUpdateSupplierTransaction->bindParam(4, $tenant_id, PDO::PARAM_INT);
                        $stmtUpdateSupplierTransaction->bindParam(5, $branch_id, PDO::PARAM_INT);
                        $stmtUpdateSupplierTransaction->execute();

                        // Update all subsequent transactions' balances
                        $updateSubsequentQuery = "UPDATE supplier_transactions
                                                 SET balance = balance + ?
                                                 WHERE supplier_id = ?
                                                 AND branch_id = ?
                                                 AND id > ?
                                                 AND tenant_id = ?
                                                 ORDER BY transaction_date ASC";

                        $stmtUpdateSubsequent = $pdo->prepare($updateSubsequentQuery);
                        $stmtUpdateSubsequent->bindParam(1, $priceDifference, PDO::PARAM_STR);
                        $stmtUpdateSubsequent->bindParam(2, $supplier, PDO::PARAM_INT);
                        $stmtUpdateSubsequent->bindParam(3, $branch_id, PDO::PARAM_INT);
                        $stmtUpdateSubsequent->bindParam(4, $transactionId, PDO::PARAM_INT);
                        $stmtUpdateSubsequent->bindParam(5, $tenant_id, PDO::PARAM_INT);
                        $stmtUpdateSubsequent->execute();
                    } else {
                        // For a new transaction record, the balance should equal the current supplier balance
                        // Create new transaction record
                        $insertSupplierTransactionQuery = "INSERT INTO supplier_transactions (supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_of, transaction_date, tenant_id, branch_id, receipt)
                                                         VALUES (?, ?, 'debit', ?, ?, ?, 'ticket_sale', NOW(), ?, ?, '')";
                        $stmtInsertSupplierTransaction = $pdo->prepare($insertSupplierTransactionQuery);
                        $description = "Purchase for ticket: $passenger_name ($origin to $destination)";
                        $stmtInsertSupplierTransaction->bindParam(1, $supplier, PDO::PARAM_INT);
                        $stmtInsertSupplierTransaction->bindParam(2, $id, PDO::PARAM_INT);
                        $stmtInsertSupplierTransaction->bindParam(3, $base, PDO::PARAM_STR);
                        $stmtInsertSupplierTransaction->bindParam(4, $newBalance, PDO::PARAM_STR);
                        $stmtInsertSupplierTransaction->bindParam(5, $description, PDO::PARAM_STR);
                        $stmtInsertSupplierTransaction->bindParam(6, $tenant_id, PDO::PARAM_INT);
                        $stmtInsertSupplierTransaction->bindParam(7, $branch_id, PDO::PARAM_INT);
                        $stmtInsertSupplierTransaction->execute();
                    }
                }
            }
        }
        
        // Handle client changes or sold price differences
        if ($sold_to != $originalClient || $soldDifference != 0) {
            // Check if original client exists and is regular
            if ($originalClient > 0) {
                $oldClientQuery = "SELECT * FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmtOldClient = $pdo->prepare($oldClientQuery);
                $stmtOldClient->bindParam(1, $originalClient, PDO::PARAM_INT);
                $stmtOldClient->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmtOldClient->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmtOldClient->execute();
                $oldClientData = $stmtOldClient->fetch(PDO::FETCH_ASSOC);
                
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
                                                    AND branch_id = ?
                                                    ORDER BY created_at ASC";
                    $stmtGetOldClientTransactions = $pdo->prepare($getOldClientTransactionsQuery);
                    $stmtGetOldClientTransactions->bindParam(1, $originalClient, PDO::PARAM_INT);
                    $stmtGetOldClientTransactions->bindParam(2, $id, PDO::PARAM_INT);
                    $stmtGetOldClientTransactions->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $stmtGetOldClientTransactions->bindParam(4, $branch_id, PDO::PARAM_INT);
                    $stmtGetOldClientTransactions->execute();
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
                                                  AND transaction_of = 'ticket_sale'
                                                  AND tenant_id = ?
                                                  AND branch_id = ?
                                                  LIMIT 1";
                    $stmtGetTransferTransaction = $pdo->prepare($getTransferTransactionQuery);
                    $stmtGetTransferTransaction->bindParam(1, $originalClient, PDO::PARAM_INT);
                    $stmtGetTransferTransaction->bindParam(2, $id, PDO::PARAM_INT);
                    $stmtGetTransferTransaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $stmtGetTransferTransaction->bindParam(4, $branch_id, PDO::PARAM_INT);
                    $stmtGetTransferTransaction->execute();
                    $transferTransactionData = $stmtGetTransferTransaction->fetch(PDO::FETCH_ASSOC);
                    $transferTransactionDate = $transferTransactionData['created_at'];

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
                        $stmtUpdateOldClientUsd->bindParam(1, $totalUsdAmount, PDO::PARAM_STR);
                        $stmtUpdateOldClientUsd->bindParam(2, $originalClient, PDO::PARAM_INT);
                        $stmtUpdateOldClientUsd->bindParam(3, $tenant_id, PDO::PARAM_INT);
                        $stmtUpdateOldClientUsd->bindParam(4, $branch_id, PDO::PARAM_INT);
                        $stmtUpdateOldClientUsd->execute();

                        // Update subsequent USD transactions for old client
                        $updateOldClientUsdSubsequentQuery = "UPDATE client_transactions
                                                          SET balance = balance + ?
                                                          WHERE client_id = ?
                                                          AND branch_id = ?
                                                          AND id > (SELECT id FROM client_transactions
                                                                   WHERE client_id = ?
                                                                   AND reference_id = ?
                                                                   AND transaction_of = 'ticket_sale'
                                                                   AND tenant_id = ?
                                                                   AND branch_id = ?
                                                                   LIMIT 1)
                                                          AND currency = 'USD'
                                                          AND tenant_id = ?
                                                          ORDER BY created_at ASC, id ASC";
                    $stmtUpdateOldClientUsdSubsequent = $pdo->prepare($updateOldClientUsdSubsequentQuery);
                    $stmtUpdateOldClientUsdSubsequent->bindParam(1, $totalUsdAmount, PDO::PARAM_STR);
                    $stmtUpdateOldClientUsdSubsequent->bindParam(2, $originalClient, PDO::PARAM_INT);
                    $stmtUpdateOldClientUsdSubsequent->bindParam(3, $branch_id, PDO::PARAM_INT);
                    $stmtUpdateOldClientUsdSubsequent->bindParam(4, $originalClient, PDO::PARAM_INT);
                    $stmtUpdateOldClientUsdSubsequent->bindParam(5, $id, PDO::PARAM_INT);
                    $stmtUpdateOldClientUsdSubsequent->bindParam(6, $tenant_id, PDO::PARAM_INT);
                    $stmtUpdateOldClientUsdSubsequent->bindParam(7, $branch_id, PDO::PARAM_INT);
                    $stmtUpdateOldClientUsdSubsequent->bindParam(8, $tenant_id, PDO::PARAM_INT);
                    $stmtUpdateOldClientUsdSubsequent->execute();
                    }
                    
                    if ($totalAfsAmount > 0) {
                        // When removing a ticket from a client, we need to add the amount back
                        $updateOldClientAfsQuery = "UPDATE clients SET afs_balance = afs_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                        $stmtUpdateOldClientAfs = $pdo->prepare($updateOldClientAfsQuery);
                        $stmtUpdateOldClientAfs->bindParam(1, $totalAfsAmount, PDO::PARAM_STR);
                        $stmtUpdateOldClientAfs->bindParam(2, $originalClient, PDO::PARAM_INT);
                        $stmtUpdateOldClientAfs->bindParam(3, $tenant_id, PDO::PARAM_INT);
                        $stmtUpdateOldClientAfs->bindParam(4, $branch_id, PDO::PARAM_INT);
                        $stmtUpdateOldClientAfs->execute();

                        // Update subsequent AFS transactions for old client
                        $updateOldClientAfsSubsequentQuery = "UPDATE client_transactions
                                                              SET balance = balance + ?
                                                              WHERE client_id = ?
                                                              AND branch_id = ?
                                                              AND id > (SELECT id FROM client_transactions
                                                                       WHERE client_id = ?
                                                                       AND reference_id = ?
                                                                       AND transaction_of = 'ticket_sale'
                                                                       AND tenant_id = ?
                                                                       AND branch_id = ?
                                                                       LIMIT 1)
                                                              AND currency = 'AFS'
                                                              AND tenant_id = ?
                                                              ORDER BY created_at ASC, id ASC";
                        $stmtUpdateOldClientAfsSubsequent = $pdo->prepare($updateOldClientAfsSubsequentQuery);
                        $stmtUpdateOldClientAfsSubsequent->bindParam(1, $totalAfsAmount, PDO::PARAM_STR);
                        $stmtUpdateOldClientAfsSubsequent->bindParam(2, $originalClient, PDO::PARAM_INT);
                        $stmtUpdateOldClientAfsSubsequent->bindParam(3, $branch_id, PDO::PARAM_INT);
                        $stmtUpdateOldClientAfsSubsequent->bindParam(4, $originalClient, PDO::PARAM_INT);
                        $stmtUpdateOldClientAfsSubsequent->bindParam(5, $id, PDO::PARAM_INT);
                        $stmtUpdateOldClientAfsSubsequent->bindParam(6, $tenant_id, PDO::PARAM_INT);
                        $stmtUpdateOldClientAfsSubsequent->bindParam(7, $branch_id, PDO::PARAM_INT);
                        $stmtUpdateOldClientAfsSubsequent->bindParam(8, $tenant_id, PDO::PARAM_INT);
                        $stmtUpdateOldClientAfsSubsequent->execute();
                    }

                    // ✅ Delete old client transactions (for consistency with supplier logic)
                    $deleteOldClientTransactionsQuery = "DELETE FROM client_transactions
                                                         WHERE client_id = ?
                                                         AND reference_id = ?
                                                         AND transaction_of = 'ticket_sale'
                                                         AND tenant_id = ?
                                                         AND branch_id = ?";
                    $stmtDeleteOldClientTransactions = $pdo->prepare($deleteOldClientTransactionsQuery);
                    $stmtDeleteOldClientTransactions->bindParam(1, $originalClient, PDO::PARAM_INT);
                    $stmtDeleteOldClientTransactions->bindParam(2, $id, PDO::PARAM_INT);
                    $stmtDeleteOldClientTransactions->bindParam(3, $tenant_id, PDO::PARAM_INT);
                    $stmtDeleteOldClientTransactions->bindParam(4, $branch_id, PDO::PARAM_INT);
                    $stmtDeleteOldClientTransactions->execute();

                    // Check if new client is regular
                    $newClientQuery = "SELECT * FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                    $stmtNewClient = $pdo->prepare($newClientQuery);
                    $stmtNewClient->bindParam(1, $sold_to, PDO::PARAM_INT);
                    $stmtNewClient->bindParam(2, $tenant_id, PDO::PARAM_INT);
                    $stmtNewClient->bindParam(3, $branch_id, PDO::PARAM_INT);
                    $stmtNewClient->execute();
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
                        $stmtGetNewClientUsdBalance->bindParam(1, $sold_to, PDO::PARAM_INT);
                        $stmtGetNewClientUsdBalance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                        $stmtGetNewClientUsdBalance->bindParam(3, $branch_id, PDO::PARAM_INT);
                        $stmtGetNewClientUsdBalance->execute();
                        $newClientUsdBalance = $stmtGetNewClientUsdBalance->fetch(PDO::FETCH_ASSOC)['usd_balance'];

                        $getNewClientAfsBalanceQuery = "SELECT afs_balance FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                        $stmtGetNewClientAfsBalance = $pdo->prepare($getNewClientAfsBalanceQuery);
                        $stmtGetNewClientAfsBalance->bindParam(1, $sold_to, PDO::PARAM_INT);
                        $stmtGetNewClientAfsBalance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                        $stmtGetNewClientAfsBalance->bindParam(3, $branch_id, PDO::PARAM_INT);
                        $stmtGetNewClientAfsBalance->execute();
                        $newClientAfsBalance = $stmtGetNewClientAfsBalance->fetch(PDO::FETCH_ASSOC)['afs_balance'];

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
                            $stmtUpdateNewClientUsd->bindParam(1, $ClientUsdBalance, PDO::PARAM_STR);
                            $stmtUpdateNewClientUsd->bindParam(2, $sold_to, PDO::PARAM_INT);
                            $stmtUpdateNewClientUsd->bindParam(3, $tenant_id, PDO::PARAM_INT);
                            $stmtUpdateNewClientUsd->bindParam(4, $branch_id, PDO::PARAM_INT);
                            $stmtUpdateNewClientUsd->execute();

                        }
                        
                        if ($totalAfsAmount > 0) {
                            // When adding a ticket to a client with negative balance, we need to subtract the amount
                            $updateNewClientAfsQuery = "UPDATE clients SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                            $stmtUpdateNewClientAfs = $pdo->prepare($updateNewClientAfsQuery);
                            $stmtUpdateNewClientAfs->bindParam(1, $totalAfsAmount, PDO::PARAM_STR);
                            $stmtUpdateNewClientAfs->bindParam(2, $sold_to, PDO::PARAM_INT);
                            $stmtUpdateNewClientAfs->bindParam(3, $tenant_id, PDO::PARAM_INT);
                            $stmtUpdateNewClientAfs->bindParam(4, $branch_id, PDO::PARAM_INT);
                            $stmtUpdateNewClientAfs->execute();

                        }
                    }
                }
            }
            
            // Check if new client exists and is regular
            if ($sold_to > 0) {
                $clientQuery = "SELECT * FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $stmtClient = $pdo->prepare($clientQuery);
                $stmtClient->bindParam(1, $sold_to, PDO::PARAM_INT);
                $stmtClient->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmtClient->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmtClient->execute();
                $clientData = $stmtClient->fetch(PDO::FETCH_ASSOC);
                
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
                        $updateClientQuery = "UPDATE clients SET $balanceField = $balanceField - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                        $stmtUpdateClient = $pdo->prepare($updateClientQuery);
                        $stmtUpdateClient->bindParam(1, $sold, PDO::PARAM_STR);
                        $stmtUpdateClient->bindParam(2, $sold_to, PDO::PARAM_INT);
                        $stmtUpdateClient->bindParam(3, $tenant_id, PDO::PARAM_INT);
                        $stmtUpdateClient->bindParam(4, $branch_id, PDO::PARAM_INT);
                        $stmtUpdateClient->execute();
                        
                        // Get current client balance for the transaction record
                        $getCurrentBalanceQuery = "SELECT $balanceField FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                        $stmtGetCurrentBalance = $pdo->prepare($getCurrentBalanceQuery);
                        $stmtGetCurrentBalance->bindParam(1, $sold_to, PDO::PARAM_INT);
                        $stmtGetCurrentBalance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                        $stmtGetCurrentBalance->bindParam(3, $branch_id, PDO::PARAM_INT);
                        $stmtGetCurrentBalance->execute();
                        $currentBalance = $stmtGetCurrentBalance->fetch(PDO::FETCH_ASSOC)[$balanceField];
                        
                        // Create new transaction record for new client
                        $insertClientTransactionQuery = "INSERT INTO client_transactions (client_id, reference_id, type, amount, currency, balance, description, transaction_of, tenant_id, branch_id, receipt) VALUES (?, ?, 'debit', ?, ?, ?, ?, 'ticket_sale', ?, ?, NULL)";
                        $stmtInsertClientTransaction = $pdo->prepare($insertClientTransactionQuery);
                        $description = "Sale for ticket: $passenger_name ($origin to $destination)";
                        $stmtInsertClientTransaction->bindParam(1, $sold_to, PDO::PARAM_INT);
                        $stmtInsertClientTransaction->bindParam(2, $id, PDO::PARAM_INT);
                        $stmtInsertClientTransaction->bindParam(3, $sold, PDO::PARAM_STR);
                        $stmtInsertClientTransaction->bindParam(4, $currency, PDO::PARAM_STR);
                        $stmtInsertClientTransaction->bindParam(5, $currentBalance, PDO::PARAM_STR);
                        $stmtInsertClientTransaction->bindParam(6, $description, PDO::PARAM_STR);
                        $stmtInsertClientTransaction->bindParam(7, $tenant_id, PDO::PARAM_INT);
                        $stmtInsertClientTransaction->bindParam(8, $branch_id, PDO::PARAM_INT);
                        $stmtInsertClientTransaction->execute();
                    } 
                    // Same client but sold price changed
                    else if ($soldDifference != 0) {
                        $balanceField = strtolower($currency) === 'usd' ? 'usd_balance' : 'afs_balance';
                        
                        // Get current client balance before update
                        $getCurrentBalanceQuery = "SELECT $balanceField FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                        $stmtGetCurrentBalance = $pdo->prepare($getCurrentBalanceQuery);
                        $stmtGetCurrentBalance->bindParam(1, $sold_to, PDO::PARAM_INT);
                        $stmtGetCurrentBalance->bindParam(2, $tenant_id, PDO::PARAM_INT);
                        $stmtGetCurrentBalance->bindParam(3, $branch_id, PDO::PARAM_INT);
                        $stmtGetCurrentBalance->execute();
                        $currentBalance = $stmtGetCurrentBalance->fetch(PDO::FETCH_ASSOC)[$balanceField];
                        
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
                        $stmtUpdateClient->bindParam(1, $soldDifference, PDO::PARAM_STR);
                        $stmtUpdateClient->bindParam(2, $sold_to, PDO::PARAM_INT);
                        $stmtUpdateClient->bindParam(3, $tenant_id, PDO::PARAM_INT);
                        $stmtUpdateClient->bindParam(4, $branch_id, PDO::PARAM_INT);
                        $stmtUpdateClient->execute();
                        
                        // Check if transaction record exists for this client
                        $checkClientTransactionQuery = "SELECT id, created_at, balance, amount FROM client_transactions WHERE client_id = ? AND reference_id = ? AND transaction_of = 'ticket_sale' AND tenant_id = ? AND branch_id = ? LIMIT 1";
                        $stmtCheckClientTransaction = $pdo->prepare($checkClientTransactionQuery);
                        $stmtCheckClientTransaction->bindParam(1, $sold_to, PDO::PARAM_INT);
                        $stmtCheckClientTransaction->bindParam(2, $id, PDO::PARAM_INT);
                        $stmtCheckClientTransaction->bindParam(3, $tenant_id, PDO::PARAM_INT);
                        $stmtCheckClientTransaction->bindParam(4, $branch_id, PDO::PARAM_INT);
                        $stmtCheckClientTransaction->execute();
                        $clientTransaction = $stmtCheckClientTransaction->fetch(PDO::FETCH_ASSOC);
                        
                        if ($clientTransaction) {
                            $transactionId = $clientTransaction['id'];
                            $transactionDate = $clientTransaction['created_at'];
                            $currentTransactionBalance = $clientTransaction['balance'];
                            $currentTransactionAmount = $clientTransaction['amount'];
                            
                            // Calculate the difference between the new sold amount and the current transaction amount
                            $amountDifference = $sold - $currentTransactionAmount;
                            
                            // For client transactions, subsequent balances should:
                            // - Increase (add) when amount decreases
                            // - Decrease (subtract) when amount increases
                            $balanceAdjustment = -$amountDifference;
                            
                            // Get the current transaction's date
                            $getCurrentTransactionQuery = "SELECT created_at FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ? LIMIT 1";
                            $stmtGetCurrentTransaction = $pdo->prepare($getCurrentTransactionQuery);
                            $stmtGetCurrentTransaction->bindParam(1, $transactionId, PDO::PARAM_INT);
                            $stmtGetCurrentTransaction->bindParam(2, $tenant_id, PDO::PARAM_INT);
                            $stmtGetCurrentTransaction->bindParam(3, $branch_id, PDO::PARAM_INT);
                            $stmtGetCurrentTransaction->execute();
                            $currentTransactionDate = $stmtGetCurrentTransaction->fetch(PDO::FETCH_ASSOC)['created_at'];
                            
                            // Update existing transaction record with adjusted balance
                            $updateClientTransactionQuery = "UPDATE client_transactions
                                                           SET amount = ?,
                                                               balance = balance + ?,
                                                               description = CONCAT('Updated: ', description)
                                                           WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                            $stmtUpdateClientTransaction = $pdo->prepare($updateClientTransactionQuery);
                            $stmtUpdateClientTransaction->bindParam(1, $sold, PDO::PARAM_STR);
                            $stmtUpdateClientTransaction->bindParam(2, $balanceAdjustment, PDO::PARAM_STR);
                            $stmtUpdateClientTransaction->bindParam(3, $transactionId, PDO::PARAM_INT);
                            $stmtUpdateClientTransaction->bindParam(4, $tenant_id, PDO::PARAM_INT);
                            $stmtUpdateClientTransaction->bindParam(5, $branch_id, PDO::PARAM_INT);
                            $stmtUpdateClientTransaction->execute();
                            
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
                            $stmtUpdateSubsequent->bindParam(1, $balanceAdjustment, PDO::PARAM_STR);
                            $stmtUpdateSubsequent->bindParam(2, $sold_to, PDO::PARAM_INT);
                            $stmtUpdateSubsequent->bindParam(3, $branch_id, PDO::PARAM_INT);
                            $stmtUpdateSubsequent->bindParam(4, $currency, PDO::PARAM_STR);
                            $stmtUpdateSubsequent->bindParam(5, $transactionId, PDO::PARAM_INT);
                            $stmtUpdateSubsequent->bindParam(6, $tenant_id, PDO::PARAM_INT);
                            $stmtUpdateSubsequent->execute();
                            $stmtUpdateSubsequent->close();
                        } else {
                            // Create new transaction record if one doesn't exist
                            $insertClientTransactionQuery = "INSERT INTO client_transactions (client_id, reference_id, type, amount, currency, balance, description, transaction_of, tenant_id, branch_id, receipt) VALUES (?, ?, 'debit', ?, ?, ?, ?, 'ticket_sale', ?, ?, '')";
                            $stmtInsertClientTransaction = $pdo->prepare($insertClientTransactionQuery);
                            $description = "Sale for ticket: $passenger_name ($origin to $destination)";
                            $stmtInsertClientTransaction->bindParam(1, $sold_to, PDO::PARAM_INT);
                            $stmtInsertClientTransaction->bindParam(2, $id, PDO::PARAM_INT);
                            $stmtInsertClientTransaction->bindParam(3, $sold, PDO::PARAM_STR);
                            $stmtInsertClientTransaction->bindParam(4, $currency, PDO::PARAM_STR);
                            $stmtInsertClientTransaction->bindParam(5, $newBalance, PDO::PARAM_STR);
                            $stmtInsertClientTransaction->bindParam(6, $description, PDO::PARAM_STR);
                            $stmtInsertClientTransaction->bindParam(7, $tenant_id, PDO::PARAM_INT);
                            $stmtInsertClientTransaction->bindParam(8, $branch_id, PDO::PARAM_INT);
                            $stmtInsertClientTransaction->execute();
                        }
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
            departure_time = ?,
            return_date = ?,
            return_departure_time = ?,
            price = ?,
            sold = ?,
            profit = ?,
            currency = ?,
            description = ?,
            paid_to = ?
            WHERE id = ? AND tenant_id = ? AND branch_id = ?";

        $stmtTicket = $pdo->prepare($updateTicketQuery);
        $stmtTicket->bindParam(1, $supplier, PDO::PARAM_INT);
        $stmtTicket->bindParam(2, $sold_to, PDO::PARAM_INT);
        $stmtTicket->bindParam(3, $trip_type, PDO::PARAM_STR);
        $stmtTicket->bindParam(4, $title, PDO::PARAM_STR);
        $stmtTicket->bindParam(5, $gender, PDO::PARAM_STR);
        $stmtTicket->bindParam(6, $passenger_name, PDO::PARAM_STR);
        $stmtTicket->bindParam(7, $pnr, PDO::PARAM_STR);
        $stmtTicket->bindParam(8, $phone, PDO::PARAM_STR);
        $stmtTicket->bindParam(9, $origin, PDO::PARAM_STR);
        $stmtTicket->bindParam(10, $destination, PDO::PARAM_STR);
        $stmtTicket->bindParam(11, $return_origin, PDO::PARAM_STR);
        $stmtTicket->bindParam(12, $return_destination, PDO::PARAM_STR);
        $stmtTicket->bindParam(13, $airline, PDO::PARAM_STR);
        $stmtTicket->bindParam(14, $issue_date, PDO::PARAM_STR);
        $stmtTicket->bindParam(15, $departure_date, PDO::PARAM_STR);
        $stmtTicket->bindParam(16, $departure_time, PDO::PARAM_STR);
        $stmtTicket->bindParam(17, $return_date, PDO::PARAM_STR);
        $stmtTicket->bindParam(18, $return_departure_time, PDO::PARAM_STR);
        $stmtTicket->bindParam(19, $base, PDO::PARAM_STR);  // This maps to the 'price' field in the database
        $stmtTicket->bindParam(20, $sold, PDO::PARAM_STR);
        $stmtTicket->bindParam(21, $profit, PDO::PARAM_STR);
        $stmtTicket->bindParam(22, $currency, PDO::PARAM_STR);
        $stmtTicket->bindParam(23, $description, PDO::PARAM_STR);
        $stmtTicket->bindParam(24, $paid_to, PDO::PARAM_INT);
        $stmtTicket->bindParam(25, $id, PDO::PARAM_INT);
        $stmtTicket->bindParam(26, $tenant_id, PDO::PARAM_INT);
        $stmtTicket->bindParam(27, $branch_id, PDO::PARAM_INT);

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
            'departure_time' => $departure_time,
            'return_date' => $return_date,
            'return_departure_time' => $return_departure_time,
            'price' => $base,
            'sold' => $sold,
            'profit' => $profit,
            'currency' => $currency,
            'description' => $description,
            'paid_to' => $paid_to,
            'market_exchange_rate' => $marketExchangeRate
        ];
        $action = 'update';
        $table_name = 'ticket_bookings';
        // Insert activity log
        $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id, branch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // Store JSON encoded values in variables first
        $old_values_json = json_encode($old_values);
        $new_values_json = json_encode($new_values);

        $activity_log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(2, $action, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(3, $table_name, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(4, $id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(5, $old_values_json, PDO::PARAM_STR);  // Use the stored JSON string
        $activity_log_stmt->bindParam(6, $new_values_json, PDO::PARAM_STR);  // Use the stored JSON string
        $activity_log_stmt->bindParam(7, $ip_address, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(8, $user_agent, PDO::PARAM_STR);
        $activity_log_stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
        $activity_log_stmt->bindParam(10, $branch_id, PDO::PARAM_INT);
        $activity_log_stmt->execute();
        
        // Commit transaction
        $pdo->commit();

        $response['success'] = true;
        $response['message'] = 'Ticket updated successfully';
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        $response['message'] = 'Error updating ticket: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>
