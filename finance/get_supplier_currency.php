<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
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

    $query = "SELECT currency FROM suppliers WHERE id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $supplierId, $tenant_id);
    $stmt->execute();
    $stmt->bind_result($currency);
    $stmt->fetch();

    echo json_encode(["currency" => $currency]);

    $stmt->close();
    $conn->close();
}
?>
