<?php
/**
 * Toggle Service Exclusion API
 * Excludes or includes a member from a specific sold service line.
 * When excluded: service cost = 0, no fulfillment, no supplier debit,
 * excluded from booking activation count.
 *
 * POST:
 *   booking_service_id  int    the service line to toggle
 *   is_excluded         int    1 = exclude, 0 = include
 */

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $errstr]);
    exit;
});

set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
enforce_auth();
require_permission('umrah.fulfill');

if (!verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id   = $_SESSION['user_id'] ?? 0;

require_once '../../includes/db.php';
require_once __DIR__ . '/fulfillment_helpers.php';

$booking_service_id = isset($_POST['booking_service_id']) ? (int)$_POST['booking_service_id'] : 0;
$is_excluded        = isset($_POST['is_excluded']) ? (int)$_POST['is_excluded'] : 0;

if (!$booking_service_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Booking service ID is required.']);
    exit;
}

$is_excluded = $is_excluded ? 1 : 0;

$pdo->beginTransaction();

try {
    // Verify the service line exists and belongs to this tenant
    $svcStmt = $pdo->prepare("
        SELECT bs.id, bs.booking_id, bs.is_excluded, bs.base_price,
               bs.service_type, bs.service_id, bs.is_optional
        FROM umrah_booking_services bs
        WHERE bs.id = ? AND bs.tenant_id = ?");
    $svcStmt->execute([$booking_service_id, $tenant_id]);
    $svc = $svcStmt->fetch(PDO::FETCH_ASSOC);

    if (!$svc) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Service line not found.']);
        exit;
    }

    // Don't allow excluding if fulfillment is already frozen
    if ($is_excluded) {
        $frozenStmt = $pdo->prepare("
            SELECT f.status FROM umrah_fulfillments f
            WHERE f.booking_service_id = ? AND f.tenant_id = ?
            ORDER BY f.id DESC LIMIT 1");
        $frozenStmt->execute([$booking_service_id, $tenant_id]);
        $frozenStatus = (string)$frozenStmt->fetchColumn();
        $openStatuses = ['pending', 'requested', 'assigned', 'not_assigned', 'reserved', 'not_applied', 'applied', 'processing', 'confirmed', 'ticketed', 'issued', 'not_ticketed'];
        if ($frozenStatus !== '' && !in_array($frozenStatus, $openStatuses, true)) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Cannot exclude — fulfillment is already ' . $frozenStatus . '.']);
            exit;
        }
    }

    // Update the exclusion flag
    $updStmt = $pdo->prepare("UPDATE umrah_booking_services SET is_excluded = ? WHERE id = ?");
    $updStmt->execute([$is_excluded, $booking_service_id]);

    // If excluding: cancel existing open fulfillments and clear cost
    if ($is_excluded) {
        // Cancel open fulfillments for this service line
        $pdo->prepare("
            UPDATE umrah_fulfillments SET status = 'cancelled'
            WHERE booking_service_id = ? AND tenant_id = ?
              AND status IN ('pending', 'requested', 'assigned', 'not_assigned', 'reserved',
                             'not_applied', 'applied', 'processing', 'not_ticketed')")
            ->execute([$booking_service_id, $tenant_id]);

        // Clear base_price on the service line
        $pdo->prepare("UPDATE umrah_booking_services SET base_price = 0 WHERE id = ?")
            ->execute([$booking_service_id]);
    } else {
        // Re-including: the service line goes back to pending, user must
        // re-fulfill it. No automatic cost restore — they must save the
        // fulfillment again.
        if ($svc['base_price'] == 0) {
            $pdo->prepare("UPDATE umrah_booking_services SET status = 'pending' WHERE id = ?")
                ->execute([$booking_service_id]);
        }
    }

    // Recalculate booking totals
    $booking_id = (int)$svc['booking_id'];
    $bpStmt = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(base_price, 0)), 0) FROM umrah_booking_services WHERE booking_id = ?");
    $bpStmt->execute([$booking_id]);
    $booking_price = round((float)$bpStmt->fetchColumn(), 3);

    // Include BRN costs in booking totals
    $brnStmt = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(cost_amount, 0)), 0) FROM umrah_brn_costs WHERE booking_id = ? AND tenant_id = ?");
    $brnStmt->execute([$booking_id, $tenant_id]);
    $booking_price = round($booking_price + (float)$brnStmt->fetchColumn(), 3);

    $bStmt = $pdo->prepare("SELECT sold_price, discount, family_id FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?");
    $bStmt->execute([$booking_id, $tenant_id]);
    $bRow = $bStmt->fetch(PDO::FETCH_ASSOC);
    if ($bRow) {
        $booking_profit = round(((float)$bRow['sold_price'] - (float)$bRow['discount']) - $booking_price, 3);
        $pdo->prepare("UPDATE umrah_bookings SET price = ?, profit = ? WHERE booking_id = ?")
            ->execute([$booking_price, $booking_profit, $booking_id]);
    }

    // Recalculate family totals
    if (!empty($bRow['family_id'])) {
        $famStmt = $pdo->prepare("
            SELECT COALESCE(SUM(COALESCE(sold_price, 0)), 0) AS total_price,
                   COALESCE(SUM(COALESCE(due, 0)), 0) AS total_due,
                   COUNT(*) AS member_count
            FROM umrah_bookings
            WHERE family_id = ? AND tenant_id = ?
              AND status NOT IN ('refunded', 'cancelled')");
        $famStmt->execute([(int)$bRow['family_id'], $tenant_id]);
        $famTots = $famStmt->fetch(PDO::FETCH_ASSOC);
        $pdo->prepare("UPDATE families SET total_members = ?, total_price = ?, total_due = ? WHERE family_id = ? AND tenant_id = ?")
            ->execute([$famTots['member_count'], $famTots['total_price'], $famTots['total_due'], (int)$bRow['family_id'], $tenant_id]);
    }

    // Audit
    umrah_audit($pdo, 'update', 'umrah_booking_services', $booking_service_id,
        ['is_excluded' => $svc['is_excluded']], ['is_excluded' => $is_excluded]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'is_excluded' => $is_excluded,
        'message' => $is_excluded ? 'Service excluded.' : 'Service included.',
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
