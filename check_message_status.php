<?php
// Check message status after processing
require_once 'includes/db.php';

echo "Checking WhatsApp message status...\n\n";

try {
    // Check all messages
    $stmt = $pdo->query("SELECT id, phone_number, status, error_message, sent_at FROM whatsapp_messages ORDER BY created_at DESC LIMIT 10");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Recent WhatsApp messages:\n";
    foreach ($messages as $msg) {
        echo "ID: {$msg['id']}, Phone: {$msg['phone_number']}, Status: {$msg['status']}, Sent: {$msg['sent_at']}\n";
        if ($msg['error_message']) {
            echo "  Error: " . substr($msg['error_message'], 0, 100) . "...\n";
        }
        echo "\n";
    }
    
    // Count by status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM whatsapp_messages GROUP BY status");
    $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nMessage counts by status:\n";
    foreach ($counts as $count) {
        echo "  {$count['status']}: {$count['count']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
?>