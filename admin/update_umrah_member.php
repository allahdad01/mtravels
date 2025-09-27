<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Database connection
require_once('../includes/db.php');

// Check if form data is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Validate required fields
$requiredFields = [
    'booking_id', 'family_id', 'supplier', 'soldTo', 'paidTo', 'entry_date', 
    'name', 'dob', 'passport_number', 'id_type',
    'duration', 'room_type','sold_price'
];

foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
        exit();
    }
}

// Validate due
$due = isset($_POST['due']) ? DbSecurity::validateInput($_POST['due'], 'float', ['min' => 0]) : null;

// Validate paid
$paid = isset($_POST['paid']) ? DbSecurity::validateInput($_POST['paid'], 'float', ['min' => 0]) : null;

// Validate bank_receipt_number
$bank_receipt_number = isset($_POST['bank_receipt_number']) ? DbSecurity::validateInput($_POST['bank_receipt_number'], 'string', ['maxlength' => 255]) : null;

// Validate received_bank_payment
$received_bank_payment = isset($_POST['received_bank_payment']) ? DbSecurity::validateInput($_POST['received_bank_payment'], 'float', ['min' => 0]) : null;

// Validate profit
$profit = isset($_POST['profit']) ? DbSecurity::validateInput($_POST['profit'], 'float', ['min' => 0]) : null;

// Validate sold_price
$sold_price = isset($_POST['sold_price']) ? DbSecurity::validateInput($_POST['sold_price'], 'float', ['min' => 0]) : null;

// Validate price
$price = isset($_POST['price']) ? DbSecurity::validateInput($_POST['price'], 'float', ['min' => 0]) : null;

// Validate supplier_currency
$supplier_currency = isset($_POST['supplier_currency']) ? DbSecurity::validateInput($_POST['supplier_currency'], 'currency') : null;

// Validate room_type
$room_type = isset($_POST['room_type']) ? DbSecurity::validateInput($_POST['room_type'], 'string', ['maxlength' => 255]) : null;

// Validate duration
$duration = isset($_POST['duration']) ? DbSecurity::validateInput($_POST['duration'], 'string', ['maxlength' => 255]) : null;

// Validate return_date
$return_date = isset($_POST['return_date']) ? DbSecurity::validateInput($_POST['return_date'], 'date') : null;

// Validate flight_date
$flight_date = isset($_POST['flight_date']) ? DbSecurity::validateInput($_POST['flight_date'], 'date') : null;

// Validate id_type
$id_type = isset($_POST['id_type']) ? DbSecurity::validateInput($_POST['id_type'], 'string', ['maxlength' => 255]) : null;

// Validate passport_number
$passport_number = isset($_POST['passport_number']) ? DbSecurity::validateInput($_POST['passport_number'], 'string', ['maxlength' => 255]) : null;

// Validate dob
$dob = isset($_POST['dob']) ? DbSecurity::validateInput($_POST['dob'], 'string', ['maxlength' => 255]) : null;

// Validate name
$name = isset($_POST['name']) ? DbSecurity::validateInput($_POST['name'], 'string', ['maxlength' => 255]) : null;

// Validate entry_date
$entry_date = isset($_POST['entry_date']) ? DbSecurity::validateInput($_POST['entry_date'], 'date') : null;

// Validate paidTo
$paidTo = isset($_POST['paidTo']) ? DbSecurity::validateInput($_POST['paidTo'], 'int', ['min' => 0]) : null;

// Validate soldTo
$soldTo = isset($_POST['soldTo']) ? DbSecurity::validateInput($_POST['soldTo'], 'int', ['min' => 0]) : null;

// Validate supplier
$supplier = isset($_POST['supplier']) ? DbSecurity::validateInput($_POST['supplier'], 'int', ['min' => 0]) : null;

// Validate family_id
$family_id = isset($_POST['family_id']) ? DbSecurity::validateInput($_POST['family_id'], 'int', ['min' => 0]) : null;

// Validate booking_id
$booking_id = isset($_POST['booking_id']) ? DbSecurity::validateInput($_POST['booking_id'], 'int', ['min' => 0]) : null;

// Validate remarks
$remarks = isset($_POST['remarks']) ? DbSecurity::validateInput($_POST['remarks'], 'string', ['maxlength' => 255]) : null;

