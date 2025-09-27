<?php
// Include session check to ensure this page is only accessible to logged-in users
require_once "includes/session_check.php";
require_once "config.php";
require_once "includes/db.php";
require_once "includes/totp_helper.php";
// Include language helper
require_once 'includes/language_helpers.php';

// Send security headers via PHP instead of META tags
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
// More permissive CSP to allow needed resources
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

// Create TOTP helper
$totpHelper = new TotpHelper($pdo, $conection_db);

// Initialize variables
$success_msg = $error_msg = "";
$totp_enabled = false;
$verification_phase = false;
$qr_code = "";
$recovery_codes = [];
$tenant_id = $_SESSION['tenant_id'];
// Check if user already has TOTP enabled
$user_id = $_SESSION["user_id"];
$user_type = $_SESSION["user_type"];
$username = $_SESSION["name"];


// Check if TOTP is already enabled
$totp_enabled = $totpHelper->isTotpEnabled($user_id, $user_type, $tenant_id);

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // Log potential CSRF attack
        error_log("CSRF attack detected: " . $_SERVER['REMOTE_ADDR']);
        die("Invalid request. Please try again.");
    }
    
    // Regenerate CSRF token to prevent replay attacks
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    // SETUP: Generate new TOTP secret
    if (isset($_POST["action"]) && $_POST["action"] == "setup") {
        // Generate a new TOTP instance
        $totp = $totpHelper->generateSecret($user_id, $user_type, $username, $tenant_id);
        
        if ($totp) {
            // Store in session for verification
            $_SESSION["pending_totp"] = $totp->getProvisioningUri();
            $verification_phase = true;
            
            // Generate QR code for setup
            $qr_code = $totpHelper->generateQrCode($totp->getProvisioningUri());
            
            // Get recovery codes
            $recovery_codes = $totpHelper->getRecoveryCodes($user_id, $user_type, $tenant_id);
        } else {
            $error_msg = "Failed to generate TOTP secret. Please try again.";
        }
    }
    // VERIFY: Verify TOTP code and enable
    else if (isset($_POST["action"]) && $_POST["action"] == "verify") {
        $totp_code = trim($_POST["totp_code"]);
        
        if (empty($totp_code)) {
            $error_msg = "Please enter the verification code.";
        } else {
            // Verify the code
            if ($totpHelper->verifyCode($user_id, $user_type, $totp_code, $tenant_id)) {
                // Enable TOTP for the user
                if ($totpHelper->enableTotp($user_id, $user_type, $tenant_id)) {
                    $success_msg = "Two-factor authentication has been enabled for your account.";
                    $totp_enabled = true;
                    // Clear verification phase
                    $verification_phase = false;
                    unset($_SESSION["pending_totp"]);
                } else {
                    $error_msg = "Failed to enable two-factor authentication. Please try again.";
                }
            } else {
                $error_msg = "Invalid verification code. Please try again.";
                $verification_phase = true;
                
                // Regenerate QR code for another attempt
                if (isset($_SESSION["pending_totp"])) {
                    $qr_code = $totpHelper->generateQrCode($_SESSION["pending_totp"]);
                    // Get recovery codes
                    $recovery_codes = $totpHelper->getRecoveryCodes($user_id, $user_type, $tenant_id);
                }
            }
        }
    }
    // DISABLE: Disable TOTP
    else if (isset($_POST["action"]) && $_POST["action"] == "disable") {
        // Require password confirmation for security
        $password = trim($_POST["password"]);
        
        if (empty($password)) {
            $error_msg = "Please enter your password to disable two-factor authentication.";
        } else {
            // Verify password
            $table = ($user_type == 'staff') ? 'users' : 'clients';
            $password_field = ($user_type == 'staff') ? 'password' : 'password_hash';
            
            $verify_sql = "SELECT {$password_field} FROM {$table} WHERE id = ?";
            $stmt = $pdo->prepare($verify_sql);
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && password_verify($password, $result[$password_field])) {
                // Disable TOTP
                if ($totpHelper->disableTotp($user_id, $user_type, $tenant_id)) {
                    $success_msg = "Two-factor authentication has been disabled for your account.";
                    $totp_enabled = false;
                } else {
                    $error_msg = "Failed to disable two-factor authentication. Please try again.";
                }
            } else {
                $error_msg = "Incorrect password. Two-factor authentication has not been disabled.";
            }
        }
    }
}

