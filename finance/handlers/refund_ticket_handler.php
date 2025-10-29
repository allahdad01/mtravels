<?php
// Database connection
require_once('../includes/db.php');

require_once '../includes/conn.php';

// Get the user ID from the session
$user_id = $_SESSION["user_id"];
$tenant_id = $_SESSION['tenant_id'];
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
       suppliers s ON rt.supplier = s.id
    LEFT JOIN 
       clients c ON rt.sold_to = c.id
    LEFT JOIN 
       main_account ma ON rt.paid_to = ma.id
    LEFT JOIN
       users u ON rt.created_by = u.id
    WHERE rt.tenant_id = ?
    ORDER BY 
       rt.id DESC
";
$stmt = $conn->prepare($ticketsQuery);
$stmt->execute([$tenant_id]);
$ticketsResult = $stmt->get_result();

// Initialize the array to hold ticket details
$tickets = [];

if ($ticketsResult && $ticketsResult->num_rows > 0) {
    // Fetch results and push them into the array
    while ($row = $ticketsResult->fetch_assoc()) {
        $tickets[] = $row;
    }
}
// Fetch Suppliers
$suppliersQuery = "SELECT id, name FROM suppliers WHERE tenant_id = ?";
$stmt = $conn->prepare($suppliersQuery);
$stmt->execute([$tenant_id]);
$suppliersResult = $stmt->get_result();
$suppliers = $suppliersResult->fetch_all(MYSQLI_ASSOC);

// Create an associative array of supplier id to supplier name for easy lookup
$supplier_names = [];
foreach ($suppliers as $supplier) {
    $supplier_names[$supplier['id']] = $supplier['name'];
}
?>