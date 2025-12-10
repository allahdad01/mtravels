<?php
/**
 * Add encryption columns to chat_messages table if they don't exist
 */
require_once __DIR__ . '/includes/db.php';

$alterStatements = [];

// Check if columns exist and add them if needed
try {
    // Check encrypted_content column
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chat_messages' AND COLUMN_NAME = 'encrypted_content'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $alterStatements[] = "ALTER TABLE `chat_messages` ADD COLUMN `encrypted_content` LONGTEXT NULL AFTER `content`";
        echo "✓ Adding encrypted_content column\n";
    } else {
        echo "✓ encrypted_content column already exists\n";
    }
    
    // Check is_encrypted column
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chat_messages' AND COLUMN_NAME = 'is_encrypted'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $alterStatements[] = "ALTER TABLE `chat_messages` ADD COLUMN `is_encrypted` TINYINT(1) DEFAULT 0 AFTER `encrypted_content`";
        echo "✓ Adding is_encrypted column\n";
    } else {
        echo "✓ is_encrypted column already exists\n";
    }
    
    // Check encryption_key_id column
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chat_messages' AND COLUMN_NAME = 'encryption_key_id'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $alterStatements[] = "ALTER TABLE `chat_messages` ADD COLUMN `encryption_key_id` INT(11) NULL AFTER `is_encrypted`";
        echo "✓ Adding encryption_key_id column\n";
    } else {
        echo "✓ encryption_key_id column already exists\n";
    }
    
    // Check tenant_id_from column
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chat_messages' AND COLUMN_NAME = 'tenant_id_from'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $alterStatements[] = "ALTER TABLE `chat_messages` ADD COLUMN `tenant_id_from` INT(11) NOT NULL DEFAULT 0 AFTER `to_user_id`";
        echo "✓ Adding tenant_id_from column\n";
    } else {
        echo "✓ tenant_id_from column already exists\n";
    }
    
    // Execute all ALTER statements
    foreach ($alterStatements as $sql) {
        try {
            $pdo->exec($sql);
            echo "✅ Executed: " . substr($sql, 0, 50) . "...\n";
        } catch (Exception $e) {
            echo "⚠️  Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✅ Database schema update complete!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
