<?php
// Include security and database connections
require_once '../../admin/security.php';
require_once '../../includes/db.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$new_flight_date = isset($_POST['new_flight_date']) ? trim($_POST['new_flight_date']) : '';
$new_return_date = isset($_POST['new_return_date']) ? trim($_POST['new_return_date']) : '';
$new_duration = isset($_POST['new_duration']) ? trim($_POST['new_duration']) : '';
$supplier_penalty = isset($_POST['supplier_penalty']) ? max(0, (float)$_POST['supplier_penalty']) : 0;
$service_penalty = isset($_POST['service_penalty']) ? max(0, (float)$_POST['service_penalty']) : 0;
$change_reason = isset($_POST['change_reason']) ? trim($_POST['change_reason']) : '';
$total_penalty = $supplier_penalty + $service_penalty;

// Validate required fields
if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
    exit;
}

if (empty($new_flight_date) || empty($new_return_date)) {
    echo json_encode(['success' => false, 'message' => 'New flight date and return date are required']);
    exit;
}

if (empty($change_reason)) {
    echo json_encode(['success' => false, 'message' => 'Reason for change is required']);
    exit;
}

// Validate dates
$flight_date_obj = DateTime::createFromFormat('Y-m-d', $new_flight_date);
$return_date_obj = DateTime::createFromFormat('Y-m-d', $new_return_date);
$flight_errors = DateTime::getLastErrors();
$return_errors = DateTime::getLastErrors();

