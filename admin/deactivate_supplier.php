<?php
// Include necessary files
require_once '../includes/db.php';
require_once '../includes/conn.php';
require_once 'security.php';

// Enforce authentication
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];

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
    $stmt = $conn->prepare("UPDATE suppliers SET status = 'inactive' WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param("ii", $supplierId, $tenant_id);
    
    // Execute the statement
    if ($stmt->execute()) {

        
        echo json_encode(['success' => true, 'message' => 'Supplier deactivated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to deactivate supplier']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>