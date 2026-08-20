<?php
// Include security and database connections
require_once '../../admin/security.php';
require_once '../../includes/db.php';

// Enforce authentication
enforce_auth();
require_permission('umrah.member_edit');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'];

header('Content-Type: application/json');

// Get date change ID
$date_change_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!$date_change_id) {
    echo json_encode(['success' => false, 'message' => 'Date change ID is required']);
    exit;
}

try {
    // Load the date change record
    $stmt = $pdo->prepare("SELECT * FROM date_change_umrah WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $date_change_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $dc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dc) {
        echo json_encode(['success' => false, 'message' => 'Date change record not found']);
        exit;
    }

    $booking_id = (int)$dc['umrah_booking_id'];

    // Only the LATEST date change for a booking can be reverted. Restoring
    // the old values of an older record over a newer change would corrupt
    // the booking and the ledger.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM date_change_umrah WHERE umrah_booking_id = ? AND tenant_id = ? AND branch_id = ? AND id > ?");
    $stmt->bindParam(1, $booking_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $date_change_id, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'This date change cannot be reverted because a newer date change exists for this booking. Revert the newest one first.']);
        exit;
    }

    // Booking must still exist to be restored
    $stmt = $pdo->prepare("SELECT * FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $booking_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found. Cannot revert this date change.']);
        exit;
    }

    $pdo->beginTransaction();

    // ---- 1. Restore the booking: dates, duration and prices
    // submit added supplier_penalty to price and total_penalty to sold_price/due
    $restored_price = $booking['price'] - $dc['supplier_penalty'];
    $restored_sold = $booking['sold_price'] - $dc['total_penalty'];
    $restored_profit = $restored_sold - $restored_price;
    $restored_due = $booking['due'] - $dc['total_penalty'];

    // Restore the booking duration and prices. The flight/return dates are
    // NOT stored on the booking row anymore — they live on the flight
    // fulfillment and are restored below.
    $old_duration = ($dc['old_duration'] ?? '') !== '' ? $dc['old_duration'] : null;

    $stmt = $pdo->prepare("
        UPDATE umrah_bookings
        SET duration = ?,
            price = ?, sold_price = ?, profit = ?, due = ?
        WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmt->bindParam(1, $old_duration, PDO::PARAM_STR);
    $stmt->bindParam(2, $restored_price, PDO::PARAM_STR);
    $stmt->bindParam(3, $restored_sold, PDO::PARAM_STR);
    $stmt->bindParam(4, $restored_profit, PDO::PARAM_STR);
    $stmt->bindParam(5, $restored_due, PDO::PARAM_STR);
    $stmt->bindParam(6, $booking_id, PDO::PARAM_INT);
    $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(8, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    // ---- 2. Restore the flight fulfillment dates (keep time-of-day)
    $stmtFf = $pdo->prepare("
        SELECT ff.id, ff.departure_time, ff.return_departure_time
        FROM umrah_flight_fulfillments ff
        JOIN umrah_fulfillments f ON f.id = ff.fulfillment_id
        JOIN umrah_booking_services bs ON bs.id = f.booking_service_id
        WHERE bs.booking_id = ? AND bs.tenant_id = ? AND bs.branch_id = ?
          AND f.fulfillment_type = 'flight' AND f.status <> 'cancelled'
        ORDER BY ff.id DESC
    ");
    $stmtFf->bindParam(1, $booking_id, PDO::PARAM_INT);
    $stmtFf->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmtFf->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmtFf->execute();
    $flightFulfillments = $stmtFf->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($flightFulfillments)) {
        $updateFf = $pdo->prepare("
            UPDATE umrah_flight_fulfillments
            SET departure_time = ?, return_departure_time = ?
            WHERE id = ?
        ");
        foreach ($flightFulfillments as $ff) {
            $old_departure_time = !empty($dc['old_flight_date'])
                ? $dc['old_flight_date'] . ' ' . date('H:i:s', strtotime($ff['departure_time']))
                : null;
            $old_return_time = !empty($dc['old_return_date'])
                ? $dc['old_return_date'] . ' ' . date('H:i:s', strtotime($ff['return_departure_time']))
                : null;

            $updateFf->bindParam(1, $old_departure_time, PDO::PARAM_STR);
            $updateFf->bindParam(2, $old_return_time, PDO::PARAM_STR);
            $updateFf->bindParam(3, $ff['id'], PDO::PARAM_INT);
            $updateFf->execute();
        }

        // Restore the fulfillment planned date
        $updatePlanned = $pdo->prepare("
            UPDATE umrah_fulfillments f
            JOIN umrah_booking_services bs ON bs.id = f.booking_service_id
            SET f.planned_date = ?
            WHERE bs.booking_id = ? AND bs.tenant_id = ? AND bs.branch_id = ?
              AND f.fulfillment_type = 'flight' AND f.status <> 'cancelled'
        ");
        $updatePlanned->bindParam(1, $dc['old_flight_date'], PDO::PARAM_STR);
        $updatePlanned->bindParam(2, $booking_id, PDO::PARAM_INT);
        $updatePlanned->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $updatePlanned->bindParam(4, $branch_id, PDO::PARAM_INT);
        $updatePlanned->execute();
    }

    // ---- 3. Reverse the supplier penalty
    if ((float)$dc['supplier_penalty'] > 0 && !empty($dc['supplier'])) {
        // Locate the supplier transaction created by this date change
        $stmtSel = $pdo->prepare("
            SELECT id, transaction_type FROM supplier_transactions
            WHERE supplier_id = ? AND reference_id = ? AND transaction_of = 'umrah_date_change'
              AND amount = ? AND tenant_id = ? AND branch_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmtSel->bindParam(1, $dc['supplier'], PDO::PARAM_INT);
        $stmtSel->bindParam(2, $booking_id, PDO::PARAM_INT);
        $stmtSel->bindParam(3, $dc['supplier_penalty'], PDO::PARAM_STR);
        $stmtSel->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmtSel->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmtSel->execute();
        $supTx = $stmtSel->fetch(PDO::FETCH_ASSOC);

        if ($supTx) {
            // Fix the running balances of all subsequent supplier transactions
            // (deleting a Debit shifts them up, deleting a Credit shifts them down)
            $shift = ($supTx['transaction_type'] === 'Credit') ? '-' : '+';
            $stmtShift = $pdo->prepare("
                UPDATE supplier_transactions
                SET balance = balance " . $shift . " ?
                WHERE supplier_id = ? AND id > ? AND tenant_id = ? AND branch_id = ?
            ");
            $stmtShift->bindParam(1, $dc['supplier_penalty'], PDO::PARAM_STR);
            $stmtShift->bindParam(2, $dc['supplier'], PDO::PARAM_INT);
            $stmtShift->bindParam(3, $supTx['id'], PDO::PARAM_INT);
            $stmtShift->bindParam(4, $tenant_id, PDO::PARAM_INT);
            $stmtShift->bindParam(5, $branch_id, PDO::PARAM_INT);
            $stmtShift->execute();

            // Delete the supplier transaction
            $stmtDel = $pdo->prepare("DELETE FROM supplier_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmtDel->bindParam(1, $supTx['id'], PDO::PARAM_INT);
            $stmtDel->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmtDel->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmtDel->execute();
        }

        // Add the amount back to the balance only if it was deducted (External suppliers)
        $stmtType = $pdo->prepare("SELECT supplier_type FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmtType->bindParam(1, $dc['supplier'], PDO::PARAM_INT);
        $stmtType->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmtType->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmtType->execute();
        $supplierType = $stmtType->fetchColumn();

        if ($supplierType === 'External') {
            $stmtBal = $pdo->prepare("UPDATE suppliers SET balance = balance + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmtBal->bindParam(1, $dc['supplier_penalty'], PDO::PARAM_STR);
            $stmtBal->bindParam(2, $dc['supplier'], PDO::PARAM_INT);
            $stmtBal->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmtBal->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmtBal->execute();
        }
    }

    // ---- 4. Reverse the client penalty
    if ((float)$dc['total_penalty'] > 0 && !empty($dc['sold_to'])) {
        // Locate the client transaction created by this date change
        $stmtSel = $pdo->prepare("
            SELECT id, type FROM client_transactions
            WHERE client_id = ? AND reference_id = ? AND transaction_of = 'umrah_date_change'
              AND amount = ? AND tenant_id = ? AND branch_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmtSel->bindParam(1, $dc['sold_to'], PDO::PARAM_INT);
        $stmtSel->bindParam(2, $booking_id, PDO::PARAM_INT);
        $stmtSel->bindParam(3, $dc['total_penalty'], PDO::PARAM_STR);
        $stmtSel->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmtSel->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmtSel->execute();
        $cliTx = $stmtSel->fetch(PDO::FETCH_ASSOC);

        if ($cliTx) {
            $currency = $dc['currency'] ?: ($booking['currency'] ?: 'USD');

            // Fix the running balances of all subsequent client transactions in
            // this currency (deleting a debit shifts them up, a credit shifts down)
            $shift = (strtolower($cliTx['type']) === 'credit') ? '-' : '+';
            $stmtShift = $pdo->prepare("
                UPDATE client_transactions
                SET balance = balance " . $shift . " ?
                WHERE client_id = ? AND id > ? AND currency = ? AND tenant_id = ? AND branch_id = ?
            ");
            $stmtShift->bindParam(1, $dc['total_penalty'], PDO::PARAM_STR);
            $stmtShift->bindParam(2, $dc['sold_to'], PDO::PARAM_INT);
            $stmtShift->bindParam(3, $cliTx['id'], PDO::PARAM_INT);
            $stmtShift->bindParam(4, $currency, PDO::PARAM_STR);
            $stmtShift->bindParam(5, $tenant_id, PDO::PARAM_INT);
            $stmtShift->bindParam(6, $branch_id, PDO::PARAM_INT);
            $stmtShift->execute();

            // Delete the client transaction
            $stmtDel = $pdo->prepare("DELETE FROM client_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmtDel->bindParam(1, $cliTx['id'], PDO::PARAM_INT);
            $stmtDel->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmtDel->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmtDel->execute();
        }

        // Add the amount back to the balance only if it was deducted (regular clients)
        $stmtType = $pdo->prepare("SELECT client_type FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmtType->bindParam(1, $dc['sold_to'], PDO::PARAM_INT);
        $stmtType->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmtType->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmtType->execute();
        $clientType = $stmtType->fetchColumn();

        if ($clientType === 'regular') {
            $currency = $dc['currency'] ?: ($booking['currency'] ?: 'USD');
            $balanceField = ($currency === 'USD') ? 'usd_balance' : 'afs_balance';
            $stmtBal = $pdo->prepare("UPDATE clients SET $balanceField = $balanceField + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmtBal->bindParam(1, $dc['total_penalty'], PDO::PARAM_STR);
            $stmtBal->bindParam(2, $dc['sold_to'], PDO::PARAM_INT);
            $stmtBal->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmtBal->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmtBal->execute();
        }
    }

    // ---- 5. Recompute family totals if the booking belongs to a family
    if (!empty($booking['family_id'])) {
        $updateFamilyStmt = $pdo->prepare("
            UPDATE families f
            SET
                f.total_members = (SELECT COUNT(*) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_price = (SELECT SUM(sold_price) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_paid = (SELECT SUM(paid) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_paid_to_bank = (SELECT SUM(received_bank_payment) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active'),
                f.total_due = (SELECT SUM(due) FROM umrah_bookings WHERE family_id = f.family_id AND tenant_id = ? AND branch_id = ? AND status = 'active')
            WHERE f.family_id = ? AND f.tenant_id = ? AND f.branch_id = ?
        ");
        $updateFamilyStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(2, $branch_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(6, $branch_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(10, $branch_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(11, $booking['family_id'], PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(12, $tenant_id, PDO::PARAM_INT);
        $updateFamilyStmt->bindParam(13, $branch_id, PDO::PARAM_INT);
        $updateFamilyStmt->execute();
    }

    // ---- 6. Delete the date change record
    $stmtDel = $pdo->prepare("DELETE FROM date_change_umrah WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmtDel->bindParam(1, $date_change_id, PDO::PARAM_INT);
    $stmtDel->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmtDel->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmtDel->execute();

    // ---- 7. Audit log
    $old_values = json_encode($dc);
    $new_values = json_encode([]);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmtLog = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, 'delete', 'date_change_umrah', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmtLog->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmtLog->bindParam(2, $date_change_id, PDO::PARAM_INT);
    $stmtLog->bindParam(3, $old_values, PDO::PARAM_STR);
    $stmtLog->bindParam(4, $new_values, PDO::PARAM_STR);
    $stmtLog->bindParam(5, $ip_address, PDO::PARAM_STR);
    $stmtLog->bindParam(6, $user_agent, PDO::PARAM_STR);
    $stmtLog->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $stmtLog->bindParam(8, $branch_id, PDO::PARAM_INT);
    $stmtLog->execute();

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Date change on booking #' . $booking_id . ' reverted. Dates, prices and balances restored.'
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'An error occurred while reverting the date change']);
}
?>
