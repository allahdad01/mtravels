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
    <title><?= htmlspecialchars($settings['platform_name']) ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" href="uploads/logo/<?= htmlspecialchars($settings['platform_logo'] ?? 'default-logo.png') ?>" type="image/x-icon">

    <!-- Google Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/fonts/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/plugins/bootstrap/css/bootstrap.min.css">

    <!-- Custom Styles -->
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #4680ff 0%, #6ec1ff 100%);
            overflow: hidden;
            position: relative;
        }

        /* Decorative Background Shapes */
        body::before, body::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 6s ease-in-out infinite alternate;
        }
        body::before {
            width: 300px; height: 300px;
            top: -80px; left: -80px;
        }
        body::after {
            width: 200px; height: 200px;
            bottom: -60px; right: -60px;
            animation-delay: 2s;
        }
        @keyframes float {
            from { transform: translateY(0px); }
            to { transform: translateY(25px); }
        }

        .login-container {
            display: flex;
            width: 100%;
            max-width: 900px;
            background: #fff;
            border-radius: 1.5rem;
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
            overflow: hidden;
        }

        /* Left side (branding) */
        .login-left {
            flex: 1;
            background: url("assets/images/travel-bg.jpg") center/cover no-repeat;
            color: #fff;
            padding: 3rem 2rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }
        .login-left::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(70, 128, 255, 0.6);
        }
        .login-left-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }
        .login-left h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .login-left p {
            font-size: 1rem;
            opacity: 0.95;
        }
        .login-left-content img.platform-logo {
            max-width: 200px;
            margin-bottom: 1rem;
            max-height: 100px;
            object-fit: contain;
        }

        /* Right side (form) */
        .login-right {
            flex: 1;
            padding: 3rem 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            animation: fadeInUp 0.8s ease;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card h3 {
            font-weight: 700;
            color: #333;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .form-control {
            border-radius: .75rem;
            padding: .75rem 1rem;
            border: 1px solid #ddd;
            transition: all 0.2s;
        }
        .form-control:focus {
            border-color: #4680ff;
            box-shadow: 0 0 0 0.25rem rgba(70,128,255,0.25);
        }

        .btn-primary {
            width: 100%;
            border-radius: .75rem;
            padding: .75rem;
            font-weight: 600;
            background: linear-gradient(135deg, #4680ff, #6ec1ff);
            border: none;
            transition: transform 0.2s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
        }

        .alert {
            font-size: 14px;
            border-radius: .75rem;
        }
        .link-text {
            font-size: .9rem;
            color: #4680ff;
            text-decoration: none;
        }
        .link-text:hover {
            text-decoration: underline;
        }

        /* ✅ Mobile Responsive */
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 95%;
            }
            .login-left {
                display: none; /* hide left on mobile */
            }
            .login-right {
                padding: 2rem 1.5rem;
            }
            .login-card {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="login-container">
    <!-- Branding -->
    <div class="login-left">
        <div class="login-left-content">
            <img src="uploads/logo/<?= htmlspecialchars($settings['platform_logo'] ?? 'default-logo.png') ?>" alt="Platform Logo" class="platform-logo">
            <h1><?= htmlspecialchars($settings['platform_name'] ?? '') ?></h1>
            <p><?= htmlspecialchars($settings['platform_description'] ?? '') ?></p>
        </div>
    </div>

    <!-- Login Form -->
    <div class="login-right">
        <div class="login-card">
            <?php if ($totp_verification): ?>
                <h3><i class="fas fa-shield-alt text-primary me-2"></i>Two-Factor Authentication</h3>
                <?php if (!empty($totp_err)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($totp_err) ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="mb-3">
                        <input type="text" class="form-control" name="totp_code" placeholder="Enter 6-digit code"
                               inputmode="numeric" pattern="[0-9]*" maxlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key me-2"></i>Verify
                    </button>
                    <div class="mt-3 text-center">
                        <a href="#" id="useRecoveryLink" class="link-text">Use recovery code instead</a>
                    </div>
                </form>

                <!-- Recovery form -->
                <form method="post" id="recoveryForm" style="display:none;">
                    <h3><i class="fas fa-unlock-alt text-primary me-2"></i>Recovery Code</h3>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="is_recovery" value="1">
                    <div class="mb-3">
                        <input type="text" class="form-control" name="totp_code" placeholder="XXXX-XXXX-XXXX-XXXX">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check me-2"></i>Verify
                    </button>
                    <div class="mt-3 text-center">
                        <a href="#" id="useTotpLink" class="link-text">Back to authentication code</a>
                    </div>
                </form>
            <?php else: ?>
                <h3><i class="fas fa-user-circle text-primary me-2"></i>Login</h3>
                <?php if (!empty($email_err) || !empty($password_err)): ?>
                    <div class="alert alert-danger">
                        <?= !empty($email_err) ? "<div>".htmlspecialchars($email_err)."</div>" : "" ?>
                        <?= !empty($password_err) ? "<div>".htmlspecialchars($password_err)."</div>" : "" ?>
                    </div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="mb-3">
                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($email); ?>" placeholder="Email">
                    </div>
                    <div class="mb-3">
                        <input type="password" class="form-control" name="password" placeholder="Password">
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="remember" id="remember-me">
                        <label for="remember-me" class="form-check-label">Remember me</label>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- JS -->
<script src="assets/js/vendor-all.min.js"></script>
<script src="assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
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