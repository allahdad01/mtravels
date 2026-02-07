<?php
/**
 * Session Status Check API
 * Returns the current session status for automatic logout handling
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and session is valid
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    echo json_encode([
        'authenticated' => false,
        'message' => 'Session expired or not logged in'
    ]);
    exit;
}

// Check for session timeout (30 minutes = 1800 seconds)
$session_timeout = 1800;

if (isset($_SESSION["last_activity"])) {
    $timeSinceLastActivity = time() - $_SESSION["last_activity"];
    
    if ($timeSinceLastActivity > $session_timeout) {
        // Session expired
        session_unset();
        session_destroy();
        
        http_response_code(401);
        echo json_encode([
            'authenticated' => false,
            'message' => 'Session expired due to inactivity',
            'expired' => true
        ]);
        exit;
    }
    
    // Calculate remaining time
    $remainingTime = $session_timeout - $timeSinceLastActivity;
    
    echo json_encode([
        'authenticated' => true,
        'user_id' => $_SESSION['user_id'],
        'role' => $_SESSION['role'] ?? null,
        'remaining_time' => $remainingTime,
        'last_activity' => $_SESSION['last_activity']
    ]);
    exit;
} else {
    // No last_activity set - session invalid
    http_response_code(401);
    echo json_encode([
        'authenticated' => false,
        'message' => 'Invalid session state'
    ]);
    exit;
}
?>