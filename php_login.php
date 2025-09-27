<?php
ob_start();

// Set secure session parameters BEFORE starting the session
ini_set('session.cookie_httponly', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

session_start();

require_once "config.php";
require_once "includes/db.php";
require_once "includes/totp_helper.php";

// Create TOTP helper
$totpHelper = new TotpHelper($pdo, $conection_db);

// Regenerate session ID periodically for security
if (!isset($_SESSION["last_regeneration"]) || (time() - $_SESSION["last_regeneration"] > 300)) {
    session_regenerate_id(true);
    $_SESSION["last_regeneration"] = time();
}

$email = $password = $totp_code = "";
$email_err = $password_err = $totp_err = "";

// Check if we're in the TOTP verification phase
$totp_verification = isset($_SESSION["totp_verification"]) && $_SESSION["totp_verification"] === true;

// Brute force protection
function checkBruteForce($email, $conection_db) {
    // Get timestamp of current time minus 30 minutes
    $valid_attempts_window = date("Y-m-d H:i:s", time() - (30 * 60));
    
    if ($stmt = $conection_db->prepare("SELECT COUNT(*) AS attempts FROM login_attempts WHERE email = ? AND time > ?")) {
        $stmt->bind_param("ss", $email, $valid_attempts_window);
        $stmt->execute();
        $stmt->bind_result($attempts);
        $stmt->fetch();
        $stmt->close();
        
        // If there have been 5 or more failed attempts
        if ($attempts >= 5) {
            return true;
        } else {
            return false;
        }
    }
}

// Record failed login attempt
function recordFailedAttempt($email, $conection_db) {
    $time = date("Y-m-d H:i:s");
    if ($stmt = $conection_db->prepare("INSERT INTO login_attempts (email, time, ip_address) VALUES (?, ?, ?)")) {
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param("sss", $email, $time, $ip_address);
        $stmt->execute();
        $stmt->close();
    }
}

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
    
    // TOTP Verification Phase
    if ($totp_verification && isset($_POST["totp_code"])) {
        $totp_code = trim($_POST["totp_code"]);
        
        // Validate TOTP code
        if (empty($totp_code)) {
            $totp_err = "Please enter the authentication code.";
        } else {
            // Check if user wants to use a recovery code
            $is_recovery = isset($_POST["is_recovery"]) && $_POST["is_recovery"] == "1";
            
            if ($is_recovery) {
                // Verify recovery code
                if ($totpHelper->verifyRecoveryCode(
                    $_SESSION["pending_user_id"], 
                    $_SESSION["pending_user_type"], 
                    $totp_code
                )) {
                    // Recovery code is valid, complete login
                    completeLogin();
                } else {
                    $totp_err = "Invalid recovery code.";
                }
            } else {
                // Verify TOTP code
                if ($totpHelper->verifyCode(
                    $_SESSION["pending_user_id"], 
                    $_SESSION["pending_user_type"], 
                    $totp_code
                )) {
                    // TOTP code is valid, complete login
                    completeLogin();
                } else {
                    $totp_err = "Invalid authentication code.";
                }
            }
        }
    } 
    // Regular Login Phase
    else {
        // Validate email
        if (empty(trim($_POST["email"]))) {
            $email_err = "Please enter your email.";
        } else {
            $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);

            // Check if account is locked from too many attempts
            if (checkBruteForce($email, $conection_db)) {
                $email_err = "Account is temporarily locked due to too many failed attempts. Please try again later or reset your password.";
            }
        }
        
        // Validate password
        if (empty(trim($_POST["password"]))) {
            $password_err = "Please enter your password.";
        } else {
            $password = trim($_POST["password"]);
        }

        // Authenticate user if no errors
        if (empty($email_err) && empty($password_err)) {
            // First check users table
            $sql = "SELECT id, tenant_id, name, email, password, role, totp_enabled FROM users WHERE email = ?";
            
            if ($stmt = $conection_db->prepare($sql)) {
                $stmt->bind_param("s", $email);
                if ($stmt->execute()) {
                    $stmt->store_result();
                    if ($stmt->num_rows == 1) {
                        $stmt->bind_result($id, $tenant_id, $name, $email, $hashed_password, $role, $totp_enabled);
                        if ($stmt->fetch() && password_verify($password, $hashed_password)) {
                            // Clear any stored login attempts
                            if ($clear_stmt = $conection_db->prepare("DELETE FROM login_attempts WHERE email = ?")) {
                                $clear_stmt->bind_param("s", $email);
                                $clear_stmt->execute();
                                $clear_stmt->close();
                            }
                            
                            // Always require TOTP if enabled, never skip this step
                            if ($totp_enabled) {
                                // Store user information for TOTP verification
                                $_SESSION["totp_verification"] = true;
                                $_SESSION["pending_user_id"] = $id;
                                $_SESSION["pending_user_name"] = $name;
                                $_SESSION["pending_user_email"] = $email;
                                $_SESSION["pending_user_role"] = $role;
                                $_SESSION["pending_user_type"] = "staff";
                                
                                // Redirect to same page to show TOTP form
                                header("Location: " . $_SERVER["PHP_SELF"]);
                                exit;
                            }
                            
                            // Set session variables for staff user
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $id;
                            $_SESSION["tenant_id"] = $tenant_id;
                            $_SESSION["name"] = $name;
                            $_SESSION["role"] = $role;
                            $_SESSION["user_type"] = "staff";
                            $_SESSION["login_time"] = time();
                            
                            // Regenerate session ID for security
                            session_regenerate_id(true);

                            // Redirect based on role
                            switch(strtolower($role)) {
                                case 'super_admin':
                                    header("location: super_admin/dashboard.php");
                                    break;
                                case 'admin':
                                    header("location: admin/dashboard.php");
                                    break;
                                case 'sales':
                                    header("location: sales/dashboard.php");
                                    break;
                                case 'finance':
                                    header("location: finance/dashboard.php");
                                    break;
                                case 'umrah':
                                    header("location: umrah/dashboard.php");
                                    break;
                                case 'visa':
                                    header("location: visa/dashboard.php");
                                    break;
                                default:
                                    header("location: user/dashboard.php");
                            }
                            exit;
                        } else {
                            recordFailedAttempt($email, $conection_db);
                            $password_err = "The password you entered was not valid.";
                        }
                    }
                    $stmt->close();

                    // If user not found in users table, check clients table
                    if (!isset($_SESSION["loggedin"]) && !isset($_SESSION["totp_verification"])) {
                        $sql = "SELECT id, tenant_id, name, email, password_hash, client_type, totp_enabled FROM clients WHERE email = ?";
                        if ($stmt = $conection_db->prepare($sql)) {
                            $stmt->bind_param("s", $email);
                            if ($stmt->execute()) {
                                $stmt->store_result();
                                if ($stmt->num_rows == 1) {
                                    $stmt->bind_result($id, $tenant_id, $name, $email, $hashed_password, $client_type, $totp_enabled);
                                    if ($stmt->fetch() && password_verify($password, $hashed_password)) {
                                        // Clear any stored login attempts
                                        if ($clear_stmt = $conection_db->prepare("DELETE FROM login_attempts WHERE email = ?")) {
                                            $clear_stmt->bind_param("s", $email);
                                            $clear_stmt->execute();
                                            $clear_stmt->close();
                                        }
                                        
                                        // Always require TOTP if enabled, never skip this step
                                        if ($totp_enabled) {
                                            // Store user information for TOTP verification
                                            $_SESSION["totp_verification"] = true;
                                            $_SESSION["pending_user_id"] = $id;
                                            $_SESSION["pending_user_name"] = $name;
                                            $_SESSION["pending_user_email"] = $email;
                                            $_SESSION["pending_user_role"] = "client";
                                            $_SESSION["pending_user_client_type"] = $client_type;
                                            $_SESSION["pending_user_type"] = "client";
                                            
                                            // Redirect to same page to show TOTP form
                                            header("Location: " . $_SERVER["PHP_SELF"]);
                                            exit;
                                        }
                                        
                                        // Set session variables for client
                                        $_SESSION["loggedin"] = true;
                                        $_SESSION["user_id"] = $id;
                                        $_SESSION["tenant_id"] = $tenant_id;
                                        $_SESSION["name"] = $name;
                                        $_SESSION["role"] = "client";
                                        $_SESSION["client_type"] = $client_type;
                                        $_SESSION["user_type"] = "client";
                                        $_SESSION["login_time"] = time();

                                        // Regenerate session ID for security
                                        session_regenerate_id(true);

                                        // Redirect to client dashboard
                                        header("location: client/dashboard.php");
                                        exit;
                                    } else {
                                        recordFailedAttempt($email, $conection_db);
                                        $password_err = "The password you entered was not valid.";
                                    }
                                } else {
                                    $email_err = "No account found with that email.";
                                }
                            } else {
                                error_log("Database Execution Error: " . $stmt->error);
                                echo "Oops! Something went wrong. Please try again later.";
                            }
                            $stmt->close();
                        }
                    }
                } else {
                    error_log("Database Execution Error: " . $stmt->error);
                    echo "Oops! Something went wrong. Please try again later.";
                }
            }
        }
    }
    $conection_db->close();
}

