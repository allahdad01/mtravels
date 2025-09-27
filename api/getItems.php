<?php
// getItems.php
include '../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
$clientId = $_GET['clientId'];
$type = $_GET['type'];

$tableMap = [
    'ticket' => 'ticket_bookings',
    'refund_ticket' => 'refunded_tickets',
    'date_change_ticket' => 'date_change_tickets',
    'visa' => 'visa_applications'
];

// Mapping tables to the specific columns to select
$columnsMap = [
    'ticket' => ['passenger_name', 'pnr', 'issue_date', 'departure_date', 'sold'],
    'refund_ticket' => ['passenger_name', 'pnr', 'supplier_penalty', 'service_penalty', 'refund_to_passenger'],
    'date_change_ticket' => ['passenger_name', 'pnr', 'supplier_penalty', 'service_penalty'],
    'visa' => ['applicant_name', 'passport_number', 'receive_date', 'issued_date', 'sold']
];

$table = $tableMap[$type] ?? null;
$columns = $columnsMap[$type] ?? null;

if ($table && $columns) {
    // Create the SELECT part dynamically based on columns
    $columnsStr = implode(", ", $columns);
    
    // Add a subquery condition to filter out items already in the invoices table
    $stmt = $pdo->prepare(
        "SELECT id, $columnsStr 
         FROM $table 
         WHERE sold_to = ? 
         AND id NOT IN (
             SELECT reference_id 
             FROM invoices 
             WHERE type = ? 
             AND tenant_id = ?
         )"
    );
    $stmt->execute([$clientId, $type, $tenant_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $items = [];
}

header('Content-Type: application/json');
echo json_encode($items);
?>
