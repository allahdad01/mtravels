<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Validate supplier_id parameter
if (!isset($_GET['supplier_id']) || !is_numeric($_GET['supplier_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid supplier ID']);
    exit();
}

$supplierId = intval($_GET['supplier_id']);

// Database connection
require_once('../includes/conn.php');

// Prepare and execute query with left joins to fetch reference information
$query = "SELECT st.*,
            CASE
                            WHEN st.transaction_of = 'ticket_sale' THEN CONCAT(tb.passenger_name)
                            WHEN st.transaction_of = 'ticket_reserve' THEN CONCAT(tr.passenger_name)
                            WHEN st.transaction_of = 'ticket_refund' THEN CONCAT(rt.passenger_name)
                            WHEN st.transaction_of = 'date_change' THEN CONCAT(dc.passenger_name)
                            WHEN st.transaction_of = 'weight_sale' THEN CONCAT(tbt.passenger_name)
                            WHEN st.transaction_of = 'visa_sale' THEN CONCAT(vs.applicant_name)
                            WHEN st.transaction_of = 'umrah' THEN CONCAT(ub.name)
                            WHEN st.transaction_of = 'umrah_refund' THEN CONCAT(ubr.name)
                            WHEN st.transaction_of = 'hotel' THEN CONCAT(hb.title,hb.first_name, hb.last_name)
                            WHEN st.transaction_of = 'fund' THEN CONCAT(usr.name)
                            WHEN st.transaction_of = 'hotel_refund' THEN CONCAT(hb.title,hb.first_name, hb.last_name)
                            WHEN st.transaction_of = 'jv_payment' THEN CONCAT(jv.jv_name)
                ELSE st.reference_id
            END AS reference_name
          FROM supplier_transactions st
          LEFT JOIN ticket_bookings tb ON st.reference_id = tb.id AND st.transaction_of = 'ticket_sale'
          LEFT JOIN ticket_reservations tr ON st.reference_id = tr.id AND st.transaction_of = 'ticket_reserve'
          LEFT JOIN ticket_weights tw ON st.reference_id = tw.id AND st.transaction_of = 'weight_sale'
          LEFT JOIN ticket_bookings tbt ON tw.ticket_id = tbt.id AND st.transaction_of = 'weight_sale'
          LEFT JOIN visa_applications vs ON st.reference_id = vs.id AND st.transaction_of = 'visa_sale'
          LEFT JOIN refunded_tickets rt ON st.reference_id = rt.id AND st.transaction_of = 'ticket_refund'
          LEFT JOIN date_change_tickets dc ON st.reference_id = dc.id AND st.transaction_of = 'date_change'
          LEFT JOIN umrah_transactions ut ON st.reference_id = ut.id AND st.transaction_of = 'umrah'
          LEFT JOIN umrah_bookings ub ON (ut.umrah_booking_id = ub.booking_id OR (ut.id IS NULL AND st.reference_id = ub.booking_id))
          LEFT JOIN umrah_refunds ur ON st.reference_id = ur.id AND st.transaction_of = 'umrah_refund'
          LEFT JOIN umrah_bookings ubr ON ur.booking_id = ubr.booking_id
          LEFT JOIN hotel_bookings hb ON st.reference_id = hb.id AND st.transaction_of = 'hotel'
          LEFT JOIN hotel_refunds hr ON st.reference_id = hr.id AND st.transaction_of = 'hotel_refund'
          LEFT JOIN users usr ON usr.id = st.reference_id AND st.transaction_of = 'fund'
          LEFT JOIN jv_payments jv ON jv.id = st.reference_id AND st.transaction_of = 'jv_payment'
          WHERE st.supplier_id = ?
          ORDER BY st.id DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $supplierId);
$stmt->execute();
$result = $stmt->get_result();

// Fetch all transactions
$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}

// Close connection
$stmt->close();
$conn->close();

// Return transactions as JSON
header('Content-Type: application/json');
echo json_encode($transactions);