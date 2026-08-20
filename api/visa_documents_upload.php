<?php
/**
 * Visa Documents Upload Handler
 * Handles document uploads for visa applications
 */

header('Content-Type: application/json');

require_once '../includes/db.php';
require_once '../admin/security.php';

enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'];

require_permission('visa.edit');

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// File upload directory
$uploadDir = realpath('../uploads/visas');
if (!$uploadDir || !is_dir($uploadDir)) {
    mkdir('../uploads/visas', 0755, true);
    $uploadDir = realpath('../uploads/visas');
}

// Allowed MIME types
$allowedMimes = [
    'application/pdf' => '.pdf',
    'image/jpeg' => '.jpg',
    'image/png' => '.png',
    'application/msword' => '.doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
    'application/vnd.ms-excel' => '.xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '.xlsx',
    'image/jpg' => '.jpg'
];

$maxFileSize = 10 * 1024 * 1024; // 10MB

if ($method === 'POST' && isset($_FILES['files'])) {
    $visaId = intval($_POST['visa_id'] ?? 0);
    $docType = trim($_POST['doc_type'] ?? '');
    $customDocType = trim($_POST['custom_doc_type'] ?? '');
    $isRequired = isset($_POST['is_required']) ? 1 : 0;
    $remarks = trim($_POST['remarks'] ?? '');

    // Validate visa ID
    if ($visaId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid visa ID']);
        exit();
    }

    // Check visa exists and belongs to tenant
    $query = "SELECT id FROM visa_applications WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $visaId, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Visa not found']);
        exit();
    }

    // Determine final document type
    $finalDocType = !empty($customDocType) ? $customDocType : $docType;
    
    if (empty($finalDocType)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Document type is required']);
        exit();
    }

    // Validate files
    if (!isset($_FILES['files']) || count($_FILES['files']['name']) === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No files selected']);
        exit();
    }

    $uploadedFiles = [];
    $errors = [];

    // Create visa-specific directory
    $visaUploadDir = $uploadDir . DIRECTORY_SEPARATOR . $visaId;
    if (!is_dir($visaUploadDir)) {
        mkdir($visaUploadDir, 0755, true);
    }

    // Process each file
    for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
        $fileName = $_FILES['files']['name'][$i];
        $fileTmp = $_FILES['files']['tmp_name'][$i];
        $fileError = $_FILES['files']['error'][$i];
        $fileSize = $_FILES['files']['size'][$i];
        $fileType = mime_content_type($fileTmp);

        // Validate file
        $validation = validateFile($fileName, $fileSize, $fileType, $allowedMimes, $maxFileSize);
        
        if (!$validation['valid']) {
            $errors[] = [
                'file' => htmlspecialchars($fileName),
                'error' => $validation['message']
            ];
            continue;
        }

        // Generate unique filename
        $fileExt = $validation['extension'];
        $safeFileName = uniqid('doc_') . $fileExt;
        $filePath = $visaUploadDir . DIRECTORY_SEPARATOR . $safeFileName;
        $relativePath = 'uploads/visas/' . $visaId . '/' . $safeFileName;

        // Move uploaded file
        if (!move_uploaded_file($fileTmp, $filePath)) {
            $errors[] = [
                'file' => htmlspecialchars($fileName),
                'error' => 'Failed to move uploaded file'
            ];
            continue;
        }

        // Set file permissions
        chmod($filePath, 0644);

        // Insert into database
        $insertQuery = "INSERT INTO visa_documents 
                       (visa_id, doc_type, file_path, original_filename, file_size, 
                        mime_type, is_required, status, uploaded_by, tenant_id, branch_id) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)";
        
        $insertStmt = $pdo->prepare($insertQuery);
        try {
            $insertStmt->execute([
                $visaId,
                $finalDocType,
                $relativePath,
                $fileName,
                $fileSize,
                $fileType,
                $isRequired,
                $user_id,
                $tenant_id,
                $branch_id
            ]);

            $uploadedFiles[] = [
                'id' => $pdo->lastInsertId(),
                'name' => htmlspecialchars($fileName),
                'size' => formatFileSize($fileSize),
                'type' => $finalDocType
            ];
        } catch (Exception $e) {
            $errors[] = [
                'file' => htmlspecialchars($fileName),
                'error' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    // Return response
    $response = [
        'success' => count($errors) === 0,
        'message' => count($uploadedFiles) . ' file(s) uploaded successfully',
        'uploaded' => $uploadedFiles,
        'errors' => $errors
    ];

    echo json_encode($response);
    exit();
}

// GET: Fetch documents for visa
if ($method === 'GET') {
    $visaId = intval($_GET['visa_id'] ?? 0);

    if ($visaId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid visa ID']);
        exit();
    }

    // Check visa exists
    $query = "SELECT id FROM visa_applications WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $visaId, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Visa not found']);
        exit();
    }

    // Fetch documents
    $docsQuery = "SELECT vd.*, u.name as uploaded_by_name
                 FROM visa_documents vd
                 LEFT JOIN users u ON vd.uploaded_by = u.id
                 WHERE vd.visa_id = ? AND vd.tenant_id = ?
                 ORDER BY vd.uploaded_at DESC";
    
    $docsStmt = $pdo->prepare($docsQuery);
    $docsStmt->bindParam(1, $visaId, PDO::PARAM_INT);
    $docsStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $docsStmt->execute();

    $documents = $docsStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'documents' => $documents
    ]);
    exit();
}

// DELETE: Remove document
if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $documentId = intval($data['id'] ?? 0);

    if ($documentId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid document ID']);
        exit();
    }

    // Verify document ownership
    $query = "SELECT vd.* FROM visa_documents vd
             JOIN visa_applications va ON vd.visa_id = va.id
             WHERE vd.id = ? AND vd.tenant_id = ? AND vd.branch_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $documentId, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit();
    }

    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    // Delete file
    $filePath = realpath('../' . $doc['file_path']);
    if ($filePath && file_exists($filePath)) {
        unlink($filePath);
    }

    // Delete from database
    $deleteQuery = "DELETE FROM visa_documents WHERE id = ?";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->bindParam(1, $documentId, PDO::PARAM_INT);
    $deleteStmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Document deleted successfully'
    ]);
    exit();
}

// Helper functions
function validateFile($fileName, $fileSize, $fileType, $allowedMimes, $maxSize) {
    // Check file size
    if ($fileSize > $maxSize) {
        return [
            'valid' => false,
            'message' => 'File size exceeds 10MB limit'
        ];
    }

    // Check MIME type
    if (!isset($allowedMimes[$fileType])) {
        return [
            'valid' => false,
            'message' => 'File type not allowed'
        ];
    }

    $extension = $allowedMimes[$fileType];

    return [
        'valid' => true,
        'extension' => $extension
    ];
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
