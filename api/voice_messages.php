<?php
/**
 * Voice Message API Handler
 * Handles voice message recording upload and retrieval
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ChatAudit.php';
require_once __DIR__ . '/../includes/RateLimiter.php';
require_once __DIR__ . '/../includes/SecureFileUpload.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$sessionRole = $_SESSION['role'] ?? 'user'; // 'admin', 'client', 'sales_agent', etc.
$method = $_SERVER['REQUEST_METHOD'];

// Validate current user and get tenant and role (check both users and clients)
$stmt = secure_query($pdo, 'SELECT id, tenant_id, role FROM users WHERE id = ?', [$currentUserId]);
$me = $stmt ? $stmt->fetch() : null;

// If not a user, check if it's a client
if (!$me && $sessionRole === 'client') {
    $stmt = secure_query($pdo, 'SELECT id, tenant_id, status as role FROM clients WHERE id = ?', [$currentUserId]);
    $me = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
}

if (!$me) {
    http_response_code(404);
    echo json_encode(['error' => 'user_not_found']);
    exit;
}
$tenantId = (int)$me['tenant_id'];
$userRole = $me['role'];

// Normalize user type: 'user' for all non-client users, 'client' for clients
$currentUserType = ($sessionRole === 'client' ? 'client' : 'user');

// Helper function to create room ID
function room_from_users($a, $b, $typeA = 'user', $typeB = 'user') {
    // Create pairs and sort them to ensure consistent room IDs
    $pairs = [
        ['id' => $a, 'type' => $typeA],
        ['id' => $b, 'type' => $typeB]
    ];
    
    // Sort by ID numerically to ensure consistent ordering
    usort($pairs, function($x, $y) {
        return $x['id'] - $y['id'];
    });
    
    // Format: msg-type1_id1-type2_id2 (where type is 'u' for user, 'c' for client)
    $t1 = substr($pairs[0]['type'], 0, 1);
    $t2 = substr($pairs[1]['type'], 0, 1);
    return 'msg-' . $t1 . $pairs[0]['id'] . '-' . $t2 . $pairs[1]['id'];
}

// POST: Upload voice message
if ($method === 'POST') {
    // Validate form data
    if (!isset($_POST['to_user_id']) || !isset($_FILES['audio'])) {
        http_response_code(400);
        echo json_encode(['error' => 'missing_required_fields']);
        exit;
    }

    $toUserId = (int)$_POST['to_user_id'];
    $toUserType = isset($_POST['to_user_type']) ? $_POST['to_user_type'] : 'user'; // 'user' or 'client'
    $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 0;
    $audioFile = $_FILES['audio'];

    if ($toUserId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_recipient']);
        exit;
    }

    // Validate audio file
    if ($audioFile['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'upload_failed', 'details' => $audioFile['error']]);
        exit;
    }

    // Validate audio MIME types
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $actualMime = finfo_file($finfo, $audioFile['tmp_name']);
    finfo_close($finfo);
    
    // Allow both audio and video webm formats (webm containers are used for both)
    $allowedMimes = ['audio/webm', 'video/webm', 'audio/mp4', 'audio/mpeg', 'audio/ogg', 'audio/wav'];
    if (!in_array($actualMime, $allowedMimes)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_audio_format', 'detected' => $actualMime]);
        exit;
    }

    // Rate limit check
    if (!RateLimiter::isAllowed($currentUserId, 'messages_per_hour', $tenantId)) {
        http_response_code(429);
        echo json_encode(['error' => 'rate_limited']);
        exit;
    }

    // Validate recipient
    $recipientStmt = secure_query($pdo, 'SELECT id, tenant_id, branch_id, role, deleted_at, fired FROM users WHERE id = ?', [$toUserId]);
    $recipient = $recipientStmt ? $recipientStmt->fetch(PDO::FETCH_ASSOC) : null;
    
    if (!$recipient || $recipient['deleted_at'] !== null || $recipient['fired']) {
        http_response_code(404);
        echo json_encode(['error' => 'recipient_not_found']);
        exit;
    }

    $recipientTenant = (int)$recipient['tenant_id'];
    $recipientBranch = (int)$recipient['branch_id'];
    $recipientRole = $recipient['role'];

    // Get current user's branch and info
    $meStmt = secure_query($pdo, 'SELECT branch_id FROM users WHERE id = ?', [$currentUserId]);
    $meUser = $meStmt ? $meStmt->fetch(PDO::FETCH_ASSOC) : null;
    $myBranch = $meUser ? (int)$meUser['branch_id'] : 0;

    // Validate branch compatibility (same tenant = same branch UNLESS either user is tenant_super_admin)
    if ($recipientTenant === $tenantId && $recipientBranch !== $myBranch && $userRole !== 'tenant_super_admin' && $recipientRole !== 'tenant_super_admin') {
        if (class_exists('ChatAudit')) {
            try {
                ChatAudit::logFailedAccess($tenantId, $myBranch, $currentUserId, $toUserId, 'send_message', 'cross_branch_denied', 'Cross-branch chat not allowed');
            } catch (Exception $e) {
                error_log('[VoiceAPI] ChatAudit cross-branch log failed: ' . $e->getMessage());
            }
        }
        http_response_code(403);
        echo json_encode(['error' => 'cross_branch_chat_not_allowed']);
        exit;
    }

    // Check block relations (if table exists)
    try {
        $checkBlockTable = $pdo->query("SHOW TABLES LIKE 'user_blocks'");
        if ($checkBlockTable && $checkBlockTable->rowCount() > 0) {
            $blockedA = secure_query($pdo, 'SELECT 1 FROM user_blocks WHERE tenant_id = ? AND user_id = ? AND blocked_user_id = ? LIMIT 1', [$tenantId, $currentUserId, $toUserId]);
            $blockedB = secure_query($pdo, 'SELECT 1 FROM user_blocks WHERE tenant_id = ? AND user_id = ? AND blocked_user_id = ? LIMIT 1', [$tenantId, $toUserId, $currentUserId]);
            if (($blockedA && $blockedA->fetch()) || ($blockedB && $blockedB->fetch())) {
                if (class_exists('ChatAudit')) {
                    ChatAudit::logFailedAccess($tenantId, $myBranch, $currentUserId, $toUserId, 'send_message', 'user_blocked', 'User blocked');
                }
                http_response_code(403);
                echo json_encode(['error' => 'blocked']);
                exit;
            }
        }
    } catch (Exception $e) {
        error_log('[VoiceAPI] Block check failed: ' . $e->getMessage());
    }

    // Upload audio file manually (bypass SecureFileUpload as it doesn't support audio formats)
    try {
        // Create upload directory
        $uploadDir = __DIR__ . '/../uploads/voices/' . $tenantId . '/' . $currentUserId;
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                http_response_code(500);
                echo json_encode(['error' => 'upload_error', 'details' => 'Failed to create upload directory']);
                exit;
            }
        }
        
        // Generate safe filename
        $filename = 'voice_' . time() . '_' . uniqid() . '.webm';
        $filepath = $uploadDir . '/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($audioFile['tmp_name'], $filepath)) {
            http_response_code(500);
            echo json_encode(['error' => 'upload_error', 'details' => 'Failed to move uploaded file']);
            exit;
        }
        
        // Set proper permissions
        chmod($filepath, 0644);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'upload_error', 'details' => $e->getMessage()]);
        exit;
    }

    // Create relative URL for storage
    $voiceUrl = 'uploads/voices/' . $tenantId . '/' . $currentUserId . '/' . $filename;

    // Store voice message in database
    $room = room_from_users($currentUserId, $toUserId, $currentUserType, $toUserType);
    
    // Create content JSON with voice metadata
    $content = json_encode([
        'type' => 'voice',
        'duration' => $duration,
        'filename' => $filename,
        'url' => $voiceUrl
    ]);

    try {
        // Check if message_type and duration columns exist
        $checkStmt = $pdo->query("
            SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'chat_messages' 
            AND COLUMN_NAME IN ('message_type', 'duration')
        ");
        $existingColumns = $checkStmt ? $checkStmt->fetchAll(PDO::FETCH_COLUMN) : [];

        // If columns don't exist, add them
        if (!in_array('message_type', $existingColumns)) {
            $pdo->exec("ALTER TABLE chat_messages ADD COLUMN message_type VARCHAR(20) DEFAULT 'text'");
        }
        if (!in_array('duration', $existingColumns)) {
            $pdo->exec("ALTER TABLE chat_messages ADD COLUMN duration INT DEFAULT 0");
        }

        $insertStmt = secure_query($pdo,
            'INSERT INTO chat_messages (room_id, from_user_id, to_user_id, tenant_id_from, content, message_type, duration, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            [$room, $currentUserId, $toUserId, $tenantId, $content, 'voice', $duration]
        );

        if ($insertStmt && $insertStmt->rowCount() > 0) {
            $messageId = $pdo->lastInsertId();

            // Log the action (ignore errors if ChatAudit not available)
            if (class_exists('ChatAudit')) {
                try {
                    ChatAudit::logSend($tenantId, $myBranch, $currentUserId, $toUserId, strlen($content), false);
                } catch (Exception $e) {
                    error_log('[VoiceAPI] ChatAudit log failed: ' . $e->getMessage());
                }
            }

            // Update rate limiter (ignore errors if RateLimiter not available)
            if (class_exists('RateLimiter')) {
                try {
                    RateLimiter::recordAction($currentUserId, 'messages_per_hour', $tenantId);
                    RateLimiter::recordAction($currentUserId, 'messages_per_day', $tenantId);
                } catch (Exception $e) {
                    error_log('[VoiceAPI] RateLimiter failed: ' . $e->getMessage());
                }
            }

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message_id' => $messageId,
                'id' => $messageId,
                'url' => $voiceUrl,
                'duration' => $duration,
                'message_type' => 'voice'
            ]);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'database_insert_failed']);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'internal_error',
            'message' => $e->getMessage(),
            'details' => 'Check server logs for details'
        ]);
        exit;
    }
}

// GET: Stream voice message
if ($method === 'GET') {
    if (!isset($_GET['message_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'missing_message_id']);
        exit;
    }

    $messageId = (int)$_GET['message_id'];

    // Fetch message
    $messageStmt = secure_query($pdo,
        'SELECT id, room_id, from_user_id, to_user_id, content, message_type, duration FROM chat_messages WHERE id = ?',
        [$messageId]
    );
    $message = $messageStmt ? $messageStmt->fetch(PDO::FETCH_ASSOC) : null;

    if (!$message || $message['message_type'] !== 'voice') {
        http_response_code(404);
        echo json_encode(['error' => 'message_not_found']);
        exit;
    }

    // Validate access
    if ($message['from_user_id'] !== $currentUserId && $message['to_user_id'] !== $currentUserId) {
        http_response_code(403);
        echo json_encode(['error' => 'access_denied']);
        exit;
    }

    // Parse content to get file path
    try {
        $contentData = json_decode($message['content'], true);
        if (!isset($contentData['filename'])) {
            http_response_code(404);
            echo json_encode(['error' => 'file_not_found']);
            exit;
        }

        // Validate filename to prevent directory traversal
        if (strpos($contentData['filename'], '..') !== false || strpos($contentData['filename'], '/') !== false) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_filename']);
            exit;
        }
        
        $filepath = __DIR__ . '/../uploads/voices/' . $tenantId . '/' . $message['from_user_id'] . '/' . $contentData['filename'];

        if (!file_exists($filepath)) {
            http_response_code(404);
            echo json_encode(['error' => 'file_not_found']);
            exit;
        }

        // Return file stream
        header('Content-Type: audio/webm');
        header('Content-Length: ' . filesize($filepath));
        header('Content-Disposition: attachment; filename="' . $contentData['filename'] . '"');
        header('Cache-Control: public, max-age=31536000');
        
        readfile($filepath);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'stream_failed']);
        exit;
    }
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
exit;
