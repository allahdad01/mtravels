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

// Validate required fields
$requiredFields = [
    'booking_id', 'soldTo', 'paidTo', 'entry_date',
    'name', 'duration', 'room_type'
];

foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || $_POST[$field] === '') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
        exit();
    }
}

// Validate booking-level fields
$paid = isset($_POST['paid']) ? DbSecurity::validateInput($_POST['paid'], 'float', ['min' => 0]) : 0;
$total_sold_price = isset($_POST['total_sold_price']) ? DbSecurity::validateInput($_POST['total_sold_price'], 'float', ['min' => 0]) : 0;
$grand_sold_price = isset($_POST['grand_sold_price']) ? DbSecurity::validateInput($_POST['grand_sold_price'], 'float', ['min' => 0]) : 0;
$room_type = DbSecurity::validateInput($_POST['room_type'], 'string', ['maxlength' => 255]);
$duration = DbSecurity::validateInput($_POST['duration'], 'string', ['maxlength' => 255]);
$return_date = isset($_POST['return_date']) ? DbSecurity::validateInput($_POST['return_date'], 'date') : null;
$flight_date = isset($_POST['flight_date']) ? DbSecurity::validateInput($_POST['flight_date'], 'date') : null;
$id_type = isset($_POST['id_type']) ? DbSecurity::validateInput($_POST['id_type'], 'string', ['maxlength' => 255]) : null;
$passport_number = isset($_POST['passport_number']) ? DbSecurity::validateInput($_POST['passport_number'], 'string', ['maxlength' => 255]) : null;
$dob = isset($_POST['dob']) ? DbSecurity::validateInput($_POST['dob'], 'string', ['maxlength' => 255]) : null;
$dob = trim((string)$dob) === '' ? null : $dob;
$name = DbSecurity::validateInput($_POST['name'], 'string', ['maxlength' => 255]);
$entry_date = DbSecurity::validateInput($_POST['entry_date'], 'date');
$paidTo = DbSecurity::validateInput($_POST['paidTo'], 'int', ['min' => 0]);
$soldTo = DbSecurity::validateInput($_POST['soldTo'], 'int', ['min' => 0]);
$booking_id = DbSecurity::validateInput($_POST['booking_id'], 'int', ['min' => 0]);
$remarks = isset($_POST['remarks']) ? DbSecurity::validateInput($_POST['remarks'], 'string', ['maxlength' => 255]) : null;
$gender = isset($_POST['gender']) ? DbSecurity::validateInput($_POST['gender'], 'string', ['maxlength' => 255]) : null;
$passport_expiry = isset($_POST['passport_expiry']) ? DbSecurity::validateInput($_POST['passport_expiry'], 'date') : null;
$relation = isset($_POST['relation']) ? DbSecurity::validateInput($_POST['relation'], 'string', ['maxlength' => 255]) : '';
$g_name = isset($_POST['g_name']) ? DbSecurity::validateInput($_POST['g_name'], 'string', ['maxlength' => 255]) : '';
$father_name = isset($_POST['father_name']) ? DbSecurity::validateInput($_POST['father_name'], 'string', ['maxlength' => 255]) : '';
$discount = isset($_POST['discount']) ? DbSecurity::validateInput($_POST['discount'], 'float', ['min' => 0]) : null;
$received_bank_payment = isset($_POST['received_bank_payment']) ? DbSecurity::validateInput($_POST['received_bank_payment'], 'float', ['min' => 0]) : null;
$bank_receipt_number = isset($_POST['bank_receipt_number']) ? DbSecurity::validateInput($_POST['bank_receipt_number'], 'string', ['maxlength' => 255]) : null;

$sale_currency = isset($_POST['sale_currency']) ? DbSecurity::validateInput($_POST['sale_currency'], 'string') : 'USD';
if (!in_array(strtoupper($sale_currency), ['USD', 'AFS'])) { $sale_currency = 'USD'; }
$exchange_rate = isset($_POST['exchange_rate']) ? (float)$_POST['exchange_rate'] : 1.0;
if ($exchange_rate <= 0) { $exchange_rate = 1.0; }

