<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get POST data
$notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';

// Validate input
if ($notification_id <= 0 || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

// Database connection
require_once '../includes/conn.php';

// Validate status
$status = isset($_POST['status']) ? DbSecurity::validateInput($_POST['status'], 'string', ['maxlength' => 255]) : null;

// Validate notification_id
$notification_id = isset($_POST['notification_id']) ? DbSecurity::validateInput($_POST['notification_id'], 'int', ['min' => 0]) : null;

// Check connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Update notification status
$sql = "UPDATE notifications SET status = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $status, $notification_id);

if ($stmt->execute()) {
    // Add activity logging
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Get notification details
    $notification_query = "SELECT * FROM notifications WHERE id = ? AND tenant_id = ?";
    $notification_stmt = $conn->prepare($notification_query);
    $notification_stmt->bind_param("ii", $notification_id, $tenant_id);
    $notification_stmt->execute();
    $notification_result = $notification_stmt->get_result();
    $notification = $notification_result->fetch_assoc();
    
    // Prepare old/new values
    $old_values = [];
    if ($notification) {
        $old_values = [
            'notification_id' => $notification_id,
            'previous_status' => $notification['status']
        ];
    }
    
    $new_values = [
        'status' => $status
    ];
    $action = 'update';
    $table_name = 'notifications';
    $record_id = $notification_id;
    $old_values = json_encode($old_values);
    $new_values = json_encode($new_values);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    // Insert activity log
    $log_stmt = $conn->prepare("INSERT INTO activity_log 
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $log_stmt->bind_param("isissssss", 
        $user_id, 
        $action, 
        $table_name, 
        $record_id, 
        $old_values, 
        $new_values, 
        $ip_address, 
        $user_agent,
        $tenant_id
    );
    $log_stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
}

$stmt->close();
$conn->close();
?> 