<?php
// Apply WhatsApp fix - switch to mock provider
require_once 'includes/db.php';

echo "Applying WhatsApp fix...\n";

try {
    // Switch to mock provider and enable real-time notifications
    $stmt = $pdo->prepare("
        UPDATE whatsapp_settings 
        SET provider = 'twilio', 
            real_time_notifications = 1,
            updated_at = NOW()
        WHERE tenant_id = 2
    ");
    
    if ($stmt->execute()) {
        $rows = $stmt->rowCount();
        echo "✓ Successfully updated WhatsApp settings\n";
        echo "  - Switched provider to 'twilio' (mock)\n";
        echo "  - Enabled real-time notifications\n";
        echo "  - Affected rows: $rows\n\n";
        
        // Verify the change
        $checkStmt = $pdo->query("SELECT provider, real_time_notifications FROM whatsapp_settings WHERE tenant_id = 2");
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Current settings:\n";
        echo "  Provider: " . ($result['provider'] ?? 'N/A') . "\n";
        echo "  Real Time Notifications: " . ($result['real_time_notifications'] ?? '0') . "\n\n";
        
        echo "Note: Messages will now use the mock provider which:\n";
        echo "  1. Logs messages to error log\n";
        echo "  2. Returns success without actually sending\n";
        echo "  3. Good for testing/development\n\n";
        
        echo "To test, create a new booking and check the error log for:\n";
        echo "  'Mock WhatsApp Message to +93780310431: ...'\n";
        
    } else {
        echo "✗ Failed to update settings\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
?>