<?php
// Include config file
require_once "config.php";
require_once "includes/db.php";
require_once "php_login.php";
// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch settings data
try {
    $settingStmt = $pdo->query("SELECT `key`, `value` FROM platform_settings");
    $settings = $settingStmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
    $settings = [
        
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="login/style.css" />
    <title><?= htmlspecialchars($settings['platform_name']) ?></title>
    <!-- Favicon -->
    <link rel="icon" href="uploads/logo/<?= htmlspecialchars($settings['platform_logo'] ?? 'default-logo.png') ?>" type="image/x-icon">
  </head>
  <body>
    <div class="container">
      <div class="forms-container">
        <div class="signin-signup">
          <form action="#" method="post" class="sign-in-form">
            <?php if ($totp_verification): ?>
                <h2 class="title">Two-Factor Authentication</h2>
                <?php if (!empty($totp_err)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($totp_err) ?></div>
                <?php endif; ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="input-field">
                  <i class="fas fa-shield-alt"></i>
                  <input type="text" name="totp_code" placeholder="Enter 6-digit code" inputmode="numeric" pattern="[0-9]*" maxlength="6" />
                </div>
                <input type="submit" value="Verify" class="btn solid" />
                <p class="social-text"><a href="#" id="useRecoveryLink" class="link-text">Use recovery code instead</a></p>
            <?php else: ?>
                <h2 class="title">Sign in</h2>
                <?php if (!empty($email_err) || !empty($password_err)): ?>
                    <div class="alert alert-danger">
                        <?= !empty($email_err) ? "<div>".htmlspecialchars($email_err)."</div>" : "" ?>
                        <?= !empty($password_err) ? "<div>".htmlspecialchars($password_err)."</div>" : "" ?>
                    </div>
                <?php endif; ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="input-field">
                  <i class="fas fa-user"></i>
                  <input type="email" name="email" value="<?= htmlspecialchars($email); ?>" placeholder="Email" />
                </div>
                <div class="input-field">
                  <i class="fas fa-lock"></i>
                  <input type="password" name="password" placeholder="Password" />
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" name="remember" id="remember-me">
                    <label for="remember-me" class="form-check-label">Remember me</label>
                </div>
                <input type="submit" value="Login" class="btn solid" />
            <?php endif; ?>
          </form>

          <!-- Recovery form -->
          <form method="post" id="recoveryForm" style="display:none;">
            <h2 class="title">Recovery Code</h2>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="is_recovery" value="1">
            <div class="input-field">
              <i class="fas fa-unlock-alt"></i>
              <input type="text" name="totp_code" placeholder="XXXX-XXXX-XXXX-XXXX" />
            </div>
            <input type="submit" value="Verify" class="btn solid" />
            <p class="social-text"><a href="#" id="useTotpLink" class="link-text">Back to authentication code</a></p>
          </form>
        </div>
      </div>

      <div class="panels-container">
        <div class="panel left-panel">
          <div class="content">
            <img src="uploads/logo/<?= htmlspecialchars($settings['platform_logo'] ?? 'default-logo.png') ?>" alt="Platform Logo" class="platform-logo" style="max-width: 200px; margin-bottom: 1rem;">
            <h3><?= htmlspecialchars($settings['platform_name'] ?? '') ?></h3>
            <p><?= htmlspecialchars($settings['platform_description'] ?? '') ?></p>
          </div>
          <img src="login/img/log.svg" class="image" alt="" />
        </div>
        <div class="panel right-panel">
          <div class="content">
            <h3>Welcome Back</h3>
            <p>Please sign in to continue.</p>
          </div>
          <img src="login/img/register.svg" class="image" alt="" />
        </div>
      </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => document.querySelectorAll('.alert').forEach(a => a.style.display='none'), 5000);
        const recoveryForm = document.getElementById('recoveryForm');
        const useRecoveryLink = document.getElementById('useRecoveryLink');
        const useTotpLink = document.getElementById('useTotpLink');
        if (useRecoveryLink) useRecoveryLink.addEventListener('click', e => {
            e.preventDefault();
            document.querySelector('form').style.display = 'none';
            recoveryForm.style.display = 'block';
        });
        if (useTotpLink) useTotpLink.addEventListener('click', e => {
            e.preventDefault();
            recoveryForm.style.display = 'none';
            document.querySelector('form').style.display = 'block';
        });
    });
    </script>
  </body>
</html>