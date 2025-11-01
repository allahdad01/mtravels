<?php
// Start session and include necessary files
session_start();
require_once '../../config.php';
require_once '../../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Initialize response
$response = ['success' => false, 'message' => ''];

// Set up base uploads directory
$uploadsDir = '../../uploads';
$realUploadsPath = realpath($uploadsDir);

// Function to create directory path recursively
function createDirectoryPath($basePath, $folderPath) {
    // Convert + to spaces and decode URL
    $folderPath = urldecode(str_replace('+', ' ', $folderPath));
    
    // Split path into segments
    $segments = array_filter(explode('/', $folderPath));
    $currentPath = $basePath;
    
    foreach ($segments as $segment) {
        $currentPath .= '/' . $segment;
        
        // Skip if it's a file path component
        if (strpos($segment, '.') !== false) continue;
        
        // Create directory if it doesn't exist
        if (!file_exists($currentPath)) {
            if (!mkdir($currentPath, 0755, true)) {
                return false;
            }
        }
    }
    
    return true;
}

// Function to delete a directory and its contents
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? deleteDirectory("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

// Function to sanitize file name
function sanitizeFileName($fileName) {
    // Remove any directory components
    $fileName = basename($fileName);
    
    // Replace spaces with underscores
    $fileName = str_replace(' ', '_', $fileName);
    
    // Remove special characters except alphanumeric, dash, underscore, and dot
    $fileName = preg_replace('/[^A-Za-z0-9\-\_\.]/', '', $fileName);
    
    return $fileName;
}

// Function to check if file already exists and generate unique name
function getUniqueFileName($targetPath, $fileName) {
    $info = pathinfo($fileName);
    $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
    $name = isset($info['filename']) ? $info['filename'] : $fileName;
    
    $counter = 0;
    $newFileName = $fileName;
    
    while (file_exists($targetPath . '/' . $newFileName)) {
        $counter++;
        $newFileName = $name . '_' . $counter . $ext;
    }
    
    return $newFileName;
}

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $currentFolder = isset($_POST['folder']) ? trim($_POST['folder']) : '';
    $currentDir = $uploadsDir;
    
    if (!empty($currentFolder)) {
        // Decode the folder path and create directories if needed
        if (!createDirectoryPath($uploadsDir, $currentFolder)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to create directory structure']);
            exit;
        }
        
        // Update current directory with decoded path
        $currentFolder = urldecode(str_replace('+', ' ', $currentFolder));
        $currentDir .= '/' . $currentFolder;
    }
    
    // Validate that the target directory exists and is within uploads
    $realCurrentDir = realpath($currentDir);
    if ($realCurrentDir === false || strpos($realCurrentDir, $realUploadsPath) !== 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid directory path']);
        exit;
    }
    
    switch ($_POST['action']) {
        case 'upload_file':
            if (!empty($_FILES['files'])) {
                $uploadedFiles = $_FILES['files'];
                $successCount = 0;
                $errors = [];
                $maxFileSize = 100 * 1024 * 1024; // 100MB limit
                
                // Handle multiple files
                for ($i = 0; $i < count($uploadedFiles['name']); $i++) {
                    $fileName = $uploadedFiles['name'][$i];
                    $fileSize = $uploadedFiles['size'][$i];
                    $tmpPath = $uploadedFiles['tmp_name'][$i];
                    $fileError = $uploadedFiles['error'][$i];
                    
                    // Check for upload errors
                    if ($fileError !== UPLOAD_ERR_OK) {
                        switch ($fileError) {
                            case UPLOAD_ERR_INI_SIZE:
                            case UPLOAD_ERR_FORM_SIZE:
                                $errors[] = "File too large: " . htmlspecialchars($fileName);
                                break;
                            case UPLOAD_ERR_PARTIAL:
                                $errors[] = "File only partially uploaded: " . htmlspecialchars($fileName);
                                break;
                            case UPLOAD_ERR_NO_FILE:
                                $errors[] = "No file was uploaded for: " . htmlspecialchars($fileName);
                                break;
                            default:
                                $errors[] = "Unknown error for: " . htmlspecialchars($fileName);
                        }
                        continue;
                    }
                    
                    // Check file size
                    if ($fileSize > $maxFileSize) {
                        $errors[] = "File too large (max 100MB): " . htmlspecialchars($fileName);
                        continue;
                    }
                    
                    // Sanitize and make filename unique
                    $safeFileName = sanitizeFileName($fileName);
                    $uniqueFileName = getUniqueFileName($currentDir, $safeFileName);
                    $targetPath = $currentDir . '/' . $uniqueFileName;
                    
                    // Move uploaded file
                    if (move_uploaded_file($tmpPath, $targetPath)) {
                        // Set proper permissions
                        chmod($targetPath, 0644);
                        $successCount++;
                    } else {
                        $errors[] = "Failed to upload: " . htmlspecialchars($fileName);
                    }
                }
                
                $response['success'] = $successCount > 0;
                $response['message'] = $successCount . " file(s) uploaded successfully. ";
                if (!empty($errors)) {
                    $response['message'] .= "Errors: " . implode(", ", $errors);
                }
            } else {
                $response['message'] = 'No files uploaded';
            }
            break;
            
        case 'delete_item':
            $itemPath = trim($_POST['item_path']);
            $fullPath = $uploadsDir . '/' . $itemPath;
            
            // Validate path is within uploads directory
            $realItemPath = realpath($fullPath);
            if ($realItemPath === false || strpos($realItemPath, $realUploadsPath) !== 0) {
                $response['message'] = 'Invalid path';
            } else {
                if (is_dir($fullPath)) {
                    // Delete directory and its contents
                    if (deleteDirectory($fullPath)) {
                        $response['success'] = true;
                        $response['message'] = 'Directory deleted successfully';
                    } else {
                        $response['message'] = 'Failed to delete directory';
                    }
                } else {
                    // Delete single file
                    if (unlink($fullPath)) {
                        $response['success'] = true;
                        $response['message'] = 'File deleted successfully';
                    } else {
                        $response['message'] = 'Failed to delete file';
                    }
                }
            }
            break;
            
        case 'rename_item':
            $oldPath = trim($_POST['old_path']);
            $newName = trim($_POST['new_name']);
            
            // Validate paths
            $fullOldPath = $uploadsDir . '/' . $oldPath;
            $pathInfo = pathinfo($fullOldPath);
            $fullNewPath = $pathInfo['dirname'] . '/' . $newName;
            
            // Validate paths are within uploads directory
            $realOldPath = realpath($fullOldPath);
            $realNewPath = realpath($pathInfo['dirname']);
            
            if ($realOldPath === false || strpos($realOldPath, $realUploadsPath) !== 0 ||
                $realNewPath === false || strpos($realNewPath, $realUploadsPath) !== 0) {
                $response['message'] = 'Invalid path';
                break;
            }
            
            // Validate new name
            if (empty($newName) || preg_match('/[\/\\\\]/', $newName)) {
                $response['message'] = 'Invalid new name';
                break;
            }
            
            // Check if target already exists
            if (file_exists($fullNewPath)) {
                $response['message'] = 'A file or folder with this name already exists';
                break;
            }
            
            // Perform rename
            if (rename($fullOldPath, $fullNewPath)) {
                $response['success'] = true;
                $response['message'] = 'Item renamed successfully';
            } else {
                $response['message'] = 'Failed to rename item';
            }
            break;
            
        case 'create_folder':
            $folderName = trim($_POST['folder_name']);
            $newPath = $currentDir . '/' . $folderName;
            
            // Basic folder name validation
            if (empty($folderName) || preg_match('/[\/\\\\]/', $folderName)) {
                $response['message'] = 'Invalid folder name';
            } else {
                // Create the folder
                if (!file_exists($newPath) && mkdir($newPath, 0755)) {
                    $response['success'] = true;
                    $response['message'] = 'Folder created successfully';
                } else {
                    $response['message'] = 'Failed to create folder';
                }
            }
            break;
            
        case 'move_items':
        case 'copy_items':
            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || !isset($input['items']) || !isset($input['destination'])) {
                $response['message'] = 'Invalid request data';
                break;
            }
            
            $items = $input['items'];
            $destination = trim($input['destination']);
            $isCopy = $_POST['action'] === 'copy_items';
            
            // Validate destination path
            $fullDestPath = $uploadsDir;
            if (!empty($destination)) {
                $fullDestPath .= '/' . $destination;
            }
            
            $realDestPath = realpath($fullDestPath);
            if ($realDestPath === false || strpos($realDestPath, $realUploadsPath) !== 0) {
                $response['message'] = 'Invalid destination path';
                break;
            }
            
            // Create destination directory if it doesn't exist
            if (!file_exists($fullDestPath)) {
                if (!mkdir($fullDestPath, 0755, true)) {
                    $response['message'] = 'Failed to create destination directory';
                    break;
                }
            }
            
            $errors = [];
            $successCount = 0;
            
            foreach ($items as $item) {
                $sourcePath = $uploadsDir . '/' . $item;
                $itemName = basename($item);
                $targetPath = $fullDestPath . '/' . $itemName;
                
                // Validate source path
                $realSourcePath = realpath($sourcePath);
                if ($realSourcePath === false || strpos($realSourcePath, $realUploadsPath) !== 0) {
                    $errors[] = "Invalid source path: $itemName";
                    continue;
                }
                
                // Skip if source and target are the same
                if ($sourcePath === $targetPath) {
                    $errors[] = "Source and destination are the same: $itemName";
                    continue;
                }
                
                // Handle name conflicts
                if (file_exists($targetPath)) {
                    $info = pathinfo($itemName);
                    $counter = 1;
                    do {
                        $newName = $info['filename'] . '_' . $counter;
                        if (isset($info['extension'])) {
                            $newName .= '.' . $info['extension'];
                        }
                        $targetPath = $fullDestPath . '/' . $newName;
                        $counter++;
                    } while (file_exists($targetPath));
                }
                
                try {
                    if ($isCopy) {
                        if (is_dir($sourcePath)) {
                            // Recursive directory copy
                            recursiveCopy($sourcePath, $targetPath);
                        } else {
                            copy($sourcePath, $targetPath);
                        }
                    } else {
                        rename($sourcePath, $targetPath);
                    }
                    $successCount++;
                } catch (Exception $e) {
                    $errors[] = ($isCopy ? 'Failed to copy: ' : 'Failed to move: ') . $itemName;
                }
            }
            
            $response['success'] = $successCount > 0;
            $response['message'] = $successCount . ' item(s) ' . 
                                 ($isCopy ? 'copied' : 'moved') . ' successfully. ';
            if (!empty($errors)) {
                $response['message'] .= 'Errors: ' . implode(', ', $errors);
            }
            break;
            
        case 'get_file_info':
            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || !isset($input['path'])) {
                $response['message'] = 'Invalid request data';
                break;
            }
            
            $path = trim($input['path']);
            $fullPath = $uploadsDir . '/' . $path;
            
            // Validate path is within uploads directory
            $realPath = realpath($fullPath);
            if ($realPath === false || strpos($realPath, $realUploadsPath) !== 0) {
                $response['message'] = 'Invalid path';
                break;
            }
            
            try {
                $stat = stat($fullPath);
                $perms = fileperms($fullPath);
                
                // Format permissions string
                $info = '';
                
                // Owner
                $info .= (($perms & 0x0100) ? 'r' : '-');
                $info .= (($perms & 0x0080) ? 'w' : '-');
                $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x' ) : (($perms & 0x0800) ? 'S' : '-'));
                
                // Group
                $info .= (($perms & 0x0020) ? 'r' : '-');
                $info .= (($perms & 0x0010) ? 'w' : '-');
                $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x' ) : (($perms & 0x0400) ? 'S' : '-'));
                
                // World
                $info .= (($perms & 0x0004) ? 'r' : '-');
                $info .= (($perms & 0x0002) ? 'w' : '-');
                $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x' ) : (($perms & 0x0200) ? 'T' : '-'));
                
                $response['success'] = true;
                $response['size'] = $stat['size'];
                $response['created'] = $stat['ctime'];
                $response['modified'] = $stat['mtime'];
                $response['accessed'] = $stat['atime'];
                $response['permissions'] = $info;
                $response['owner'] = function_exists('posix_getpwuid') ? 
                    posix_getpwuid($stat['uid'])['name'] : $stat['uid'];
                $response['group'] = function_exists('posix_getgrgid') ? 
                    posix_getgrgid($stat['gid'])['name'] : $stat['gid'];
            } catch (Exception $e) {
                $response['message'] = 'Failed to get file information';
            }
            break;
            
        default:
            $response['message'] = 'Invalid action';
            break;
    }
    
    // Function to recursively copy a directory
    function recursiveCopy($source, $dest) {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                mkdir($dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            } else {
                copy($item, $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response); 