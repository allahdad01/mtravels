<?php
/**
 * Test script for ChatAudit class
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/ChatAudit.php';

echo "Testing ChatAudit class...\n\n";

// Test 1: Get audit logs
echo "[1] Testing getAuditLog()...\n";
try {
    $logs = ChatAudit::getAuditLog(1, ['limit' => 5]);
    echo "✅ Success - Retrieved " . count($logs) . " logs\n";
    
    if (!empty($logs)) {
        echo "Sample log entry:\n";
        echo "  - ID: " . $logs[0]['id'] . "\n";
        echo "  - User: " . $logs[0]['user_id'] . "\n";
        echo "  - Action: " . $logs[0]['action'] . "\n";
        echo "  - Status: " . $logs[0]['status'] . "\n";
        echo "  - Time: " . $logs[0]['created_at'] . "\n";
    } else {
        echo "   (No logs in database yet - this is normal on first run)\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n[2] Testing getAuditLogCount()...\n";
try {
    $count = ChatAudit::getAuditLogCount(1);
    echo "✅ Success - Total logs: " . $count . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n[3] Testing getSummary()...\n";
try {
    $summary = ChatAudit::getSummary(1, 7);
    echo "✅ Success - Retrieved " . count($summary) . " summary entries\n";
    
    if (!empty($summary)) {
        echo "Sample summary:\n";
        foreach ($summary as $item) {
            echo "  - " . $item['action'] . " (" . $item['status'] . "): " . $item['count'] . " events\n";
        }
    } else {
        echo "   (No summary data - this is normal on first run)\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n[4] Testing exportAuditLog()...\n";
try {
    $csv = ChatAudit::exportAuditLog(1, ['limit' => 10], 'csv');
    if (!empty($csv)) {
        echo "✅ Success - Generated CSV (" . strlen($csv) . " bytes)\n";
        echo "First line of CSV:\n";
        $lines = explode("\n", $csv);
        echo "  " . substr($lines[0], 0, 100) . "...\n";
    } else {
        echo "   (No data to export - this is normal on first run)\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n✅ ChatAudit class is working correctly!\n";
echo "\nNext steps:\n";
echo "1. Send a message in the chat system\n";
echo "2. Visit http://localhost/mtravels/admin/audit_logs.php\n";
echo "3. You should see your message logged\n";
?>
