<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();


// Include database connection
include '../includes/db.php';

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
try {
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $message_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        // Log the activity
        $old_values = json_encode([
            'message_id' => $message_id
        ]);
        $new_values = json_encode([]);

        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $log_query = "INSERT INTO activity_log
                      (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
                      VALUES (?, 'delete', 'messages', ?, ?, ?, ?, ?, NOW(), ?, ?)";

        $stmt_log = $pdo->prepare($log_query);
        $stmt_log->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt_log->bindParam(2, $message_id, PDO::PARAM_INT);
        $stmt_log->bindParam(3, $old_values, PDO::PARAM_STR);
        $stmt_log->bindParam(4, $new_values, PDO::PARAM_STR);
        $stmt_log->bindParam(5, $ip_address, PDO::PARAM_STR);
        $stmt_log->bindParam(6, $user_agent, PDO::PARAM_STR);
        $stmt_log->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $stmt_log->bindParam(8, $branch_id, PDO::PARAM_INT);
        $stmt_log->execute();

        $_SESSION['success_message'] = "Message deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting message";
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error deleting message: " . $e->getMessage();
    error_log("Delete message error: " . $e->getMessage());
}

// Redirect back to the messages page
header('Location: send_messages.php');
exit();
?> 