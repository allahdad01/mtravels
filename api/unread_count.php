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
$myBranchId = isset($_SESSION['branch_id']) ? (int)$_SESSION['branch_id'] : 0;

// Get user's branch if not in session
if ($myBranchId === 0) {
    $branchStmt = secure_query($pdo, 'SELECT branch_id FROM users WHERE id = ?', [$currentUserId]);
    $branchUser = $branchStmt ? $branchStmt->fetch(PDO::FETCH_ASSOC) : null;
    $myBranchId = $branchUser ? (int)$branchUser['branch_id'] : 0;
}

// Allowed tenants: self + approved peers (both directions) + tenants with approved branch peering
$peerSql = 'SELECT peer_tenant_id AS peer FROM tenant_peering WHERE tenant_id = ? AND status = "approved"
            UNION
            SELECT tenant_id AS peer FROM tenant_peering WHERE peer_tenant_id = ? AND status = "approved"
            UNION
            SELECT DISTINCT peer_tenant_id AS peer FROM branch_peering WHERE tenant_id IN (SELECT id FROM branches WHERE tenant_id = ?) AND status = "approved"
            UNION
            SELECT DISTINCT tenant_id AS peer FROM branch_peering WHERE peer_tenant_id = ? AND status = "approved"';
$peerStmt = secure_query($pdo, $peerSql, [$tenantId, $tenantId, $tenantId, $tenantId]);
$peerTenantIds = $peerStmt ? array_map(function($r){ return (int)$r['peer']; }, $peerStmt->fetchAll()) : [];
$allowedTenantIds = array_values(array_unique(array_merge([$tenantId], $peerTenantIds)));

// Get total unread count with branch peering validation
$totalUnread = 0;
if (count($allowedTenantIds) > 0) {
    // Get all unread messages from allowed tenants
    $in = implode(',', array_fill(0, count($allowedTenantIds), '?'));
    $params = array_merge([$currentUserId], $allowedTenantIds);

    $unreadStmt = secure_query($pdo,
        'SELECT cm.id, cm.from_user_id, u.tenant_id, u.branch_id, cm.tenant_id_from
         FROM chat_messages cm
         JOIN users u ON cm.from_user_id = u.id
         WHERE cm.to_user_id = ? AND cm.tenant_id_from IN (' . $in . ') AND cm.seen_at IS NULL',
        $params
    );

    $messages = $unreadStmt ? $unreadStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    
    // Filter by branch peering
    foreach ($messages as $msg) {
        $senderTenant = (int)$msg['tenant_id_from'];
        $senderBranch = (int)$msg['branch_id'];
        
        // Same tenant: always count
        if ($senderTenant === $tenantId) {
            $totalUnread++;
            continue;
        }
        
        // Different tenant: check branch peering
        $peeringCheck = secure_query($pdo, 
            'SELECT 1 FROM branch_peering WHERE status = "approved" AND (
                (tenant_id = ? AND branch_id = ? AND peer_tenant_id = ? AND peer_branch_id = ?)
                OR
                (tenant_id = ? AND branch_id = ? AND peer_tenant_id = ? AND peer_branch_id = ?)
            ) LIMIT 1', 
            [$tenantId, $myBranchId, $senderTenant, $senderBranch, $senderTenant, $senderBranch, $tenantId, $myBranchId]);
        
        if ($peeringCheck && $peeringCheck->fetch()) {
            $totalUnread++;
        }
    }
}

echo json_encode([
    'total_unread' => $totalUnread
]);
exit;
?>