// Fetch settings data
try {
    $settingStmt = $pdo->query("SELECT * FROM settings WHERE tenant_id = $tenant_id");
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
    $settings = ['agency_name' => 'Default Name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Two-Factor Authentication Setup - <?= htmlspecialchars($settings['agency_name']) ?></title>
    <!-- Meta -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="Two-Factor Authentication Setup" />
    <!-- Security headers are now sent via PHP instead of META tags -->

    <!-- Favicon icon -->
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <!-- fontawesome icon -->
    <link rel="stylesheet" href="assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <!-- animation css -->
    <link rel="stylesheet" href="assets/plugins/animation/css/animate.min.css">
    <!-- vendor css -->
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
    .setup-page {
        max-width: 600px;
        margin: 50px auto;
    }
    .alert {
        border-radius: 3px;
        padding: 10px;
        margin-bottom: 1rem;
        font-size: 14px;
    }
    .alert-danger {
        background-color: #fff2f2;
        border-left: 3px solid #f44336;
        color: #f44336;
    }
    .alert-success {
        background-color: #f0fff0;
        border-left: 3px solid #4caf50;
        color: #4caf50;
    }
    .qr-container {
        text-align: center;
        margin: 20px 0;
    }
    .qr-code {
        border: 1px solid #ddd;
        padding: 10px;
        display: inline-block;
        background: white;
    }
    .recovery-codes {
        font-family: monospace;
        background-color: #f5f5f5;
        padding: 15px;
        border-radius: 5px;
        margin-top: 20px;
        font-size: 14px;
    }
    .recovery-code {
        display: inline-block;
        width: 48%;
        margin-bottom: 10px;
    }
    .step-instruction {
        margin-bottom: 15px;
        font-size: 15px;
    }
    </style>
</head>

<body>
    <div class="setup-page">
        <div class="card">
            <div class="card-header">
                <h5><?= __('two_factor_authentication') ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($success_msg)): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success_msg); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error_msg); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($totp_enabled): ?>
                    <!-- TOTP Already Enabled -->
                    <div class="text-center mb-4">
                        <i class="fas fa-shield-alt fa-3x text-success"></i>
                        <h3 class="mt-3"><?= __('two_factor_authentication_is_enabled') ?></h3>
                        <p><?= __('your_account_is_currently_secured_with_two_factor_authentication') ?></p>
                    </div>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="disable">
                        
                        <div class="form-group">
                            <label for="password"><?= __('enter_your_password_to_disable_two_factor_authentication') ?></label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-danger mb-4">
                                <i class="fas fa-shield-alt"></i> <?= __('disable_two_factor_authentication') ?>
                            </button>
                        </div>
                    </form>
                    
                <?php elseif ($verification_phase): ?>
                    <!-- TOTP Verification Phase -->
                    <h3><?= __('set_up_two_factor_authentication') ?></h3>
                    
                    <div class="step-instruction">
                        <strong><?= __('step_1') ?>:</strong> <?= __('scan_this_qr_code_with_your_authenticator_app') ?>
                    </div>
                    
                    <div class="qr-container">
                        <div class="qr-code">
                            <img src="<?php echo $qr_code; ?>" alt="QR Code for Two-Factor Authentication">
                        </div>
                    </div>
                    
                    <div class="step-instruction">
                        <strong><?= __('step_2') ?>:</strong> <?= __('enter_the_6_digit_code_from_your_authenticator_app_to_verify_setup') ?>
                    </div>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="verify">
                        
                        <div class="form-group">
                            <input type="text" 
                                   class="form-control" 
                                   name="totp_code" 
                                   placeholder="<?= __('enter_6_digit_code') ?>"
                                   autocomplete="one-time-code"
                                   inputmode="numeric"
                                   pattern="[0-9]*"
                                   maxlength="6"
                                   required>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary mb-4">
                                <i class="fas fa-check"></i> <?= __('verify_and_enable') ?>
                            </button>
                        </div>
                    </form>
                    
                    <div class="step-instruction">
                        <strong><?= __('step_3') ?>:</strong> <?= __('save_these_recovery_codes_in_a_safe_place') ?>
                    </div>
                    
                    <div class="recovery-codes">
                        <?php foreach ($recovery_codes as $code): ?>
                            <div class="recovery-code"><?php echo htmlspecialchars($code); ?></div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="alert alert-danger mt-3">
                        <strong><?= __('important') ?>:</strong> <?= __('these_recovery_codes_will_only_be_shown_once') ?>
                    </div>
                    
                <?php else: ?>
                    <!-- Initial Setup Phase -->
                    <div class="text-center mb-4">
                        <i class="fas fa-shield-alt fa-3x"></i>
                        <h3 class="mt-3"><?= __('enhance_your_account_security') ?></h3>
                        <p><?= __('two_factor_authentication_adds_an_extra_layer_of_security_to_your_account_by_requiring_a_second_verification_step_when_logging_in') ?></p>
                    </div>
                    
                    <div class="mb-4">
                        <h5><?= __('how_it_works') ?>:</h5>
                        <ul>
                            <li><?= __('you_ll_set_up_an_authenticator_app_on_your_phone') ?></li>
                            <li><?= __('when_logging_in_you_ll_need_to_enter_a_code_from_the_app') ?></li>
                            <li><?= __('this_protects_your_account_even_if_your_password_is_compromised') ?></li>
                        </ul>
                    </div>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="action" value="setup">
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary mb-4">
                                <i class="fas fa-shield-alt"></i> <?= __('set_up_two_factor_authentication') ?>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <?php
                    // Determine correct return path based on user role
                    $return_path = '';
                    if ($_SESSION["user_type"] === "client") {
                        $return_path = 'client/dashboard.php';
                    } else {
                        switch(strtolower($_SESSION["role"])) {
                            case 'admin':
                                $return_path = 'admin/dashboard.php';
                                break;
                            case 'sales':
                                $return_path = 'sales/dashboard.php';
                                break;
                            case 'finance':
                                $return_path = 'finance/dashboard.php';
                                break;
                            case 'umrah':
                                $return_path = 'umrah/dashboard.php';
                                break;
                            
                            default:
                                $return_path = 'user/dashboard.php';
                        }
                    }
                    ?>
                    <a href="<?php echo $return_path; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> <?= __('return_to_dashboard') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Required Js -->
    <script src="assets/js/vendor-all.min.js"></script>
    <script src="assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script>
    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            const alerts = document.getElementsByClassName('alert-success');
            for(let alert of alerts) {
                alert.style.transition = 'opacity 0.5s ease-out';
                alert.style.opacity = '0';
                setTimeout(() => alert.style.display = 'none', 500);
            }
        }, 5000);
    });
    </script>
</body>
</html> 