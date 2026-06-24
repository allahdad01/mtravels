<?php
/**
 * Automated Database Backup Script
 * 
 * Creates a database backup and optionally:
 * - Sends it via email
 * - Uploads to Google Drive
 * - Uploads to OneDrive
 * 
 * Schedule: Daily at 12:00 AM
 * Windows: schtasks /create /sc daily /tn "MTravelsAutoBackup" /tr "php C:\xampp\htdocs\mtravels\cron\auto_backup.php" /st 00:00
 * Linux:   0 0 * * * /usr/bin/php /var/www/mtravels/cron/auto_backup.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli' && !isset($_GET['force'])) {
    die("This script must be run via CLI or with ?force=1 parameter.\n");
}

// Error reporting for cron
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$startTime = microtime(true);
$log = [];

function autoBackupLog(&$log, $msg) {
    $log[] = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    echo $msg . "\n";
}

// Bootstrap
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Read platform settings
$settings = [];
try {
    $stmt = $pdo->prepare("SELECT `key`, `value` FROM platform_settings");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['key']] = $row['value'];
    }
} catch (Exception $e) {
    autoBackupLog($log, 'ERROR: Failed to read platform settings: ' . $e->getMessage());
    exit(1);
}

$autoBackupEnabled = $settings['auto_backup_enabled'] ?? '0';
if ($autoBackupEnabled !== '1' && !isset($_GET['force'])) {
    autoBackupLog($log, 'Auto backup is disabled. Skip. (Enable in Backup Settings)');
    exit(0);
}

autoBackupLog($log, 'Starting automated database backup...');

// ── 1. Create Backup ───────────────────────────────────
$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$timestamp = date('Ymd_His');
$filename = "auto_backup_{$timestamp}.sql";
$absPath = $backupDir . '/' . $filename;

try {
    // Use PDO-based backup (same logic as backup_management.php)
    $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdoBackup = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
    ]);

    $fh = fopen($absPath, 'w');
    if (!$fh) {
        throw new Exception("Failed to open file for writing: $absPath");
    }

    fwrite($fh, "-- MTravels Automated Database Backup\n");
    fwrite($fh, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($fh, "-- Host: " . DB_SERVER . "\n");
    fwrite($fh, "-- Database: " . DB_NAME . "\n");
    fwrite($fh, "SET NAMES utf8mb4;\n\n");

    $tables = $pdoBackup->query("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tables as $t) {
        $table = $t['TABLE_NAME'];
        $tableType = $t['TABLE_TYPE'];
        $isView = ($tableType === 'VIEW');
        $tableId = '`' . str_replace('`', '``', $table) . '`';

        $createResult = $pdoBackup->query("SHOW CREATE TABLE {$tableId}");
        if (!$createResult) continue;
        $createRow = $createResult->fetch(PDO::FETCH_NUM);

        $dropSql = $isView ? "DROP VIEW IF EXISTS {$tableId};\n" : "DROP TABLE IF EXISTS {$tableId};\n";
        fwrite($fh, $dropSql . $createRow[1] . ";\n\n");

        // Views don't store data; skip INSERT
        if ($isView) continue;

        $selectStmt = $pdoBackup->prepare("SELECT * FROM {$tableId}");
        $selectStmt->execute();
        while ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
            $cols = array_map(function($k) { return '`' . str_replace('`', '``', $k) . '`'; }, array_keys($row));
            $vals = array_map(function($v) {
                if ($v === null) return 'NULL';
                return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$v) . "'";
            }, array_values($row));
            fwrite($fh, sprintf("INSERT INTO %s (%s) VALUES (%s);\n", $tableId, implode(',', $cols), implode(',', $vals)));
        }
        fwrite($fh, "\n");
    }
    fclose($fh);

    if (!file_exists($absPath) || filesize($absPath) === 0) {
        throw new Exception("Backup file is empty");
    }

    $fileSize = filesize($absPath);
    autoBackupLog($log, "Backup created: {$filename} (" . round($fileSize / 1024 / 1024, 2) . " MB)");
} catch (Exception $e) {
    autoBackupLog($log, 'ERROR: Backup creation failed: ' . $e->getMessage());
    exit(1);
}

// ── 2. Send via Email ──────────────────────────────────
$emailEnabled = $settings['backup_email_enabled'] ?? '0';
$emailTo = $settings['backup_email_to'] ?? '';

if ($emailEnabled === '1' && !empty($emailTo)) {
    autoBackupLog($log, 'Sending backup via email to: ' . $emailTo);
    try {
        require_once __DIR__ . '/../includes/functions.php';

        $platformSettings = getPlatformSettingsFormatted();
        if (!empty($platformSettings['smtp_enabled']) && !empty($platformSettings['smtp_host'])) {
            // Send using the platform SMTP
            $sent = sendEmail(
                $emailTo,
                'Database Backup - ' . date('Y-m-d'),
                '<h2>Automated Database Backup</h2>' .
                '<p>Backup file: <strong>' . $filename . '</strong></p>' .
                '<p>Size: <strong>' . round($fileSize / 1024 / 1024, 2) . ' MB</strong></p>' .
                '<p>Date: <strong>' . date('Y-m-d H:i:s') . '</strong></p>',
                true,
                'backup',
                '',
                null,
                [['path' => $absPath, 'name' => $filename]]
            );
            if ($sent) {
                autoBackupLog($log, 'Email sent successfully');
            } else {
                autoBackupLog($log, 'WARNING: Failed to send email (SMTP may be disabled)');
            }
        } else {
            autoBackupLog($log, 'WARNING: SMTP not configured. Email not sent.');
        }
    } catch (Exception $e) {
        autoBackupLog($log, 'WARNING: Email sending failed: ' . $e->getMessage());
    }
} else {
    autoBackupLog($log, 'Email backup disabled or no recipient configured. Skip.');
}

// ── 3. Upload to Google Drive ──────────────────────────
$gdEnabled = $settings['backup_gd_enabled'] ?? '0';
$gdRefreshToken = $settings['backup_gd_refresh_token'] ?? '';
$gdClientId = $settings['backup_gd_client_id'] ?? '';
$gdClientSecret = $settings['backup_gd_client_secret'] ?? '';
$gdFolderId = $settings['backup_gd_folder_id'] ?? '';

if ($gdEnabled === '1' && !empty($gdRefreshToken) && !empty($gdClientId) && !empty($gdClientSecret)) {
    autoBackupLog($log, 'Uploading to Google Drive...');
    try {
        // Get access token from refresh token
        $tokenResponse = autoBackupCurlPost('https://oauth2.googleapis.com/token', [
            'client_id' => $gdClientId,
            'client_secret' => $gdClientSecret,
            'refresh_token' => $gdRefreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (!$tokenResponse || !isset($tokenResponse['access_token'])) {
            throw new Exception('Failed to get Google Drive access token');
        }
        $accessToken = $tokenResponse['access_token'];

        // Upload file
        $mimeType = 'application/sql';
        $metadata = [
            'name' => $filename,
            'mimeType' => $mimeType,
        ];
        if (!empty($gdFolderId)) {
            $metadata['parents'] = [$gdFolderId];
        }

        $uploadUrl = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart';
        $boundary = 'boundary_' . uniqid();

        $body = "--{$boundary}\r\n";
        $body .= 'Content-Type: application/json; charset=UTF-8' . "\r\n\r\n";
        $body .= json_encode($metadata) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= 'Content-Type: ' . $mimeType . "\r\n";
        $body .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
        $body .= chunk_split(base64_encode(file_get_contents($absPath))) . "\r\n";
        $body .= "--{$boundary}--";

        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: multipart/related; boundary=' . $boundary,
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $uploadData = json_decode($response, true);
            autoBackupLog($log, 'Google Drive upload successful! File ID: ' . ($uploadData['id'] ?? 'unknown'));
        } else {
            autoBackupLog($log, 'WARNING: Google Drive upload failed (HTTP ' . $httpCode . ')');
        }
    } catch (Exception $e) {
        autoBackupLog($log, 'WARNING: Google Drive upload failed: ' . $e->getMessage());
    }
} else {
    autoBackupLog($log, 'Google Drive backup disabled or not configured. Skip.');
}

// ── 4. Upload to OneDrive ──────────────────────────────
$odEnabled = $settings['backup_od_enabled'] ?? '0';
$odRefreshToken = $settings['backup_od_refresh_token'] ?? '';
$odClientId = $settings['backup_od_client_id'] ?? '';
$odClientSecret = $settings['backup_od_client_secret'] ?? '';
$odFolderPath = $settings['backup_od_folder_path'] ?? 'MTravelsBackups';

if ($odEnabled === '1' && !empty($odRefreshToken) && !empty($odClientId) && !empty($odClientSecret)) {
    autoBackupLog($log, 'Uploading to OneDrive...');
    try {
        // Get access token from refresh token
        $tokenResponse = autoBackupCurlPost('https://login.microsoftonline.com/consumers/oauth2/v2.0/token', [
            'client_id' => $odClientId,
            'client_secret' => $odClientSecret,
            'refresh_token' => $odRefreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (!$tokenResponse || !isset($tokenResponse['access_token'])) {
            throw new Exception('Failed to get OneDrive access token');
        }
        $accessToken = $tokenResponse['access_token'];

        // Create folder if needed and upload
        $encodedPath = rawurlencode($odFolderPath . '/' . $filename);
        $uploadUrl = "https://graph.microsoft.com/v1.0/me/drive/root:/{$encodedPath}:/content";

        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/octet-stream',
            ],
            CURLOPT_PUT => true,
            CURLOPT_INFILE => fopen($absPath, 'r'),
            CURLOPT_INFILESIZE => $fileSize,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 || $httpCode === 201) {
            autoBackupLog($log, 'OneDrive upload successful!');
        } else {
            autoBackupLog($log, 'WARNING: OneDrive upload failed (HTTP ' . $httpCode . ')');
        }
    } catch (Exception $e) {
        autoBackupLog($log, 'WARNING: OneDrive upload failed: ' . $e->getMessage());
    }
} else {
    autoBackupLog($log, 'OneDrive backup disabled or not configured. Skip.');
}

// ── 5. Cleanup Old Local Backups ───────────────────────
$keepLocal = $settings['backup_keep_local'] ?? '1';
$maxLocalBackups = intval($settings['backup_max_local'] ?? '30');

if ($keepLocal !== '1') {
    // Delete the backup file if not keeping local copies
    if (file_exists($absPath)) {
        unlink($absPath);
        autoBackupLog($log, 'Local backup file deleted (keep_local disabled).');
    }
} elseif ($maxLocalBackups > 0) {
    // Clean up old auto_backup files, keep only the latest N
    $existingFiles = glob($backupDir . '/auto_backup_*.sql');
    usort($existingFiles, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    while (count($existingFiles) > $maxLocalBackups) {
        $oldFile = array_pop($existingFiles);
        if (file_exists($oldFile)) {
            unlink($oldFile);
            autoBackupLog($log, 'Cleaned up old backup: ' . basename($oldFile));
        }
    }
}

// ── Done ────────────────────────────────────────────────
$elapsed = round(microtime(true) - $startTime, 2);
autoBackupLog($log, "Backup completed in {$elapsed}s");

// Store last backup info
try {
    $stmt = $pdo->prepare("INSERT INTO platform_settings (`key`, `value`, `type`, `description`, `created_at`, `updated_at`)
                            VALUES ('backup_last_run', NOW(), 'string', 'Last backup timestamp', NOW(), NOW())
                            ON DUPLICATE KEY UPDATE `value` = NOW(), `updated_at` = NOW()");
    $stmt->execute();

    $stmt = $pdo->prepare("INSERT INTO platform_settings (`key`, `value`, `type`, `description`, `created_at`, `updated_at`)
                            VALUES ('backup_last_file', ?, 'string', 'Last backup filename', NOW(), NOW())
                            ON DUPLICATE KEY UPDATE `value` = ?, `updated_at` = NOW()");
    $stmt->execute([$filename, $filename]);

    $stmt = $pdo->prepare("INSERT INTO platform_settings (`key`, `value`, `type`, `description`, `created_at`, `updated_at`)
                            VALUES ('backup_last_size', ?, 'string', 'Last backup file size', NOW(), NOW())
                            ON DUPLICATE KEY UPDATE `value` = ?, `updated_at` = NOW()");
    $sizeStr = round($fileSize / 1024 / 1024, 2) . ' MB';
    $stmt->execute([$sizeStr, $sizeStr]);

    $stmt = $pdo->prepare("INSERT INTO platform_settings (`key`, `value`, `type`, `description`, `created_at`, `updated_at`)
                            VALUES ('backup_last_status', 'success', 'string', 'Last backup status', NOW(), NOW())
                            ON DUPLICATE KEY UPDATE `value` = 'success', `updated_at` = NOW()");
    $stmt->execute();
} catch (Exception $e) {
    // Non-critical
}

exit(0);

// ── Helper Functions ───────────────────────────────────
function autoBackupCurlPost($url, $data) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return json_decode($response, true);
    }
    return null;
}
