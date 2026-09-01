<?php
/**
 * Cleanup orphaned temp files.
 * Deletes passport/photo files in uploads/temp/ older than 1 hour.
 * Run via cron: php api/umrah/cleanup_temp_files.php
 */

require_once __DIR__ . '/../../includes/db.php';

$tempDir = __DIR__ . '/../../uploads/temp';
if (!is_dir($tempDir)) {
    echo "Temp directory does not exist.\n";
    exit(0);
}

$files = glob($tempDir . '/passport_*');
$now = time();
$deleted = 0;

foreach ($files as $file) {
    if (is_file($file) && ($now - filemtime($file)) > 3600) {
        if (@unlink($file)) {
            $deleted++;
        }
    }
}

echo "Cleaned up {$deleted} orphaned temp file(s).\n";
