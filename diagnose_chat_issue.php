<?php
/**
 * Diagnostic script to check chat system status
 */

require_once __DIR__ . '/includes/db.php';

echo "=== Chat System Diagnostic Report ===\n\n";

// 1. Check chat_messages table structure
echo "1️⃣  TABLE STRUCTURE CHECK:\n";
echo "   Looking for required columns in chat_messages...\n\n";

$requiredColumns = [
    'id' => 'bigint',
    'room_id' => 'varchar',
    'from_user_id' => 'int',
    'to_user_id' => 'int',
    'tenant_id_from' => 'int',
    'content' => 'text',
    'encrypted_content' => 'longtext',
    'is_encrypted' => 'tinyint',
    'encryption_key_id' => 'int'
];

$stmt = $pdo->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'chat_messages'
                    ORDER BY ORDINAL_POSITION");

$existingColumns = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existingColumns[$row['COLUMN_NAME']] = $row['COLUMN_TYPE'];
    echo "   ✅ {$row['COLUMN_NAME']} ({$row['COLUMN_TYPE']})\n";
}

echo "\n   MISSING COLUMNS:\n";
$missingColumns = [];
foreach ($requiredColumns as $col => $type) {
    if (!isset($existingColumns[$col])) {
        echo "   ❌ $col ($type)\n";
        $missingColumns[] = $col;
    }
}

if (empty($missingColumns)) {
    echo "   ✅ All required columns exist!\n";
} else {
    echo "\n   ⚠️  Missing " . count($missingColumns) . " column(s)\n";
    echo "   You MUST run the migration to add these columns.\n";
}

// 2. Check recent messages in database
echo "\n\n2️⃣  RECENT MESSAGES IN DATABASE:\n";

$stmt = $pdo->query("SELECT id, from_user_id, to_user_id, room_id, 
                           SUBSTRING(content, 1, 50) as content_preview,
                           is_encrypted, created_at
                    FROM chat_messages 
                    ORDER BY id DESC 
                    LIMIT 10");

$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($messages)) {
    echo "   No messages found in database\n";
} else {
    echo "   Found " . count($messages) . " messages:\n\n";
    foreach ($messages as $msg) {
        echo "   ID: {$msg['id']}\n";
        echo "   From: {$msg['from_user_id']} → To: {$msg['to_user_id']}\n";
        echo "   Content: {$msg['content_preview']}...\n";
        echo "   Encrypted: " . ($msg['is_encrypted'] ? 'YES' : 'NO') . "\n";
        echo "   Created: {$msg['created_at']}\n";
        echo "   ---\n";
    }
}

// 3. Check for errors in PHP error log
echo "\n\n3️⃣  ERROR LOG CHECK:\n";

$errorLogPath = ini_get('error_log');
if ($errorLogPath && file_exists($errorLogPath)) {
    echo "   Error log location: $errorLogPath\n";
    echo "   Last 10 entries:\n\n";
    
    $lines = array_slice(file($errorLogPath), -10);
    foreach ($lines as $line) {
        echo "   " . trim($line) . "\n";
    }
} else {
    echo "   Error log not configured or not found\n";
}

// 4. Test message encryption class
echo "\n\n4️⃣  ENCRYPTION CLASS TEST:\n";

try {
    require_once __DIR__ . '/includes/MessageEncryption.php';
    $encryptor = new MessageEncryption($pdo);
    echo "   ✅ MessageEncryption class loaded successfully\n";
    
    // Try to encrypt a test message
    $testContent = "Hello, this is a test message";
    $testTenantId = 1;
    
    try {
        $encrypted = $encryptor->encrypt($testContent, $testTenantId);
        echo "   ✅ Encryption works\n";
        echo "   - Encrypted content length: " . strlen($encrypted['encrypted_content']) . " chars\n";
        echo "   - Key ID: " . $encrypted['key_id'] . "\n";
    } catch (Exception $e) {
        echo "   ❌ Encryption failed: " . $e->getMessage() . "\n";
    }
} catch (Exception $e) {
    echo "   ❌ Could not load MessageEncryption class: " . $e->getMessage() . "\n";
}

// 5. Database connection test
echo "\n\n5️⃣  DATABASE CONNECTION TEST:\n";

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM chat_messages");
    $result = $stmt->fetch();
    echo "   ✅ Database connected\n";
    echo "   - Total messages: " . $result['count'] . "\n";
} catch (Exception $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
}

// 6. Recommendations
echo "\n\n6️⃣  RECOMMENDATIONS:\n\n";

if (!empty($missingColumns)) {
    echo "   🔴 CRITICAL: Run this SQL to add missing columns:\n\n";
    echo "   ALTER TABLE `chat_messages` \n";
    echo "   ADD COLUMN `encrypted_content` LONGTEXT NULL AFTER `content`,\n";
    echo "   ADD COLUMN `is_encrypted` TINYINT(1) DEFAULT 0 AFTER `encrypted_content`,\n";
    echo "   ADD COLUMN `encryption_key_id` INT(11) NULL AFTER `is_encrypted`;\n\n";
    echo "   OR run: migrations/simple_encryption_fix.sql\n";
    echo "   OR visit: /apply_encryption_migration.php\n";
} else {
    echo "   ✅ All columns exist - try sending a message\n";
    echo "   📝 Check if the message appears in the database\n";
    echo "   🔍 Check browser console for errors\n";
}

echo "\n=== End of Diagnostic Report ===\n";
?>
