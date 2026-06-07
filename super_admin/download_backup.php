<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

$file = isset($_GET['file']) ? basename($_GET['file']) : '';
if (empty($file) || !preg_match('/^backup_\d{8}_\d{6}\.sql$/', $file)) {
    http_response_code(400);
    exit('Invalid file');
}

$path = __DIR__ . '/../backups/' . $file;
if (!file_exists($path)) {
    http_response_code(404);
    exit('File not found');
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($path);
exit();
