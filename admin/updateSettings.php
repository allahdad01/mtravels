<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../includes/conn.php';

// Validate existing_logo
$existing_logo = isset($_POST['existing_logo']) ? DbSecurity::validateInput($_POST['existing_logo'], 'string', ['maxlength' => 255]) : null;

// Validate address
$address = isset($_POST['address']) ? DbSecurity::validateInput($_POST['address'], 'string', ['maxlength' => 255]) : null;

// Validate email
$email = isset($_POST['email']) ? DbSecurity::validateInput($_POST['email'], 'email') : null;

// Validate phone
$phone = isset($_POST['phone']) ? DbSecurity::validateInput($_POST['phone'], 'string', ['maxlength' => 255]) : null;

// Validate title
$title = isset($_POST['title']) ? DbSecurity::validateInput($_POST['title'], 'string', ['maxlength' => 255]) : null;

// Validate agency_name
$agency_name = isset($_POST['agency_name']) ? DbSecurity::validateInput($_POST['agency_name'], 'string', ['maxlength' => 255]) : null;

// Validate id
$id = isset($_POST['id']) ? DbSecurity::validateInput($_POST['id'], 'int', ['min' => 0]) : null;

    if ($conn->connect_error) {
        $_SESSION['settings_message'] = "Connection failed: " . $conn->connect_error;
        $_SESSION['settings_type'] = 'danger';
        header('Location: settings.php');
        exit();
    }

    // Retrieve the posted form data
    $id = intval($_POST['id']);
    $agency_name = $_POST['agency_name'];
    $title = $_POST['title'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $logo = $_FILES['logo'];

    // Get current settings for activity log
    $getCurrentSettingsQuery = "SELECT agency_name, title, phone, email, address, logo FROM settings WHERE id = ? AND tenant_id = ?";
    $getCurrentSettingsStmt = $conn->prepare($getCurrentSettingsQuery);
    $getCurrentSettingsStmt->bind_param("ii", $id, $tenant_id);
    $getCurrentSettingsStmt->execute();
    $currentSettingsResult = $getCurrentSettingsStmt->get_result();
    $oldSettings = $currentSettingsResult->fetch_assoc();
    $getCurrentSettingsStmt->close();

    // Handle logo upload (if a new file is uploaded)
    if ($logo['name']) {
        // Define the upload directory
        $upload_dir = '../uploads/logo/';
        $logo_name = basename($logo['name']);
        $target_file = $upload_dir . $logo_name;

        // Check if file upload was successful
        if (move_uploaded_file($logo['tmp_name'], $target_file)) {
            $logo_path = $logo_name;  // Save just the file name (not the full path)
        } else {
            die("Failed to upload logo image.");
        }
    } else {
        // If no new file is uploaded, keep the existing logo (only its file name)
        $logo_path = $_POST['existing_logo'];
    }

    // Update query to save logo name (not full path)
    $query = "
        UPDATE settings SET
            agency_name = ?, title = ?, phone = ?, email = ?, address = ?, logo = ?
        WHERE id = ? AND tenant_id = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssssii", $agency_name, $title, $phone, $email, $address, $logo_path, $id, $tenant_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Add activity log
        $userId = $_SESSION['user_id'];
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        
        // Create old values JSON
        $oldValues = json_encode([
            'id' => $id,
            'agency_name' => $oldSettings['agency_name'],
            'title' => $oldSettings['title'],
            'phone' => $oldSettings['phone'],
            'email' => $oldSettings['email'],
            'address' => $oldSettings['address'],
            'logo' => $oldSettings['logo']
        ]);
        
        // Create new values JSON
        $newValues = json_encode([
            'id' => $id,
            'agency_name' => $agency_name,
            'title' => $title,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'logo' => $logo_path
        ]);
        
        // Insert activity log
        $logQuery = "INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id) 
                    VALUES (?, ?, ?, 'update', 'settings', ?, ?, ?, NOW(), ?)";
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bind_param("ississi", $userId, $ipAddress, $userAgent, $id, $oldValues, $newValues, $tenant_id);
        
        if (!$logStmt->execute()) {
            // Log the error but continue
            error_log("Failed to insert activity log: " . $logStmt->error);
        }
        $logStmt->close();
        
        $_SESSION['settings_message'] = "Settings updated successfully!";
        $_SESSION['settings_type'] = 'success';
    } else {
        $_SESSION['settings_message'] = "No changes made or update failed.";
        $_SESSION['settings_type'] = 'danger';
    }

    $stmt->close();
    $conn->close();

    header('Location: settings.php');
    exit();
}
?>
