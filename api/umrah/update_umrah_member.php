<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Database connection
require_once('../../includes/db.php');

// Check if form data is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Validate required fields.
// total_sold_price is never posted: the edit modal sends grand_sold_price,
// which overrides per-service sold sums (see below).
$requiredFields = [
    'booking_id', 'soldTo', 'paidTo', 'entry_date',
    'name', 'duration', 'room_type', 'total_base_price', 'total_profit'
];

// Check if suppliers data is provided (multi-supplier support)
$suppliers = isset($_POST['edit_services']) ? $_POST['edit_services'] : (isset($_POST['suppliers']) ? json_decode($_POST['suppliers'], true) : null);

// An empty array is allowed for unpriced package bookings where the grand sold price carries the price
if (!is_array($suppliers)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Either suppliers array or supplier field is required']);
    exit();
}

    foreach ($suppliers as $index => &$supplier) {
        if (!isset($supplier['service_type']) || !isset($supplier['supplier_id']) ||
            !isset($supplier['base_price']) || !isset($supplier['sold_price']) ||
            !isset($supplier['profit']) || !isset($supplier['currency'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "Supplier at index $index is missing required fields"]);
            exit();
        }

        // Validate each supplier's data
        // Empty supplier_id is allowed for unpriced package skeleton rows (NULL supplier)
        $supplier['supplier_id'] = (!isset($supplier['supplier_id']) || $supplier['supplier_id'] === '' || $supplier['supplier_id'] === null)
            ? null
            : DbSecurity::validateInput($supplier['supplier_id'], 'int', ['min' => 1]);
        $supplier['base_price'] = DbSecurity::validateInput($supplier['base_price'], 'float', ['min' => 0]);
        $supplier['sold_price'] = DbSecurity::validateInput($supplier['sold_price'], 'float', ['min' => 0]);
        $supplier['profit'] = DbSecurity::validateInput($supplier['profit'], 'float');
        $supplier['currency'] = DbSecurity::validateInput($supplier['currency'], 'string', ['maxlength' => 10]);

        // Validate service_type (single service or compound like 'ticket+visa')
        $validServiceTypes = ['all', 'ticket', 'visa', 'hotel', 'transport'];
        $isValidServiceType = in_array($supplier['service_type'], $validServiceTypes)
            || (bool)preg_match('/^(ticket|visa|hotel|transport)(\+(ticket|visa|hotel|transport))*$/', $supplier['service_type']);
        if (!$isValidServiceType) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "Invalid service_type for supplier at index $index"]);
            exit();
        }

        // Price-engine snapshot fields (optional; kept for fulfillment + collapsed display)
        $supplier['service_id'] = isset($supplier['service_id']) ? DbSecurity::validateInput($supplier['service_id'], 'int') : null;
        $supplier['pricing_unit'] = isset($supplier['pricing_unit']) ? DbSecurity::validateInput($supplier['pricing_unit'], 'string', ['maxlength' => 50]) : null;
        $supplier['quantity'] = isset($supplier['quantity']) ? max(1, (int)DbSecurity::validateInput($supplier['quantity'], 'int')) : 1;
        $supplier['is_optional'] = isset($supplier['is_optional']) ? (int)$supplier['is_optional'] : 0;
        $supplier['hotel_id'] = isset($supplier['hotel_id']) ? DbSecurity::validateInput($supplier['hotel_id'], 'int') : null;
        $supplier['room_type_id'] = isset($supplier['room_type_id']) ? DbSecurity::validateInput($supplier['room_type_id'], 'int') : null;
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

// Validate paid - default to 0 if not provided
$paid = isset($_POST['paid']) ? DbSecurity::validateInput($_POST['paid'], 'float', ['min' => 0]) : 0;

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

