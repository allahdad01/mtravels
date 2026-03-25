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
$currentUserType = $_SESSION['user_type'] ?? 'user'; // 'user' or 'client'
$peerId = isset($_POST['peer_id']) ? (int)$_POST['peer_id'] : null;
$peerType = isset($_POST['peer_type']) ? $_POST['peer_type'] : 'user'; // 'user' or 'client'
$isTyping = isset($_POST['typing']) ? (int)$_POST['typing'] : 0;

if (!$peerId) {
    http_response_code(400);
    echo json_encode(['error' => 'peer_id required']);
    exit;
}

// Validate peer_type
if (!in_array($peerType, ['user', 'client'])) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid peer_type']);
    exit;
}

try {
    // Create table if not exists with support for user_type
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_typing_status (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            user_type VARCHAR(10) NOT NULL DEFAULT 'user',
            peer_id INT NOT NULL,
            peer_type VARCHAR(10) NOT NULL DEFAULT 'user',
            is_typing TINYINT DEFAULT 0,
            last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_typing (user_id, user_type, peer_id, peer_type),
            INDEX idx_last_update (last_update)
        )
    ");

    if ($isTyping) {
        // User is typing
        $stmt = secure_query($pdo,
            'INSERT INTO user_typing_status (user_id, user_type, peer_id, peer_type, is_typing, last_update)
             VALUES (?, ?, ?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE is_typing = 1, last_update = NOW()',
            [$currentUserId, $currentUserType, $peerId, $peerType]
        );
    } else {
        // User stopped typing
        $stmt = secure_query($pdo,
            'UPDATE user_typing_status SET is_typing = 0, last_update = NOW()
             WHERE user_id = ? AND user_type = ? AND peer_id = ? AND peer_type = ?',
            [$currentUserId, $currentUserType, $peerId, $peerType]
        );
    }

    // Clean up stale typing indicators (older than 5 seconds)
    $pdo->exec("DELETE FROM user_typing_status WHERE last_update < DATE_SUB(NOW(), INTERVAL 5 SECOND)");

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
?>
