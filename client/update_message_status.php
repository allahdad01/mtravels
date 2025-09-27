<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in with proper role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

// Check if required parameters are present
if (!isset($_POST['message_id']) || !isset($_POST['status'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit();
}

// Get parameters
$message_id = intval($_POST['message_id']);
$status = $_POST['status'];
$user_id = $_SESSION['user_id'];

// Validate status
if ($status !== 'read') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status'
    ]);
    exit();
}

// Database connection
require_once '../includes/db.php';

try {
    // Prepare and execute the update query
    $query = "UPDATE messages 
              SET status = ?, 
                  updated_at = NOW() 
              WHERE id = ? 
              AND (recipient_type = 'all'
              OR recipient_type = 'clients'
              OR (recipient_type = 'individual'
              AND recipient_id = ?
              AND recipient_table = 'clients'))
              AND status = 'unread'";
    
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([$status, $message_id, $user_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Message marked as read successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update message status or message already read'
        ]);
    }
} catch (PDOException $e) {
    error_log("Error updating message status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?> 