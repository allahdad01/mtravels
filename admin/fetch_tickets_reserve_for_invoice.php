<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

include '../includes/conn.php';

// Check if the user is logged in
if (!isset($_SESSION['name'])) {
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in to access this resource']);
    exit;
}
$tenant_id = $_SESSION['tenant_id'];
try {
    // Query to get tickets
    $query = "SELECT tb.id, tb.passenger_name, tb.pnr, tb.origin, tb.destination, 
              tb.airline, tb.departure_date, tb.sold, tb.trip_type, tb.return_destination, 
              tb.return_date, c.name as client_name
              FROM ticket_reservations tb
              JOIN clients c ON tb.sold_to = c.id
              WHERE tb.tenant_id = ?
              ORDER BY tb.id DESC
              LIMIT 100";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $tickets = [];
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
    
    echo json_encode(['status' => 'success', 'tickets' => $tickets]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?> 