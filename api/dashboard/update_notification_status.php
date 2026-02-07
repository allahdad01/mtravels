<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once __DIR__ . '/../../admin/includes/db_security.php';

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

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
require_once '../../includes/db.php';

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

// Validate status
$status = isset($_POST['status']) ? DbSecurity::validateInput($_POST['status'], 'string', ['maxlength' => 255]) : null;

// Validate notification_id
$notification_id = isset($_POST['notification_id']) ? DbSecurity::validateInput($_POST['notification_id'], 'int', ['min' => 0]) : null;

// Update notification status
$sql = "UPDATE notifications SET status = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $status, PDO::PARAM_STR);
$stmt->bindParam(2, $notification_id, PDO::PARAM_INT);
$stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    // Add activity logging
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Get notification details
    $notification_query = "SELECT * FROM notifications WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $notification_stmt = $pdo->prepare($notification_query);
    $notification_stmt->bindParam(1, $notification_id, PDO::PARAM_INT);
    $notification_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $notification_stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $notification_stmt->execute();
    $notification = $notification_stmt->fetch(PDO::FETCH_ASSOC);

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
    $old_values_json = json_encode($old_values);
    $new_values_json = json_encode($new_values);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    // Insert activity log
    $log_stmt = $pdo->prepare("INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id, branch_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $log_stmt->bindParam(2, $action, PDO::PARAM_STR);
    $log_stmt->bindParam(3, $table_name, PDO::PARAM_STR);
    $log_stmt->bindParam(4, $record_id, PDO::PARAM_INT);
    $log_stmt->bindParam(5, $old_values_json, PDO::PARAM_STR);
    $log_stmt->bindParam(6, $new_values_json, PDO::PARAM_STR);
    $log_stmt->bindParam(7, $ip_address, PDO::PARAM_STR);
    $log_stmt->bindParam(8, $user_agent, PDO::PARAM_STR);
    $log_stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
    $log_stmt->bindParam(10, $branch_id, PDO::PARAM_INT);
    $log_stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
}
?>