<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

// Database connection
require_once '../../includes/db.php';

// Ensure the required parameter is set

// Validate ticketId
$ticketId = isset($_POST['ticketId']) ? DbSecurity::validateInput($_POST['ticketId'], 'int', ['min' => 0]) : null;
if (isset($_POST['ticketId'])) {
    $ticketId = intval($_POST['ticketId']); // Ensure ticket ID is an integer

    // Query to get the client type based on the ticket's sold_to field
    $query = "
        SELECT c.client_type
        FROM ticket_bookings tb
        JOIN clients c ON tb.sold_to = c.id
        WHERE tb.id = ? AND tb.tenant_id = ? AND tb.branch_id = ? AND c.branch_id = ?
    ";

    // Prepare and execute the query
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $ticketId, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if a matching record was found
    if ($data) {
        echo json_encode(['status' => 'success', 'client_type' => $data['client_type']]);
    } else {
        // No matching ticket or client found
        echo json_encode(['status' => 'error', 'message' => 'No matching client type found for the given ticket.']);
    }
} else {
    // Handle the case where the ticket ID is not provided
    echo json_encode(['status' => 'error', 'message' => 'Invalid request. Ticket ID is missing.']);
}
?>
