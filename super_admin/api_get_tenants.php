<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset(); session_destroy();
    echo json_encode(['success' => false, 'message' => 'Session expired']); exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit();
}

require_once '../includes/db.php';

try {
    $stmt = $pdo->prepare("SELECT id, name, identifier, status FROM tenants WHERE status IN ('active','trial') AND deleted_at IS NULL ORDER BY name");
    $stmt->execute();
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'tenants' => $tenants]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch tenants']);
}
