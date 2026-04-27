<?php
/**
 * Cron job to process pending WhatsApp messages
 * This should be scheduled to run every 5-10 minutes
 * 
 * Usage: php cron/process_whatsapp_queue.php
 */

// Set working directory
chdir(dirname(__DIR__));

// Include required files
require_once 'includes/db.php';
require_once 'api/whatsapp/WhatsAppManager.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting WhatsApp queue processing...\n";

try {
    // Get all tenants with WhatsApp enabled
    $stmt = $pdo->query("
        SELECT DISTINCT tenant_id 
        FROM whatsapp_settings 
        WHERE status = 'active' 
        AND (auto_notifications = 1 OR real_time_notifications = 0)
    ");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($tenants)) {
        echo "No tenants with active WhatsApp settings found.\n";
        exit(0);
    }
    
    $total_processed = 0;
    $total_failed = 0;
    
    foreach ($tenants as $tenant) {
        $tenant_id = $tenant['tenant_id'];
        echo "Processing tenant ID: $tenant_id\n";
        
        try {
            // Initialize WhatsApp Manager for this tenant
            $whatsapp = new WhatsAppManager($tenant_id);
            
            // Process queue (limit to 20 messages per run to avoid timeouts)
            $reflection = new ReflectionClass($whatsapp);
            $processMethod = $reflection->getMethod('processQueue');
            $processMethod->setAccessible(true);
            $results = $processMethod->invoke($whatsapp, 20);
            
            $processed = count($results);
            $total_processed += $processed;
            
            echo "  Processed $processed messages for tenant $tenant_id\n";
            
            // Log failures
            foreach ($results as $result) {
                if (!$result['success']) {
                    $total_failed++;
                    error_log("WhatsApp message failed: " . ($result['error'] ?? 'Unknown error'));
                }
            }
            
        } catch (Exception $e) {
            echo "  Error processing tenant $tenant_id: " . $e->getMessage() . "\n";
            error_log("WhatsApp queue processing error for tenant $tenant_id: " . $e->getMessage());
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Processing complete.\n";
    echo "Total messages processed: $total_processed\n";
    echo "Total messages failed: $total_failed\n";
    
    // Clean up old failed messages (older than 30 days)
    $cleanupStmt = $pdo->prepare("
        DELETE FROM whatsapp_messages 
        WHERE status = 'failed' 
        AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $deleted = $cleanupStmt->execute() ? $cleanupStmt->rowCount() : 0;
    
    echo "Cleaned up $deleted old failed messages.\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Critical error: " . $e->getMessage() . "\n";
    error_log("WhatsApp queue processing critical error: " . $e->getMessage());
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] WhatsApp queue processing finished successfully.\n";
exit(0);
?>