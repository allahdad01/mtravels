<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Include database connection
require_once('../includes/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maktob_id = isset($_POST['maktob_id']) ? (int)$_POST['maktob_id'] : 0;
    $subject = $_POST['subject'];
    $content = $_POST['content'];
    $company_name = $_POST['company_name'];
    $maktob_number = $_POST['maktob_number'];
    $maktob_date = $_POST['maktob_date'];
    $language = $_POST['language'];

    // Validate maktob_id
    if ($maktob_id > 0) {
        // Check if maktob exists
        $check_query = "SELECT 1 FROM maktobs WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->bindParam(1, $maktob_id, PDO::PARAM_INT);
        $check_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $check_stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $check_stmt->execute();

        if ($check_stmt->fetch(PDO::FETCH_ASSOC)) {
            // Update maktob
            $query = "UPDATE maktobs SET
                      subject = ?,
                      content = ?,
                      company_name = ?,
                      maktob_number = ?,
                      maktob_date = ?,
                      language = ?
                      WHERE id = ? AND tenant_id = ? AND branch_id = ?";

            $update_stmt = $pdo->prepare($query);
            $update_stmt->bindParam(1, $subject, PDO::PARAM_STR);
            $update_stmt->bindParam(2, $content, PDO::PARAM_STR);
            $update_stmt->bindParam(3, $company_name, PDO::PARAM_STR);
            $update_stmt->bindParam(4, $maktob_number, PDO::PARAM_STR);
            $update_stmt->bindParam(5, $maktob_date, PDO::PARAM_STR);
            $update_stmt->bindParam(6, $language, PDO::PARAM_STR);
            $update_stmt->bindParam(7, $maktob_id, PDO::PARAM_INT);
            $update_stmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
            $update_stmt->bindParam(9, $branch_id, PDO::PARAM_INT);

            if ($update_stmt->execute()) {
                // Add activity logging
                $user_id = $_SESSION['user_id'] ?? 0;
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                // Get original maktob data
                $get_original = "SELECT * FROM maktobs WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $original_stmt = $pdo->prepare($get_original);
                $original_stmt->bindParam(1, $maktob_id, PDO::PARAM_INT);
                $original_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $original_stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                $original_stmt->execute();
                $original_data = $original_stmt->fetch(PDO::FETCH_ASSOC);
                $old_values = [];

                if ($original_data) {
                    $old_values = [
                        'subject' => $original_data['subject'],
                        'content' => $original_data['content'],
                        'company_name' => $original_data['company_name'],
                        'maktob_number' => $original_data['maktob_number'],
                        'maktob_date' => $original_data['maktob_date'],
                        'language' => $original_data['language']
                    ];
                }
                
                // Prepare new values
                $new_values = [
                    'subject' => $subject,
                    'content' => $content,
                    'company_name' => $company_name,
                    'maktob_number' => $maktob_number,
                    'maktob_date' => $maktob_date,
                    'language' => $language
                ];
                
                // Insert activity log using PDO connection
                $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
                    (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id, branch_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $activity_log_stmt->execute([
                    $user_id,
                    'update',
                    'maktobs',
                    $maktob_id,
                    json_encode($old_values),
                    json_encode($new_values),
                    $ip_address,
                    $user_agent,
                    $tenant_id,
                    $branch_id
                ]);
                
                $_SESSION['success_message'] = "Maktob updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating maktob";
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