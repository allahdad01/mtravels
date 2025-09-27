<?php
session_start();
$user_id = $_SESSION['user_id'];
// load_entities.php - Fetches entities dynamically based on the selected report type
require_once '../includes/conn.php';


$type = $_POST['type'];
$options = [];

switch ($type) {
    case 'supplier':
        $query = "SELECT id, name FROM suppliers";
        break;
    case 'main_account':
        $query = "SELECT id, name FROM main_account";
        break;
    case 'client':
        $query = "SELECT id, name FROM clients where id = $user_id";
        break;
    default:
        echo json_encode(["success" => false, "message" => "Invalid type"]);
        exit();
}

$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $options[] = $row;
}

echo json_encode(["success" => true, "data" => $options]);
?>