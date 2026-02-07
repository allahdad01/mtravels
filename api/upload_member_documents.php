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

// Database connection
require_once '../includes/db.php';

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'];
$booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
$document_type = isset($_POST['document_type']) ? trim($_POST['document_type']) : ''; // 'photo' or 'passport'

// Validate input
if (!$booking_id || !in_array($document_type, ['photo', 'passport'])) {
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

// File validation
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
$max_file_size = 5 * 1024 * 1024; // 5MB

if (!in_array($file['type'], $allowed_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, PDF']);
    exit;
}

if ($file['size'] > $max_file_size) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
    exit;
}

// Create folder structure: uploads/tenant_id/branch_id/umrah/family_id/
$base_upload_dir = __DIR__ . '/../uploads';
$tenant_dir = $base_upload_dir . '/' . $tenant_id;
$branch_dir = $tenant_dir . '/' . $branch_id;
$umrah_dir = $branch_dir . '/umrah';
$family_dir = $umrah_dir . '/' . $family_id;

// Create directories if they don't exist
if (!is_dir($base_upload_dir)) mkdir($base_upload_dir, 0755, true);
if (!is_dir($tenant_dir)) mkdir($tenant_dir, 0755, true);
if (!is_dir($branch_dir)) mkdir($branch_dir, 0755, true);
if (!is_dir($umrah_dir)) mkdir($umrah_dir, 0755, true);
if (!is_dir($family_dir)) mkdir($family_dir, 0755, true);

// Generate unique filename
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$timestamp = time();
$random_string = substr(md5(mt_rand()), 0, 8);
$new_filename = $document_type . '_' . $booking_id . '_' . $timestamp . '_' . $random_string . '.' . $file_extension;

// Full file path
$file_path = $family_dir . '/' . $new_filename;

// Determine the correct relative path based on current request
// Get the base path from the request
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path = str_replace('/api/upload_member_documents.php', '', $request_uri);

// Build relative path accessible from browser
$relative_path = $base_path . '/uploads/' . $tenant_id . '/' . $branch_id . '/umrah/' . $family_id . '/' . $new_filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit;
}

// Delete old file if exists
$column_name = $document_type . '_path';
$stmt = $pdo->prepare("SELECT $column_name FROM umrah_bookings WHERE booking_id = ?");
$stmt->execute([$booking_id]);
$old_file_info = $stmt->fetch(PDO::FETCH_ASSOC);

if ($old_file_info && $old_file_info[$column_name]) {
    $old_file = __DIR__ . '/..' . $old_file_info[$column_name];
    if (file_exists($old_file)) {
        unlink($old_file);
    }
}

// Update database
$uploaded_at_column = $document_type . '_uploaded_at';
$stmt = $pdo->prepare("UPDATE umrah_bookings 
                      SET $column_name = ?, $uploaded_at_column = NOW() 
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
