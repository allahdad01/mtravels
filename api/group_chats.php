<?php
/**
 * Group Chats API
 * Handle CRUD operations for group chats including users and clients
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

// CSRF Protection for POST/PUT/DELETE requests
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE']) && !verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['error' => 'Security validation failed. Please try again.']);
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$sessionRole = $_SESSION['role'] ?? 'user'; // 'admin', 'client', 'sales_agent', etc.
$tenantId = (int)$_SESSION['tenant_id'];
$branchId = (int)($_SESSION['branch_id'] ?? 0);

// Normalize user type: 'user' for all non-client users, 'client' for clients
$currentUserType = ($sessionRole === 'client' ? 'client' : 'user');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get all groups for current user
    $stmt = secure_query($pdo, "
        SELECT g.id, g.group_name, g.description, g.group_type, g.branch_id, 
               g.profile_pic, g.created_by_id, g.created_by_type,
               (SELECT COUNT(*) FROM chat_group_members WHERE group_id = g.id AND left_at IS NULL) as member_count,
               (SELECT COUNT(*) FROM chat_group_messages WHERE group_id = g.id) as message_count,
               (SELECT MAX(created_at) FROM chat_group_messages WHERE group_id = g.id) as last_message_at
        FROM chat_groups g
        WHERE g.tenant_id = ? 
        AND g.id IN (
            SELECT group_id FROM chat_group_members 
            WHERE member_id = ? AND member_type = ? AND left_at IS NULL
        )
        ORDER BY last_message_at DESC
    ", [$tenantId, $currentUserId, $currentUserType]);
    
    $groups = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    
    echo json_encode(['success' => true, 'groups' => $groups]);
    exit;
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        // Clients can only create groups within their own branch
        if ($currentUserType === 'client') {
            http_response_code(403);
            echo json_encode(['error' => 'Clients cannot create groups']);
            exit;
        }
        
        $groupName = trim($_POST['group_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $groupType = $_POST['group_type'] ?? 'custom'; // 'branch' or 'custom'
        $branchIdParam = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
        $memberIds = isset($_POST['member_ids']) ? json_decode($_POST['member_ids'], true) : [];
        $memberTypes = isset($_POST['member_types']) ? json_decode($_POST['member_types'], true) : [];
        
        if (empty($groupName) || strlen($groupName) > 255) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid group name']);
            exit;
        }
        
        // Validate group type
        if (!in_array($groupType, ['branch', 'custom'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid group type']);
            exit;
        }
        
        // If branch type, validate branch_id
        if ($groupType === 'branch') {
            if (!$branchIdParam) {
                http_response_code(400);
                echo json_encode(['error' => 'Branch ID required for branch groups']);
                exit;
            }
            // Verify user has access to this branch
            $branchStmt = secure_query($pdo, "SELECT id FROM branches WHERE id = ? AND tenant_id = ?", [$branchIdParam, $tenantId]);
            if (!$branchStmt || !$branchStmt->fetch()) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied to this branch']);
                exit;
            }
        } else {
            // For custom groups, if user is not admin, set branch to their branch
            if ($currentUserType !== 'admin' && $currentUserType !== 'tenant_super_admin') {
                $branchIdParam = $branchId;
            }
        }
        
        try {
            // Create group
            $insertStmt = secure_query($pdo, "
                INSERT INTO chat_groups (tenant_id, group_name, description, group_type, branch_id, created_by_id, created_by_type)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [$tenantId, $groupName, $description, $groupType, $branchIdParam, $currentUserId, $currentUserType]);
            
            if (!$insertStmt) {
                throw new Exception('Failed to create group');
            }
            
            $groupId = $pdo->lastInsertId();
            
            // Add creator as admin member
            secure_query($pdo, "
                INSERT INTO chat_group_members (group_id, member_id, member_type, role)
                VALUES (?, ?, ?, 'admin')
            ", [$groupId, $currentUserId, $currentUserType]);
            
            // Add other members (allow cross-tenant members)
            if (!empty($memberIds)) {
                $addedMembers = 0;
                $failedMembers = [];
                
                for ($i = 0; $i < count($memberIds); $i++) {
                    $memberId = (int)$memberIds[$i];
                    $memberType = $memberTypes[$i] ?? 'user';
                    
                    // Validate member exists (allow cross-tenant members)
                    if ($memberType === 'client') {
                        $memberStmt = secure_query($pdo, "SELECT id FROM clients WHERE id = ?", [$memberId]);
                    } else {
                        $memberStmt = secure_query($pdo, "SELECT id FROM users WHERE id = ?", [$memberId]);
                    }
                    
                    if ($memberStmt && $memberStmt->fetch()) {
                        // Insert each member individually to ensure all are inserted
                        secure_query($pdo, "
                            INSERT INTO chat_group_members (group_id, member_id, member_type, role)
                            VALUES (?, ?, ?, 'member')
                        ", [$groupId, $memberId, $memberType]);
                        $addedMembers++;
                    } else {
                        $failedMembers[] = "$memberId ($memberType)";
                    }
                }
                
                if (!empty($failedMembers)) {
                    error_log("Group $groupId: Failed to add members: " . implode(', ', $failedMembers));
                }
            }
            
            echo json_encode(['success' => true, 'group_id' => (int)$groupId, 'group_name' => $groupName]);
            exit;
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create group']);
            exit;
        }
    }
    
    if ($action === 'add_members') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        $memberIds = isset($_POST['member_ids']) ? json_decode($_POST['member_ids'], true) : [];
        $memberTypes = isset($_POST['member_types']) ? json_decode($_POST['member_types'], true) : [];
        
        if ($groupId <= 0 || empty($memberIds)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input']);
            exit;
        }
        
        // Verify user is admin of group
        $adminStmt = secure_query($pdo, "
            SELECT id FROM chat_group_members 
            WHERE group_id = ? AND member_id = ? AND member_type = ? AND role = 'admin'
        ", [$groupId, $currentUserId, $currentUserType]);
        
        if (!$adminStmt || !$adminStmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Only group admins can add members']);
            exit;
        }
        
        $added = 0;
        for ($i = 0; $i < count($memberIds); $i++) {
            $memberId = (int)$memberIds[$i];
            $memberType = $memberTypes[$i] ?? 'user';
            
            // Check if already a member
            $existStmt = secure_query($pdo, "
                SELECT id FROM chat_group_members 
                WHERE group_id = ? AND member_id = ? AND member_type = ?
            ", [$groupId, $memberId, $memberType]);
            
            if (!$existStmt || !$existStmt->fetch()) {
                $insertStmt = secure_query($pdo, "
                    INSERT INTO chat_group_members (group_id, member_id, member_type, role)
                    VALUES (?, ?, ?, 'member')
                ", [$groupId, $memberId, $memberType]);
                
                if ($insertStmt && $insertStmt->rowCount() > 0) {
                    $added++;
                }
            }
        }
        
        echo json_encode(['success' => true, 'added' => $added]);
        exit;
    }
    
    if ($action === 'remove_member') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        $memberId = (int)($_POST['member_id'] ?? 0);
        $memberType = $_POST['member_type'] ?? 'user';
        
        if ($groupId <= 0 || $memberId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input']);
            exit;
        }
        
        // Verify user is admin or is removing self
        if ($currentUserId !== $memberId || $currentUserType !== $memberType) {
            $adminStmt = secure_query($pdo, "
                SELECT id FROM chat_group_members 
                WHERE group_id = ? AND member_id = ? AND member_type = ? AND role = 'admin'
            ", [$groupId, $currentUserId, $currentUserType]);
            
            if (!$adminStmt || !$adminStmt->fetch()) {
                http_response_code(403);
                echo json_encode(['error' => 'Only group admins can remove members']);
                exit;
            }
        }
        
        // Remove member
        $stmt = secure_query($pdo, "
            UPDATE chat_group_members 
            SET left_at = NOW()
            WHERE group_id = ? AND member_id = ? AND member_type = ?
        ", [$groupId, $memberId, $memberType]);
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'leave') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        
        if ($groupId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid group ID']);
            exit;
        }
        
        $stmt = secure_query($pdo, "
            UPDATE chat_group_members 
            SET left_at = NOW()
            WHERE group_id = ? AND member_id = ? AND member_type = ? AND left_at IS NULL
        ", [$groupId, $currentUserId, $currentUserType]);
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'update') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        $groupName = trim($_POST['group_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if ($groupId <= 0 || empty($groupName)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input']);
            exit;
        }
        
        // Verify user is admin of the group
        $adminStmt = secure_query($pdo, "
            SELECT id FROM chat_group_members 
            WHERE group_id = ? AND member_id = ? AND member_type = ? AND role = 'admin'
        ", [$groupId, $currentUserId, $currentUserType]);
        
        if (!$adminStmt || !$adminStmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Only group admins can edit the group']);
            exit;
        }
        
        // Update group
        $stmt = secure_query($pdo, "
            UPDATE chat_groups
            SET group_name = ?, description = ?
            WHERE id = ?
        ", [$groupName, $description, $groupId]);
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'update_image') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        
        if ($groupId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid group ID']);
            exit;
        }
        
        if (!isset($_FILES['image'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No image provided']);
            exit;
        }
        
        // Verify user is admin of the group
        $adminStmt = secure_query($pdo, "
            SELECT id FROM chat_group_members 
            WHERE group_id = ? AND member_id = ? AND member_type = ? AND role = 'admin'
        ", [$groupId, $currentUserId, $currentUserType]);
        
        if (!$adminStmt || !$adminStmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Only group admins can change the group image']);
            exit;
        }
        
        try {
            $file = $_FILES['image'];
            $filename = $groupId . '_' . time() . '_' . basename($file['name']);
            $uploadDir = __DIR__ . '/../uploads/groups/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $filepath = $uploadDir . $filename;
            
            // Validate file type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mimeType, $allowedMimes)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid image format']);
                exit;
            }
            
            // Move file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to upload image']);
                exit;
            }
            
            // Store relative path in database
            $imagePath = 'uploads/groups/' . $filename;
            
            // Update group image
            secure_query($pdo, "
                UPDATE chat_groups
                SET profile_pic = ?
                WHERE id = ?
            ", [$imagePath, $groupId]);
            
            echo json_encode(['success' => true, 'image_url' => $imagePath]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to upload image']);
            exit;
        }
    }
    
    if ($action === 'delete') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        
        if ($groupId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid group ID']);
            exit;
        }
        
        // Verify user is admin of the group
        $adminStmt = secure_query($pdo, "
            SELECT id FROM chat_group_members 
            WHERE group_id = ? AND member_id = ? AND member_type = ? AND role = 'admin'
        ", [$groupId, $currentUserId, $currentUserType]);
        
        if (!$adminStmt || !$adminStmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Only group admins can delete the group']);
            exit;
        }
        
        try {
            // Delete group (this will cascade to members and messages if set up properly)
            secure_query($pdo, "DELETE FROM chat_groups WHERE id = ?", [$groupId]);
            
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete group']);
            exit;
        }
    }
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
exit;
?>
