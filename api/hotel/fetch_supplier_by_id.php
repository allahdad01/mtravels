<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

include '../includes/conn.php';


$id = $_GET['id'];

$query = "SELECT * FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('iii', $id, $tenant_id, $branch_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode($result->fetch_assoc());
} else {
    echo json_encode(['error' => 'Supplier not found.']);
}
?>
