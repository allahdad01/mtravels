<?php
require_once '../../includes/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Get booking ID from request
$bookingId = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit();
}

// Fetch member details.
// The flight dates may live on the flight fulfillment (assigned via the
// fulfillment modal) — join it so the authoritative dates are available
// when the booking row itself is empty.
$sql = "SELECT ub.*,
               c.name as client_name,
               ma.name as main_account_name,
               u.name as created_by,
               ff.departure_time,
               ff.return_departure_time
        FROM umrah_bookings ub
        LEFT JOIN umrah_booking_services ubs ON ub.booking_id = ubs.booking_id AND (ubs.service_type = 'all' OR FIND_IN_SET('ticket', REPLACE(ubs.service_type, '+', ',')) > 0) AND ubs.tenant_id = ? AND ubs.branch_id = ?
        LEFT JOIN umrah_fulfillments f ON f.booking_service_id = ubs.id AND f.fulfillment_type = 'flight' AND f.status <> 'cancelled' AND f.tenant_id = ? AND f.branch_id = ?
        LEFT JOIN umrah_flight_fulfillments ff ON ff.fulfillment_id = f.id
        LEFT JOIN clients c ON ub.sold_to = c.id AND c.tenant_id = ? AND c.branch_id = ?
        LEFT JOIN main_account ma ON ub.paid_to = ma.id AND ma.tenant_id = ? AND ma.branch_id = ?
        LEFT JOIN users u ON ub.created_by = u.id AND u.tenant_id = ? AND u.branch_id = ?
        WHERE ub.booking_id = ? AND ub.tenant_id = ? AND ub.branch_id = ?
        ORDER BY ff.id DESC
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $bookingId, $tenant_id, $branch_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if ($member) {
    // Fetch services with supplier information. Since Phase 19-21 the assigned
    // supplier lives on the fulfillment row (umrah_fulfillments.supplier_id),
    // not on the sold-service line — join the service's first (cost-bearing)
    // fulfillment, with a legacy fallback to the old line-level supplier.
    $servicesSql = "SELECT ubs.*, s.name as supplier_name
                    FROM umrah_booking_services ubs
                    LEFT JOIN umrah_fulfillments f ON f.booking_service_id = ubs.id
                      AND f.id = (SELECT MIN(f2.id) FROM umrah_fulfillments f2 WHERE f2.booking_service_id = ubs.id AND f2.tenant_id = ubs.tenant_id)
                    LEFT JOIN suppliers s ON s.id = COALESCE(f.supplier_id, ubs.supplier_id)
                    WHERE ubs.booking_id = ? AND ubs.tenant_id = ? AND ubs.branch_id = ?";
    $servicesStmt = $pdo->prepare($servicesSql);
    $servicesStmt->execute([$bookingId, $tenant_id, $branch_id]);
    $services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Expose the package-pinned hotel/room (from the sale snapshot) on each
    // service so the edit form preserves them across member edits.
    foreach ($services as &$svc) {
        $snap = json_decode((string)($svc['price_snapshot'] ?? ''), true) ?: [];
        if (empty($svc['hotel_id']) && !empty($snap['hotel_id'])) { $svc['hotel_id'] = (int)$snap['hotel_id']; }
        if (empty($svc['room_type_id']) && !empty($snap['room_type_id'])) { $svc['room_type_id'] = (int)$snap['room_type_id']; }
    }
    unset($svc);
    $member['services'] = $services;

    // Format dates for display (guard NULLs instead of showing 1970-01-01)
    $member['entry_date'] = !empty($member['entry_date']) ? date('Y-m-d', strtotime($member['entry_date'])) : '';
    $member['dob'] = !empty($member['dob']) ? date('Y-m-d', strtotime($member['dob'])) : '';
    $member['passport_expiry'] = !empty($member['passport_expiry']) ? date('Y-m-d', strtotime($member['passport_expiry'])) : '';

    // The flight dates live on the flight fulfillment (assigned via the
    // fulfillment modal) — the booking row flight/return columns are no
    // longer used
    $member['flight_date'] = !empty($member['departure_time']) ? date('Y-m-d', strtotime($member['departure_time'])) : '';
    $member['return_date'] = !empty($member['return_departure_time']) ? date('Y-m-d', strtotime($member['return_departure_time'])) : '';
    if (empty($member['duration']) && !empty($member['flight_date']) && !empty($member['return_date'])) {
        $dep = DateTime::createFromFormat('Y-m-d', $member['flight_date']);
        $ret = DateTime::createFromFormat('Y-m-d', $member['return_date']);
        if ($dep && $ret && $ret > $dep) {
            $member['duration'] = $dep->diff($ret)->days . ' Days';
        }
    }
    unset($member['departure_time'], $member['return_departure_time']);

    // Add refund flag
    $member['has_refund'] = ($member['status'] === 'refunded');

    // Add additional information
    $member['client_details'] = [
        'name' => $member['client_name'],
        'main_account' => $member['main_account_name'],
        'created_by' => $member['created_by']
    ];

    echo json_encode(['success' => true, 'member' => $member]);
} else {
    echo json_encode(['success' => false, 'message' => 'Member not found']);
}
?>