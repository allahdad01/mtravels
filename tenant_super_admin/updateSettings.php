<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../includes/db.php';
    require_once '../includes/CsrfProtection.php';
    require_once '../includes/SecureFileUpload.php';
    require_once '../includes/CommunicationAddonManager.php';
    
    // Validate CSRF token for all POST requests
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? null)) {
        echo json_encode(['success' => false, 'message' => 'Security token validation failed']);
        exit;
    }

    // Retrieve the posted form data
    $id = intval($_POST['id'] ?? 0);
    $agency_name = $_POST['agency_name'] ?? '';
    $title = $_POST['title'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $address = $_POST['address'] ?? '';
    $logo = $_FILES['logo'] ?? ['name' => ''];

    // SMTP settings
    $smtp_host = $_POST['smtp_host'] ?? '';
    $smtp_port = $_POST['smtp_port'] ?? '';
    $smtp_encryption = $_POST['smtp_encryption'] ?? '';
    $smtp_username = $_POST['smtp_username'] ?? '';
    $smtp_password = $_POST['smtp_password'] ?? '';
    $smtp_from_email = $_POST['smtp_from_email'] ?? '';
    $smtp_from_name = $_POST['smtp_from_name'] ?? '';
    $smtp_enabled = isset($_POST['smtp_enabled']) && $_POST['smtp_enabled'] === '1' ? 1 : 0;

    // Validate required fields
    if (empty($agency_name) || empty($title) || empty($phone) || empty($email) || empty($address)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit;
    }

    // Get current settings for activity log
    $getCurrentSettingsQuery = "SELECT agency_name, title, phone, email, address, logo, smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password, smtp_from_email, smtp_from_name, smtp_enabled FROM settings WHERE id = ? AND tenant_id = ?";
    $getCurrentSettingsStmt = $pdo->prepare($getCurrentSettingsQuery);
    $getCurrentSettingsStmt->execute([$id, $tenant_id]);
    $oldSettings = $getCurrentSettingsStmt->fetch(PDO::FETCH_ASSOC);

    $communicationAddonManager = new CommunicationAddonManager($pdo, $tenant_id);
    $has_smtp_addon = $communicationAddonManager->hasActiveAddon($tenant_id, 'smtp');
    if (!$has_smtp_addon && $oldSettings) {
        // Protect SMTP fields from direct POST updates when SMTP add-on is not active.
        $smtp_host = $oldSettings['smtp_host'] ?? '';
        $smtp_port = $oldSettings['smtp_port'] ?? '';
        $smtp_encryption = $oldSettings['smtp_encryption'] ?? '';
        $smtp_username = $oldSettings['smtp_username'] ?? '';
        $smtp_password = $oldSettings['smtp_password'] ?? '';
        $smtp_from_email = $oldSettings['smtp_from_email'] ?? '';
        $smtp_from_name = $oldSettings['smtp_from_name'] ?? '';
        $smtp_enabled = 0;
    }

    // Handle logo upload (if a new file is uploaded) using SecureFileUpload
    $logo_path = '';
    if (!empty($logo['name'])) {
        try {
            $uploader = new SecureFileUpload(2 * 1024 * 1024, '../uploads/');
            $result = $uploader->upload('logo', 'logo', 1);
            
            if ($result['success']) {
                $logo_path = $result['data']['filename'];  // Save just the file name (not the full path)
            } else {
                die("Failed to upload logo image: " . $result['error']);
            }
        } catch (Exception $e) {
            die("Upload error: " . $e->getMessage());
        }
    } else {
        // If no new file is uploaded, keep the existing logo (only its file name)
        $logo_path = $_POST['existing_logo'] ?? '';
    }

    // Update query to save logo name and SMTP settings (not full path)
    $query = "
        UPDATE settings SET
            agency_name = ?, title = ?, phone = ?, email = ?, address = ?, logo = ?,
            smtp_host = ?, smtp_port = ?, smtp_encryption = ?, smtp_username = ?, smtp_password = ?, smtp_from_email = ?, smtp_from_name = ?, smtp_enabled = ?
        WHERE id = ? AND tenant_id = ?
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$agency_name, $title, $phone, $email, $address, $logo_path,
                     $smtp_host, $smtp_port, $smtp_encryption, $smtp_username, $smtp_password, $smtp_from_email, $smtp_from_name, $smtp_enabled,
                     $id, $tenant_id]);

    if ($stmt->rowCount() > 0) {
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
            'logo' => $oldSettings['logo'],
            'smtp_host' => $oldSettings['smtp_host'] ?? '',
            'smtp_port' => $oldSettings['smtp_port'] ?? '',
            'smtp_encryption' => $oldSettings['smtp_encryption'] ?? '',
            'smtp_username' => $oldSettings['smtp_username'] ?? '',
            'smtp_from_email' => $oldSettings['smtp_from_email'] ?? '',
            'smtp_from_name' => $oldSettings['smtp_from_name'] ?? '',
            'smtp_enabled' => $oldSettings['smtp_enabled'] ?? 0
        ]);

        // Create new values JSON
        $newValues = json_encode([
            'id' => $id,
            'agency_name' => $agency_name,
            'title' => $title,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'logo' => $logo_path,
            'smtp_host' => $smtp_host,
            'smtp_port' => $smtp_port,
            'smtp_encryption' => $smtp_encryption,
            'smtp_username' => $smtp_username,
            'smtp_from_email' => $smtp_from_email,
            'smtp_from_name' => $smtp_from_name,
            'smtp_enabled' => $smtp_enabled
        ]);
        
        // Insert activity log
         $logQuery = "INSERT INTO activity_log (user_id, ip_address, user_agent, action, table_name, record_id, old_values, new_values, created_at, tenant_id) 
                     VALUES (?, ?, ?, 'update', 'settings', ?, ?, ?, NOW(), ?)";
         $logStmt = $pdo->prepare($logQuery);
         
         if (!$logStmt->execute([$userId, $ipAddress, $userAgent, $id, $oldValues, $newValues, $tenant_id])) {
             // Log the error but continue
             error_log("Failed to insert activity log");
         }
        
        $_SESSION['settings_message'] = "Settings updated successfully!";
        $_SESSION['settings_type'] = 'success';
    } else {
        $_SESSION['settings_message'] = "No changes made or update failed.";
        $_SESSION['settings_type'] = 'danger';
    }

    header('Location: tenant_settings.php');
    exit();
}
?>
