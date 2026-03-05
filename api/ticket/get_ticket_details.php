<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security and database
require_once '../../admin/security.php';
require_once '../../includes/db.php';

// Enforce authentication
enforce_auth();

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
$branch_id = $_SESSION['branch_id'] ?? null;

if (!$tenant_id || !$branch_id) {
    echo json_encode(['success' => false, 'message' => 'Tenant or branch not found']);
    exit;
}

$ticketId = isset($_GET['ticketId']) ? intval($_GET['ticketId']) : null;

if (!$ticketId) {
    echo json_encode(['success' => false, 'message' => 'Ticket ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            passenger_name,
            pnr,
            trip_type,
            departure_date,
            return_date,
            origin,
            destination,
            airline,
            currency,
            price,
            sold
        FROM ticket_bookings
        WHERE id = ? AND tenant_id = ? AND branch_id = ?
    ");
    
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
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
