<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

require_once '../includes/conn.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch suppliers
$suppliersQuery = "SELECT id, name FROM suppliers WHERE tenant_id = ? AND branch_id = ?";
$suppliersResult = $conn->prepare($suppliersQuery);
$suppliersResult->bind_param("ii", $tenant_id, $branch_id);
$suppliersResult->execute();
$suppliersResult = $suppliersResult->get_result();

$suppliers = [];
while ($row = $suppliersResult->fetch_assoc()) {
    $suppliers[] = $row;
}

// Fetch main account details
$mainAccountQuery = "SELECT id, name FROM main_account WHERE tenant_id = ? AND branch_id = ?";
$mainAccountResult = $conn->prepare($mainAccountQuery);
$mainAccountResult->bind_param("ii", $tenant_id, $branch_id);
$mainAccountResult->execute();
$mainAccountResult = $mainAccountResult->get_result();

$mainAccount = $mainAccountResult->fetch_assoc();

// Combine data into a single response
$response = [
    'main_account' => $mainAccount, // Single main account
    'suppliers' => $suppliers       // Array of suppliers
];

echo json_encode($response);
$conn->close();
?>
