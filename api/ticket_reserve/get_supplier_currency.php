<?php
// Include security module
session_start();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

// Database connection
require_once '../../includes/db.php';

// get_supplier_currency.php
if (isset($_GET['supplier_id'])) {
    $supplierId = intval($_GET['supplier_id']);

    $query = "SELECT currency FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $supplierId, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(["currency" => $result['currency'] ?? null]);
}
?>