// Validate passport expiry (must be at least 6 months from today)
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
    $pdo->beginTransaction();

    $tenant_id = $_SESSION['tenant_id'];
    $branch_id = $_SESSION['branch_id'];

    // Get current booking data
    $stmtCurrentData = $pdo->prepare("
        SELECT sold_to, family_id, paid_to, entry_date, name, dob, passport_number,
               id_type, flight_date, return_date, duration, room_type, price,
               sold_price, profit, received_bank_payment, bank_receipt_number,
               paid, due, discount, status, currency, exchange_rate
        FROM umrah_bookings
        WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmtCurrentData->execute([$booking_id, $tenant_id, $branch_id]);
    $currentData = $stmtCurrentData->fetch(PDO::FETCH_ASSOC);

    if (!$currentData) {
        throw new PDOException("Booking not found");
    }

    $isActiveStatus = ($currentData['status'] === 'active');
    $oldSoldPrice = floatval($currentData['sold_price']);
    $currentPaid = floatval($currentData['paid']);

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

    // Determine sold_price: grand_sold_price overrides total_sold_price
    $totalSoldPrice = ($grand_sold_price > 0) ? $grand_sold_price : $total_sold_price;
    $clientPriceAdjustment = $totalSoldPrice - $oldSoldPrice;
    $currentCurrency = strtoupper(trim((string)($currentData['currency'] ?? 'USD')));

    // Update umrah_bookings table
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
            sold_price = ?,
            paid = ?,
            gender = ?,
            passport_expiry = ?,
            remarks = ?,
            relation = ?,
            gfname = ?,
            fname = ?,
            discount = ?,
            received_bank_payment = ?,
            bank_receipt_number = ?,
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
        $totalSoldPrice,
        $paid,
        $gender,
        $passport_expiry,
        $remarks,
        $relation,
        $g_name,
        $father_name,
        $discount,
        $received_bank_payment,
        $bank_receipt_number,
        $booking_id,
        $tenant_id,
        $branch_id
    ]);

    // Update due amount: due = sold_price - paid
    $pdo->prepare("UPDATE umrah_bookings SET due = sold_price - paid WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?")
        ->execute([$booking_id, $tenant_id, $branch_id]);

    // Handle client balance updates (only if status is active and something changed)
    $clientCurrency = $sale_currency;
    if ($isActiveStatus && ($soldTo != $currentData['sold_to'] || $clientPriceAdjustment != 0)) {

        if ($soldTo != $currentData['sold_to']) {
            // Client changed - handle old and new client
            if ($oldClientIsRegular) {
                $oldClientCurrency = $currentCurrency;
                $oldClientBalanceField = $oldClientCurrency == 'USD' ? 'usd_balance' : 'afs_balance';

                // Reverse old client's balance
                $pdo->prepare("UPDATE clients SET $oldClientBalanceField = $oldClientBalanceField + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                    ->execute([$oldSoldPrice, $currentData['sold_to'], $tenant_id, $branch_id]);

                // Find old client transaction
                $checkOldTxn = $pdo->prepare("
                    SELECT id, amount, currency FROM client_transactions
                    WHERE client_id = ? AND reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ? AND branch_id = ?
                    LIMIT 1
                ");
                $checkOldTxn->execute([$currentData['sold_to'], $booking_id, $tenant_id, $branch_id]);
                $oldClientTransaction = $checkOldTxn->fetch(PDO::FETCH_ASSOC);

                if ($oldClientTransaction) {
                    // Update subsequent transactions
                    $txnAmount = abs($oldClientTransaction['amount']);
                    $pdo->prepare("
                        UPDATE client_transactions SET balance = balance + ?
                        WHERE client_id = ? AND id > ? AND currency = ? AND id != ?
                          AND tenant_id = ? AND branch_id = ?
                    ")->execute([$txnAmount, $currentData['sold_to'], $oldClientTransaction['id'], $oldClientTransaction['currency'], $oldClientTransaction['id'], $tenant_id, $branch_id]);

                    // Delete old transaction
                    $pdo->prepare("DELETE FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$oldClientTransaction['id'], $tenant_id, $branch_id]);
                }
            } else {
                // Old client is agency - just delete the transaction
                $checkOldTxn = $pdo->prepare("
                    SELECT id FROM client_transactions
                    WHERE client_id = ? AND reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ? AND branch_id = ?
                    LIMIT 1
                ");
                $checkOldTxn->execute([$currentData['sold_to'], $booking_id, $tenant_id, $branch_id]);
                $oldClientTransaction = $checkOldTxn->fetch(PDO::FETCH_ASSOC);

                if ($oldClientTransaction) {
                    $pdo->prepare("DELETE FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$oldClientTransaction['id'], $tenant_id, $branch_id]);
                }
            }

            // Add transaction to new client
            if ($isRegularClient) {
                $newBalanceField = $clientCurrency == 'USD' ? 'usd_balance' : 'afs_balance';
                $pdo->prepare("UPDATE clients SET $newBalanceField = $newBalanceField - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                    ->execute([$totalSoldPrice, $soldTo, $tenant_id, $branch_id]);
            }

            // Get new client balance
            $getNewBalance = $pdo->prepare("SELECT " . ($clientCurrency == 'USD' ? 'usd_balance' : 'afs_balance') . " as current_balance FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $getNewBalance->execute([$soldTo, $tenant_id, $branch_id]);
            $newBalance = $getNewBalance->fetchColumn();

            // Create new transaction
            $pdo->prepare("
                INSERT INTO client_transactions (client_id, reference_id, type, amount, currency, description, balance, transaction_of, receipt, tenant_id, branch_id)
                VALUES (?, ?, 'debit', ?, ?, ?, ?, 'umrah', NULL, ?, ?)
            ")->execute([$soldTo, $booking_id, $totalSoldPrice, $clientCurrency, "Sale for member: $name (Passport: $passport_number)", $newBalance, $tenant_id, $branch_id]);

        } else {
            // Same client, adjust for price difference
            if ($clientPriceAdjustment != 0 && $isRegularClient) {
                $balanceField = $clientCurrency == 'USD' ? 'usd_balance' : 'afs_balance';
                $adj = $clientPriceAdjustment;

                if ($adj > 0) {
                    $pdo->prepare("UPDATE clients SET $balanceField = $balanceField - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$adj, $soldTo, $tenant_id, $branch_id]);
                } else {
                    $adj = abs($adj);
                    $pdo->prepare("UPDATE clients SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                        ->execute([$adj, $soldTo, $tenant_id, $branch_id]);
                }
            }

            // Update existing client transaction
            $checkTxn = $pdo->prepare("
                SELECT id, balance, amount FROM client_transactions
                WHERE client_id = ? AND reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ? AND branch_id = ?
                LIMIT 1
            ");
            $checkTxn->execute([$soldTo, $booking_id, $tenant_id, $branch_id]);
            $clientTransaction = $checkTxn->fetch(PDO::FETCH_ASSOC);

            if ($clientTransaction) {
                $currentTxnAmount = abs(floatval($clientTransaction['amount']));
                $amountDifference = $totalSoldPrice - $currentTxnAmount;
                $newTxnBalance = $isRegularClient ? $clientTransaction['balance'] - $amountDifference : $clientTransaction['balance'];

                // Get family name for description
                $familyId = $currentData['family_id'] ?? null;
                $familyName = '';
                if (!empty($familyId)) {
                    $fhStmt = $pdo->prepare("SELECT head_of_family FROM families WHERE family_id = ? AND tenant_id = ?");
                    $fhStmt->execute([$familyId, $tenant_id]);
                    $familyName = trim((string)($fhStmt->fetchColumn() ?: ''));
                }
                $memberLabel = $familyName !== '' ? $name . ' (' . $familyName . ' family)' : $name;
                $newDescription = "Client was debited $totalSoldPrice $clientCurrency for umrah booking for $memberLabel";

                $pdo->prepare("
                    UPDATE client_transactions SET amount = ?, balance = ?, description = ?
                    WHERE id = ? AND tenant_id = ? AND branch_id = ?
                ")->execute([abs($totalSoldPrice), $newTxnBalance, $newDescription, $clientTransaction['id'], $tenant_id, $branch_id]);

                // Recalculate ALL subsequent balances (avoids double-counting)
                if ($isRegularClient && $amountDifference != 0) {
                    $laterStmt = $pdo->prepare("
                        SELECT id, amount, type FROM client_transactions
                        WHERE client_id = ? AND currency = ? AND id > ?
                          AND tenant_id = ? AND branch_id = ?
                        ORDER BY id ASC
                    ");
                    $laterStmt->execute([$soldTo, $clientCurrency, $clientTransaction['id'], $tenant_id, $branch_id]);
                    $laterTxns = $laterStmt->fetchAll(PDO::FETCH_ASSOC);

                    $runningBalance = $newTxnBalance;
                    $updStmt = $pdo->prepare("UPDATE client_transactions SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                    foreach ($laterTxns as $lt) {
                        $amt = (float)$lt['amount'];
                        if (strtolower((string)$lt['type']) === 'credit') {
                            $runningBalance = round($runningBalance + abs($amt), 3);
                        } else {
                            $runningBalance = round($runningBalance - abs($amt), 3);
                        }
                        $updStmt->execute([$runningBalance, $lt['id'], $tenant_id, $branch_id]);
                    }
                }
            }
        }
    }

    // Update family totals
    $family_id = $currentData['family_id'];
    if ($isActiveStatus && $family_id) {
        $pdo->prepare("
            UPDATE families f SET
                f.total_members = (SELECT COUNT(*) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_price = (SELECT SUM(COALESCE(sold_price, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_paid = (SELECT SUM(COALESCE(paid, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_paid_to_bank = (SELECT SUM(COALESCE(received_bank_payment, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_due = (SELECT SUM(COALESCE(due, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active')
            WHERE f.family_id = ? AND f.tenant_id = ? AND f.branch_id = ?
        ")->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $family_id, $tenant_id, $branch_id]);
    }

    // Activity logging
    $userId = $_SESSION['user_id'] ?? 0;
    $userIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $oldValues = json_encode($currentData);
    $newValues = json_encode([
        'booking_id' => $booking_id,
        'family_id' => $family_id,
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
        'sold_price' => $totalSoldPrice,
        'paid' => $paid,
        'gender' => $gender,
        'passport_expiry' => $passport_expiry,
        'remarks' => $remarks,
        'relation' => $relation,
        'g_name' => $g_name,
        'father_name' => $father_name,
        'discount' => $discount
    ]);

    $pdo->prepare("
        INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id, branch_id)
        VALUES (?, ?, ?, 'update_umrah_member', 'umrah_bookings', ?, ?, ?, NOW(), ?, ?)
    ")->execute([$userId, $userIp, $userAgent, $booking_id, $oldValues, $newValues, $tenant_id, $branch_id]);

    $pdo->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Member updated successfully']);

} catch (PDOException $e) {
    $pdo->rollBack();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
