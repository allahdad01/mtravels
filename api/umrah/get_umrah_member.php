<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();


// Database connection
require_once('../../includes/db.php');

// Check if booking_id is provided
if (!isset($_GET['booking_id']) || empty($_GET['booking_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
    exit();
}

$bookingId = intval($_GET['booking_id']);

try {
    // Prepare the SQL query for booking data.
    // Dates may live on the flight fulfillment (assigned via the fulfillment
    // modal) — join it so the authoritative dates are available when the
    // booking row itself is empty.
    $sql = "SELECT
                b.*,
                c.name as client_name,
                m.name as account_name,
                ff.departure_time,
                ff.return_departure_time
            FROM
                umrah_bookings b
            LEFT JOIN
                umrah_booking_services ubs ON b.booking_id = ubs.booking_id AND (ubs.service_type = 'all' OR FIND_IN_SET('ticket', REPLACE(ubs.service_type, '+', ',')) > 0) AND ubs.tenant_id = ? AND ubs.branch_id = ?
            LEFT JOIN
                umrah_fulfillments uf ON uf.booking_service_id = ubs.id AND uf.fulfillment_type = 'flight' AND uf.status <> 'cancelled' AND uf.tenant_id = ? AND uf.branch_id = ?
            LEFT JOIN
                umrah_flight_fulfillments ff ON ff.fulfillment_id = uf.id
            LEFT JOIN
                clients c ON b.sold_to = c.id
            LEFT JOIN
                main_account m ON b.paid_to = m.id
            WHERE
                b.booking_id = ? AND b.tenant_id = ? AND b.branch_id = ?
            ORDER BY ff.id DESC
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $bookingId, $tenant_id, $branch_id]);

    if ($stmt->rowCount() > 0) {
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

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

        // Get all services for this booking
        $servicesSql = "SELECT
                            ubs.id as service_id,
                            ubs.service_type,
                            COALESCE(uff.supplier_id, ubs.supplier_id) as supplier_id,
                            s.name as supplier_name,
                            ubs.base_price,
                            ubs.sold_price,
                            ubs.profit,
                            ubs.currency,
                            s.supplier_type
                        FROM
                            umrah_booking_services ubs
                        LEFT JOIN
                            umrah_fulfillments uff ON uff.booking_service_id = ubs.id AND uff.fulfillment_type = 'flight' AND uff.status <> 'cancelled' AND uff.id = (SELECT MIN(uff2.id) FROM umrah_fulfillments uff2 WHERE uff2.booking_service_id = ubs.id)
                        LEFT JOIN
                            suppliers s ON s.id = COALESCE(uff.supplier_id, ubs.supplier_id)
                        WHERE
                            ubs.booking_id = ? AND ubs.tenant_id = ? AND ubs.branch_id = ?
                        ORDER BY ubs.id";

        $servicesStmt = $pdo->prepare($servicesSql);
        $servicesStmt->execute([$bookingId, $tenant_id, $branch_id]);
        $services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

        // Add services to member data
        $member['services'] = $services;

        echo json_encode(['success' => true, 'member' => $member]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Member not found']);
    }
} catch (PDOException $e) {
   // Return error message
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 