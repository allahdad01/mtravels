<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

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
    'booking_id', 'soldTo', 'paidTo', 'entry_date',
    'name', 'dob', 'passport_number', 'id_type',
    'duration', 'room_type','total_base_price', 'total_sold_price', 'total_profit'
];

// Check if suppliers data is provided (multi-supplier support)
$suppliers = isset($_POST['edit_services']) ? $_POST['edit_services'] : (isset($_POST['suppliers']) ? json_decode($_POST['suppliers'], true) : null);

// Validate that either suppliers array or single supplier is provided
if (!$suppliers) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Either suppliers array or supplier field is required']);
    exit();
}



// Validate suppliers array if provided
if ($suppliers) {
    if (!is_array($suppliers) || empty($suppliers)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Suppliers must be a non-empty array']);
        exit();
    }

    foreach ($suppliers as $index => $supplier) {
        if (!isset($supplier['service_type']) || !isset($supplier['supplier_id']) ||
            !isset($supplier['base_price']) || !isset($supplier['sold_price']) ||
            !isset($supplier['profit']) || !isset($supplier['currency'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "Supplier at index $index is missing required fields"]);
            exit();
        }

        // Validate each supplier's data
        $supplier['supplier_id'] = DbSecurity::validateInput($supplier['supplier_id'], 'int', ['min' => 1]);
        $supplier['base_price'] = DbSecurity::validateInput($supplier['base_price'], 'float', ['min' => 0]);
        $supplier['sold_price'] = DbSecurity::validateInput($supplier['sold_price'], 'float', ['min' => 0]);
        $supplier['profit'] = DbSecurity::validateInput($supplier['profit'], 'float', ['min' => 0]);
        $supplier['currency'] = DbSecurity::validateInput($supplier['currency'], 'string', ['maxlength' => 10]);

        // Validate service_type
        $validServiceTypes = ['all', 'ticket', 'visa', 'hotel', 'transport'];
        if (!in_array($supplier['service_type'], $validServiceTypes)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "Invalid service_type for supplier at index $index"]);
            exit();
        }
    }
}

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
$total_profit = isset($_POST['total_profit']) ? DbSecurity::validateInput($_POST['total_profit'], 'float', ['min' => 0]) : null;

// Validate sold_price
$total_sold_price = isset($_POST['total_sold_price']) ? DbSecurity::validateInput($_POST['total_sold_price'], 'float', ['min' => 0]) : null;

