<?php
// Include necessary files
require_once 'security.php';
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON content type
header('Content-Type: application/json');

// Check if it's an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}
$tenant_id = $_SESSION['tenant_id'];

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Verify CSRF token
if (!isset($data['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
    die(json_encode(['success' => false, 'message' => __('invalid_csrf_token')]));
}

// Check if ID is provided
if (!isset($data['id']) || empty($data['id'])) {
    die(json_encode(['success' => false, 'message' => __('user_id_required')]));
}

// Prevent deleting your own account
if ($data['id'] == $_SESSION['user_id']) {
    die(json_encode(['success' => false, 'message' => __('cannot_delete_your_own_account')]));
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get user's profile picture before deletion
    $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$data['id'], $tenant_id]);
    $profile_pic = $stmt->fetchColumn();

    if (!$profile_pic) {
        throw new Exception(__('user_not_found'));
    }

    // Delete related records first
    // 1. Delete login history
    $stmt = $pdo->prepare("DELETE FROM login_history WHERE user_id = ? AND tenant_id = ?");
    $stmt->execute([$data['id'], $tenant_id]);

    // 2. Delete any other related records (add more if needed)
    // Example:
    // $stmt = $pdo->prepare("DELETE FROM user_preferences WHERE user_id = ?");
    // $stmt->execute([$data['id']]);

    // Finally, delete the user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$data['id'], $tenant_id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception(__('user_not_found'));
    }

    // Delete profile picture if it exists and is not the default
    if ($profile_pic && $profile_pic !== 'default-avatar.jpg') {
        $pic_path = "../assets/images/user/" . $profile_pic;
        if (file_exists($pic_path)) {
            unlink($pic_path);
        }
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => __('user_deleted_successfully')
    ]);

} catch (Exception $e) {
    // Rollback transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error in delete_user.php: " . $e->getMessage());
    
    // Format the error message to be more user-friendly
    $errorMessage = $e->getMessage();
    if (strpos($errorMessage, 'foreign key constraint fails') !== false) {
        $errorMessage = __('cannot_delete_user_has_related_records');
    }
    
    echo json_encode([
        'success' => false,
        'message' => $errorMessage
    ]);
} 