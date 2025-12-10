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

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Validate current user and get tenant
$stmt = secure_query($pdo, 'SELECT id, tenant_id FROM users WHERE id = ?', [$currentUserId]);
$me = $stmt ? $stmt->fetch() : null;
if (!$me) {
    http_response_code(404);
    echo json_encode(['error' => 'user_not_found']);
    exit;
}
$tenantId = (int)$me['tenant_id'];

// Helper function to create room ID
function room_from_users($a, $b) {
    $ids = [$a, $b];
    sort($ids, SORT_NUMERIC);
    return 'u-' . $ids[0] . '-' . $ids[1];
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

    // Check file type (audio only)
    $mimeType = $audioFile['type'];
    $allowed_types = ['audio/webm', 'audio/mp4', 'audio/mpeg', 'audio/ogg', 'audio/wav'];
    if (!in_array($mimeType, $allowed_types)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_audio_format']);
        exit;
    }

    // Check file size (10MB max for voice)
    $maxSize = 10 * 1024 * 1024;
    if ($audioFile['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['error' => 'file_too_large', 'max_bytes' => $maxSize]);
        exit;
    }

    // Rate limit check
    if (!RateLimiter::isAllowed($currentUserId, 'messages_per_hour', $tenantId)) {
        http_response_code(429);
        echo json_encode(['error' => 'rate_limited']);
        exit;
    }

    // Validate recipient
    $recipientStmt = secure_query($pdo, 'SELECT id, tenant_id, branch_id, deleted_at, fired FROM users WHERE id = ?', [$toUserId]);
    $recipient = $recipientStmt ? $recipientStmt->fetch(PDO::FETCH_ASSOC) : null;
    
    if (!$recipient || $recipient['deleted_at'] !== null || $recipient['fired']) {
        http_response_code(404);
        echo json_encode(['error' => 'recipient_not_found']);
        exit;
    }

    $recipientTenant = (int)$recipient['tenant_id'];
    $recipientBranch = (int)$recipient['branch_id'];

    // Get current user's branch and info
    $meStmt = secure_query($pdo, 'SELECT branch_id FROM users WHERE id = ?', [$currentUserId]);
    $meUser = $meStmt ? $meStmt->fetch(PDO::FETCH_ASSOC) : null;
    $myBranch = $meUser ? (int)$meUser['branch_id'] : 0;

    // Validate branch compatibility
    if ($recipientTenant === $tenantId && $recipientBranch !== $myBranch) {
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

    // Create uploads directory if it doesn't exist
    $voicesDir = __DIR__ . '/../uploads/voices';
    if (!is_dir($voicesDir)) {
        mkdir($voicesDir, 0755, true);
    }

    // Generate unique filename
    $filename = 'voice_' . $tenantId . '_' . $currentUserId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.webm';
    $filepath = $voicesDir . '/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($audioFile['tmp_name'], $filepath)) {
        http_response_code(500);
        echo json_encode(['error' => 'file_save_failed']);
        exit;
    }

    // Create relative URL for storage
    $voiceUrl = 'uploads/voices/' . $filename;

    // Store voice message in database
    $room = room_from_users($currentUserId, $toUserId);
    
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
        error_log('[VoiceAPI] Error: ' . $e->getMessage());
        error_log('[VoiceAPI] Exception: ' . $e->getFile() . ':' . $e->getLine());
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

        $filepath = __DIR__ . '/../uploads/voices/' . $contentData['filename'];

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
