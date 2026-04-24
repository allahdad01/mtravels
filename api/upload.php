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
$sessionRole = $_SESSION['role'] ?? 'user';
$currentUserType = $sessionRole === 'client' ? 'client' : 'user';
$method = $_SERVER['REQUEST_METHOD'];

function room_from_users($a, $b, $typeA = 'user', $typeB = 'user') {
    $pairs = [
        ['id' => $a, 'type' => $typeA],
        ['id' => $b, 'type' => $typeB]
    ];

    usort($pairs, function($x, $y) {
        return $x['id'] - $y['id'];
    });

    $t1 = substr($pairs[0]['type'], 0, 1);
    $t2 = substr($pairs[1]['type'], 0, 1);

    return 'msg-' . $t1 . $pairs[0]['id'] . '-' . $t2 . $pairs[1]['id'];
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

// Validate CSRF token
$csrfToken = isset($_POST['csrf_token']) ? trim($_POST['csrf_token']) : '';
if (empty($csrfToken) || !hash_equals(($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'csrf_token_invalid']);
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
$toUserType = isset($_POST['to_user_type']) ? trim($_POST['to_user_type']) : 'user';
if ($toUserId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_peer']);
    exit;
}

if (!in_array($toUserType, ['user', 'client'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_peer_type']);
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

// Verify peer and tenant/branch peering
if ($toUserType === 'client') {
    $peerStmt = secure_query($pdo, 'SELECT tenant_id, branch_id, status as role FROM clients WHERE id = ?', [$toUserId]);
    $peer = $peerStmt ? $peerStmt->fetch(PDO::FETCH_ASSOC) : null;
} else {
    $peerStmt = secure_query($pdo, 'SELECT tenant_id, branch_id, role, deleted_at, fired FROM users WHERE id = ?', [$toUserId]);
    $peer = $peerStmt ? $peerStmt->fetch(PDO::FETCH_ASSOC) : null;
    if ($peer && ($peer['deleted_at'] !== null || $peer['fired'])) {
        $peer = null;
    }
}
if (!$peer) {
    http_response_code(404);
    echo json_encode(['error' => 'peer_not_found']);
    exit;
}

$peerTenantId = (int)$peer['tenant_id'];
$peerBranchId = isset($peer['branch_id']) ? (int)$peer['branch_id'] : 0;

// Get current user's branch
$myStmt = secure_query($pdo, 'SELECT branch_id FROM users WHERE id = ?', [$currentUserId]);
$myUser = $myStmt ? $myStmt->fetch(PDO::FETCH_ASSOC) : null;
$myBranch = isset($myUser['branch_id']) ? (int)$myUser['branch_id'] : 0;

if ($peerTenantId !== $tenantId) {
    // Cross-tenant: check both tenant and branch peering
    $tenantPeeringAllow = secure_query($pdo, 'SELECT 1 FROM tenant_peering WHERE status = "approved" AND ((tenant_id = ? AND peer_tenant_id = ?) OR (tenant_id = ? AND peer_tenant_id = ?)) LIMIT 1', [$tenantId, $peerTenantId, $peerTenantId, $tenantId]);
    $tenantPeeringExists = $tenantPeeringAllow && $tenantPeeringAllow->fetch();
    
    $branchPeeringAllow = secure_query($pdo, 
        'SELECT 1 FROM branch_peering WHERE status = "approved" AND (
            (tenant_id = ? AND branch_id = ? AND peer_tenant_id = ? AND peer_branch_id = ?)
            OR
            (tenant_id = ? AND branch_id = ? AND peer_tenant_id = ? AND peer_branch_id = ?)
        ) LIMIT 1', 
        [$tenantId, $myBranch, $peerTenantId, $peerBranchId, $peerTenantId, $peerBranchId, $tenantId, $myBranch]);
    $branchPeeringExists = $branchPeeringAllow && $branchPeeringAllow->fetch();
    
    if (!$tenantPeeringExists && !$branchPeeringExists) {
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

// Generate room_id using the same format as direct chat messages
$room = room_from_users($currentUserId, $toUserId, $currentUserType, $toUserType);

// Save file metadata to chat_messages
$content = json_encode([
    'type' => 'file',
    'filename' => $file['name'],
    'name' => $file['name'],
    'size' => $file['size'],
    'mimetype' => $mimeType,
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
