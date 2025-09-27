<?php
// Include necessary files
require_once 'security.php';
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON content type
header('Content-Type: application/json');


// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die(json_encode(['success' => false, 'message' => __('invalid_csrf_token')]));
}

try {
    // Validate required fields
    if (empty($_POST['name']) || empty($_POST['email']) || empty($_POST['password'])) {
        throw new Exception(__('name_email_and_password_required'));
    }

    // Validate email format
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception(__('invalid_email_format'));
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND tenant_id = ?");
    $stmt->execute([$_POST['email'], $tenant_id]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception(__('email_already_exists'));
    }

    // Handle file upload
    $profile_pic = 'default-avatar.jpg'; // Default profile picture
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
        $profile_pic = $new_filename;
    }

    // Insert new user
    $stmt = $pdo->prepare("
        INSERT INTO users (
            name, email, password, role, phone, address, 
            hire_date, profile_pic, created_at, tenant_id
        ) VALUES (
            :name, :email, :password, :role, :phone, :address, 
            :hire_date, :profile_pic, NOW(), :tenant_id
        )
    ");
    
    $stmt->execute([
        'name' => $_POST['name'],
        'email' => $_POST['email'],
        'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
        'role' => $_POST['role'],
        'phone' => $_POST['phone'] ?? null,
        'address' => $_POST['address'] ?? null,
        'hire_date' => $_POST['hire_date'] ?? null,
        'profile_pic' => $profile_pic,
        'tenant_id' => $tenant_id
    ]);
    
    // Get the new user ID
    $userId = $pdo->lastInsertId();
    
    // Handle document uploads
    if (isset($_FILES['user_documents']) && !empty($_FILES['user_documents']['name'][0])) {
        // Create user directory if it doesn't exist
        $userDocDir = "../uploads/user_documents/{$userId}";
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
                        'user_id' => $userId,
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
    
    echo json_encode([
        'success' => true, 
        'message' => __('user_added_successfully')
    ]);

} catch (Exception $e) {
    // If there was an error and we uploaded a file, delete it
    if (isset($new_filename) && file_exists('../assets/images/user/' . $new_filename)) {
        unlink('../assets/images/user/' . $new_filename);
    }
    
    // If there was an error and we uploaded documents, delete them
    if (isset($userId) && isset($uploadedDocs)) {
        foreach ($uploadedDocs as $doc) {
            $docPath = "../uploads/user_documents/{$userId}/{$doc}";
            if (file_exists($docPath)) {
                unlink($docPath);
            }
        }
    }
    
    error_log("Error in save_user.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
} 