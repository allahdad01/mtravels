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
require_once('../includes/SecureFileUpload.php');

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token()) {
    http_response_code(403);
    $_SESSION['error_message'] = 'Security validation failed. Please try again.';
    header('Location: ../../admin/manage_maktobs.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maktob_id = isset($_POST['maktob_id']) ? (int)$_POST['maktob_id'] : 0;
    $subject = $_POST['subject'];
    $content = $_POST['content'];
    $company_name = $_POST['company_name'];
    $maktob_number = $_POST['maktob_number'];
    $maktob_date = $_POST['maktob_date'];
    $language = $_POST['language'];

    // Handle file uploads using SecureFileUpload
    $file_path = null;
    $pdf_path = null;

    try {
        $uploader = new SecureFileUpload(10 * 1024 * 1024, '../../uploads/');
        
        // Handle PDF file upload
        if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
            $result = $uploader->upload('pdf_file', 'maktobs', 1);
            if ($result['success']) {
                $pdf_path = 'uploads/maktobs/' . $result['data']['filename'];
            } else {
                $_SESSION['error_message'] = 'Failed to upload PDF file: ' . $result['error'];
                header('Location: ../../admin/manage_maktobs.php');
                exit();
            }
        }

        // Handle attachment upload
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $result = $uploader->upload('attachment', 'maktobs', 1);
            if ($result['success']) {
                $file_path = 'uploads/maktobs/' . $result['data']['filename'];
            } else {
                $_SESSION['error_message'] = 'Failed to upload attachment: ' . $result['error'];
                header('Location: ../../admin/manage_maktobs.php');
                exit();
            }
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Upload error: ' . $e->getMessage();
        header('Location: ../../admin/manage_maktobs.php');
        exit();
    }

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
            // Build dynamic update query
            $update_fields = [
                'subject = ?',
                'content = ?',
                'company_name = ?',
                'maktob_number = ?',
                'maktob_date = ?',
                'language = ?'
            ];
            $params = [$subject, $content, $company_name, $maktob_number, $maktob_date, $language];

            if ($file_path !== null) {
                $update_fields[] = 'file_path = ?';
                $params[] = $file_path;
            }

            if ($pdf_path !== null) {
                $update_fields[] = 'pdf_path = ?';
                $params[] = $pdf_path;
            }

            $query = "UPDATE maktobs SET " . implode(', ', $update_fields) . " WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $params[] = $maktob_id;
            $params[] = $tenant_id;
            $params[] = $branch_id;

            $update_stmt = $pdo->prepare($query);
            foreach ($params as $index => $param) {
                $update_stmt->bindValue($index + 1, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }

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
                        'language' => $original_data['language'],
                        'file_path' => $original_data['file_path'],
                        'pdf_path' => $original_data['pdf_path']
                    ];
                }

                // Prepare new values
                $new_values = [
                    'subject' => $subject,
                    'content' => $content,
                    'company_name' => $company_name,
                    'maktob_number' => $maktob_number,
                    'maktob_date' => $maktob_date,
                    'language' => $language,
                    'file_path' => $file_path !== null ? $file_path : $original_data['file_path'],
                    'pdf_path' => $pdf_path !== null ? $pdf_path : $original_data['pdf_path']
                ];

                // Insert maktob log
                $log_stmt = $pdo->prepare("INSERT INTO maktob_logs
                    (tenant_id, maktob_id, user_id, action, old_values, new_values, ip_address, branch_id)
                    VALUES (?, ?, ?, 'edit', ?, ?, ?, ?)");
                $log_stmt->execute([
                    $tenant_id,
                    $maktob_id,
                    $user_id,
                    json_encode($old_values),
                    json_encode($new_values),
                    $ip_address,
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