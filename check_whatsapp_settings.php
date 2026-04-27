<?php
// Simple script to check WhatsApp settings in database
require_once 'includes/db.php';

echo "Checking WhatsApp settings in database...\n\n";

try {
    // Check whatsapp_settings table
    $stmt = $pdo->query("SELECT * FROM whatsapp_settings LIMIT 5");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($settings)) {
        echo "No WhatsApp settings found in database.\n";
    } else {
        echo "Found " . count($settings) . " WhatsApp settings:\n";
        foreach ($settings as $i => $setting) {
            echo "\nSetting #" . ($i + 1) . ":\n";
            echo "  Tenant ID: " . ($setting['tenant_id'] ?? 'N/A') . "\n";
            echo "  Provider: " . ($setting['provider'] ?? 'N/A') . "\n";
            echo "  Status: " . ($setting['status'] ?? 'N/A') . "\n";
            echo "  Phone Number ID: " . ($setting['phone_number_id'] ?? 'N/A') . "\n";
            echo "  API Token: " . (empty($setting['api_token']) ? 'Not set' : 'Set (hidden)') . "\n";
            echo "  Auto Notifications: " . ($setting['auto_notifications'] ?? '0') . "\n";
            echo "  Real Time Notifications: " . ($setting['real_time_notifications'] ?? '0') . "\n";
        }
    }
    
    // Check communication_addons table
    echo "\n\nChecking communication addons...\n";
    $stmt = $pdo->query("SELECT * FROM communication_addons WHERE addon_type = 'whatsapp' LIMIT 5");
    $addons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($addons)) {
        echo "No WhatsApp addons found.\n";
    } else {
        echo "Found " . count($addons) . " WhatsApp addons:\n";
        foreach ($addons as $addon) {
            echo "  Tenant ID: " . $addon['tenant_id'] . ", Status: " . $addon['status'] . "\n";
        }
    }
    
    // Check whatsapp_messages table for recent messages
    echo "\n\nChecking recent WhatsApp messages...\n";
    $stmt = $pdo->query("SELECT * FROM whatsapp_messages ORDER BY created_at DESC LIMIT 5");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($messages)) {
        echo "No WhatsApp messages found.\n";
    } else {
        echo "Recent WhatsApp messages:\n";
        foreach ($messages as $msg) {
            echo "  ID: " . $msg['id'] . ", Phone: " . $msg['phone_number'] . ", Status: " . $msg['status'] . ", Created: " . $msg['created_at'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
?>