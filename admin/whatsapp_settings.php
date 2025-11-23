<?php
/**
 * WhatsApp Settings Management Interface
 * Admin interface for configuring tenant WhatsApp settings and templates
 */

// Check authentication and permissions
session_start();
require_once '../includes/db.php';
require_once '../includes/conn.php';

// Verify user authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$tenant_id = $_SESSION['tenant_id'] ?? null;
$user_id = $_SESSION['user_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    handleAjaxRequest();
    exit;
}

// Load existing settings with debugging - use different variable name to avoid conflict
$whatsapp_settings = loadWhatsAppSettings($tenant_id);

// If no settings exist, create default ones
if (empty($whatsapp_settings)) {
    error_log("DEBUG: WhatsApp settings are empty for tenant_id: " . $tenant_id);
    ensureDefaultSettings($tenant_id);
    // Reload settings after creating defaults
    $whatsapp_settings = loadWhatsAppSettings($tenant_id);
}

// Merge with defaults to ensure all fields have values
$defaults = getDefaultSettings();
$whatsapp_settings = array_merge($defaults, $whatsapp_settings);

// Load other data
$templates = loadTemplates($tenant_id);
$analytics = loadAnalytics($tenant_id);


function loadWhatsAppSettings($tenant_id) {
    global $pdo;
    
    try {
        // Query for specific tenant settings
        $stmt = $pdo->prepare("SELECT * FROM whatsapp_settings WHERE tenant_id = ?");
        $stmt->execute([$tenant_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no settings exist, return empty array
        if (!$settings) {
            error_log("No WhatsApp settings found for tenant_id: " . $tenant_id);
            return [];
        }
        
        return $settings;
        
    } catch (Exception $e) {
        error_log("Error loading WhatsApp settings for tenant_id " . $tenant_id . ": " . $e->getMessage());
        return [];
    }
}

function getDefaultSettings() {
    return [
        'provider' => 'meta',
        'api_token' => '',
        'phone_number_id' => '',
        'webhook_verify_token' => '',
        'webhook_url' => '',
        'auto_notifications' => 1,
        'real_time_notifications' => 0,
        'max_messages_per_hour' => 1000,
        'retry_attempts' => 3,
        'status' => 'inactive'
    ];
}

function ensureDefaultSettings($tenant_id) {
    global $pdo;
    
    // Check if settings exist
    $stmt = $pdo->prepare("SELECT id FROM whatsapp_settings WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $exists = $stmt->fetch();
    
    if (!$exists) {
        // Create default settings
        try {
            $stmt = $pdo->prepare("
                INSERT INTO whatsapp_settings (
                    tenant_id, provider, api_token, phone_number_id,
                    webhook_verify_token, webhook_url, status, auto_notifications,
                    real_time_notifications, max_messages_per_hour, retry_attempts,
                    created_at, updated_at
                ) VALUES (?, 'meta', '', '', '', '', 'inactive', 1, 0, 1000, 3, NOW(), NOW())
            ");
            $stmt->execute([$tenant_id]);
            error_log("Created default WhatsApp settings for tenant_id: " . $tenant_id);
            return true;
        } catch (Exception $e) {
            error_log("Failed to create default settings: " . $e->getMessage());
            return false;
        }
    }
    return true;
}


function loadTemplates($tenant_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM whatsapp_templates 
            WHERE tenant_id = ? 
            ORDER BY template_type, language
        ");
        $stmt->execute([$tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error loading templates: " . $e->getMessage());
        return [];
    }
}

function loadAnalytics($tenant_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM whatsapp_analytics 
            WHERE tenant_id = ? 
            ORDER BY date DESC 
            LIMIT 30
        ");
        $stmt->execute([$tenant_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error loading analytics: " . $e->getMessage());
        return [];
    }
}

function handleAjaxRequest() {
    global $pdo, $tenant_id, $user_id;
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_settings':
                updateSettings();
                break;
            case 'save_template':
                saveTemplate();
                break;
            case 'test_connection':
                testConnection();
                break;
            case 'send_test_message':
                sendTestMessage();
                break;
            case 'get_queue_status':
                getQueueStatus();
                break;
            case 'process_queue':
                processQueue();
                break;
            default:
                throw new Exception("Invalid action");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateSettings() {
    global $pdo, $tenant_id;
    
    $settings = [
        'provider' => $_POST['provider'] ?? 'meta',
        'api_token' => $_POST['api_token'] ?? '',
        'phone_number_id' => $_POST['phone_number_id'] ?? '',
        'webhook_verify_token' => $_POST['webhook_verify_token'] ?? '',
        'webhook_url' => $_POST['webhook_url'] ?? '',
        'auto_notifications' => isset($_POST['auto_notifications']) ? 1 : 0,
        'real_time_notifications' => isset($_POST['real_time_notifications']) ? 1 : 0,
        'max_messages_per_hour' => (int)($_POST['max_messages_per_hour'] ?? 1000),
        'retry_attempts' => (int)($_POST['retry_attempts'] ?? 3),
        'status' => $_POST['status'] ?? 'inactive'
    ];
    
    try {
        // First check if settings exist
        $check_stmt = $pdo->prepare("SELECT id FROM whatsapp_settings WHERE tenant_id = ?");
        $check_stmt->execute([$tenant_id]);
        $exists = $check_stmt->fetch();
        
        if ($exists) {
            // Update existing settings
            $stmt = $pdo->prepare("
                UPDATE whatsapp_settings
                SET provider = ?, api_token = ?, phone_number_id = ?,
                    webhook_verify_token = ?, webhook_url = ?, auto_notifications = ?,
                    real_time_notifications = ?, max_messages_per_hour = ?,
                    retry_attempts = ?, status = ?, updated_at = NOW()
                WHERE tenant_id = ?
            ");
            
            $result = $stmt->execute([
                $settings['provider'],
                $settings['api_token'],
                $settings['phone_number_id'],
                $settings['webhook_verify_token'],
                $settings['webhook_url'],
                $settings['auto_notifications'],
                $settings['real_time_notifications'],
                $settings['max_messages_per_hour'],
                $settings['retry_attempts'],
                $settings['status'],
                $tenant_id
            ]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("No settings were updated. Please check if the record exists.");
            }
        } else {
            // Insert new settings
            $stmt = $pdo->prepare("
                INSERT INTO whatsapp_settings (
                    tenant_id, provider, api_token, phone_number_id,
                    webhook_verify_token, webhook_url, auto_notifications,
                    real_time_notifications, max_messages_per_hour,
                    retry_attempts, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $result = $stmt->execute([
                $tenant_id,
                $settings['provider'],
                $settings['api_token'],
                $settings['phone_number_id'],
                $settings['webhook_verify_token'],
                $settings['webhook_url'],
                $settings['auto_notifications'],
                $settings['real_time_notifications'],
                $settings['max_messages_per_hour'],
                $settings['retry_attempts'],
                $settings['status']
            ]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
        
    } catch (Exception $e) {
        error_log("WhatsApp settings update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function saveTemplate() {
    global $pdo, $tenant_id, $user_id;
    
    $template_type = $_POST['template_type'] ?? '';
    $language = $_POST['language'] ?? 'en';
    $message_template = $_POST['message_template'] ?? '';
    $template_name = $_POST['template_name'] ?? '';
    
    if (empty($template_type) || empty($message_template)) {
        throw new Exception("Template type and message are required");
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO whatsapp_templates (
            tenant_id, template_name, template_type, language, 
            message_template, created_by, status
        ) VALUES (?, ?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE
            template_name = VALUES(template_name),
            message_template = VALUES(message_template),
            updated_at = NOW()
    ");
    
    $stmt->execute([
        $tenant_id,
        $template_name ?: "{$template_type}_{$language}",
        $template_type,
        $language,
        $message_template,
        $user_id
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Template saved successfully']);
}

function testConnection() {
    global $pdo, $tenant_id;
    
    // Load current settings
    $stmt = $pdo->prepare("SELECT * FROM whatsapp_settings WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $api_token = $_POST['api_token'] ?? $settings['api_token'] ?? '';
    $phone_number_id = $_POST['phone_number_id'] ?? $settings['phone_number_id'] ?? '';
    
    if (empty($api_token) || empty($phone_number_id)) {
        throw new Exception("API token and phone number ID are required");
    }
    
    // Test basic connectivity first
    if (!function_exists('curl_init')) {
        echo json_encode([
            'success' => false,
            'message' => 'cURL is not available on this server. Please enable cURL extension in php.ini (extension=curl).'
        ]);
        return;
    }
    
    // Test server IP to see if using VPN
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://httpbin.org/ip");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $ip_response = curl_exec($ch);
    $ip_error = curl_error($ch);
    curl_close($ch);
    
    $server_ip_info = '';
    if (!$ip_error && $ip_response) {
        $ip_data = json_decode($ip_response, true);
        $server_ip_info = " | Server IP: " . ($ip_data['origin'] ?? 'Unknown');
    }
    
    // Test basic connectivity with multiple fallback methods
    $test_sites = [
        'https://www.google.com',
        'https://graph.facebook.com',
        'https://8.8.8.8' // Google DNS
    ];
    
    $network_working = false;
    $network_error = '';
    
    foreach ($test_sites as $test_url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $test_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; WhatsApp API Test)');
        
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if (!$curl_error && $http_code >= 200 && $http_code < 400) {
            $network_working = true;
            break;
        } else {
            $network_error = $curl_error ?: "HTTP {$http_code}";
        }
    }
    
    if (!$network_working) {
        echo json_encode([
            'success' => false,
            'message' => 'Network connectivity issue: ' . $network_error . '. Check firewall/proxy settings.' . $server_ip_info
        ]);
        return;
    }
    
    // Test WhatsApp API connection
    $api_url = "https://graph.facebook.com/v18.0/{$phone_number_id}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; WhatsApp API Client)');
    
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);
    
    if ($curl_error) {
        echo json_encode([
            'success' => false,
            'message' => 'WhatsApp API connection error: ' . $curl_error . ' (Time: ' . $total_time . 's)' . $server_ip_info
        ]);
        return;
    }
    
    if ($http_code === 200) {
        echo json_encode([
            'success' => true,
            'message' => "Connection test successful - WhatsApp API is accessible ({$total_time}s)" . $server_ip_info
        ]);
    } elseif ($http_code === 400) {
        echo json_encode([
            'success' => false,
            'message' => 'Bad Request - Check your API token and phone number ID format. HTTP 400: ' . $response . $server_ip_info
        ]);
    } elseif ($http_code === 401) {
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized - Invalid API token or insufficient permissions. HTTP 401' . $server_ip_info
        ]);
    } elseif ($http_code === 403) {
        echo json_encode([
            'success' => false,
            'message' => 'Forbidden - Check if your WhatsApp Business account is approved and phone number is verified. HTTP 403' . $server_ip_info
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => "WhatsApp API error - HTTP {$http_code}: {$response} (Time: {$total_time}s)" . $server_ip_info
        ]);
    }
}

function sendTestMessage() {
    global $pdo, $tenant_id;
    
    $phone_number = $_POST['phone_number'] ?? '';
    $message = $_POST['message'] ?? 'Test message from WhatsApp API';
    
    if (empty($phone_number)) {
        throw new Exception("Phone number is required");
    }
    
    // Load WhatsApp settings
    $stmt = $pdo->prepare("SELECT * FROM whatsapp_settings WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings || empty($settings['api_token']) || empty($settings['phone_number_id'])) {
        throw new Exception("WhatsApp settings not configured properly");
    }
    
    // Clean phone number (remove + and other characters)
    $phone_number = preg_replace('/[^0-9]/', '', $phone_number);
    if (substr($phone_number, 0, 1) === '1') {
        // Add country code if missing
        $phone_number = '1' . $phone_number;
    }
    
    // Check if cURL is available
    if (!function_exists('curl_init')) {
        // Fallback: Simulate success for testing if cURL is not available
        $stmt = $pdo->prepare("
            INSERT INTO whatsapp_messages (
                tenant_id, phone_number, message, message_type,
                reference_id, status, provider_message_id, created_at
            ) VALUES (?, ?, ?, 'test', 0, 'simulated_success', 'SIM_' . NOW(), NOW())
        ");
        $stmt->execute([$tenant_id, $phone_number, $message, 'simulated']);
        
        echo json_encode([
            'success' => false,
            'message' => 'cURL not available - simulated message created for testing. Contact administrator to enable cURL for real WhatsApp API integration.'
        ]);
        return;
    }
    
    // Prepare message payload for Meta WhatsApp Cloud API
    $message_data = [
        'messaging_product' => 'whatsapp',
        'to' => $phone_number,
        'type' => 'text',
        'text' => [
            'body' => $message
        ]
    ];
    
    // Send via Meta WhatsApp Cloud API
    $api_url = "https://graph.facebook.com/v18.0/{$settings['phone_number_id']}/messages";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $settings['api_token'],
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Skip SSL verification for testing
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $response_data = json_decode($response, true);
    
    // Insert message record
    $stmt = $pdo->prepare("
        INSERT INTO whatsapp_messages (
            tenant_id, phone_number, message, message_type,
            reference_id, status, provider_message_id, created_at
        ) VALUES (?, ?, ?, 'test', 0, ?, ?, NOW())
    ");
    
    if ($curl_error) {
        // Network error
        $stmt->execute([$tenant_id, $phone_number, $message, 'failed', null]);
        echo json_encode([
            'success' => false,
            'message' => 'Network error: ' . $curl_error
        ]);
        return;
    }
    
    if ($http_code === 200 && isset($response_data['messages'][0]['id'])) {
        // Message sent successfully
        $stmt->execute([$tenant_id, $phone_number, $message, 'sent', $response_data['messages'][0]['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Test message sent successfully via WhatsApp API',
            'message_id' => $response_data['messages'][0]['id']
        ]);
    } else {
        // Message failed
        $error_msg = $response_data['error']['message'] ?? $response;
        $stmt->execute([$tenant_id, $phone_number, $message, 'failed', null]);
        
        echo json_encode([
            'success' => false,
            'message' => 'WhatsApp API error - HTTP ' . $http_code . ': ' . $error_msg
        ]);
    }
}

function getQueueStatus() {
    global $pdo, $tenant_id;
    
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM whatsapp_messages 
        WHERE tenant_id = ? 
        GROUP BY status
    ");
    $stmt->execute([$tenant_id]);
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'queue_status' => $statuses
    ]);
}

function processQueue() {
    global $pdo, $tenant_id;
    
    // Get pending messages
    $stmt = $pdo->prepare("
        SELECT * FROM whatsapp_messages
        WHERE tenant_id = ? AND status = 'pending'
        ORDER BY created_at ASC
        LIMIT 10
    ");
    $stmt->execute([$tenant_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Load WhatsApp settings
    $stmt = $pdo->prepare("SELECT * FROM whatsapp_settings WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings || empty($settings['api_token']) || empty($settings['phone_number_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'WhatsApp settings not configured properly'
        ]);
        return;
    }
    
    $processed = 0;
    $failed = 0;
    
    foreach ($messages as $message) {
        $success = false;
        $provider_message_id = null;
        
        if ($message['message_type'] === 'text') {
            // Clean phone number
            $phone_number = preg_replace('/[^0-9]/', '', $message['phone_number']);
            
            // Prepare message payload
            $message_data = [
                'messaging_product' => 'whatsapp',
                'to' => $phone_number,
                'type' => 'text',
                'text' => [
                    'body' => $message['message']
                ]
            ];
            
            // Send via Meta WhatsApp Cloud API
            $api_url = "https://graph.facebook.com/v18.0/{$settings['phone_number_id']}/messages";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $settings['api_token'],
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $response_data = json_decode($response, true);
            
            if ($http_code === 200 && isset($response_data['messages'][0]['id'])) {
                $success = true;
                $provider_message_id = $response_data['messages'][0]['id'];
            }
        }
        
        // Update message status
        $new_status = $success ? 'sent' : 'failed';
        $stmt = $pdo->prepare("
            UPDATE whatsapp_messages
            SET status = ?, provider_message_id = ?, sent_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$new_status, $provider_message_id, $message['id']]);
        
        if ($success) {
            $processed++;
        } else {
            $failed++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'processed' => $processed,
        'failed' => $failed,
        'total' => count($messages)
    ]);
}

include '../includes/header.php';
?>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="page-header-title">
                                <h5 class="m-b-10">WhatsApp Automation Settings</h5>
                                <p class="m-b-0">Configure WhatsApp notifications for your tenant</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-right">
                                <button type="button" class="btn btn-primary" id="test-connection-btn" onclick="testConnection()">
                                    <i class="fas fa-plug" id="test-connection-icon"></i> <span id="test-connection-text">Test Connection</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="main-body">
                <div class="page-wrapper">
                    <div class="row">
                        <!-- Settings Tab -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>WhatsApp Configuration</h5>
                                </div>
                                <div class="card-body">
                                    <!-- System Information -->
                                    <div class="alert alert-info">
                                        <strong>System Information:</strong><br>
                                        cURL Available: <?= function_exists('curl_init') ? '✅ Yes' : '❌ No' ?><br>
                                        SSL Enabled: <?= extension_loaded('openssl') ? '✅ Yes' : '❌ No' ?><br>
                                        PHP Version: <?= phpversion() ?><br>
                                        Server: <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?>
                                    </div>
                                    
                                    <form id="whatsapp-settings-form">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="provider">Provider</label>
                                                    <select class="form-control" id="provider" name="provider">
                                                        <option value="meta" <?= ($whatsapp_settings['provider'] ?? 'meta') === 'meta' ? 'selected' : '' ?>>Meta WhatsApp Business API</option>
                                                        <option value="twilio" <?= ($whatsapp_settings['provider'] ?? 'meta') === 'twilio' ? 'selected' : '' ?>>Twilio</option>
                                                        <option value="messagebird" <?= ($whatsapp_settings['provider'] ?? 'meta') === 'messagebird' ? 'selected' : '' ?>>MessageBird</option>
                                                        <option value="360dialog" <?= ($whatsapp_settings['provider'] ?? 'meta') === '360dialog' ? 'selected' : '' ?>>360Dialog</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="status">Status</label>
                                                    <select class="form-control" id="status" name="status">
                                                        <option value="active" <?= ($whatsapp_settings['status'] ?? 'inactive') === 'active' ? 'selected' : '' ?>>Active</option>
                                                        <option value="inactive" <?= ($whatsapp_settings['status'] ?? 'inactive') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                        <option value="testing" <?= ($whatsapp_settings['status'] ?? 'inactive') === 'testing' ? 'selected' : '' ?>>Testing</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="api_token">API Token</label>
                                            <input type="password" class="form-control" id="api_token" name="api_token"
                                                   value="<?= htmlspecialchars($whatsapp_settings['api_token'] ?? '') ?>"
                                                   placeholder="Enter your WhatsApp API token">
                                        </div>

                                        <div class="form-group">
                                            <label for="phone_number_id">Phone Number ID</label>
                                            <input type="text" class="form-control" id="phone_number_id" name="phone_number_id"
                                                   value="<?= htmlspecialchars($whatsapp_settings['phone_number_id'] ?? '') ?>"
                                                   placeholder="Enter your WhatsApp Business Phone Number ID">
                                        </div>

                                        <div class="form-group">
                                            <label for="webhook_verify_token">Webhook Verify Token</label>
                                            <input type="text" class="form-control" id="webhook_verify_token" name="webhook_verify_token"
                                                   value="<?= htmlspecialchars($whatsapp_settings['webhook_verify_token'] ?? '') ?>"
                                                   placeholder="Enter webhook verification token">
                                        </div>

                                        <div class="form-group">
                                            <label for="webhook_url">Webhook URL</label>
                                            <input type="text" class="form-control" id="webhook_url" name="webhook_url"
                                                   value="<?= htmlspecialchars($whatsapp_settings['webhook_url'] ?? '') ?>"
                                                   placeholder="Enter your webhook URL">
                                            <small class="form-text text-muted">
                                                URL where WhatsApp will send status updates and messages
                                            </small>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="max_messages_per_hour">Max Messages/Hour</label>
                                                    <input type="number" class="form-control" id="max_messages_per_hour" name="max_messages_per_hour"
                                                           value="<?= $whatsapp_settings['max_messages_per_hour'] ?? 1000 ?>" min="1">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="retry_attempts">Retry Attempts</label>
                                                    <input type="number" class="form-control" id="retry_attempts" name="retry_attempts"
                                                           value="<?= $whatsapp_settings['retry_attempts'] ?? 3 ?>" min="0" max="10">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="auto_notifications" name="auto_notifications"
                                                       <?= ($whatsapp_settings['auto_notifications'] ?? 1) ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="auto_notifications">
                                                    Enable Auto Notifications
                                                </label>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="real_time_notifications" name="real_time_notifications"
                                                       <?= ($whatsapp_settings['real_time_notifications'] ?? 0) ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="real_time_notifications">
                                                    Real-time Notifications (Queue if disabled)
                                                </label>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-save"></i> Save Settings
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Templates Tab -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Message Templates</h5>
                                    <button type="button" class="btn btn-sm btn-primary float-right" onclick="openTemplateModal()">
                                        <i class="fas fa-plus"></i> Add Template
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div id="templates-list">
                                        <?php if (empty($templates)): ?>
                                            <p class="text-muted">No templates found. Add your first template.</p>
                                        <?php else: ?>
                                            <?php foreach ($templates as $template): ?>
                                                <div class="template-item" data-id="<?= $template['id'] ?>">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <strong><?= ucfirst($template['template_type']) ?></strong> 
                                                            <small class="text-muted">(<?= strtoupper($template['language']) ?>)</small>
                                                            <br>
                                                            <small><?= htmlspecialchars(substr($template['message_template'], 0, 100)) ?>...</small>
                                                        </div>
                                                        <div>
                                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editTemplate(<?= $template['id'] ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteTemplate(<?= $template['id'] ?>)">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <hr>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Test Message Section -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Send Test Message</h5>
                                </div>
                                <div class="card-body">
                                    <form id="test-message-form">
                                        <div class="form-group">
                                            <label for="test_phone">Phone Number</label>
                                            <input type="text" class="form-control" id="test_phone" name="phone_number" 
                                                   placeholder="+1234567890" required>
                                            <small class="form-text text-muted">Include country code (e.g., +1 for US)</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="test_message">Message</label>
                                            <textarea class="form-control" id="test_message" name="message" rows="3" 
                                                      placeholder="Enter test message">Hello! This is a test message from your WhatsApp automation system.</textarea>
                                        </div>
                                        <button type="submit" class="btn btn-info" id="send-test-btn">
                                            <i class="fas fa-paper-plane" id="send-test-icon"></i> <span id="send-test-text">Send Test Message</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Queue Status -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Message Queue Status</h5>
                                    <button type="button" class="btn btn-sm btn-warning float-right" id="process-queue-btn" onclick="processQueue()">
                                        <i class="fas fa-cog" id="process-queue-icon"></i> <span id="process-queue-text">Process Queue</span>
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div id="queue-status">
                                        <div class="text-center">
                                            <div class="spinner-border spinner-border-sm" role="status">
                                                <span class="sr-only">Loading...</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Message Template</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="template-form">
                    <input type="hidden" id="template_id" name="template_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="template_type">Template Type</label>
                                <select class="form-control" id="template_type" name="template_type" required>
                                    <option value="visa">Visa</option>
                                    <option value="umrah">Umrah</option>
                                    <option value="hotel">Hotel</option>
                                    <option value="ticket">Flight Ticket</option>
                                    <option value="refund">Refund</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="template_language">Language</label>
                                <select class="form-control" id="template_language" name="language" required>
                                    <option value="en">English</option>
                                    <option value="fa">Persian (فارسی)</option>
                                    <option value="ps">Pashto (پښتو)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="template_name">Template Name</label>
                        <input type="text" class="form-control" id="template_name" name="template_name" 
                               placeholder="Optional template name">
                    </div>
                    <div class="form-group">
                        <label for="message_template">Message Template</label>
                        <textarea class="form-control" id="message_template" name="message_template" rows="8" 
                                  placeholder="Enter your message template with {{variables}}" required></textarea>
                        <small class="form-text text-muted">
                            Available variables: {client_name}, {booking_date}, {agency_name}, {contact_info}, etc.
                            <br>Use {variable_name} format for variables.
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveTemplate()">Save Template</button>
            </div>
        </div>
    </div>
</div>
    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
<script>
$(document).ready(function() {
    loadQueueStatus();
    
    // Settings form submission
    $('#whatsapp-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'update_settings');
        
        $.ajax({
            url: '',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                const data = JSON.parse(response);
                if (data.success) {
                    alert('Settings updated successfully!');
                } else {
                    alert('Error: ' + data.message);
                }
            },
            error: function() {
                alert('Error updating settings');
            }
        });
    });
    
    // Test message form submission
    $('#test-message-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'send_test_message');
        
        // Show loading state
        setButtonLoading('send-test-btn', 'send-test-icon', 'send-test-text', true, 'Sending...');
        
        $.ajax({
            url: '',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                const data = JSON.parse(response);
                if (data.success) {
                    alert('Test message sent successfully!');
                    $('#test-message-form')[0].reset();
                } else {
                    alert('Error: ' + data.message);
                }
            },
            error: function() {
                alert('Error sending test message');
            },
            complete: function() {
                // Hide loading state
                setButtonLoading('send-test-btn', 'send-test-icon', 'send-test-text', false);
            }
        });
    });
});

function setButtonLoading(buttonId, iconId, textId, isLoading, loadingText = 'Loading...') {
    const btn = $('#' + buttonId);
    const icon = $('#' + iconId);
    const text = $('#' + textId);
    
    if (isLoading) {
        btn.prop('disabled', true);
        icon.removeClass('fa-plug fa-paper-plane fa-cog').addClass('fa-spinner fa-spin');
        text.text(loadingText);
    } else {
        btn.prop('disabled', false);
        icon.removeClass('fa-spinner fa-spin').addClass(iconId.includes('test-connection') ? 'fa-plug' : iconId.includes('send-test') ? 'fa-paper-plane' : 'fa-cog');
        text.text(iconId.includes('test-connection') ? 'Test Connection' : iconId.includes('send-test') ? 'Send Test Message' : 'Process Queue');
    }
}

function testConnection() {
    const apiToken = $('#api_token').val();
    const phoneNumberId = $('#phone_number_id').val();
    
    if (!apiToken || !phoneNumberId) {
        alert('Please fill in API Token and Phone Number ID first');
        return;
    }
    
    // Show loading state
    setButtonLoading('test-connection-btn', 'test-connection-icon', 'test-connection-text', true, 'Testing...');
    
    $.ajax({
        url: '',
        type: 'POST',
        data: {
            action: 'test_connection',
            api_token: apiToken,
            phone_number_id: phoneNumberId
        },
        success: function(response) {
            const data = JSON.parse(response);
            if (data.success) {
                alert('Connection test successful!');
            } else {
                alert('Connection test failed: ' + data.message);
            }
        },
        error: function() {
            alert('Error testing connection');
        },
        complete: function() {
            // Hide loading state
            setButtonLoading('test-connection-btn', 'test-connection-icon', 'test-connection-text', false);
        }
    });
}

function openTemplateModal(templateId = null) {
    if (templateId) {
        // Load existing template data
        // Implementation would load template data via AJAX
        $('#templateModal').modal('show');
    } else {
        $('#template-form')[0].reset();
        $('#template_id').val('');
        $('#templateModal').modal('show');
    }
}

function saveTemplate() {
    const formData = new FormData($('#template-form')[0]);
    formData.append('action', 'save_template');
    
    $.ajax({
        url: '',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            const data = JSON.parse(response);
            if (data.success) {
                alert('Template saved successfully!');
                $('#templateModal').modal('hide');
                location.reload(); // Reload to show updated templates
            } else {
                alert('Error: ' + data.message);
            }
        },
        error: function() {
            alert('Error saving template');
        }
    });
}

function loadQueueStatus() {
    $.ajax({
        url: '',
        type: 'POST',
        data: { action: 'get_queue_status' },
        success: function(response) {
            const data = JSON.parse(response);
            if (data.success) {
                displayQueueStatus(data.queue_status);
            }
        },
        error: function() {
            $('#queue-status').html('<p class="text-danger">Error loading queue status</p>');
        }
    });
}

function displayQueueStatus(statuses) {
    let html = '';
    if (statuses.length === 0) {
        html = '<p class="text-muted">No messages in queue</p>';
    } else {
        statuses.forEach(status => {
            const badgeClass = getStatusBadgeClass(status.status);
            html += `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge ${badgeClass}">${status.status}</span>
                    <span class="font-weight-bold">${status.count}</span>
                </div>
            `;
        });
    }
    $('#queue-status').html(html);
}

function getStatusBadgeClass(status) {
    const classes = {
        'pending': 'badge-warning',
        'sent': 'badge-success',
        'delivered': 'badge-info',
        'failed': 'badge-danger',
        'expired': 'badge-secondary'
    };
    return classes[status] || 'badge-secondary';
}

function processQueue() {
    // Show loading state
    setButtonLoading('process-queue-btn', 'process-queue-icon', 'process-queue-text', true, 'Processing...');
    
    $.ajax({
        url: '',
        type: 'POST',
        data: { action: 'process_queue' },
        success: function(response) {
            const data = JSON.parse(response);
            if (data.success) {
                alert(`Processed ${data.processed} messages${data.failed > 0 ? ` (${data.failed} failed)` : ''}`);
                loadQueueStatus();
            } else {
                alert('Error processing queue: ' + data.message);
            }
        },
        error: function() {
            alert('Error processing queue');
        },
        complete: function() {
            // Hide loading state
            setButtonLoading('process-queue-btn', 'process-queue-icon', 'process-queue-text', false);
        }
    });
}
</script>

<?php include '../includes/admin_footer.php'; ?>