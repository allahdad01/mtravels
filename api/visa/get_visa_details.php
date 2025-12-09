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



// Include database connection
require_once('../../includes/db.php');



// Validate input
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid visa ID']);
    exit;
}

$visaId = intval($_GET['id']);

try {
    // Prepare the query to fetch visa details
    $query = "SELECT v.*

               FROM visa_applications v

               WHERE v.id = ? AND v.tenant_id = ? AND v.branch_id = ?";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $visaId, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    $visa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$visa) {
        echo json_encode(['success' => false, 'message' => 'Visa not found']);
        exit;
    }


    // Return success response with visa data
    echo json_encode([
        'success' => true,
        'visa' => $visa
    ]);

} catch (PDOException $e) {
    // Log error (adjust this according to your logging system)
    error_log('Error fetching visa details: ' . $e->getMessage());

    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching visa details'
    ]);
}
?>