<?php
/**
 * Fix WhatsApp integration issues
 * This script helps diagnose and fix common WhatsApp integration problems
 */

require_once 'includes/db.php';
require_once 'api/whatsapp/WhatsAppManager.php';

echo "=== WhatsApp Integration Fix Tool ===\n\n";

try {
    // Initialize WhatsApp Manager
    $whatsapp = new WhatsAppManager(2);
    
    // Get settings via reflection
    $reflection = new ReflectionClass($whatsapp);
    $settingsProperty = $reflection->getProperty('settings');
    $settingsProperty->setAccessible(true);
    $settings = $settingsProperty->getValue($whatsapp);
    
    echo "1. CURRENT CONFIGURATION:\n";
    echo "   Provider: " . ($settings['provider'] ?? 'N/A') . "\n";
    echo "   Status: " . ($settings['status'] ?? 'N/A') . "\n";
    echo "   Phone Number ID: " . ($settings['phone_number_id'] ?? 'N/A') . "\n";
    echo "   API Token: " . (empty($settings['api_token']) ? 'Not set' : substr($settings['api_token'], 0, 15) . '...') . "\n";
    echo "   Auto Notifications: " . ($settings['auto_notifications'] ?? '0') . "\n";
    echo "   Real Time Notifications: " . ($settings['real_time_notifications'] ?? '0') . "\n";
    
    echo "\n2. IDENTIFIED ISSUES:\n";
    
    // Check API token validity by making a test API call
    if (!empty($settings['api_token']) && !empty($settings['phone_number_id'])) {
        echo "   - Testing API token validity...\n";
        
        $ch = curl_init();
        $url = "https://graph.facebook.com/v18.0/" . $settings['phone_number_id'];
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $settings['api_token']
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            echo "   ✓ API token is valid\n";
        } else {
            echo "   ✗ API token is INVALID (HTTP $http_code)\n";
            echo "     Error: The access token may be expired or revoked\n";
            echo "     Solution: Generate a new access token from Meta Developer Portal\n";
        }
    } else {
        echo "   ✗ API token or phone number ID is missing\n";
    }
    
    // Check for recipient phone number issue
    echo "\n   - Checking recipient phone number format...\n";
    $stmt = $pdo->query("SELECT DISTINCT phone_number FROM whatsapp_messages WHERE status = 'failed' AND error_message LIKE '%not in allowed list%' LIMIT 5");
    $problem_numbers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($problem_numbers)) {
        echo "   ✗ Found phone numbers not in allowed list:\n";
        foreach ($problem_numbers as $num) {
            echo "     - " . $num['phone_number'] . "\n";
        }
        echo "     Solution: Add these numbers to allowed list in Meta Developer Portal\n";
    } else {
        echo "   ✓ No phone number format issues detected\n";
    }
    
    echo "\n3. RECOMMENDED FIXES:\n";
    echo "   A. REGENERATE ACCESS TOKEN:\n";
    echo "      1. Go to https://developers.facebook.com/apps/\n";
    echo "      2. Select your WhatsApp Business app\n";
    echo "      3. Go to WhatsApp → API Setup\n";
    echo "      4. Generate a new permanent access token\n";
    echo "      5. Update the token in WhatsApp settings page\n\n";
    
    echo "   B. ADD PHONE NUMBERS TO ALLOWED LIST:\n";
    echo "      1. In Meta Developer Portal, go to WhatsApp → Configuration\n";
    echo "      2. Scroll to 'Allowed Phone Numbers' section\n";
    echo "      3. Add the following numbers:\n";
    echo "         - +93780310431 (your number)\n";
    echo "         - 0777305730 (other test numbers)\n";
    echo "      4. Save changes\n\n";
    
    echo "   C. UPDATE SYSTEM CONFIGURATION:\n";
    echo "      1. Enable 'Real Time Notifications' in settings\n";
    echo "      2. Test with a simple message first\n";
    echo "      3. Monitor error logs\n\n";
    
    echo "   D. ALTERNATIVE SOLUTION (Use Mock Provider for Testing):\n";
    echo "      If you just need notifications for testing, switch to mock provider:\n";
    echo "      1. Update provider to 'twilio' (uses mock)\n";
    echo "      2. Messages will be logged but not actually sent\n";
    echo "      3. Good for development/testing\n";
    
    echo "\n4. QUICK FIX SCRIPT:\n";
    echo "   To switch to mock provider for testing, run:\n";
    echo "   UPDATE whatsapp_settings SET provider = 'twilio', real_time_notifications = 1 WHERE tenant_id = 2;\n";
    
    // Offer to apply quick fix
    echo "\n5. APPLY QUICK FIX NOW? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) === 'y') {
        echo "   Applying quick fix...\n";
        
        // Switch to mock provider
        $updateStmt = $pdo->prepare("
            UPDATE whatsapp_settings 
            SET provider = 'twilio', 
                real_time_notifications = 1,
                updated_at = NOW()
            WHERE tenant_id = 2
        ");
        
        if ($updateStmt->execute()) {
            echo "   ✓ Switched to mock provider\n";
            echo "   ✓ Enabled real-time notifications\n";
            echo "   Note: Messages will be logged but not actually sent via WhatsApp\n";
        } else {
            echo "   ✗ Failed to update settings\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIX COMPLETE ===\n";
echo "Next steps:\n";
echo "1. Update your WhatsApp Business API credentials\n";
echo "2. Add recipient phone numbers to allowed list\n";
echo "3. Test with a new booking\n";
echo "4. Check error logs for any remaining issues\n";
?>