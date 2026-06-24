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

// Validate client_id parameter
if (!isset($_GET['client_id']) || !is_numeric($_GET['client_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid client ID']);
    exit();
}

$clientId = intval($_GET['client_id']);

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Database connection
require_once('../../includes/db.php');

// Get filter parameters
$currency = isset($_GET['currency']) && $_GET['currency'] !== 'all' ? $_GET['currency'] : null;
$receipt = isset($_GET['receipt']) ? trim($_GET['receipt']) : null;
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : null;
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : null;

// Build WHERE clause with filters
$whereConditions = ["ct.client_id = ? AND ct.tenant_id = ? AND ct.branch_id = ?"];
$params = [$clientId, $tenant_id, $branch_id];

if ($currency) {
    $whereConditions[] = "ct.currency = ?";
    $params[] = $currency;
}

if ($receipt) {
    $whereConditions[] = "ct.receipt LIKE ?";
    $params[] = '%' . $receipt . '%';
}

if ($startDate && $endDate) {
    $whereConditions[] = "DATE(ct.created_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

$whereClause = implode(' AND ', $whereConditions);

// Count total transactions with filters
try {
    $countQuery = "SELECT COUNT(*) as total FROM client_transactions ct
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

// Prepare and execute query with left joins to fetch reference information
$query = "SELECT ct.*, 
            CASE 
                WHEN ct.transaction_of = 'ticket_sale' THEN CONCAT(tb.passenger_name,' Sector: ', tb.origin,'-',tb.destination,' PNR: ', tb.pnr) 
                WHEN ct.transaction_of = 'ticket_reserve' THEN CONCAT(tr.passenger_name,' Sector: ', tr.origin,'-',tr.destination,' PNR: ', tr.pnr) 
                WHEN ct.transaction_of = 'ticket_refund' THEN CONCAT(rt.passenger_name,' Sector: ', rt.origin,'-',rt.destination,' PNR: ', rt.pnr) 
                WHEN ct.transaction_of = 'date_change' THEN CONCAT(dc.passenger_name,' Sector: ', dc.origin,'-',dc.destination,' PNR: ', dc.pnr) 
                WHEN ct.transaction_of = 'weight_sale' THEN CONCAT(tbt.passenger_name,' Sector: ', tbt.origin,'-',tbt.destination,' PNR: ', tbt.pnr)
                WHEN ct.transaction_of = 'visa_sale' THEN CONCAT(vs.applicant_name) 
                WHEN ct.transaction_of = 'visa_refund' THEN CONCAT(vr_app.applicant_name)
                WHEN ct.transaction_of = 'umrah' THEN CONCAT(ub.name)
                WHEN ct.transaction_of = 'umrah_transaction' THEN CONCAT(ub_ut.name)
                WHEN ct.transaction_of = 'umrah_refund' THEN CONCAT(ur_book.name)
                WHEN ct.transaction_of = 'hotel' THEN CONCAT(hb.title, ' ', hb.first_name, ' ', hb.last_name)
                WHEN ct.transaction_of = 'hotel_refund' THEN CONCAT(hr_book.title, ' ', hr_book.first_name, ' ', hr_book.last_name)
                WHEN ct.transaction_of = 'fund' THEN CONCAT(usr.name) 
                WHEN ct.transaction_of = 'client_withdrawal' THEN CONCAT(usr.name) 
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
          LEFT JOIN visa_refunds vr ON ct.reference_id = vr.id AND ct.transaction_of = 'visa_refund' AND vr.tenant_id = ? AND vr.branch_id = ?
          LEFT JOIN visa_applications vr_app ON vr.visa_id = vr_app.id AND ct.transaction_of = 'visa_refund' AND vr_app.tenant_id = ? AND vr_app.branch_id = ?
          LEFT JOIN refunded_tickets rt ON ct.reference_id = rt.id AND ct.transaction_of = 'ticket_refund' AND rt.tenant_id = ? AND rt.branch_id = ?
          LEFT JOIN date_change_tickets dc ON ct.reference_id = dc.id AND ct.transaction_of = 'date_change' AND dc.tenant_id = ? AND dc.branch_id = ?
          LEFT JOIN umrah_bookings ub ON ct.reference_id = ub.booking_id AND ct.transaction_of = 'umrah' AND ub.tenant_id = ? AND ub.branch_id = ?
          LEFT JOIN umrah_transactions ut ON ct.transaction_of = 'umrah_transaction' AND ct.reference_id = ut.id
          LEFT JOIN umrah_bookings ub_ut ON ut.umrah_booking_id = ub_ut.booking_id
          LEFT JOIN umrah_refunds ur ON ct.reference_id = ur.id AND ct.transaction_of = 'umrah_refund' AND ur.tenant_id = ? AND ur.branch_id = ?
          LEFT JOIN umrah_bookings ur_book ON ur.booking_id = ur_book.booking_id AND ct.transaction_of = 'umrah_refund' AND ur_book.tenant_id = ? AND ur_book.branch_id = ?
          LEFT JOIN hotel_bookings hb ON ct.reference_id = hb.id AND ct.transaction_of = 'hotel' AND hb.tenant_id = ? AND hb.branch_id = ?
          LEFT JOIN hotel_refunds hr ON ct.reference_id = hr.id AND ct.transaction_of = 'hotel_refund' AND hr.tenant_id = ? AND hr.branch_id = ?
          LEFT JOIN hotel_bookings hr_book ON hr.booking_id = hr_book.id AND ct.transaction_of = 'hotel_refund' AND hr_book.tenant_id = ? AND hr_book.branch_id = ?
          LEFT JOIN users usr ON usr.id = ct.reference_id AND ct.transaction_of IN ('fund', 'client_withdrawal') AND usr.tenant_id = ? AND usr.branch_id = ?
          LEFT JOIN jv_transactions jvt ON jvt.id = ct.reference_id AND ct.transaction_of = 'jv_payment' AND jvt.tenant_id = ? AND jvt.branch_id = ?
          LEFT JOIN jv_payments jv ON jv.id = jvt.jv_payment_id AND ct.transaction_of = 'jv_payment' AND jv.tenant_id = ? AND jv.branch_id = ?
          LEFT JOIN additional_payments ap ON ap.id = ct.reference_id AND ct.transaction_of = 'additional_payment' AND ap.tenant_id = ? AND ap.branch_id = ?
          WHERE " . $whereClause . "
          ORDER BY ct.id DESC
          LIMIT ? OFFSET ?";

try {
    $stmt = $pdo->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . implode(", ", $pdo->errorInfo()));
    }

    // Prepare parameters array with tenant/branch from joins
     $joinParams = [];
     for ($i = 0; $i < 19; $i++) {
         $joinParams[] = $tenant_id;
         $joinParams[] = $branch_id;
     }

    // Merge join params with filter params and LIMIT/OFFSET
    $allParams = array_merge($joinParams, $params, [$perPage, $offset]);
    
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
