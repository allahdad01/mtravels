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

// Include database connection
include '../includes/db.php';
include '../includes/conn.php';

// Verify that the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method";
    header('Location: send_messages.php');
    exit();
}

// Get form data
$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$subject = isset($_POST['subject']) ? mysqli_real_escape_string($conn, $_POST['subject']) : '';
$message = isset($_POST['message']) ? mysqli_real_escape_string($conn, $_POST['message']) : '';
$recipient_type = isset($_POST['recipient_type']) ? mysqli_real_escape_string($conn, $_POST['recipient_type']) : '';
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
    $user_check = mysqli_query($conn, "SELECT 1 FROM users WHERE id = $recipient_id AND tenant_id = $tenant_id");
    $client_check = mysqli_query($conn, "SELECT 1 FROM clients WHERE id = $recipient_id AND tenant_id = $tenant_id");
    
    if (mysqli_num_rows($user_check) > 0) {
        $recipient_table = 'users';
        $valid_recipient = true;
    } else if (mysqli_num_rows($client_check) > 0) {
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
    $query = "UPDATE messages SET 
              subject = '$subject', 
              message = '$message', 
              recipient_type = '$recipient_type', 
              recipient_id = $recipient_id, 
              recipient_table = '$recipient_table' 
              WHERE id = $message_id AND tenant_id = $tenant_id";
} else {
    // Update the message with non-individual recipient
    $query = "UPDATE messages SET 
              subject = '$subject', 
              message = '$message', 
              recipient_type = '$recipient_type', 
              recipient_id = NULL, 
              recipient_table = NULL 
              WHERE id = $message_id AND tenant_id = $tenant_id";
}

// Execute query
if (mysqli_query($conn, $query)) {
    // Add activity logging
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Get original message data if possible
    $old_values = [];
    $get_original = "SELECT * FROM messages WHERE id = $message_id AND tenant_id = $tenant_id";
    $original_result = mysqli_query($conn, $get_original);
    
    if ($original_result && mysqli_num_rows($original_result) > 0) {
        $original_data = mysqli_fetch_assoc($original_result);
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
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $activity_log_stmt->execute([
        $user_id,
        'update',
        'messages',
        $message_id,
        json_encode($old_values),
        json_encode($new_values),
        $ip_address,
        $user_agent,
        $tenant_id
    ]);
    
    $_SESSION['success_message'] = "Message updated successfully!";
} else {
    $_SESSION['error_message'] = "Error updating message: " . mysqli_error($conn);
}

// Redirect back to the messages page
header('Location: send_messages.php');
exit();
?> 