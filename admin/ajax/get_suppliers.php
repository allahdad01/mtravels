<?php
// Include security module
require_once '../security.php';

// Enforce authentication
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Connect to database
require_once '../../includes/conn.php';

// Check connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Get suppliers using prepared statement to prevent SQL injection
$stmt = $conn->prepare("SELECT id, name, currency FROM suppliers WHERE tenant_id = ? AND branch_id = ?");

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("ii", $tenant_id, $branch_id);
$stmt->execute();
$result = $stmt->get_result();

$suppliers = [];
while ($row = $result->fetch_assoc()) {
    $suppliers[] = $row;
}

$stmt->close();

echo json_encode(['success' => true, 'suppliers' => $suppliers]);

$conn->close();
?>