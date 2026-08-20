<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Database connection
require_once('../../includes/db.php');
require_once('../../includes/permissions.php');
require_permission('reports.view');

try {
    // Fetch families for the current tenant
    $query = "SELECT DISTINCT family_id, head_of_family FROM families WHERE tenant_id = ? AND branch_id = ? ORDER BY head_of_family ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tenant_id, $branch_id]);
    $families = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $families
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error loading families: ' . $e->getMessage()
    ]);
}
?>