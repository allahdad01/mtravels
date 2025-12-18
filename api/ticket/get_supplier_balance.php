<?php
// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Database connection
require_once '../../includes/db.php';

// Get supplier ID from request
$supplierId = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;

if (!$supplierId) {
    die(json_encode(['success' => false, 'message' => 'Missing supplier ID']));
}

// Get the supplier type and balance
$query = "SELECT name, supplier_type, balance FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($query);
$stmt->bindParam(1, $supplierId, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
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
?>