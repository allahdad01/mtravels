<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

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

header('Content-Type: application/json');

$tenant_id = (int)($_GET['tenant_id'] ?? 0);
$branch_id = (int)($_GET['branch_id'] ?? 0);

if ($tenant_id <= 0) {
    echo json_encode(['success' => true, 'accounts' => []]);
    exit;
}

try {
    if ($branch_id > 0) {
        $stmt = $pdo->prepare("SELECT id, name, usd_balance, afs_balance, euro_balance, darham_balance, sar_balance FROM main_account WHERE tenant_id = ? AND (branch_id = ? OR branch_id IS NULL) AND status = 'active' ORDER BY name");
        $stmt->execute([$tenant_id, $branch_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id, name, usd_balance, afs_balance, euro_balance, darham_balance, sar_balance FROM main_account WHERE tenant_id = ? AND status = 'active' ORDER BY name");
        $stmt->execute([$tenant_id]);
    }
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'accounts' => $accounts]);
} catch (PDOException $e) {
    echo json_encode(['success' => true, 'accounts' => []]);
}
