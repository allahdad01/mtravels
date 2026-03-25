<?php
/**
 * Group Members API
 * Get members for a group
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../admin/security.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$sessionRole = $_SESSION['role'] ?? 'user'; // 'admin', 'client', 'sales_agent', etc.
$tenantId = (int)$_SESSION['tenant_id'];

// Normalize user type: 'user' for all non-client users, 'client' for clients
$currentUserType = ($sessionRole === 'client' ? 'client' : 'user');

// Get group_id from GET or POST
$groupId = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupId = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
} else {
    $groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
}

if ($groupId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_group']);
    exit;
}

// Verify group exists and get its details
$groupStmt = secure_query($pdo, "
    SELECT id, tenant_id, branch_id FROM chat_groups
    WHERE id = ? AND tenant_id = ?
", [$groupId, $tenantId]);

$group = $groupStmt ? $groupStmt->fetch(PDO::FETCH_ASSOC) : null;

if (!$group) {
    http_response_code(404);
    echo json_encode(['error' => 'Group not found']);
    exit;
}

// For clients, verify group is in their branch
if ($currentUserType === 'client') {
    if ($group['branch_id'] && $group['branch_id'] != $_SESSION['branch_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
}

// Verify user is member of group
$memberStmt = secure_query($pdo, "
    SELECT id, role FROM chat_group_members 
    WHERE group_id = ? AND member_id = ? AND member_type = ? AND left_at IS NULL
", [$groupId, $currentUserId, $currentUserType]);

$currentMember = $memberStmt ? $memberStmt->fetch(PDO::FETCH_ASSOC) : null;

if (!$currentMember) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Handle POST requests (remove member)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection for POST requests
    if (!verify_csrf_token()) {
        http_response_code(403);
        echo json_encode(['error' => 'Security validation failed. Please try again.']);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'remove') {
        $memberId = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
        $memberType = isset($_POST['member_type']) ? $_POST['member_type'] : '';
        
        if ($memberId <= 0 || !in_array($memberType, ['user', 'client'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid member data']);
            exit;
        }
        
        // Only group admins can remove members
        if ($currentMember['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Only group admins can remove members']);
            exit;
        }
        
        // Can't remove the last admin
        $adminCountStmt = secure_query($pdo, "
            SELECT COUNT(*) as count FROM chat_group_members
            WHERE group_id = ? AND role = 'admin' AND left_at IS NULL
        ", [$groupId]);
        
        $adminCount = $adminCountStmt ? (int)$adminCountStmt->fetchColumn() : 0;
        
        if ($adminCount <= 1) {
            // Check if trying to remove the last admin
            $targetStmt = secure_query($pdo, "
                SELECT role FROM chat_group_members
                WHERE group_id = ? AND member_id = ? AND member_type = ? AND left_at IS NULL
            ", [$groupId, $memberId, $memberType]);
            
            $targetMember = $targetStmt ? $targetStmt->fetch(PDO::FETCH_ASSOC) : null;
            
            if ($targetMember && $targetMember['role'] === 'admin') {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot remove the last admin from the group']);
                exit;
            }
        }
        
        // Remove the member
        $removeStmt = secure_query($pdo, "
            UPDATE chat_group_members
            SET left_at = NOW()
            WHERE group_id = ? AND member_id = ? AND member_type = ?
        ", [$groupId, $memberId, $memberType]);
        
        if (!$removeStmt) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to remove member']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Member removed successfully'
        ]);
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

// Handle GET requests (list members)
// Get all members
$stmt = secure_query($pdo, "
    SELECT cgm.member_id, cgm.member_type, cgm.role, cgm.joined_at,
           CASE 
               WHEN cgm.member_type = 'client' THEN c.name
               ELSE u.name
           END as name,
           CASE 
               WHEN cgm.member_type = 'client' THEN c.image
               ELSE u.profile_pic
           END as profile_pic
     FROM chat_group_members cgm
     LEFT JOIN users u ON cgm.member_type = 'user' AND cgm.member_id = u.id
     LEFT JOIN clients c ON cgm.member_type = 'client' AND cgm.member_id = c.id
     WHERE cgm.group_id = ? AND cgm.left_at IS NULL
     ORDER BY cgm.role DESC, cgm.joined_at ASC
", [$groupId]);

$members = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

echo json_encode([
    'success' => true,
    'group_id' => $groupId,
    'members' => $members,
    'total' => count($members)
]);
exit;
?>