if (!$flight_date_obj || !$return_date_obj ||
    (is_array($flight_errors) && $flight_errors['warning_count'] > 0) ||
    (is_array($return_errors) && $return_errors['warning_count'] > 0)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

if ($return_date_obj <= $flight_date_obj) {
    echo json_encode(['success' => false, 'message' => 'Return date must be after flight date']);
    exit;
}

try {
    // Load booking details with supplier and client info
    $stmt = $pdo->prepare("
        SELECT ub.*, ub.price as current_price, ub.sold_price, ub.profit, ub.due, ub.family_id,
               COALESCE(f.supplier_id, ubs.supplier_id) AS supplier, ub.sold_to, ub.currency,
               s.name as supplier_name, s.balance as supplier_balance, s.supplier_type,
               c.name as client_name, c.usd_balance, c.afs_balance, c.client_type
        FROM umrah_bookings ub
        LEFT JOIN umrah_booking_services ubs ON ub.booking_id = ubs.booking_id AND (ubs.service_type = 'all' OR FIND_IN_SET('ticket', REPLACE(ubs.service_type, '+', ',')) > 0) AND ubs.tenant_id = ? AND ubs.branch_id = ?
        LEFT JOIN umrah_fulfillments f ON f.booking_service_id = ubs.id AND f.fulfillment_type = 'flight' AND f.status <> 'cancelled' AND f.id = (SELECT MIN(f2.id) FROM umrah_fulfillments f2 WHERE f2.booking_service_id = ubs.id AND f2.tenant_id = ?)
        LEFT JOIN suppliers s ON s.id = COALESCE(f.supplier_id, ubs.supplier_id) AND s.tenant_id = ? AND s.branch_id = ?
        LEFT JOIN clients c ON ub.sold_to = c.id AND c.tenant_id = ? AND c.branch_id = ?
        WHERE ub.booking_id = ? AND ub.tenant_id = ? AND ub.branch_id = ?
    ");
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(8, $booking_id, PDO::PARAM_INT);
    $stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(10, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }

    // Penalties need real entities to post transactions against
    if ($supplier_penalty > 0 && empty($booking['supplier'])) {
        echo json_encode(['success' => false, 'message' => 'Supplier penalty cannot be applied: no supplier is assigned to this booking. Assign a supplier first or remove the supplier penalty.']);
        exit;
    }
    if ($total_penalty > 0 && empty($booking['sold_to'])) {
        echo json_encode(['success' => false, 'message' => 'Penalty cannot be applied: no client is attached to this booking']);
        exit;
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Apply the date change with penalties
    $new_price = $booking['current_price'] + $supplier_penalty;
    $new_sold_price = $booking['sold_price'] + $total_penalty;
    $new_profit = $new_sold_price - $new_price;
    $new_due = $booking['due'] + $total_penalty;

    // Update the booking with the new duration and adjusted prices. The
    // flight/return dates are no longer stored on the booking row — they live
    // on the flight fulfillment (updated below).
    $updateBookingSql = "
        UPDATE umrah_bookings
        SET duration = ?,
            price = ?, sold_price = ?, profit = ?, due = ?
        WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?
    ";
    $stmt = $pdo->prepare($updateBookingSql);
    $stmt->bindParam(1, $new_duration, PDO::PARAM_STR);
    $stmt->bindParam(2, $new_price, PDO::PARAM_STR);
    $stmt->bindParam(3, $new_sold_price, PDO::PARAM_STR);
    $stmt->bindParam(4, $new_profit, PDO::PARAM_STR);
    $stmt->bindParam(5, $new_due, PDO::PARAM_STR);
    $stmt->bindParam(6, $booking_id, PDO::PARAM_INT);
    $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(8, $branch_id, PDO::PARAM_INT);

    if (!$stmt->execute()) {
        throw new PDOException('Failed to update booking');
    }

    // Sync the flight service fulfillment dates with the new flight dates.
    // The fulfillment (which drives the flights view and tickets) is the
    // single source of truth for the flight/return dates.
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
            $new_departure_time = !empty($ff['departure_time'])
                ? $new_flight_date . ' ' . date('H:i:s', strtotime($ff['departure_time']))
                : $new_flight_date . ' 00:00:00';
            $new_return_time = !empty($ff['return_departure_time'])
                ? $new_return_date . ' ' . date('H:i:s', strtotime($ff['return_departure_time']))
                : $new_return_date . ' 00:00:00';

            $updateFf->bindParam(1, $new_departure_time, PDO::PARAM_STR);
            $updateFf->bindParam(2, $new_return_time, PDO::PARAM_STR);
            $updateFf->bindParam(3, $ff['id'], PDO::PARAM_INT);
            if (!$updateFf->execute()) {
                throw new PDOException('Failed to update flight fulfillment dates');
            }
        }

        // Keep the fulfillment planned date in sync too
        $updatePlanned = $pdo->prepare("
            UPDATE umrah_fulfillments f
            JOIN umrah_booking_services bs ON bs.id = f.booking_service_id
            SET f.planned_date = ?
            WHERE bs.booking_id = ? AND bs.tenant_id = ? AND bs.branch_id = ?
              AND f.fulfillment_type = 'flight' AND f.status <> 'cancelled'
        ");
        $updatePlanned->bindParam(1, $new_flight_date, PDO::PARAM_STR);
        $updatePlanned->bindParam(2, $booking_id, PDO::PARAM_INT);
        $updatePlanned->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $updatePlanned->bindParam(4, $branch_id, PDO::PARAM_INT);
        if (!$updatePlanned->execute()) {
            throw new PDOException('Failed to update flight fulfillment planned date');
        }
    }

    // Handle supplier penalty deduction
    if ($supplier_penalty > 0) {
        $new_supplier_balance = $booking['supplier_balance'] - $supplier_penalty;

        // Insert supplier transaction
        $supplier_remarks = "Supplier penalty of {$supplier_penalty} {$booking['currency']} deducted for date change on booking #{$booking_id}";
        $stmt_supplier_transaction = $pdo->prepare("
            INSERT INTO supplier_transactions (
                supplier_id, reference_id, transaction_type, amount, balance, remarks, transaction_date, transaction_of, tenant_id, branch_id, receipt, updated_at
            ) VALUES (?, ?, 'Debit', ?, ?, ?, NOW(), 'umrah_date_change', ?, ?, '', NOW())
        ");
        $stmt_supplier_transaction->bindParam(1, $booking['supplier'], PDO::PARAM_INT);
        $stmt_supplier_transaction->bindParam(2, $booking_id, PDO::PARAM_INT);
        $stmt_supplier_transaction->bindParam(3, $supplier_penalty, PDO::PARAM_STR);
        $stmt_supplier_transaction->bindParam(4, $new_supplier_balance, PDO::PARAM_STR);
        $stmt_supplier_transaction->bindParam(5, $supplier_remarks, PDO::PARAM_STR);
        $stmt_supplier_transaction->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmt_supplier_transaction->bindParam(7, $branch_id, PDO::PARAM_INT);

        if (!$stmt_supplier_transaction->execute()) {
            throw new PDOException('Failed to create supplier penalty transaction');
        }

        // Update supplier balance if external supplier
        if ($booking['supplier_type'] === 'External') {
            $stmt_update_supplier_balance = $pdo->prepare("
                UPDATE suppliers SET balance = balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ");
            $stmt_update_supplier_balance->bindParam(1, $supplier_penalty, PDO::PARAM_STR);
            $stmt_update_supplier_balance->bindParam(2, $booking['supplier'], PDO::PARAM_INT);
            $stmt_update_supplier_balance->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt_update_supplier_balance->bindParam(4, $branch_id, PDO::PARAM_INT);

            if (!$stmt_update_supplier_balance->execute()) {
                throw new PDOException('Failed to update supplier balance');
            }
        }
    }

    // Handle client penalty deduction
    if ($total_penalty > 0) {
        $client_description = "Client debited {$total_penalty} {$booking['currency']} for date change penalty on booking #{$booking_id}";

        // Determine which balance to update based on currency
        $current_client_balance = ($booking['currency'] === 'USD') ? $booking['usd_balance'] : $booking['afs_balance'];
        $new_client_balance = $current_client_balance - $total_penalty;

        // Insert client transaction
        $stmt_client_transaction = $pdo->prepare("
            INSERT INTO client_transactions (
                client_id, type, transaction_of, reference_id, amount, balance, currency, description, created_at, tenant_id, branch_id, receipt
            ) VALUES (?, 'Debit', 'umrah_date_change', ?, ?, ?, ?, ?, NOW(), ?, ?, '')
        ");
        $stmt_client_transaction->bindParam(1, $booking['sold_to'], PDO::PARAM_INT);
        $stmt_client_transaction->bindParam(2, $booking_id, PDO::PARAM_INT);
        $stmt_client_transaction->bindParam(3, $total_penalty, PDO::PARAM_STR);
        $stmt_client_transaction->bindParam(4, $new_client_balance, PDO::PARAM_STR);
        $stmt_client_transaction->bindParam(5, $booking['currency'], PDO::PARAM_STR);
        $stmt_client_transaction->bindParam(6, $client_description, PDO::PARAM_STR);
        $stmt_client_transaction->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $stmt_client_transaction->bindParam(8, $branch_id, PDO::PARAM_INT);

        if (!$stmt_client_transaction->execute()) {
            throw new PDOException('Failed to create client penalty transaction');
        }

        // Update client balance if regular client
        if ($booking['client_type'] === 'regular') {
            if ($booking['currency'] === 'USD') {
                $stmt_update_client_balance = $pdo->prepare("
                    UPDATE clients SET usd_balance = usd_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?
                ");
            } else {
                $stmt_update_client_balance = $pdo->prepare("
                    UPDATE clients SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?
                ");
            }

            $stmt_update_client_balance->bindParam(1, $total_penalty, PDO::PARAM_STR);
            $stmt_update_client_balance->bindParam(2, $booking['sold_to'], PDO::PARAM_INT);
            $stmt_update_client_balance->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt_update_client_balance->bindParam(4, $branch_id, PDO::PARAM_INT);

            if (!$stmt_update_client_balance->execute()) {
                throw new PDOException('Failed to update client balance');
            }
        }
    }

    // Record the completed date change in history with penalties
    $remarks = $change_reason;
    if ($total_penalty > 0) {
        $remarks .= "\n\nPenalty Details:\n";
        $remarks .= "Supplier Penalty: " . number_format($supplier_penalty, 2) . "\n";
        $remarks .= "Service Penalty: " . number_format($service_penalty, 2) . "\n";
        $remarks .= "Total Penalty: " . number_format($total_penalty, 2);
    }

    $stmt = $pdo->prepare("
        INSERT INTO date_change_umrah (
            umrah_booking_id, family_id, supplier, sold_to, paid_to,
            passenger_name, old_flight_date, new_flight_date,
            old_return_date, new_return_date, old_duration, new_duration,
            old_price, new_price, price_difference, currency,
            supplier_penalty, service_penalty, total_penalty,
            status, approved_by, approved_at, processed_by, processed_at,
            remarks, created_by, tenant_id, branch_id
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            'Completed', ?, NOW(), ?, NOW(), ?, ?, ?, ?
        )
    ");

    $currency = $booking['currency'] ?: 'USD';
    $price_difference = $new_sold_price - $booking['sold_price'];
    $user_id = $_SESSION['user_id'];

    // The flight/return dates live on the flight fulfillment — store the
    // pre-change dates so the change can be reverted exactly later
    $old_flight_date = !empty($flightFulfillments[0]['departure_time']) ? date('Y-m-d', strtotime($flightFulfillments[0]['departure_time'])) : '';
    $old_return_date = !empty($flightFulfillments[0]['return_departure_time']) ? date('Y-m-d', strtotime($flightFulfillments[0]['return_departure_time'])) : '';
    $old_duration = $booking['duration'];
    if (empty($old_duration) && !empty($old_flight_date) && !empty($old_return_date)) {
        $dep = DateTime::createFromFormat('Y-m-d', $old_flight_date);
        $ret = DateTime::createFromFormat('Y-m-d', $old_return_date);
        if ($dep && $ret && $ret > $dep) {
            $old_duration = $dep->diff($ret)->days . ' Days';
        }
    }

    $stmt->bindParam(1, $booking_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $booking['family_id'], PDO::PARAM_INT);
    $stmt->bindParam(3, $booking['supplier'], PDO::PARAM_INT);
    $stmt->bindParam(4, $booking['sold_to'], PDO::PARAM_INT);
    $stmt->bindParam(5, $booking['paid_to'], PDO::PARAM_INT);
    $stmt->bindParam(6, $booking['name'], PDO::PARAM_STR);
    $stmt->bindParam(7, $old_flight_date, PDO::PARAM_STR);
    $stmt->bindParam(8, $new_flight_date, PDO::PARAM_STR);
    $stmt->bindParam(9, $old_return_date, PDO::PARAM_STR);
    $stmt->bindParam(10, $new_return_date, PDO::PARAM_STR);
    $stmt->bindParam(11, $old_duration, PDO::PARAM_STR);
    $stmt->bindParam(12, $new_duration, PDO::PARAM_STR);
    $stmt->bindParam(13, $booking['sold_price'], PDO::PARAM_STR);
    $stmt->bindParam(14, $new_sold_price, PDO::PARAM_STR);
    $stmt->bindParam(15, $price_difference, PDO::PARAM_STR);
    $stmt->bindParam(16, $currency, PDO::PARAM_STR);
    $stmt->bindParam(17, $supplier_penalty, PDO::PARAM_STR);
    $stmt->bindParam(18, $service_penalty, PDO::PARAM_STR);
    $stmt->bindParam(19, $total_penalty, PDO::PARAM_STR);
    $stmt->bindParam(20, $user_id, PDO::PARAM_INT);
    $stmt->bindParam(21, $user_id, PDO::PARAM_INT);
    $stmt->bindParam(22, $remarks, PDO::PARAM_STR);
    $stmt->bindParam(23, $user_id, PDO::PARAM_INT);
    $stmt->bindParam(24, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(25, $branch_id, PDO::PARAM_INT);

    if (!$stmt->execute()) {
        throw new PDOException('Failed to record date change history');
    }

    // Commit transaction
    $pdo->commit();

    // Update family totals
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

    echo json_encode([
        'success' => true,
        'message' => 'Date change applied successfully to booking #' . $booking_id . ($total_penalty > 0 ? ' with penalties processed' : '')
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'An error occurred while applying the date change']);
}
?>