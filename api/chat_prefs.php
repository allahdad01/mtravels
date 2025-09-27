<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$targetId = (int)($_POST['target_id'] ?? $_GET['target_id'] ?? 0);

// Resolve tenant
$stmt = secure_query($pdo, 'SELECT id, tenant_id FROM users WHERE id = ?', [$currentUserId]);
$me = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

if (!$me) {
    http_response_code(404);
    echo json_encode(['error' => 'user_not_found']);
    exit;
}
$tenantId = (int)$me['tenant_id'];

// Handle block/unblock/mute/unmute
if ($method === 'POST' && in_array($action, ['block', 'unblock', 'mute', 'unmute'], true)) {
    if ($targetId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_target']);
        exit;
    }

    switch ($action) {
        case 'block':
            secure_query($pdo,
                'INSERT IGNORE INTO user_blocks (tenant_id, user_id, blocked_user_id) VALUES (?, ?, ?)',
                [$tenantId, $currentUserId, $targetId]
            );
            break;

        case 'unblock':
            secure_query($pdo,
                'DELETE FROM user_blocks WHERE tenant_id = ? AND user_id = ? AND blocked_user_id = ?',
                [$tenantId, $currentUserId, $targetId]
            );
            break;

        case 'mute':
            secure_query($pdo,
                'INSERT IGNORE INTO user_mutes (tenant_id, user_id, muted_user_id) VALUES (?, ?, ?)',
                [$tenantId, $currentUserId, $targetId]
            );
            break;

        case 'unmute':
            secure_query($pdo,
                'DELETE FROM user_mutes WHERE tenant_id = ? AND user_id = ? AND muted_user_id = ?',
                [$tenantId, $currentUserId, $targetId]
            );
            break;
    }

    echo json_encode(['ok' => true]);
    exit;
}

// Handle list action
if ($method === 'GET' && $action === 'list') {
    $blocksStmt = secure_query($pdo,
        'SELECT blocked_user_id FROM user_blocks WHERE tenant_id = ? AND user_id = ?',
        [$tenantId, $currentUserId]
    );
    $mutesStmt = secure_query($pdo,
        'SELECT muted_user_id FROM user_mutes WHERE tenant_id = ? AND user_id = ?',
        [$tenantId, $currentUserId]
    );

    $blocked = $blocksStmt ? array_map(fn($r) => (int)$r['blocked_user_id'], $blocksStmt->fetchAll(PDO::FETCH_ASSOC)) : [];
    $muted = $mutesStmt ? array_map(fn($r) => (int)$r['muted_user_id'], $mutesStmt->fetchAll(PDO::FETCH_ASSOC)) : [];

    echo json_encode([
        'blocked' => $blocked,
        'muted'   => $muted
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'bad_request']);
exit;
