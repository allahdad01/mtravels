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
    // Get tenant and branch info from session
    $tenantId = $_SESSION['tenant_id'] ?? null;
    $branchId = $_SESSION['branch_id'] ?? null;

    if (!$tenantId || !$branchId) {
        throw new Exception('Missing tenant or branch information');
    }

    // Create upload directory: uploads/tenant_id/branch_id/umrah/family_id/
    $uploadBase = __DIR__ . '/../../uploads';
    $uploadDir = $uploadBase . '/' . $tenantId . '/' . $branchId . '/umrah/';
    if ($familyId) {
        $uploadDir .= $familyId . '/';
    }

    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0755, true)) {
            throw new Exception('Could not create upload directory');
        }
    }

    // Clean up old passport document if re-uploading for the same family
    if ($familyId) {
        $sql = "SELECT passport_path FROM umrah_bookings WHERE family_id = ? AND tenant_id = ? AND branch_id = ? AND passport_path IS NOT NULL AND passport_path != '' LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$familyId, $tenantId, $branchId]);
        $old = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($old && $old['passport_path']) {
            $oldFile = $uploadBase . $old['passport_path'];
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
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
    $filepath = $uploadDir . $filename;
    $relativePath = '/uploads/' . $tenantId . '/' . $branchId . '/umrah/' . ($familyId ? $familyId . '/' : '') . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save file');
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Passport document saved successfully',
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
