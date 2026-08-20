<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

// Database connection
require_once '../../includes/db.php';

require_permission('tickets.weights');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

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
            tw.tenant_id = ? AND tw.branch_id = ?
        ORDER BY
            tw.created_at DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $weights = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'weights' => $weights]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>