// Validate price
$total_base_price = isset($_POST['total_base_price']) ? DbSecurity::validateInput($_POST['total_base_price'], 'float', ['min' => 0]) : null;

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
    
    // Get tenant_id - you might need to adjust this logic based on your system
    $tenant_id = 1; // Default value
    
    if (isset($_SESSION['tenant_id'])) {
        $tenant_id = $_SESSION['tenant_id'];
    }
    
    // First, get the current booking data to calculate balance adjustments
    $stmtCurrentData = $pdo->prepare("
        SELECT sold_to, family_id, paid_to, entry_date, name, dob, passport_number, id_type, flight_date, return_date, duration, room_type, price, sold_price, profit, received_bank_payment, bank_receipt_number, paid, due, discount
        FROM umrah_bookings
        WHERE booking_id = ?
    ");
    $stmtCurrentData->execute([$booking_id]);
    $currentData = $stmtCurrentData->fetch(PDO::FETCH_ASSOC);

    // Get current services data for multi-supplier support
    $stmtCurrentServices = $pdo->prepare("
        SELECT id, service_type, supplier_id, base_price, sold_price, profit, currency
        FROM umrah_booking_services
        WHERE booking_id = ?
        ORDER BY id
    ");
    $stmtCurrentServices->execute([$booking_id]);
    $currentServices = $stmtCurrentServices->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$currentData) {
        throw new PDOException("Booking not found");
    }
    
    // Get client type
    $stmtClientType = $pdo->prepare("SELECT client_type FROM clients WHERE id = ?");
    $stmtClientType->execute([$soldTo]);
    $clientData = $stmtClientType->fetch(PDO::FETCH_ASSOC);
    $isRegularClient = ($clientData && $clientData['client_type'] === 'regular');
    
    // Get old client type if client has changed
    $oldClientIsRegular = false;
    if ($soldTo != $currentData['sold_to']) {
        $stmtOldClientType = $pdo->prepare("SELECT client_type FROM clients WHERE id = ?");
        $stmtOldClientType->execute([$currentData['sold_to']]);
        $oldClientData = $stmtOldClientType->fetch(PDO::FETCH_ASSOC);
        $oldClientIsRegular = ($oldClientData && $oldClientData['client_type'] === 'regular');
    }
    
    // Calculate totals from new suppliers
    $totalBasePrice = array_sum(array_column($suppliers, 'base_price'));
    $totalSoldPrice = array_sum(array_column($suppliers, 'sold_price'));
    $totalProfit = array_sum(array_column($suppliers, 'profit'));

    // Calculate totals from current services (not from main booking record)
    $currentTotalBasePrice = array_sum(array_column($currentServices, 'base_price'));
    $currentTotalSoldPrice = array_sum(array_column($currentServices, 'sold_price'));
    
    // Calculate proper adjustments
    $supplierPriceAdjustment = $totalBasePrice - $currentTotalBasePrice;
    $clientPriceAdjustment = $totalSoldPrice - $currentTotalSoldPrice;
    $paidAdjustment = $paid - $currentData['paid'];
    
    // Update umrah_bookings table with totals
    $stmt = $pdo->prepare("
        UPDATE umrah_bookings SET
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
            gender = ?,
            passport_expiry = ?,
            remarks = ?,
            relation = ?,
            gfname = ?,
            fname = ?,
            discount = ?,
            updated_at = NOW()
        WHERE booking_id = ?
    ");

    $stmt->execute([
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
        ($suppliers[0]['currency'] ?? 'USD'), // Use first supplier's currency for main record
        $totalBasePrice,
        $totalSoldPrice,
        $totalProfit,
        $gender,
        $passport_expiry,
        $remarks,
        $relation,
        $g_name,
        $father_name,
        $discount,
        $booking_id
    ]);

    // Handle supplier balance updates - IMPROVED LOGIC
    $currentSupplierMap = [];
    foreach ($currentServices as $service) {
        $key = $service['supplier_id'] . '_' . $service['service_type'];
        $currentSupplierMap[$key] = $service;
    }

    $newSupplierMap = [];
    foreach ($suppliers as $service) {
        $key = $service['supplier_id'] . '_' . $service['service_type'];
        $newSupplierMap[$key] = $service;
    }

    // Process removed services
    foreach ($currentSupplierMap as $key => $currentService) {
        if (!isset($newSupplierMap[$key])) {
            // Service removed - reverse the transaction
            $stmtSupplierType = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ?");
            $stmtSupplierType->execute([$currentService['supplier_id']]);
            $supplierTypeData = $stmtSupplierType->fetch(PDO::FETCH_ASSOC);

            if ($supplierTypeData && $supplierTypeData['supplier_type'] === 'External') {
                // Reverse the balance
                $updateSupplierStmt = $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ?");
                $updateSupplierStmt->execute([$currentService['base_price'], $currentService['supplier_id']]);

                // Get transaction details before deleting
                $getTransactionStmt = $pdo->prepare("
                    SELECT id, transaction_date, amount, balance 
                    FROM supplier_transactions 
                    WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                    AND remarks LIKE CONCAT('%', ?, '%')
                    LIMIT 1
                ");
                $getTransactionStmt->execute([
                    $currentService['supplier_id'], 
                    $booking_id, 
                    $currentService['service_type']
                ]);
                $transactionData = $getTransactionStmt->fetch(PDO::FETCH_ASSOC);

                if ($transactionData) {
                    // Update all subsequent transactions' balances
                    $updateSubsequentStmt = $pdo->prepare("
                        UPDATE supplier_transactions 
                        SET balance = balance + ? 
                        WHERE supplier_id = ? 
                        AND transaction_date > ? 
                        AND id != ?
                    ");
                    $updateSubsequentStmt->execute([
                        $currentService['base_price'],
                        $currentService['supplier_id'],
                        $transactionData['transaction_date'],
                        $transactionData['id']
                    ]);

                    // Delete the transaction record
                    $deleteTransactionStmt = $pdo->prepare("
                        DELETE FROM supplier_transactions
                        WHERE id = ?
                    ");
                    $deleteTransactionStmt->execute([$transactionData['id']]);
                }
            }
        }
    }

    // Process changed services
    foreach ($currentSupplierMap as $key => $currentService) {
        if (isset($newSupplierMap[$key]) && $newSupplierMap[$key]['base_price'] != $currentService['base_price']) {
            // Price changed - adjust the difference
            $priceDiff = $newSupplierMap[$key]['base_price'] - $currentService['base_price'];

            $stmtSupplierType = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ?");
            $stmtSupplierType->execute([$currentService['supplier_id']]);
            $supplierTypeData = $stmtSupplierType->fetch(PDO::FETCH_ASSOC);

            if ($supplierTypeData && $supplierTypeData['supplier_type'] === 'External') {
                // Update supplier balance
                $updateSupplierStmt = $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ?");
                $updateSupplierStmt->execute([$priceDiff, $currentService['supplier_id']]);

                // Find and update the transaction record
                $getTransactionStmt = $pdo->prepare("
                    SELECT id, transaction_date, balance 
                    FROM supplier_transactions 
                    WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                    AND remarks LIKE CONCAT('%', ?, '%')
                    LIMIT 1
                ");
                $getTransactionStmt->execute([
                    $currentService['supplier_id'], 
                    $booking_id, 
                    $currentService['service_type']
                ]);
                $transactionData = $getTransactionStmt->fetch(PDO::FETCH_ASSOC);

                if ($transactionData) {
                    $newTransactionBalance = $transactionData['balance'] - $priceDiff;

                    // Update transaction amount and balance
                    $updateTransactionStmt = $pdo->prepare("
                        UPDATE supplier_transactions
                        SET amount = ?, balance = ?, remarks = CONCAT('Updated: ', remarks)
                        WHERE id = ?
                    ");
                    $updateTransactionStmt->execute([
                        $newSupplierMap[$key]['base_price'],
                        $newTransactionBalance,
                        $transactionData['id']
                    ]);

                    // Update all subsequent transactions' balances
                    $updateSubsequentStmt = $pdo->prepare("
                        UPDATE supplier_transactions 
                        SET balance = balance - ? 
                        WHERE supplier_id = ? 
                        AND transaction_date > ?
                    ");
                    $updateSubsequentStmt->execute([
                        $priceDiff,
                        $currentService['supplier_id'],
                        $transactionData['transaction_date']
                    ]);
                }
            }
        }
    }

    // Process new services
    foreach ($newSupplierMap as $key => $newService) {
        if (!isset($currentSupplierMap[$key])) {
            // New service added
            $stmtSupplierType = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ?");
            $stmtSupplierType->execute([$newService['supplier_id']]);
            $supplierTypeData = $stmtSupplierType->fetch(PDO::FETCH_ASSOC);

            if ($supplierTypeData && $supplierTypeData['supplier_type'] === 'External') {
                // Update supplier balance
                $updateSupplierStmt = $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ?");
                $updateSupplierStmt->execute([$newService['base_price'], $newService['supplier_id']]);

                // Get updated balance
                $getBalanceStmt = $pdo->prepare("SELECT balance FROM suppliers WHERE id = ?");
                $getBalanceStmt->execute([$newService['supplier_id']]);
                $newBalance = $getBalanceStmt->fetchColumn();

                // Create transaction record
                $insertTransactionStmt = $pdo->prepare("
                    INSERT INTO supplier_transactions (tenant_id, supplier_id, reference_id, transaction_type, amount, remarks, balance, transaction_of)
                    VALUES (?, ?, ?, 'Debit', ?, ?, ?, 'umrah')
                ");
                $insertTransactionStmt->execute([
                    $tenant_id,
                    $newService['supplier_id'],
                    $booking_id,
                    $newService['base_price'],
                    "Purchase for {$newService['service_type']}: $name (Passport: $passport_number)",
                    $newBalance
                ]);
            }
        }
    }

    // Update or replace services in umrah_booking_services table
    $deleteServicesStmt = $pdo->prepare("DELETE FROM umrah_booking_services WHERE booking_id = ?");
    $deleteServicesStmt->execute([$booking_id]);

    // Insert new services
    $insertServiceStmt = $pdo->prepare("
        INSERT INTO umrah_booking_services (tenant_id, booking_id, service_type, supplier_id, base_price, sold_price, profit, currency)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($suppliers as $service) {
        $insertServiceStmt->execute([
            $tenant_id,
            $booking_id,
            $service['service_type'],
            $service['supplier_id'],
            $service['base_price'],
            $service['sold_price'],
            $service['profit'],
            $service['currency']
        ]);
    }

    // Handle client balance updates - SAME AS SINGLE SUPPLIER VERSION
    if ($soldTo != $currentData['sold_to'] || $clientPriceAdjustment != 0) {
        $clientCurrency = ($suppliers[0]['currency'] ?? 'USD');

        if ($soldTo != $currentData['sold_to']) {
            // Client changed - handle old and new client
            if ($oldClientIsRegular) {
                $oldClientCurrency = (!empty($currentServices) ? ($currentServices[0]['currency'] ?? 'USD') : 'USD');

                if ($oldClientCurrency == 'USD') {
                    $updateOldClientStmt = $pdo->prepare("UPDATE clients SET usd_balance = usd_balance + ? WHERE id = ?");
                } else {
                    $updateOldClientStmt = $pdo->prepare("UPDATE clients SET afs_balance = afs_balance + ? WHERE id = ?");
                }
                $updateOldClientStmt->execute([$currentTotalSoldPrice, $currentData['sold_to']]);

                // Handle old client transaction removal
                $checkOldClientTransactionStmt = $pdo->prepare("
                    SELECT id, created_at, amount, currency
                    FROM client_transactions
                    WHERE client_id = ? AND reference_id = ? and transaction_of = 'umrah'
                    LIMIT 1
                ");
                $checkOldClientTransactionStmt->execute([$currentData['sold_to'], $booking_id]);
                $oldClientTransaction = $checkOldClientTransactionStmt->fetch(PDO::FETCH_ASSOC);

                if ($oldClientTransaction) {
                    // Update subsequent transactions
                    $updateSubsequentStmt = $pdo->prepare("
                        UPDATE client_transactions
                        SET balance = balance + ?
                        WHERE client_id = ?
                        AND created_at > ?
                        AND currency = ?
                        AND id != ?
                    ");
                    $transactionAmount = abs($oldClientTransaction['amount']);
                    $updateSubsequentStmt->execute([
                        $transactionAmount,
                        $currentData['sold_to'],
                        $oldClientTransaction['created_at'],
                        $oldClientTransaction['currency'],
                        $oldClientTransaction['id']
                    ]);

                    // Delete old transaction
                    $deleteOldClientTransactionStmt = $pdo->prepare("
                        DELETE FROM client_transactions
                        WHERE id = ?
                    ");
                    $deleteOldClientTransactionStmt->execute([$oldClientTransaction['id']]);
                }
            }

            // Add transaction to new client
            if ($isRegularClient) {
                if ($clientCurrency == 'USD') {
                    $updateNewClientStmt = $pdo->prepare("UPDATE clients SET usd_balance = usd_balance - ? WHERE id = ?");
                } else {
                    $updateNewClientStmt = $pdo->prepare("UPDATE clients SET afs_balance = afs_balance - ? WHERE id = ?");
                }
                $updateNewClientStmt->execute([$totalSoldPrice, $soldTo]);

                // Get updated balance
                $getNewBalanceStmt = $pdo->prepare("
                    SELECT " . ($clientCurrency == 'USD' ? 'usd_balance' : 'afs_balance') . " as current_balance
                    FROM clients WHERE id = ?
                ");
                $getNewBalanceStmt->execute([$soldTo]);
                $newBalance = $getNewBalanceStmt->fetchColumn();

                // Create new transaction
                $insertNewClientTransactionStmt = $pdo->prepare("
                    INSERT INTO client_transactions (client_id, reference_id, type, amount, currency, description, balance, transaction_of)
                    VALUES (?, ?, 'debit', ?, ?, ?, ?, 'umrah')
                ");
                $insertNewClientTransactionStmt->execute([
                    $soldTo,
                    $booking_id,
                    $totalSoldPrice,
                    $clientCurrency,
                    "Sale for member: $name (Passport: $passport_number)",
                    $newBalance
                ]);
            }
        } else {
            // Same client, adjust for price difference
            if ($clientPriceAdjustment != 0) {
                // Update client balance only for regular clients
                if ($isRegularClient) {
                    $balanceField = $clientCurrency == 'USD' ? 'usd_balance' : 'afs_balance';

                    // Update client balance
                    if ($clientPriceAdjustment > 0) {
                        $updateClientStmt = $pdo->prepare("UPDATE clients SET $balanceField = $balanceField - ? WHERE id = ?");
                    } else {
                        $updateClientStmt = $pdo->prepare("UPDATE clients SET $balanceField = $balanceField + ? WHERE id = ?");
                        $clientPriceAdjustment = abs($clientPriceAdjustment);
                    }
                    $updateClientStmt->execute([$clientPriceAdjustment, $soldTo]);
                }

                // Update client transaction for all clients (regular and agency)
                $checkClientTransactionStmt = $pdo->prepare("
                    SELECT id, created_at, balance, amount FROM client_transactions
                    WHERE client_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                    LIMIT 1
                ");
                $checkClientTransactionStmt->execute([$soldTo, $booking_id]);
                $clientTransaction = $checkClientTransactionStmt->fetch(PDO::FETCH_ASSOC);

                if ($clientTransaction) {
                    $currentTransactionAmount = abs(floatval($clientTransaction['amount']));
                    $amountDifference = $totalSoldPrice - $currentTransactionAmount;

                    // For regular clients, update balance; for agency, keep balance the same
                    $newTransactionBalance = $isRegularClient ? $clientTransaction['balance'] - $amountDifference : $clientTransaction['balance'];

                    // Update transaction
                    $updateClientTransactionStmt = $pdo->prepare("
                        UPDATE client_transactions
                        SET amount = ?, balance = ?, description = CONCAT('Updated: ', description)
                        WHERE id = ?
                    ");
                    $negativeAmount = -1 * abs($totalSoldPrice);
                    $updateClientTransactionStmt->execute([
                        $negativeAmount,
                        $newTransactionBalance,
                        $clientTransaction['id']
                    ]);

                    // Update subsequent transactions only for regular clients
                    if ($isRegularClient) {
                        if ($amountDifference > 0) {
                            $updateSubsequentClientStmt = $pdo->prepare("
                                UPDATE client_transactions
                                SET balance = balance - ?
                                WHERE client_id = ? AND created_at > ? AND currency = ?
                            ");
                        } else {
                            $updateSubsequentClientStmt = $pdo->prepare("
                                UPDATE client_transactions
                                SET balance = balance + ?
                                WHERE client_id = ? AND created_at > ? AND currency = ?
                            ");
                        }

                        $absAmountDifference = abs($amountDifference);
                        $updateSubsequentClientStmt->execute([
                            $absAmountDifference,
                            $soldTo,
                            $clientTransaction['created_at'],
                            $clientCurrency
                        ]);
                    }
                }
            }
        }
    }
    
    // Update due amount: due = sold_price - paid
    $updateDueStmt = $pdo->prepare("UPDATE umrah_bookings SET due = sold_price - paid WHERE booking_id = ?");
    $updateDueStmt->execute([$booking_id]);

    // Update family totals (same as original)
    $family_id = $currentData['family_id'];
    $updateFamilyStmt = $pdo->prepare("
        UPDATE families f
        SET
            f.total_members = (SELECT COUNT(*) FROM umrah_bookings WHERE family_id = f.family_id),
            f.total_price = (SELECT SUM(COALESCE(sold_price)) FROM umrah_bookings WHERE family_id = f.family_id),
            f.total_paid = (SELECT SUM(paid) FROM umrah_bookings WHERE family_id = f.family_id),
            f.total_paid_to_bank = (SELECT SUM(received_bank_payment) FROM umrah_bookings WHERE family_id = f.family_id),
            f.total_due = (SELECT SUM(due) FROM umrah_bookings WHERE family_id = f.family_id)
        WHERE f.family_id = ?
    ");
    $updateFamilyStmt->execute([$family_id]);
    
    // Activity logging (same as original)
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $userIp = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    $oldValues = json_encode($currentData);
    $newValues = json_encode([
        'booking_id' => $booking_id,
        'family_id' => $family_id,
        'suppliers' => $suppliers,
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
        'total_base_price' => $totalBasePrice,
        'total_sold_price' => $totalSoldPrice,
        'total_profit' => $totalProfit,
        'received_bank_payment' => $received_bank_payment,
        'bank_receipt_number' => $bank_receipt_number,
        'paid' => $paid,
        'due' => $due,
        'gender' => $gender,
        'passport_expiry' => $passport_expiry,
        'remarks' => $remarks,
        'relation' => $relation,
        'g_name' => $g_name,
        'father_name' => $father_name,
        'discount' => $discount
    ]);
    
    $logStmt = $pdo->prepare("
        INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id)
        VALUES (?, ?, ?, 'update_umrah_member', 'umrah_bookings', ?, ?, ?, NOW(), ?)
    ");
    $logStmt->execute([$userId, $userIp, $userAgent, $booking_id, $oldValues, $newValues, $tenant_id]);
    
    // Commit transaction
    $pdo->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Member updated successfully']);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database Error in update_umrah_member.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>