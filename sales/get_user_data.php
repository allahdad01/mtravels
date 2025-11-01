<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}           
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

include '../config.php';

$userId = $_SESSION['user_id'];

$sql = "SELECT name, email, profile_pic, role, phone, address, hire_date FROM users WHERE id = ? AND tenant_id = ?";
$stmt = $conection_db->prepare($sql);
$stmt->bind_param("ii", $userId, $tenant_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    echo json_encode($result->fetch_assoc());
} else {
    http_response_code(404);
    echo json_encode(["error" => "User not found"]);
}

$stmt->close();
$conection_db->close();
?>
