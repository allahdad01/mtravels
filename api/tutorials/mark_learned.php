<?php
require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';

$allowed = ['admin', 'finance', 'sales', 'umrah', 'staff', 'tenant_super_admin'];
enforce_auth($allowed);

require_once '../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$tutorial_id = (int) ($_POST['tutorial_id'] ?? 0);

if ($tutorial_id <= 0 || $user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO user_tutorial_learned (user_id, tutorial_id, learned_at) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $tutorial_id]);

    $inserted = $stmt->rowCount();
    echo json_encode([
        'success' => true,
        'message' => $inserted > 0 ? 'Tutorial marked as learned' : 'Already marked as learned'
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
