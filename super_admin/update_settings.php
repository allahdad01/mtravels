<?php
session_start();
require_once '../includes/conn.php';

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: platform_settings.php?error=invalid_csrf');
    exit();
}

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to update_settings.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Get all form data
$platform_name = $_POST['platform_name'] ?? '';
$support_email = $_POST['support_email'] ?? '';
$contact_email = $_POST['contact_email'] ?? '';
$website_url = $_POST['website_url'] ?? '';
$contact_phone = $_POST['contact_phone'] ?? '';
$support_phone = $_POST['support_phone'] ?? '';
$contact_address = $_POST['contact_address'] ?? '';
$contact_facebook = $_POST['contact_facebook'] ?? '';
$contact_twitter = $_POST['contact_twitter'] ?? '';
$contact_linkedin = $_POST['contact_linkedin'] ?? '';
$contact_instagram = $_POST['contact_instagram'] ?? '';
$default_currency = $_POST['default_currency'] ?? '';
$max_users_per_tenant = $_POST['max_users_per_tenant'] ?? '';
$api_enabled = $_POST['api_enabled'] ?? '';

// Handle file uploads
$platform_logo = $_FILES['platform_logo'] ?? null;
$platform_favicon = $_FILES['platform_favicon'] ?? null;

$errors = [];

// Validate required fields
if (empty($platform_name)) {
    $errors[] = "Platform name is required.";
}
if (empty($support_email)) {
    $errors[] = "Support email is required.";
}
if (empty($default_currency)) {
    $errors[] = "Default currency is required.";
}
if (empty($max_users_per_tenant)) {
    $errors[] = "Max users per tenant is required.";
}

