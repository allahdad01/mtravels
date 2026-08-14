<?php
// Include security and database connections
require_once '../../admin/security.php';
require_once '../../includes/db.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

header('Content-Type: application/json');

// Check if request is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get booking ID
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
    exit;
}

try {
    // The authoritative dates are the flight fulfillment times (assigned via
    // the fulfillment modal).
    $stmt = $pdo->prepare("
        SELECT ub.duration, ub.currency, ub.name,
               ff.departure_time, ff.return_departure_time
        FROM umrah_bookings ub
        LEFT JOIN umrah_booking_services ubs ON ub.booking_id = ubs.booking_id AND (ubs.service_type = 'all' OR FIND_IN_SET('ticket', REPLACE(ubs.service_type, '+', ',')) > 0) AND ubs.tenant_id = ? AND ubs.branch_id = ?
        LEFT JOIN umrah_fulfillments f ON f.booking_service_id = ubs.id AND f.fulfillment_type = 'flight' AND f.status <> 'cancelled' AND f.tenant_id = ? AND f.branch_id = ?
        LEFT JOIN umrah_flight_fulfillments ff ON ff.fulfillment_id = f.id
        WHERE ub.booking_id = ? AND ub.tenant_id = ? AND ub.branch_id = ?
        ORDER BY ff.id DESC
        LIMIT 1
    ");
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(5, $booking_id, PDO::PARAM_INT);
    $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }

    // The authoritative dates live on the flight fulfillment (assigned via
    // the fulfillment modal). The booking row flight/return columns are no
    // longer used.
    $flight_date = !empty($row['departure_time']) ? date('Y-m-d', strtotime($row['departure_time'])) : '';
    $return_date = !empty($row['return_departure_time']) ? date('Y-m-d', strtotime($row['return_departure_time'])) : '';
    $duration = !empty($row['duration']) ? $row['duration'] : '';

    // Compute duration when the booking has no value but both dates exist
    if (empty($duration) && !empty($flight_date) && !empty($return_date)) {
        $dep = DateTime::createFromFormat('Y-m-d', $flight_date);
        $ret = DateTime::createFromFormat('Y-m-d', $return_date);
        if ($dep && $ret && $ret > $dep) {
            $duration = $dep->diff($ret)->days . ' Days';
        }
    }

    echo json_encode([
        'success' => true,
        'flight_date' => $flight_date,
        'return_date' => $return_date,
        'duration' => $duration,
        'currency' => $row['currency'] ?: 'USD',
        'passenger_name' => $row['name']
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to load booking dates']);
}
?>