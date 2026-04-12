<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once('../includes/db.php');
require_once('db_security.php');
require_once('../includes/SecureFileUpload.php');
require_once('../includes/PasswordValidator.php');

try {
    $user_id = $_SESSION['user_id'];

    // Validate inputs
    $confirm_password = $_POST['confirm_password'] ?? null;
    $new_password = $_POST['new_password'] ?? null;
    $current_password = $_POST['current_password'] ?? null;

    $updates = [];
    $params = [];

    // Update text fields
    $fields = ['name', 'email', 'phone', 'address'];
    foreach ($fields as $field) {
        if (!empty($_POST[$field])) {
            $updates[] = "$field = ?";
            $params[] = DbSecurity::validateInput($_POST[$field], 'string', ['maxlength' => 255]);
        }
    }

    // Handle password update
    if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$user_id, $tenant_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($current_password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit;
        }

        if ($new_password !== $confirm_password) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
            exit;
        }

        // Validate password strength
        $validation = PasswordValidator::validate($new_password);
        if (!$validation['valid']) {
            echo json_encode(['success' => false, 'message' => 'Password does not meet requirements: ' . implode(', ', $validation['errors'])]);
            exit;
        }

        $updates[] = "password = ?";
        $params[] = password_hash($new_password, PASSWORD_DEFAULT);
    }

    // Handle image upload using SecureFileUpload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        try {
            $uploader = new SecureFileUpload(2 * 1024 * 1024, '../assets/');
            $result = $uploader->upload('profile_image', 'images/user');
            
            if ($result['success']) {
                // Delete old image if exists
                $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$user_id, $tenant_id]);
                $oldImage = $stmt->fetchColumn();
                if ($oldImage && $oldImage !== 'default-avatar.jpg') {
                    $oldImagePath = '../assets/images/user/' . $oldImage;
                    if (file_exists($oldImagePath)) unlink($oldImagePath);
                }

                $updates[] = "profile_pic = ?";
                $params[] = $result['data']['filename'];
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload image: ' . $result['error']]);
                exit;
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Upload error: ' . $e->getMessage()]);
            exit;
        }
    }

    if (!empty($updates)) {
        // Add WHERE clause params
        $params[] = $user_id;
        $params[] = $tenant_id;

        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute($params)) {
            // Fetch updated user
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$user_id, $tenant_id]);
            $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
            unset($updatedUser['password']); // Remove password from response

            // Log activity
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $updated_fields = [];
            foreach ($fields as $field) {
                if (!empty($_POST[$field])) $updated_fields[$field] = $_POST[$field];
            }
            if (!empty($new_password)) $updated_fields['password'] = '(password changed)';
            if (isset($fileName)) $updated_fields['profile_pic'] = $fileName;

            $activity_log_stmt = $pdo->prepare("
                INSERT INTO activity_log
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id)
                VALUES (?, 'update', 'users', ?, ?, ?, ?, ?, ?)
            ");
            $activity_log_stmt->execute([
                $user_id,
                $user_id,
                json_encode([]),
                json_encode($updated_fields),
                $ip_address,
                $user_agent,
                $tenant_id
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Profile updated successfully' . (!empty($new_password) ? ' (including password)' : ''),
                'user' => $updatedUser
            ]);
        } else {
            $errorInfo = $stmt->errorInfo();
            echo json_encode(['success' => false, 'message' => 'Failed to update profile', 'error' => $errorInfo]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes to update']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>