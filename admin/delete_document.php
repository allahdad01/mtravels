<?php
// Include necessary files
require_once 'security.php';
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['success' => false, 'message' => __('unauthorized_access')]));
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Verify CSRF token
if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
    die(json_encode(['success' => false, 'message' => __('invalid_csrf_token')]));
}

// Check if document ID is provided
if (!isset($input['id']) || empty($input['id'])) {
    die(json_encode(['success' => false, 'message' => __('document_id_required')]));
}

try {
    // Get document info
    $stmt = $pdo->prepare("SELECT * FROM user_documents WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$input['id'], $tenant_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        throw new Exception(__('document_not_found'));
    }
    
    // Delete the file
    $filePath = "../uploads/user_documents/{$document['user_id']}/{$document['filename']}";
    if (file_exists($filePath)) {
        if (!unlink($filePath)) {
            throw new Exception(__('error_deleting_document_file'));
        }
    }
    
    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM user_documents WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$input['id'], $tenant_id]);
    
    echo json_encode([
        'success' => true,
        'message' => __('document_deleted_successfully')
    ]);
    
} catch (Exception $e) {
    error_log("Error in delete_document.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 