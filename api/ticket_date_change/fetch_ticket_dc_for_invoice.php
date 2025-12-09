<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

// Database connection
require_once('../../includes/db.php');

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
              WHERE tb.tenant_id = ? AND tb.branch_id = ?
              ORDER BY tb.id DESC
              LIMIT 100";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'tickets' => $tickets]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>