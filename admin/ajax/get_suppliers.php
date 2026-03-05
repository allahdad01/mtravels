<?php
// Include security module
require_once '../security.php';

// Enforce authentication
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Connect to database
require_once '../../includes/db.php';

// Get suppliers using prepared statement to prevent SQL injection
$stmt = $pdo->prepare("SELECT id, name, currency FROM suppliers WHERE tenant_id = ? AND branch_id = ?");

$stmt->execute([$tenant_id, $branch_id]);

$suppliers = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $suppliers[] = $row;
}

echo json_encode(['success' => true, 'suppliers' => $suppliers]);
?>