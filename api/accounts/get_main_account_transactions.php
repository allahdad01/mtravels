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

// Validate account_id parameter
if (!isset($_GET['account_id']) || !is_numeric($_GET['account_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid account ID']);
    exit();
}

$accountId = intval($_GET['account_id']);

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
$whereConditions = ["mt.main_account_id = ? AND mt.tenant_id = ? AND mt.branch_id = ?"];
$params = [$accountId, $tenant_id, $branch_id];

if ($currency) {
    $whereConditions[] = "mt.currency = ?";
    $params[] = $currency;
}

if ($receipt) {
    $whereConditions[] = "mt.receipt LIKE ?";
    $params[] = '%' . $receipt . '%';
}

if ($startDate && $endDate) {
    $whereConditions[] = "DATE(mt.created_at) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

$whereClause = implode(' AND ', $whereConditions);

// Count total transactions with filters
$countQuery = "SELECT COUNT(*) as total FROM main_account_transactions mt
              WHERE " . $whereClause;
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalTransactions = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalTransactions / $perPage);

// Prepare and execute query with pagination and filters
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
          WHERE " . $whereClause . "
          ORDER BY mt.id DESC
          LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($query);
// Add LIMIT and OFFSET parameters
$allParams = array_merge($params, [$perPage, $offset]);
$stmt->execute($allParams);

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
