<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();


// Include database connection
include '../includes/db.php';
include '../includes/conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maktob_id = isset($_POST['maktob_id']) ? (int)$_POST['maktob_id'] : 0;

    // Validate maktob_id
    if ($maktob_id > 0) {
        // Check if maktob exists and get file paths
        $check_query = "SELECT file_path, pdf_path FROM maktobs WHERE id = $maktob_id AND tenant_id = $tenant_id";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            // Get file paths before deleting
            $file_data = mysqli_fetch_assoc($check_result);
            $file_path = $file_data['file_path'] ?? null;
            $pdf_path = $file_data['pdf_path'] ?? null;
            
            // Delete maktob
            $query = "DELETE FROM maktobs WHERE id = $maktob_id AND tenant_id = $tenant_id";

            if (mysqli_query($conn, $query)) {
                // Delete the associated files if they exist
                if ($file_path && file_exists("../{$file_path}")) {
                    unlink("../{$file_path}");
                }
                
                if ($pdf_path && file_exists("../{$pdf_path}")) {
                    unlink("../{$pdf_path}");
                }
                
                // Log the activity
                $old_values = json_encode([
                    'maktob_id' => $maktob_id
                ]);
                $new_values = json_encode([]);
                
                $user_id = $_SESSION['user_id'] ?? 0;
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                $log_query = "INSERT INTO activity_log 
                              (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
                              VALUES (?, 'delete', 'maktobs', ?, ?, ?, ?, ?, NOW(), ?)";
                
                $stmt_log = $conn->prepare($log_query);
                $stmt_log->bind_param("iisssss", $user_id, $maktob_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
                $stmt_log->execute();
                $stmt_log->close();
                
                $_SESSION['success_message'] = "Maktob deleted successfully!";
            } else {
                $_SESSION['error_message'] = "Error deleting maktob: " . mysqli_error($conn);
            }
        } else {
            $_SESSION['error_message'] = "Maktob not found";
        }
    } else {
        $_SESSION['error_message'] = "Invalid maktob ID";
    }
} else {
    $_SESSION['error_message'] = "Invalid request method";
}

// Redirect back to manage maktobs page
header('Location: manage_maktobs.php');
exit(); 