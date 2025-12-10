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
$peerId = isset($_POST['peer_id']) ? (int)$_POST['peer_id'] : null;
$isTyping = isset($_POST['typing']) ? (int)$_POST['typing'] : 0;

if (!$peerId) {
    http_response_code(400);
    echo json_encode(['error' => 'peer_id required']);
    exit;
}

try {
    // Create table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_typing_status (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            peer_id INT NOT NULL,
            is_typing TINYINT DEFAULT 0,
            last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_typing (user_id, peer_id),
            INDEX idx_last_update (last_update)
        )
    ");

    if ($isTyping) {
        // User is typing
        $stmt = secure_query($pdo,
            'INSERT INTO user_typing_status (user_id, peer_id, is_typing, last_update)
             VALUES (?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE is_typing = 1, last_update = NOW()',
            [$currentUserId, $peerId]
        );
    } else {
        // User stopped typing
        $stmt = secure_query($pdo,
            'UPDATE user_typing_status SET is_typing = 0, last_update = NOW()
             WHERE user_id = ? AND peer_id = ?',
            [$currentUserId, $peerId]
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
