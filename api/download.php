<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session_check.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Location: /login.php');
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$fileName = $_GET['file'] ?? '';
$inline = isset($_GET['inline']) && $_GET['inline'] === '1';

if (!$fileName || !preg_match('/^file_[a-zA-Z0-9_-]+\.[a-zA-Z0-9]+$/', $fileName)) {
    http_response_code(400);
    echo 'Invalid file name';
    exit;
}

$filePath = __DIR__ . '/../uploads/files/' . $fileName;
if (!file_exists($filePath)) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

// Verify access: file must be in a message the user can access
$stmt = secure_query($pdo, 'SELECT room_id FROM chat_messages WHERE content LIKE ? AND (from_user_id = ? OR to_user_id = ?)', 
                    ['%"filePath":"' . $fileName . '"%', $currentUserId, $currentUserId]);
$access = $stmt ? $stmt->fetch() : null;
if (!$access) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

// Serve the file (inline for previews or attachment for downloads)
$mime = mime_content_type($filePath) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('Content-Transfer-Encoding: binary');
header(($inline ? 'Content-Disposition: inline; filename="' : 'Content-Disposition: attachment; filename="') . $fileName . '"');

// Clear output buffer to avoid corrupting binary content
if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) { ob_end_clean(); }
}

readfile($filePath);
exit;
?>