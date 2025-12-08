<?php
// Include necessary files
require_once '../../includes/db.php';
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Get the raw POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate input
if (!isset($data['id']) || !is_numeric($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid supplier ID']);
    exit();
}

$supplierId = $data['id'];

try {
    // Prepare SQL to update supplier status
    $stmt = $pdo->prepare("UPDATE suppliers SET status = 'active' WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $supplierId, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);

    // Execute the statement
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Supplier activated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to activate supplier']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>