// Function to complete login after successful TOTP verification
function completeLogin() {
    // Set regular session variables from pending data
    $_SESSION["loggedin"] = true;
    $_SESSION["user_id"] = $_SESSION["pending_user_id"];
    $_SESSION["tenant_id"] = $_SESSION["pending_user_tenant_id"];
    $_SESSION["name"] = $_SESSION["pending_user_name"];
    $_SESSION["role"] = $_SESSION["pending_user_role"];
    $_SESSION["user_type"] = $_SESSION["pending_user_type"];
    $_SESSION["login_time"] = time();
    
    // Add client-specific data if available
    if (isset($_SESSION["pending_user_client_type"])) {
        $_SESSION["client_type"] = $_SESSION["pending_user_client_type"];
    }
    
    // Clear temporary TOTP verification data
    unset($_SESSION["totp_verification"]);
    unset($_SESSION["pending_user_id"]);
    unset($_SESSION["pending_user_tenant_id"]);
    unset($_SESSION["pending_user_name"]);
    unset($_SESSION["pending_user_email"]);
    unset($_SESSION["pending_user_role"]);
    unset($_SESSION["pending_user_type"]);
    if (isset($_SESSION["pending_user_client_type"])) {
        unset($_SESSION["pending_user_client_type"]);
    }
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Redirect based on role
    if ($_SESSION["role"] === "client") {
        header("location: client/dashboard.php");
    } else {
        switch(strtolower($_SESSION["role"])) {
            case 'super_admin':
                header("location: super_admin/dashboard.php");
                break;
            case 'admin':
                header("location: admin/dashboard.php");
                break;
            case 'sales':
                header("location: sales/dashboard.php");
                break;
            case 'finance':
                header("location: finance/dashboard.php");
                break;
            case 'umrah':
                header("location: umrah/dashboard.php");
                break;
            case 'visa':
                header("location: visa/dashboard.php");
                break;
            default:
                header("location: user/dashboard.php");
        }
    }
    exit;
}
?>
