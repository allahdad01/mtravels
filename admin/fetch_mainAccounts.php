<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

require '../vendor/autoload.php';
require_once '../includes/conn.php';
$tenant_id = $_SESSION['tenant_id'];
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$response = ['success' => false, 'accounts' => []];

$query = "SELECT id, name, usd_balance, afs_balance FROM main_account where status = 'active' AND tenant_id = ?";
$result = $conn->query($query, [$tenant_id]);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $response['accounts'][] = $row;
    }
    $response['success'] = true;
}

echo json_encode($response);  // Send as JSON
?>
