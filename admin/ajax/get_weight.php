<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../../includes/conn.php';

$tenant_id = $_SESSION['tenant_id'];

// Get weight ID from request
$weightId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($weightId <= 0) {
    die(json_encode([
        'success' => false,
        'message' => 'Invalid weight ID'
    ]));
}

// Query to get weight details with related information
$query = "
    SELECT 
        tw.*,
        t.passenger_name,
        t.pnr,
        t.airline,
        t.origin,
        t.destination,
        t.departure_date,
        t.currency,
        s.name AS supplier_name,
        c.name AS sold_to_name
    FROM 
        ticket_weights tw
    LEFT JOIN 
        ticket_bookings t ON tw.ticket_id = t.id
    LEFT JOIN 
        suppliers s ON t.supplier = s.id
    LEFT JOIN 
        clients c ON t.sold_to = c.id
    WHERE 
        tw.id = ? AND tw.tenant_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $weightId, $tenant_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'weight' => $row
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Weight not found'
    ]);
}

$stmt->close();
$conn->close(); 