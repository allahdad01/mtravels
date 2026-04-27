<?php
// Test sending a WhatsApp message directly
require_once 'includes/db.php';
require_once 'api/whatsapp/WhatsAppManager.php';

echo "Testing WhatsApp message sending...\n\n";

try {
    // Initialize WhatsApp Manager
    $whatsapp = new WhatsAppManager(2); // Tenant ID 2
    
    echo "1. WhatsApp Manager initialized for tenant 2\n";
    
    // Check settings via reflection
    $reflection = new ReflectionClass($whatsapp);
    $settingsProperty = $reflection->getProperty('settings');
    $settingsProperty->setAccessible(true);
    $settings = $settingsProperty->getValue($whatsapp);
    
    echo "2. Current Settings:\n";
    echo "   Provider: " . ($settings['provider'] ?? 'N/A') . "\n";
    echo "   Status: " . ($settings['status'] ?? 'N/A') . "\n";
    echo "   Phone Number ID: " . ($settings['phone_number_id'] ?? 'N/A') . "\n";
    echo "   API Token: " . (empty($settings['api_token']) ? 'Not set' : substr($settings['api_token'], 0, 10) . '...') . "\n";
    echo "   Auto Notifications: " . ($settings['auto_notifications'] ?? '0') . "\n";
    echo "   Real Time Notifications: " . ($settings['real_time_notifications'] ?? '0') . "\n";
    
    // Test sending a direct message
    echo "\n3. Testing direct message send:\n";
    
    // Get provider instance
    $providerMethod = $reflection->getMethod('getProvider');
    $providerMethod->setAccessible(true);
    $provider = $providerMethod->invoke($whatsapp);
    
    echo "   Provider class: " . get_class($provider) . "\n";
    
    // Test with phone number +93780310431
    $test_phone = "+93780310431";
    $test_message = "Test WhatsApp message from MTravels system at " . date('Y-m-d H:i:s');
    
    echo "   Sending to: $test_phone\n";
    echo "   Message: $test_message\n";
    
    $result = $provider->sendMessage($test_phone, $test_message);
    
    echo "   Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    if (isset($result['error'])) {
        echo "   Error: " . $result['error'] . "\n";
    }
    if (isset($result['message_id'])) {
        echo "   Message ID: " . $result['message_id'] . "\n";
    }
    
    // Process the queue
    echo "\n4. Processing message queue:\n";
    $processMethod = $reflection->getMethod('processQueue');
    $processMethod->setAccessible(true);
    $queueResults = $processMethod->invoke($whatsapp, 10);
    
    echo "   Processed " . count($queueResults) . " messages\n";
    
    // Check message status after processing
    echo "\n5. Checking message status in database:\n";
    $stmt = $pdo->query("SELECT id, phone_number, status, error_message FROM whatsapp_messages WHERE status = 'pending' ORDER BY created_at DESC LIMIT 5");
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Pending messages: " . count($pending) . "\n";
    foreach ($pending as $msg) {
        echo "   - ID: {$msg['id']}, Phone: {$msg['phone_number']}, Status: {$msg['status']}, Error: {$msg['error_message']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\nDone.\n";
?>