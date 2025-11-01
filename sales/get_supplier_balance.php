<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

// Database connection
require_once '../includes/conn.php';

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Get supplier ID from request
$supplierId = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;

if (!$supplierId) {
    die(json_encode(['success' => false, 'message' => 'Missing supplier ID']));
}

// Get the supplier type and balance
$query = "SELECT name, supplier_type, balance FROM suppliers WHERE id = ? AND tenant_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $supplierId, $tenant_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Check if supplier is External (case-insensitive comparison)
    $isExternal = (strtolower(trim($row['supplier_type'])) === 'external');
    
    // Debug information
    error_log("Supplier Type: '" . $row['supplier_type'] . "', Is External: " . ($isExternal ? 'true' : 'false'));
    
    echo json_encode([
        'success' => true, 
        'balance' => $row['balance'],
        'supplier_name' => $row['name'],
        'supplier_type' => $row['supplier_type'],
        'is_external' => $isExternal
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Supplier not found']);
}

$stmt->close();
$conn->close();
?>