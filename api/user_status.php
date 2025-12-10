<?php
// DEPRECATED: This file has been replaced by api/online_sessions.php
// The online_sessions.php provides real-time online status using session tracking
// instead of relying on activity logs.

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized', 'message' => 'Use api/online_sessions.php instead']);
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];

// Get online status for all users (users who have been active in the last 5 minutes)
// and currently typing users
try {
    // Get recently active users (last activity in last 5 minutes)
    $fiveMinutesAgo = date('Y-m-d H:i:s', time() - 300); // 5 minutes
    
    // Check activity_log for recent activity
    $stmt = secure_query($pdo, 
        'SELECT DISTINCT user_id, MAX(created_at) as last_activity
         FROM activity_log
         WHERE created_at > ?
         GROUP BY user_id',
        [$fiveMinutesAgo]
    );
    
    $onlineUsers = [];
    if ($stmt) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $onlineUsers[(int)$row['user_id']] = true;
        }
    }
    
    // Get users with recent messages (also considered online)
    $messageStmt = secure_query($pdo,
        'SELECT DISTINCT from_user_id as user_id, MAX(created_at) as last_message
         FROM chat_messages
         WHERE created_at > ?
         GROUP BY from_user_id',
        [$fiveMinutesAgo]
    );
    
    if ($messageStmt) {
        while ($row = $messageStmt->fetch(PDO::FETCH_ASSOC)) {
            $onlineUsers[(int)$row['user_id']] = true;
        }
    }
    
    // For now, typing status is empty (would need WebSocket/real-time system)
    // But we can simulate it based on recent message activity within last 10 seconds
    $tenSecondsAgo = date('Y-m-d H:i:s', time() - 10);
    $typingStmt = secure_query($pdo,
        'SELECT DISTINCT from_user_id as user_id
         FROM chat_messages
         WHERE created_at > ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 SECOND)',
        [$tenSecondsAgo]
    );
    
    $typingUsers = [];
    if ($typingStmt) {
        while ($row = $typingStmt->fetch(PDO::FETCH_ASSOC)) {
            $typingUsers[(int)$row['user_id']] = true;
        }
    }
    
    echo json_encode([
        'success' => true,
        'online' => array_keys($onlineUsers),
        'typing' => array_keys($typingUsers),
        'timestamp' => date('c')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to get user status',
        'message' => $e->getMessage()
    ]);
}
exit;
?>
