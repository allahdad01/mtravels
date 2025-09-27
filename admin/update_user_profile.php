<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
include '../config.php';  // Include your database config

// Validate hire_date
$hire_date = isset($_POST['hire_date']) ? DbSecurity::validateInput($_POST['hire_date'], 'date') : null;

// Validate address
$address = isset($_POST['address']) ? DbSecurity::validateInput($_POST['address'], 'string', ['maxlength' => 255]) : null;

// Validate phone
$phone = isset($_POST['phone']) ? DbSecurity::validateInput($_POST['phone'], 'string', ['maxlength' => 255]) : null;

// Validate role
$role = isset($_POST['role']) ? DbSecurity::validateInput($_POST['role'], 'string', ['maxlength' => 255]) : null;

// Validate name
$name = isset($_POST['name']) ? DbSecurity::validateInput($_POST['name'], 'string', ['maxlength' => 255]) : null;

// Check if the session has the user_id
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get the user ID and other input data from POST
$userId = $_SESSION['user_id'];
$name = $_POST['name'];
$role = $_POST['role'];
$phone = $_POST['phone'];
$address = $_POST['address'];
$hireDate = $_POST['hire_date'];
$profilePic = $_FILES['profile_pic'];

// Initialize the new profile picture variable to be the existing one if no new picture is uploaded
$newFileName = "";

try {
    // Get the current user data for activity log
    $sql = "SELECT name, role, phone, address, hire_date, profile_pic FROM users WHERE id = ? AND tenant_id = ?";
    $stmt = $conection_db->prepare($sql);
    $stmt->bind_param("ii", $userId, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldUserData = $result->fetch_assoc();
    $stmt->close();
    
    // Check if a new profile picture has been uploaded
    if ($profilePic['error'] === UPLOAD_ERR_OK) {
        // Validate file type (only allow image files)
        $allowedFileTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($profilePic['tmp_name']);

        if (!in_array($fileType, $allowedFileTypes)) {
            throw new Exception('Invalid file type. Please upload an image file (JPEG, PNG, GIF).');
        }

        // Limit file size (for example, max 2MB)
        $maxFileSize = 2 * 1024 * 1024;
        if ($profilePic['size'] > $maxFileSize) {
            throw new Exception('File size exceeds the maximum allowed size (2MB).');
        }

        // Generate a new file name to avoid conflicts
        $uploadDir = '../assets/images/user/';
        $newFileName = $uploadDir . uniqid('', true) . basename($profilePic['name']);

        // Move the uploaded file
        if (!move_uploaded_file($profilePic['tmp_name'], $newFileName)) {
            throw new Exception('Failed to upload profile picture.');
        }
    } else {
        // If no new profile picture is uploaded, retain the existing picture from the database
        $sql = "SELECT profile_pic FROM users WHERE id = ? AND tenant_id = ?";
        $stmt = $conection_db->prepare($sql);
        $stmt->bind_param("ii", $userId, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $profilePicData = $result->fetch_assoc();
        $newFileName = $profilePicData['profile_pic'];
    }

    // Update the user details in the database
    $sql = "UPDATE users SET name = ?, role = ?, phone = ?, address = ?, hire_date = ?, profile_pic = ? WHERE id = ? AND tenant_id = ?";
    $stmt = $conection_db->prepare($sql);
    $stmt->bind_param("ssssssii", $name, $role, $phone, $address, $hireDate, $newFileName, $userId, $tenant_id);
    
    if ($stmt->execute()) {
        // Create activity log
        $logUserId = $userId; // The user making the change
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        
        // Create old values JSON
        $oldValues = json_encode([
            'id' => $userId,
            'name' => $oldUserData['name'],
            'role' => $oldUserData['role'],
            'phone' => $oldUserData['phone'],
            'address' => $oldUserData['address'],
            'hire_date' => $oldUserData['hire_date'],
            'profile_pic' => $oldUserData['profile_pic']
        ]);
        
        // Create new values JSON
        $newValues = json_encode([
            'id' => $userId,
            'name' => $name,
            'role' => $role,
            'phone' => $phone,
            'address' => $address,
            'hire_date' => $hireDate,
            'profile_pic' => $newFileName
        ]);
        
        // Insert activity log
        $logSql = "INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id) 
                   VALUES (?, ?, ?, 'update', 'users', ?, ?, ?, NOW(), ?)";
        $logStmt = $conection_db->prepare($logSql);
        $logStmt->bind_param("issiissi", $logUserId, $ipAddress, $userAgent, $userId, $oldValues, $newValues, $tenant_id);
        
        if (!$logStmt->execute()) {
            // Log error but continue to send success response
            error_log("Failed to insert activity log: " . $logStmt->error);
        }
        $logStmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
    } else {
        throw new Exception('Error updating profile in the database.');
    }

} catch (Exception $e) {
    // Handle any errors
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Close the database connection
$stmt->close();
$conection_db->close();
?>

