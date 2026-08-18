<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// Set JSON response header
header('Content-Type: application/json; charset=utf-8');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Database connection
require_once '../../includes/db.php';

// Pagination
$results_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $results_per_page;

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM ticket_bookings WHERE tenant_id = ? AND branch_id = ?";
$stmtCount = $pdo->prepare($countQuery);
$stmtCount->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmtCount->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmtCount->execute();
$totalTickets = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($totalTickets / $results_per_page);

// Main query
$ticketsQuery = "
    SELECT
        tb.id, tb.supplier, tb.sold_to, tb.title, tb.passenger_name, tb.pnr, tb.airline,
        tb.origin, tb.destination, tb.issue_date, tb.departure_date, tb.departure_time, tb.sold, tb.price,
        tb.profit, tb.gender, tb.currency, tb.phone, tb.description, tb.status,
        tb.trip_type, tb.return_date, tb.return_departure_time, tb.return_origin, tb.return_destination,
        tb.flight_legs, tb.return_flight_legs,

        s.name as supplier_name,
        c.name as sold_to_name,
        c.client_type,
        ma.name as paid_to_name,
        u.name as created_by_name,
        rt.supplier_penalty AS refund_supplier_penalty,
        rt.service_penalty AS refund_service_penalty,
        rt.refund_to_passenger,
        rt.currency AS refund_currency,
        rt.status AS refund_status,
        rt.remarks AS refund_remarks,
        dct.departure_date AS date_change_departure_date,
        dct.currency AS date_change_currency,
        dct.supplier_penalty AS date_change_supplier_penalty,
        dct.service_penalty AS date_change_service_penalty,
        dct.status AS date_change_status,
        dct.remarks AS date_change_remarks,
        (SELECT COUNT(*) FROM ticket_weights WHERE ticket_id = tb.id AND tenant_id = tb.tenant_id AND branch_id = tb.branch_id) as weight_count,
        (SELECT COALESCE(SUM(weight), 0) FROM ticket_weights WHERE ticket_id = tb.id AND tenant_id = tb.tenant_id AND branch_id = tb.branch_id) as total_weight
    FROM ticket_bookings tb
    LEFT JOIN refunded_tickets rt ON tb.id = rt.ticket_id AND rt.tenant_id = tb.tenant_id AND rt.branch_id = tb.branch_id
    LEFT JOIN date_change_tickets dct ON tb.id = dct.ticket_id AND dct.tenant_id = tb.tenant_id AND dct.branch_id = tb.branch_id
    LEFT JOIN suppliers s ON tb.supplier = s.id AND s.tenant_id = tb.tenant_id AND s.branch_id = tb.branch_id
    LEFT JOIN clients c   ON tb.sold_to = c.id AND c.tenant_id = tb.tenant_id AND c.branch_id = tb.branch_id
    LEFT JOIN main_account ma ON tb.paid_to = ma.id AND ma.tenant_id = tb.tenant_id AND ma.branch_id = tb.branch_id
    LEFT JOIN users u ON tb.created_by = u.id AND u.tenant_id = tb.tenant_id AND u.branch_id = tb.branch_id
    WHERE tb.tenant_id = ? AND tb.branch_id = ?
    ORDER BY tb.id DESC
    LIMIT ?, ?
";

