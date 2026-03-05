<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

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

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Database connection
require_once('../../includes/db.php');

// Get filter parameters
$receipt = isset($_GET['receipt']) ? trim($_GET['receipt']) : null;
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : null;
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : null;

// Build WHERE clause with filters
$whereConditions = ["st.supplier_id = ? AND st.tenant_id = ? AND st.branch_id = ?"];
$params = [$supplierId, $tenant_id, $branch_id];

if ($receipt) {
    $whereConditions[] = "st.receipt LIKE ?";
    $params[] = '%' . $receipt . '%';
}

if ($startDate && $endDate) {
    $whereConditions[] = "DATE(st.transaction_date) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

$whereClause = implode(' AND ', $whereConditions);

// Count total transactions with filters
try {
    $countQuery = "SELECT COUNT(*) as total FROM supplier_transactions st
                  WHERE " . $whereClause;
    $countStmt = $pdo->prepare($countQuery);
    if (!$countStmt) {
        throw new Exception("Failed to prepare count statement: " . implode(", ", $pdo->errorInfo()));
    }
    if (!$countStmt->execute($params)) {
        throw new Exception("Failed to execute count statement: " . implode(", ", $countStmt->errorInfo()));
    }
    $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $totalTransactions = $countResult ? $countResult['total'] : 0;
    $totalPages = ceil($totalTransactions / $perPage);
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Count query failed: ' . $e->getMessage()]);
    exit();
}

// Prepare and execute query with left joins to fetch reference information and filters
$query = "SELECT st.*,
            CASE
                            WHEN st.transaction_of = 'ticket_sale' THEN CONCAT(tb.passenger_name)
                            WHEN st.transaction_of = 'ticket_reserve' THEN CONCAT(tr.passenger_name)
                            WHEN st.transaction_of = 'ticket_refund' THEN CONCAT(rt.passenger_name)
                            WHEN st.transaction_of = 'date_change' THEN CONCAT(dc.passenger_name)
                            WHEN st.transaction_of = 'weight_sale' THEN CONCAT(tbt.passenger_name)
                            WHEN st.transaction_of = 'visa_sale' THEN CONCAT(vs.applicant_name)
                            WHEN st.transaction_of = 'visa_refund' THEN CONCAT(va.applicant_name)
                            WHEN st.transaction_of = 'umrah' THEN CONCAT(ub.name)
                            WHEN st.transaction_of = 'umrah_transaction' THEN CONCAT(ub_ut.name)
                            WHEN st.transaction_of = 'umrah_refund' THEN CONCAT(ubr.name)
                            WHEN st.transaction_of = 'hotel' THEN CONCAT(hb.title,hb.first_name, hb.last_name)
                            WHEN st.transaction_of = 'hotel_refund' THEN CONCAT(hbr.title,hbr.first_name, hbr.last_name)
                            WHEN st.transaction_of = 'fund' THEN CONCAT(usr.name)
                            WHEN st.transaction_of = 'jv_payment' THEN CONCAT(jv.jv_name)
                            WHEN st.transaction_of = 'additional_payment' THEN CONCAT(ap.payment_type)
                ELSE st.reference_id
            END AS reference_name
          FROM supplier_transactions st
          LEFT JOIN ticket_bookings tb ON st.reference_id = tb.id AND st.transaction_of = 'ticket_sale'
          LEFT JOIN ticket_reservations tr ON st.reference_id = tr.id AND st.transaction_of = 'ticket_reserve'
          LEFT JOIN ticket_weights tw ON st.reference_id = tw.id AND st.transaction_of = 'weight_sale'
          LEFT JOIN ticket_bookings tbt ON tw.ticket_id = tbt.id AND st.transaction_of = 'weight_sale'
          LEFT JOIN visa_applications vs ON st.reference_id = vs.id AND st.transaction_of = 'visa_sale'
          LEFT JOIN visa_refunds vr ON st.reference_id = vr.id AND st.transaction_of = 'visa_refund'
          LEFT JOIN visa_applications va ON va.id = vr.visa_id
          LEFT JOIN refunded_tickets rt ON st.reference_id = rt.id AND st.transaction_of = 'ticket_refund'
          LEFT JOIN date_change_tickets dc ON st.reference_id = dc.id AND st.transaction_of = 'date_change'
          LEFT JOIN umrah_bookings ub ON st.transaction_of = 'umrah' AND st.reference_id = ub.booking_id
          LEFT JOIN umrah_transactions ut ON st.transaction_of = 'umrah_transaction' AND st.reference_id = ut.id
          LEFT JOIN umrah_bookings ub_ut ON ut.umrah_booking_id = ub_ut.booking_id
          LEFT JOIN umrah_refunds ur ON st.transaction_of = 'umrah_refund' AND st.reference_id = ur.id
          LEFT JOIN umrah_bookings ubr ON ur.booking_id = ubr.booking_id
          LEFT JOIN hotel_bookings hb ON st.reference_id = hb.id AND st.transaction_of = 'hotel'
          LEFT JOIN hotel_refunds hr ON st.reference_id = hr.id AND st.transaction_of = 'hotel_refund'
          LEFT JOIN hotel_bookings hbr ON hbr.id = hr.booking_id
          LEFT JOIN users usr ON usr.id = st.reference_id AND st.transaction_of = 'fund'
          LEFT JOIN jv_payments jv ON jv.id = st.reference_id AND st.transaction_of = 'jv_payment'
          LEFT JOIN additional_payments ap ON ap.id = st.reference_id AND st.transaction_of = 'additional_payment'
          WHERE " . $whereClause . "
          ORDER BY st.id DESC
          LIMIT ? OFFSET ?";

try {
    $stmt = $pdo->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . implode(", ", $pdo->errorInfo()));
    }
    // Add LIMIT and OFFSET parameters
    $allParams = array_merge($params, [$perPage, $offset]);
    
    if (!$stmt->execute($allParams)) {
        throw new Exception("Failed to execute statement: " . implode(", ", $stmt->errorInfo()));
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit();
}

// Fetch transactions for current page
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return transactions as JSON with pagination metadata
header('Content-Type: application/json');
echo json_encode([
    'data' => $transactions,
    'pagination' => [
        'current_page' => $page,
        'per_page' => $perPage,
        'total' => $totalTransactions,
        'total_pages' => $totalPages,
        'offset' => $offset
    ]
]);