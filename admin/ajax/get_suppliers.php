<?php
// Include security module
require_once '../security.php';

// Enforce authentication
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];

// Connect to database
require_once '../../includes/conn.php';

// Check connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Get suppliers
$result = $conn->query("SELECT id, name, currency FROM suppliers WHERE tenant_id = $tenant_id");

$suppliers = [];
while ($row = $result->fetch_assoc()) {
    $suppliers[] = $row;
}

echo json_encode(['success' => true, 'suppliers' => $suppliers]);

$conn->close();
?>