<?php
/**
 * Test WhatsApp Message Sending
 * This script helps you test WhatsApp integration with your API credentials
 */

require_once 'includes/db.php';
require_once 'api/whatsapp/WhatsAppManager.php';

echo "=== WhatsApp Integration Test ===\n\n";

// Check if user is logged in (for session)
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "⚠️  Not logged in. Starting test in admin mode...\n";
    // For testing, we'll use tenant_id 2 (from backup)
    $_SESSION['tenant_id'] = 2;
    $_SESSION['user_id'] = 1;
}

try {
    // Initialize WhatsApp Manager
    $whatsapp = new WhatsAppManager();
    
    echo "1. WhatsApp Manager initialized\n";
    echo "   Tenant ID: " . ($_SESSION['tenant_id'] ?? 'Not set') . "\n";
    
    // Check current settings
    echo "\n2. Current Settings Status:\n";
    
    // Try to get settings via reflection (since they're private)
    $reflection = new ReflectionClass($whatsapp);
    $settingsProperty = $reflection->getProperty('settings');
    $settingsProperty->setAccessible(true);
    $settings = $settingsProperty->getValue($whatsapp);
    
    echo "   Provider: " . ($settings['provider'] ?? 'Not set') . "\n";
    echo "   Status: " . ($settings['status'] ?? 'Not set') . "\n";
    echo "   Phone Number ID: " . ($settings['phone_number_id'] ?? 'Not set') . "\n";
    echo "   API Token: " . (empty($settings['api_token']) ? 'Not set' : 'Set (hidden)') . "\n";
    echo "   Auto Notifications: " . ($settings['auto_notifications'] ?? '0') . "\n";
    
    // Test sending a message
    echo "\n3. Testing Message Sending:\n";
    
    // Test phone number (replace with your test number)
    $test_phone = "93780310431"; // From backup data
    
    // Create a test message
    $test_message = "🔄 *WhatsApp Integration Test*\n\nHello! This is a test message from your MTravels WhatsApp integration.\n\n✅ Webhook configured\n✅ Database connected\n✅ API ready\n\nTime: " . date('Y-m-d H:i:s');
    
    echo "   Test Phone: " . $test_phone . "\n";
    echo "   Message Length: " . strlen($test_message) . " chars\n";
    
    // Check if we can send
    if (($settings['status'] ?? 'inactive') !== 'active') {
        echo "   ⚠️  WhatsApp status is not 'active'. Cannot send test message.\n";
        echo "   Please update settings at: http://localhost/almoqadas/mtravels/tenant_super_admin/whatsapp_settings.php\n";
    } elseif (empty($settings['api_token']) || empty($settings['phone_number_id'])) {
        echo "   ⚠️  API credentials missing. Cannot send test message.\n";
        echo "   Please add your Meta WhatsApp Business API credentials.\n";
    } else {
        echo "   ✅ Ready to send test message\n";
        
        // Uncomment to actually send (be careful with rate limits)
        /*
        $result = $whatsapp->sendBookingNotification('test', 0, [
            'client_name' => 'Test Client',
            'client_phone' => $test_phone
        ]);
        
        echo "   Send Result: " . ($result['success'] ? '✅ Success' : '❌ Failed') . "\n";
        if (!$result['success']) {
            echo "   Error: " . $result['message'] . "\n";
        }
        */
    }
    
    // Test API endpoint
    echo "\n4. Testing API Endpoint:\n";
    $api_url = "http://localhost/almoqadas/mtravels/api/whatsapp/index.php";
    echo "   API URL: " . $api_url . "\n";
    
    // Check if we can access the settings page
    echo "\n5. Settings Page:\n";
    $settings_url = "http://localhost/almoqadas/mtravels/tenant_super_admin/whatsapp_settings.php";
    echo "   Settings Page: " . $settings_url . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== CONFIGURATION GUIDE ===\n\n";

echo "A. UPDATE WHATSAPP SETTINGS:\n";
echo "   1. Go to: http://localhost/almoqadas/mtravels/tenant_super_admin/whatsapp_settings.php\n";
echo "   2. Update these fields:\n";
echo "      - Provider: 'meta' (for Meta WhatsApp Business API)\n";
echo "      - API Token: Your actual Meta WhatsApp Business API token\n";
echo "      - Phone Number ID: Your WhatsApp Business phone number ID\n";
echo "      - Status: 'active'\n";
echo "      - Auto Notifications: Enable\n";
echo "      - Webhook Verify Token: 'test' (or any string)\n";
echo "      - Webhook URL: http://localhost/almoqadas/mtravels/api/whatsapp/index.php?webhook\n\n";

echo "B. META DEVELOPER PORTAL SETUP:\n";
echo "   1. Go to: https://developers.facebook.com/apps/\n";
echo "   2. Select your WhatsApp Business app\n";
echo "   3. Go to WhatsApp → Configuration\n";
echo "   4. Set Webhook URL: http://localhost/almoqadas/mtravels/api/whatsapp/index.php?webhook\n";
echo "   5. Set Verify Token: Same as in your settings (default: 'test')\n";
echo "   6. Subscribe to: messages, message_template_status_update\n";
echo "   7. Save changes\n\n";

echo "C. TEST WITH REAL CREDENTIALS:\n";
echo "   1. Get test credentials from Meta:\n";
echo "      - Go to WhatsApp → API Setup\n";
echo "      - Get temporary access token\n";
echo "      - Get phone number ID\n";
echo "   2. Update settings with real credentials\n";
echo "   3. Send test message from Meta dashboard\n";
echo "   4. Check webhook logs in your database\n\n";

echo "D. TROUBLESHOOTING:\n";
echo "   - Ensure XAMPP Apache is running\n";
echo "   - Check PHP error logs: c:/xampp/php/logs/php_error_log\n";
echo "   - Check Apache logs: c:/xampp/apache/logs/error.log\n";
echo "   - Verify database connection in includes/db.php\n";
echo "   - Test webhook with: php test_webhook.php\n";

echo "\n=== QUICK TEST COMMANDS ===\n";
echo "1. Test webhook: php test_webhook.php\n";
echo "2. Check settings: Visit the settings page URL above\n";
echo "3. Send test via API:\n";
echo "   curl -X POST http://localhost/almoqadas/mtravels/api/whatsapp/index.php \\\n";
echo "     -H \"Content-Type: application/json\" \\\n";
echo "     -d '{\"action\":\"send\",\"type\":\"test\",\"phone\":\"93780310431\",\"message\":\"Test message\"}'\n";

?>