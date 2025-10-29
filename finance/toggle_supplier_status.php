<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}
$tenant_id = $_SESSION['tenant_id'];
// Database connection
require_once('../includes/db.php');
require_once('../includes/conn.php');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get the data from POST request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['supplier_id']) || !isset($data['new_status'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$supplierId = intval($data['supplier_id']);
$newStatus = $data['new_status'];

// Validate status
if (!in_array($newStatus, ['active', 'inactive'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit();
}

try {
    // Update the supplier status
    $stmt = $pdo->prepare("UPDATE suppliers SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
    $result = $stmt->execute([$newStatus, $supplierId, $tenant_id]);

    if ($result) {
        // Log status change
        $username = isset($_SESSION['name']) ? $_SESSION['name'] : 'User ID: ' . $_SESSION['user_id'];
        error_log("Supplier status change: User $username changed supplier ID $supplierId to status '$newStatus'");
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Supplier status updated successfully',
            'new_status' => $newStatus
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to update supplier status']);
    }
} catch (PDOException $e) {
    // Log the error
    error_log("Database Error: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 