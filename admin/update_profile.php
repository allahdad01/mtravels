<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

$tenant_id = $_SESSION['tenant_id'] ?? null;
$branch_id = $_SESSION['branch_id'] ?? null;

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once('../includes/db.php');

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
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$user_id, $tenant_id, $branch_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($current_password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit;
        }

        if ($new_password !== $confirm_password) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
            exit;
        }

        $updates[] = "password = ?";
        $params[] = password_hash($new_password, PASSWORD_DEFAULT);
    }

    // Handle image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = $_FILES['profile_image']['type'];

        if (!in_array($fileType, $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG and GIF allowed.']);
            exit;
        }

        // Create directory if it doesn't exist
        $uploadDir = '../assets/images/user/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = uniqid() . '_' . basename($_FILES['profile_image']['name']);
        $uploadPath = $uploadDir . $fileName;

        // Debug: Log upload attempt
        error_log("Attempting to upload file: " . $fileName . " to path: " . $uploadPath);

        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath)) {
            // Delete old image if exists
            $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->execute([$user_id, $tenant_id, $branch_id]);
            $oldImage = $stmt->fetchColumn();
            if ($oldImage && $oldImage !== 'default-avatar.jpg') {
                $oldImagePath = $uploadDir . $oldImage;
                if (file_exists($oldImagePath)) unlink($oldImagePath);
            }

            $updates[] = "profile_pic = ?";
            $params[] = $fileName;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image file']);
            exit;
        }
    }

    if (!empty($updates)) {
        // Add WHERE clause params
        $params[] = $user_id;
        $params[] = $tenant_id;
        $params[] = $branch_id;

        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ? AND branch_id = ?";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute($params)) {
            // Fetch updated user
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->execute([$user_id, $tenant_id, $branch_id]);
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
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, tenant_id, branch_id)
                VALUES (?, 'update', 'users', ?, ?, ?, ?, ?, ?, ?)
            ");
            $activity_log_stmt->execute([
                $user_id,
                $user_id,
                json_encode([]),
                json_encode($updated_fields),
                $ip_address,
                $user_agent,
                $tenant_id,
                $branch_id
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
    error_log("Profile Update Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>