<?php
/**
 * Save Passport Document Upload
 * Handles saving the passport document file to disk
 * Stores path for later association with member booking
 */

require_once '../../includes/db.php';
require_once '../../admin/security.php';

enforce_auth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['passport_file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['passport_file'];
$familyId = isset($_POST['family_id']) ? intval($_POST['family_id']) : null;

// Validate file
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File upload error']);
    exit;
}

// Validate file type
$allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
if (!in_array($file['type'], $allowedMimes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF and images allowed.']);
    exit;
}

// Validate file size (10MB max)
if ($file['size'] > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum 10MB allowed.']);
    exit;
}

try {
    $uploadBase = __DIR__ . '/../../uploads';

    // Save to temp directory — moved to final location only when member is saved
    $tempDir = $uploadBase . '/temp';
    if (!is_dir($tempDir)) {
        if (!@mkdir($tempDir, 0755, true)) {
            throw new Exception('Could not create temp upload directory');
        }
    }

    // Generate secure filename
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    
    // Determine file extension
    $ext = 'pdf';
    if (strpos($file['type'], 'image') !== false) {
        $ext = 'jpg';
    }
    
    $filename = "passport_document_{$timestamp}_{$random}.{$ext}";
    $filepath = $tempDir . '/' . $filename;
    $relativePath = '/uploads/temp/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save file');
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Passport document saved temporarily',
        'document_path' => $relativePath,
        'filename' => $filename
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
