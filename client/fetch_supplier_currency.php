<?php
require_once '../config.php';

// Database connection using secure config
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
    exit();
}

// Fetch suppliers
$sql = "SELECT id, name, currency FROM suppliers";
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
