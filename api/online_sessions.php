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
$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    // First, ensure the table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_online_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(255),
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_session (user_id, session_id),
            INDEX idx_last_activity (last_activity)
        )
    ");
    
    // Clean up stale sessions (older than 5 minutes)
    $pdo->exec("DELETE FROM user_online_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");

    if ($action === 'ping') {
        // Update current user's session as active
        $sessionId = session_id();
        
        $stmt = secure_query($pdo,
            'INSERT INTO user_online_sessions (user_id, session_id, last_activity, created_at)
             VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE last_activity = NOW()',
            [$currentUserId, $sessionId]
        );
        
        // Get online users (last activity within 5 minutes)
        $fiveMinutesAgo = date('Y-m-d H:i:s', time() - 300);
        $onlineStmt = secure_query($pdo,
            'SELECT DISTINCT user_id FROM user_online_sessions WHERE last_activity > ?',
            [$fiveMinutesAgo]
        );
        
        $onlineUsers = [];
        if ($onlineStmt) {
            while ($row = $onlineStmt->fetch(PDO::FETCH_ASSOC)) {
                $onlineUsers[] = (int)$row['user_id'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'online' => $onlineUsers,
            'timestamp' => date('c')
        ]);
        
    } elseif ($action === 'logout') {
        // Remove session when user logs out
        $sessionId = session_id();
        secure_query($pdo,
            'DELETE FROM user_online_sessions WHERE user_id = ? AND session_id = ?',
            [$currentUserId, $sessionId]
        );
        
        echo json_encode(['success' => true]);
        
    } else {
        // Just return current online users
        $fiveMinutesAgo = date('Y-m-d H:i:s', time() - 300);
        $onlineStmt = secure_query($pdo,
            'SELECT DISTINCT user_id FROM user_online_sessions WHERE last_activity > ?',
            [$fiveMinutesAgo]
        );
        
        $onlineUsers = [];
        if ($onlineStmt) {
            while ($row = $onlineStmt->fetch(PDO::FETCH_ASSOC)) {
                $onlineUsers[] = (int)$row['user_id'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'online' => $onlineUsers,
            'timestamp' => date('c')
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to update online status',
        'message' => $e->getMessage()
    ]);
}
exit;
?>
