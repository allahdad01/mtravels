<?php
// Include necessary files
require_once 'security.php';
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once '../includes/SecureFileUpload.php';
require_once '../includes/PasswordValidator.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
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
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$_POST['email'], $tenant_id, $branch_id]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception(__('email_already_exists'));
    }

    // Validate password strength
    $password_validation = PasswordValidator::validate($_POST['password']);
    if (!$password_validation['valid']) {
        throw new Exception(__('password_does_not_meet_requirements') . ': ' . implode(', ', $password_validation['errors']));
    }

    // Handle profile picture upload - SECURE VERSION
    $profile_pic = 'default-avatar.jpg'; // Default profile picture
    if (isset($_FILES['profile_pic'])) {
        // Use SecureFileUpload for profile pictures
        $uploader = new SecureFileUpload(5 * 1024 * 1024, '../assets/'); // 5MB max
        $result = $uploader->upload('profile_pic', 'images/user');
        
        if ($result['success']) {
            $profile_pic = $result['data']['filename'];
        } else {
            throw new Exception(__('error_uploading_profile_picture') . ': ' . $result['error']);
        }
    }

    // Insert new user
    $stmt = $pdo->prepare("
        INSERT INTO users (
            name, email, password, role, phone, address,
            hire_date, profile_pic, created_at, tenant_id, branch_id
        ) VALUES (
            :name, :email, :password, :role, :phone, :address,
            :hire_date, :profile_pic, NOW(), :tenant_id, :branch_id
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
        'tenant_id' => $tenant_id,
        'branch_id' => $branch_id
    ]);
    
    // Get the new user ID
    $userId = $pdo->lastInsertId();
    
    // Handle document uploads - SECURE VERSION
    if (isset($_FILES['user_documents'])) {
        // Use SecureFileUpload for user documents
        $uploader = new SecureFileUpload(10 * 1024 * 1024, '../uploads/'); // 10MB per file
        $upload_result = $uploader->uploadMultiple('user_documents', "user_documents/{$userId}", 10); // Max 10 files
        
        if ($upload_result['success']) {
            // Process successfully uploaded documents
            $uploadedDocs = [];
            
            foreach ($upload_result['data']['files'] as $file_result) {
                if ($file_result['success']) {
                    // Save document info in the database
                    $docStmt = $pdo->prepare("
                        INSERT INTO user_documents (
                            user_id, filename, original_name, file_type, uploaded_at, tenant_id, branch_id
                        ) VALUES (
                            :user_id, :filename, :original_name, :file_type, NOW(), :tenant_id, :branch_id
                        )
                    ");

                    $docStmt->execute([
                        'user_id' => $userId,
                        'filename' => $file_result['data']['filename'],
                        'original_name' => $file_result['data']['original_name'],
                        'file_type' => $file_result['data']['extension'],
                        'tenant_id' => $tenant_id,
                        'branch_id' => $branch_id
                    ]);
                    
                    $uploadedDocs[] = $file_result['data']['filename'];
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
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
} 