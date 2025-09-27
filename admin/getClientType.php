<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

// Database connection
require_once '../includes/conn.php';

// Check for database connection error
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

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
        WHERE tb.id = ? AND tb.tenant_id = ?
    ";

    // Prepare and execute the query
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $ticketId, $tenant_id); // Bind the ticket ID as an integer
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if a matching record was found
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc(); // Fetch the client type
        echo json_encode(['status' => 'success', 'client_type' => $data['client_type']]);
    } else {
        // No matching ticket or client found
        echo json_encode(['status' => 'error', 'message' => 'No matching client type found for the given ticket.']);
    }

    // Close the statement and connection
    $stmt->close();
    $conn->close();
} else {
    // Handle the case where the ticket ID is not provided
    echo json_encode(['status' => 'error', 'message' => 'Invalid request. Ticket ID is missing.']);
}
?>
