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
    // Query to get weights with related information
    $query = "
        SELECT
            tw.*,
            t.passenger_name,
            t.pnr,
            t.phone,
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
            tw.tenant_id = ?
        ORDER BY
            tw.created_at DESC
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

    $weights = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'weights' => $weights]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>