<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$tenantId = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : 0;

if ($tenantId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_tenant_id']);
    exit;
}

// Fetch active branches for the tenant
$stmt = secure_query($pdo, 'SELECT id, name FROM branches WHERE tenant_id = ? AND status = "active" ORDER BY name', [$tenantId]);
$branches = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

echo json_encode(['branches' => $branches]);
exit;
?>
