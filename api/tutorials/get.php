<?php
require_once '../../admin/includes/db_security.php';
require_once '../../admin/security.php';

$allowed_manage = ['super_admin'];
enforce_auth($allowed_manage);

require_once '../../includes/db.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM tutorials WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $tutorial = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tutorial) {
        $tutorial['roles'] = json_decode($tutorial['roles'], true);
        echo json_encode(['success' => true, 'tutorial' => $tutorial]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tutorial not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
