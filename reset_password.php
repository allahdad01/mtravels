<?php
// Start session before any other operations
session_start();

// Enable detailed error reporting for debugging
ini_set('display_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Include config file
require_once "config.php";
require_once "includes/db.php";
require_once "includes/functions.php";
require_once "includes/PasswordValidator.php";

// Disable error display in production
if (!defined('APP_ENV')) {
    define('APP_ENV', getenv('APP_ENV') ?: 'production');
}
if (APP_ENV !== 'development') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
}
error_reporting(E_ALL);

// Force HTTPS in production
if (APP_ENV !== 'development' && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
    if (strpos($_SERVER['HTTP_HOST'], 'localhost') === false && strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === false) {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Add security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
if (APP_ENV !== 'development') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self'");

// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$message_type = '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$valid_token = false;
$user_id = null;
$reset_success = false;

// Fetch settings data
try {
    $settingStmt = $pdo->query("SELECT `key`, `value` FROM platform_settings");
    $settings = $settingStmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
    $settings = [];
}

// Validate token
if (!empty($token)) {
    try {
        // Log reset attempt
        error_log("[SECURITY] Password reset page accessed from IP: {$_SERVER['REMOTE_ADDR']}, Token length: " . strlen($token));
        
        // Hash the token from URL
        $token_hash = hash('sha256', $token);
        
        $stmt = $pdo->prepare("
            SELECT user_id FROM password_resets 
            WHERE token = ? AND token_expiry > NOW() AND used = 0
            LIMIT 1
        ");
        $stmt->execute([$token_hash]);
        $reset_record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reset_record) {
            $valid_token = true;
            $user_id = $reset_record['user_id'];
            error_log("[SECURITY] Valid password reset token validated for user_id: {$user_id}");
        } else {
            $message = "Invalid or expired reset link. Please request a new one.";
            $message_type = 'error';
            error_log("[SECURITY] Invalid/expired password reset token attempt from IP: {$_SERVER['REMOTE_ADDR']}");
        }
    } catch (PDOException $e) {
        error_log("Error validating reset token: " . $e->getMessage());
        $message = "An error occurred. Please try again.";
        $message_type = 'error';
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Security token expired. Please try again.";
        $message_type = 'error';
    } else {
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';

        // Validate password using PasswordValidator class
        $errors = [];

        if (empty($password)) {
            $errors[] = "Password is required.";
        } else {
            // Use the strong PasswordValidator
            $validation = PasswordValidator::validate($password);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if (empty($password_confirm)) {
            $errors[] = "Please confirm your password.";
        } elseif ($password !== $password_confirm) {
            $errors[] = "Passwords do not match.";
        }

        if (!empty($errors)) {
            $message = implode("<br>", $errors);
            $message_type = 'error';
            error_log("[SECURITY] Password validation failed for user_id: {$user_id} - Errors: " . implode(", ", $errors));
        } else {
            try {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$password_hash, $user_id]);

                $markStmt = $pdo->prepare("UPDATE password_resets SET used = 1, updated_at = NOW() WHERE token = ?");
                $markStmt->execute([$token_hash]);

                // Invalidate all existing sessions for this user (security best practice)
                try {
                    $delStmt = $pdo->prepare("DELETE FROM sessions WHERE user_id = ?");
                    $delStmt->execute([$user_id]);
                } catch (Exception $e) {
                    error_log("Warning: Could not invalidate sessions: " . $e->getMessage());
                }

                error_log("[SECURITY] Password successfully reset for user_id: {$user_id} from IP: {$_SERVER['REMOTE_ADDR']}");
                $message = "Your password has been reset successfully. Redirecting to login...";
                $message_type = 'success';
                $reset_success = true;
                $valid_token = false;

            } catch (PDOException $e) {
                error_log("Error updating password: " . $e->getMessage());
                $message = "An error occurred while resetting your password. Please try again.";
                $message_type = 'error';
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="login/style.css" />
    <title>Reset Password - <?= htmlspecialchars($settings['platform_name'] ?? 'MTravels') ?></title>
    <!-- Favicon -->
    <link rel="icon" href="uploads/logo/<?= htmlspecialchars($settings['platform_logo'] ?? 'default-logo.png') ?>" type="image/x-icon">
  </head>
  <body>
    <div class="container">
      <div class="forms-container">
        <div class="signin-signup">
          <?php if ($valid_token && !$reset_success): ?>
            <form action="" method="post" class="sign-in-form" autocomplete="off">
              <h2 class="title">Reset Password</h2>

              <?php if (!empty($message)): ?>
                <div class="alert alert-<?= htmlspecialchars($message_type) ?>">
                  <?= $message ?>
                </div>
              <?php endif; ?>

              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

              <div class="input-field" style="position: relative;">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="New Password" required id="password" autocomplete="new-password" />
                <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                  <i class="fas fa-eye"></i>
                </button>
              </div>

              <div class="input-field">
                <i class="fas fa-lock"></i>
                <input type="password" name="password_confirm" placeholder="Confirm Password" required id="password_confirm" autocomplete="new-password" />
              </div>

              <div class="password-requirements">
                 <p>Password Requirements:</p>
                 <div class="requirement" id="req-length">
                   <span class="requirement-icon">✗</span>
                   <span>At least 12 characters</span>
                 </div>
                 <div class="requirement" id="req-uppercase">
                   <span class="requirement-icon">✗</span>
                   <span>One uppercase letter (A-Z)</span>
                 </div>
                 <div class="requirement" id="req-lowercase">
                   <span class="requirement-icon">✗</span>
                   <span>One lowercase letter (a-z)</span>
                 </div>
                 <div class="requirement" id="req-number">
                   <span class="requirement-icon">✗</span>
                   <span>One number (0-9)</span>
                 </div>
                 <div class="requirement" id="req-special">
                   <span class="requirement-icon">✗</span>
                   <span>One special character (!@#$%^&*...)</span>
                 </div>
                 <div class="requirement" id="req-match">
                   <span class="requirement-icon">✗</span>
                   <span>Passwords match</span>
                 </div>
               </div>

              <input type="submit" value="Reset Password" class="btn solid" />

              <div style="text-align: center; margin-top: 15px;">
                <a href="login.php" style="font-size: 14px; color: #666; text-decoration: none; transition: color 0.3s; font-weight: bold;">Back to Login</a>
              </div>
            </form>
          <?php else: ?>
            <div style="text-align: center; padding: 3rem 2rem;">
              <?php if ($reset_success || ($message_type === 'success' && !$valid_token)): ?>
                <i class="fas fa-check-circle" style="font-size: 64px; color: #2ed8b6; margin-bottom: 20px; display: block;"></i>
                <h2 class="title" style="color: #2ed8b6; margin-bottom: 15px;">Password Reset!</h2>
                <p style="color: #666; margin-bottom: 30px; font-size: 1rem;">Your password has been reset successfully. You will be redirected to login page shortly.</p>
              <?php else: ?>
                <i class="fas fa-exclamation-circle" style="font-size: 64px; color: #ef4444; margin-bottom: 20px; display: block;"></i>
                <h2 class="title" style="color: #ef4444; margin-bottom: 15px;">Invalid Link</h2>
                <?php if (!empty($message)): ?>
                  <p style="color: #666; margin-bottom: 30px; font-size: 1rem;"><?= htmlspecialchars($message) ?></p>
                <?php else: ?>
                  <p style="color: #666; margin-bottom: 30px; font-size: 1rem;">The reset link is invalid or has expired.</p>
                <?php endif; ?>
              <?php endif; ?>

              <div style="text-align: center; margin-top: 20px;">
                <a href="login.php" style="font-size: 14px; color: #4099ff; text-decoration: none; transition: color 0.3s; font-weight: bold;">Go to Login</a>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="panels-container">
        <div class="panel left-panel">
          <div class="content">
            <img src="uploads/logo/<?= htmlspecialchars($settings['platform_logo'] ?? 'default-logo.png') ?>" alt="Platform Logo" class="platform-logo" style="max-width: 200px; margin-bottom: 1rem; background-color: #fff; border-radius: 50%; padding: 5px;">
            <h3><?= htmlspecialchars($settings['platform_name'] ?? 'MTravels') ?></h3>
            <p><?= htmlspecialchars($settings['platform_description'] ?? '') ?></p>
          </div>
          <img src="login/img/log.svg" class="image" alt="" />
        </div>
        <div class="panel right-panel">
          <div class="content">
            <h3>Secure Your Account</h3>
            <p>Create a strong password to protect your account.</p>
          </div>
        </div>
      </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => alert.style.display = 'none', 500);
            });
        }, 5000);

        // Redirect on successful reset after 3 seconds
        const successMsg = document.querySelector('.alert-danger');
        if (successMsg && successMsg.textContent.includes('successfully')) {
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 3000);
        }

        // Password visibility toggle
        const togglePasswordBtn = document.querySelector('.toggle-password');
        const passwordInput = document.getElementById('password');
        
        if (togglePasswordBtn && passwordInput) {
            togglePasswordBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        }

        // Real-time password validation
        const passwordInputField = document.getElementById('password');
        const confirmInput = document.getElementById('password_confirm');
        
        if (passwordInputField) {
             function validatePassword() {
                 const password = passwordInputField.value;
                 
                 const hasLength = password.length >= 12;
                 const hasUppercase = /[A-Z]/.test(password);
                 const hasLowercase = /[a-z]/.test(password);
                 const hasNumber = /[0-9]/.test(password);
                 const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\,.<>\/?]/.test(password);
                 const matchesConfirm = confirmInput && password === confirmInput.value && password.length > 0;
                 
                 updateRequirement('req-length', hasLength);
                 updateRequirement('req-uppercase', hasUppercase);
                 updateRequirement('req-lowercase', hasLowercase);
                 updateRequirement('req-number', hasNumber);
                 updateRequirement('req-special', hasSpecial);
                 if (confirmInput) {
                     updateRequirement('req-match', matchesConfirm);
                 }
                 
                 const submitBtn = document.querySelector('input[type="submit"]');
                 if (submitBtn) {
                     const allMet = hasLength && hasUppercase && hasLowercase && hasNumber && hasSpecial && (!confirmInput || matchesConfirm);
                     submitBtn.disabled = !allMet;
                     submitBtn.style.opacity = allMet ? '1' : '0.5';
                     submitBtn.style.cursor = allMet ? 'pointer' : 'not-allowed';
                 }
             }
            
            function updateRequirement(id, met) {
                const element = document.getElementById(id);
                if (element) {
                    const icon = element.querySelector('.requirement-icon');
                    if (met) {
                        element.classList.add('met');
                        icon.textContent = '✓';
                    } else {
                        element.classList.remove('met');
                        icon.textContent = '✗';
                    }
                }
            }
            
            passwordInputField.addEventListener('input', validatePassword);
            if (confirmInput) {
                confirmInput.addEventListener('input', validatePassword);
            }
            
            validatePassword();
        }

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const activeElement = document.activeElement;
                if (activeElement.tagName === 'INPUT' && activeElement.type !== 'submit') {
                    e.preventDefault();
                    const form = activeElement.closest('form');
                    if (form) {
                        const inputs = Array.from(form.querySelectorAll('input[type="password"]'));
                        const currentIndex = inputs.indexOf(activeElement);
                        
                        if (currentIndex < inputs.length - 1) {
                            inputs[currentIndex + 1].focus();
                        } else {
                            const submitBtn = form.querySelector('input[type="submit"]');
                            if (submitBtn && !submitBtn.disabled) {
                                submitBtn.click();
                            }
                        }
                    }
                }
            }
        });
    });
    </script>
  </body>
</html>
