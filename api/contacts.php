<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$sessionRole = $_SESSION['role'] ?? 'user'; // 'admin', 'client', 'sales_agent', etc.

// Load current user with tenant and branch (check both users and clients tables)
$stmt = secure_query($pdo, 'SELECT u.id, u.tenant_id, u.branch_id, u.name, u.email, u.role, s.agency_name, "user" as user_type
                           FROM users u 
                           JOIN tenants t ON u.tenant_id = t.id 
                           LEFT JOIN settings s ON t.id = s.tenant_id 
                           WHERE u.id = ?', [$currentUserId]);
$user = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

// If not a user, check if it's a client
if (!$user && $sessionRole === 'client') {
    $stmt = secure_query($pdo, 'SELECT c.id, c.tenant_id, c.branch_id, c.name, c.email, "client" as role, s.agency_name, "client" as user_type
                               FROM clients c 
                               JOIN tenants t ON c.tenant_id = t.id 
                               LEFT JOIN settings s ON t.id = s.tenant_id 
                               WHERE c.id = ?', [$currentUserId]);
    $user = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
}

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'user_not_found']);
    exit;
}

// Normalize user type: 'user' for all non-client users, 'client' for clients
$currentUserType = ($sessionRole === 'client' ? 'client' : 'user');

$tenantId = (int)$user['tenant_id'];
$myBranch = (int)$user['branch_id'];
$isTenantOwner = $user['role'] === 'tenant_super_admin';

// Allowed tenants: 
// - Clients: only their own tenant (NO peering)
// - Users/Admins: self + approved peers (both directions)
$allowedTenantIds = [$tenantId];

if ($currentUserType !== 'client') {
    // Only non-clients can see peered tenants
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
}

