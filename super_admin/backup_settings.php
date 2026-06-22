<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../includes/db.php';

if (!function_exists('h')) {
    function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

// Load platform settings
$platformSettings = [];
try {
    $stmt = $pdo->prepare("SELECT `key`, `value` FROM platform_settings");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $platformSettings[$row['key']] = $row['value'];
    }
} catch (PDOException $e) {
    // ignore
}

// Load SMTP settings for display
$smtpConfigured = !empty($platformSettings['smtp_host'] ?? '');

include '../includes/header_super_admin.php';
?>

<style>
:root {
    --bs-primary: #4099ff;
    --bs-success: #2ed8b6;
    --bs-danger: #ff5370;
    --bs-warning: #ffb64d;
    --bs-bg: #f4f6f9;
    --bs-surface: #fff;
    --bs-text: #2c3e50;
    --bs-text-muted: #6c757d;
    --bs-border: #e8ecf1;
    --bs-radius: 12px;
    --bs-shadow: 0 2px 8px rgba(0,0,0,.08);
}

.bs-page { padding: 20px; max-width: 960px; margin: 0 auto; }
.bs-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.bs-header h2 { margin:0; font-size:1.5rem; font-weight:600; color:var(--bs-text); display:flex; align-items:center; gap:10px; }

.bs-card { background:var(--bs-surface); border-radius:var(--bs-radius); box-shadow:var(--bs-shadow); overflow:hidden; margin-bottom:20px; border:1px solid var(--bs-border); }
.bs-card-header { padding:16px 20px; border-bottom:1px solid var(--bs-border); display:flex; align-items:center; justify-content:space-between; }
.bs-card-header h3 { margin:0; font-size:1rem; font-weight:600; color:var(--bs-text); display:flex; align-items:center; gap:8px; }
.bs-card-body { padding:20px; }

