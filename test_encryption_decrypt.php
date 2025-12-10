<?php
/**
 * Test encryption and decryption round-trip
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/MessageEncryption.php';

echo "=== Encryption/Decryption Test ===\n\n";

try {
    $encryptor = new MessageEncryption($pdo);
    
    $testMessage = "Hello World - Test Message";
    $tenantId = 1;
    
    echo "1. Original message: $testMessage\n\n";
    
    // Encrypt
    echo "2. Encrypting...\n";
    $encrypted = $encryptor->encrypt($testMessage, $tenantId);
    echo "   ✅ Encrypted successfully\n";
    echo "   - Encrypted content length: " . strlen($encrypted['encrypted_content']) . "\n";
    echo "   - Key ID: " . $encrypted['key_id'] . "\n\n";
    
    // Decrypt
    echo "3. Decrypting...\n";
    $decrypted = $encryptor->decrypt($encrypted['encrypted_content'], $tenantId, $encrypted['key_id']);
    echo "   ✅ Decrypted successfully\n";
    echo "   - Decrypted message: $decrypted\n\n";
    
    // Verify
    echo "4. Verification:\n";
    if ($decrypted === $testMessage) {
        echo "   ✅ PERFECT! Original == Decrypted\n";
    } else {
        echo "   ❌ ERROR: Messages don't match!\n";
        echo "   Original:  '$testMessage'\n";
        echo "   Decrypted: '$decrypted'\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nFull trace:\n";
    echo $e->getTraceAsString();
}

echo "\n✅ Test complete!\n";
?>
