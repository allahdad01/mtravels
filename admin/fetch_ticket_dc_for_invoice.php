<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

include '../includes/conn.php';

// Check if the user is logged in
if (!isset($_SESSION['name'])) {
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in to access this resource']);
    exit;
}

try {
    // Query to get tickets with client information
    $query = "SELECT tb.id, tb.passenger_name, tb.pnr, tb.origin, tb.destination, 
              tb.airline, tb.departure_date, tb.sold,
              tb.service_penalty + tb.supplier_penalty as charges,
              c.name as sold_to_name
              FROM date_change_tickets tb
              LEFT JOIN clients c ON tb.sold_to = c.id
              WHERE tb.tenant_id = ?
              ORDER BY tb.id DESC
              LIMIT 100";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $tenant_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $result = $stmt->get_result();
    $tickets = [];
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
    $stmt->close();
    
    echo json_encode(['status' => 'success', 'tickets' => $tickets]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?> 