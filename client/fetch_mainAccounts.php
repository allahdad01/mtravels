<?php
require '../vendor/autoload.php';

$conn = new mysqli("localhost", "root", "", "travelagency");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$response = ['success' => false, 'accounts' => []];

$query = "SELECT id, name, usd_balance, afs_balance FROM main_account";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $response['accounts'][] = $row;
    }
    $response['success'] = true;
}

echo json_encode($response);  // Send as JSON
?>
