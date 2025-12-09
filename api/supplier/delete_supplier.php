<?php
// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

require_once '../../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

$data = json_decode(file_get_contents("php://input"), true);

// ✅ CSRF Token Validation
if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

$stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
$stmt->bindParam(1, $data['id'], PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);

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


    $stmt_log = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, 'delete', 'suppliers', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt_log->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt_log->bindParam(2, $supplier_id, PDO::PARAM_INT);
    $stmt_log->bindParam(3, $old_values, PDO::PARAM_STR);
    $stmt_log->bindParam(4, $new_values, PDO::PARAM_STR);
    $stmt_log->bindParam(5, $ip_address, PDO::PARAM_STR);
    $stmt_log->bindParam(6, $user_agent, PDO::PARAM_STR);
    $stmt_log->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $stmt_log->bindParam(8, $branch_id, PDO::PARAM_INT);
    $stmt_log->execute();

    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Database error"]);
}
?>
