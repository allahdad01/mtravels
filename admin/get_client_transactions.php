<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();



// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Validate client_id parameter
if (!isset($_GET['client_id']) || !is_numeric($_GET['client_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid client ID']);
    exit();
}

$clientId = intval($_GET['client_id']);

// Database connection
require_once('../includes/conn.php');

// Prepare and execute query with left joins to fetch reference information
$query = "SELECT ct.*, 
            CASE 
                WHEN ct.transaction_of = 'ticket_sale' THEN CONCAT(tb.passenger_name,' Sector: ', tb.origin,'-',tb.destination,' PNR: ', tb.pnr) 
                WHEN ct.transaction_of = 'ticket_reserve' THEN CONCAT(tr.passenger_name,' Sector: ', tr.origin,'-',tr.destination,' PNR: ', tr.pnr) 
                WHEN ct.transaction_of = 'ticket_refund' THEN CONCAT(rt.passenger_name,' Sector: ', rt.origin,'-',rt.destination,' PNR: ', rt.pnr) 
                WHEN ct.transaction_of = 'date_change' THEN CONCAT(dc.passenger_name,' Sector: ', dc.origin,'-',dc.destination,' PNR: ', dc.pnr) 
                WHEN ct.transaction_of = 'weight_sale' THEN CONCAT(tbt.passenger_name,' Sector: ', tbt.origin,'-',tbt.destination,' PNR: ', tbt.pnr)
                WHEN ct.transaction_of = 'visa_sale' THEN CONCAT(vs.applicant_name) 
                WHEN ct.transaction_of = 'umrah' THEN CONCAT(ub.name)
                WHEN ct.transaction_of = 'hotel' THEN CONCAT(hb.title, ' ', hb.first_name, ' ', hb.last_name)
                WHEN ct.transaction_of = 'fund' THEN CONCAT(usr.name) 
                WHEN ct.transaction_of = 'jv_payment' THEN CONCAT(jv.jv_name)
                WHEN ct.transaction_of = 'additional_payment' THEN CONCAT(ap.payment_type)
                ELSE ct.reference_id
            END AS reference_name
          FROM client_transactions ct
          LEFT JOIN ticket_bookings tb ON ct.reference_id = tb.id AND ct.transaction_of = 'ticket_sale' AND tb.tenant_id = ? AND tb.branch_id = ?
          LEFT JOIN ticket_reservations tr ON ct.reference_id = tr.id AND ct.transaction_of = 'ticket_reserve' AND tr.tenant_id = ? AND tr.branch_id = ?
          LEFT JOIN ticket_weights tw ON ct.reference_id = tw.id AND ct.transaction_of = 'weight_sale' AND tw.tenant_id = ? AND tw.branch_id = ?
          LEFT JOIN ticket_bookings tbt ON tw.ticket_id = tbt.id AND ct.transaction_of = 'weight_sale' AND tbt.tenant_id = ? AND tbt.branch_id = ?
          LEFT JOIN visa_applications vs ON ct.reference_id = vs.id AND ct.transaction_of = 'visa_sale' AND vs.tenant_id = ? AND vs.branch_id = ?
          LEFT JOIN refunded_tickets rt ON ct.reference_id = rt.id AND ct.transaction_of = 'ticket_refund' AND rt.tenant_id = ? AND rt.branch_id = ?
          LEFT JOIN date_change_tickets dc ON ct.reference_id = dc.id AND ct.transaction_of = 'date_change' AND dc.tenant_id = ? AND dc.branch_id = ?
          LEFT JOIN umrah_bookings ub ON ct.reference_id = ub.booking_id AND ct.transaction_of = 'umrah' AND ub.tenant_id = ? AND ub.branch_id = ?
          LEFT JOIN hotel_bookings hb ON ct.reference_id = hb.id AND ct.transaction_of = 'hotel' AND hb.tenant_id = ? AND hb.branch_id = ?
          LEFT JOIN users usr ON usr.id = ct.reference_id AND ct.transaction_of = 'fund' AND usr.tenant_id = ? AND usr.branch_id = ?
          LEFT JOIN jv_payments jv ON jv.id = ct.reference_id AND ct.transaction_of = 'jv_payment' AND jv.tenant_id = ? AND jv.branch_id = ?
          LEFT JOIN additional_payments ap ON ap.id = ct.reference_id AND ct.transaction_of = 'additional_payment' AND ap.tenant_id = ? AND ap.branch_id = ?
          WHERE ct.client_id = ? AND ct.tenant_id = ? AND ct.branch_id = ?
          ORDER BY ct.id DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("iiiiiiiiiiiiiiiiiiii", $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $clientId, $tenant_id, $branch_id);
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
