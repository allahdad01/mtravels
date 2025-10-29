<?php
// Initialize the session
session_start();
$tenant_id = $_SESSION['tenant_id'];

// Include config file
require_once "../../includes/db.php";

// Fetch user data with proper error handling
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$_SESSION['user_id'], $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Log the error
        error_log("User not found: " . $_SESSION['user_id'] . " - IP: " . $_SERVER['REMOTE_ADDR'] . " - Tenant ID: " . $tenant_id);
        
        // Redirect to login if user not found
        session_destroy();
        header('Location: ../login.php');
        exit();
    }
} catch (PDOException $e) {
    // Log the error without exposing details
    error_log("Database Error in dashboard.php: " . $e->getMessage());
    
    $user = null;
    // Show generic error message
    $error_message = "A system error occurred. Please try again later.";
}

// Fetch settings data
try {
    $settingStmt = $pdo->query("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
    $settings = ['agency_name' => 'Default Name'];
}
$profilePic = !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : 'default-avatar.jpg';
$imagePath = "../../assets/images/user/" . $profilePic;

// Check if id parameter is present
if (!isset($_GET["id"]) || empty(trim($_GET["id"]))) {
    header("location: payroll.php");
    exit();
}

// Get payroll record
$payroll_id = trim($_GET["id"]);
$sql = "SELECT * FROM payroll_records WHERE id = ? AND tenant_id = ?";

if ($stmt = mysqli_prepare($conection_db, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $payroll_id, $tenant_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $payroll = $row;
            
            // Check if payroll is in draft status
            if ($payroll["status"] !== "draft") {
                header("location: payroll.php");
                exit();
            }
            
            // Update payroll status to processed
            $update_sql = "UPDATE payroll_records SET status = 'processed' WHERE id = ? AND tenant_id = ?";
            
            if ($update_stmt = mysqli_prepare($conection_db, $update_sql)) {
                mysqli_stmt_bind_param($update_stmt, "ii", $payroll_id, $tenant_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    // Redirect to view payroll page
                    header("location: view_payroll.php?id=$payroll_id&success=1");
                    exit();
                } else {
                    echo "Oops! Something went wrong. Please try again later.";
                }
                
                mysqli_stmt_close($update_stmt);
            }
        } else {
            header("location: payroll.php");
            exit();
        }
    } else {
        echo "Oops! Something went wrong. Please try again later.";
    }
    
    mysqli_stmt_close($stmt);
}

// Close connection
mysqli_close($conection_db);
?> 