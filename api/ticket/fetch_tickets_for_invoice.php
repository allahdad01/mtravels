<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

require_once '../../includes/db.php';

require_permission('tickets.view');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

try {
    // Query to get tickets
    $query = "
        SELECT tb.id, tb.passenger_name, tb.pnr, tb.origin, tb.destination, 
              tb.airline, tb.departure_date, tb.sold, tb.trip_type, tb.return_destination, 
              tb.return_date, c.name as sold_to_name
              FROM ticket_bookings tb
              JOIN clients c ON tb.sold_to = c.id
              WHERE tb.status != 'Refunded' AND tb.status != 'Cancelled'
          AND tb.tenant_id = ? AND tb.branch_id = ?
        ORDER BY tb.id DESC
    ";
    
    $stmt = $pdo->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed");
    }

    // Bind parameters
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);

    if (!$stmt->execute()) {
        throw new Exception("Execute failed");
    }

    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'tickets' => $tickets]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