$rows = [];
if (count($allowedTenantIds) > 0) {
    $in = implode(',', array_fill(0, count($allowedTenantIds), '?'));
    
    if ($isTenantOwner) {
        // Tenant owner sees all users and clients in their tenant (no branch filtering)
        $params = array_merge([$tenantId], [$currentUserId], [$tenantId], [$currentUserId]);
        $sql = '(SELECT u.id, u.role, u.name, u.tenant_id, u.branch_id, u.profile_pic, s.agency_name, b.name as branch_name, t.name as tenant_name, "user" as user_type
                FROM users u 
                JOIN tenants t ON u.tenant_id = t.id 
                LEFT JOIN settings s ON t.id = s.tenant_id 
                LEFT JOIN branches b ON u.branch_id = b.id
                WHERE u.tenant_id = ? 
                AND u.id <> ? 
                AND u.deleted_at IS NULL 
                AND u.fired <> 1)
                UNION
                (SELECT c.id, c.status as role, c.name, c.tenant_id, c.branch_id, c.image as profile_pic, s.agency_name, b.name as branch_name, t.name as tenant_name, "client" as user_type
                FROM clients c 
                JOIN tenants t ON c.tenant_id = t.id 
                LEFT JOIN settings s ON t.id = s.tenant_id 
                LEFT JOIN branches b ON c.branch_id = b.id
                WHERE c.tenant_id = ? 
                AND c.id <> ?)';
    } else {
        // Regular users and clients: show same branch in same tenant, all tenant admins, or all contacts from different tenants
        $params = array_merge($allowedTenantIds, [$currentUserId, $tenantId, $myBranch, $tenantId], 
                             $allowedTenantIds, [$currentUserId, $tenantId, $myBranch, $tenantId]);
        $sql = '(SELECT u.id, u.role, u.name, u.tenant_id, u.branch_id, u.profile_pic, s.agency_name, b.name as branch_name, t.name as tenant_name, "user" as user_type
                FROM users u 
                JOIN tenants t ON u.tenant_id = t.id 
                LEFT JOIN settings s ON t.id = s.tenant_id 
                LEFT JOIN branches b ON u.branch_id = b.id
                WHERE u.tenant_id IN (' . $in . ') 
                AND u.id <> ? 
                AND u.deleted_at IS NULL 
                AND u.fired <> 1
                AND (
                    (u.tenant_id = ? AND (u.branch_id = ? OR u.role = "tenant_super_admin"))
                    OR u.tenant_id <> ?
                ))
                UNION
                (SELECT c.id, c.status as role, c.name, c.tenant_id, c.branch_id, c.image as profile_pic, s.agency_name, b.name as branch_name, t.name as tenant_name, "client" as user_type
                FROM clients c 
                JOIN tenants t ON c.tenant_id = t.id 
                LEFT JOIN settings s ON t.id = s.tenant_id 
                LEFT JOIN branches b ON c.branch_id = b.id
                WHERE c.tenant_id IN (' . $in . ') 
                AND c.id <> ? 
                AND (
                    (c.tenant_id = ? AND (c.branch_id = ? OR c.status = "active"))
                    OR c.tenant_id <> ?
                ))';
    }
    
    $stmt = secure_query($pdo, $sql, $params);
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    
    // Filter by branch peering for cross-tenant contacts
    $rows = array_filter($rows, function($r) use ($pdo, $tenantId, $myBranch) {
        $rTenantId = (int)$r['tenant_id'];
        $rBranchId = (int)$r['branch_id'];
        
        // Same tenant: always allow
        if ($rTenantId === $tenantId) {
            return true;
        }
        
        // Different tenant: check branch-level peering
        // Check both directions: I initiated the peering OR they initiated it
        $peeringCheck = secure_query($pdo, 
            'SELECT 1 FROM branch_peering WHERE status = "approved" AND (
                (tenant_id = ? AND branch_id = ? AND peer_tenant_id = ? AND peer_branch_id = ?)
                OR
                (tenant_id = ? AND branch_id = ? AND peer_tenant_id = ? AND peer_branch_id = ?)
            ) LIMIT 1',
            [$tenantId, $myBranch, $rTenantId, $rBranchId, $rTenantId, $rBranchId, $tenantId, $myBranch]
        );
        
        return $peeringCheck && $peeringCheck->fetch();
    });
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
$contacts = array_map(function($r) use ($currentUserId, $currentUserType, $pdo, $tenantId) {
    // Create room_id that includes user type to distinguish users from clients
    // Create pairs and sort them to ensure consistent room IDs
    $pairs = [
        ['id' => $currentUserId, 'type' => $currentUserType],
        ['id' => (int)$r['id'], 'type' => $r['user_type']]
    ];
    
    // Sort by ID numerically to ensure consistent ordering
    usort($pairs, function($x, $y) {
        return $x['id'] - $y['id'];
    });
    
    // Format: msg-type1_id1-type2_id2 (where type is 'u' for user, 'c' for client)
    $t1 = substr($pairs[0]['type'], 0, 1);
    $t2 = substr($pairs[1]['type'], 0, 1);
    $room = 'msg-' . $t1 . $pairs[0]['id'] . '-' . $t2 . $pairs[1]['id'];
    
    // Get the most recent message between the current user and this contact
     $msgStmt = secure_query($pdo,
         'SELECT content, encrypted_content, is_encrypted, encryption_key_id, tenant_id_from, created_at
          FROM chat_messages
          WHERE room_id = ?
          ORDER BY created_at DESC LIMIT 1',
         [$room]
     );
     $lastMessage = '';
     $lastMessageTime = null;
     if ($msgStmt && ($msgRow = $msgStmt->fetch(PDO::FETCH_ASSOC))) {
         $lastMessageTime = $msgRow['created_at'];
        if ($msgRow['is_encrypted'] && $msgRow['encrypted_content']) {
            // Decrypt the message
            try {
                require_once __DIR__ . '/../includes/MessageEncryption.php';
                $encryptor = new MessageEncryption($pdo);
                $messageDecryptTenant = isset($msgRow['tenant_id_from']) ? (int)$msgRow['tenant_id_from'] : $tenantId;
                $lastMessage = $encryptor->decrypt($msgRow['encrypted_content'], $messageDecryptTenant, (int)$msgRow['encryption_key_id']);
            } catch (Exception $e) {
                $lastMessage = '[Message]';
            }
        } else {
            $lastMessage = $msgRow['content'] ?: '';
        }
    }

    // Get unread message count for this contact
    $unreadStmt = secure_query($pdo,
        'SELECT COUNT(*) as unread_count
         FROM chat_messages
         WHERE room_id = ? AND to_user_id = ? AND seen_at IS NULL',
        [$room, $currentUserId]
    );
    $unreadCount = $unreadStmt ? (int)$unreadStmt->fetchColumn() : 0;

    // Store only the filename - frontend will construct the full path
    $photo = !empty($r['profile_pic']) ? $r['profile_pic'] : null;

    // Format time for display in sidebar
    $time = '';
    if ($lastMessageTime) {
        $datetime = new DateTime($lastMessageTime);
        $now = new DateTime();
        $diff = $now->diff($datetime);
        
        if ($diff->days === 0 && $datetime->format('Y-m-d') === $now->format('Y-m-d')) {
            $time = $datetime->format('H:i');
        } else if ($diff->days === 1) {
            $time = 'Yesterday';
        } else if ($diff->days < 7) {
            $time = $datetime->format('l');
        } else {
            $time = $datetime->format('M d');
        }
    }
    
    return [
         'id' => (int)$r['id'],
         'user_type' => $r['user_type'] ?: 'user', // 'user' or 'client'
         'role' => $r['role'] ?: 'Unknown Role',
         'name' => $r['name'] ?: 'Unknown user',
         'agency_name' => $r['agency_name'] ?: 'Unknown Agency',
         'branch_name' => $r['branch_name'] ?: 'Unknown Branch',
         'tenant_id' => (int)$r['tenant_id'],
         'tenant_name' => $r['tenant_name'] ?: 'Unknown Tenant',
         'room_id' => $room,
         'lastMessage' => $lastMessage,
         'time' => $time,
         'unread' => $unreadCount,
         'photo' => $photo,
         'last_message_at' => $lastMessageTime
     ];
    }, $rows);

    // Sort contacts by last message time (newest first)
    usort($contacts, function($a, $b) {
    $timeA = strtotime($a['last_message_at'] ?? '1970-01-01');
    $timeB = strtotime($b['last_message_at'] ?? '1970-01-01');
    return $timeB - $timeA; // Descending order (newest first)
    });

