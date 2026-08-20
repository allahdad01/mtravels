<?php
// Initialize the session
session_start();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once __DIR__ . '/../includes/permissions.php';
require_permission('hr.salary');

// Process delete operation after confirmation
if (isset($_GET["id"]) && !empty($_GET["id"])) {
    // Include config file
    require_once "../includes/db.php";
    
    // Prepare a delete statement
    $sql = "DELETE FROM salary_bonuses WHERE id = ? AND tenant_id = ? AND branch_id = ?";

    try {
        $stmt = $pdo->prepare($sql);

        // Set parameters
        $param_id = trim($_GET["id"]);

        // Bind parameters
        $stmt->bindParam(1, $param_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);

        // Attempt to execute the prepared statement
        if ($stmt->execute()) {
            // Records deleted successfully. Redirect to landing page
            header("location: manage_bonuses.php?deleted=1");
            exit();
        } else {
            echo "Oops! Something went wrong. Please try again later.";
        }
    } catch (PDOException $e) {
        echo "Oops! Something went wrong. Please try again later.";
    }
} else {
    // URL doesn't contain id parameter
    header("location: manage_bonuses.php");
    exit();
}
?> 