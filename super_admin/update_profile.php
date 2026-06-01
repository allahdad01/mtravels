<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once('../includes/db.php');
require_once('../includes/SecureFileUpload.php');
require_once('../includes/PasswordValidator.php');

$user_id = $_SESSION['user_id'];

try {
    $current_password = $_POST['current_password'] ?? null;
    $new_password = $_POST['new_password'] ?? null;
    $confirm_password = $_POST['confirm_password'] ?? null;

    // If it's a password-only request
    if ($current_password && $new_password && $confirm_password) {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($current_password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit;
        }

        if ($new_password !== $confirm_password) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
            exit;
        }

        $validation = PasswordValidator::validate($new_password);
        if (!$validation['valid']) {
            echo json_encode(['success' => false, 'message' => 'Password does not meet requirements: ' . implode(', ', $validation['errors'])]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([password_hash($new_password, PASSWORD_DEFAULT), $user_id]);

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES (?, 'change_password', 'user', ?, ?, ?, NOW())");
        $log->execute([$user_id, $user_id, json_encode(['password_changed' => true]), $ip]);

        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        exit;
    }

    // Profile update
    $updates = [];
    $params = [];

    $fields = ['name', 'email', 'phone', 'address'];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $trimmed = trim($_POST[$field]);
            if ($trimmed !== '') {
                $updates[] = "$field = ?";
                $params[] = $trimmed;
            }
        }
    }

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploader = new SecureFileUpload(5 * 1024 * 1024, '../assets/images/');
        $result = $uploader->upload('profile_image', 'user');

        if ($result['success']) {
            $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $oldImage = $stmt->fetchColumn();

            if ($oldImage && $oldImage !== 'default-avatar.jpg') {
                $oldImagePath = '../assets/images/user/' . $oldImage;
                if (file_exists($oldImagePath) && strpos(realpath($oldImagePath), realpath('../assets/images/user/')) === 0) {
                    @unlink($oldImagePath);
                }
            }

            $updates[] = "profile_pic = ?";
            $params[] = $result['data']['filename'];
        }
    }

    if (!empty($updates)) {
        $params[] = $user_id;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute($params)) {
            // Log activity
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $updatedFields = [];
            foreach ($fields as $f) {
                if (!empty($_POST[$f])) $updatedFields[$f] = $_POST[$f];
            }

            $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES (?, 'update_profile', 'user', ?, ?, ?, NOW())");
            $log->execute([$user_id, $user_id, json_encode($updatedFields), $ip]);

            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes to update']);
    }

} catch (PDOException $e) {
    error_log("Super admin profile update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
