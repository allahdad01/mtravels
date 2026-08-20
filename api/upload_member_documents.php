<?php
/**
 * Upload handler for member photo and passport documents
 * Creates organized folder structure: uploads/tenant_id/branch_id/umrah/family_id/
 */

header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'includes/permissions.php';
require_permission('umrah.member_edit');

// Database connection
require_once '../includes/db.php';

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'];
$booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
$document_type = isset($_POST['document_type']) ? trim($_POST['document_type']) : ''; // 'photo' or 'passport'

// Validate input
if (!$booking_id || !in_array($document_type, ['photo', 'passport', 'visa'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Check if file is uploaded
if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
$file_error = $file['error'];

// Check for upload errors
if ($file_error !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds max upload size',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form max size',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error_messages[$file_error] ?? 'Unknown upload error']);
    exit;
}

// Get booking details
$stmt = $pdo->prepare("SELECT f.family_id FROM umrah_bookings ub 
                      LEFT JOIN families f ON ub.family_id = f.family_id 
                      WHERE ub.booking_id = ? AND ub.tenant_id = ? AND ub.branch_id = ?");
$stmt->execute([$booking_id, $tenant_id, $branch_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit;
}

$family_id = $booking['family_id'];

// Create upload directory structure manually to bypass SecureFileUpload's path sanitization
try {
    $upload_base = __DIR__ . '/../uploads';
    $upload_dir = $upload_base . '/' . $tenant_id . '/' . $branch_id . '/umrah/' . $family_id;
    
    // Create directories if they don't exist
    if (!file_exists($upload_dir)) {
        if (!@mkdir($upload_dir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not create upload directory']);
            exit;
        }
    }
    
    $file = $_FILES['file'];
    
    // Validate file
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, $allowed_mimes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit;
    }
    
    // Check extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_extensions)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid file extension']);
        exit;
    }
    
    // Generate secure filename
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    $new_filename = "file_{$timestamp}_{$random}." . $extension;
    $target_path = $upload_dir . '/' . $new_filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
        exit;
    }
    
    // Set proper permissions
    chmod($target_path, 0644);
    
    $relative_path = '/uploads/' . $tenant_id . '/' . $branch_id . '/umrah/' . $family_id . '/' . $new_filename;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $e->getMessage()]);
    exit;
}

// Delete old file if exists
$column_name = $document_type . '_path';
$stmt = $pdo->prepare("SELECT `$column_name` FROM umrah_bookings WHERE booking_id = ?");
$stmt->execute([$booking_id]);
$old_file_info = $stmt->fetch(PDO::FETCH_ASSOC);

if ($old_file_info && !empty($old_file_info[$column_name])) {
    $old_file = __DIR__ . '/..' . $old_file_info[$column_name];
    if (file_exists($old_file)) {
        unlink($old_file);
    }
}

// Update database using prepared statement with backticks for column names
$uploaded_at_column = $document_type . '_uploaded_at';
$stmt = $pdo->prepare("UPDATE umrah_bookings 
                      SET `$column_name` = ?, `$uploaded_at_column` = NOW() 
                      WHERE booking_id = ?");
$result = $stmt->execute([$relative_path, $booking_id]);

if ($result) {
    echo json_encode([
        'success' => true,
        'message' => ucfirst($document_type) . ' uploaded successfully',
        'file_path' => $relative_path,
        'file_name' => $new_filename
    ]);
} else {
    // Delete uploaded file if database update fails
    unlink($file_path);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save file information']);
}
?>
