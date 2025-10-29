<?php
// Include necessary files
require_once 'security.php';
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
// Set JSON content type
header('Content-Type: application/json');


// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die(json_encode(['success' => false, 'message' => __('invalid_csrf_token')]));
}

// Check if user_id is provided
if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
    die(json_encode(['success' => false, 'message' => __('user_id_required')]));
}

try {
    // Validate required fields
    if (empty($_POST['name']) || empty($_POST['email'])) {
        throw new Exception(__('name_and_email_are_required'));
    }

    // Validate email format
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception(__('invalid_email_format'));
    }

    // Check if email exists for other users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ? AND tenant_id = ?");
    $stmt->execute([$_POST['email'], $_POST['user_id'], $tenant_id]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception(__('email_already_exists'));
    }

    // Handle file upload
    $profile_pic = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_pic']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            throw new Exception(__('invalid_file_type'));
        }
        
        // Generate unique filename
        $new_filename = uniqid() . '.' . $ext;
        $upload_path = '../assets/images/user/' . $new_filename;
        
        if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
            throw new Exception(__('error_uploading_profile_picture'));
        }
        
        // Delete old profile picture
        $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$_POST['user_id'], $tenant_id]);
        $old_pic = $stmt->fetchColumn();
        if ($old_pic && $old_pic !== 'default-avatar.jpg') {
            $old_pic_path = "../assets/images/user/" . $old_pic;
            if (file_exists($old_pic_path)) {
                unlink($old_pic_path);
            }
        }
        
        $profile_pic = $new_filename;
    }

    // Prepare update parameters
    $params = [
        'name' => $_POST['name'],
        'email' => $_POST['email'],
        'role' => $_POST['role'],
        'phone' => $_POST['phone'] ?? null,
        'address' => $_POST['address'] ?? null,
        'hire_date' => $_POST['hire_date'] ?? null,
        'user_id' => $_POST['user_id'],
        'tenant_id' => $tenant_id
    ];

    // Build update SQL
    $sql = "UPDATE users SET 
            name = :name, 
            email = :email, 
            role = :role, 
            phone = :phone, 
            address = :address, 
            hire_date = :hire_date";

    // Add password to update if provided
    if (!empty($_POST['password'])) {
        $params['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql .= ", password = :password";
    }

    // Add profile pic to update if provided
    if ($profile_pic) {
        $params['profile_pic'] = $profile_pic;
        $sql .= ", profile_pic = :profile_pic";
    }

    $sql .= " WHERE id = :user_id AND tenant_id = :tenant_id";

    // Execute update
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Handle document uploads
    if (isset($_FILES['user_documents']) && !empty($_FILES['user_documents']['name'][0])) {
        // Create user directory if it doesn't exist
        $userDocDir = "../uploads/user_documents/{$_POST['user_id']}/{$tenant_id}";
        if (!file_exists($userDocDir)) {
            mkdir($userDocDir, 0755, true);
        }
        
        // Process each uploaded document
        $allowedDocs = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $uploadedDocs = [];
        
        foreach ($_FILES['user_documents']['name'] as $key => $filename) {
            if ($_FILES['user_documents']['error'][$key] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (!in_array($ext, $allowedDocs)) {
                    continue; // Skip invalid file types
                }
                
                // Generate unique filename
                $newFilename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
                $uploadPath = "{$userDocDir}/{$newFilename}";
                
                if (move_uploaded_file($_FILES['user_documents']['tmp_name'][$key], $uploadPath)) {
                    // Save document info in the database
                    $docStmt = $pdo->prepare("
                        INSERT INTO user_documents (
                            user_id, filename, original_name, file_type, uploaded_at, tenant_id
                        ) VALUES (
                            :user_id, :filename, :original_name, :file_type, NOW(), :tenant_id
                        )
                    ");
                    
                    $docStmt->execute([
                        'user_id' => $_POST['user_id'],
                        'filename' => $newFilename,
                        'original_name' => $filename,
                        'file_type' => $ext,
                        'tenant_id' => $tenant_id
                    ]);
                    
                    $uploadedDocs[] = $newFilename;
                }
            }
        }
    }

    if ($stmt->rowCount() === 0 && empty($uploadedDocs)) {
        throw new Exception(__('no_changes_made'));
    }

    echo json_encode([
        'success' => true,
        'message' => __('user_updated_successfully')
    ]);

} catch (Exception $e) {
    // If there was an error and we uploaded a file, delete it
    if (isset($profile_pic) && file_exists('../assets/images/user/' . $profile_pic)) {
        unlink('../assets/images/user/' . $profile_pic);
    }
    
    // If there was an error and we uploaded documents, delete them
    if (isset($uploadedDocs)) {
        foreach ($uploadedDocs as $doc) {
            $docPath = "../uploads/user_documents/{$_POST['user_id']}/{$tenant_id}/{$doc}";
            if (file_exists($docPath)) {
                unlink($docPath);
            }
        }
    }
    
    error_log("Error in update_user.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

 