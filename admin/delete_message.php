<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();


// Include database connection
include '../includes/db.php';
include '../includes/conn.php';

// Verify that the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method";
    header('Location: send_messages.php');
    exit();
}

// Get message ID
$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;

// Validate message_id
if ($message_id <= 0) {
    $_SESSION['error_message'] = "Invalid message ID";
    header('Location: send_messages.php');
    exit();
}

// Delete the message
$query = "DELETE FROM messages WHERE id = $message_id AND tenant_id = $tenant_id";

// Execute query
if (mysqli_query($conn, $query)) {
    // Log the activity
    $old_values = json_encode([
        'message_id' => $message_id
    ]);
    $new_values = json_encode([]);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $log_query = "INSERT INTO activity_log 
                  (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
                  VALUES (?, 'delete', 'messages', ?, ?, ?, ?, ?, NOW(), ?)";
    
    $stmt_log = $conn->prepare($log_query);
    $stmt_log->bind_param("iissss", $user_id, $message_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
    $stmt_log->execute();
    $stmt_log->close();
    
    $_SESSION['success_message'] = "Message deleted successfully!";
} else {
    $_SESSION['error_message'] = "Error deleting message: " . mysqli_error($conn);
}

// Redirect back to the messages page
header('Location: send_messages.php');
exit();
?> 