<?php
// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

// Database connection
require_once '../../includes/db.php';

// Get client ID from request
$clientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

if (!$clientId) {
    die(json_encode(['success' => false, 'message' => 'Missing client ID']));
}

$query = "SELECT name, client_type, phone FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($query);
$stmt->bindParam(1, $clientId, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    // Check if client is Regular type
    $isRegular = strtolower($row['client_type']) === 'regular';
    
    echo json_encode([
        'success' => true,
        'client_name' => $row['name'],
        'client_type' => $row['client_type'],
        'phone' => $row['phone'],
        'is_regular' => $isRegular
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Client not found']);
}
?>
