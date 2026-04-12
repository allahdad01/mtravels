<?php
// Include security module
require_once 'security.php';

// Enforce client authentication (function is called from security.php auto-enforce)
// No need to call it again, it's already enforced at the bottom of security.php

$tenant_id = $_SESSION['tenant_id'] ?? null;
$client_id = $_SESSION['client_id'] ?? $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'client';
$branch_id = $_SESSION['branch_id'] ?? null;

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once('../includes/db.php');
require_once('../includes/SecureFileUpload.php');
require_once('../includes/PasswordValidator.php');
require_once('../includes/InputValidator.php');

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
        if (isset($_POST[$field])) {
            $validated = InputValidator::getString($_POST[$field], 255);
            // Only include field if it has a value (even if whitespace-only, trim it first)
            $trimmed = trim($validated);
            if ($trimmed !== '') {
                $updates[] = "$field = ?";
                $params[] = $trimmed;
            }
        }
    }

    // Handle password update for both clients and staff
    if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
        // Verify current password
        if ($user_role === 'client') {
            $stmt = $pdo->prepare("SELECT password_hash FROM clients WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$client_id, $tenant_id]);
            $passwordColumn = 'password_hash';
        } else {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->execute([$user_id, $tenant_id, $branch_id]);
            $passwordColumn = 'password';
        }
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentHashedPassword = $user[$passwordColumn] ?? null;

        if (!$user || !password_verify($current_password, $currentHashedPassword)) {
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

        $updates[] = "$passwordColumn = ?";
        $params[] = password_hash($new_password, PASSWORD_DEFAULT);
    }

    // Handle image upload - SECURE VERSION
     if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
          $uploader = new SecureFileUpload(
              5 * 1024 * 1024, // 5MB max size
              '../assets/images/'
          );
          
          // Use different subdirectory based on role
          $uploadSubdir = ($user_role === 'client') ? 'client' : 'user';
          $result = $uploader->upload('profile_image', $uploadSubdir);
          
          if ($result['success']) {
              // Delete old image if exists
              if ($user_role === 'client') {
                  $stmt = $pdo->prepare("SELECT image FROM clients WHERE id = ? AND tenant_id = ?");
                  $stmt->execute([$client_id, $tenant_id]);
                  $imageColumn = 'image';
                  $imagePath = '../assets/images/client/';
              } else {
                  $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                  $stmt->execute([$user_id, $tenant_id, $branch_id]);
                  $imageColumn = 'profile_pic';
                  $imagePath = '../assets/images/user/';
              }
              
              $oldImage = $stmt->fetchColumn();
              if ($oldImage && $oldImage !== 'default-avatar.jpg') {
                   $oldImagePath = $imagePath . $oldImage;
                   // Verify old file path is safe before deleting
                   if (file_exists($oldImagePath) && strpos(realpath($oldImagePath), realpath($imagePath)) === 0) {
                       @unlink($oldImagePath);
                   }
              }

              $updates[] = "$imageColumn = ?";
              $params[] = $result['data']['filename'];
          } else {
              echo json_encode(['success' => false, 'message' => 'Failed to upload image: ' . $result['error']]);
              exit;
          }
     }

    if (!empty($updates)) {
          // Prepare update based on role
          if ($user_role === 'client') {
              $params[] = $client_id;
              $params[] = $tenant_id;
              
              $sql = "UPDATE clients SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?";
              $stmt = $pdo->prepare($sql);
              
              if ($stmt->execute($params)) {
                  // Fetch updated user
                  $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? AND tenant_id = ?");
                  $stmt->execute([$client_id, $tenant_id]);
                  $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
              }
          } else {
              // Add WHERE clause params for staff
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
              }
          }

         if (!empty($updatedUser)) {
             if (isset($updatedUser['password'])) unset($updatedUser['password']); // Remove password from response
             if (isset($updatedUser['password_hash'])) unset($updatedUser['password_hash']); // Remove password_hash from response

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
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>