// Empty date fields must be NULL, not '' (MySQL DATE columns reject '')
$dob = trim((string)$dob) === '' ? null : $dob;

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
$relation = isset($_POST['relation']) ? DbSecurity::validateInput($_POST['relation'], 'string', ['maxlength' => 255]) : '';
$g_name = isset($_POST['g_name']) ? DbSecurity::validateInput($_POST['g_name'], 'string', ['maxlength' => 255]) : '';
$father_name = isset($_POST['father_name']) ? DbSecurity::validateInput($_POST['father_name'], 'string', ['maxlength' => 255]) : '';
$discount = isset($_POST['discount']) ? DbSecurity::validateInput($_POST['discount'], 'float', ['min' => 0]) : null;

// Grand sold price agreed with the customer (overrides per-service sold sums when set)
$grand_sold_price = isset($_POST['grand_sold_price']) ? DbSecurity::validateInput($_POST['grand_sold_price'], 'float', ['min' => 0]) : 0;

$sale_currency = isset($_POST['sale_currency']) ? DbSecurity::validateInput($_POST['sale_currency'], 'string') : 'USD';
if (!in_array(strtoupper($sale_currency), ['USD', 'AFS'])) { $sale_currency = 'USD'; }
$exchange_rate = isset($_POST['exchange_rate']) ? (float)$_POST['exchange_rate'] : 1.0;
if ($exchange_rate <= 0) { $exchange_rate = 1.0; }

// Package chip rows submit an empty currency; fall back to the booking's sale currency
foreach ($suppliers as &$curSvc) {
    if (empty($curSvc['currency'])) {
        $curSvc['currency'] = $sale_currency;
    }
}
unset($curSvc);

