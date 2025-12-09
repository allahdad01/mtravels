<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

// Database connection
require_once '../includes/db.php';

// Fetch suppliers
$sql = "SELECT id, name, currency FROM suppliers WHERE tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchAll();

if (count($result) > 0) {
    $suppliers = [];
    foreach ($result as $row) {
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
?>
