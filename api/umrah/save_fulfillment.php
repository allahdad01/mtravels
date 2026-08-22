<?php
/**
 * Save Fulfillment API (Phase 19-21 + skeleton write-back)
 * Applies the fulfillment for one sold service: supplier assignment,
 * actual procurement cost, dates, notes and status. Core logic lives in
 * fulfillment_helpers.php (shared with the multi-member bulk endpoint).
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
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'] ?? 0;

require_once '../../includes/db.php';
require_once __DIR__ . '/fulfillment_helpers.php';

$result = fulfillment_save($pdo, [
    'tenant_id'         => $tenant_id,
    'branch_id'         => $branch_id,
    'user_id'           => $user_id,
    'booking_service_id'=> $_POST['booking_service_id'] ?? 0,
    'supplier_id'       => $_POST['supplier_id'] ?? '',
    'status'            => $_POST['status'] ?? 'pending',
    'supplier_currency' => $_POST['supplier_currency'] ?? '',
    'supplier_cost'     => $_POST['supplier_cost'] ?? '',
    'exchange_rate'     => $_POST['exchange_rate'] ?? '',
    'requested_date'    => $_POST['requested_date'] ?? '',
    'planned_date'      => $_POST['planned_date'] ?? '',
    'completed_date'    => $_POST['completed_date'] ?? '',
    'notes'             => $_POST['notes'] ?? '',
    'hotel_id'          => $_POST['hotel_id'] ?? '',
    'room_id'           => $_POST['room_id'] ?? '',
    'room_type_id'      => $_POST['room_type_id'] ?? '',
    'extra_bed'         => $_POST['extra_bed'] ?? '',
    'check_in'          => $_POST['check_in'] ?? '',
    'check_out'         => $_POST['check_out'] ?? '',
    'nights'            => $_POST['nights'] ?? '',
    'nightly_rate'      => $_POST['nightly_rate'] ?? '',
    'contract_id'       => $_POST['contract_id'] ?? 0,
    'hotel_stays'       => isset($_POST['hotel_stays']) ? json_decode((string)$_POST['hotel_stays'], true) : null,
    'makkah_currency'   => $_POST['makkah_currency'] ?? '',
    'makkah_cost'       => $_POST['makkah_cost'] ?? '',
    'makkah_rate'       => $_POST['makkah_rate'] ?? '',
    'madinah_currency'  => $_POST['madinah_currency'] ?? '',
    'madinah_cost'      => $_POST['madinah_cost'] ?? '',
    'madinah_rate'      => $_POST['madinah_rate'] ?? '',
    'ticket_number'     => $_POST['ticket_number'] ?? '',
    'pnr'               => $_POST['pnr'] ?? '',
    'airline'           => $_POST['airline'] ?? '',
    'flight_number'     => $_POST['flight_number'] ?? '',
    'flight_type'       => $_POST['flight_type'] ?? '',
    'flight_legs'       => $_POST['flight_legs'] ?? '',
    'departure_city'    => $_POST['departure_city'] ?? '',
    'arrival_city'      => $_POST['arrival_city'] ?? '',
    'departure_time'    => $_POST['departure_time'] ?? '',
    'arrival_time'      => $_POST['arrival_time'] ?? '',
    'return_flight_number' => $_POST['return_flight_number'] ?? '',
    'return_departure_time' => $_POST['return_departure_time'] ?? '',
    'return_arrival_time' => $_POST['return_arrival_time'] ?? '',
    'transport_vehicle' => $_POST['transport_vehicle'] ?? '',
    'transport_trip_date' => $_POST['transport_trip_date'] ?? '',
]);

if (!$result['success']) {
    http_response_code($result['code'] ?? 400);
    echo json_encode(['success' => false, 'message' => $result['message']]);
    exit;
}

echo json_encode($result);