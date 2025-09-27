<?php
// Initialize the session
session_start();
$tenant_id = $_SESSION['tenant_id'];

// Process delete operation after confirmation
if (isset($_GET["id"]) && !empty($_GET["id"])) {
    // Include config file
    require_once "../includes/db.php";
    
    // Prepare a delete statement
    $sql = "DELETE FROM salary_deductions WHERE id = ? AND tenant_id = ?";
    
    if ($stmt = mysqli_prepare($conection_db, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "ii", $param_id, $tenant_id);
        
        // Set parameters
        $param_id = trim($_GET["id"]);
        
        // Attempt to execute the prepared statement
        if (mysqli_stmt_execute($stmt)) {
            // Records deleted successfully. Redirect to landing page
            header("location: manage_deductions.php?deleted=1");
            exit();
        } else {
            echo "Oops! Something went wrong. Please try again later.";
        }
    }
     
    // Close statement
    mysqli_stmt_close($stmt);
    
    // Close connection
    mysqli_close($conection_db);
} else {
    // URL doesn't contain id parameter
    header("location: manage_deductions.php");
    exit();
}
?> 