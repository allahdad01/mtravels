<?php
/**
 * Auto-apply encryption migration to chat_messages table
 * This script adds the required columns for message encryption
 */

require_once __DIR__ . '/includes/db.php';

echo "=== Chat Encryption Migration ===\n\n";

try {
    // List of columns to add
    $columnsToAdd = [
        'encrypted_content' => 'LONGTEXT NULL AFTER `content`',
        'is_encrypted' => 'TINYINT(1) DEFAULT 0 AFTER `encrypted_content`',
        'encryption_key_id' => 'INT(11) NULL AFTER `is_encrypted`'
    ];
    
    // Verify tenant_id_from exists
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                          WHERE TABLE_SCHEMA = DATABASE() 
                          AND TABLE_NAME = 'chat_messages' 
                          AND COLUMN_NAME = 'tenant_id_from'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        echo "⚠️  tenant_id_from column missing! Adding it...\n";
        $pdo->exec("ALTER TABLE `chat_messages` ADD COLUMN `tenant_id_from` INT(11) NOT NULL DEFAULT 0 AFTER `to_user_id`");
        echo "✅ Added tenant_id_from column\n\n";
    } else {
        echo "✅ tenant_id_from column exists\n\n";
    }
    
    // Check and add encryption columns
    foreach ($columnsToAdd as $columnName => $definition) {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                              WHERE TABLE_SCHEMA = DATABASE() 
                              AND TABLE_NAME = 'chat_messages' 
                              AND COLUMN_NAME = ?");
        $stmt->execute([$columnName]);
        
        if ($stmt->rowCount() == 0) {
            echo "⚠️  $columnName column missing! Adding it...\n";
            $sql = "ALTER TABLE `chat_messages` ADD COLUMN `$columnName` $definition";
            $pdo->exec($sql);
            echo "✅ Added $columnName column\n";
        } else {
            echo "✅ $columnName column already exists\n";
        }
    }
    
    echo "\n=== Adding Indexes ===\n\n";
    
    // Add indexes
    $indexesToAdd = [
        'idx_encryption_key' => 'ADD INDEX `idx_encryption_key` (`encryption_key_id`)',
        'idx_tenant_from' => 'ADD INDEX `idx_tenant_from` (`tenant_id_from`)',
        'idx_is_encrypted' => 'ADD INDEX `idx_is_encrypted` (`is_encrypted`)'
    ];
    
    foreach ($indexesToAdd as $indexName => $definition) {
        $stmt = $pdo->prepare("SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS 
                              WHERE TABLE_SCHEMA = DATABASE() 
                              AND TABLE_NAME = 'chat_messages' 
                              AND INDEX_NAME = ?");
        $stmt->execute([$indexName]);
        
        if ($stmt->rowCount() == 0) {
            echo "⚠️  $indexName index missing! Adding it...\n";
            try {
                $pdo->exec("ALTER TABLE `chat_messages` $definition");
                echo "✅ Added $indexName index\n";
            } catch (Exception $e) {
                echo "⚠️  Could not add index: " . $e->getMessage() . "\n";
            }
        } else {
            echo "✅ $indexName index already exists\n";
        }
    }
    
    echo "\n=== Final Verification ===\n\n";
    
    // Verify the structure
    $stmt = $pdo->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
                        FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = 'chat_messages'
                        ORDER BY ORDINAL_POSITION");
    
    echo "Chat Messages Table Columns:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $nullable = $row['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $row['COLUMN_DEFAULT'] ? " DEFAULT {$row['COLUMN_DEFAULT']}" : '';
        printf("  %-25s %s %s%s\n", $row['COLUMN_NAME'], $row['COLUMN_TYPE'], $nullable, $default);
    }
    
    echo "\n✅ Migration complete! Messages can now be encrypted and decrypted properly.\n";
    echo "\n📝 Next steps:\n";
    echo "1. Send a test message between two users\n";
    echo "2. Refresh the page\n";
    echo "3. The message should now persist and display correctly\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "\nDebug info:\n";
    echo var_dump($e);
}
?>
