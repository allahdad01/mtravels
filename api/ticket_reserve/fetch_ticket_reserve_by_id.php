<?php
// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// Database connection
require_once '../../includes/db.php';

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

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
          WHERE t.id = ? AND t.tenant_id = ? AND t.branch_id = ?";

$stmt = $pdo->prepare($query);
$stmt->bindParam(1, $ticketId, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if ($ticket) {
    echo json_encode([
        'success' => true,
        'ticket' => $ticket
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Ticket not found']);
}
?>