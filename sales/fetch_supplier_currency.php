<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

// Database connection
require_once '../includes/conn.php';

// Check connection
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
    exit();
}

// Fetch suppliers
$sql = "SELECT id, name, currency FROM suppliers WHERE tenant_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $suppliers = [];
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = [
            "id" => $row["id"],
            "name" => $row["name"],
            "currency" => $row["currency"]
        ];
    }
    echo json_encode(["success" => true, "data" => $suppliers]);
} else {
    echo json_encode(["success" => false, "message" => "No suppliers found."]);
}

$conn->close();
?>
