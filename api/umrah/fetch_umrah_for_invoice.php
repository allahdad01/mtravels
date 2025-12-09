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

require_once '../../includes/db.php';

// Check if the user is logged in
if (!isset($_SESSION['name'])) {
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in to access this resource']);
    exit;
}

try {
    // Query to get tickets
    $query = "SELECT um.booking_id, um.name, um.passport_number, f.package_type,
              um.flight_date, um.sold_price,
              um.duration
              FROM umrah_bookings um
              left join families f on um.family_id = f.family_id
              WHERE um.tenant_id = ? AND um.branch_id = ?
              ORDER BY um.booking_id DESC
              LIMIT 100";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tickets = $result;

    echo json_encode(['status' => 'success', 'tickets' => $tickets]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>