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
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in as admin to access this resource']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];

try {
    // Query to get tickets
    $query = "
        SELECT tb.id, tb.passenger_name, tb.pnr, tb.origin, tb.destination, 
              tb.airline, tb.departure_date, tb.sold, tb.trip_type, tb.return_destination, 
              tb.return_date, c.name as sold_to_name
              FROM ticket_bookings tb
              JOIN clients c ON tb.sold_to = c.id
              WHERE tb.status != 'Refunded' AND tb.status != 'Cancelled'
          AND tb.tenant_id = ?
        ORDER BY tb.id DESC
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Detect type of tenant_id
    if (is_int($tenant_id)) {
        $stmt->bind_param("i", $tenant_id);
    } else {
        $stmt->bind_param("s", $tenant_id);
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result === false) {
        throw new Exception("get_result() failed: " . $stmt->error);
    }
    
    $tickets = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['status' => 'success', 'tickets' => $tickets]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
