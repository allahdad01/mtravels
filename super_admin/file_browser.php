<?php
// Start session and include necessary files
session_start();
require_once '../config.php';
require_once '../includes/db.php';

// Include language system
require_once '../includes/language_helpers.php';
$lang = init_language();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get settings for site title, etc.
$settings = getSettingsPdo();

// Function to get all files in a directory recursively
function scanDirectory($dir, $baseDir = '') {
    $result = [];
    
    // Check if directory exists before scanning
    if (!is_dir($dir)) {
        return $result;
    }
    
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $path = $dir . '/' . $file;
        $relativePath = $baseDir ? $baseDir . '/' . $file : $file;
        
        if (is_dir($path)) {
            $result = array_merge($result, scanDirectory($path, $relativePath));
        } else {
            $result[] = [
                'name' => $file,
                'path' => $relativePath,
                'full_path' => $path,
                'size' => filesize($path),
                'type' => mime_content_type($path),
                'modified' => filemtime($path)
            ];
        }
    }
    
    return $result;
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

// Initialize variables
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$currentFolder = isset($_GET['folder']) ? trim($_GET['folder']) : '';
$uploadsDir = '../uploads';
$currentDir = $uploadsDir;

if (!empty($currentFolder)) {
    $currentDir .= '/' . $currentFolder;
}

// Validate that the requested directory is within uploads
$realUploadsPath = realpath($uploadsDir);
$requestedPath = realpath($currentDir);

if ($requestedPath === false || strpos($requestedPath, $realUploadsPath) !== 0) {
    // Directory traversal attempt or invalid directory
    $currentDir = $uploadsDir;
    $currentFolder = '';
}

