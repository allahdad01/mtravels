<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('../../admin/includes/db_security.php');
require_once('../../admin/security.php');
require_once('../../includes/db.php');
enforce_auth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$userId = $_SESSION['user_id'] ?? 0;
$userIp = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$bookingId = intval($_POST['booking_id'] ?? 0);
if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
    exit;
}

$name = DbSecurity::validateInput($_POST['name'] ?? '', 'string', ['maxlength' => 50]);
$fname = DbSecurity::validateInput($_POST['fname'] ?? '', 'string', ['maxlength' => 50]);
$passportNumber = DbSecurity::validateInput($_POST['passport_number'] ?? '', 'string', ['maxlength' => 20]);
$roomType = DbSecurity::validateInput($_POST['room_type'] ?? '', 'string', ['maxlength' => 50]);
$duration = DbSecurity::validateInput($_POST['duration'] ?? '', 'string', ['maxlength' => 11]);
$dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
$dob = trim((string)$dob) === '' ? null : $dob;
$newSoldPrice = floatval($_POST['sold_price'] ?? 0);

try {
    $pdo->beginTransaction();

    // Get current booking data
    $stmtCurrent = $pdo->prepare("
        SELECT sold_to, family_id, paid_to, entry_date, name, dob, passport_number, room_type,
               duration, price, sold_price, profit, paid, due, discount, status, currency, exchange_rate
        FROM umrah_bookings
        WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmtCurrent->execute([$bookingId, $tenant_id, $branch_id]);
    $currentData = $stmtCurrent->fetch(PDO::FETCH_ASSOC);

    if (!$currentData) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }

    $isActiveStatus = ($currentData['status'] === 'active');
    $soldTo = $currentData['sold_to'];
    $familyId = $currentData['family_id'];
    $oldSoldPrice = floatval($currentData['sold_price']);
    $currentPaid = floatval($currentData['paid']);

    // Get client info
    $stmtClient = $pdo->prepare("SELECT client_type FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmtClient->execute([$soldTo, $tenant_id, $branch_id]);
    $clientData = $stmtClient->fetch(PDO::FETCH_ASSOC);
    $isRegularClient = ($clientData && $clientData['client_type'] === 'regular');

    $currentCurrency = strtoupper(trim((string)($currentData['currency'] ?? 'USD')));
    $clientCurrency = $currentCurrency;

    // Calculate price difference
    $clientPriceAdjustment = $newSoldPrice - $oldSoldPrice;

    // Recalculate due = sold_price - paid
    $newDue = $newSoldPrice - $currentPaid;

    // Update booking record
    $stmt = $pdo->prepare("
        UPDATE umrah_bookings
        SET name = ?, fname = ?, passport_number = ?, room_type = ?, duration = ?,
            dob = ?, sold_price = ?, due = ?, updated_at = NOW()
        WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmt->execute([$name, $fname, $passportNumber, $roomType, $duration, $dob, $newSoldPrice, $newDue, $bookingId, $tenant_id, $branch_id]);

    // Handle client balance adjustments when sold_price changes (same client)
    if ($isActiveStatus && $clientPriceAdjustment != 0) {
        if ($isRegularClient) {
            $balanceField = $clientCurrency == 'USD' ? 'usd_balance' : 'afs_balance';
            if ($clientPriceAdjustment > 0) {
                $pdo->prepare("UPDATE clients SET $balanceField = $balanceField - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                    ->execute([$clientPriceAdjustment, $soldTo, $tenant_id, $branch_id]);
            } else {
                $absAdj = abs($clientPriceAdjustment);
                $pdo->prepare("UPDATE clients SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                    ->execute([$absAdj, $soldTo, $tenant_id, $branch_id]);
            }
        }

        // Update client transaction
        $checkTxn = $pdo->prepare("
            SELECT id, balance, amount FROM client_transactions
            WHERE client_id = ? AND reference_id = ? AND transaction_of = 'umrah' AND tenant_id = ? AND branch_id = ?
            LIMIT 1
        ");
        $checkTxn->execute([$soldTo, $bookingId, $tenant_id, $branch_id]);
        $clientTransaction = $checkTxn->fetch(PDO::FETCH_ASSOC);

        if ($clientTransaction) {
            $currentTxnAmount = abs(floatval($clientTransaction['amount']));
            $amountDifference = $newSoldPrice - $currentTxnAmount;
            $newTxnBalance = $isRegularClient ? $clientTransaction['balance'] - $amountDifference : $clientTransaction['balance'];

            $pdo->prepare("
                UPDATE client_transactions
                SET amount = ?, balance = ?, description = CONCAT('Updated: ', description)
                WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ")->execute([-1 * abs($newSoldPrice), $newTxnBalance, $clientTransaction['id'], $tenant_id, $branch_id]);

            // Recalculate ALL subsequent balances from scratch (avoids
            // double-counting when multiple members are updated in sequence).
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
                        $runningBalance = round($runningBalance + $amt, 3);
                    } else {
                        $runningBalance = round($runningBalance - $amt, 3);
                    }
                    $updStmt->execute([$runningBalance, $lt['id'], $tenant_id, $branch_id]);
                }
            }
        }
    }

    // Update family totals
    if ($isActiveStatus && $familyId) {
        $pdo->prepare("
            UPDATE families f SET
                f.total_members = (SELECT COUNT(*) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_price = (SELECT SUM(COALESCE(sold_price, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_paid = (SELECT SUM(COALESCE(paid, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_paid_to_bank = (SELECT SUM(COALESCE(received_bank_payment, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_due = (SELECT SUM(COALESCE(due, 0)) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active')
            WHERE f.family_id = ? AND f.tenant_id = ? AND f.branch_id = ?
        ")->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $familyId, $tenant_id, $branch_id]);
    }

    // Activity logging
    $oldValues = json_encode($currentData);
    $newValues = json_encode([
        'booking_id' => $bookingId,
        'name' => $name,
        'fname' => $fname,
        'passport_number' => $passportNumber,
        'room_type' => $roomType,
        'duration' => $duration,
        'dob' => $dob,
        'sold_price' => $newSoldPrice,
        'due' => $newDue
    ]);
    $pdo->prepare("
        INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id, branch_id)
        VALUES (?, ?, ?, 'update_umrah_member', 'umrah_bookings', ?, ?, ?, NOW(), ?, ?)
    ")->execute([$userId, $userIp, $userAgent, $bookingId, $oldValues, $newValues, $tenant_id, $branch_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Member updated successfully']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error updating member: ' . $e->getMessage()]);
}
