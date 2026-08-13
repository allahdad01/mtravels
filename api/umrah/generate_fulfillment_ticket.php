<?php
/**
 * Per-member flight fulfillment ticket (PDF) — printed from the fulfillment
 * card's "Print Ticket" button for a saved flight fulfillment.
 *
 * GET: booking_service_id
 */
require_once('../../includes/db.php');
require_once('../../admin/security.php');
require_once('../../includes/language_helpers.php');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
enforce_auth();

$bookingServiceId = isset($_GET['booking_service_id']) ? (int)$_GET['booking_service_id'] : 0;
if (!$bookingServiceId) {
    die('Invalid request: booking_service_id required');
}

// Fetch the flight fulfillment attached to this booking service line
$stmt = $pdo->prepare("
    SELECT bs.id AS booking_service_id, bs.booking_id,
           f.id AS fulfillment_id,
           ff.ticket_number, ff.pnr, ff.airline, ff.flight_type, ff.flight_legs, ff.flight_number,
           ff.departure_city, ff.arrival_city, ff.departure_time, ff.arrival_time,
           ff.return_flight_number, ff.return_departure_time, ff.return_arrival_time, ff.class
    FROM umrah_booking_services bs
    LEFT JOIN umrah_fulfillments f ON f.booking_service_id = bs.id
      AND f.id = (SELECT MIN(f2.id) FROM umrah_fulfillments f2 WHERE f2.booking_service_id = bs.id AND f2.tenant_id = bs.tenant_id)
    LEFT JOIN umrah_flight_fulfillments ff ON ff.fulfillment_id = f.id
    WHERE bs.id = ? AND bs.tenant_id = ? AND bs.branch_id = ?
");
$stmt->execute([$bookingServiceId, $tenant_id, $branch_id]);
$svc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$svc || empty($svc['fulfillment_id'])) {
    die('No flight fulfillment found for this service. Save the flight details first.');
}

$flightType = !empty($svc['flight_type']) ? $svc['flight_type'] : 'direct';
$pnr = !empty($svc['pnr']) ? $svc['pnr'] : (!empty($svc['ticket_number']) ? $svc['ticket_number'] : 'N/A');
$remarks = trim(($svc['airline'] ?? '') . (!empty($svc['class']) ? ' - ' . $svc['class'] : ''));

// Build the flight layout — same shape the group ticket renderer expects
$outboundFlights = [];
$returnFlights = [];

if ($flightType === 'direct') {
    if (!empty($svc['flight_number'])) {
        $outboundFlights[] = [
            'flight_number' => $svc['flight_number'],
            'departure_city' => $svc['departure_city'] ?? '',
            'arrival_city' => $svc['arrival_city'] ?? '',
            'departure_datetime' => $svc['departure_time'] ?? '',
            'arrival_datetime' => $svc['arrival_time'] ?? ''
        ];
    }
    if (!empty($svc['return_flight_number'])) {
        $returnFlights[] = [
            'flight_number' => $svc['return_flight_number'],
            'departure_city' => $svc['arrival_city'] ?? '',
            'arrival_city' => $svc['departure_city'] ?? '',
            'departure_datetime' => $svc['return_departure_time'] ?? '',
            'arrival_datetime' => $svc['return_arrival_time'] ?? ''
        ];
    }
} else {
    $legs = [];
    if (!empty($svc['flight_legs'])) {
        $decoded = json_decode($svc['flight_legs'], true);
        if (is_array($decoded)) { $legs = $decoded; }
    }
    foreach ($legs as $leg) {
        if (!is_array($leg) || empty($leg['flight_no'])) { continue; }
        $flight = [
            'flight_number' => $leg['flight_no'],
            'departure_city' => $leg['dep_city'] ?? '',
            'arrival_city' => $leg['arr_city'] ?? '',
            'departure_datetime' => trim(($leg['dep_date'] ?? '') . ' ' . ($leg['dep_time'] ?? '')),
            'arrival_datetime' => trim(($leg['arr_date'] ?? '') . ' ' . ($leg['arr_time'] ?? ''))
        ];
        $label = (string)($leg['label'] ?? '');
        if (strpos($label, 'return') === 0) {
            $returnFlights[] = $flight;
        } else {
            $outboundFlights[] = $flight;
        }
    }
}

// Fetch this pilgrim's details
$pStmt = $pdo->prepare("
    SELECT b.*, f.head_of_family, f.package_type
    FROM umrah_bookings b
    LEFT JOIN families f ON b.family_id = f.family_id AND f.tenant_id = ? AND f.branch_id = ?
    WHERE b.booking_id = ? AND b.tenant_id = ? AND b.branch_id = ?
");
$pStmt->execute([$tenant_id, $branch_id, $svc['booking_id'], $tenant_id, $branch_id]);
$pilgrims = $pStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pilgrims)) die('No pilgrim data found');
if (empty($outboundFlights) && empty($returnFlights)) die('No flight details saved yet');

require __DIR__ . '/ticket_pdf_render.php';
