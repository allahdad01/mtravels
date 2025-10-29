<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

if (!isset($_GET['file'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('File parameter missing');
}

$filename = basename($_GET['file']); // Prevent directory traversal
$filepath = __DIR__ . '/reports/' . $filename;

// Check if file exists
if (!file_exists($filepath)) {
    header('HTTP/1.1 404 Not Found');
    exit('File not found');
}

// Check if file is in reports directory
$realpath = realpath($filepath);
$reports_dir = realpath(__DIR__ . '/reports');

if (strpos($realpath, $reports_dir) !== 0) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

// Set appropriate headers for download
$content_type = 'application/octet-stream'; // Default

// Determine content type based on file extension
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
switch ($extension) {
    case 'pdf':
        $content_type = 'application/pdf';
        break;
    case 'xlsx':
    case 'xls':
        $content_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        break;
    case 'csv':
        $content_type = 'text/csv';
        break;
    case 'txt':
        $content_type = 'text/plain';
        break;
}

header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Clear output buffer
if (ob_get_level()) {
    ob_clean();
}

// Output file content
readfile($filepath);

// Optionally delete the file after download to save space
// unlink($filepath);

exit;
?>