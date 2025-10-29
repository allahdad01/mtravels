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

// Include database connection
include '../includes/db.php';
include '../includes/conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maktob_id = isset($_POST['maktob_id']) ? (int)$_POST['maktob_id'] : 0;
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
    $maktob_number = mysqli_real_escape_string($conn, $_POST['maktob_number']);
    $maktob_date = mysqli_real_escape_string($conn, $_POST['maktob_date']);
    $language = mysqli_real_escape_string($conn, $_POST['language']);

    // Validate maktob_id
    if ($maktob_id > 0) {
        // Check if maktob exists
        $check_query = "SELECT 1 FROM maktobs WHERE id = $maktob_id AND tenant_id = $tenant_id";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            // Update maktob
            $query = "UPDATE maktobs SET 
                     subject = '$subject',
                     content = '$content',
                     company_name = '$company_name',
                     maktob_number = '$maktob_number',
                     maktob_date = '$maktob_date',
                     language = '$language'
                     WHERE id = $maktob_id AND tenant_id = $tenant_id";

            if (mysqli_query($conn, $query)) {
                // Add activity logging
                $user_id = $_SESSION['user_id'] ?? 0;
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                // Get original maktob data
                $get_original = "SELECT * FROM maktobs WHERE id = $maktob_id AND tenant_id = $tenant_id";
                $original_result = mysqli_query($conn, $get_original);
                $old_values = [];
                
                if ($original_result && mysqli_num_rows($original_result) > 0) {
                    $original_data = mysqli_fetch_assoc($original_result);
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
                    (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $activity_log_stmt->execute([
                    $user_id,
                    'update',
                    'maktobs',
                    $maktob_id,
                    json_encode($old_values),
                    json_encode($new_values),
                    $ip_address,
                    $user_agent,
                    $tenant_id
                ]);
                
                $_SESSION['success_message'] = "Maktob updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating maktob: " . mysqli_error($conn);
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