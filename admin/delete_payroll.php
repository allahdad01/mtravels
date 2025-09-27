<?php
// Initialize the session
session_start();
$tenant_id = $_SESSION['tenant_id'];

// Include config file
require_once "../../config.php";

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
                header("location: payroll.php?error=1");
                exit();
            }
            
            // Start transaction
            mysqli_begin_transaction($conection_db);
            
            try {
                // First, delete payroll details
                $delete_details_sql = "DELETE FROM payroll_details WHERE payroll_id = ? AND tenant_id = ?";
                
                if ($delete_details_stmt = mysqli_prepare($conection_db, $delete_details_sql)) {
                    mysqli_stmt_bind_param($delete_details_stmt, "ii", $payroll_id, $tenant_id);
                    mysqli_stmt_execute($delete_details_stmt);
                    mysqli_stmt_close($delete_details_stmt);
                }
                
                // Then, delete payroll record
                $delete_payroll_sql = "DELETE FROM payroll_records WHERE id = ? AND tenant_id = ?";
                
                if ($delete_payroll_stmt = mysqli_prepare($conection_db, $delete_payroll_sql)) {
                    mysqli_stmt_bind_param($delete_payroll_stmt, "ii", $payroll_id, $tenant_id);
                    mysqli_stmt_execute($delete_payroll_stmt);
                    mysqli_stmt_close($delete_payroll_stmt);
                }
                
                // Commit transaction
                mysqli_commit($conection_db);
                
                // Redirect to payroll page
                header("location: payroll.php?deleted=1");
                exit();
            } catch (Exception $e) {
                // Roll back transaction on error
                mysqli_rollback($conection_db);
                echo "Error: " . $e->getMessage();
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