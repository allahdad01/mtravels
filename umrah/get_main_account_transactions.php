<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Validate account_id parameter
if (!isset($_GET['account_id']) || !is_numeric($_GET['account_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid account ID']);
    exit();
}

$accountId = intval($_GET['account_id']);

// Database connection
require_once('../includes/conn.php');

// Prepare and execute query
$query = "SELECT mt.*, 
            CASE 
                            WHEN mt.transaction_of = 'ticket_sale' THEN CONCAT(tb.passenger_name) 
                            WHEN mt.transaction_of = 'ticket_reserve' THEN CONCAT(tr.passenger_name) 
                            WHEN mt.transaction_of = 'ticket_refund' THEN CONCAT(rt.passenger_name) 
                            WHEN mt.transaction_of = 'date_change' THEN CONCAT(dc.passenger_name) 
                            WHEN mt.transaction_of = 'visa_sale' THEN CONCAT(vs.applicant_name) 
                            WHEN mt.transaction_of = 'umrah' THEN CONCAT(ub.name)
                            WHEN mt.transaction_of = 'hotel' THEN CONCAT(hb.title,hb.first_name, hb.last_name)
                            WHEN mt.transaction_of = 'fund' THEN CONCAT(usr.name) 
                            WHEN mt.transaction_of = 'hotel_refund' THEN CONCAT(hb.title,hb.first_name, hb.last_name)
                ELSE mt.reference_id
            END AS reference_name
          FROM main_account_transactions mt
          LEFT JOIN ticket_bookings tb ON mt.reference_id = tb.id AND mt.transaction_of = 'ticket_sale'
          LEFT JOIN ticket_reservations tr ON mt.reference_id = tr.id AND mt.transaction_of = 'ticket_reserve'
          LEFT JOIN visa_applications vs ON mt.reference_id = vs.id AND mt.transaction_of = 'visa_sale'
          LEFT JOIN refunded_tickets rt ON mt.reference_id = rt.id AND mt.transaction_of = 'ticket_refund'
          LEFT JOIN date_change_tickets dc ON mt.reference_id = dc.id AND mt.transaction_of = 'date_change'
          LEFT JOIN umrah_bookings ub ON mt.reference_id = ub.booking_id AND mt.transaction_of = 'umrah'
          LEFT JOIN hotel_bookings hb ON mt.reference_id = hb.id AND mt.transaction_of = 'hotel'
          LEFT JOIN hotel_refunds hr ON mt.reference_id = hr.id AND mt.transaction_of = 'hotel_refund'
          LEFT JOIN users usr ON usr.id = mt.reference_id AND mt.transaction_of = 'fund'
          WHERE mt.main_account_id = ? AND mt.tenant_id = ?
          ORDER BY mt.id DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $accountId, $tenant_id);
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
