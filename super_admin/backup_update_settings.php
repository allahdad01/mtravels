<?php
session_start();

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Security validation failed']);
    exit();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../includes/db.php';

$settings = [
    ['key' => 'auto_backup_enabled', 'value' => isset($_POST['auto_backup_enabled']) ? '1' : '0', 'type' => 'boolean', 'description' => 'Enable automated daily backup'],
    ['key' => 'backup_keep_local', 'value' => $_POST['backup_keep_local'] ?? '1', 'type' => 'boolean', 'description' => 'Keep local backup copies'],
    ['key' => 'backup_max_local', 'value' => intval($_POST['backup_max_local'] ?? 30), 'type' => 'integer', 'description' => 'Maximum number of local backups to retain'],
    ['key' => 'backup_email_enabled', 'value' => isset($_POST['backup_email_enabled']) ? '1' : '0', 'type' => 'boolean', 'description' => 'Send backup via email'],
    ['key' => 'backup_email_to', 'value' => trim($_POST['backup_email_to'] ?? ''), 'type' => 'string', 'description' => 'Email recipient for backup'],
    ['key' => 'backup_gd_enabled', 'value' => isset($_POST['backup_gd_enabled']) ? '1' : '0', 'type' => 'boolean', 'description' => 'Upload backup to Google Drive'],
    ['key' => 'backup_gd_client_id', 'value' => trim($_POST['backup_gd_client_id'] ?? ''), 'type' => 'string', 'description' => 'Google Drive OAuth client ID'],
    ['key' => 'backup_gd_client_secret', 'value' => trim($_POST['backup_gd_client_secret'] ?? ''), 'type' => 'string', 'description' => 'Google Drive OAuth client secret'],
    ['key' => 'backup_gd_refresh_token', 'value' => trim($_POST['backup_gd_refresh_token'] ?? ''), 'type' => 'string', 'description' => 'Google Drive OAuth refresh token'],
    ['key' => 'backup_gd_folder_id', 'value' => trim($_POST['backup_gd_folder_id'] ?? ''), 'type' => 'string', 'description' => 'Google Drive folder ID for backups'],
    ['key' => 'backup_od_enabled', 'value' => isset($_POST['backup_od_enabled']) ? '1' : '0', 'type' => 'boolean', 'description' => 'Upload backup to OneDrive'],
    ['key' => 'backup_od_client_id', 'value' => trim($_POST['backup_od_client_id'] ?? ''), 'type' => 'string', 'description' => 'OneDrive OAuth client ID'],
    ['key' => 'backup_od_client_secret', 'value' => trim($_POST['backup_od_client_secret'] ?? ''), 'type' => 'string', 'description' => 'OneDrive OAuth client secret'],
    ['key' => 'backup_od_refresh_token', 'value' => trim($_POST['backup_od_refresh_token'] ?? ''), 'type' => 'string', 'description' => 'OneDrive OAuth refresh token'],
    ['key' => 'backup_od_folder_path', 'value' => trim($_POST['backup_od_folder_path'] ?? 'MTravelsBackups'), 'type' => 'string', 'description' => 'OneDrive folder path for backups'],
];

try {
    $stmt = $pdo->prepare("INSERT INTO platform_settings (`key`, `value`, `type`, `description`, `created_at`, `updated_at`)
                            VALUES (?, ?, ?, ?, NOW(), NOW())
                            ON DUPLICATE KEY UPDATE `value` = ?, `updated_at` = NOW()");

    foreach ($settings as $setting) {
        $stmt->execute([$setting['key'], $setting['value'], $setting['type'], $setting['description'], $setting['value']]);
    }

    // Audit log
    $user_id = $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                            VALUES (?, 'update_backup_settings', 'platform_setting', 0, ?, ?, NOW())");
    $stmt->execute([$user_id, json_encode(['updated' => true]), $ip]);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Backup settings updated']);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
