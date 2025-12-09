<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Include database connection
include '../includes/db.php';

// Verify that the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method";
    header('Location: send_messages.php');
    exit();
}

// Get form data
$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$recipient_type = isset($_POST['recipient_type']) ? trim($_POST['recipient_type']) : '';
$recipient_id = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : null;

// Validate message_id
if ($message_id <= 0) {
    $_SESSION['error_message'] = "Invalid message ID";
    header('Location: send_messages.php');
    exit();
}

// Validate required fields
if (empty($subject) || empty($message) || empty($recipient_type)) {
    $_SESSION['error_message'] = "All required fields must be filled out";
    header('Location: send_messages.php');
    exit();
}

// Prepare update data
if ($recipient_type === 'individual' && $recipient_id) {
    // Check if recipient exists in either users or clients table
    $user_check_stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $user_check_stmt->bindParam(1, $recipient_id, PDO::PARAM_INT);
    $user_check_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $user_check_stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $user_check_stmt->execute();

    $client_check_stmt = $pdo->prepare("SELECT 1 FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $client_check_stmt->bindParam(1, $recipient_id, PDO::PARAM_INT);
    $client_check_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $client_check_stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $client_check_stmt->execute();

    if ($user_check_stmt->rowCount() > 0) {
        $recipient_table = 'users';
        $valid_recipient = true;
    } else if ($client_check_stmt->rowCount() > 0) {
        $recipient_table = 'clients';
        $valid_recipient = true;
    } else {
        $valid_recipient = false;
    }

    if (!$valid_recipient) {
        $_SESSION['error_message'] = "Invalid recipient selected";
        header('Location: send_messages.php');
        exit();
    }

    // Update the message with individual recipient
    $update_stmt = $pdo->prepare("UPDATE messages SET
              subject = ?,
              message = ?,
              recipient_type = ?,
              recipient_id = ?,
              recipient_table = ?
              WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $update_stmt->bindParam(1, $subject, PDO::PARAM_STR);
    $update_stmt->bindParam(2, $message, PDO::PARAM_STR);
    $update_stmt->bindParam(3, $recipient_type, PDO::PARAM_STR);
    $update_stmt->bindParam(4, $recipient_id, PDO::PARAM_INT);
    $update_stmt->bindParam(5, $recipient_table, PDO::PARAM_STR);
    $update_stmt->bindParam(6, $message_id, PDO::PARAM_INT);
    $update_stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $update_stmt->bindParam(8, $branch_id, PDO::PARAM_INT);
} else {
    // Update the message with non-individual recipient
    $update_stmt = $pdo->prepare("UPDATE messages SET
              subject = ?,
              message = ?,
              recipient_type = ?,
              recipient_id = NULL,
              recipient_table = NULL
              WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $update_stmt->bindParam(1, $subject, PDO::PARAM_STR);
    $update_stmt->bindParam(2, $message, PDO::PARAM_STR);
    $update_stmt->bindParam(3, $recipient_type, PDO::PARAM_STR);
    $update_stmt->bindParam(4, $message_id, PDO::PARAM_INT);
    $update_stmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
    $update_stmt->bindParam(6, $branch_id, PDO::PARAM_INT);
}

// Execute query
if ($update_stmt->execute()) {
    // Add activity logging
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Get original message data if possible
    $old_values = [];
    $get_original_stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $get_original_stmt->bindParam(1, $message_id, PDO::PARAM_INT);
    $get_original_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $get_original_stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $get_original_stmt->execute();

    if ($get_original_stmt->rowCount() > 0) {
        $original_data = $get_original_stmt->fetch(PDO::FETCH_ASSOC);
        $old_values = [
            'subject' => $original_data['subject'],
            'message' => $original_data['message'],
            'recipient_type' => $original_data['recipient_type'],
            'recipient_id' => $original_data['recipient_id'],
            'recipient_table' => $original_data['recipient_table']
        ];
    }
    
    // Prepare new values
    $new_values = [
        'subject' => $subject,
        'message' => $message,
        'recipient_type' => $recipient_type,
        'recipient_id' => $recipient_id,
        'recipient_table' => $recipient_table ?? null
    ];
    
    // Insert activity log using PDO connection
    $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id, branch_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $activity_log_stmt->execute([
        $user_id,
        'update',
        'messages',
        $message_id,
        json_encode($old_values),
        json_encode($new_values),
        $ip_address,
        $user_agent,
        $tenant_id,
        $branch_id
    ]);
    
    $_SESSION['success_message'] = "Message updated successfully!";
} else {
    $_SESSION['error_message'] = "Error updating message: " . $update_stmt->errorInfo()[2];
}

// Redirect back to the messages page
header('Location: send_messages.php');
exit();
?> 