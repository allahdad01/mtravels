<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../../includes/db.php';

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Get weight ID from request
$weightId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($weightId <= 0) {
    die(json_encode([
        'success' => false,
        'message' => 'Invalid weight ID'
    ]));
}

// Query to get weight details with related information
$query = "
    SELECT
        tw.*,
        t.passenger_name,
        t.pnr,
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
        ticket_bookings t ON tw.ticket_id = t.id AND t.tenant_id = ? AND t.branch_id = ?
    LEFT JOIN
        suppliers s ON t.supplier = s.id AND s.tenant_id = ? AND s.branch_id = ?
    LEFT JOIN
        clients c ON t.sold_to = c.id AND c.tenant_id = ? AND c.branch_id = ?
    WHERE
        tw.id = ? AND tw.tenant_id = ? AND tw.branch_id = ?
";

$stmt = $pdo->prepare($query);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
$stmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(6, $branch_id, PDO::PARAM_INT);
$stmt->bindParam(7, $weightId, PDO::PARAM_INT);
$stmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(9, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo json_encode([
        'success' => true,
        'weight' => $row
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Weight not found'
    ]);
}
?>