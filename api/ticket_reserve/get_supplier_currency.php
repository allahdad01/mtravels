<?php
// Include security module
session_start();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

// get_supplier_currency.php
if (isset($_GET['supplier_id'])) {
    $supplierId = intval($_GET['supplier_id']);

    // Connect to your database
    require_once '../includes/conn.php';

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $query = "SELECT currency FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $supplierId, $tenant_id, $branch_id);
    $stmt->execute();
    $stmt->bind_result($currency);
    $stmt->fetch();

    echo json_encode(["currency" => $currency]);

    $stmt->close();
    $conn->close();
}
?>
