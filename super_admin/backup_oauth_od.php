<?php
/**
 * OneDrive (Microsoft Graph) OAuth Callback Handler
 * 
 * Receives the authorization code from Microsoft after user authorizes access.
 * Exchanges the code for tokens and stores the refresh token in platform_settings.
 * 
 * Setup in Azure Portal:
 * 1. Azure AD → App registrations → New registration
 * 2. Redirect URI: Web → this file's URL
 * 3. API permissions → Microsoft Graph → Delegated → Files.ReadWrite
 * 4. Certificates & secrets → New client secret
 */

session_start();

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !empty($_SESSION['tenant_id'])) {
    die('Unauthorized. Please log in as super admin first.');
}

require_once '../includes/db.php';

$code = $_GET['code'] ?? '';
$error = $_GET['error'] ?? '';

if (!empty($error)) {
    $errorDesc = $_GET['error_description'] ?? $error;
    ?>
    <!DOCTYPE html>
    <html><head><title>OneDrive Auth Failed</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8f9fa;}
    .card{background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.08);text-align:center;max-width:500px;}
    .error{color:#ef4444;font-size:1.1rem;margin:16px 0;}
    a{display:inline-block;margin-top:16px;color:#4099ff;text-decoration:none;font-weight:600;}</style></head>
    <body><div class="card">
        <h2>Authorization Failed</h2>
        <p class="error"><?= htmlspecialchars($errorDesc) ?></p>
        <a href="backup_settings.php">← Back to Backup Settings</a>
    </div></body></html>
    <?php
    exit();
}

if (empty($code)) {
    die('No authorization code received.');
}

// Load settings
$settings = [];
try {
    $stmt = $pdo->prepare("SELECT `key`, `value` FROM platform_settings WHERE `key` IN ('backup_od_client_id', 'backup_od_client_secret')");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['key']] = $row['value'];
    }
} catch (PDOException $e) {
    die('Database error.');
}

$clientId = $settings['backup_od_client_id'] ?? '';
$clientSecret = $settings['backup_od_client_secret'] ?? '';

if (empty($clientId) || empty($clientSecret)) {
    die('OneDrive API credentials not configured. Save Client ID and Client Secret first.');
}

$redirectUri = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/') . '/backup_oauth_od.php';

// Exchange code for tokens
$ch = curl_init('https://login.microsoftonline.com/consumers/oauth2/v2.0/token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code,
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
    <html><head><title>OneDrive Token Exchange Failed</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8f9fa;}
    .card{background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.08);text-align:center;max-width:500px;}
    .error{color:#ef4444;font-size:1rem;margin:16px 0;}
    a{display:inline-block;margin-top:16px;color:#4099ff;text-decoration:none;font-weight:600;}</style></head>
    <body><div class="card">
        <h2>Token Exchange Failed</h2>
        <p class="error"><?= htmlspecialchars($errorMsg) ?></p>
        <p style="color:#6c757d;font-size:.85rem;">Make sure Client ID and Client Secret are correct, and redirect URI matches Azure AD registration.</p>
        <a href="backup_settings.php">← Back to Backup Settings</a>
    </div></body></html>
    <?php
    exit();
}

$tokenData = json_decode($response, true);
$refreshToken = $tokenData['refresh_token'] ?? '';

if (empty($refreshToken)) {
    ?>
    <!DOCTYPE html>
    <html><head><title>OneDrive - No Refresh Token</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8f9fa;}
    .card{background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.08);text-align:center;max-width:500px;}
    .warn{color:#f59e0b;font-size:1rem;margin:16px 0;}
    a{display:inline-block;margin-top:16px;color:#4099ff;text-decoration:none;font-weight:600;}</style></head>
    <body><div class="card">
        <h2>No Refresh Token</h2>
        <p class="warn">Microsoft did not return a refresh token. Ensure 'offline_access' scope is included.</p>
        <a href="backup_settings.php">← Back to Backup Settings</a>
    </div></body></html>
    <?php
    exit();
}

// Store the refresh token
try {
    $stmt = $pdo->prepare("INSERT INTO platform_settings (`key`, `value`, `type`, `description`, `created_at`, `updated_at`)
                            VALUES ('backup_od_refresh_token', ?, 'string', 'OneDrive OAuth refresh token', NOW(), NOW())
                            ON DUPLICATE KEY UPDATE `value` = ?, `updated_at` = NOW()");
    $stmt->execute([$refreshToken, $refreshToken]);

    $stmt = $pdo->prepare("INSERT INTO platform_settings (`key`, `value`, `type`, `description`, `created_at`, `updated_at`)
                            VALUES ('backup_od_enabled', '1', 'boolean', 'Upload backup to OneDrive', NOW(), NOW())
                            ON DUPLICATE KEY UPDATE `value` = '1', `updated_at` = NOW()");
    $stmt->execute();
} catch (PDOException $e) {
    die('Database error while saving token.');
}

?>
<!DOCTYPE html>
<html><head><title>OneDrive Connected!</title>
<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8f9fa;}
.card{background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.08);text-align:center;max-width:500px;}
.success{color:#10b981;font-size:1.1rem;margin:16px 0;}
a{display:inline-block;margin-top:16px;padding:10px 24px;background:#4099ff;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;}
a:hover{background:#3a8df5;}</style></head>
<body><div class="card">
    <h2>✅ OneDrive Connected!</h2>
    <p class="success">Your OneDrive has been successfully linked.</p>
    <p style="color:#6c757d;font-size:.85rem;">Automated backups will now be uploaded to OneDrive.</p>
    <a href="backup_settings.php">← Back to Backup Settings</a>
</div></body></html>
