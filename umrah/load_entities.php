<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

// load_entities.php - Fetches entities dynamically based on the selected report type
require_once '../includes/conn.php';

// Validate type
$type = isset($_POST['type']) ? DbSecurity::validateInput($_POST['type'], 'string', ['maxlength' => 255]) : null;


$type = $_POST['type'];
$options = [];

switch ($type) {
    case 'supplier':
        $query = "SELECT id, name FROM suppliers where tenant_id = ?";
        break;
    case 'main_account':
        $query = "SELECT id, name FROM main_account where tenant_id = ?";
        break;
    case 'client':
        $query = "SELECT id, name FROM clients where tenant_id = ?";
        break;
    default:
        echo json_encode(["success" => false, "message" => "Invalid type"]);
        exit();
}

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $options[] = $row;
}
$stmt->close();

echo json_encode(["success" => true, "data" => $options]);
?>