$stmt = $pdo->prepare($ticketsQuery);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->bindParam(3, $offset, PDO::PARAM_INT);
$stmt->bindParam(4, $results_per_page, PDO::PARAM_INT);
$stmt->execute();
$ticketsResult = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build result
$tickets = [];
foreach ($ticketsResult as $row) {
    $ticket_id = $row['id'];
    if (!isset($tickets[$ticket_id])) {
        $tickets[$ticket_id] = [
            'ticket' => [
                'id' => $row['id'],
                'supplier_name' => $row['supplier_name'],
                'sold_to' => $row['sold_to_name'],
                'paid_to' => $row['paid_to_name'],
                'client_type' => $row['client_type'],
                'title' => $row['title'],
                'passenger_name' => $row['passenger_name'],
                'pnr' => $row['pnr'],
                'airline' => $row['airline'],
                'origin' => $row['origin'],
                'destination' => $row['destination'],
                'issue_date' => $row['issue_date'],
                'departure_date' => $row['departure_date'],
                'departure_time' => $row['departure_time'],
                'sold' => $row['sold'],
                'price' => $row['price'],
                'profit' => $row['profit'],
                'gender' => $row['gender'],
                'currency' => $row['currency'],
                'phone' => $row['phone'],
                'description' => $row['description'],
                'status' => $row['status'],
                'trip_type' => $row['trip_type'],
                'return_date' => $row['return_date'],
                'return_departure_time' => $row['return_departure_time'],
                'return_origin' => $row['return_origin'],
                'return_destination' => $row['return_destination'],
                'created_by_name' => $row['created_by_name'],
                'flight_legs' => $row['flight_legs'],
                'return_flight_legs' => $row['return_flight_legs'],
                'route' => null,
                'weight_count' => $row['weight_count'],
                'total_weight' => $row['total_weight']
            ],
            'refund_data' => null,
            'date_change_data' => null
        ];

        // Build full route string when multi-leg itinerary is present
        if (!empty($row['flight_legs'])) {
            $legs = json_decode($row['flight_legs'], true);
            if (is_array($legs) && count($legs) > 1) {
                $cities = [];
                foreach ($legs as $leg) {
                    if (!empty($leg['origin'])) $cities[] = $leg['origin'];
                }
                $lastLeg = $legs[count($legs) - 1];
                if (!empty($lastLeg['destination'])) $cities[] = $lastLeg['destination'];
                if (count($cities) > 1) {
                    $tickets[$ticket_id]['ticket']['route'] = implode(' → ', $cities);
                }
            }
        }
    }

    if (!empty($row['refund_supplier_penalty']) || !empty($row['refund_service_penalty'])) {
        $tickets[$ticket_id]['refund_data'] = [
            'supplier_penalty' => $row['refund_supplier_penalty'],
            'service_penalty' => $row['refund_service_penalty'],
            'refund_to_passenger' => $row['refund_to_passenger'],
            'currency' => $row['refund_currency'],
            'remarks' => $row['refund_remarks']
        ];
    }

    if (!empty($row['date_change_departure_date'])) {
        $tickets[$ticket_id]['date_change_data'] = [
            'departure_date' => $row['date_change_departure_date'],
            'currency' => $row['date_change_currency'],
            'supplier_penalty' => $row['date_change_supplier_penalty'],
            'service_penalty' => $row['date_change_service_penalty'],
            'remarks' => $row['date_change_remarks']
        ];
    }
}

// Fetch payment data for each ticket
foreach ($tickets as &$ticket) {
    $ticketId = $ticket['ticket']['id'];
    $clientType = $ticket['ticket']['client_type'] ?? null;
    
    if ($clientType === 'agency') {
        // Get transactions for this ticket
        $transactionStmt = $pdo->prepare("
            SELECT amount, currency, exchange_rate 
            FROM main_account_transactions 
            WHERE transaction_of = 'ticket_sale' 
            AND reference_id = ? 
            AND tenant_id = ? 
            AND branch_id = ?
        ");
        $transactionStmt->bindParam(1, $ticketId, PDO::PARAM_INT);
        $transactionStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $transactionStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $transactionStmt->execute();
        $transactions = $transactionStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $ticket['transactions'] = $transactions;
    } else {
        $ticket['transactions'] = [];
    }
}
unset($ticket);

echo json_encode([
    'status' => 'success',
    'tickets' => array_values($tickets),
    'total' => $totalTickets,
    'total_pages' => $total_pages,
    'current_page' => $page
]);
?>
