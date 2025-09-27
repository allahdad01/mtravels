<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module and database connection
require_once '../admin/security.php';
require_once '../includes/db.php';

// Enforce authentication
enforce_auth();

// Check if user is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Set JSON header
header('Content-Type: application/json');

// Check if file path is provided
if (!isset($_POST['file_path'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No file path provided'
    ]);
    exit();
}

// Sanitize and validate file path
$filePath = $_POST['file_path'];

// Ensure the file is within the invoices directory
$invoicesDir = realpath('../uploads/invoices/');
$fullPath = realpath('../' . $filePath);

// Security check: Ensure the file is within the invoices directory
if (!$fullPath || strpos($fullPath, $invoicesDir) !== 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid file path'
    ]);
    exit();
}

// Attempt to delete the file
try {
    if (file_exists($fullPath)) {
        if (unlink($fullPath)) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Invoice deleted successfully'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to delete invoice'
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invoice file not found'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error deleting invoice: ' . $e->getMessage()
    ]);
}
exit();
?>