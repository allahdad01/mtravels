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

include '../../includes/db.php';

// Check if the user is logged in
if (!isset($_SESSION['name'])) {
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in to access this resource']);
    exit;
}

try {
    // Query to get tickets
    $query = "SELECT ap.id, ap.applicant_name, ap.passport_number, ap.visa_type, ap.country, ap.applied_date, ap.issued_date, ap.sold
              FROM visa_applications ap
              WHERE ap.tenant_id = ? AND ap.branch_id = ?
              ORDER BY ap.id DESC
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