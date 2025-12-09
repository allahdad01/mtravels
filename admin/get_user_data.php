<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}           
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

include '../config.php';

$userId = $_SESSION['user_id'];

$sql = "SELECT name, email, profile_pic, role, phone, address, hire_date FROM users WHERE id = ? AND tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $userId, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchAll();

if (count($result) === 1) {
    echo json_encode($result[0]);
} else {
    http_response_code(404);
    echo json_encode(["error" => "User not found"]);
}
?>
