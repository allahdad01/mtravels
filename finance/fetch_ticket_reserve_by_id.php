<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Database connection
require_once '../includes/conn.php';
$tenant_id = $_SESSION['tenant_id'];

// Get ticket ID
$ticketId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$ticketId) {
    die(json_encode(['success' => false, 'message' => 'Missing ticket ID']));
}

// Query to get ticket data
$query = "SELECT t.*, 
          s.name AS supplier_name, 
          c.name AS client_name,
          m.name AS paid_to_name
          FROM ticket_reservations t
          LEFT JOIN suppliers s ON t.supplier = s.id
          LEFT JOIN clients c ON t.sold_to = c.id
          LEFT JOIN main_account m ON t.paid_to = m.id
          WHERE t.id = ? AND t.tenant_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $ticketId, $tenant_id);
$stmt->execute();
$result = $stmt->get_result();

if ($ticket = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'ticket' => $ticket
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Ticket not found']);
}

$stmt->close();
$conn->close();
?>