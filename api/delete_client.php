<?php
// delete_client.php
header('Content-Type: application/json');

// Get the input data
$data = json_decode(file_get_contents("php://input"), true);
$clientId = $data['id'] ?? null;

if (!$clientId) {
    echo json_encode(['success' => false, 'message' => 'Client ID is required.']);
    exit;
}

include '../includes/db.php'; // Your DB connection
$tenant_id = $_SESSION['tenant_id'];
try {
    // Delete client from database
    $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ? and tenant_id = ?");
    $stmt->execute([$clientId, $tenant_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Client deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Client not found or already deleted.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error deleting client: ' . $e->getMessage()]);
}
