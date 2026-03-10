<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once('../../admin/security.php');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();


// Include database connection
require_once('../../includes/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maktob_id = isset($_POST['maktob_id']) ? (int)$_POST['maktob_id'] : 0;

    // Validate maktob_id
    if ($maktob_id > 0) {
        // Check if maktob exists and get file paths
        $check_query = "SELECT file_path, pdf_path FROM maktobs WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($check_query);
        $stmt->bindParam(1, $maktob_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $file_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($file_data) {
            $file_path = $file_data['file_path'] ?? null;
            $pdf_path = $file_data['pdf_path'] ?? null;
            
            // Log the deletion BEFORE deleting (to satisfy foreign key constraint)
            $old_values = json_encode([
                'maktob_id' => $maktob_id,
                'file_path' => $file_path,
                'pdf_path' => $pdf_path
            ]);
            $new_values = json_encode([]);

            $user_id = $_SESSION['user_id'] ?? 0;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

            $log_query = "INSERT INTO maktob_logs
                          (tenant_id, maktob_id, user_id, action, old_values, new_values, ip_address, branch_id)
                          VALUES (?, ?, ?, 'delete', ?, ?, ?, ?)";

            $stmt_log = $pdo->prepare($log_query);
            $stmt_log->bindParam(1, $tenant_id, PDO::PARAM_INT);
            $stmt_log->bindParam(2, $maktob_id, PDO::PARAM_INT);
            $stmt_log->bindParam(3, $user_id, PDO::PARAM_INT);
            $stmt_log->bindParam(4, $old_values, PDO::PARAM_STR);
            $stmt_log->bindParam(5, $new_values, PDO::PARAM_STR);
            $stmt_log->bindParam(6, $ip_address, PDO::PARAM_STR);
            $stmt_log->bindParam(7, $branch_id, PDO::PARAM_INT);
            
            if ($stmt_log->execute()) {
                // Delete maktob
                $query = "DELETE FROM maktobs WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $deleteStmt = $pdo->prepare($query);
                $deleteStmt->bindParam(1, $maktob_id, PDO::PARAM_INT);
                $deleteStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $deleteStmt->bindParam(3, $branch_id, PDO::PARAM_INT);

                if ($deleteStmt->execute()) {
                    // Delete the associated files if they exist
                    if ($file_path && file_exists("../{$file_path}")) {
                        unlink("../{$file_path}");
                    }
                    
                    if ($pdf_path && file_exists("../{$pdf_path}")) {
                        unlink("../{$pdf_path}");
                    }
                    
                    $_SESSION['success_message'] = "Maktob deleted successfully!";
                } else {
                    $_SESSION['error_message'] = "Error deleting maktob";
                }
            } else {
                $_SESSION['error_message'] = "Error logging maktob deletion";
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
header('Location: ../../admin/manage_maktobs.php');
exit(); 