<?php
/**
 * Message Encryption Migration Script
 * 
 * This script encrypts all existing plaintext messages in the chat_messages table.
 * Run this once after deploying the MessageEncryption class and database schema.
 * 
 * Usage: php migrate_to_encrypted.php [--tenant_id=N] [--batch_size=100]
 * 
 * @version 1.0
 * @date 2025-12-10
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/MessageEncryption.php';

// Parse command line arguments
$options = getopt('', ['tenant_id::', 'batch_size::', 'dry-run', 'verbose']);
$tenantId = isset($options['tenant_id']) ? (int)$options['tenant_id'] : 0;
$batchSize = isset($options['batch_size']) ? (int)$options['batch_size'] : 100;
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);

if (!function_exists('log_msg')) {
    function log_msg($msg, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[$timestamp] [$level] $msg\n";
    }
}

log_msg('Starting message encryption migration...');

if ($dryRun) {
    log_msg('DRY RUN MODE - No changes will be made', 'WARN');
}

try {
    $encryptor = new MessageEncryption($pdo);
    
    // Get tenants to process
    if ($tenantId > 0) {
        $tenants = [['id' => $tenantId]];
        log_msg("Processing only tenant ID: $tenantId");
    } else {
        $stmt = $pdo->prepare('SELECT id FROM tenants WHERE status != "deleted" ORDER BY id');
        $stmt->execute();
        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        log_msg('Processing all tenants: ' . count($tenants));
    }
    
    $totalProcessed = 0;
    $totalSkipped = 0;
    $totalFailed = 0;
    
    foreach ($tenants as $tenant) {
        $tId = (int)$tenant['id'];
        log_msg("Processing tenant ID: $tId");
        
        // Get count of unencrypted messages
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) as count FROM chat_messages WHERE tenant_id_from = ? AND (is_encrypted = 0 OR is_encrypted IS NULL)'
        );
        $countStmt->execute([$tId]);
        $result = $countStmt->fetch(PDO::FETCH_ASSOC);
        $totalMessages = (int)$result['count'];
        
        if ($totalMessages === 0) {
            log_msg("  No unencrypted messages found", 'INFO');
            continue;
        }
        
        log_msg("  Found $totalMessages unencrypted messages");
        
        // Process in batches
        $processed = 0;
        $offset = 0;
        
        while ($offset < $totalMessages) {
            $stmt = $pdo->prepare(
                'SELECT id, content FROM chat_messages 
                 WHERE tenant_id_from = ? AND (is_encrypted = 0 OR is_encrypted IS NULL) 
                 ORDER BY id ASC LIMIT ? OFFSET ?'
            );
            $stmt->execute([$tId, $batchSize, $offset]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($messages)) {
                break;
            }
            
            foreach ($messages as $msg) {
                $mId = (int)$msg['id'];
                $content = $msg['content'];
                
                try {
                    // Skip null or empty content
                    if (empty($content)) {
                        $totalSkipped++;
                        continue;
                    }
                    
                    if ($verbose) {
                        log_msg("  Encrypting message ID: $mId (length: " . strlen($content) . ')');
                    }
                    
                    // Encrypt the message
                    $encryptionData = $encryptor->encrypt($content, $tId);
                    
                    if (!$dryRun) {
                        // Update the message with encrypted content
                        $updateStmt = $pdo->prepare(
                            'UPDATE chat_messages 
                             SET encrypted_content = ?, encryption_key_id = ?, is_encrypted = 1, content = NULL
                             WHERE id = ?'
                        );
                        $updateStmt->execute([
                            $encryptionData['encrypted_content'],
                            $encryptionData['key_id'],
                            $mId
                        ]);
                    }
                    
                    $processed++;
                    $totalProcessed++;
                    
                    if ($processed % 10 === 0) {
                        log_msg("  Progress: $processed/$totalMessages encrypted");
                    }
                } catch (Exception $e) {
                    log_msg("  Failed to encrypt message ID $mId: " . $e->getMessage(), 'ERROR');
                    $totalFailed++;
                }
            }
            
            $offset += $batchSize;
        }
        
        log_msg("  Tenant complete: $processed encrypted, $totalSkipped skipped");
    }
    
    // Summary
    log_msg('============================================');
    log_msg('Migration Summary:', 'INFO');
    log_msg("  Total Processed: $totalProcessed");
    log_msg("  Total Skipped: $totalSkipped");
    log_msg("  Total Failed: $totalFailed");
    
    if ($dryRun) {
        log_msg('DRY RUN - No changes were made', 'WARN');
    } else {
        log_msg('Migration completed successfully!', 'INFO');
    }
    
} catch (Exception $e) {
    log_msg('Migration failed: ' . $e->getMessage(), 'FATAL');
    exit(1);
}

exit(0);
?>
