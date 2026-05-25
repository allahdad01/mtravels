<?php
// Database connection
require_once('../includes/db.php');

// Get the user ID from the session
$user_id = $_SESSION["user_id"];
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Pagination settings
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Search functionality
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
$search_params = [$tenant_id, $branch_id];

if (!empty($search_query)) {
    $search_condition = " AND (
        rt.passenger_name LIKE ? OR
        rt.pnr LIKE ? OR
        rt.phone LIKE ? OR
        c.name LIKE ? OR
        rt.airline LIKE ? OR
        rt.origin LIKE ? OR
        rt.destination LIKE ?
    )";
    $search_param = '%' . $search_query . '%';
    $search_params = [
        $tenant_id, 
        $branch_id, 
        $search_param, 
        $search_param, 
        $search_param, 
        $search_param, 
        $search_param, 
        $search_param, 
        $search_param
    ];
}

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM refunded_tickets rt 
              LEFT JOIN clients c ON rt.sold_to = c.id 
              WHERE rt.tenant_id = ? AND rt.branch_id = ?" . $search_condition;
$stmt = $pdo->prepare($countQuery);
$stmt->execute($search_params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $items_per_page);

$ticketsQuery = "
    SELECT
       rt.*,
       rt.supplier_penalty AS refund_supplier_penalty,
       rt.service_penalty AS refund_service_penalty,
       rt.refund_to_passenger,
       rt.status AS refund_status,
       rt.remarks AS refund_remarks,

       s.name AS supplier_name,
       c.name AS sold_to_name,
       ma.name AS paid_to_name,
       u.name AS created_by
    FROM
       refunded_tickets rt
    LEFT JOIN
       suppliers s ON rt.supplier = s.id AND s.tenant_id = rt.tenant_id AND s.branch_id = rt.branch_id
    LEFT JOIN
       clients c ON rt.sold_to = c.id AND c.tenant_id = rt.tenant_id AND c.branch_id = rt.branch_id
    LEFT JOIN
       main_account ma ON rt.paid_to = ma.id AND ma.tenant_id = rt.tenant_id AND ma.branch_id = rt.branch_id
    LEFT JOIN
       users u ON rt.created_by = u.id AND u.tenant_id = rt.tenant_id AND u.branch_id = rt.branch_id
    WHERE rt.tenant_id = ? AND rt.branch_id = ?" . $search_condition . "
    ORDER BY
       rt.id DESC
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($ticketsQuery);
// Add search params if exists, then add pagination params
$params = $search_params;
$params[] = $items_per_page;
$params[] = $offset;
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Suppliers
$suppliersQuery = "SELECT id, name FROM suppliers WHERE tenant_id = ? AND branch_id = ? AND status = 'active' AND category IN ('ticket', 'all')";
$stmt = $pdo->prepare($suppliersQuery);
$stmt->execute([$tenant_id, $branch_id]);
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create an associative array of supplier id to supplier name for easy lookup
$supplier_names = [];
foreach ($suppliers as $supplier) {
    $supplier_names[$supplier['id']] = $supplier['name'];
}
?>