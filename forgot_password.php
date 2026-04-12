<?php
// Start session before any output or includes
session_start();

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

// Include config file
require_once "config.php";
require_once "includes/db.php";
require_once "includes/functions.php";

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
$show_reset_form = false;
$reset_email = '';
$reset_token = '';

// Initialize global variables for email tracking
$tenant_id = isset($_SESSION['tenant_id']) ? intval($_SESSION['tenant_id']) : null;
$branch_id = isset($_SESSION['branch_id']) ? intval($_SESSION['branch_id']) : null;

// Fetch settings data
try {
    $settingStmt = $pdo->query("SELECT `key`, `value` FROM platform_settings");
    $settings = $settingStmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $settings = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $message = "Security token expired. Please try again.";
            $message_type = 'error';
        } elseif (isset($_POST['action']) && $_POST['action'] === 'request') {
            
            // Request password reset
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';

            if (empty($email)) {
                $message = "Please enter your email address.";
                $message_type = 'error';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "Please enter a valid email address.";
                $message_type = 'error';
            } else {
                
                // Rate limiting: Check if too many reset requests from this IP
                $rate_limit_check = false;
                try {
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    
                    // Check recent password reset requests from this IP
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as count FROM password_reset_requests 
                        WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    ");
                    $stmt->execute([$ip_address]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result['count'] >= 5) {
                        $rate_limit_check = true;
                    } else {
                        error_log("Rate limit check passed. Current count: {$result['count']}/5");
                    }
                } catch (Exception $e) {
                    error_log("Warning: Rate limit check failed: " . $e->getMessage());
                    // Don't block if rate limit table doesn't exist yet
                }
                
                if ($rate_limit_check) {
                    $message = "Too many password reset requests. Please try again in 1 hour.";
                    $message_type = 'error';
                } else {
                    // Check if user exists
                    try {
                        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ? AND fired != 1");
                        $stmt->execute([$email]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        $message = "An error occurred while processing your request.";
                        $message_type = 'error';
                        $user = null;
                    }
                }

                if ($user) {
                    try {
                        
                        // Generate reset token
                        $reset_token = bin2hex(random_bytes(32));
                        $token_hash = hash('sha256', $reset_token);

                        // Store reset token in database (HASHED)
                        try {
                            // Calculate expiry in database to avoid timezone issues
                            $stmt = $pdo->prepare("
                                INSERT INTO password_resets (user_id, token, token_expiry, created_at)
                                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())
                                ON DUPLICATE KEY UPDATE
                                token = VALUES(token),
                                token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR),
                                created_at = NOW()
                            ");
                            $stmt->execute([$user['id'], $token_hash]);
                        } catch (Exception $e) {
                            $message = "An error occurred while processing your request.";
                            $message_type = 'error';
                            throw $e;
                        }

                        // Build reset link
                        try {
                            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                            
                            $reset_link = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/almoqadas/mtravels/reset_password.php?token=' . urlencode($reset_token);
                            
                        } catch (Exception $e) {
                            $message = "An error occurred while processing your request.";
                            $message_type = 'error';
                            throw $e;
                        }

                        // Prepare email
                        try {
                            $subject = "Password Reset Request";
                            $body = "
                            <html>
                            <body style=\"font-family: Arial, sans-serif;\">
                                <h2>Password Reset Request</h2>
                                <p>Hello " . htmlspecialchars($user['name']) . ",</p>
                                <p>We received a request to reset your password. Click the link below to reset it:</p>
                                <p><a href=\"" . htmlspecialchars($reset_link) . "\" style=\"background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;\">Reset Password</a></p>
                                <p>Or copy and paste this link in your browser:</p>
                                <p><small>" . htmlspecialchars($reset_link) . "</small></p>
                                <p><strong>This link will expire in 1 hour.</strong></p>
                                <p>If you didn't request this, please ignore this email or contact support.</p>
                                <hr>
                                <p style=\"font-size: 12px; color: #666;\">" . htmlspecialchars($settings['platform_name'] ?? 'MTravels') . "</p>
                            </body>
                            </html>
                            ";
                        } catch (Exception $e) {
                            $message = "An error occurred while processing your request.";
                            $message_type = 'error';
                            throw $e;
                        }

                        // Send email
                        try {
                            $emailResult = sendEmail($email, $subject, $body, true, 'password_reset', $user['name']);
                        
                            
                            $message = "If an account with this email exists, you will receive a password reset link.";
                            $message_type = 'success';
                        } catch (Exception $e) {
                            $message = "If an account with this email exists, you will receive a password reset link.";
                            $message_type = 'success'; // Show same message for security
                        }
                        
                    } catch (Exception $e) {
                        $message = "An error occurred while processing your request.";
                        $message_type = 'error';
                    }
                } else {
                      // Log the rate limit anyway for security tracking
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO password_reset_requests (ip_address, email, created_at) 
                            VALUES (?, ?, NOW())
                        ");
                        $stmt->execute([$_SERVER['REMOTE_ADDR'], $email]);
                    } catch (Exception $e) {
                        error_log("Warning: Could not log reset request: " . $e->getMessage());
                    }
                    $message = "If an account with this email exists, you will receive a password reset link.";
                    $message_type = 'success';
                }
                
                // Log successful reset request for rate limiting (when user is found)
                if ($user) {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO password_reset_requests (ip_address, email, created_at) 
                            VALUES (?, ?, NOW())
                        ");
                        $stmt->execute([$_SERVER['REMOTE_ADDR'], $email]);
                    } catch (Exception $e) {
                        error_log("Warning: Could not log reset request: " . $e->getMessage());
                    }
                }
            }
        }
    } catch (Exception $e) {
        $message = "An error occurred: " . htmlspecialchars($e->getMessage());
        $message_type = 'error';
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
    <title>Forgot Password - <?= htmlspecialchars($settings['platform_name'] ?? 'MTravels') ?></title>
    <!-- Favicon -->
    <link rel="icon" href="uploads/logo/<?= htmlspecialchars($settings['platform_logo'] ?? 'default-logo.png') ?>" type="image/x-icon">
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 0;
            background: #f5f5f5;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .sign-in-form {
            max-width: 100%;
        }
        
        .input-field {
            margin-bottom: 15px;
        }
        
        .forgot-link {
            text-align: center;
            margin-top: 15px;
        }
        .forgot-link a {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        .forgot-link a:hover {
            text-decoration: underline;
        }
        
        /* Mobile responsive styles */
        @media (max-width: 768px) {
            .left-panel {
                display: none;
            }
            
            .panels-container {
                display: none;
            }
            
            .alert {
                padding: 12px;
                font-size: 14px;
            }
            
            .sign-in-form p {
                font-size: 14px;
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .left-panel {
                display: none;
            }
            
            .panels-container {
                display: none;
            }
            
            .container {
                height: auto;
                min-height: 100vh;
            }
            
            .forms-container {
                width: 100%;
                height: auto;
            }
            
            .alert {
                padding: 10px;
                font-size: 13px;
            }
            
            .sign-in-form {
                padding: 30px 20px;
            }
            
            .sign-in-form p {
                font-size: 13px;
                margin-bottom: 12px;
                line-height: 1.4;
            }
            
            .btn.solid {
                padding: 12px 20px;
                font-size: 16px;
            }
            
            .forgot-link {
                margin-top: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="forms-container">
            <div class="signin-signup">
                <form action="forgot_password.php" method="post" class="sign-in-form">
                    <h2 class="title">Reset Password</h2>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?= htmlspecialchars($message_type) ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="request">

                    <p style="margin-bottom: 25px; color: #666; font-size: 14px; line-height: 1.5;">
                        Enter your email address and we'll send you a link to reset your password.
                    </p>

                    <div class="input-field">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="Email Address" required autocomplete="email" />
                    </div>

                    <input type="submit" value="Send Reset Link" class="btn solid" />

                    <div class="forgot-link">
                        <a href="login.php">Back to Login</a>
                    </div>
                </form>
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
                    <h3>Need Help?</h3>
                    <p>No problem! We'll help you reset your password in just a few steps.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => document.querySelectorAll('.alert').forEach(a => a.style.display='none'), 5000);
    });
    </script>
</body>
</html>
