<?php
// Database connection
require_once('../includes/db.php');
include '../includes/conn.php';

$tenant_id = $_SESSION['tenant_id'];
$user_id   = $_SESSION["user_id"];

// Pagination
$results_per_page = 10;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $results_per_page;

// Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchCondition = "";
$searchParams    = [];
$searchTypes     = "";

// Build search condition dynamically
if (!empty($search)) {
    $searchTerm = "%{$search}%";
    $searchCondition = " AND (
        tb.pnr LIKE ? OR 
        tb.passenger_name LIKE ? OR 
        tb.airline LIKE ? OR 
        tb.phone LIKE ? OR 
        tb.title LIKE ? OR 
        tb.origin LIKE ? OR 
        tb.destination LIKE ?
    )";
    $searchParams = array_fill(0, 7, $searchTerm);
    $searchTypes  = str_repeat("s", 7);
}

// Count total
$totalCountQuery = "SELECT COUNT(*) as total 
                    FROM ticket_bookings tb 
                    WHERE tb.tenant_id = ? $searchCondition";
$stmtCount = $conn->prepare($totalCountQuery);

if (!empty($searchCondition)) {
    $stmtCount->bind_param("s".$searchTypes, $tenant_id, ...$searchParams);
} else {
    $stmtCount->bind_param("s", $tenant_id);
}
$stmtCount->execute();
$totalTickets = $stmtCount->get_result()->fetch_assoc()['total'];
$total_pages  = ceil($totalTickets / $results_per_page);
$stmtCount->close();

// Main query
$ticketsQuery = "
    SELECT 
        tb.id, tb.supplier, tb.sold_to, tb.title, tb.passenger_name, tb.pnr, tb.airline, 
        tb.origin, tb.destination, tb.issue_date, tb.departure_date, tb.sold, tb.price, 
        tb.profit, tb.gender, tb.currency, tb.phone, tb.description, tb.status, 
        tb.trip_type, tb.return_date, tb.return_origin, tb.return_destination,
        
        s.name as supplier_name,
        c.name as sold_to_name,
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
        dct.remarks AS date_change_remarks
    FROM ticket_bookings tb
    LEFT JOIN refunded_tickets rt ON tb.id = rt.ticket_id
    LEFT JOIN date_change_tickets dct ON tb.id = dct.ticket_id
    LEFT JOIN suppliers s ON tb.supplier = s.id
    LEFT JOIN clients c   ON tb.sold_to = c.id
    LEFT JOIN main_account ma ON tb.paid_to = ma.id
    LEFT JOIN users u ON tb.created_by = u.id
    WHERE tb.tenant_id = ? $searchCondition
    ORDER BY tb.id DESC
    LIMIT ?, ?
";

$stmt = $conn->prepare($ticketsQuery);

// Bind params
if (!empty($searchCondition)) {
    // Add tenant_id, offset, and limit to params
    $params = array_merge([$tenant_id], $searchParams, [$offset, $results_per_page]);
    $types  = "s" . $searchTypes . "ii";

    // Prepare array for bind_param (needs references)
    $bind_names[] = $types;
    foreach ($params as $key => $value) {
        $bind_names[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $bind_names);

} else {
    $stmt->bind_param("sii", $tenant_id, $offset, $results_per_page);
}

$stmt->execute();
$ticketsResult = $stmt->get_result();

// Build result
$tickets = [];
while ($row = $ticketsResult->fetch_assoc()) {
    $ticket_id = $row['id'];
    if (!isset($tickets[$ticket_id])) {
        $tickets[$ticket_id] = [
            'ticket' => [
                'id' => $row['id'],
                'supplier_name' => $row['supplier_name'],
                'sold_to' => $row['sold_to_name'],
                'paid_to' => $row['paid_to_name'],
                'title' => $row['title'],
                'passenger_name' => $row['passenger_name'],
                'pnr' => $row['pnr'],
                'airline' => $row['airline'],
                'origin' => $row['origin'],
                'destination' => $row['destination'],
                'issue_date' => $row['issue_date'],
                'departure_date' => $row['departure_date'],
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
                'return_origin' => $row['return_origin'],
                'return_destination' => $row['return_destination'],
                'created_by_name' => $row['created_by_name']
            ],
            'refund_data' => null,
            'date_change_data' => null
        ];
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
$stmt->close();

// Fetch Suppliers
$suppliersQuery = "SELECT id, name FROM suppliers WHERE status = 'active' AND tenant_id = ?";
$stmt = $conn->prepare($suppliersQuery);
$stmt->bind_param("s", $tenant_id);
$stmt->execute();
$suppliersResult = $stmt->get_result();
$suppliers = $suppliersResult->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Supplier names map
$supplier_names = [];
foreach ($suppliers as $supplier) {
    $supplier_names[$supplier['id']] = $supplier['name'];
}
?>
