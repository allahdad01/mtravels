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
$stmt = secure_query($pdo, 'SELECT u.id, u.tenant_id, u.name, u.email, u.role, s.agency_name 
                           FROM users u 
                           JOIN tenants t ON u.tenant_id = t.id 
                           LEFT JOIN settings s ON t.id = s.tenant_id 
                           WHERE u.id = ?', [$currentUserId]);
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

$rows = [];
if (count($allowedTenantIds) > 0) {
    $in = implode(',', array_fill(0, count($allowedTenantIds), '?'));
    $params = array_merge($allowedTenantIds, [$currentUserId]);
    $sql = 'SELECT u.id, u.role, u.name, u.tenant_id, u.profile_pic, s.agency_name 
            FROM users u 
            JOIN tenants t ON u.tenant_id = t.id 
            LEFT JOIN settings s ON t.id = s.tenant_id 
            WHERE u.tenant_id IN (' . $in . ') AND u.id <> ? AND u.deleted_at IS NULL AND u.fired <> 1';
    $stmt = secure_query($pdo, $sql, $params);
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

// Exclude users I blocked and users who blocked me
if (count($allowedTenantIds) > 0) {
    $tenantIn = implode(',', array_fill(0, count($allowedTenantIds), '?'));
    
    // Get users I blocked
    $blockedParams = array_merge($allowedTenantIds, [$currentUserId]);
    $blockedStmt = secure_query($pdo, 
        'SELECT blocked_user_id FROM user_blocks WHERE tenant_id IN (' . $tenantIn . ') AND user_id = ?', 
        $blockedParams
    );
    $blockedIds = $blockedStmt ? array_map(function($r){ return (int)$r['blocked_user_id']; }, $blockedStmt->fetchAll()) : [];
    
    // Get users who blocked me
    $blockedMeParams = array_merge($allowedTenantIds, [$currentUserId]);
    $blockedMeStmt = secure_query($pdo, 
        'SELECT user_id FROM user_blocks WHERE tenant_id IN (' . $tenantIn . ') AND blocked_user_id = ?', 
        $blockedMeParams
    );
    $blockedMeIds = $blockedMeStmt ? array_map(function($r){ return (int)$r['user_id']; }, $blockedMeStmt->fetchAll()) : [];
    
    $excluded = array_flip(array_unique(array_merge($blockedIds, $blockedMeIds)));
    $rows = array_values(array_filter($rows, function($r) use ($excluded){ return !isset($excluded[(int)$r['id']]); }));
}

// Fetch last message for each contact
$contacts = array_map(function($r) use ($currentUserId, $pdo) {
    $ids = [$currentUserId, (int)$r['id']]; 
    sort($ids, SORT_NUMERIC);
    $room = 'u-' . $ids[0] . '-' . $ids[1];
    
    // Get the most recent message between the current user and this contact
    $msgStmt = secure_query($pdo,
        'SELECT content
         FROM chat_messages
         WHERE room_id = ?
         ORDER BY created_at DESC LIMIT 1',
        [$room]
    );
    $lastMessage = $msgStmt ? $msgStmt->fetchColumn() : '';

    // Get unread message count for this contact
    $unreadStmt = secure_query($pdo,
        'SELECT COUNT(*) as unread_count
         FROM chat_messages
         WHERE room_id = ? AND to_user_id = ? AND seen_at IS NULL',
        [$room, $currentUserId]
    );
    $unreadCount = $unreadStmt ? (int)$unreadStmt->fetchColumn() : 0;

    $photo = !empty($r['profile_pic']) ? ('assets/images/user/' . $r['profile_pic']) : null;

    return [
        'id' => (int)$r['id'],
        'role' => $r['role'] ?: 'Unknown Role',
  'name' => $r['name'] ?: 'Unknown user',
        'agency_name' => $r['agency_name'] ?: 'Unknown Agency',
        'tenant_id' => (int)$r['tenant_id'],
        'room_id' => $room,
        'lastMessage' => $lastMessage,
        'unread' => $unreadCount,
        'photo' => $photo
    ];
}, $rows);

echo json_encode([
    'me' => [ 
        'id' => (int)$user['id'], 
        'tenant_id' => $tenantId, 
        'role' => $user['role'] ?: 'Unknown Role', 
		'name' => $user['name'] ?: 'Unknown user',
        'agency_name' => $user['agency_name'] ?: 'Unknown Agency' 
    ], 
    'contacts' => $contacts
]);
exit;
?>