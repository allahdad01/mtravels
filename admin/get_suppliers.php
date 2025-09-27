<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once '../includes/conn.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch suppliers
$suppliersQuery = "SELECT id, name FROM suppliers WHERE tenant_id = ?";
$suppliersResult = $conn->query($suppliersQuery);

$suppliers = [];
while ($row = $suppliersResult->fetch_assoc()) {
    $suppliers[] = $row;
}

// Fetch main account details
$mainAccountQuery = "SELECT id, name FROM main_account WHERE tenant_id = ?";
$mainAccountResult = $conn->query($mainAccountQuery, [$tenant_id]);

$mainAccount = $mainAccountResult->fetch_assoc();

// Combine data into a single response
$response = [
    'main_account' => $mainAccount, // Single main account
    'suppliers' => $suppliers       // Array of suppliers
];

echo json_encode($response);
$conn->close();
?>
