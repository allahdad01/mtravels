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

// Load current user with tenant
$stmt = secure_query($pdo, 'SELECT u.id, u.tenant_id FROM users u WHERE u.id = ?', [$currentUserId]);
$user = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'user_not_found']);
    exit;
}

$tenantId = (int)$user['tenant_id'];

// Allowed tenants: self + approved peers (both directions)
$peerSql = 'SELECT peer_tenant_id AS peer FROM tenant_peering WHERE tenant_id = ? AND status = "approved"
            UNION
            SELECT tenant_id AS peer FROM tenant_peering WHERE peer_tenant_id = ? AND status = "approved"';
$peerStmt = secure_query($pdo, $peerSql, [$tenantId, $tenantId]);
$peerTenantIds = $peerStmt ? array_map(function($r){ return (int)$r['peer']; }, $peerStmt->fetchAll()) : [];
$allowedTenantIds = array_values(array_unique(array_merge([$tenantId], $peerTenantIds)));

// Get total unread count
$totalUnread = 0;
if (count($allowedTenantIds) > 0) {
    $in = implode(',', array_fill(0, count($allowedTenantIds), '?'));
    $params = array_merge([$currentUserId], $allowedTenantIds);

    $unreadStmt = secure_query($pdo,
        'SELECT COUNT(*) as total_unread
         FROM chat_messages cm
         JOIN users u ON cm.from_user_id = u.id
         WHERE cm.to_user_id = ? AND cm.tenant_id_from IN (' . $in . ') AND cm.seen_at IS NULL',
        $params
    );

    $totalUnread = $unreadStmt ? (int)$unreadStmt->fetchColumn() : 0;
}

echo json_encode([
    'total_unread' => $totalUnread
]);
exit;
?>