// Validate passport expiry (must be at least 6 months from today).
// Invalid or past dates are treated as "not provided" — only real
// future expiries are enforced.
if (!empty($passport_expiry)) {
    try {
        $expiryDate = new DateTime($passport_expiry);
    } catch (Exception $e) {
        $expiryDate = null;
    }
    if ($expiryDate !== null && $expiryDate >= (new DateTime())->setTime(0, 0)) {
        $sixMonthsLater = (new DateTime())->modify('+6 months');
        if ($expiryDate < $sixMonthsLater) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Passport must be valid for at least 6 months from today for Umrah visa requirements']);
            exit();
        }
    }
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    

        $tenant_id = $_SESSION['tenant_id'];
        $branch_id = $_SESSION['branch_id'];
    
    
    // First, get the current booking data to calculate balance adjustments
    $stmtCurrentData = $pdo->prepare("
        SELECT sold_to, family_id, paid_to, entry_date, name, dob, passport_number, id_type, flight_date, return_date, duration, room_type, price, sold_price, profit, received_bank_payment, bank_receipt_number, paid, due, discount, status, currency, exchange_rate
        FROM umrah_bookings
        WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmtCurrentData->execute([$booking_id, $tenant_id, $branch_id]);
    $currentData = $stmtCurrentData->fetch(PDO::FETCH_ASSOC);

    // Get current services data for multi-supplier support
    $stmtCurrentServices = $pdo->prepare("
        SELECT id, service_type, supplier_id, base_price, sold_price, profit, currency
        FROM umrah_booking_services
        WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?
        ORDER BY id
    ");
    $stmtCurrentServices->execute([$booking_id, $tenant_id, $branch_id]);
    $currentServices = $stmtCurrentServices->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$currentData) {
        throw new PDOException("Booking not found");
    }
    
    // Check if booking status is active - only update supplier/client balances if active
    $isActiveStatus = ($currentData['status'] === 'active');
    
    // Get client type
    $stmtClientType = $pdo->prepare("SELECT client_type FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmtClientType->execute([$soldTo, $tenant_id, $branch_id]);
    $clientData = $stmtClientType->fetch(PDO::FETCH_ASSOC);
    $isRegularClient = ($clientData && $clientData['client_type'] === 'regular');
    
    // Get old client type if client has changed
    $oldClientIsRegular = false;
    if ($soldTo != $currentData['sold_to']) {
        $stmtOldClientType = $pdo->prepare("SELECT client_type FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmtOldClientType->execute([$currentData['sold_to'], $tenant_id, $branch_id]);
        $oldClientData = $stmtOldClientType->fetch(PDO::FETCH_ASSOC);
        $oldClientIsRegular = ($oldClientData && $oldClientData['client_type'] === 'regular');
    }
    
    // Calculate totals from new suppliers (converted to sale currency)
    $totalBasePrice = 0;
    $totalSoldPrice = 0;
    $totalProfit = 0;
    foreach ($suppliers as &$svc) {
        $svcCurrency = strtoupper(trim((string)($svc['currency'] ?? '')));
        $svc['base_in_sale'] = (empty($svcCurrency) || $svcCurrency === strtoupper($sale_currency)) ? (float)$svc['base_price'] : ((float)$svc['base_price'] / $exchange_rate);
        $svcSold = (float)$svc['sold_price'];
        $svc['profit'] = $svcSold - $svc['base_in_sale'];
        $totalBasePrice += $svc['base_in_sale'];
        $totalSoldPrice += $svcSold;
        $totalProfit += $svc['profit'];
    }
    unset($svc);

    // Grand agreed price overrides per-service sold sums
    if ($grand_sold_price > 0) {
        $totalSoldPrice = $grand_sold_price;
        $totalProfit = $totalSoldPrice - $totalBasePrice;
    }

    // Calculate totals from current services (not from main booking record)
    $currentCurrency = strtoupper(trim((string)($currentData['currency'] ?? 'USD')));
    $currentRate = (float)($currentData['exchange_rate'] ?? 1);
    if ($currentRate <= 0) { $currentRate = 1; }
    $currentTotalBasePrice = 0;
    foreach ($currentServices as $cs) {
        $csCur = strtoupper(trim((string)($cs['currency'] ?? '')));
        $currentTotalBasePrice += (empty($csCur) || $csCur === $currentCurrency) ? (float)$cs['base_price'] : ((float)$cs['base_price'] / $currentRate);
    }
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
            exchange_rate = ?,
            price = ?,
            sold_price = ?,
            profit = ?,
            paid = ?,
            gender = ?,
            passport_expiry = ?,
            remarks = ?,
            relation = ?,
            gfname = ?,
            fname = ?,
            discount = ?,
            updated_at = NOW()
        WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?
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
        $sale_currency,
        $exchange_rate,
        $totalBasePrice,
        $totalSoldPrice,
        $totalProfit,
        $paid,
        $gender,
        $passport_expiry,
        $remarks,
        $relation,
        $g_name,
        $father_name,
        $discount,
        $booking_id,
        $tenant_id,
        $branch_id
    ]);

    // Handle supplier balance updates - IMPROVED LOGIC (only if status is active)
    if ($isActiveStatus) {
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
                // Service removed - get supplier type for balance adjustment
                $stmtSupplierType = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmtSupplierType->execute([$currentService['supplier_id'], $tenant_id, $branch_id]);
                $supplierTypeData = $stmtSupplierType->fetch(PDO::FETCH_ASSOC);

                // Get transaction details (needed for both Internal and External)
                $getTransactionStmt = $pdo->prepare("
                    SELECT id, transaction_date, amount, balance
                    FROM supplier_transactions
                    WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                    AND remarks LIKE CONCAT('%', ?, '%') AND tenant_id = ? AND branch_id = ?
                    LIMIT 1
                ");
                $getTransactionStmt->execute([
                    $currentService['supplier_id'],
                    $booking_id,
                    $currentService['service_type'],
                    $tenant_id,
                    $branch_id
                ]);
                $transactionData = $getTransactionStmt->fetch(PDO::FETCH_ASSOC);

                // Delete transaction record for BOTH Internal and External suppliers
                if ($transactionData) {
                    // Update all subsequent transactions' balances (for both Internal and External)
                    $updateSubsequentStmt = $pdo->prepare("
                        UPDATE supplier_transactions
                        SET balance = balance + ?
                        WHERE supplier_id = ?
                        AND id > ?
                        AND id != ? AND tenant_id = ? AND branch_id = ?
                    ");
                    $updateSubsequentStmt->execute([
                        $currentService['base_price'],
                        $currentService['supplier_id'],
                        $transactionData['id'],
                        $transactionData['id'],
                        $tenant_id,
                        $branch_id
                    ]);

                    // Delete the transaction record (for both Internal and External)
                    $deleteTransactionStmt = $pdo->prepare("
                        DELETE FROM supplier_transactions
                        WHERE id = ? AND tenant_id = ? AND branch_id = ?
                    ");
                    $deleteTransactionStmt->execute([$transactionData['id'], $tenant_id, $branch_id]);
                }

                // For External suppliers: Reverse the balance (not for Internal)
                if ($supplierTypeData && $supplierTypeData['supplier_type'] === 'External') {
                    $updateSupplierStmt = $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    $updateSupplierStmt->execute([$currentService['base_price'], $currentService['supplier_id'], $tenant_id, $branch_id]);
                }
            }
        }

        // Process changed services
        foreach ($currentSupplierMap as $key => $currentService) {
            if (isset($newSupplierMap[$key]) && $newSupplierMap[$key]['base_price'] != $currentService['base_price']) {
                // Price changed - adjust the difference
                $priceDiff = $newSupplierMap[$key]['base_price'] - $currentService['base_price'];

                $stmtSupplierType = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmtSupplierType->execute([$currentService['supplier_id'], $tenant_id, $branch_id]);
                $supplierTypeData = $stmtSupplierType->fetch(PDO::FETCH_ASSOC);

                // Find and update the transaction record (for both Internal and External)
                $getTransactionStmt = $pdo->prepare("
                    SELECT id, transaction_date, balance
                    FROM supplier_transactions
                    WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah'
                    AND remarks LIKE CONCAT('%', ?, '%') AND tenant_id = ? AND branch_id = ?
                    LIMIT 1
                ");
                $getTransactionStmt->execute([
                    $currentService['supplier_id'],
                    $booking_id,
                    $currentService['service_type'],
                    $tenant_id,
                    $branch_id
                ]);
                $transactionData = $getTransactionStmt->fetch(PDO::FETCH_ASSOC);

                if ($transactionData) {
                    $newTransactionBalance = $transactionData['balance'] - $priceDiff;

                    // Update transaction amount and balance
                    $updateTransactionStmt = $pdo->prepare("
                        UPDATE supplier_transactions
                        SET amount = ?, balance = ?, remarks = CONCAT('Updated: ', remarks)
                        WHERE id = ? AND tenant_id = ? AND branch_id = ?
                    ");
                    $updateTransactionStmt->execute([
                        $newSupplierMap[$key]['base_price'],
                        $newTransactionBalance,
                        $transactionData['id'],
                        $tenant_id,
                        $branch_id
                    ]);

                    // Update all subsequent transactions' balances (for both Internal and External)
                    $updateSubsequentStmt = $pdo->prepare("
                        UPDATE supplier_transactions
                        SET balance = balance - ?
                        WHERE supplier_id = ?
                        AND id > ? AND tenant_id = ? AND branch_id = ?
                    ");
                    $updateSubsequentStmt->execute([
                        $priceDiff,
                        $currentService['supplier_id'],
                        $transactionData['id'],
                        $tenant_id,
                        $branch_id
                    ]);
                }

                // For External suppliers: Update the balance (not for Internal)
                if ($supplierTypeData && $supplierTypeData['supplier_type'] === 'External') {
                    $updateSupplierStmt = $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    $updateSupplierStmt->execute([$priceDiff, $currentService['supplier_id'], $tenant_id, $branch_id]);
                }
            }
        }

        // Process new services
        foreach ($newSupplierMap as $key => $newService) {
            if (!isset($currentSupplierMap[$key])) {
                // New service added
                $stmtSupplierType = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmtSupplierType->execute([$newService['supplier_id'], $tenant_id, $branch_id]);
                $supplierTypeData = $stmtSupplierType->fetch(PDO::FETCH_ASSOC);

                // For External suppliers: Update balance and create transaction
                if ($supplierTypeData && $supplierTypeData['supplier_type'] === 'External') {
                    // Update supplier balance
                    $updateSupplierStmt = $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    $updateSupplierStmt->execute([$newService['base_price'], $newService['supplier_id'], $tenant_id, $branch_id]);

                    // Get updated balance
                    $getBalanceStmt = $pdo->prepare("SELECT balance FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    $getBalanceStmt->execute([$newService['supplier_id'], $tenant_id, $branch_id]);
                    $newBalance = $getBalanceStmt->fetchColumn();

                    // Create transaction record
                    $insertTransactionStmt = $pdo->prepare("
                        INSERT INTO supplier_transactions (tenant_id, branch_id, supplier_id, reference_id, transaction_type, amount, remarks, balance, transaction_of, receipt)
                        VALUES (?, ?, ?, ?, 'Debit', ?, ?, ?, 'umrah', ?)
                    ");
                    $insertTransactionStmt->execute([
                        $tenant_id,
                        $branch_id,
                        $newService['supplier_id'],
                        $booking_id,
                        $newService['base_price'],
                        "Purchase for {$newService['service_type']}: $name (Passport: $passport_number)",
                        $newBalance,
                        ''
                    ]);
                } else {
                    // For Internal suppliers: Create transaction record with 0 balance (balance not tracked)
                    $insertTransactionStmt = $pdo->prepare("
                        INSERT INTO supplier_transactions (tenant_id, branch_id, supplier_id, reference_id, transaction_type, amount, remarks, balance, transaction_of, receipt)
                        VALUES (?, ?, ?, ?, 'Debit', ?, ?, 0, 'umrah', ?)
                    ");
                    $insertTransactionStmt->execute([
                        $tenant_id,
                        $branch_id,
                        $newService['supplier_id'],
                        $booking_id,
                        $newService['base_price'],
                        "Purchase for {$newService['service_type']}: $name (Passport: $passport_number)",
                        ''
                    ]);
                }
            }
        }
    }

    // Update or replace services in umrah_booking_services table.
    // Capture the old rows first: umrah_fulfillments has ON DELETE CASCADE
    // from umrah_booking_services, so a plain delete would silently destroy
    // all flight/hotel/visa/transport fulfillment data. The fulfillments and
    // statuses are migrated to the replacement rows after the re-insert.
    $oldServices = [];
    $oldSvcStmt = $pdo->prepare("SELECT id, service_type, status FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
    $oldSvcStmt->execute([$booking_id, $tenant_id, $branch_id]);
    $oldServices = $oldSvcStmt->fetchAll(PDO::FETCH_ASSOC);

    $deleteServicesStmt = $pdo->prepare("DELETE FROM umrah_booking_services WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
    $deleteServicesStmt->execute([$booking_id, $tenant_id, $branch_id]);

    // Insert new services
    $insertServiceStmt = $pdo->prepare("
        INSERT INTO umrah_booking_services (tenant_id, branch_id, booking_id, service_type, supplier_id, base_price, sold_price, profit, currency, service_id, pricing_unit, quantity, is_optional, price_snapshot)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $newServiceIds = [];

    foreach ($suppliers as $service) {
        $snapshot = null;
        if (!empty($service['service_id'])) {
            $snapshot = json_encode([
                'service_id'      => $service['service_id'],
                'package_quantity'=> $service['quantity'],
                'pricing_unit'    => $service['pricing_unit'],
                'is_optional'     => $service['is_optional'],
                'hotel_id'        => $service['hotel_id'],
                'room_type_id'    => $service['room_type_id'],
                'currency'        => $service['currency'],
                'base_price'      => (float)$service['base_price'],
                'sold_price'      => (float)$service['sold_price'],
                'profit'          => (float)$service['profit'],
                'sale_currency'   => $sale_currency,
                'sale_exchange_rate' => (!empty($service['currency']) && strtoupper($service['currency']) !== strtoupper($sale_currency)) ? $exchange_rate : null
            ], JSON_UNESCAPED_UNICODE);
        }
        $insertServiceStmt->execute([
            $tenant_id,
            $branch_id,
            $booking_id,
            $service['service_type'],
            $service['supplier_id'],
            $service['base_price'],
            $service['sold_price'],
            $service['profit'],
            $service['currency'],
            $service['service_id'],
            $service['pricing_unit'],
            $service['quantity'],
            $service['is_optional'],
            $snapshot
        ]);
        $newServiceIds[] = (int)$pdo->lastInsertId();
    }

    // Migrate fulfillments + statuses from the old rows to their replacements
    $usedOldIds = [];
    $migrateFulfStmt = $pdo->prepare("UPDATE umrah_fulfillments SET booking_service_id = ? WHERE booking_service_id = ? AND tenant_id = ?");
    $restoreStatusStmt = $pdo->prepare("UPDATE umrah_booking_services SET status = ? WHERE id = ? AND tenant_id = ?");
    foreach ($suppliers as $i => $service) {
        if (!isset($newServiceIds[$i])) {
            continue;
        }
        $oldMatch = null;
        foreach ($oldServices as $os) {
            if (in_array((int)$os['id'], $usedOldIds, true)) {
                continue;
            }
            if ($os['service_type'] === $service['service_type']) {
                $oldMatch = $os;
                break;
            }
        }
        if ($oldMatch === null) {
            foreach ($oldServices as $os) {
                if (in_array((int)$os['id'], $usedOldIds, true)) {
                    continue;
                }
                $oldMatch = $os;
                break;
            }
        }
        if ($oldMatch) {
            $usedOldIds[] = (int)$oldMatch['id'];
            $migrateFulfStmt->execute([$newServiceIds[$i], (int)$oldMatch['id'], $tenant_id]);
            $restoreStatusStmt->execute([$oldMatch['status'], $newServiceIds[$i], $tenant_id]);
        }
    }

    // Handle client balance updates - SAME AS SINGLE SUPPLIER VERSION (only if status is active)
    if ($isActiveStatus && ($soldTo != $currentData['sold_to'] || $clientPriceAdjustment != 0)) {
        $clientCurrency = $sale_currency;

        if ($soldTo != $currentData['sold_to']) {
            // Client changed - handle old and new client
            if ($oldClientIsRegular) {
                $oldClientCurrency = $currentCurrency;

                if ($oldClientCurrency == 'USD') {
                    $updateOldClientStmt = $pdo->prepare("UPDATE clients SET usd_balance = usd_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                } else {
                    $updateOldClientStmt = $pdo->prepare("UPDATE clients SET afs_balance = afs_balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                }
                $updateOldClientStmt->execute([$currentTotalSoldPrice, $currentData['sold_to'], $tenant_id, $branch_id]);

                // Handle old client transaction removal
                $checkOldClientTransactionStmt = $pdo->prepare("
                    SELECT id, created_at, amount, currency
                    FROM client_transactions
                    WHERE client_id = ? AND reference_id = ? and transaction_of = 'umrah' AND tenant_id = ? AND branch_id = ?
                    LIMIT 1
                ");
                $checkOldClientTransactionStmt->execute([$currentData['sold_to'], $booking_id, $tenant_id, $branch_id]);
                $oldClientTransaction = $checkOldClientTransactionStmt->fetch(PDO::FETCH_ASSOC);

                if ($oldClientTransaction) {
                    // Update subsequent transactions
                    $updateSubsequentStmt = $pdo->prepare("
                        UPDATE client_transactions
                        SET balance = balance + ?
                        WHERE client_id = ?
                        AND id > ?
                        AND currency = ?
                        AND id != ?
                        AND tenant_id = ? AND branch_id = ?
                    ");
                    $transactionAmount = abs($oldClientTransaction['amount']);
                    $updateSubsequentStmt->execute([
                        $transactionAmount,
                        $currentData['sold_to'],
                        $oldClientTransaction['id'],
                        $oldClientTransaction['currency'],
                        $oldClientTransaction['id'],
                        $tenant_id,
                        $branch_id
                    ]);

                    // Delete old transaction
                    $deleteOldClientTransactionStmt = $pdo->prepare("
                        DELETE FROM client_transactions
                        WHERE id = ? AND tenant_id = ? AND branch_id = ?
                    ");
                    $deleteOldClientTransactionStmt->execute([$oldClientTransaction['id'], $tenant_id, $branch_id]);
                }
            } else {
                // Old client is not regular (agency) - just delete the transaction without updating subsequent ones
                $checkOldClientTransactionStmt = $pdo->prepare("
                    SELECT id
                    FROM client_transactions
                    WHERE client_id = ? AND reference_id = ? and transaction_of = 'umrah' AND tenant_id = ? AND branch_id = ?
                    LIMIT 1
                ");
                $checkOldClientTransactionStmt->execute([$currentData['sold_to'], $booking_id, $tenant_id, $branch_id]);
                $oldClientTransaction = $checkOldClientTransactionStmt->fetch(PDO::FETCH_ASSOC);

                if ($oldClientTransaction) {
                    // Delete old transaction
                    $deleteOldClientTransactionStmt = $pdo->prepare("
                        DELETE FROM client_transactions
                        WHERE id = ? AND tenant_id = ? AND branch_id = ?
                    ");
                    $deleteOldClientTransactionStmt->execute([$oldClientTransaction['id'], $tenant_id, $branch_id]);
                }
            }

            // Add transaction to new client
            if ($isRegularClient) {
                // For regular clients: update balance and create transaction
                if ($clientCurrency == 'USD') {
                    $updateNewClientStmt = $pdo->prepare("UPDATE clients SET usd_balance = usd_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                } else {
                    $updateNewClientStmt = $pdo->prepare("UPDATE clients SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                }
                $updateNewClientStmt->execute([$totalSoldPrice, $soldTo, $tenant_id, $branch_id]);

                // Get updated balance
                $getNewBalanceStmt = $pdo->prepare("
                    SELECT " . ($clientCurrency == 'USD' ? 'usd_balance' : 'afs_balance') . " as current_balance
                    FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?
                ");
                $getNewBalanceStmt->execute([$soldTo, $tenant_id, $branch_id]);
                $newBalance = $getNewBalanceStmt->fetchColumn();

                // Create new transaction
                $insertNewClientTransactionStmt = $pdo->prepare("
                    INSERT INTO client_transactions (client_id, reference_id, type, amount, currency, description, balance, transaction_of, receipt, tenant_id, branch_id)
                    VALUES (?, ?, 'debit', ?, ?, ?, ?, 'umrah', NULL, ?, ?)
                ");
                $insertNewClientTransactionStmt->execute([
                    $soldTo,
                    $booking_id,
                    $totalSoldPrice,
                    $clientCurrency,
                    "Sale for member: $name (Passport: $passport_number)",
                    $newBalance,
                    $tenant_id,
                    $branch_id
                ]);
            } else {
                // For non-regular clients: only create transaction (no balance update)
                $getNewBalanceStmt = $pdo->prepare("
                    SELECT " . ($clientCurrency == 'USD' ? 'usd_balance' : 'afs_balance') . " as current_balance
                    FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?
                ");
                $getNewBalanceStmt->execute([$soldTo, $tenant_id, $branch_id]);
                $newBalance = $getNewBalanceStmt->fetchColumn();

                // Create new transaction
                $insertNewClientTransactionStmt = $pdo->prepare("
                    INSERT INTO client_transactions (client_id, reference_id, type, amount, currency, description, balance, transaction_of, receipt, tenant_id, branch_id)
                    VALUES (?, ?, 'debit', ?, ?, ?, ?, 'umrah', NULL, ?, ?)
                ");
                $insertNewClientTransactionStmt->execute([
                    $soldTo,
                    $booking_id,
                    $totalSoldPrice,
                    $clientCurrency,
                    "Sale for member: $name (Passport: $passport_number)",
                    $newBalance,
                    $tenant_id,
                    $branch_id
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
                        $updateClientStmt = $pdo->prepare("UPDATE clients SET $balanceField = $balanceField - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    } else {
                        $updateClientStmt = $pdo->prepare("UPDATE clients SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                        $clientPriceAdjustment = abs($clientPriceAdjustment);
                    }
                    $updateClientStmt->execute([$clientPriceAdjustment, $soldTo, $tenant_id, $branch_id]);
                }

                // Update client transaction for all clients (regular and agency)
                $checkClientTransactionStmt = $pdo->prepare("
                    SELECT id, created_at, balance, amount FROM client_transactions
                    WHERE client_id = ? AND reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ? AND branch_id = ?
                    LIMIT 1
                ");
                $checkClientTransactionStmt->execute([$soldTo, $booking_id, $tenant_id, $branch_id]);
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
                        WHERE id = ? AND tenant_id = ? AND branch_id = ?
                    ");
                    $negativeAmount = -1 * abs($totalSoldPrice);
                    $updateClientTransactionStmt->execute([
                        $negativeAmount,
                        $newTransactionBalance,
                        $clientTransaction['id'],
                        $tenant_id,
                        $branch_id
                    ]);

                    // Update subsequent transactions only for regular clients
                    if ($isRegularClient) {
                        if ($amountDifference > 0) {
                            $updateSubsequentClientStmt = $pdo->prepare("
                                UPDATE client_transactions
                                SET balance = balance - ?
                                WHERE client_id = ?
                                AND id > ?
                                AND currency = ?
                                AND tenant_id = ? AND branch_id = ?
                            ");
                        } else {
                            $updateSubsequentClientStmt = $pdo->prepare("
                                UPDATE client_transactions
                                SET balance = balance + ?
                                WHERE client_id = ?
                                AND id > ?
                                AND currency = ?
                                AND tenant_id = ? AND branch_id = ?
                            ");
                        }

                        $absAmountDifference = abs($amountDifference);
                        $updateSubsequentClientStmt->execute([
                            $absAmountDifference,
                            $soldTo,
                            $clientTransaction['id'],
                            $clientCurrency,
                            $tenant_id,
                            $branch_id
                        ]);
                    }
                }
            }
        }
    }
    
    // Update due amount: due = sold_price - paid
    $updateDueStmt = $pdo->prepare("UPDATE umrah_bookings SET due = sold_price - paid WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
    $updateDueStmt->execute([$booking_id, $tenant_id, $branch_id]);

    // Update family totals for active members
    $family_id = $currentData['family_id'];
    // Only update family totals if booking is active
    if ($currentData['status'] === 'active') {
        $updateFamilyStmt = $pdo->prepare("
            UPDATE families f
            SET
                f.total_members = (SELECT COUNT(*) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_price = (SELECT SUM(COALESCE(sold_price, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_paid = (SELECT SUM(COALESCE(paid, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_paid_to_bank = (SELECT SUM(COALESCE(received_bank_payment, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_due = (SELECT SUM(COALESCE(due, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active')
            WHERE f.family_id = ? AND f.tenant_id = ? AND f.branch_id = ?
        ");
        $updateFamilyStmt->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $family_id, $tenant_id, $branch_id]);
    }
    
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
        INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id, branch_id)
        VALUES (?, ?, ?, 'update_umrah_member', 'umrah_bookings', ?, ?, ?, NOW(), ?, ?)
    ");
    $logStmt->execute([$userId, $userIp, $userAgent, $booking_id, $oldValues, $newValues, $tenant_id, $branch_id]);
    
    // Commit transaction
    $pdo->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Member updated successfully']);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>