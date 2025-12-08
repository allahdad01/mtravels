<?php
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

// load_entities.php - Fetches entities dynamically based on the selected report type
require_once '../../includes/db.php';

// Validate type
$type = isset($_POST['type']) ? DbSecurity::validateInput($_POST['type'], 'string', ['maxlength' => 255]) : null;


$type = $_POST['type'];
$options = [];

switch ($type) {
    case 'supplier':
        $query = "SELECT id, name FROM suppliers where tenant_id = ? AND branch_id = ?";
        break;
    case 'main_account':
        $query = "SELECT id, name FROM main_account where tenant_id = ? AND branch_id = ?";
        break;
    case 'client':
        $query = "SELECT id, name FROM clients where tenant_id = ? AND branch_id = ?";
        break;
    default:
        echo json_encode(["success" => false, "message" => "Invalid type"]);
        exit();
}

$stmt = $pdo->prepare($query);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$options = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(["success" => true, "data" => $options]);
?>