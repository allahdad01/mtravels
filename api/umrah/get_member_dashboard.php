<?php
/**
 * Get Member Dashboard API (Phase 23)
 * The operational center of a member: package + financial summary +
 * sold services with their fulfillment state + payment history.
 * Read-only view; mutations happen through the existing APIs
 * (fulfillment, transactions, edit member, ...).
 */

require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once '../../includes/db.php';

$booking_id = isset($_GET['booking_id']) ? DbSecurity::validateInput($_GET['booking_id'], 'int') : 0;

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Booking ID is required.']);
    exit;
}

// ---- Booking header ------------------------------------------------------
$bStmt = $pdo->prepare("
    SELECT b.booking_id, b.family_id, b.package_id, b.name, b.fname, b.gfname,
           b.dob, b.gender, b.passport_number, b.entry_date, b.flight_date, b.return_date,
           b.duration, b.room_type, b.price, b.sold_price, b.discount, b.profit,
           b.paid, b.due, b.currency, b.exchange_rate, b.received_bank_payment,
           b.bank_receipt_number, b.status, b.remarks,
           p.name AS package_name, p.code AS package_code,
           f.head_of_family,
           u.name AS created_by
    FROM umrah_bookings b
    LEFT JOIN umrah_packages p ON b.package_id = p.id AND p.tenant_id = ?
    LEFT JOIN families f ON b.family_id = f.family_id AND f.tenant_id = ?
    LEFT JOIN users u ON b.created_by = u.id
    WHERE b.booking_id = ? AND b.tenant_id = ?");
$bStmt->execute([$tenant_id, $tenant_id, $booking_id, $tenant_id]);
$booking = $bStmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

// ---- Sold services + fulfillment state ------------------------------------
$sStmt = $pdo->prepare("
    SELECT bs.id AS booking_service_id,
           bs.service_type, bs.service_id, bs.pricing_unit, bs.quantity, bs.is_optional,
           bs.base_price, bs.sold_price, bs.profit, bs.currency,
           bs.status AS sold_status,
           s.name AS service_name, c.name AS category_name,
           sup.name AS supplier_name,
           f.id AS fulfillment_id, f.status AS fulfill_status,
           f.supplier_id AS fulfill_supplier_id,
           sup2.name AS fulfill_supplier_name,
           f.supplier_currency, f.supplier_cost, f.exchange_rate,
           f.cost_amount, f.requested_date, f.planned_date, f.completed_date,
           hf.hotel_id, hf.room_type_id, hf.check_in, hf.check_out, hf.nights, hf.nightly_rate,
           h.name AS hotel_name, rt.name AS room_type_name,
           ff.ticket_number, ff.pnr, ff.airline, ff.flight_number,
           ff.departure_city, ff.arrival_city, ff.departure_time, ff.arrival_time,
           ff.return_flight_number, ff.return_departure_time, ff.return_arrival_time, ff.class,
           (SELECT fd.detail_value FROM umrah_fulfillment_details fd WHERE fd.fulfillment_id = f.id AND fd.detail_key = 'vehicle' LIMIT 1) AS transport_vehicle,
           (SELECT fd.detail_value FROM umrah_fulfillment_details fd WHERE fd.fulfillment_id = f.id AND fd.detail_key = 'trip_date' LIMIT 1) AS transport_trip_date
    FROM umrah_booking_services bs
    LEFT JOIN umrah_services s ON bs.service_id = s.id
    LEFT JOIN umrah_service_categories c ON s.category_id = c.id
    LEFT JOIN suppliers sup ON bs.supplier_id = sup.id
    LEFT JOIN umrah_fulfillments f ON f.booking_service_id = bs.id
    LEFT JOIN suppliers sup2 ON f.supplier_id = sup2.id
    LEFT JOIN umrah_hotel_fulfillments hf ON hf.fulfillment_id = f.id
    LEFT JOIN umrah_hotels h ON hf.hotel_id = h.id
    LEFT JOIN umrah_hotel_room_types rt ON hf.room_type_id = rt.id
    LEFT JOIN umrah_flight_fulfillments ff ON ff.fulfillment_id = f.id
    WHERE bs.booking_id = ? AND bs.tenant_id = ?
    ORDER BY bs.is_optional, bs.id");
$sStmt->execute([$booking_id, $tenant_id]);
$services = $sStmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Payment history --------------------------------------------------------
$pStmt = $pdo->prepare("
    SELECT t.id, t.payment_date, t.payment_amount, t.payment_description,
           t.receipt, t.currency, t.exchange_rate, t.transaction_to
    FROM umrah_transactions t
    WHERE t.umrah_booking_id = ? AND t.tenant_id = ?
    ORDER BY t.payment_date DESC, t.id DESC");
$pStmt->execute([$booking_id, $tenant_id]);
$payments = $pStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'booking' => $booking,
    'services' => $services,
    'payments' => $payments,
]);