// Validate email formats
if (!empty($support_email) && !filter_var($support_email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid support email format.";
}
if (!empty($contact_email) && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid contact email format.";
}

// Validate URL formats
if (!empty($website_url) && !filter_var($website_url, FILTER_VALIDATE_URL)) {
    $errors[] = "Invalid website URL format.";
}
if (!empty($contact_facebook) && !filter_var($contact_facebook, FILTER_VALIDATE_URL)) {
    $errors[] = "Invalid Facebook URL format.";
}
if (!empty($contact_twitter) && !filter_var($contact_twitter, FILTER_VALIDATE_URL)) {
    $errors[] = "Invalid Twitter URL format.";
}
if (!empty($contact_linkedin) && !filter_var($contact_linkedin, FILTER_VALIDATE_URL)) {
    $errors[] = "Invalid LinkedIn URL format.";
}
if (!empty($contact_instagram) && !filter_var($contact_instagram, FILTER_VALIDATE_URL)) {
    $errors[] = "Invalid Instagram URL format.";
}

// Validate currency code
if (!empty($default_currency) && !preg_match('/^[A-Z]{3}$/', $default_currency)) {
    $errors[] = "Invalid currency code format (must be 3 uppercase letters).";
}

// Validate API enabled
if (!in_array($api_enabled, ['true', 'false'])) {
    $errors[] = "Invalid API enabled value.";
}

// Validate max users
if (!empty($max_users_per_tenant) && (!is_numeric($max_users_per_tenant) || $max_users_per_tenant < 1)) {
    $errors[] = "Max users per tenant must be a number greater than 0.";
}

// Handle file uploads
$upload_dir = '../uploads/';

// Create uploads directory if it doesn't exist
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle platform logo upload
$platform_logo_path = null;
if ($platform_logo && $platform_logo['size'] > 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($platform_logo['type'], $allowed_types)) {
        $errors[] = "Invalid platform logo format. Only JPEG, PNG, and GIF are allowed.";
    } elseif ($platform_logo['size'] > 2 * 1024 * 1024) { // 2MB limit
        $errors[] = "Platform logo size exceeds 2MB.";
    } else {
        $file_extension = pathinfo($platform_logo['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . time() . '_' . uniqid() . '.' . $file_extension;
        $full_path = $upload_dir . $filename;

        if (move_uploaded_file($platform_logo['tmp_name'], $full_path)) {
            $platform_logo_path = $filename;
        } else {
            $errors[] = "Failed to upload platform logo.";
        }
    }
}

// Handle platform favicon upload
$platform_favicon_path = null;
if ($platform_favicon && $platform_favicon['size'] > 0) {
    $allowed_types = ['image/x-icon', 'image/png'];
    if (!in_array($platform_favicon['type'], $allowed_types)) {
        $errors[] = "Invalid favicon format. Only ICO and PNG are allowed.";
    } elseif ($platform_favicon['size'] > 100 * 1024) { // 100KB limit
        $errors[] = "Favicon size exceeds 100KB.";
    } else {
        $file_extension = pathinfo($platform_favicon['name'], PATHINFO_EXTENSION);
        $filename = 'favicon_' . time() . '_' . uniqid() . '.' . $file_extension;
        $full_path = $upload_dir . $filename;

        if (move_uploaded_file($platform_favicon['tmp_name'], $full_path)) {
            $platform_favicon_path = $filename;
        } else {
            $errors[] = "Failed to upload favicon.";
        }
    }
}

if (empty($errors)) {
    // Update settings - include all form fields
    $settings = [
        ['key' => 'platform_name', 'value' => $platform_name, 'type' => 'string', 'description' => 'Platform name'],
        ['key' => 'support_email', 'value' => $support_email, 'type' => 'string', 'description' => 'Contact email for platform support'],
        ['key' => 'contact_email', 'value' => $contact_email, 'type' => 'string', 'description' => 'Contact email'],
        ['key' => 'website_url', 'value' => $website_url, 'type' => 'string', 'description' => 'Website URL'],
        ['key' => 'contact_phone', 'value' => $contact_phone, 'type' => 'string', 'description' => 'Contact phone number'],
        ['key' => 'support_phone', 'value' => $support_phone, 'type' => 'string', 'description' => 'Support phone number'],
        ['key' => 'contact_address', 'value' => $contact_address, 'type' => 'string', 'description' => 'Contact address'],
        ['key' => 'contact_facebook', 'value' => $contact_facebook, 'type' => 'string', 'description' => 'Facebook URL'],
        ['key' => 'contact_twitter', 'value' => $contact_twitter, 'type' => 'string', 'description' => 'Twitter URL'],
        ['key' => 'contact_linkedin', 'value' => $contact_linkedin, 'type' => 'string', 'description' => 'LinkedIn URL'],
        ['key' => 'contact_instagram', 'value' => $contact_instagram, 'type' => 'string', 'description' => 'Instagram URL'],
        ['key' => 'default_currency', 'value' => $default_currency, 'type' => 'string', 'description' => 'Default currency for new tenants'],
        ['key' => 'max_users_per_tenant', 'value' => $max_users_per_tenant, 'type' => 'integer', 'description' => 'Maximum users allowed per tenant on basic plan'],
        ['key' => 'api_enabled', 'value' => $api_enabled, 'type' => 'boolean', 'description' => 'Whether API access is enabled globally'],
    ];

    // Handle file uploads - only add to settings if new files were uploaded
    if ($platform_logo_path) {
        $settings[] = ['key' => 'platform_logo', 'value' => $platform_logo_path, 'type' => 'string', 'description' => 'Platform logo file name'];
    }

    if ($platform_favicon_path) {
        $settings[] = ['key' => 'platform_favicon', 'value' => $platform_favicon_path, 'type' => 'string', 'description' => 'Platform favicon file name'];
    }

    $stmt = $conn->prepare("INSERT INTO platform_settings (`key`, `value`, `type`, `description`, `created_at`, `updated_at`) 
                            VALUES (?, ?, ?, ?, NOW(), NOW()) 
                            ON DUPLICATE KEY UPDATE `value` = ?, `updated_at` = NOW()");
    foreach ($settings as $setting) {
        $stmt->bind_param('sssss', $setting['key'], $setting['value'], $setting['type'], $setting['description'], $setting['value']);
        $stmt->execute();
    }
    $stmt->close();

    // Log action
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                            VALUES (?, 'update_platform_settings', 'platform_setting', 0, ?, ?, NOW())");
    $details = json_encode([
        'platform_name' => $platform_name,
        'support_email' => $support_email,
        'contact_email' => $contact_email,
        'website_url' => $website_url,
        'contact_phone' => $contact_phone,
        'support_phone' => $support_phone,
        'contact_address' => $contact_address,
        'contact_facebook' => $contact_facebook,
        'contact_twitter' => $contact_twitter,
        'contact_linkedin' => $contact_linkedin,
        'contact_instagram' => $contact_instagram,
        'default_currency' => $default_currency,
        'max_users_per_tenant' => $max_users_per_tenant,
        'api_enabled' => $api_enabled,
        'logo_updated' => !!$platform_logo_path,
        'favicon_updated' => !!$platform_favicon_path
    ]);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param('iss', $user_id, $details, $ip_address);
    $stmt->execute();
    $stmt->close();

    // Return JSON response for AJAX
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Settings updated successfully',
        'redirect' => 'platform_settings.php?success=settings_updated'
    ]);
} else {
    // Return JSON response for AJAX
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => implode(', ', $errors)
    ]);
}
exit();
?>