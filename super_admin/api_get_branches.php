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

$tenant_id = (int)($_GET['tenant_id'] ?? 0);
if ($tenant_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid tenant ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name, code FROM branches WHERE tenant_id = ? AND status = 'active' ORDER BY name");
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $branches[] = ['id' => 0, 'name' => 'All Branches', 'code' => 'ALL'];

    echo json_encode(['success' => true, 'branches' => $branches]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch branches']);
}
