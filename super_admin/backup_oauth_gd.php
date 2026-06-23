<?php
/**
 * Google Drive OAuth Callback Handler
 * 
 * Receives the authorization code from Google after user authorizes access.
 * Exchanges the code for tokens and stores the refresh token in platform_settings.
 * 
 * Setup in Google Cloud Console:
 * 1. Create project → Enable Google Drive API
 * 2. Credentials → OAuth 2.0 Client IDs → Web application
 * 3. Add this file's URL as Authorized redirect URI
 */

session_start();

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

// Only super_admin can complete OAuth
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !empty($_SESSION['tenant_id'])) {
    die('Unauthorized. Please log in as super admin first.');
}

require_once '../includes/db.php';

$code = $_GET['code'] ?? '';
$error = $_GET['error'] ?? '';

if (!empty($error)) {
    $errorMsg = 'Authorization denied or failed: ' . htmlspecialchars($error);
    ?>
    <!DOCTYPE html>
    <html><head><title>Google Drive Auth Failed</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8f9fa;}
    .card{background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.08);text-align:center;max-width:500px;}
    .error{color:#ef4444;font-size:1.1rem;margin:16px 0;}
    a{display:inline-block;margin-top:16px;color:#4099ff;text-decoration:none;font-weight:600;}</style></head>
    <body><div class="card">
        <h2>Authorization Failed</h2>
        <p class="error"><?= $errorMsg ?></p>
        <a href="backup_settings.php">← Back to Backup Settings</a>
    </div></body></html>
    <?php
    exit();
}

if (empty($code)) {
    die('No authorization code received.');
}

// Load settings to get client_id and client_secret
$settings = [];
try {
    $stmt = $pdo->prepare("SELECT `key`, `value` FROM platform_settings WHERE `key` IN ('backup_gd_client_id', 'backup_gd_client_secret')");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['key']] = $row['value'];
    }
} catch (PDOException $e) {
    die('Database error.');
}

$clientId = $settings['backup_gd_client_id'] ?? '';
$clientSecret = $settings['backup_gd_client_secret'] ?? '';

if (empty($clientId) || empty($clientSecret)) {
    die('Google Drive API credentials not configured. Save Client ID and Client Secret first.');
}

// Determine the redirect URI (must match what's registered in Google Cloud Console)
$redirectUri = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/') . '/backup_oauth_gd.php';

// Exchange authorization code for tokens
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    $errorData = json_decode($response, true);
    $errorMsg = $errorData['error_description'] ?? $errorData['error'] ?? 'Unknown error';
    ?>
    <!DOCTYPE html>
    <html><head><title>Google Drive Token Exchange Failed</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8f9fa;}
    .card{background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.08);text-align:center;max-width:500px;}
    .error{color:#ef4444;font-size:1rem;margin:16px 0;}
    a{display:inline-block;margin-top:16px;color:#4099ff;text-decoration:none;font-weight:600;}</style></head>
    <body><div class="card">
        <h2>Token Exchange Failed</h2>
        <p class="error"><?= htmlspecialchars($errorMsg) ?></p>
        <p style="color:#6c757d;font-size:.85rem;">Make sure the Client ID and Client Secret are correct, and the redirect URI matches exactly.</p>
        <a href="backup_settings.php">← Back to Backup Settings</a>
    </div></body></html>
    <?php
    exit();
}

$tokenData = json_decode($response, true);
$refreshToken = $tokenData['refresh_token'] ?? '';
$accessToken = $tokenData['access_token'] ?? '';

if (empty($refreshToken)) {
    ?>
    <!DOCTYPE html>
    <html><head><title>Google Drive - No Refresh Token</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8f9fa;}
    .card{background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.08);text-align:center;max-width:500px;}
    .warn{color:#f59e0b;font-size:1rem;margin:16px 0;}
    a{display:inline-block;margin-top:16px;color:#4099ff;text-decoration:none;font-weight:600;}</style></head>
    <body><div class="card">
        <h2>No Refresh Token Received</h2>
        <p class="warn">Google did not return a refresh token. This usually happens if the account was already authorized before. You may need to revoke access first.</p>
        <p style="color:#6c757d;font-size:.85rem;">You can manually enter the access token below, or <a href="https://myaccount.google.com/permissions" target="_blank">revoke access</a> and try again.</p>
        <p style="font-size:.78rem;color:#6c757d;word-break:break-all;">Access Token: <?= htmlspecialchars($accessToken) ?></p>
        <a href="backup_settings.php">← Back to Backup Settings</a>
    </div></body></html>
    <?php
    exit();
}

// Store the refresh token
try {
    $stmt = $pdo->prepare("INSERT INTO platform_settings (`key`, `value`, `type`, `description`, `created_at`, `updated_at`)
                            VALUES ('backup_gd_refresh_token', ?, 'string', 'Google Drive OAuth refresh token', NOW(), NOW())
                            ON DUPLICATE KEY UPDATE `value` = ?, `updated_at` = NOW()");
    $stmt->execute([$refreshToken, $refreshToken]);

    // Also enable Google Drive backup
    $stmt = $pdo->prepare("INSERT INTO platform_settings (`key`, `value`, `type`, `description`, `created_at`, `updated_at`)
                            VALUES ('backup_gd_enabled', '1', 'boolean', 'Upload backup to Google Drive', NOW(), NOW())
                            ON DUPLICATE KEY UPDATE `value` = '1', `updated_at` = NOW()");
    $stmt->execute();
} catch (PDOException $e) {
    die('Database error while saving token.');
}

// Success page
?>
<!DOCTYPE html>
<html><head><title>Google Drive Connected!</title>
<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8f9fa;}
.card{background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.08);text-align:center;max-width:500px;}
.success{color:#10b981;font-size:1.1rem;margin:16px 0;}
a{display:inline-block;margin-top:16px;padding:10px 24px;background:#4099ff;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;}
a:hover{background:#3a8df5;}</style></head>
<body><div class="card">
    <h2>✅ Google Drive Connected!</h2>
    <p class="success">Your Google Drive has been successfully linked.</p>
    <p style="color:#6c757d;font-size:.85rem;">Automated backups will now be uploaded to Google Drive.</p>
    <a href="backup_settings.php">← Back to Backup Settings</a>
</div></body></html>