// Handle AJAX requests first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if ($_POST['action'] === 'delete_item') {
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
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    if ($_POST['action'] === 'create_folder') {
        $folderName = trim($_POST['folder_name']);
        $currentPath = $currentDir . '/' . $folderName;
        
        // Basic folder name validation
        if (empty($folderName) || preg_match('/[\/\\\\]/', $folderName)) {
            $response['message'] = 'Invalid folder name';
        } else {
            // Create the folder
            if (!file_exists($currentPath) && mkdir($currentPath, 0755)) {
                $response['success'] = true;
                $response['message'] = 'Folder created successfully';
            } else {
                $response['message'] = 'Failed to create folder';
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    if ($_POST['action'] === 'upload_file') {
        if (!empty($_FILES['files'])) {
            $uploadedFiles = $_FILES['files'];
            $successCount = 0;
            $errors = [];
            
            // Handle multiple files
            for ($i = 0; $i < count($uploadedFiles['name']); $i++) {
                $fileName = $uploadedFiles['name'][$i];
                $tmpPath = $uploadedFiles['tmp_name'][$i];
                $targetPath = $currentDir . '/' . $fileName;
                
                // Basic file name validation
                if (preg_match('/[\/\\\\]/', $fileName)) {
                    $errors[] = "Invalid filename: " . htmlspecialchars($fileName);
                    continue;
                }
                
                // Move uploaded file
                if (move_uploaded_file($tmpPath, $targetPath)) {
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
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Get all files in current directory or based on search
$allFiles = [];

if (!empty($searchQuery)) {
    // Search in all directories
    $allFiles = scanDirectory($uploadsDir);
    
    // Filter by search query
    $filteredFiles = [];
    foreach ($allFiles as $file) {
        if (stripos($file['name'], $searchQuery) !== false || 
            stripos($file['path'], $searchQuery) !== false) {
            $filteredFiles[] = $file;
        }
    }
    $allFiles = $filteredFiles;
} else {
    // Just list files in current directory (non-recursive)
    if (is_dir($currentDir)) {
        $files = scandir($currentDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $path = $currentDir . '/' . $file;
            $relativePath = $currentFolder ? $currentFolder . '/' . $file : $file;
            
            if (is_dir($path)) {
                $allFiles[] = [
                    'name' => $file,
                    'path' => $relativePath,
                    'full_path' => $path,
                    'size' => 0,
                    'type' => 'directory',
                    'modified' => filemtime($path),
                    'is_dir' => true
                ];
            } else {
                $allFiles[] = [
                    'name' => $file,
                    'path' => $relativePath,
                    'full_path' => $path,
                    'size' => filesize($path),
                    'type' => mime_content_type($path),
                    'modified' => filemtime($path),
                    'is_dir' => false
                ];
            }
        }
        
        // Sort directories first, then files
        usort($allFiles, function($a, $b) {
            if ($a['is_dir'] && !$b['is_dir']) return -1;
            if (!$a['is_dir'] && $b['is_dir']) return 1;
            return strcasecmp($a['name'], $b['name']);
        });
    }
}

// Format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Get file icon based on type
function getFileIcon($type) {
    if ($type === 'directory') {
        return 'feather icon-folder';
    } elseif (strpos($type, 'image/') === 0) {
        return 'feather icon-image';
    } elseif (strpos($type, 'application/pdf') === 0) {
        return 'feather icon-file-text';
    } elseif (strpos($type, 'text/') === 0) {
        return 'feather icon-file-text';
    } elseif (strpos($type, 'application/vnd.openxmlformats-officedocument') === 0 || 
              strpos($type, 'application/vnd.ms-') === 0) {
        return 'feather icon-file';
    } else {
        return 'feather icon-file';
    }
}

// Get file type label and class
function getFileTypeLabel($type) {
    if (strpos($type, 'image/') === 0) {
        return 'Image';
    } elseif (strpos($type, 'application/pdf') === 0) {
        return 'PDF';
    } elseif (strpos($type, 'text/') === 0) {
        return 'Text';
    } elseif (strpos($type, 'application/vnd.openxmlformats-officedocument') === 0 || 
              strpos($type, 'application/vnd.ms-') === 0) {
        return 'Document';
    } else {
        return 'File';
    }
}

// Get file type class for badge
function getFileTypeClass($type) {
    if (strpos($type, 'image/') === 0) {
        return 'file-type-image';
    } elseif (strpos($type, 'application/pdf') === 0) {
        return 'file-type-pdf';
    } elseif (strpos($type, 'text/') === 0) {
        return 'file-type-text';
    } elseif (strpos($type, 'application/vnd.openxmlformats-officedocument') === 0 || 
              strpos($type, 'application/vnd.ms-') === 0) {
        return 'file-type-archive';
    } else {
        return 'file-type-other';
    }
}

// Breadcrumb generation
function generateBreadcrumb($folder) {
    // Use function instead of global variable for language
    $uploadsText = __('uploads');
    if ($uploadsText === 'uploads') {
        $uploadsText = 'Uploads';
    }
    
    $parts = explode('/', $folder);
    $breadcrumb = '<li class="breadcrumb-item"><a href="file_browser.php">' . $uploadsText . '</a></li>';
    $path = '';
    
    foreach ($parts as $part) {
        if (empty($part)) continue;
        $path .= '/' . $part;
        $breadcrumb .= '<li class="breadcrumb-item"><a href="file_browser.php?folder=' . urlencode(trim($path, '/')) . '">' . htmlspecialchars($part) . '</a></li>';
    }
    
    return $breadcrumb;
}

// Page title
$fileBrowserText = __('file_browser');
if ($fileBrowserText === 'file_browser') {
    $fileBrowserText = 'File Browser';
}

$searchResultsText = __('search_results');
if ($searchResultsText === 'search_results') {
    $searchResultsText = 'Search Results';
}

$pageTitle = empty($searchQuery) ? $fileBrowserText : $searchResultsText . ': ' . htmlspecialchars($searchQuery);

// Function to get web-accessible URL for a file
function getFileUrl($path) {
    // Remove the leading "../" from the path
    $path = preg_replace('/^\.\.\//', '', $path);
    
    // Return the URL with the correct base
    return '../' . $path;
}

?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<?php
// Handle file upload
?>

<head>
    <title><?php echo htmlspecialchars($pageTitle); ?> | <?php echo htmlspecialchars($settings['agency_name']); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="File browser for uploads directory" />
    <meta name="keywords" content="file browser, uploads, documents" />
    
    <!-- Favicon icon -->
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
    <!-- fontawesome icon -->
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <!-- animation css -->
    <link rel="stylesheet" href="../assets/plugins/animation/css/animate.min.css">
    <!-- vendor css -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Custom styles for this page -->
    <style>
        .file-card {
            transition: all 0.3s ease;
        }
        
        .file-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .file-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .file-name {
            word-break: break-word;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .file-info {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .preview-image {
            max-height: 150px;
            max-width: 100%;
            margin-bottom: 10px;
            object-fit: contain;
        }
        
        .search-container {
            position: relative;
        }
        
        .search-container .search-icon {
            position: absolute;
            top: 50%;
            right: 20px;
            transform: translateY(-50%);
            color: #6c757d;
        }

        /* New styles for enhanced preview */
        .preview-modal .modal-dialog {
            max-width: 90%;
            height: 90vh;
            margin: 1.75rem auto;
        }

        .preview-modal .modal-content {
            height: 100%;
        }

        .preview-modal .modal-body {
            height: calc(100% - 120px);
            padding: 0;
            overflow: hidden;
        }

        .preview-frame {
            width: 100%;
            height: 100%;
            border: none;
        }

        .preview-content {
            width: 100%;
            height: 100%;
            overflow: auto;
            padding: 1rem;
        }

        .preview-text {
            font-family: monospace;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .preview-pdf {
            width: 100%;
            height: 100%;
        }

        .preview-image-full {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .preview-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1050;
        }

        .preview-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            font-size: 1.2rem;
            color: #6c757d;
        }

        .file-type-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* File type colors */
        .file-type-image { background-color: #28a745; color: white; }
        .file-type-pdf { background-color: #dc3545; color: white; }
        .file-type-text { background-color: #17a2b8; color: white; }
        .file-type-code { background-color: #6610f2; color: white; }
        .file-type-archive { background-color: #fd7e14; color: white; }
        .file-type-other { background-color: #6c757d; color: white; }

        .dropzone {
            border: 2px dashed #ccc;
            border-radius: 4px;
            padding: 30px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .dropzone:hover {
            border-color: #007bff;
            background: #f1f8ff;
        }

        .dropzone.dragover {
            border-color: #28a745;
            background: #e8f5e9;
        }

        .upload-icon {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
        }

        .progress {
            background-color: #e9ecef;
            box-shadow: inset 0 1px 2px rgba(0,0,0,.1);
        }

        .progress-bar {
            transition: width 0.3s ease;
            font-size: 14px;
            font-weight: 600;
            line-height: 25px;
            text-align: center;
        }

        .progress-bar.bg-success {
            background-color: #28a745 !important;
        }

        .progress-bar.bg-danger {
            background-color: #dc3545 !important;
        }

        .progress-bar.bg-warning {
            background-color: #ffc107 !important;
            color: #000;
        }

        .progress-text {
            font-size: 15px;
        }

        .progress-percentage {
            font-size: 15px;
            min-width: 50px;
            text-align: right;
        }

        .progress-info {
            font-size: 13px;
        }
        
        /* Bulk action styles */
        .bulk-actions {
            display: none;
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            background: #fff;
            padding: 15px 25px;
            border-radius: 50px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .bulk-actions.show {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .bulk-actions .selected-count {
            font-weight: 600;
            margin-right: 15px;
        }
        
        .file-card {
            position: relative;
        }
        
        .file-select {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 1;
            transform: scale(1.2);
        }
        
        .file-card.selected {
            border: 2px solid #007bff;
            box-shadow: 0 0 10px rgba(0,123,255,0.2);
        }
        
        /* Select all checkbox */
        .select-all-container {
            margin-bottom: 15px;
        }
        
        /* Existing styles */
        
        /* View mode styles */
        .view-mode-list .file-card {
            margin-bottom: 0.5rem !important;
        }
        
        .view-mode-list .file-card .card-body {
            padding: 0.75rem;
            display: flex;
            align-items: center;
        }
        
        .view-mode-list .file-card .file-icon {
            font-size: 1.5rem;
            margin: 0 1rem 0 0;
        }
        
        .view-mode-list .file-card .file-name {
            margin: 0;
            flex: 1;
            text-align: left;
        }
        
        .view-mode-list .file-card .file-info {
            display: flex;
            align-items: center;
            margin-left: 1rem;
        }
        
        .view-mode-list .file-card .file-info p {
            margin: 0 1rem 0 0;
        }
        
        .view-mode-list .file-card .file-info > div {
            margin: 0;
            display: flex;
            gap: 0.5rem;
        }
        
        .view-mode-list .preview-image {
            max-height: 40px;
            margin: 0 1rem 0 0;
        }
        
        .view-mode-list .file-type-badge {
            position: static;
            margin-left: 1rem;
        }
        
        /* View mode toggle button styles */
        .view-mode-toggle {
            display: flex;
            gap: 0.5rem;
        }
        
        .view-mode-toggle button {
            padding: 0.375rem 0.75rem;
            border: 1px solid #dee2e6;
            background: #fff;
            color: #6c757d;
            border-radius: 0.25rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .view-mode-toggle button:hover {
            background: #f8f9fa;
        }
        
        .view-mode-toggle button.active {
            background: #007bff;
            color: #fff;
            border-color: #007bff;
        }
    </style>
</head>

    <!-- [ Header ] start -->
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="css/modal-styles.css">
    <!-- [ Header ] end -->

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ breadcrumb ] start -->
                            <div class="page-header">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="page-header-title">
                                            <h5><?php echo htmlspecialchars($pageTitle); ?></h5>
                                            <nav aria-label="breadcrumb">
                                                <ol class="breadcrumb">
                                                    <?php if (empty($searchQuery)): ?>
                                                        <?php if (empty($currentFolder)): ?>
                                                            <li class="breadcrumb-item active"><?= __('uploads') !== 'uploads' ? __('uploads') : 'Uploads' ?></li>
                                                        <?php else: ?>
                                                            <?php echo generateBreadcrumb($currentFolder); ?>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <li class="breadcrumb-item"><a href="file_browser.php"><?= __('uploads') !== 'uploads' ? __('uploads') : 'Uploads' ?></a></li>
                                                        <li class="breadcrumb-item active"><?= __('search_results') !== 'search_results' ? __('search_results') : 'Search results' ?></li>
                                                    <?php endif; ?>
                                                </ol>
                                            </nav>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="search-container mb-3">
                                            <form action="file_browser.php" method="GET">
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="search" placeholder="<?= __('search_files') !== 'search_files' ? __('search_files') : 'Search files...' ?>" value="<?php echo htmlspecialchars($searchQuery); ?>">
                                                    <div class="input-group-append">
                                                        <button class="btn btn-primary" type="submit">
                                                            <i class="feather icon-search"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- [ breadcrumb ] end -->

                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <?php if (empty($allFiles)): ?>
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-body text-center py-5">
                                                <i class="feather icon-search" style="font-size: 3rem; color: #6c757d;"></i>
                                                <h3 class="mt-3"><?= __('no_files_found') !== 'no_files_found' ? __('no_files_found') : 'No files found' ?></h3>
                                                <?php if (!empty($searchQuery)): ?>
                                                    <p>No files match your search query: "<?php echo htmlspecialchars($searchQuery); ?>"</p>
                                                    <a href="file_browser.php" class="btn btn-outline-primary mt-2"><?= __('clear_search') !== 'clear_search' ? __('clear_search') : 'Clear Search' ?></a>
                                                <?php else: ?>
                                                    <p><?= __('directory_empty') !== 'directory_empty' ? __('directory_empty') : 'This directory is empty' ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($allFiles as $file): ?>
                                        <div class="col-sm-6 col-md-4 col-lg-3 mb-4">
                                            <div class="card file-card h-100" data-mime-type="<?php echo htmlspecialchars($file['type']); ?>">
                                                <div class="card-body text-center">
                                                    <?php if ($file['is_dir'] ?? false): ?>
                                                        <a href="file_browser.php?folder=<?php echo urlencode($file['path']); ?>">
                                                            <i class="<?php echo getFileIcon('directory'); ?> file-icon text-primary"></i>
                                                            <h5 class="file-name"><?php echo htmlspecialchars($file['name']); ?></h5>
                                                        </a>
                                                    <?php else: ?>
                                                        <?php 
                                                        // Get the correct URL for the file
                                                        $fileUrl = getFileUrl($file['full_path']);
                                                        ?>
                                                        <span class="file-type-badge <?php echo getFileTypeClass($file['type']); ?>">
                                                            <?php echo getFileTypeLabel($file['type']); ?>
                                                        </span>
                                                        
                                                        <?php if (strpos($file['type'], 'image/') === 0): ?>
                                                            <a href="<?php echo htmlspecialchars($fileUrl); ?>" target="_blank">
                                                                <img src="<?php echo htmlspecialchars($fileUrl); ?>" class="preview-image" alt="<?php echo htmlspecialchars($file['name']); ?>">
                                                                <h5 class="file-name"><?php echo htmlspecialchars($file['name']); ?></h5>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="<?php echo htmlspecialchars($fileUrl); ?>" target="_blank">
                                                                <i class="<?php echo getFileIcon($file['type']); ?> file-icon text-primary"></i>
                                                                <h5 class="file-name"><?php echo htmlspecialchars($file['name']); ?></h5>
                                                            </a>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    
                                                    <div class="file-info">
                                                        <?php if (!($file['is_dir'] ?? false)): ?>
                                                            <p class="mb-1"><?php echo formatFileSize($file['size']); ?></p>
                                                        <?php endif; ?>
                                                        <p class="mb-0">
                                                            <?= __('modified') !== 'modified' ? __('modified') : 'Modified' ?>: <?php echo date('Y-m-d H:i', $file['modified']); ?>
                                                        </p>
                                                        <?php if (!empty($searchQuery)): ?>
                                                            <p class="mt-2 text-muted">
                                                                <?= __('path') !== 'path' ? __('path') : 'Path' ?>: <?php echo htmlspecialchars($file['path']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        <div class="mt-3">
                                                            <?php if (!($file['is_dir'] ?? false)): ?>
                                                                <button type="button" class="btn btn-sm btn-info preview-file" 
                                                                        data-url="<?php echo htmlspecialchars($fileUrl); ?>"
                                                                        data-name="<?php echo htmlspecialchars($file['name']); ?>"
                                                                        data-type="<?php echo htmlspecialchars($file['type']); ?>">
                                                                    <i class="feather icon-eye"></i> Preview
                                                                </button>
                                                            <?php endif; ?>
                                                            <button type="button" class="btn btn-sm btn-danger delete-item" 
                                                                    data-path="<?php echo htmlspecialchars($file['path']); ?>"
                                                                    data-name="<?php echo htmlspecialchars($file['name']); ?>"
                                                                    data-type="<?php echo $file['is_dir'] ?? false ? 'directory' : 'file'; ?>">
                                                                <i class="feather icon-trash-2"></i> Delete
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <!-- [ Main Content ] end -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <span id="deleteItemName"></span>?</p>
                    <p class="text-danger" id="deleteWarning"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload and New Folder Modals -->
    <div class="modal fade" id="uploadModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Files</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="uploadForm" enctype="multipart/form-data">
                        <div class="dropzone" id="dropzone">
                            <i class="feather icon-upload-cloud upload-icon"></i>
                            <p>Drag & drop files here or click to select</p>
                            <p class="text-muted small">Maximum file size: 100MB</p>
                            <input type="file" id="fileInput" multiple style="display: none;">
                        </div>
                        <div id="fileList" class="mt-3"></div>
                        <div class="upload-progress mt-3" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="progress-text font-weight-medium">Uploading...</span>
                                <span class="progress-percentage font-weight-bold">0%</span>
                            </div>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                                     role="progressbar" 
                                     style="width: 0%" 
                                     aria-valuenow="0" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">0%</div>
                            </div>
                            <p class="progress-info text-center text-muted mt-2 mb-0"></p>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="cancelUpload" style="display: none;">
                        <i class="feather icon-x"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="uploadButton">
                        <i class="feather icon-upload"></i> Upload
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="newFolderModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Folder</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="newFolderForm">
                        <div class="form-group">
                            <label for="folderName">Folder Name</label>
                            <input type="text" class="form-control" id="folderName" name="folder_name" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="createFolderButton">Create</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Preview Modal -->
    <div class="modal fade preview-modal" id="previewModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">File Preview</h5>
                    <div class="preview-actions">
                        <a href="#" class="btn btn-sm btn-primary mr-2" id="downloadFile" download>
                            <i class="feather icon-download"></i> Download
                        </a>
                        <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">
                            <i class="feather icon-x"></i> Close
                        </button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="preview-loading">
                        <div class="text-center">
                            <i class="feather icon-loader spin mr-2"></i> Loading preview...
                        </div>
                    </div>
                    <div class="preview-content" style="display: none;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Rename Modal -->
    <div class="modal fade" id="renameModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rename Item</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="renameForm">
                        <div class="form-group">
                            <label for="newName">New Name</label>
                            <input type="text" class="form-control" id="newName" name="new_name" required>
                            <small class="form-text text-muted">Enter the new name for this item</small>
                        </div>
                        <input type="hidden" id="oldPath" name="old_path">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmRename">Rename</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Move/Copy Modal -->
    <div class="modal fade" id="moveModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Move/Copy Items</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Destination Folder</label>
                        <select class="form-control" id="destinationFolder">
                            <option value="">Root</option>
                            <?php
                            function listFoldersRecursive($dir, $baseDir = '', $level = 0) {
                                $folders = array_filter(scandir($dir), function($item) use ($dir) {
                                    return $item != '.' && $item != '..' && is_dir($dir . '/' . $item);
                                });
                                
                                foreach ($folders as $folder) {
                                    $path = $baseDir ? $baseDir . '/' . $folder : $folder;
                                    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
                                    echo '<option value="' . htmlspecialchars($path) . '">' . $indent . htmlspecialchars($folder) . '</option>';
                                    
                                    listFoldersRecursive($dir . '/' . $folder, $path, $level + 1);
                                }
                            }
                            
                            listFoldersRecursive($uploadsDir);
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmMove">Move Here</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Bulk Delete Modal -->
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Bulk Delete</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the selected items? This action cannot be undone.</p>
                    <div id="selectedItemsList"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmBulkDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    
    <!-- Add Keyboard Shortcuts Help Modal -->
    <div class="modal fade" id="keyboardShortcutsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Keyboard Shortcuts</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Shortcut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Upload Files</td>
                                <td><kbd>U</kbd></td>
                            </tr>
                            <tr>
                                <td>New Folder</td>
                                <td><kbd>N</kbd></td>
                            </tr>
                            <tr>
                                <td>Select All</td>
                                <td><kbd>Ctrl</kbd> + <kbd>A</kbd></td>
                            </tr>
                            <tr>
                                <td>Delete Selected</td>
                                <td><kbd>Delete</kbd></td>
                            </tr>
                            <tr>
                                <td>Cancel Selection</td>
                                <td><kbd>Esc</kbd></td>
                            </tr>
                            <tr>
                                <td>Quick Filter</td>
                                <td><kbd>Ctrl</kbd> + <kbd>F</kbd></td>
                            </tr>
                            <tr>
                                <td>Toggle View Mode</td>
                                <td><kbd>V</kbd></td>
                            </tr>
                            <tr>
                                <td>Show Shortcuts</td>
                                <td><kbd>?</kbd></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add File Details Panel -->
    <div class="file-details-panel" style="display: none;">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">File Details</h5>
                <button type="button" class="close" id="closeDetails">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <i class="file-icon-large mb-3"></i>
                    <h5 class="details-filename mb-0"></h5>
                </div>
                
                <div class="details-content">
                    <table class="table table-sm">
                        <tbody>
                            <tr>
                                <th>Type:</th>
                                <td class="details-type"></td>
                            </tr>
                            <tr>
                                <th>Size:</th>
                                <td class="details-size"></td>
                            </tr>
                            <tr>
                                <th>Location:</th>
                                <td class="details-path"></td>
                            </tr>
                            <tr>
                                <th>Created:</th>
                                <td class="details-created"></td>
                            </tr>
                            <tr>
                                <th>Modified:</th>
                                <td class="details-modified"></td>
                            </tr>
                            <tr>
                                <th>Permissions:</th>
                                <td class="details-permissions"></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="details-preview mt-4">
                        <h6>Preview</h6>
                        <div class="preview-container"></div>
                    </div>
                    
                    <div class="details-actions mt-4">
                        <div class="btn-group w-100">
                            <button type="button" class="btn btn-sm btn-primary details-download">
                                <i class="feather icon-download"></i> Download
                            </button>
                            <button type="button" class="btn btn-sm btn-info details-preview-btn">
                                <i class="feather icon-eye"></i> Preview
                            </button>
                            <button type="button" class="btn btn-sm btn-warning details-rename">
                                <i class="feather icon-edit-2"></i> Rename
                            </button>
                            <button type="button" class="btn btn-sm btn-danger details-delete">
                                <i class="feather icon-trash-2"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    /* Existing styles */
    
    /* File details panel styles */
    .file-details-panel {
        position: fixed;
        top: 0;
        right: 0;
        width: 350px;
        height: 100vh;
        background: #fff;
        border-left: 1px solid #ddd;
        z-index: 1030;
        transform: translateX(100%);
        transition: transform 0.3s ease;
    }
    
    .file-details-panel.show {
        transform: translateX(0);
    }
    
    .file-details-panel .card {
        height: 100%;
        border: none;
        border-radius: 0;
    }
    
    .file-details-panel .card-body {
        overflow-y: auto;
    }
    
    .file-icon-large {
        font-size: 3rem;
        color: #007bff;
    }
    
    .details-filename {
        word-break: break-word;
    }
    
    .details-preview {
        max-height: 200px;
        overflow: hidden;
    }
    
    .preview-container {
        width: 100%;
        height: 150px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border-radius: 4px;
    }
    
    .preview-container img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    
    .preview-container pre {
        width: 100%;
        height: 100%;
        margin: 0;
        padding: 10px;
        font-size: 12px;
        overflow: auto;
    }
    
    /* Adjust main content when details panel is open */
    .pcoded-main-container {
        transition: padding-right 0.3s ease;
    }
    
    .pcoded-main-container.details-open {
        padding-right: 350px;
    }
    </style>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize filter functionality
            let currentFilter = 'all';
            let currentSort = 'name-asc';
            
            // Get main container and check if we have files to filter
            const mainContainer = document.querySelector('.pcoded-main-container');
            const fileContainer = mainContainer?.querySelector('.row');
            const contentRow = fileContainer?.querySelector('.row:not(.align-items-center)');
            
            if (!contentRow) {
                console.log('No content row found, skipping filter initialization');
                return;
            }
            
            // Filter functions
            const filterFunctions = {
                all: () => true,
                image: card => card.querySelector('[data-mime-type]')?.dataset.mimeType.startsWith('image/'),
                document: card => {
                    const mimeType = card.querySelector('[data-mime-type]')?.dataset.mimeType;
                    return mimeType?.includes('pdf') || 
                           mimeType?.includes('document') || 
                           mimeType?.includes('text/');
                },
                archive: card => {
                    const mimeType = card.querySelector('[data-mime-type]')?.dataset.mimeType;
                    return mimeType?.includes('zip') || 
                           mimeType?.includes('compressed') || 
                           mimeType?.includes('archive');
                },
                folder: card => card.querySelector('[data-is-dir="true"]'),
                today: card => {
                    const modified = new Date(card.querySelector('.file-info p:nth-child(2)')?.textContent.split(': ')[1]);
                    const today = new Date();
                    return modified.toDateString() === today.toDateString();
                },
                week: card => {
                    const modified = new Date(card.querySelector('.file-info p:nth-child(2)')?.textContent.split(': ')[1]);
                    const weekAgo = new Date();
                    weekAgo.setDate(weekAgo.getDate() - 7);
                    return modified >= weekAgo;
                },
                month: card => {
                    const modified = new Date(card.querySelector('.file-info p:nth-child(2)')?.textContent.split(': ')[1]);
                    const monthAgo = new Date();
                    monthAgo.setMonth(monthAgo.getMonth() - 1);
                    return modified >= monthAgo;
                }
            };
            
            // Sort functions
            const sortFunctions = {
                'name-asc': (a, b) => {
                    const aName = a.querySelector('.file-name')?.textContent || '';
                    const bName = b.querySelector('.file-name')?.textContent || '';
                    return aName.localeCompare(bName);
                },
                'name-desc': (a, b) => {
                    const aName = a.querySelector('.file-name')?.textContent || '';
                    const bName = b.querySelector('.file-name')?.textContent || '';
                    return bName.localeCompare(aName);
                },
                'date-asc': (a, b) => {
                    const aDate = new Date(a.querySelector('.file-info p:nth-child(2)')?.textContent.split(': ')[1] || 0);
                    const bDate = new Date(b.querySelector('.file-info p:nth-child(2)')?.textContent.split(': ')[1] || 0);
                    return aDate - bDate;
                },
                'date-desc': (a, b) => {
                    const aDate = new Date(a.querySelector('.file-info p:nth-child(2)')?.textContent.split(': ')[1] || 0);
                    const bDate = new Date(b.querySelector('.file-info p:nth-child(2)')?.textContent.split(': ')[1] || 0);
                    return bDate - aDate;
                },
                'size-asc': (a, b) => {
                    const aSize = parseInt(a.querySelector('.file-info p:first-child')?.textContent.match(/\d+/)?.[0] || '0');
                    const bSize = parseInt(b.querySelector('.file-info p:first-child')?.textContent.match(/\d+/)?.[0] || '0');
                    return aSize - bSize;
                },
                'size-desc': (a, b) => {
                    const aSize = parseInt(a.querySelector('.file-info p:first-child')?.textContent.match(/\d+/)?.[0] || '0');
                    const bSize = parseInt(b.querySelector('.file-info p:first-child')?.textContent.match(/\d+/)?.[0] || '0');
                    return bSize - aSize;
                }
            };
            
            // Apply filter and sort
            function applyFilterAndSort() {
                if (!contentRow) return;

                const cards = Array.from(document.querySelectorAll('.file-card'));
                if (cards.length === 0) return;

                // Apply filter
                const filteredCards = cards.filter(filterFunctions[currentFilter]);
                
                // Sort filtered cards
                filteredCards.sort(sortFunctions[currentSort]);
                
                // Clear container
                while (contentRow.firstChild) {
                    contentRow.removeChild(contentRow.firstChild);
                }
                
                // Append sorted and filtered cards
                filteredCards.forEach(card => {
                    const col = document.createElement('div');
                    col.className = 'col-sm-6 col-md-4 col-lg-3 mb-4';
                    col.appendChild(card);
                    contentRow.appendChild(col);
                });
                
                // Show message if no results
                if (filteredCards.length === 0) {
                    const noResults = document.createElement('div');
                    noResults.className = 'col-12 text-center py-5';
                    noResults.innerHTML = `
                        <i class="feather icon-search h1 text-muted"></i>
                        <h4 class="mt-3">No items found</h4>
                        <p class="text-muted">Try changing your filter criteria</p>
                    `;
                    contentRow.appendChild(noResults);
                }
            }
            
            // Filter click handlers
            document.querySelectorAll('[data-filter]').forEach(filter => {
                filter.addEventListener('click', (e) => {
                    e.preventDefault();
                    currentFilter = e.target.dataset.filter;
                    applyFilterAndSort();
                    
                    // Update filter button text
                    const filterBtn = document.querySelector('[data-toggle="dropdown"]');
                    if (filterBtn) {
                        filterBtn.innerHTML = `<i class="feather icon-filter"></i> ${e.target.textContent}`;
                    }
                });
            });
            
            // Sort click handlers
            document.querySelectorAll('[data-sort]').forEach(sort => {
                sort.addEventListener('click', (e) => {
                    e.preventDefault();
                    currentSort = e.target.dataset.sort;
                    applyFilterAndSort();
                    
                    // Update sort button text
                    const sortBtn = document.querySelector('[data-toggle="dropdown"]:nth-child(2)');
                    if (sortBtn) {
                        sortBtn.innerHTML = `<i class="feather icon-sort"></i> ${e.target.textContent}`;
                    }
                });
            });
            
            // Quick filter functionality
            const quickFilter = document.getElementById('quickFilter');
            let quickFilterTimeout;
            
            if (quickFilter) {
                quickFilter.addEventListener('input', () => {
                    clearTimeout(quickFilterTimeout);
                    quickFilterTimeout = setTimeout(() => {
                        const searchTerm = quickFilter.value.toLowerCase();
                        const cards = document.querySelectorAll('.file-card');
                        
                        cards.forEach(card => {
                            const fileName = card.querySelector('.file-name')?.textContent.toLowerCase() || '';
                            const fileInfo = card.querySelector('.file-info')?.textContent.toLowerCase() || '';
                            const matches = fileName.includes(searchTerm) || fileInfo.includes(searchTerm);
                            const col = card.closest('[class*="col-"]');
                            if (col) {
                                col.style.display = matches ? '' : 'none';
                            }
                        });
                    }, 300);
                });
            }
            
            // Clear filter
            const clearFilterBtn = document.getElementById('clearFilter');
            if (clearFilterBtn) {
                clearFilterBtn.addEventListener('click', () => {
                    if (quickFilter) quickFilter.value = '';
                    currentFilter = 'all';
                    currentSort = 'name-asc';
                    applyFilterAndSort();
                    
                    // Reset button texts
                    const filterBtn = document.querySelector('[data-toggle="dropdown"]');
                    const sortBtn = document.querySelector('[data-toggle="dropdown"]:nth-child(2)');
                    if (filterBtn) filterBtn.innerHTML = '<i class="feather icon-filter"></i> Filter';
                    if (sortBtn) sortBtn.innerHTML = '<i class="feather icon-sort"></i> Sort By';
                });
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();

            // File upload functionality
            const dropzone = document.getElementById('dropzone');
            const fileInput = document.getElementById('fileInput');
            const uploadButton = document.getElementById('uploadButton');
            const cancelUpload = document.getElementById('cancelUpload');
            const progressContainer = document.querySelector('.upload-progress');
            const progressBar = document.querySelector('.progress-bar');
            const progressText = document.querySelector('.progress-text');
            const progressPercentage = document.querySelector('.progress-percentage');
            const progressInfo = document.querySelector('.progress-info');
            const fileList = document.getElementById('fileList');

            // Function to format file size
            function formatFileSize(bytes) {
                if (bytes >= 1073741824) {
                    return (bytes / 1073741824).toFixed(2) + ' GB';
                } else if (bytes >= 1048576) {
                    return (bytes / 1048576).toFixed(2) + ' MB';
                } else if (bytes >= 1024) {
                    return (bytes / 1024).toFixed(2) + ' KB';
                } else {
                    return bytes + ' bytes';
                }
            }

            // Function to update file list
            function updateFileList() {
                fileList.innerHTML = '';
                const files = fileInput.files;
                
                if (files.length > 0) {
                    const ul = document.createElement('ul');
                    ul.className = 'list-group';
                    
                    for (let file of files) {
                        const li = document.createElement('li');
                        li.className = 'list-group-item d-flex justify-content-between align-items-center';
                        
                        const nameSpan = document.createElement('span');
                        nameSpan.textContent = file.name;
                        
                        const sizeSpan = document.createElement('span');
                        sizeSpan.className = 'badge badge-primary badge-pill';
                        sizeSpan.textContent = formatFileSize(file.size);
                        
                        li.appendChild(nameSpan);
                        li.appendChild(sizeSpan);
                        ul.appendChild(li);
                        
                        // Check file size
                        if (file.size > 100 * 1024 * 1024) { // 100MB
                            const warning = document.createElement('div');
                            warning.className = 'alert alert-warning mt-2';
                            warning.textContent = `Warning: ${file.name} exceeds the 100MB size limit and will not be uploaded.`;
                            ul.appendChild(warning);
                        }
                    }
                    
                    fileList.appendChild(ul);
                }
            }

            // Drag and drop handlers
            dropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });

            dropzone.addEventListener('dragleave', () => {
                dropzone.classList.remove('dragover');
            });

            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
                fileInput.files = e.dataTransfer.files;
                updateFileList();
            });

            dropzone.addEventListener('click', () => {
                fileInput.click();
            });

            fileInput.addEventListener('change', updateFileList);

            // Upload handler
            uploadButton.addEventListener('click', async () => {
                const files = fileInput.files;
                if (files.length === 0) return;

                const formData = new FormData();
                formData.append('action', 'upload_file');
                const folder = '<?php echo $currentFolder; ?>';
                formData.append('folder', folder.replace(/ /g, '+'));
                
                let totalSize = 0;
                let validFiles = 0;
                
                for (let file of files) {
                    if (file.size <= 100 * 1024 * 1024) { // 100MB limit
                        formData.append('files[]', file);
                        totalSize += file.size;
                        validFiles++;
                    }
                }
                
                if (validFiles === 0) {
                    alert('No valid files to upload. Please ensure files are under 100MB.');
                    return;
                }

                uploadButton.disabled = true;
                progressContainer.style.display = 'block';
                cancelUpload.style.display = 'inline-block';
                
                try {
                    currentXHR = new XMLHttpRequest();
                    
                    // Setup progress handler
                    currentXHR.upload.addEventListener('progress', (event) => {
                        if (event.lengthComputable) {
                            const percent = Math.round((event.loaded / event.total) * 100);
                            const loadedSize = formatFileSize(event.loaded);
                            const totalSize = formatFileSize(event.total);

                            progressBar.style.width = percent + '%';
                            progressBar.setAttribute('aria-valuenow', percent);
                            progressBar.textContent = percent + '%';
                            
                            progressText.textContent = 'Uploading...';
                            progressPercentage.textContent = percent + '%';
                            progressInfo.textContent = `${loadedSize} of ${totalSize}`;
                        }
                    });
                    
                    // Setup completion handler
                    currentXHR.addEventListener('load', () => {
                        try {
                            const result = JSON.parse(currentXHR.responseText);
                            if (result.success) {
                                progressBar.classList.remove('progress-bar-animated');
                                progressBar.classList.add('bg-success');
                                progressText.textContent = 'Upload Complete!';
                                setTimeout(() => {
                                    location.reload();
                                }, 1000);
                            } else {
                                progressBar.classList.remove('progress-bar-animated');
                                progressBar.classList.add('bg-danger');
                                progressText.textContent = 'Upload Failed';
                                progressInfo.textContent = result.message;
                                alert(result.message);
                            }
                        } catch (error) {
                            progressBar.classList.add('bg-danger');
                            progressText.textContent = 'Upload Failed';
                            progressInfo.textContent = 'Invalid server response';
                            alert('Upload failed: Invalid server response');
                        }
                    });
                    
                    // Setup error handler
                    currentXHR.addEventListener('error', () => {
                        progressBar.classList.remove('progress-bar-animated');
                        progressBar.classList.add('bg-danger');
                        progressText.textContent = 'Upload Failed';
                        progressInfo.textContent = 'Network error occurred';
                        alert('Upload failed: Network error');
                    });
                    
                    // Setup abort handler
                    currentXHR.addEventListener('abort', () => {
                        progressBar.classList.remove('progress-bar-animated');
                        progressBar.classList.add('bg-warning');
                        progressText.textContent = 'Upload Cancelled';
                        progressInfo.textContent = 'Upload was cancelled by user';
                    });
                    
                    // Send the request
                    currentXHR.open('POST', 'handlers/file_operations.php');
                    currentXHR.send(formData);
                    
                } catch (error) {
                    progressBar.classList.remove('progress-bar-animated');
                    progressBar.classList.add('bg-danger');
                    progressText.textContent = 'Upload Failed';
                    progressInfo.textContent = error.message;
                    alert('Upload failed: ' + error.message);
                }
            });

            // Cancel upload handler
            cancelUpload.addEventListener('click', () => {
                if (currentXHR) {
                    currentXHR.abort();
                    currentXHR = null;
                }
                uploadButton.disabled = false;
                resetProgress();
            });

            // Modal close handler
            $('#uploadModal').on('hidden.bs.modal', function () {
                if (currentXHR) {
                    currentXHR.abort();
                    currentXHR = null;
                }
                uploadButton.disabled = false;
                resetProgress();
                fileInput.value = '';
                fileList.innerHTML = '';
            });

            // New folder functionality
            const createFolderButton = document.getElementById('createFolderButton');
            const newFolderForm = document.getElementById('newFolderForm');

            createFolderButton.addEventListener('click', async () => {
                const folderName = document.getElementById('folderName').value;
                if (!folderName) return;

                const formData = new FormData();
                formData.append('action', 'create_folder');
                formData.append('folder_name', folderName);
                formData.append('folder', '<?php echo urlencode($currentFolder); ?>');

                try {
                    const response = await fetch('handlers/file_operations.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    alert(result.message);
                    
                    if (result.success) {
                        location.reload();
                    }
                } catch (error) {
                    alert('Failed to create folder: ' + error.message);
                }
            });

            // Delete item functionality
            let deleteItemPath = '';
            
            document.addEventListener('click', function(event) {
                const deleteButton = event.target.closest('.delete-item');
                if (deleteButton) {
                    const itemName = deleteButton.dataset.name;
                    deleteItemPath = deleteButton.dataset.path;
                    const itemType = deleteButton.dataset.type;

                    document.getElementById('deleteItemName').textContent = itemName;
                    document.getElementById('deleteWarning').textContent = itemType === 'directory' ? 
                        'This action will delete the entire directory and all its contents.' : 
                        'This action will delete the file.';

                    $('#deleteModal').modal('show');
                }
            });

            document.getElementById('confirmDelete').addEventListener('click', async () => {
                if (!deleteItemPath) return;
                
                try {
                    const response = await fetch('handlers/file_operations.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=delete_item&item_path=' + encodeURIComponent(deleteItemPath)
                    });
                    
                    const result = await response.json();
                    $('#deleteModal').modal('hide');
                    
                    if (result.success) {
                        location.reload();
                    } else {
                        alert(result.message);
                    }
                } catch (error) {
                    alert('Failed to delete item: ' + error.message);
                }
            });

            // Preview functionality
            document.addEventListener('click', function(event) {
                const previewButton = event.target.closest('.preview-file');
                if (previewButton) {
                    const fileUrl = previewButton.dataset.url;
                    const fileName = previewButton.dataset.name;
                    const fileType = previewButton.dataset.type;

                    $('#previewModal').modal('show');
                    $('#previewModal .modal-title').text('Preview: ' + fileName);
                    $('#downloadFile').attr('href', fileUrl);

                    const previewContent = $('#previewModal .preview-content');
                    const previewLoading = $('#previewModal .preview-loading');
                    
                    // Show loading state
                    previewContent.hide();
                    previewLoading.show();

                    // Handle different file types
                    if (fileType.startsWith('image/')) {
                        // Image preview
                        const img = new Image();
                        img.onload = function() {
                            previewLoading.hide();
                            previewContent.empty().append(img).show();
                        };
                        img.onerror = function() {
                            previewLoading.hide();
                            previewContent.html('<div class="alert alert-danger">Failed to load image preview.</div>').show();
                        };
                        img.src = fileUrl;
                        img.className = 'preview-image-full';
                        img.alt = fileName;
                    } else if (fileType === 'application/pdf') {
                        // PDF preview
                        const iframe = document.createElement('iframe');
                        iframe.src = fileUrl;
                        iframe.className = 'preview-pdf';
                        previewLoading.hide();
                        previewContent.empty().append(iframe).show();
                    } else if (fileType.startsWith('text/') || 
                             fileType.includes('javascript') || 
                             fileType.includes('json')) {
                        // Text preview
                        fetch(fileUrl)
                            .then(response => response.text())
                            .then(text => {
                                const pre = document.createElement('pre');
                                pre.className = 'preview-text';
                                pre.textContent = text;
                                previewLoading.hide();
                                previewContent.empty().append(pre).show();
                            })
                            .catch(error => {
                                previewLoading.hide();
                                previewContent.html('<div class="alert alert-danger">Failed to load text preview: ' + error.message + '</div>').show();
                            });
                    } else {
                        // Unsupported file type
                        previewLoading.hide();
                        previewContent.html(`
                            <div class="text-center">
                                <i class="feather icon-file h1 text-muted"></i>
                                <p class="mt-3">Preview not available for this file type (${fileType})</p>
                                <a href="${fileUrl}" class="btn btn-primary mt-2" download>
                                    <i class="feather icon-download"></i> Download File
                                </a>
                            </div>
                        `).show();
                    }
                }
            });

            // Add rename button to file cards
            const fileCards = document.querySelectorAll('.file-card');
            
            fileCards.forEach(card => {
                const actionDiv = card.querySelector('.file-info > div');
                const filePath = card.querySelector('.delete-item').dataset.path;
                const fileName = card.querySelector('.delete-item').dataset.name;
                
                // Add rename button
                const renameBtn = document.createElement('button');
                renameBtn.className = 'btn btn-sm btn-warning mr-2';
                renameBtn.innerHTML = '<i class="feather icon-edit-2"></i> Rename';
                renameBtn.onclick = (e) => {
                    e.preventDefault();
                    showRenameModal(filePath, fileName);
                };
                
                // Insert rename button before delete button
                actionDiv.insertBefore(renameBtn, actionDiv.lastChild);
            });
            
            // Rename functionality
            function showRenameModal(path, currentName) {
                const modal = $('#renameModal');
                const newNameInput = modal.find('#newName');
                const oldPathInput = modal.find('#oldPath');
                
                newNameInput.val(currentName);
                oldPathInput.val(path);
                
                modal.modal('show');
                newNameInput.select();
            }
            
            document.getElementById('confirmRename').addEventListener('click', async () => {
                const modal = $('#renameModal');
                const newName = modal.find('#newName').val().trim();
                const oldPath = modal.find('#oldPath').val();
                
                if (!newName) return;
                
                try {
                    const response = await fetch('handlers/file_operations.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=rename_item&old_path=' + encodeURIComponent(oldPath) + 
                              '&new_name=' + encodeURIComponent(newName)
                    });
                    
                    const result = await response.json();
                    modal.modal('hide');
                    
                    if (result.success) {
                        location.reload();
                    } else {
                        alert(result.message);
                    }
                } catch (error) {
                    alert('Failed to rename item: ' + error.message);
                }
            });
            
            // Handle enter key in rename modal
            document.getElementById('newName').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('confirmRename').click();
                }
            });

            // Bulk actions functionality
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = bulkActions.querySelector('.selected-count');
            let selectedItems = new Set();
            
            // Add checkboxes to file cards
            document.querySelectorAll('.file-card').forEach(card => {
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'file-select';
                card.insertBefore(checkbox, card.firstChild);
                
                checkbox.addEventListener('change', () => {
                    const itemPath = card.querySelector('.delete-item').dataset.path;
                    if (checkbox.checked) {
                        selectedItems.add(itemPath);
                        card.classList.add('selected');
                    } else {
                        selectedItems.delete(itemPath);
                        card.classList.remove('selected');
                    }
                    updateBulkActions();
                });
            });
            
            // Update bulk actions bar
            function updateBulkActions() {
                const count = selectedItems.size;
                selectedCount.textContent = `${count} item${count !== 1 ? 's' : ''} selected`;
                bulkActions.classList.toggle('show', count > 0);
            }
            
            // Cancel selection
            document.getElementById('cancelSelection').addEventListener('click', () => {
                selectedItems.clear();
                document.querySelectorAll('.file-select').forEach(checkbox => {
                    checkbox.checked = false;
                });
                document.querySelectorAll('.file-card').forEach(card => {
                    card.classList.remove('selected');
                });
                updateBulkActions();
            });
            
            // Bulk delete
            document.getElementById('bulkDelete').addEventListener('click', () => {
                const modal = document.getElementById('bulkDeleteModal');
                const itemsList = document.getElementById('selectedItemsList');
                itemsList.innerHTML = '<ul class="list-group mt-3">' + 
                    Array.from(selectedItems).map(path => 
                        `<li class="list-group-item">${path}</li>`
                    ).join('') + '</ul>';
                $(modal).modal('show');
            });
            
            document.getElementById('confirmBulkDelete').addEventListener('click', async () => {
                try {
                    const promises = Array.from(selectedItems).map(path => 
                        fetch('handlers/file_operations.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=delete_item&item_path=${encodeURIComponent(path)}`
                        }).then(r => r.json())
                    );
                    
                    const results = await Promise.all(promises);
                    const success = results.every(r => r.success);
                    
                    if (success) {
                        location.reload();
                    } else {
                        const errors = results.filter(r => !r.success).map(r => r.message);
                        alert('Some items could not be deleted:\n' + errors.join('\n'));
                    }
                } catch (error) {
                    alert('Failed to delete items: ' + error.message);
                }
                $('#bulkDeleteModal').modal('hide');
            });
            
            // Bulk move/copy
            let isCopyOperation = false;
            
            document.getElementById('bulkMove').addEventListener('click', () => {
                isCopyOperation = false;
                document.getElementById('confirmMove').textContent = 'Move Here';
                $('#moveModal').modal('show');
            });
            
            document.getElementById('bulkCopy').addEventListener('click', () => {
                isCopyOperation = true;
                document.getElementById('confirmMove').textContent = 'Copy Here';
                $('#moveModal').modal('show');
            });
            
            document.getElementById('confirmMove').addEventListener('click', async () => {
                const destination = document.getElementById('destinationFolder').value;
                const action = isCopyOperation ? 'copy_items' : 'move_items';
                
                try {
                    const response = await fetch('handlers/file_operations.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: action,
                            items: Array.from(selectedItems),
                            destination: destination
                        })
                    });
                    
                    const result = await response.json();
                    $('#moveModal').modal('hide');
                    
                    if (result.success) {
                        location.reload();
                    } else {
                        alert(result.message);
                    }
                } catch (error) {
                    alert(`Failed to ${isCopyOperation ? 'copy' : 'move'} items: ` + error.message);
                }
            });

            // Sorting and filtering functionality
            const fileContainer = document.querySelector('.row');
            let currentFilter = 'all';
            let currentSort = 'name-asc';
            let isGridView = true;
            
            // Get all file cards
            const getAllCards = () => Array.from(document.querySelectorAll('.col-sm-6'));
            
            // Filter functions
            const filterFunctions = {
                all: () => true,
                image: card => card.querySelector('[data-mime-type]')?.dataset.mimeType.startsWith('image/'),
                document: card => {
                    const mimeType = card.querySelector('[data-mime-type]')?.dataset.mimeType;
                    return mimeType?.includes('pdf') || 
                           mimeType?.includes('document') || 
                           mimeType?.includes('text/');
                },
                archive: card => {
                    const mimeType = card.querySelector('[data-mime-type]')?.dataset.mimeType;
                    return mimeType?.includes('zip') || 
                           mimeType?.includes('compressed') || 
                           mimeType?.includes('archive');
                },
                folder: card => card.querySelector('[data-is-dir="true"]'),
                today: card => {
                    const modified = new Date(card.querySelector('.file-info p:nth-child(2)').textContent.split(': ')[1]);
                    const today = new Date();
                    return modified.toDateString() === today.toDateString();
                },
                week: card => {
                    const modified = new Date(card.querySelector('.file-info p:nth-child(2)').textContent.split(': ')[1]);
                    const weekAgo = new Date();
                    weekAgo.setDate(weekAgo.getDate() - 7);
                    return modified >= weekAgo;
                },
                month: card => {
                    const modified = new Date(card.querySelector('.file-info p:nth-child(2)').textContent.split(': ')[1]);
                    const monthAgo = new Date();
                    monthAgo.setMonth(monthAgo.getMonth() - 1);
                    return modified >= monthAgo;
                }
            };
            
            // Sort functions
            const sortFunctions = {
                'name-asc': (a, b) => {
                    const aName = a.querySelector('.file-name').textContent;
                    const bName = b.querySelector('.file-name').textContent;
                    return aName.localeCompare(bName);
                },
                'name-desc': (a, b) => {
                    const aName = a.querySelector('.file-name').textContent;
                    const bName = b.querySelector('.file-name').textContent;
                    return bName.localeCompare(aName);
                },
                'date-asc': (a, b) => {
                    const aDate = new Date(a.querySelector('.file-info p:nth-child(2)').textContent.split(': ')[1]);
                    const bDate = new Date(b.querySelector('.file-info p:nth-child(2)').textContent.split(': ')[1]);
                    return aDate - bDate;
                },
                'date-desc': (a, b) => {
                    const aDate = new Date(a.querySelector('.file-info p:nth-child(2)').textContent.split(': ')[1]);
                    const bDate = new Date(b.querySelector('.file-info p:nth-child(2)').textContent.split(': ')[1]);
                    return bDate - aDate;
                },
                'size-asc': (a, b) => {
                    const aSize = parseInt(a.querySelector('.file-info p:first-child')?.textContent.match(/\d+/)?.[0] || 0);
                    const bSize = parseInt(b.querySelector('.file-info p:first-child')?.textContent.match(/\d+/)?.[0] || 0);
                    return aSize - bSize;
                },
                'size-desc': (a, b) => {
                    const aSize = parseInt(a.querySelector('.file-info p:first-child')?.textContent.match(/\d+/)?.[0] || 0);
                    const bSize = parseInt(b.querySelector('.file-info p:first-child')?.textContent.match(/\d+/)?.[0] || 0);
                    return bSize - aSize;
                }
            };
            
            // Apply filter and sort
            function applyFilterAndSort() {
                const cards = getAllCards();
                const filteredCards = cards.filter(filterFunctions[currentFilter]);
                
                // Sort filtered cards
                filteredCards.sort(sortFunctions[currentSort]);
                
                // Clear container
                while (fileContainer.firstChild) {
                    fileContainer.removeChild(fileContainer.firstChild);
                }
                
                // Append sorted and filtered cards
                filteredCards.forEach(card => fileContainer.appendChild(card));
                
                // Update view mode
                updateViewMode();
                
                // Show message if no results
                if (filteredCards.length === 0) {
                    const noResults = document.createElement('div');
                    noResults.className = 'col-12 text-center py-5';
                    noResults.innerHTML = `
                        <i class="feather icon-search h1 text-muted"></i>
                        <h4 class="mt-3">No items found</h4>
                        <p class="text-muted">Try changing your filter criteria</p>
                    `;
                    fileContainer.appendChild(noResults);
                }
            }
            
            // Filter click handlers
            document.querySelectorAll('[data-filter]').forEach(filter => {
                filter.addEventListener('click', (e) => {
                    e.preventDefault();
                    currentFilter = e.target.dataset.filter;
                    applyFilterAndSort();
                    
                    // Update filter button text
                    const filterBtn = document.querySelector('[data-toggle="dropdown"]');
                    filterBtn.innerHTML = `<i class="feather icon-filter"></i> ${e.target.textContent}`;
                });
            });
            
            // Sort click handlers
            document.querySelectorAll('[data-sort]').forEach(sort => {
                sort.addEventListener('click', (e) => {
                    e.preventDefault();
                    currentSort = e.target.dataset.sort;
                    applyFilterAndSort();
                    
                    // Update sort button text
                    const sortBtn = document.querySelector('[data-toggle="dropdown"]:nth-child(2)');
                    sortBtn.innerHTML = `<i class="feather icon-sort"></i> ${e.target.textContent}`;
                });
            });
            
            // Quick filter functionality
            const quickFilter = document.getElementById('quickFilter');
            let quickFilterTimeout;
            
            quickFilter.addEventListener('input', () => {
                clearTimeout(quickFilterTimeout);
                quickFilterTimeout = setTimeout(() => {
                    const searchTerm = quickFilter.value.toLowerCase();
                    const cards = getAllCards();
                    
                    cards.forEach(card => {
                        const fileName = card.querySelector('.file-name').textContent.toLowerCase();
                        const fileInfo = card.querySelector('.file-info').textContent.toLowerCase();
                        const matches = fileName.includes(searchTerm) || fileInfo.includes(searchTerm);
                        card.style.display = matches ? '' : 'none';
                    });
                }, 300);
            });
            
            // Clear filter
            document.getElementById('clearFilter').addEventListener('click', () => {
                quickFilter.value = '';
                currentFilter = 'all';
                currentSort = 'name-asc';
                applyFilterAndSort();
                
                // Reset button texts
                document.querySelector('[data-toggle="dropdown"]').innerHTML = '<i class="feather icon-filter"></i> Filter';
                document.querySelector('[data-toggle="dropdown"]:nth-child(2)').innerHTML = '<i class="feather icon-sort"></i> Sort By';
            });
            
            // View mode toggle
            function updateViewMode() {
                const cards = getAllCards();
                cards.forEach(card => {
                    card.className = isGridView ? 'col-sm-6 col-md-4 col-lg-3 mb-4' : 'col-12 mb-2';
                    if (!isGridView) {
                        const cardBody = card.querySelector('.card-body');
                        cardBody.className = 'card-body d-flex align-items-center';
                        cardBody.style.textAlign = 'left';
                    }
                });
                
                const toggleBtn = document.getElementById('toggleViewMode');
                toggleBtn.innerHTML = isGridView ? 
                    '<i class="feather icon-list"></i>' : 
                    '<i class="feather icon-grid"></i>';
            }
            
            document.getElementById('toggleViewMode').addEventListener('click', () => {
                isGridView = !isGridView;
                updateViewMode();
            });

            // File details panel functionality
            const detailsPanel = document.querySelector('.file-details-panel');
            const mainContainer = document.querySelector('.pcoded-main-container');
            let currentDetailsFile = null;
            
            // Show file details
            async function showFileDetails(file) {
                const fileCard = file.closest('.file-card');
                if (!fileCard) return;
                
                currentDetailsFile = fileCard;
                
                const fileName = fileCard.querySelector('.file-name').textContent;
                const filePath = fileCard.querySelector('.delete-item').dataset.path;
                const fileType = fileCard.dataset.mimeType;
                const fileUrl = fileCard.querySelector('a').href;
                
                // Update details panel
                detailsPanel.querySelector('.details-filename').textContent = fileName;
                detailsPanel.querySelector('.details-type').textContent = fileType || 'Unknown';
                detailsPanel.querySelector('.details-path').textContent = filePath;
                
                const fileIcon = fileCard.querySelector('.file-icon').className;
                detailsPanel.querySelector('.file-icon-large').className = fileIcon + ' file-icon-large';
                
                // Get additional file info via AJAX
                try {
                    const response = await fetch('handlers/file_operations.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'get_file_info',
                            path: filePath
                        })
                    });
                    
                    const fileInfo = await response.json();
                    if (fileInfo.success) {
                        detailsPanel.querySelector('.details-size').textContent = formatFileSize(fileInfo.size);
                        detailsPanel.querySelector('.details-created').textContent = new Date(fileInfo.created * 1000).toLocaleString();
                        detailsPanel.querySelector('.details-modified').textContent = new Date(fileInfo.modified * 1000).toLocaleString();
                        detailsPanel.querySelector('.details-permissions').textContent = fileInfo.permissions;
                        
                        // Show preview if possible
                        const previewContainer = detailsPanel.querySelector('.preview-container');
                        previewContainer.innerHTML = '';
                        
                        if (fileType.startsWith('image/')) {
                            const img = document.createElement('img');
                            img.src = fileUrl;
                            img.alt = fileName;
                            previewContainer.appendChild(img);
                        } else if (fileType.startsWith('text/') || fileType.includes('javascript') || fileType.includes('json')) {
                            const pre = document.createElement('pre');
                            const text = await fetch(fileUrl).then(r => r.text());
                            pre.textContent = text.slice(0, 500) + (text.length > 500 ? '...' : '');
                            previewContainer.appendChild(pre);
                        } else {
                            previewContainer.innerHTML = '<i class="feather icon-file h1 text-muted"></i>';
                        }
                    }
                } catch (error) {
                    console.error('Failed to get file info:', error);
                }
                
                // Setup action buttons
                detailsPanel.querySelector('.details-download').onclick = () => {
                    const link = document.createElement('a');
                    link.href = fileUrl;
                    link.download = fileName;
                    link.click();
                };
                
                detailsPanel.querySelector('.details-preview-btn').onclick = () => {
                    const previewBtn = fileCard.querySelector('.preview-file');
                    if (previewBtn) previewBtn.click();
                };
                
                detailsPanel.querySelector('.details-rename').onclick = () => {
                    const renameBtn = fileCard.querySelector('.rename-item');
                    if (renameBtn) renameBtn.click();
                };
                
                detailsPanel.querySelector('.details-delete').onclick = () => {
                    const deleteBtn = fileCard.querySelector('.delete-item');
                    if (deleteBtn) deleteBtn.click();
                };
                
                // Show panel
                detailsPanel.classList.add('show');
                mainContainer.classList.add('details-open');
            }
            
            // Close details panel
            function closeDetails() {
                detailsPanel.classList.remove('show');
                mainContainer.classList.remove('details-open');
                currentDetailsFile = null;
            }
            
            // Add info button to file cards
            document.querySelectorAll('.file-card').forEach(card => {
                const actionDiv = card.querySelector('.file-info > div');
                const infoBtn = document.createElement('button');
                infoBtn.className = 'btn btn-sm btn-secondary mr-2';
                infoBtn.innerHTML = '<i class="feather icon-info"></i>';
                infoBtn.onclick = (e) => {
                    e.preventDefault();
                    showFileDetails(card);
                };
                actionDiv.insertBefore(infoBtn, actionDiv.firstChild);
            });
            
            // Close panel button
            detailsPanel.querySelector('#closeDetails').onclick = closeDetails;
            
            // Close panel on escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && detailsPanel.classList.contains('show')) {
                    closeDetails();
                }
            });
            
            // Update details when file is modified
            document.addEventListener('fileModified', (e) => {
                if (currentDetailsFile && e.detail.file === currentDetailsFile) {
                    showFileDetails(currentDetailsFile);
                }
            });

            // View mode functionality
            document.addEventListener('DOMContentLoaded', function() {
                const container = document.querySelector('.row');
                if (!container) return; // Exit if container doesn't exist
                
                // Create view mode toggle buttons
                const viewModeToggle = document.createElement('div');
                viewModeToggle.className = 'view-mode-toggle';
                viewModeToggle.innerHTML = `
                    <button type="button" data-view="grid" title="Grid View">
                        <i class="feather icon-grid"></i>
                    </button>
                    <button type="button" data-view="list" title="List View">
                        <i class="feather icon-list"></i>
                    </button>
                `;
                
                // Find and replace the old toggle button
                const oldToggle = document.getElementById('toggleViewMode');
                if (oldToggle && oldToggle.parentNode) {
                    oldToggle.parentNode.replaceChild(viewModeToggle, oldToggle);
                } else {
                    // If old toggle doesn't exist, append to a suitable container
                    const buttonContainer = document.querySelector('.btn-group');
                    if (buttonContainer) {
                        buttonContainer.appendChild(viewModeToggle);
                    }
                }
                
                // Get saved view mode preference
                let currentView = localStorage.getItem('fileViewMode') || 'grid';
                
                // Apply initial view mode
                applyViewMode(currentView);
                
                // View mode toggle handlers
                viewModeToggle.querySelectorAll('button').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const view = btn.dataset.view;
                        applyViewMode(view);
                        localStorage.setItem('fileViewMode', view);
                    });
                });
                
                function applyViewMode(view) {
                    // Update toggle buttons
                    viewModeToggle.querySelectorAll('button').forEach(btn => {
                        btn.classList.toggle('active', btn.dataset.view === view);
                    });
                    
                    // Update container class
                    container.classList.remove('view-mode-grid', 'view-mode-list');
                    container.classList.add(`view-mode-${view}`);
                    
                    // Update card classes
                    const cards = document.querySelectorAll('.file-card');
                    cards.forEach(card => {
                        if (!card) return; // Skip if card doesn't exist
                        
                        const col = card.closest('[class*="col-"]');
                        if (!col) return; // Skip if column doesn't exist
                        
                        if (view === 'list') {
                            col.className = 'col-12';
                            card.classList.add('mb-2');
                        } else {
                            col.className = 'col-sm-6 col-md-4 col-lg-3 mb-4';
                            card.classList.remove('mb-2');
                        }
                        
                        // Update layout
                        const cardBody = card.querySelector('.card-body');
                        if (!cardBody) return; // Skip if card body doesn't exist
                        
                        if (view === 'list') {
                            const fileIcon = cardBody.querySelector('.file-icon, .preview-image');
                            const fileName = cardBody.querySelector('.file-name');
                            const fileInfo = cardBody.querySelector('.file-info');
                            const fileType = card.querySelector('.file-type-badge');
                            
                            // Store original content if not already stored
                            if (!card.dataset.originalContent) {
                                card.dataset.originalContent = cardBody.innerHTML;
                            }
                            
                            // Rearrange elements for list view
                            cardBody.innerHTML = '';
                            if (fileIcon) cardBody.appendChild(fileIcon.cloneNode(true));
                            if (fileName) cardBody.appendChild(fileName.cloneNode(true));
                            if (fileType) cardBody.appendChild(fileType.cloneNode(true));
                            if (fileInfo) cardBody.appendChild(fileInfo.cloneNode(true));
                            
                            cardBody.className = 'card-body d-flex align-items-center';
                        } else {
                            // Restore original content for grid view
                            if (card.dataset.originalContent) {
                                cardBody.innerHTML = card.dataset.originalContent;
                            }
                            cardBody.className = 'card-body text-center';
                        }
                    });
                }
                
                // Update view mode when window is resized
                let resizeTimeout;
                window.addEventListener('resize', () => {
                    if (!resizeTimeout) {
                        resizeTimeout = setTimeout(() => {
                            applyViewMode(currentView);
                            resizeTimeout = null;
                        }, 250);
                    }
                });
            });
        });
    </script>
</body>

</html>