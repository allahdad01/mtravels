<?php
/**
 * Client Ticket Handler
 * Fetches tickets filtered by client_id only - read-only
 */

require_once '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

$tenant_id = $_SESSION['tenant_id'];
// For clients, client_id should be set. Fallback to user_id if not set
$client_id = $_SESSION['client_id'] ?? $_SESSION['user_id'];

// Pagination
$results_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $results_per_page;

// Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchCondition = '';
$searchParams = [];

if (!empty($search)) {
    $searchTerm = "%{$search}%";
    $searchCondition = " AND (
        tb.pnr LIKE ? OR 
        tb.passenger_name LIKE ? OR 
        tb.airline LIKE ? OR 
        tb.origin LIKE ? OR 
        tb.destination LIKE ?
    )";
    $searchParams = array_fill(0, 5, $searchTerm);
}

// Count total tickets for this client
$totalCountQuery = "
    SELECT COUNT(*) as total
    FROM ticket_bookings tb
    WHERE tb.tenant_id = ? AND tb.sold_to = ? $searchCondition
";

$stmtCount = $pdo->prepare($totalCountQuery);
$stmtCount->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmtCount->bindParam(2, $client_id, PDO::PARAM_INT);

$paramIndex = 3;
foreach ($searchParams as $param) {
    $stmtCount->bindParam($paramIndex++, $param, PDO::PARAM_STR);
}

$stmtCount->execute();
$totalTickets = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($totalTickets / $results_per_page);

// Main query - fetch tickets for this client only
$ticketsQuery = "
    SELECT
        tb.id, tb.supplier, tb.sold_to, tb.title, tb.passenger_name, tb.pnr, tb.airline,
        tb.origin, tb.destination, tb.issue_date, tb.departure_date, tb.departure_time, 
        tb.sold, tb.price, tb.profit, tb.currency, tb.phone, tb.description, tb.status,
        tb.trip_type, tb.return_date, tb.return_departure_time, tb.return_origin, tb.return_destination,
        tb.created_at,
        
        s.name as supplier_name,
        c.name as sold_to_name,
        u.name as created_by_name,
        rt.refund_to_passenger,
        rt.currency AS refund_currency,
        rt.status AS refund_status,
        dct.departure_date AS date_change_departure_date,
        dct.currency AS date_change_currency,
        dct.supplier_penalty AS date_change_supplier_penalty,
        dct.service_penalty AS date_change_service_penalty,
        (SELECT COUNT(*) FROM ticket_weights WHERE ticket_id = tb.id AND tenant_id = tb.tenant_id) as weight_count,
        (SELECT COALESCE(SUM(weight), 0) FROM ticket_weights WHERE ticket_id = tb.id AND tenant_id = tb.tenant_id) as total_weight
    FROM ticket_bookings tb
    LEFT JOIN refunded_tickets rt ON tb.id = rt.ticket_id AND rt.tenant_id = tb.tenant_id
    LEFT JOIN date_change_tickets dct ON tb.id = dct.ticket_id AND dct.tenant_id = tb.tenant_id
    LEFT JOIN suppliers s ON tb.supplier = s.id AND s.tenant_id = tb.tenant_id
    LEFT JOIN clients c ON tb.sold_to = c.id AND c.tenant_id = tb.tenant_id
    LEFT JOIN users u ON tb.created_by = u.id AND u.tenant_id = tb.tenant_id
    WHERE tb.tenant_id = ? AND tb.sold_to = ? $searchCondition
    ORDER BY tb.id DESC
    LIMIT ?, ?
";

$stmt = $pdo->prepare($ticketsQuery);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $client_id, PDO::PARAM_INT);

$paramIndex = 3;
foreach ($searchParams as $param) {
    $stmt->bindParam($paramIndex++, $param, PDO::PARAM_STR);
}

$stmt->bindParam($paramIndex++, $offset, PDO::PARAM_INT);
$stmt->bindParam($paramIndex++, $results_per_page, PDO::PARAM_INT);

$stmt->execute();
$ticketResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format results
$baseCurrency = 'USD';
$tickets = [];

if (!empty($ticketResults)) {
    foreach ($ticketResults as $row) {
        $ticket = [
            'ticket' => $row,
            'refund_data' => $row['refund_status'] ? [
                'refund_to_passenger' => $row['refund_to_passenger'],
                'currency' => $row['refund_currency'],
                'status' => $row['refund_status']
            ] : null,
            'date_change_data' => $row['date_change_status'] ? [
                'supplier_penalty' => $row['date_change_supplier_penalty'],
                'service_penalty' => $row['date_change_service_penalty'],
                'currency' => $row['date_change_currency'],
                'status' => $row['date_change_status']
            ] : null
        ];
        $tickets[] = $ticket;
    }
}
