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
$tenant_id = $_SESSION['tenant_id'];

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die(json_encode(['success' => false, 'message' => __('id_required')]));
}

try {
    // Get user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$_GET['id'], $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception(__('user_not_found'));
    }
    
    // Get user documents
    $docStmt = $pdo->prepare("SELECT * FROM user_documents WHERE user_id = ? AND tenant_id = ? ORDER BY uploaded_at DESC");
    $docStmt->execute([$_GET['id'], $tenant_id]);
    $documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add documents to user data
    $user['documents'] = $documents;
    
    echo json_encode([
        'success' => true,
        'data' => $user
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_user.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 