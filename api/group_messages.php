<?php
/**
 * Group Messages API
 * Handle sending, retrieving, and managing group chat messages
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

// Normalize user type: 'user' for all non-client users, 'client' for clients
$currentUserType = ($sessionRole === 'client' ? 'client' : 'user');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get messages for a group
    $groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
    $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;
    $beforeId = isset($_GET['before_id']) ? (int)$_GET['before_id'] : 0;
    
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
        SELECT id FROM chat_group_members 
        WHERE group_id = ? AND member_id = ? AND member_type = ? AND left_at IS NULL
    ", [$groupId, $currentUserId, $currentUserType]);
    
    if (!$memberStmt || !$memberStmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    
    // Get messages
    $params = [$groupId];
    $where = 'm.group_id = ? AND m.deleted_at IS NULL';
    if ($beforeId > 0) {
        $where .= ' AND m.id < ?';
        $params[] = $beforeId;
    }
    
    $stmt = secure_query($pdo, "
        SELECT m.id, m.group_id, m.from_user_id, m.from_user_type, m.content, m.message_type,
               m.file_url, m.file_name, m.file_size,
               DATE_FORMAT(m.created_at, '%Y-%m-%dT%H:%i:%sZ') AS created_at,
               CASE WHEN m.from_user_type = 'client' THEN c.name ELSE u.name END as sender_name
        FROM chat_group_messages m
        LEFT JOIN users u ON m.from_user_type = 'user' AND m.from_user_id = u.id
        LEFT JOIN clients c ON m.from_user_type = 'client' AND m.from_user_id = c.id
        WHERE $where
        ORDER BY m.id DESC
        LIMIT $limit
    ", $params);
    
    $messages = $stmt ? array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC)) : [];
    $nextBeforeId = count($messages) ? (int)$messages[0]['id'] : 0;
    
    echo json_encode([
        'success' => true,
        'group_id' => $groupId,
        'messages' => $messages,
        'next_before_id' => $nextBeforeId
    ]);
    exit;
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        $messageType = $_POST['message_type'] ?? 'text';
        $content = trim($_POST['content'] ?? '');
        
        // For voice messages, content comes from file upload, not POST
        if ($messageType === 'voice') {
            if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['error' => 'No audio file provided or upload error']);
                exit;
            }
            
            $audioFile = $_FILES['audio'];
            $duration = (int)($_POST['duration'] ?? 0);
            
            // Validate audio MIME types
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $actualMime = finfo_file($finfo, $audioFile['tmp_name']);
            finfo_close($finfo);
            
            $allowedMimes = ['audio/webm', 'video/webm', 'audio/mp4', 'audio/mpeg', 'audio/ogg', 'audio/wav'];
            if (!in_array($actualMime, $allowedMimes)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid audio format']);
                exit;
            }
            
            // Upload audio file
            try {
                $uploadDir = __DIR__ . '/../uploads/voices/' . $tenantId . '/group_' . $groupId;
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        throw new Exception('Failed to create upload directory');
                    }
                }
                
                $filename = 'voice_' . time() . '_' . uniqid() . '.webm';
                $filepath = $uploadDir . '/' . $filename;
                
                if (!move_uploaded_file($audioFile['tmp_name'], $filepath)) {
                    throw new Exception('Failed to move uploaded file');
                }
                
                chmod($filepath, 0644);
                
                $voiceUrl = 'uploads/voices/' . $tenantId . '/group_' . $groupId . '/' . $filename;
                $content = json_encode([
                    'type' => 'voice',
                    'duration' => $duration,
                    'filename' => $filename,
                    'url' => $voiceUrl
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Upload failed: ' . $e->getMessage()]);
                exit;
            }
        } elseif (empty($content)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input']);
            exit;
        }
        
        if ($groupId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input']);
            exit;
        }
        
        // Verify user is member of group
        $memberStmt = secure_query($pdo, "
            SELECT id FROM chat_group_members 
            WHERE group_id = ? AND member_id = ? AND member_type = ? AND left_at IS NULL
        ", [$groupId, $currentUserId, $currentUserType]);
        
        if (!$memberStmt || !$memberStmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }
        
        // Validate message type
        if (!in_array($messageType, ['text', 'file', 'voice', 'image'])) {
            $messageType = 'text';
        }
        
        try {
            // Insert message
            $insertStmt = secure_query($pdo, "
                INSERT INTO chat_group_messages 
                (group_id, from_user_id, from_user_type, content, message_type)
                VALUES (?, ?, ?, ?, ?)
            ", [$groupId, $currentUserId, $currentUserType, $content, $messageType]);
            
            if (!$insertStmt) {
                throw new Exception('Failed to send message');
            }
            
            $messageId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message_id' => (int)$messageId,
                'group_id' => $groupId
            ]);
            exit;
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to send message']);
            exit;
        }
    }
    
    if ($action === 'mark_read') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        $messageIds = isset($_POST['message_ids']) ? json_decode($_POST['message_ids'], true) : [];
        
        if ($groupId <= 0 || empty($messageIds)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input']);
            exit;
        }
        
        $marked = 0;
        foreach ($messageIds as $msgId) {
            $msgId = (int)$msgId;
            
            // Check if not already marked
            $checkStmt = secure_query($pdo, "
                SELECT id FROM chat_group_message_reads
                WHERE message_id = ? AND member_id = ? AND member_type = ?
            ", [$msgId, $currentUserId, $currentUserType]);
            
            if (!$checkStmt || !$checkStmt->fetch()) {
                $insertStmt = secure_query($pdo, "
                    INSERT INTO chat_group_message_reads 
                    (message_id, member_id, member_type)
                    VALUES (?, ?, ?)
                ", [$msgId, $currentUserId, $currentUserType]);
                
                if ($insertStmt && $insertStmt->rowCount() > 0) {
                    $marked++;
                }
            }
        }
        
        echo json_encode(['success' => true, 'marked' => $marked]);
        exit;
    }
    
    if ($action === 'delete') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        
        if ($messageId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid message ID']);
            exit;
        }
        
        // Verify message belongs to current user
        $msgStmt = secure_query($pdo, "
            SELECT id FROM chat_group_messages 
            WHERE id = ? AND from_user_id = ? AND from_user_type = ?
        ", [$messageId, $currentUserId, $currentUserType]);
        
        if (!$msgStmt || !$msgStmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }
        
        $stmt = secure_query($pdo, "
            UPDATE chat_group_messages 
            SET deleted_by_user_id = ?, deleted_at = NOW()
            WHERE id = ?
        ", [$currentUserId, $messageId]);
        
        echo json_encode(['success' => true]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
exit;
?>
