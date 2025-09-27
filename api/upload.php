<?php

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_check.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

// Get user's tenant and settings
$stmt = secure_query($pdo, 'SELECT u.tenant_id, t.chat_max_file_bytes, t.chat_allowed_mime_prefixes 
                           FROM users u 
                           JOIN tenants t ON u.tenant_id = t.id 
                           WHERE u.id = ?', [$currentUserId]);
$user = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'user_not_found']);
    exit;
}
$tenantId = (int)$user['tenant_id'];
$maxFileBytes = (int)$user['chat_max_file_bytes'];
$allowedMimePrefixes = explode(',', $user['chat_allowed_mime_prefixes']);

// Validate file
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'no_file_uploaded']);
    exit;
}

$file = $_FILES['file'];
$toUserId = isset($_POST['to_user_id']) ? (int)$_POST['to_user_id'] : 0;
if ($toUserId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_peer']);
    exit;
}

if ($file['size'] > $maxFileBytes) {
    http_response_code(400);
    echo json_encode(['error' => 'file_too_large']);
    exit;
}

$mimeType = mime_content_type($file['tmp_name']);
if (!in_array($mimeType, array_map('trim', $allowedMimePrefixes), true)) {
    $prefixMatch = false;
    foreach ($allowedMimePrefixes as $prefix) {
        if (strpos($mimeType, trim($prefix)) === 0) {
            $prefixMatch = true;
            break;
        }
    }
    if (!$prefixMatch) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_file_type']);
        exit;
    }
}

// Verify peer and tenant peering
$peerStmt = secure_query($pdo, 'SELECT tenant_id FROM users WHERE id = ?', [$toUserId]);
$peer = $peerStmt ? $peerStmt->fetch(PDO::FETCH_ASSOC) : null;
if (!$peer) {
    http_response_code(404);
    echo json_encode(['error' => 'peer_not_found']);
    exit;
}
$peerTenantId = (int)$peer['tenant_id'];
if ($peerTenantId !== $tenantId) {
    $allow = secure_query($pdo, 'SELECT 1 FROM tenant_peering WHERE status = "approved" AND ((tenant_id = ? AND peer_tenant_id = ?) OR (tenant_id = ? AND peer_tenant_id = ?)) LIMIT 1', 
                         [$tenantId, $peerTenantId, $peerTenantId, $tenantId]);
    if (!$allow || !$allow->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'peer_not_allowed']);
        exit;
    }
}

// Check for blocks
$blockedA = secure_query($pdo, 'SELECT 1 FROM user_blocks WHERE tenant_id = ? AND user_id = ? AND blocked_user_id = ? LIMIT 1', [$tenantId, $currentUserId, $toUserId]);
$blockedB = secure_query($pdo, 'SELECT 1 FROM user_blocks WHERE tenant_id = ? AND user_id = ? AND blocked_user_id = ? LIMIT 1', [$tenantId, $toUserId, $currentUserId]);
if (($blockedA && $blockedA->fetch()) || ($blockedB && $blockedB->fetch())) {
    http_response_code(403);
    echo json_encode(['error' => 'blocked']);
    exit;
}

// Store the file
$uploadDir = __DIR__ . '/../uploads/files/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$fileName = uniqid('file_') . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', $file['name']);
$filePath = $uploadDir . $fileName;
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    http_response_code(500);
    echo json_encode(['error' => 'file_upload_failed']);
    exit;
}

// Generate room_id
$ids = [$currentUserId, $toUserId];
sort($ids, SORT_NUMERIC);
$room = 'u-' . $ids[0] . '-' . $ids[1];

// Save file metadata to chat_messages
$content = json_encode([
    'type' => 'file',
    'name' => $file['name'],
    'size' => $file['size'],
    'mimeType' => $mimeType,
    'filePath' => $fileName
]);
$stmt = secure_query($pdo, 'INSERT INTO chat_messages (room_id, from_user_id, to_user_id, tenant_id_from, content) VALUES (?, ?, ?, ?, ?)', 
                    [$room, $currentUserId, $toUserId, $tenantId, $content]);
if (!$stmt) {
    unlink($filePath); // Clean up if database fails
    http_response_code(500);
    echo json_encode(['error' => 'save_failed']);
    exit;
}
$messageId = $pdo->lastInsertId();

echo json_encode([
    'ok' => true,
    'id' => (int)$messageId,
    'room_id' => $room,
    'file_name' => $file['name'],
    'file_path' => $fileName
]);
exit;
?>