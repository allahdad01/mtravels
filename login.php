<?php
// Include config file
require_once "config.php";
require_once "includes/db.php";
require_once "php_login.php";

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $settingStmt = $pdo->query("SELECT `key`, `value` FROM platform_settings");
    $settings = $settingStmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
    $settings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="assets/fonts/fontawesome/css/fontawesome-all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
  <title><?= htmlspecialchars($settings['platform_name'] ?? 'Login') ?></title>
  <link rel="icon" href="uploads/logo/<?= htmlspecialchars($settings['platform_logo'] ?? 'default-logo.png') ?>" type="image/x-icon">
  <link rel="stylesheet" href="login/style.css" />
</head>
<body>

  <div class="login-layout">

    <!-- LEFT PANEL -->
    <aside class="login-brand">
      <div class="brand-inner">
        <div class="brand-logo-wrap">
          <img src="uploads/logo/<?= htmlspecialchars($settings['platform_logo'] ?? 'default-logo.png') ?>" alt="Logo" class="brand-logo">
        </div>
        <div class="brand-copy">
          <h1 class="brand-name"><?= htmlspecialchars($settings['platform_name'] ?? '') ?></h1>
          <p class="brand-desc"><?= htmlspecialchars($settings['platform_description'] ?? '') ?></p>
        </div>
        <div class="brand-illustration" aria-hidden="true">
          <img src="login/img/log.svg" alt="">
        </div>
        <div class="brand-dots" aria-hidden="true">
          <span></span><span></span><span></span>
        </div>
      </div>
      <div class="brand-noise"></div>
    </aside>

    <!-- RIGHT PANEL -->
    <main class="login-form-side">
      <div class="form-card">

        <!-- TOTP verification -->
        <?php if ($totp_verification): ?>
          <div class="form-header">
            <div class="form-icon"><i class="fas fa-shield-alt"></i></div>
            <h2>Two-Factor Auth</h2>
            <p>Enter the 6-digit code from your authenticator app.</p>
          </div>
          <?php if (!empty($totp_err)): ?>
            <div class="alert alert-danger" role="alert">
              <i class="fas fa-exclamation-circle"></i>
              <?= htmlspecialchars($totp_err) ?>
            </div>
          <?php endif; ?>
          <form method="post" id="totpForm" class="auth-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="field-group">
              <label for="totp_code">Authentication Code</label>
              <div class="input-wrap">
                <i class="fas fa-key input-icon"></i>
                <input type="text" id="totp_code" name="totp_code"
                  placeholder="000000" inputmode="numeric"
                  pattern="[0-9]*" maxlength="6" autocomplete="one-time-code">
              </div>
            </div>
            <button type="submit" class="btn-primary">Verify Code</button>
            <p class="form-footnote">
              <a href="#" id="useRecoveryLink">Use a recovery code instead</a>
            </p>
          </form>

          <!-- Recovery form (hidden by default) -->
          <form method="post" id="recoveryForm" class="auth-form" style="display:none;" novalidate>
            <div class="form-header">
              <div class="form-icon"><i class="fas fa-unlock-alt"></i></div>
              <h2>Recovery Code</h2>
              <p>Enter one of your saved recovery codes.</p>
            </div>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="is_recovery" value="1">
            <div class="field-group">
              <label for="recovery_code">Recovery Code</label>
              <div class="input-wrap">
                <i class="fas fa-lock-open input-icon"></i>
                <input type="text" id="recovery_code" name="totp_code" placeholder="XXXX-XXXX-XXXX-XXXX">
              </div>
            </div>
            <button type="submit" class="btn-primary">Verify Recovery Code</button>
            <p class="form-footnote">
              <a href="#" id="useTotpLink">Back to authentication code</a>
            </p>
          </form>

        <?php else: ?>
          <!-- Standard sign-in -->
          <div class="form-header">
            <div class="form-icon"><i class="fas fa-user"></i></div>
            <h2>Welcome back</h2>
            <p>Sign in to your account to continue.</p>
          </div>

          <?php if (!empty($email_err) || !empty($password_err)): ?>
            <div class="alert alert-danger" role="alert">
              <i class="fas fa-exclamation-circle"></i>
              <div>
                <?= !empty($email_err) ? "<span>" . htmlspecialchars($email_err) . "</span>" : "" ?>
                <?= !empty($password_err) ? "<span>" . htmlspecialchars($password_err) . "</span>" : "" ?>
              </div>
            </div>
          <?php endif; ?>

          <form method="post" class="auth-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="field-group">
              <label for="email">Email address</label>
              <div class="input-wrap">
                <i class="fas fa-envelope input-icon"></i>
                <input type="email" id="email" name="email"
                  value="<?= htmlspecialchars($email ?? '') ?>"
                  placeholder="you@example.com"
                  required autocomplete="email">
              </div>
            </div>

            <div class="field-group">
              <div class="label-row">
                <label for="password">Password</label>
                <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
              </div>
              <div class="input-wrap">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" id="password" name="password"
                  placeholder="••••••••"
                  required autocomplete="current-password">
                <button type="button" id="togglePassword" class="toggle-password" aria-label="Toggle password visibility">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
            </div>

            <button type="submit" class="btn-primary">
              <span>Sign in</span>
              <i class="fas fa-arrow-right"></i>
            </button>
          </form>
        <?php endif; ?>

      </div>

      <p class="layout-footnote">
        &copy; <?= date('Y') ?> <?= htmlspecialchars($settings['platform_name'] ?? '') ?>. All rights reserved.
      </p>
    </main>

  </div>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    // Auto-dismiss alerts
    document.querySelectorAll('.alert').forEach(function (alert) {
      // Announce to screen readers immediately, then fade
      setTimeout(function () {
        alert.style.transition = 'opacity 0.4s ease';
        alert.style.opacity = '0';
        setTimeout(function () { alert.style.display = 'none'; }, 400);
      }, 6000);
    });

    // TOTP / recovery toggle
    const totpForm     = document.getElementById('totpForm');
    const recoveryForm = document.getElementById('recoveryForm');
    const useRecovery  = document.getElementById('useRecoveryLink');
    const useTotp      = document.getElementById('useTotpLink');

    if (useRecovery) {
      useRecovery.addEventListener('click', function (e) {
        e.preventDefault();
        totpForm.style.display = 'none';
        recoveryForm.style.display = 'flex';
        document.getElementById('recovery_code').focus();
      });
    }
    if (useTotp) {
      useTotp.addEventListener('click', function (e) {
        e.preventDefault();
        recoveryForm.style.display = 'none';
        totpForm.style.display = 'flex';
        document.getElementById('totp_code').focus();
      });
    }

    // Password visibility toggle
    const toggleBtn   = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    if (toggleBtn && passwordInput) {
      toggleBtn.addEventListener('click', function () {
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        const icon = this.querySelector('i');
        icon.classList.toggle('fa-eye', !isPassword);
        icon.classList.toggle('fa-eye-slash', isPassword);
      });
    }

    // OTP auto-formatting: spaces every digit for readability isn't needed,
    // but focus the input on page load for faster UX
    const totpInput = document.getElementById('totp_code');
    if (totpInput) totpInput.focus();
  });
  </script>
</body>
</html>