// Fetch groups for current user
// For clients: only show groups from their own tenant and branch (no peering)
// For users: show groups from their tenant and peered tenants
$groups = [];
try {
    // Build allowed tenant list
    $allowedTenantIds = [$tenantId];
    
    // Only allow peering for non-clients
    if ($currentUserType !== 'client') {
        // Allowed tenants: self + approved peers (both directions)
        $peerSql = 'SELECT peer_tenant_id AS peer FROM tenant_peering WHERE tenant_id = ? AND status = "approved" 
                    UNION 
                    SELECT tenant_id AS peer FROM tenant_peering WHERE peer_tenant_id = ? AND status = "approved"';
        $peerStmt = secure_query($pdo, $peerSql, [$tenantId, $tenantId]);
        $peerTenantIds = $peerStmt ? array_map(function($r){ return (int)$r['peer']; }, $peerStmt->fetchAll()) : [];
        $allowedTenantIds = array_values(array_unique(array_merge([$tenantId], $peerTenantIds)));
    }
    
    if (count($allowedTenantIds) > 0) {
        $tenantIn = implode(',', array_fill(0, count($allowedTenantIds), '?'));
        $groupParams = $allowedTenantIds;
        
        $groupWhere = "g.tenant_id IN (" . $tenantIn . ")";
        
        // If current user is a client, also filter by branch
        if ($currentUserType === 'client') {
            $groupWhere .= " AND (g.branch_id = ? OR g.branch_id IS NULL)";
            $groupParams[] = $myBranch;
        }
        
        $groupStmt = secure_query($pdo, "
            SELECT g.id, g.group_name, g.description, g.group_type, g.profile_pic,
                   (SELECT COUNT(*) FROM chat_group_members WHERE group_id = g.id AND left_at IS NULL) as member_count,
                   (SELECT MAX(created_at) FROM chat_group_messages WHERE group_id = g.id) as last_message_at,
                   (SELECT content FROM chat_group_messages WHERE group_id = g.id ORDER BY created_at DESC LIMIT 1) as last_message_content
            FROM chat_groups g
            WHERE $groupWhere
            AND g.id IN (
                SELECT group_id FROM chat_group_members 
                WHERE member_id = ? AND member_type = ? AND left_at IS NULL
            )
            ORDER BY last_message_at DESC
        ", array_merge($groupParams, [$currentUserId, $currentUserType]));
        
        if ($groupStmt) {
            $groups = array_map(function($g) {
                // Format time for display in sidebar
                $time = '';
                if ($g['last_message_at']) {
                    $datetime = new DateTime($g['last_message_at']);
                    $now = new DateTime();
                    $diff = $now->diff($datetime);
                    
                    if ($diff->days === 0 && $datetime->format('Y-m-d') === $now->format('Y-m-d')) {
                        $time = $datetime->format('H:i');
                    } else if ($diff->days === 1) {
                        $time = 'Yesterday';
                    } else if ($diff->days < 7) {
                        $time = $datetime->format('l');
                    } else {
                        $time = $datetime->format('M d');
                    }
                }
                
                return [
                    'id' => (int)$g['id'],
                    'type' => 'group',
                    'group_name' => $g['group_name'],
                    'description' => $g['description'],
                    'group_type' => $g['group_type'],
                    'profile_pic' => $g['profile_pic'],
                    'member_count' => (int)$g['member_count'],
                    'last_message_at' => $g['last_message_at'],
                    'lastMessage' => $g['last_message_content'] ?: '',
                    'time' => $time
                ];
            }, $groupStmt->fetchAll(PDO::FETCH_ASSOC));
        }
    }
} catch (Exception $e) {
    error_log("Groups fetch error: " . $e->getMessage());
}

echo json_encode([
    'me' => [ 
        'id' => (int)$user['id'],
        'user_type' => $user['user_type'] ?: 'user', // 'user' or 'client'
        'tenant_id' => $tenantId, 
        'role' => $user['role'] ?: 'Unknown Role', 
		'name' => $user['name'] ?: 'Unknown user',
        'agency_name' => $user['agency_name'] ?: 'Unknown Agency' 
    ], 
    'contacts' => $contacts,
    'groups' => $groups
]);
exit;
?>