// New fields
$gender = isset($_POST['gender']) ? DbSecurity::validateInput($_POST['gender'], 'string', ['maxlength' => 255]) : null;
$passport_expiry = isset($_POST['passport_expiry']) ? DbSecurity::validateInput($_POST['passport_expiry'], 'date') : null;
$relation = isset($_POST['relation']) ? DbSecurity::validateInput($_POST['relation'], 'string', ['maxlength' => 255]) : null;
$g_name = isset($_POST['g_name']) ? DbSecurity::validateInput($_POST['g_name'], 'string', ['maxlength' => 255]) : null;
$father_name = isset($_POST['father_name']) ? DbSecurity::validateInput($_POST['father_name'], 'string', ['maxlength' => 255]) : null;
$discount = isset($_POST['discount']) ? DbSecurity::validateInput($_POST['discount'], 'float', ['min' => 0]) : null;

// Validate passport expiry (must be at least 6 months from today)
if (!empty($passport_expiry)) {
    $today = new DateTime();
    $sixMonthsLater = (new DateTime())->modify('+6 months');
    $expiryDate = new DateTime($passport_expiry);
    
    if ($expiryDate < $sixMonthsLater) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Passport must be valid for at least 6 months from today for Umrah visa requirements']);
        exit();
    }
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // First, get the current booking data to calculate balance adjustments
    $stmtCurrentData = $pdo->prepare("
        SELECT supplier, sold_to, paid_to, entry_date, name, dob, passport_number, id_type, flight_date, return_date, duration, room_type, price, sold_price, profit, received_bank_payment, bank_receipt_number, paid, due, discount
        FROM umrah_bookings 
        WHERE booking_id = ? AND tenant_id = ?
    ");
    $stmtCurrentData->execute([$booking_id, $tenant_id]);
    $currentData = $stmtCurrentData->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentData) {
        throw new PDOException("Booking not found");
    }
    
    // Get supplier type
    $stmtSupplierType = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?");
    $stmtSupplierType->execute([$supplier, $tenant_id]);
    $supplierData = $stmtSupplierType->fetch(PDO::FETCH_ASSOC);
    $isExternalSupplier = ($supplierData && $supplierData['supplier_type'] === 'External');
    
    // Get old supplier type if supplier has changed
    $oldSupplierIsExternal = false;
    if ($supplier != $currentData['supplier']) {
        $stmtOldSupplierType = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ?");
        $stmtOldSupplierType->execute([$currentData['supplier'], $tenant_id]);
        $oldSupplierData = $stmtOldSupplierType->fetch(PDO::FETCH_ASSOC);
        $oldSupplierIsExternal = ($oldSupplierData && $oldSupplierData['supplier_type'] === 'External');
    }
    
    // Get client type
    $stmtClientType = $pdo->prepare("SELECT client_type FROM clients WHERE id = ? AND tenant_id = ?");
    $stmtClientType->execute([$soldTo, $tenant_id]);
    $clientData = $stmtClientType->fetch(PDO::FETCH_ASSOC);
    $isRegularClient = ($clientData && $clientData['client_type'] === 'regular');
    
    // Get old client type if client has changed
    $oldClientIsRegular = false;
    if ($soldTo != $currentData['sold_to']) {
        $stmtOldClientType = $pdo->prepare("SELECT client_type FROM clients WHERE id = ? AND tenant_id = ?");
        $stmtOldClientType->execute([$currentData['sold_to'], $tenant_id]);
        $oldClientData = $stmtOldClientType->fetch(PDO::FETCH_ASSOC);
        $oldClientIsRegular = ($oldClientData && $oldClientData['client_type'] === 'regular');
    }
    
    // Calculate adjustments
    $supplierPriceAdjustment = $price - $currentData['price'];
    $clientPriceAdjustment = $sold_price - $currentData['sold_price'];
    $paidAdjustment = $paid - $currentData['paid'];
    
    // Update umrah_bookings table
    $stmt = $pdo->prepare("
        UPDATE umrah_bookings SET 
            family_id = ?,
            supplier = ?,
            sold_to = ?,
            paid_to = ?,
            entry_date = ?,
            name = ?,
            dob = ?,
            passport_number = ?,
            id_type = ?,
            flight_date = ?,
            return_date = ?,
            duration = ?,
            room_type = ?,
            currency = ?,
            price = ?,
            sold_price = ?,
            profit = ?,
            received_bank_payment = ?,
            bank_receipt_number = ?,
            paid = ?,
            due = ?,
            gender = ?,
            passport_expiry = ?,
            remarks = ?,
            relation = ?,
            gfname = ?,
            fname = ?,
            discount = ?,
            updated_at = NOW()
        WHERE booking_id = ? AND tenant_id = ?
    ");
    
    $stmt->execute([
        $family_id,
        $supplier,
        $soldTo,
        $paidTo,
        $entry_date,
        $name,
        $dob,
        $passport_number,
        $id_type,
        $flight_date,
        $return_date,
        $duration,
        $room_type,
        $supplier_currency,
        $price,
        $sold_price,
        $profit,
        $received_bank_payment,
        $bank_receipt_number,
        $paid,
        $due,
        $gender,
        $passport_expiry,
        $remarks,
        $relation,
        $g_name,
        $father_name,
        $discount,
        $booking_id,
        $tenant_id
    ]);
    
    // Update supplier balance if supplier has changed or price has changed
    // Only update if supplier is External
    if ($supplier != $currentData['supplier'] || $supplierPriceAdjustment != 0) {
        // If supplier has changed
        if ($supplier != $currentData['supplier']) {
            // Adjust old supplier balance if old supplier was External
            if ($oldSupplierIsExternal) {
                $updateOldSupplierStmt = $pdo->prepare("
                    UPDATE suppliers 
                    SET balance = balance + ? 
                    WHERE id = ? AND tenant_id = ?
                ");
                $updateOldSupplierStmt->execute([$currentData['price'], $currentData['supplier'], $tenant_id]);
                
                // Check if transaction record exists for old supplier
                $checkOldSupplierTransactionStmt = $pdo->prepare("
                    SELECT id FROM supplier_transactions 
                    WHERE supplier_id = ? AND reference_id = ? and transaction_of = 'umrah' AND tenant_id = ?
                    LIMIT 1
                ");
                $checkOldSupplierTransactionStmt->execute([$currentData['supplier'], $booking_id, $tenant_id]);
                $oldSupplierTransactionExists = $checkOldSupplierTransactionStmt->fetch();
                
                if ($oldSupplierTransactionExists) {
                    // Get transaction details before deleting
                    $getOldSupplierTransactionStmt = $pdo->prepare("
                        SELECT id, transaction_date, amount 
                        FROM supplier_transactions 
                        WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah' 
                        AND tenant_id = ?
                        LIMIT 1
                    ");
                    $getOldSupplierTransactionStmt->execute([$currentData['supplier'], $booking_id, $tenant_id]);
                    $oldSupplierTransactionData = $getOldSupplierTransactionStmt->fetch(PDO::FETCH_ASSOC);

                    if ($oldSupplierTransactionData) {
                        // Update all subsequent transactions' balances
                        // Since we're removing a debit transaction, we need to increase subsequent balances
                        $updateSubsequentSupplierStmt = $pdo->prepare("
                            UPDATE supplier_transactions 
                            SET balance = balance + ? 
                            WHERE supplier_id = ? AND tenant_id = ?
                            AND transaction_date > ? 
                            AND id != ?
                        ");
                        $transactionAmount = abs($oldSupplierTransactionData['amount']); // Make sure it's positive
                        $updateSubsequentSupplierStmt->execute([
                            $transactionAmount,
                            $currentData['supplier'],
                            $tenant_id,
                            $oldSupplierTransactionData['transaction_date'],
                            $oldSupplierTransactionData['id'],
                            $tenant_id
                        ]);
                    }

                    // Delete the old transaction record
                    $deleteOldSupplierTransactionStmt = $pdo->prepare("
                        DELETE FROM supplier_transactions 
                        WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ?
                    ");
                    $deleteOldSupplierTransactionStmt->execute([$currentData['supplier'], $booking_id, $tenant_id]);
                }
            }
            
            // Add new transaction to new supplier if new supplier is External
            if ($isExternalSupplier) {
                $updateNewSupplierStmt = $pdo->prepare("
                    UPDATE suppliers 
                    SET balance = balance - ? 
                    WHERE id = ? AND tenant_id = ?
                ");
                $updateNewSupplierStmt->execute([$price, $supplier, $tenant_id]);

                // Get the updated balance after the update
                $getNewSupplierBalanceStmt = $pdo->prepare("
                    SELECT balance as current_balance 
                    FROM suppliers 
                    WHERE id = ? AND tenant_id = ?
                ");
                $getNewSupplierBalanceStmt->execute([$supplier, $tenant_id]);
                $newSupplierBalance = $getNewSupplierBalanceStmt->fetchColumn();
                
                // Create new transaction record for new supplier
                $insertNewSupplierTransactionStmt = $pdo->prepare("
                    INSERT INTO supplier_transactions (supplier_id, reference_id, transaction_type, amount, remarks, balance, transaction_of, tenant_id)
                    VALUES (?, ?, 'Debit', ?, ?, ?, 'umrah', ?)
                ");
                $insertNewSupplierTransactionStmt->execute([
                    $supplier, 
                    $booking_id, 
                    $price, 
                    "Purchase for member: $name (Passport: $passport_number)",
                    $newSupplierBalance,
                    $tenant_id
                ]);
            }
        } else {
            // Same supplier, just adjust the balance by the difference if supplier is External
            if ($isExternalSupplier && $supplierPriceAdjustment != 0) {
                // Get current supplier balance before update
                $getCurrentSupplierBalanceStmt = $pdo->prepare("
                    SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ?
                ");
                $getCurrentSupplierBalanceStmt->execute([$supplier, $tenant_id]);
                $currentSupplierBalance = $getCurrentSupplierBalanceStmt->fetchColumn();
                
                // Calculate new balance
                $newSupplierBalance = $currentSupplierBalance - $supplierPriceAdjustment;
                
                $updateSupplierStmt = $pdo->prepare("
                    UPDATE suppliers 
                    SET balance = balance - ? 
                    WHERE id = ? AND tenant_id = ?
                ");
                $updateSupplierStmt->execute([$supplierPriceAdjustment, $supplier, $tenant_id]);
                
                // Check if transaction record exists for this supplier
                $checkSupplierTransactionStmt = $pdo->prepare("
                    SELECT id, transaction_date, balance, amount FROM supplier_transactions 
                    WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ?
                ");
                $checkSupplierTransactionStmt->execute([$supplier, $booking_id, $tenant_id]);
                $supplierTransactionExists = $checkSupplierTransactionStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($supplierTransactionExists) {
                    $supplierTransactionId = $supplierTransactionExists['id'];
                    $supplierTransactionDate = $supplierTransactionExists['transaction_date'];
                    $currentTransactionBalance = floatval($supplierTransactionExists['balance']);
                    $currentTransactionAmount = floatval($supplierTransactionExists['amount']); // Ensure positive value
                    
                    // Calculate the difference between the new price and the current transaction amount
                    $amountDifference = $price - $currentTransactionAmount;
                    
                    // Calculate the new balance for this transaction
                    // If price increased, balance should decrease by the difference
                    // If price decreased, balance should increase by the difference
                    $newTransactionBalance = $currentTransactionBalance - $amountDifference;
                    
                    // Update amount field to new price
                    $updateSupplierAmountStmt = $pdo->prepare("
                        UPDATE supplier_transactions 
                        SET amount = ?
                        WHERE id = ? AND tenant_id = ?
                    ");
                    $updateSupplierAmountStmt->execute([$price, $supplierTransactionId, $tenant_id]);
                    
                    // Update existing transaction record with adjusted balance
                    $updateSupplierTransactionStmt = $pdo->prepare("
                        UPDATE supplier_transactions 
                        SET balance = ?,
                            remarks = CONCAT('Updated: ', remarks)
                        WHERE id = ? AND tenant_id = ?
                    ");
                    $updateSupplierTransactionStmt->execute([$newTransactionBalance, $supplierTransactionId, $tenant_id]);
                    
                    // Update all subsequent transactions' balances
                    // If price increased, decrease subsequent balances
                    // If price decreased, increase subsequent balances
                    if ($amountDifference > 0) {
                        // Price increased, decrease subsequent balances
                        $updateSubsequentSupplierStmt = $pdo->prepare("
                            UPDATE supplier_transactions 
                            SET balance = balance - ? 
                            WHERE supplier_id = ? AND tenant_id = ?
                            AND transaction_date > ?
                        ");
                    } else {
                        // Price decreased, increase subsequent balances
                        $updateSubsequentSupplierStmt = $pdo->prepare("
                            UPDATE supplier_transactions 
                            SET balance = balance + ? 
                            WHERE supplier_id = ? AND tenant_id = ?
                            AND transaction_date > ?
                        ");
                    }
                    
                    $absAmountDifference = abs($amountDifference);
                    $updateSubsequentSupplierStmt->execute([$absAmountDifference, $supplier, $tenant_id, $supplierTransactionDate]);
                }
            }
        }
    }
    
    // Update client balance if client has changed or sold price has changed
    // Only update if client is Regular
    if ($soldTo != $currentData['sold_to'] || $clientPriceAdjustment != 0) {
        // If client has changed
        if ($soldTo != $currentData['sold_to']) {
            // Adjust old client balance if old client was Regular
            if ($oldClientIsRegular) {
                if ($supplier_currency == 'USD') {
                    $updateOldClientStmt = $pdo->prepare("
                        UPDATE clients 
                        SET usd_balance = usd_balance + ? 
                        WHERE id = ? AND tenant_id = ?
                    ");
                } else {
                    $updateOldClientStmt = $pdo->prepare("
                        UPDATE clients 
                        SET afs_balance = afs_balance + ? 
                        WHERE id = ? AND tenant_id = ?
                    ");
                }
                $updateOldClientStmt->execute([$currentData['sold_price'], $currentData['sold_to'], $tenant_id]);
                
                // Check if transaction record exists for old client
                $checkOldClientTransactionStmt = $pdo->prepare("
                    SELECT id FROM client_transactions 
                    WHERE client_id = ? AND reference_id = ? and transaction_of = 'umrah' AND tenant_id = ?
                    
                ");
                $checkOldClientTransactionStmt->execute([$currentData['sold_to'], $booking_id, $tenant_id]);
                $oldClientTransactionExists = $checkOldClientTransactionStmt->fetch();
                
                if ($oldClientTransactionExists) {
                    // Get transaction details before deleting
                    $getOldClientTransactionStmt = $pdo->prepare("
                        SELECT id, created_at, amount, currency 
                        FROM client_transactions 
                        WHERE client_id = ? AND reference_id = ? AND transaction_of = 'umrah' 
                        AND tenant_id = ?
                        LIMIT 1
                    ");
                    $getOldClientTransactionStmt->execute([$currentData['sold_to'], $booking_id, $tenant_id]);
                    $oldTransactionData = $getOldClientTransactionStmt->fetch(PDO::FETCH_ASSOC);

                    if ($oldTransactionData) {
                        // Update all subsequent transactions' balances
                        // Since we're removing a debit transaction, we need to increase subsequent balances
                        $updateSubsequentStmt = $pdo->prepare("
                            UPDATE client_transactions 
                            SET balance = balance + ? 
                            WHERE client_id = ? AND tenant_id = ?
                            AND created_at > ? 
                            AND currency = ? 
                            AND id != ?
                        ");
                        $transactionAmount = abs($oldTransactionData['amount']); // Make sure it's positive
                        $updateSubsequentStmt->execute([
                            $transactionAmount,
                            $currentData['sold_to'],
                            $oldTransactionData['created_at'],
                            $oldTransactionData['currency'],
                            $oldTransactionData['id'],
                            $tenant_id
                        ]);
                    }

                    // Delete the old transaction record
                    $deleteOldClientTransactionStmt = $pdo->prepare("
                        DELETE FROM client_transactions 
                        WHERE client_id = ? AND reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ?
                    ");
                    $deleteOldClientTransactionStmt->execute([$currentData['sold_to'], $booking_id, $tenant_id]);
                }
            }
            
            // Add new transaction to new client if new client is Regular
            if ($isRegularClient) {
                if ($supplier_currency == 'USD') {
                    $updateNewClientStmt = $pdo->prepare("
                        UPDATE clients 
                        SET usd_balance = usd_balance - ? 
                        WHERE id = ? AND tenant_id = ?
                    ");
                } else {
                    $updateNewClientStmt = $pdo->prepare("
                        UPDATE clients 
                        SET afs_balance = afs_balance - ? 
                        WHERE id = ? AND tenant_id = ?
                    ");
                }
                $updateNewClientStmt->execute([$sold_price, $soldTo, $tenant_id]);

                // Get the updated balance after the update
                $getNewBalanceStmt = $pdo->prepare("
                    SELECT " . ($supplier_currency == 'USD' ? 'usd_balance' : 'afs_balance') . " as current_balance 
                    FROM clients 
                    WHERE id = ? AND tenant_id = ?
                ");
                $getNewBalanceStmt->execute([$soldTo, $tenant_id]);
                $newBalance = $getNewBalanceStmt->fetchColumn();
                
                // Create new transaction record for new client
                $insertNewClientTransactionStmt = $pdo->prepare("
                    INSERT INTO client_transactions (client_id, reference_id, type, amount, currency, description, balance, transaction_of, tenant_id)
                    VALUES (?, ?, 'debit', ?, ?, ?, ?, 'umrah', ?)
                ");
                $insertNewClientTransactionStmt->execute([
                    $soldTo, 
                    $booking_id, 
                    $sold_price, 
                    $supplier_currency,
                    "Sale for member: $name (Passport: $passport_number)",
                    $newBalance,
                    $tenant_id
                ]);
            }
        } else {
            // Same client, just adjust the balance by the difference if client is Regular
            if ($isRegularClient && $clientPriceAdjustment != 0) {
                // Determine which balance field to update based on currency
                $balanceField = $supplier_currency == 'USD' ? 'usd_balance' : 'afs_balance';
                
                // Get current client balance before update
                $getCurrentClientBalanceStmt = $pdo->prepare("
                    SELECT $balanceField FROM clients WHERE id = ? AND tenant_id = ?
                ");
                $getCurrentClientBalanceStmt->execute([$soldTo, $tenant_id]);
                $currentClientBalance = $getCurrentClientBalanceStmt->fetchColumn();
                
                // Calculate new balance
                $newClientBalance = 0;
                if ($clientPriceAdjustment > 0) {
                    // Sold price decreased, client owes less, balance decreases
                    $updateClientStmt = $pdo->prepare("
                        UPDATE clients 
                        SET $balanceField = $balanceField - ? 
                        WHERE id = ? AND tenant_id = ?
                    ");
                    $newClientBalance = $currentClientBalance - $clientPriceAdjustment;
                } else {
                    // Sold price increased, client owes more, balance increases
                    $updateClientStmt = $pdo->prepare("
                        UPDATE clients 
                        SET $balanceField = $balanceField + ? 
                        WHERE id = ? AND tenant_id = ?
                    ");
                    // Make the difference positive for the query
                    $clientPriceAdjustment = abs($clientPriceAdjustment);
                    $newClientBalance = $currentClientBalance + $clientPriceAdjustment;
                }
                
                $updateClientStmt->execute([$clientPriceAdjustment, $soldTo, $tenant_id]);
                
                // Check if transaction record exists for this client
                $checkClientTransactionStmt = $pdo->prepare("
                    SELECT id, created_at, balance, amount FROM client_transactions 
                    WHERE client_id = ? AND reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ?
                ");
                $checkClientTransactionStmt->execute([$soldTo, $booking_id, $tenant_id]);
                $clientTransactionExists = $checkClientTransactionStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($clientTransactionExists) {
                    $clientTransactionId = $clientTransactionExists['id'];
                    $clientTransactionDate = $clientTransactionExists['created_at'];
                    $currentTransactionBalance = floatval($clientTransactionExists['balance']);
                    $currentTransactionAmount = abs(floatval($clientTransactionExists['amount'])); // Ensure positive value
                    
                    // Calculate the difference between the new sold amount and the current transaction amount
                    $amountDifference = $sold_price - $currentTransactionAmount;
                    
                    // Calculate the new balance for this transaction
                    // If amount increased, balance should decrease by the difference
                    // If amount decreased, balance should increase by the difference
                    $newTransactionBalance = $currentTransactionBalance - $amountDifference;
                    
                    // Update amount field to new sold price (as negative value for debit)
                    $negativeAmount = -1 * abs($sold_price);
                    $updateClientAmountStmt = $pdo->prepare("
                        UPDATE client_transactions 
                        SET amount = ?
                        WHERE id = ? AND tenant_id = ?
                    ");
                    $updateClientAmountStmt->execute([$negativeAmount, $clientTransactionId, $tenant_id]);
                    
                    // Update existing transaction record with adjusted balance
                    $updateClientTransactionStmt = $pdo->prepare("
                        UPDATE client_transactions 
                        SET balance = ?,
                            description = CONCAT('Updated: ', description)
                        WHERE id = ? AND tenant_id = ?
                    ");
                    $updateClientTransactionStmt->execute([$newTransactionBalance, $clientTransactionId, $tenant_id]);
                    
                    // Update all subsequent transactions' balances
                    // If amount increased, decrease subsequent balances
                    // If amount decreased, increase subsequent balances
                    if ($amountDifference > 0) {
                        // Amount increased, decrease subsequent balances
                        $updateSubsequentClientStmt = $pdo->prepare("
                            UPDATE client_transactions 
                            SET balance = balance - ? 
                            WHERE client_id = ? AND tenant_id = ?
                            AND created_at > ? 
                            AND currency = ?
                        ");
                    } else {
                        // Amount decreased, increase subsequent balances
                        $updateSubsequentClientStmt = $pdo->prepare("
                            UPDATE client_transactions 
                            SET balance = balance + ? 
                            WHERE client_id = ? AND tenant_id = ?
                            AND created_at > ? 
                            AND currency = ?
                        ");
                    }
                    
                    $absAmountDifference = abs($amountDifference);
                    $updateSubsequentClientStmt->execute([$absAmountDifference, $soldTo, $tenant_id, $clientTransactionDate, $supplier_currency]);
                }
            }
        }
    }
    
    // Update family totals
    $updateFamilyStmt = $pdo->prepare("
        UPDATE families f
        SET 
            f.total_members = (SELECT COUNT(*) FROM umrah_bookings WHERE family_id = f.family_id),
            f.total_price = (SELECT SUM(sold_price) FROM umrah_bookings WHERE family_id = f.family_id),
            f.total_paid = (SELECT SUM(paid) FROM umrah_bookings WHERE family_id = f.family_id),
            f.total_paid_to_bank = (SELECT SUM(received_bank_payment) FROM umrah_bookings WHERE family_id = f.family_id),
            f.total_due = (SELECT SUM(due) FROM umrah_bookings WHERE family_id = f.family_id)
        WHERE f.family_id = ? AND f.tenant_id = ?
    ");
    $updateFamilyStmt->execute([$family_id, $tenant_id]);
    
    // Add activity log
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $userIp = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    // Prepare old values as JSON
    $oldValues = json_encode($currentData);
    
    // Prepare new values as JSON
    $newValues = json_encode([
        'booking_id' => $booking_id,
        'family_id' => $family_id,
        'supplier' => $supplier,
        'sold_to' => $soldTo,
        'paid_to' => $paidTo,
        'entry_date' => $entry_date,
        'name' => $name,
        'dob' => $dob,
        'passport_number' => $passport_number,
        'id_type' => $id_type,
        'flight_date' => $flight_date,
        'return_date' => $return_date,
        'duration' => $duration,
        'room_type' => $room_type,
        'currency' => $supplier_currency,
        'price' => $price,
        'sold_price' => $sold_price,
        'profit' => $profit,
        'received_bank_payment' => $received_bank_payment,
        'bank_receipt_number' => $bank_receipt_number,
        'paid' => $paid,
        'due' => $due,
        'gender' => $gender,

        'passport_expiry' => $passport_expiry,

    ]);
    
    // Insert activity log record
    $logStmt = $pdo->prepare("
        INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id)
        VALUES (?, ?, ?, 'update_umrah_member', 'umrah_bookings', ?, ?, ?, NOW(), ?)
    ");
    $logStmt->execute([$userId, $userIp, $userAgent, $booking_id, $oldValues, $newValues, $tenant_id]);
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Member updated successfully']);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    // Log the error
    error_log("Database Error in update_umrah_member.php: " . $e->getMessage());
    
    // Return error message
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 