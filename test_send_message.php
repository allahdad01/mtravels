<?php
/**
 * Test script to verify message sending works
 * This simulates sending a message and checks if it's saved
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    die('Please log in first');
}

require_once __DIR__ . '/includes/db.php';

echo "=== Message Send Test ===\n\n";

$senderId = (int)$_SESSION['user_id'];
$recipientId = isset($_GET['to']) ? (int)$_GET['to'] : 19; // Default to user 19

echo "Sender ID: $senderId\n";
echo "Recipient ID: $recipientId\n\n";

// Get sender and recipient info
$senderStmt = $pdo->prepare("SELECT id, name, tenant_id FROM users WHERE id = ?");
$senderStmt->execute([$senderId]);
$sender = $senderStmt->fetch(PDO::FETCH_ASSOC);

$recipientStmt = $pdo->prepare("SELECT id, name, tenant_id FROM users WHERE id = ?");
$recipientStmt->execute([$recipientId]);
$recipient = $recipientStmt->fetch(PDO::FETCH_ASSOC);

if (!$sender || !$recipient) {
    die('Sender or recipient not found');
}

echo "Sender: {$sender['name']} (Tenant {$sender['tenant_id']})\n";
echo "Recipient: {$recipient['name']} (Tenant {$recipient['tenant_id']})\n\n";

// Create room ID
$ids = [$senderId, $recipientId];
sort($ids, SORT_NUMERIC);
$room = 'u-' . $ids[0] . '-' . $ids[1];

echo "Room: $room\n\n";

// Test 1: Send plaintext message
echo "1. Testing plaintext message send...\n";

$testMessage = "Test message at " . date('Y-m-d H:i:s');
$stmt = $pdo->prepare("INSERT INTO chat_messages (room_id, from_user_id, to_user_id, tenant_id_from, content) 
                       VALUES (?, ?, ?, ?, ?)");

try {
    $stmt->execute([$room, $senderId, $recipientId, $sender['tenant_id'], $testMessage]);
    $messageId = $pdo->lastInsertId();
    echo "✅ Message sent successfully! ID: $messageId\n";
    echo "   Content: $testMessage\n\n";
} catch (Exception $e) {
    echo "❌ Failed to send message: " . $e->getMessage() . "\n\n";
}

// Test 2: Verify message in database
echo "2. Checking if message was saved...\n";

$stmt = $pdo->prepare("SELECT id, from_user_id, to_user_id, content, is_encrypted, created_at 
                       FROM chat_messages 
                       WHERE room_id = ? AND from_user_id = ? 
                       ORDER BY id DESC LIMIT 1");
$stmt->execute([$room, $senderId]);
$lastMessage = $stmt->fetch(PDO::FETCH_ASSOC);

if ($lastMessage) {
    echo "✅ Message found in database!\n";
    echo "   ID: {$lastMessage['id']}\n";
    echo "   Content: {$lastMessage['content']}\n";
    echo "   Encrypted: " . ($lastMessage['is_encrypted'] ? 'YES' : 'NO') . "\n";
    echo "   Time: {$lastMessage['created_at']}\n\n";
} else {
    echo "❌ Message NOT found in database!\n\n";
}

// Test 3: Test encryption (if columns exist)
echo "3. Testing message encryption...\n";

$checkStmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                          WHERE TABLE_SCHEMA = DATABASE() 
                          AND TABLE_NAME = 'chat_messages' 
                          AND COLUMN_NAME = 'encrypted_content'");

if ($checkStmt->rowCount() > 0) {
    echo "   Encrypted_content column exists\n";
    
    try {
        require_once __DIR__ . '/includes/MessageEncryption.php';
        $encryptor = new MessageEncryption($pdo);
        
        $encryptedData = $encryptor->encrypt($testMessage, $sender['tenant_id']);
        echo "✅ Encryption successful\n";
        echo "   Encrypted content length: " . strlen($encryptedData['encrypted_content']) . " chars\n";
        echo "   Key ID: " . $encryptedData['key_id'] . "\n\n";
    } catch (Exception $e) {
        echo "❌ Encryption failed: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "   Encrypted_content column not found (plaintext mode only)\n\n";
}

// Test 4: Retrieve message as recipient would
echo "4. Testing message retrieval (as recipient)...\n";

$stmt = $pdo->prepare("SELECT id, from_user_id, to_user_id, content, encrypted_content, is_encrypted, encryption_key_id, tenant_id_from 
                       FROM chat_messages 
                       WHERE room_id = ? AND (from_user_id = ? OR to_user_id = ?)
                       ORDER BY id DESC 
                       LIMIT 5");
$stmt->execute([$room, $senderId, $senderId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($messages) . " messages in room:\n";
foreach ($messages as $msg) {
    $from = $msg['from_user_id'] == $senderId ? 'You' : 'Them';
    echo "   [$from] " . substr($msg['content'], 0, 40) . "...\n";
}

echo "\n✅ TEST COMPLETE!\n";
echo "\nNow go to /chat.php and try:\n";
echo "1. Send a message\n";
echo "2. Refresh the page\n";
echo "3. Message should persist\n";
?>
