<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once '../includes/conn.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch Tickets
$ticketsQuery = "SELECT * FROM ticket_bookings where tenant_id = ?";
$ticketsResult = $conn->query($ticketsQuery, [$tenant_id]);
$tickets = $ticketsResult->fetch_all(MYSQLI_ASSOC);

// Fetch Suppliers
$suppliersQuery = "SELECT id, name FROM suppliers where tenant_id = ?";
$suppliersResult = $conn->query($suppliersQuery, [$tenant_id]);
$suppliers = $suppliersResult->fetch_all(MYSQLI_ASSOC);

// Create an associative array of supplier id to supplier name for easy lookup
$supplier_names = [];
foreach ($suppliers as $supplier) {
    $supplier_names[$supplier['id']] = $supplier['name'];
}

// Now, for each ticket, add the supplier's name based on the supplier ID
foreach ($tickets as $key => $ticket) {
    $supplier_id = $ticket['supplier'];  // The supplier ID in the ticket record
    $tickets[$key]['supplier_name'] = isset($supplier_names[$supplier_id]) ? $supplier_names[$supplier_id] : 'Unknown';
}
?>