.bs-form-group { margin-bottom:16px; }
.bs-form-label { display:block; font-size:.82rem; font-weight:500; color:var(--bs-text); margin-bottom:4px; }
.bs-form-control { width:100%; padding:8px 12px; border:1.5px solid var(--bs-border); border-radius:6px; font-size:.88rem; transition:border-color .2s; background:var(--bs-surface); color:var(--bs-text); }
.bs-form-control:focus { outline:none; border-color:var(--bs-primary); box-shadow:0 0 0 3px rgba(64,153,255,.12); }
.bs-form-control:disabled { background:#f5f5f5; cursor:not-allowed; }

.bs-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.bs-check-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.bs-check-row input[type="checkbox"] { width:18px;height:18px;accent-color:var(--bs-primary);cursor:pointer; }
.bs-check-row label { font-size:.88rem; color:var(--bs-text); cursor:pointer; user-select:none; }

.bs-btn { padding:8px 20px; border:none; border-radius:6px; font-size:.88rem; font-weight:500; cursor:pointer; transition:all .2s; display:inline-flex; align-items:center; gap:6px; }
.bs-btn-primary { background:var(--bs-primary); color:#fff; }
.bs-btn-primary:hover { background:#3a8df5; transform:translateY(-1px); box-shadow:0 4px 12px rgba(64,153,255,.3); }
.bs-btn-success { background:var(--bs-success); color:#fff; }
.bs-btn-success:hover { background:#26c9a8; }
.bs-btn-danger { background:var(--bs-danger); color:#fff; }
.bs-btn-danger:hover { background:#e84662; }
.bs-btn-outline { background:transparent; border:1.5px solid var(--bs-border); color:var(--bs-text); }
.bs-btn-outline:hover { border-color:var(--bs-primary);color:var(--bs-primary); }
.bs-btn-sm { padding:5px 12px; font-size:.8rem; }
.bs-btn:disabled { opacity:.5; cursor:not-allowed; transform:none!important; }

.bs-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:99px; font-size:.75rem; font-weight:600; }
.bs-badge-active { background:#d4edda; color:#155724; }
.bs-badge-inactive { background:#f8d7da; color:#721c24; }
.bs-badge-info { background:#dbeafe; color:#1d4ed8; }

.bs-help-text { font-size:.78rem; color:var(--bs-text-muted); margin-top:4px; }
.bs-divider { border:none; border-top:1px solid var(--bs-border); margin:16px 0; }

.bs-oauth-status { display:flex; align-items:center; gap:10px; padding:12px 16px; border-radius:8px; margin-bottom:12px; }
.bs-oauth-status.connected { background:#d4edda; color:#155724; }
.bs-oauth-status.disconnected { background:#f8d7da; color:#721c24; }
.bs-oauth-status i { font-size:1.1rem; }

.bs-toast { position:fixed; top:20px; right:20px; z-index:9999; padding:14px 20px; border-radius:8px; color:#fff; font-size:.88rem; box-shadow:0 4px 16px rgba(0,0,0,.15); transform:translateX(120%); transition:transform .3s ease; }
.bs-toast.show { transform:translateX(0); }
.bs-toast-success { background:var(--bs-success); }
.bs-toast-error { background:var(--bs-danger); }
.bs-toast-info { background:var(--bs-primary); }

.bs-last-run { display:flex; gap:16px; flex-wrap:wrap; align-items:center; }
.bs-last-run-item { display:flex; align-items:center; gap:6px; font-size:.85rem; color:var(--bs-text-muted); }
.bs-last-run-item strong { color:var(--bs-text); }

@media (max-width:768px) { .bs-row { grid-template-columns:1fr; } }
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="bs-page">

                            <div class="bs-header">
                                <h2><i class="feather icon-database" style="color:var(--bs-primary)"></i> Backup Settings</h2>
                                <div style="display:flex;gap:8px;">
                                    <a href="backup_management.php" class="bs-btn bs-btn-outline bs-btn-sm">
                                        <i class="feather icon-folder"></i> Manage Backups
                                    </a>
                                    <a href="platform_settings.php" class="bs-btn bs-btn-outline bs-btn-sm">
                                        <i class="feather icon-settings"></i> Platform Settings
                                    </a>
                                </div>
                            </div>

                            <!-- Last Backup Status -->
                            <div class="bs-card">
                                <div class="bs-card-header">
                                    <h3><i class="feather icon-clock" style="color:var(--bs-primary)"></i> Last Backup</h3>
                                </div>
                                <div class="bs-card-body">
                                    <div class="bs-last-run">
                                        <div class="bs-last-run-item">
                                            <i class="feather icon-calendar"></i>
                                            Last run: <strong><?= h($platformSettings['backup_last_run'] ?? 'Never') ?></strong>
                                        </div>
                                        <div class="bs-last-run-item">
                                            <i class="feather icon-file"></i>
                                            File: <strong><?= h($platformSettings['backup_last_file'] ?? '—') ?></strong>
                                        </div>
                                        <div class="bs-last-run-item">
                                            <i class="feather icon-hard-drive"></i>
                                            Size: <strong><?= h($platformSettings['backup_last_size'] ?? '—') ?></strong>
                                        </div>
                                        <div class="bs-last-run-item">
                                            <span class="bs-badge <?= ($platformSettings['backup_last_status'] ?? '') === 'success' ? 'bs-badge-active' : 'bs-badge-inactive' ?>">
                                                <i class="feather icon-<?= ($platformSettings['backup_last_status'] ?? '') === 'success' ? 'check-circle' : 'alert-circle' ?>"></i>
                                                <?= h(ucfirst($platformSettings['backup_last_status'] ?? 'Never')) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <form id="backupSettingsForm">
                                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

                                <!-- General Settings -->
                                <div class="bs-card">
                                    <div class="bs-card-header">
                                        <h3><i class="feather icon-toggle-right" style="color:var(--bs-primary)"></i> General</h3>
                                    </div>
                                    <div class="bs-card-body">
                                        <div class="bs-check-row">
                                            <input type="checkbox" name="auto_backup_enabled" id="autoBackupEnabled" value="1" <?= ($platformSettings['auto_backup_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                            <label for="autoBackupEnabled">Enable automated daily backup (runs at midnight)</label>
                                        </div>

                                        <div class="bs-row">
                                            <div class="bs-form-group">
                                                <label class="bs-form-label">Keep Local Copies</label>
                                                <select class="bs-form-control" name="backup_keep_local">
                                                    <option value="1" <?= ($platformSettings['backup_keep_local'] ?? '1') === '1' ? 'selected' : '' ?>>Yes, keep local backups</option>
                                                    <option value="0" <?= ($platformSettings['backup_keep_local'] ?? '1') === '0' ? 'selected' : '' ?>>No, delete after upload</option>
                                                </select>
                                                <div class="bs-help-text">Keep a copy of the backup file on the server after upload.</div>
                                            </div>
                                            <div class="bs-form-group">
                                                <label class="bs-form-label">Max Local Backups</label>
                                                <input type="number" class="bs-form-control" name="backup_max_local" value="<?= h($platformSettings['backup_max_local'] ?? '30') ?>" min="1" max="365">
                                                <div class="bs-help-text">Number of backup files to retain locally (older ones are deleted).</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Email Settings -->
                                <div class="bs-card">
                                    <div class="bs-card-header">
                                        <h3><i class="feather icon-mail" style="color:var(--bs-success)"></i> Email Backup</h3>
                                        <span class="bs-badge <?= ($platformSettings['backup_email_enabled'] ?? '0') === '1' ? 'bs-badge-active' : 'bs-badge-inactive' ?>" id="emailBadge">
                                            <?= ($platformSettings['backup_email_enabled'] ?? '0') === '1' ? 'Enabled' : 'Disabled' ?>
                                        </span>
                                    </div>
                                    <div class="bs-card-body">
                                        <?php if (!$smtpConfigured): ?>
                                        <div class="bs-oauth-status disconnected">
                                            <i class="feather icon-alert-triangle"></i>
                                            SMTP is not configured. <a href="platform_settings.php" style="color:#721c24;font-weight:600;margin-left:4px;">Configure SMTP first</a>
                                        </div>
                                        <?php endif; ?>

                                        <div class="bs-check-row">
                                            <input type="checkbox" name="backup_email_enabled" id="backupEmailEnabled" value="1" <?= ($platformSettings['backup_email_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                            <label for="backupEmailEnabled">Send backup via email</label>
                                        </div>
                                        <div class="bs-form-group">
                                            <label class="bs-form-label">Recipient Email</label>
                                            <input type="email" class="bs-form-control" name="backup_email_to" value="<?= h($platformSettings['backup_email_to'] ?? '') ?>" placeholder="admin@example.com">
                                            <div class="bs-help-text">This email will receive the backup file as an attachment.</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Google Drive Settings -->
                                <div class="bs-card">
                                    <div class="bs-card-header">
                                        <h3><i class="fab fa-google-drive" style="color:#4285F4"></i> Google Drive</h3>
                                        <span class="bs-badge <?= ($platformSettings['backup_gd_enabled'] ?? '0') === '1' ? 'bs-badge-active' : 'bs-badge-inactive' ?>" id="gdBadge">
                                            <?= ($platformSettings['backup_gd_enabled'] ?? '0') === '1' ? 'Connected' : 'Disconnected' ?>
                                        </span>
                                    </div>
                                    <div class="bs-card-body">
                                        <?php if (!empty($platformSettings['backup_gd_refresh_token'])): ?>
                                        <div class="bs-oauth-status connected">
                                            <i class="feather icon-check-circle"></i>
                                            Google Drive is connected and authenticated.
                                        </div>
                                        <?php else: ?>
                                        <div class="bs-oauth-status disconnected">
                                            <i class="feather icon-alert-circle"></i>
                                            Not connected to Google Drive.
                                        </div>
                                        <?php endif; ?>

                                        <div class="bs-check-row">
                                            <input type="checkbox" name="backup_gd_enabled" id="backupGdEnabled" value="1" <?= ($platformSettings['backup_gd_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                            <label for="backupGdEnabled">Upload backup to Google Drive</label>
                                        </div>

                                        <div class="bs-row">
                                            <div class="bs-form-group">
                                                <label class="bs-form-label">Client ID</label>
                                                <input type="text" class="bs-form-control" name="backup_gd_client_id" value="<?= h($platformSettings['backup_gd_client_id'] ?? '') ?>" placeholder="1234567890-xxx.apps.googleusercontent.com">
                                            </div>
                                            <div class="bs-form-group">
                                                <label class="bs-form-label">Client Secret</label>
                                                <input type="password" class="bs-form-control" name="backup_gd_client_secret" value="<?= h($platformSettings['backup_gd_client_secret'] ?? '') ?>" placeholder="GOCSPX-xxxxxxxxxxxx">
                                            </div>
                                        </div>

                                        <div class="bs-row">
                                            <div class="bs-form-group">
                                                <label class="bs-form-label">Refresh Token</label>
                                                <input type="password" class="bs-form-control" name="backup_gd_refresh_token" value="<?= h($platformSettings['backup_gd_refresh_token'] ?? '') ?>" placeholder="1//0xxxxxxxxxxxx">
                                                <div class="bs-help-text">Generated after OAuth authorization.</div>
                                            </div>
                                            <div class="bs-form-group">
                                                <label class="bs-form-label">Folder ID (optional)</label>
                                                <input type="text" class="bs-form-control" name="backup_gd_folder_id" value="<?= h($platformSettings['backup_gd_folder_id'] ?? '') ?>" placeholder="Leave empty for root">
                                                <div class="bs-help-text">Google Drive folder ID where backups will be stored.</div>
                                            </div>
                                        </div>

                                        <div style="margin-top:8px;">
                                            <a href="<?= h(getGoogleDriveAuthUrl()) ?>" class="bs-btn bs-btn-primary bs-btn-sm" target="_blank" <?php if (empty($platformSettings['backup_gd_client_id'])) echo 'onclick="showToast(\'Save Client ID first\',\'error\');return false;"' ?>>
                                                <i class="fab fa-google"></i> Authorize Google Drive
                                            </a>
                                            <span class="bs-help-text" style="display:inline;margin-left:8px;">Opens Google to authorize access.</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- OneDrive Settings -->
                                <div class="bs-card">
                                    <div class="bs-card-header">
                                        <h3><i class="fab fa-microsoft" style="color:#00A4EF"></i> OneDrive</h3>
                                        <span class="bs-badge <?= ($platformSettings['backup_od_enabled'] ?? '0') === '1' ? 'bs-badge-active' : 'bs-badge-inactive' ?>" id="odBadge">
                                            <?= ($platformSettings['backup_od_enabled'] ?? '0') === '1' ? 'Connected' : 'Disconnected' ?>
                                        </span>
                                    </div>
                                    <div class="bs-card-body">
                                        <?php if (!empty($platformSettings['backup_od_refresh_token'])): ?>
                                        <div class="bs-oauth-status connected">
                                            <i class="feather icon-check-circle"></i>
                                            OneDrive is connected and authenticated.
                                        </div>
                                        <?php else: ?>
                                        <div class="bs-oauth-status disconnected">
                                            <i class="feather icon-alert-circle"></i>
                                            Not connected to OneDrive.
                                        </div>
                                        <?php endif; ?>

                                        <div class="bs-check-row">
                                            <input type="checkbox" name="backup_od_enabled" id="backupOdEnabled" value="1" <?= ($platformSettings['backup_od_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                            <label for="backupOdEnabled">Upload backup to OneDrive</label>
                                        </div>

                                        <div class="bs-row">
                                            <div class="bs-form-group">
                                                <label class="bs-form-label">Client ID (Application ID)</label>
                                                <input type="text" class="bs-form-control" name="backup_od_client_id" value="<?= h($platformSettings['backup_od_client_id'] ?? '') ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                                            </div>
                                            <div class="bs-form-group">
                                                <label class="bs-form-label">Client Secret</label>
                                                <input type="password" class="bs-form-control" name="backup_od_client_secret" value="<?= h($platformSettings['backup_od_client_secret'] ?? '') ?>" placeholder="Your client secret">
                                            </div>
                                        </div>

                                        <div class="bs-form-group">
                                            <label class="bs-form-label">Refresh Token</label>
                                            <input type="password" class="bs-form-control" name="backup_od_refresh_token" value="<?= h($platformSettings['backup_od_refresh_token'] ?? '') ?>" placeholder="0.xxxxxxxxxx...">
                                            <div class="bs-help-text">Generated after OAuth authorization.</div>
                                        </div>
                                        <div class="bs-form-group">
                                            <label class="bs-form-label">Folder Path (optional)</label>
                                            <input type="text" class="bs-form-control" name="backup_od_folder_path" value="<?= h($platformSettings['backup_od_folder_path'] ?? 'MTravelsBackups') ?>" placeholder="MTravelsBackups">
                                            <div class="bs-help-text">OneDrive folder path where backups will be stored.</div>
                                        </div>

                                        <div style="margin-top:8px;">
                                            <a href="<?= h(getOneDriveAuthUrl()) ?>" class="bs-btn bs-btn-primary bs-btn-sm" target="_blank" <?php if (empty($platformSettings['backup_od_client_id'])) echo 'onclick="showToast(\'Save Client ID first\',\'error\');return false;"' ?>>
                                                <i class="fab fa-microsoft"></i> Authorize OneDrive
                                            </a>
                                            <span class="bs-help-text" style="display:inline;margin-left:8px;">Opens Microsoft to authorize access.</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Save -->
                                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:10px;">
                                    <button type="submit" class="bs-btn bs-btn-primary">
                                        <i class="feather icon-save"></i> Save Settings
                                    </button>
                                    <button type="button" class="bs-btn bs-btn-success" onclick="runManualBackup()" id="manualBackupBtn">
                                        <i class="feather icon-play"></i> Run Backup Now
                                    </button>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
let toastTimer;

function showToast(msg, type) {
    const existing = document.querySelector('.bs-toast');
    if (existing) existing.remove();
    const div = document.createElement('div');
    div.className = 'bs-toast bs-toast-' + type;
    div.textContent = msg;
    document.body.appendChild(div);
    clearTimeout(toastTimer);
    requestAnimationFrame(() => div.classList.add('show'));
    toastTimer = setTimeout(() => { div.classList.remove('show'); setTimeout(() => div.remove(), 300); }, 4000);
}

document.getElementById('backupSettingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="feather icon-loader"></i> Saving...';

    const formData = new FormData(this);

    fetch('backup_update_settings.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Settings saved successfully!', 'success');
            } else {
                showToast(data.message || 'Save failed', 'error');
            }
        })
        .catch(() => showToast('Network error', 'error'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="feather icon-save"></i> Save Settings';
        });
});

function runManualBackup() {
    const btn = document.getElementById('manualBackupBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="feather icon-loader"></i> Running backup...';

    fetch('../cron/auto_backup.php?force=1')
        .then(r => r.text())
        .then(data => {
            showToast('Backup completed! Check the logs below.', 'success');
            setTimeout(() => window.location.reload(), 1500);
        })
        .catch(() => showToast('Backup failed. Check server logs.', 'error'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="feather icon-play"></i> Run Backup Now';
        });
}

// Toggle badge labels
document.getElementById('backupEmailEnabled').addEventListener('change', function() {
    document.getElementById('emailBadge').textContent = this.checked ? 'Enabled' : 'Disabled';
    document.getElementById('emailBadge').className = 'bs-badge ' + (this.checked ? 'bs-badge-active' : 'bs-badge-inactive');
});
document.getElementById('backupGdEnabled').addEventListener('change', function() {
    document.getElementById('gdBadge').textContent = this.checked ? 'Enabled' : 'Disabled';
    document.getElementById('gdBadge').className = 'bs-badge ' + (this.checked ? 'bs-badge-active' : 'bs-badge-inactive');
});
document.getElementById('backupOdEnabled').addEventListener('change', function() {
    document.getElementById('odBadge').textContent = this.checked ? 'Enabled' : 'Disabled';
    document.getElementById('odBadge').className = 'bs-badge ' + (this.checked ? 'bs-badge-active' : 'bs-badge-inactive');
});
</script>

<?php
// Helper functions for OAuth URLs
function getGoogleDriveAuthUrl() {
    global $platformSettings;
    $clientId = $platformSettings['backup_gd_client_id'] ?? '';
    if (empty($clientId)) return '#';

    $redirectUri = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/') . '/backup_oauth_gd.php';
    $params = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/drive.file',
        'access_type' => 'offline',
        'prompt' => 'consent',
    ]);
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
}

function getOneDriveAuthUrl() {
    global $platformSettings;
    $clientId = $platformSettings['backup_od_client_id'] ?? '';
    if (empty($clientId)) return '#';

    $redirectUri = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/') . '/backup_oauth_od.php';
    $params = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'offline_access files.readwrite',
    ]);
    return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . $params;
}
?>

</body>
</html>
