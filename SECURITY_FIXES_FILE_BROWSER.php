<?php
/**
 * Security Fix for file_browser.php
 * 
 * Apply these fixes to prevent:
 * 1. Path traversal attacks
 * 2. CSRF vulnerabilities in delete operations
 * 3. Filename directory traversal
 */

// ===== FIX 1: Path Traversal Validation =====
// Add this after setting $uploadsDir (around line 50-60)

function validateUploadPath($uploadsDir, $requestedPath) {
    // Get real paths
    $realUploadsDir = realpath($uploadsDir);
    if ($realUploadsDir === false) {
        return false;
    }
    
    // If no path requested, return uploads dir
    if (empty($requestedPath)) {
        return $realUploadsDir;
    }
    
    // Resolve requested path
    $fullPath = realpath($uploadsDir . '/' . $requestedPath);
    
    // Path must exist and be within uploads directory
    if ($fullPath === false) {
        return false;
    }
    
    // Critical: Ensure the resolved path is within uploads directory
    if (strpos($fullPath, $realUploadsDir) !== 0) {
        return false;
    }
    
    return $fullPath;
}

// ===== FIX 2: Safe Filename Generation =====
// Replace filename handling in upload handler (around line 152-169)

function sanitizeUploadFilename($originalName) {
    // Remove any path components
    $fileName = basename($originalName);
    
    // Get extension
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Whitelist allowed extensions
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'];
    if (!in_array($ext, $allowedExts)) {
        throw new Exception("File type not allowed: " . htmlspecialchars($ext));
    }
    
    // Verify MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    // Note: $tmpFile is the uploaded tmp file
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'text/plain' => 'txt',
        'text/csv' => 'csv'
    ];
    finfo_close($finfo);
    
    // Generate cryptographically secure filename
    $hash = bin2hex(random_bytes(16));
    $safeFileName = 'upload_' . $hash . '.' . $ext;
    
    return $safeFileName;
}

// ===== IMPLEMENTATION IN file_browser.php =====

// Replace the existing path handling section with:
if (isset($_GET['dir'])) {
    $requestedDir = $_GET['dir'];
    
    // Validate the path
    $validatedPath = validateUploadPath($uploadsDir, $requestedDir);
    
    if ($validatedPath === false) {
        http_response_code(403);
        die(json_encode(['error' => 'Access denied']));
    }
    
    $currentDir = $validatedPath;
} else {
    $currentDir = realpath($uploadsDir);
}

// For POST operations (delete, create folder, upload):
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Always validate CSRF token first
    if (!isset($_POST['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
        exit();
    }
    
    // Validate current directory
    $validatedCurrentDir = validateUploadPath($uploadsDir, $_POST['dir'] ?? '');
    if ($validatedCurrentDir === false) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid directory']);
        exit();
    }
    
    $currentDir = $validatedCurrentDir;
    
    // Handle delete action
    if ($_POST['action'] === 'delete') {
        if (isset($_POST['path'])) {
            // Validate the file path
            $filePath = $currentDir . '/' . basename($_POST['path']);
            $realFilePath = realpath($filePath);
            
            // Ensure path is within uploads and file exists
            if ($realFilePath === false || strpos($realFilePath, realpath($uploadsDir)) !== 0) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'File access denied']);
                exit();
            }
            
            if (is_dir($realFilePath)) {
                deleteDirectory($realFilePath);
            } else {
                unlink($realFilePath);
            }
            
            echo json_encode(['success' => true, 'message' => 'Deleted successfully']);
            exit();
        }
    }
    
    // Handle file upload
    if ($_POST['action'] === 'upload_file') {
        if (!empty($_FILES['files'])) {
            $uploadedFiles = $_FILES['files'];
            $successCount = 0;
            $errors = [];
            
            for ($i = 0; $i < count($uploadedFiles['name']); $i++) {
                try {
                    $originalName = $uploadedFiles['name'][$i];
                    $tmpPath = $uploadedFiles['tmp_name'][$i];
                    
                    // Sanitize filename
                    $safeFileName = sanitizeUploadFilename($originalName);
                    $targetPath = $currentDir . '/' . $safeFileName;
                    
                    if (move_uploaded_file($tmpPath, $targetPath)) {
                        $successCount++;
                    } else {
                        $errors[] = "Failed to upload: " . htmlspecialchars($originalName);
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
            
            echo json_encode([
                'success' => $successCount > 0,
                'message' => $successCount . " file(s) uploaded successfully",
                'errors' => $errors
            ]);
            exit();
        }
    }
}
?>
