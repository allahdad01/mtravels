<?php
// Start session to access user information
session_start();

// Check if user is logged in
$logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
$user_name = $logged_in ? $_SESSION["name"] : "Guest";
$role = $logged_in ? $_SESSION["role"] : "none";
$tenant_id = $_SESSION['tenant_id'];
// Fetch settings data for site name
require_once "config.php";
require_once "includes/db.php";

try {
    $settingStmt = $pdo->query("SELECT agency_name FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
    $agency_name = htmlspecialchars($settings['agency_name']);
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
    $agency_name = 'Travel Agency';
}

// Log unauthorized access attempt
if ($logged_in) {
    error_log("Unauthorized access attempt: User ID: " . $_SESSION["user_id"] . 
              ", Role: " . $role . ", Attempted Path: " . $_SERVER['REQUEST_URI']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Access Denied - <?= $agency_name ?></title>
    <!-- Meta -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="Access Denied Page" />
    <!-- Security headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">

    <!-- Favicon icon -->
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    <!-- fontawesome icon -->
    <link rel="stylesheet" href="assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <!-- animation css -->
    <link rel="stylesheet" href="assets/plugins/animation/css/animate.min.css">
    <!-- vendor css -->
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .error-card {
            max-width: 600px;
            margin: 100px auto;
            text-align: center;
        }
        .error-icon {
            font-size: 6rem;
            color: #f44336;
            margin-bottom: 1rem;
        }
        .error-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .error-text {
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }
        .btn-back {
            margin-top: 1rem;
        }
    </style>
</head>

<body>
    <div class="auth-wrapper">
        <div class="auth-content">
            <div class="error-card">
                <div class="card">
                    <div class="card-body">
                        <i class="fas fa-exclamation-triangle error-icon"></i>
                        <h3 class="error-title">Access Denied</h3>
                        <p class="error-text">
                            Sorry, <?= htmlspecialchars($user_name) ?>, you don't have permission to access this page.
                        </p>
                        <?php if ($logged_in): ?>
                            <p>Your current role (<?= htmlspecialchars($role) ?>) does not have the required permissions.</p>
                            <?php 
                            // Determine correct dashboard based on role
                            $dashboard_url = '';
                            switch(strtolower($role)) {
                                case 'admin':
                                    $dashboard_url = 'admin/dashboard.php';
                                    break;
                                case 'sales':
                                    $dashboard_url = 'sales/dashboard.php';
                                    break;
                                case 'finance':
                                    $dashboard_url = 'finance/dashboard.php';
                                    break;
                                case 'umrah':
                                    $dashboard_url = 'umrah/dashboard.php';
                                    break;
                                case 'visa':
                                    $dashboard_url = 'visa/dashboard.php';
                                    break;
                                case 'client':
                                    $dashboard_url = 'client/dashboard.php';
                                    break;
                                default:
                                    $dashboard_url = 'login.php';
                            }
                            ?>
                            <a href="<?= $dashboard_url ?>" class="btn btn-primary btn-back">
                                <i class="fas fa-home"></i> Return to Dashboard
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary btn-back">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Required Js -->
    <script src="assets/js/vendor-all.min.js"></script>
    <script src="assets/plugins/bootstrap/js/bootstrap.min.js"></script>
</body>
</html> 