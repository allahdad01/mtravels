<?php
session_start();
require_once('../includes/db.php');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    $updates = [];
    $params = [];
    
    // Handle text fields
    $fields = ['name', 'email', 'phone', 'address'];
    foreach ($fields as $field) {
        if (isset($_POST[$field]) && !empty($_POST[$field])) {
            $updates[] = "$field = ?";
            $params[] = $_POST[$field];
        }
    }

    // Handle password update
    if (!empty($_POST['current_password']) && !empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
        // First verify current password
        $stmt = $pdo->prepare("SELECT password_hash FROM clients WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($_POST['current_password'], $client['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit;
        }

        // Verify new passwords match
        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
            exit;
        }

        // Add password update to query
        $updates[] = "password_hash = ?";
        $params[] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    }

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = $_FILES['image']['type'];
        
        if (!in_array($fileType, $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG and GIF allowed.']);
            exit;
        }

        $fileName = uniqid() . '_' . $_FILES['image']['name'];
        $uploadPath = '../assets/images/client/' . $fileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            // Delete old image if exists
            $stmt = $pdo->prepare("SELECT image FROM clients WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $oldImage = $stmt->fetchColumn();
            if ($oldImage && $oldImage !== 'default-avatar.jpg') {
                $oldImagePath = '../assets/images/client/' . $oldImage;
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }

            $updates[] = "image = ?";
            $params[] = $fileName;
        }
    }

    // Add updated_at timestamp
    $updates[] = "updated_at = CURRENT_TIMESTAMP";

    if (!empty($updates)) {
        $params[] = $_SESSION['user_id']; // Add user_id for WHERE clause
        
        $sql = "UPDATE clients SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute($params)) {
            // Fetch updated client data
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Remove password_hash from response
            unset($updatedUser['password_hash']);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Profile updated successfully' . 
                    (!empty($_POST['new_password']) ? ' (including password)' : ''),
                'user' => $updatedUser
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes to update']);
    }

} catch (PDOException $e) {
    error_log("Profile Update Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} 