<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

require_once '../includes/conn.php';
$tenant_id = $_SESSION['tenant_id'];

$data = json_decode(file_get_contents("php://input"), true);
$stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ? AND tenant_id = ?");
$stmt->bind_param("ii", $data['id'], $tenant_id);

if ($stmt->execute()) {
    // Get the supplier ID for logging
    $supplier_id = $data['id'];
    
    // Log the activity
    $old_values = json_encode([
        'supplier_id' => $supplier_id
    ]);
    $new_values = json_encode([]);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    
    $stmt_log = $conn->prepare("
        INSERT INTO activity_log 
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
        VALUES (?, 'delete', 'suppliers', ?, ?, ?, ?, ?, NOW(), ?)
    ");
    $stmt_log->bind_param("iissssi", $user_id, $supplier_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
    $stmt_log->execute();
    $stmt_log->close();
    
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => $conn->error]);
}
?>
