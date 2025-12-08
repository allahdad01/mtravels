<?php
// Database connection
require_once('../includes/db.php');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Get the user ID from the session
$user_id = $_SESSION["user_id"];
$ticketsQuery = "
    SELECT
        rt.*,
        rt.supplier_penalty AS refund_supplier_penalty,
        rt.service_penalty AS refund_service_penalty,

        rt.status AS refund_status,
        rt.remarks AS refund_remarks,

        s.name AS supplier_name,
        c.name AS sold_to_name,
        ma.name AS paid_to_name,
        tb.departure_date AS old_departure_date,
        u.name AS created_by
    FROM
        date_change_tickets rt
    LEFT JOIN
        suppliers s ON rt.supplier = s.id AND s.tenant_id = ? AND s.branch_id = ?
    LEFT JOIN
        clients c ON rt.sold_to = c.id AND c.tenant_id = ? AND c.branch_id = ?
    LEFT JOIN
        main_account ma ON rt.paid_to = ma.id AND ma.tenant_id = ? AND ma.branch_id = ?
    LEFT JOIN
        ticket_bookings tb ON rt.ticket_id = tb.id AND tb.tenant_id = ? AND tb.branch_id = ?
    LEFT JOIN
        users u ON rt.created_by = u.id AND u.tenant_id = ? AND u.branch_id = ?
    WHERE rt.tenant_id = ? AND rt.branch_id = ?
    ORDER BY
        rt.id ASC
 ";

$stmt = $pdo->prepare($ticketsQuery);
$stmt->execute([$tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Fetch Suppliers
$suppliersQuery = "SELECT id, name FROM suppliers WHERE tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($suppliersQuery);
$stmt->execute([$tenant_id, $branch_id]);
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create an associative array of supplier id to supplier name for easy lookup
$supplier_names = [];
foreach ($suppliers as $supplier) {
    $supplier_names[$supplier['id']] = $supplier['name'];
}
?>