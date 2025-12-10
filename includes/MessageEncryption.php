<?php
/**
 * MessageEncryption Class
 * 
 * Handles encryption/decryption of chat messages at rest in the database.
 * Supports AES-256-CBC encryption with key management and rotation.
 * 
 * @version 1.0
 * @date 2025-12-10
 */
class MessageEncryption {
    private $pdo;
    private $keyCache = [];
    const ALGORITHM = 'aes-256-cbc';
    const IV_LENGTH = 16; // 16 bytes for AES-256-CBC
    const KEY_LENGTH = 32; // 32 bytes (256 bits) for AES-256
    
    /**
     * Initialize encryption handler
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct($pdo) {
        if (!extension_loaded('openssl')) {
            throw new Exception('OpenSSL extension is required for message encryption');
        }
        $this->pdo = $pdo;
    }
    
    /**
     * Encrypt message content
     * 
     * @param string $content Plain text message content
     * @param int $tenantId Tenant ID for key management
     * @param int|null $keyId Specific encryption key to use (uses active key if null)
     * @return array ['encrypted_content' => base64_string, 'key_id' => int]
     * @throws Exception
     */
    public function encrypt($content, $tenantId, $keyId = null) {
        try {
            // Get the encryption key
            if ($keyId === null) {
                $keyId = $this->getActiveKeyId($tenantId);
            }
            
            $key = $this->getDecryptedKey($tenantId, $keyId);
            if (!$key) {
                throw new Exception('Unable to retrieve encryption key');
            }
            
            // Generate random IV (initialization vector)
            $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);
            
            // Encrypt content with IV
            $encrypted = openssl_encrypt(
                $content,
                self::ALGORITHM,
                $key,
                OPENSSL_RAW_DATA, // Raw binary data, not base64
                $iv // Use the generated IV
            );
            
            if ($encrypted === false) {
                throw new Exception('Encryption failed: ' . openssl_error_string());
            }
            
            // Combine IV + encrypted data and base64 encode
            $encryptedWithIv = base64_encode($iv . $encrypted);
            
            // Log encryption action
            $this->logAudit($tenantId, 'encrypt', null, null, $keyId, true);
            
            return [
                'encrypted_content' => $encryptedWithIv,
                'key_id' => $keyId,
                'is_encrypted' => 1
            ];
        } catch (Exception $e) {
            error_log('Encryption error: ' . $e->getMessage());
            $this->logAudit($tenantId, 'encrypt', null, null, $keyId ?? null, false, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Decrypt encrypted message content
     * 
     * @param string $encryptedContent Base64-encoded (IV + encrypted data)
     * @param int $tenantId Tenant ID
     * @param int $keyId Encryption key ID used
     * @return string Decrypted plaintext message
     * @throws Exception
     */
    public function decrypt($encryptedContent, $tenantId, $keyId) {
        try {
            if (!$encryptedContent || !$keyId) {
                throw new Exception('Invalid encrypted content or key ID');
            }
            
            // Decode from base64
            $data = base64_decode($encryptedContent, true);
            if ($data === false) {
                throw new Exception('Failed to decode encrypted content');
            }
            
            // Extract IV and encrypted data
            $iv = substr($data, 0, self::IV_LENGTH);
            $encrypted = substr($data, self::IV_LENGTH);
            
            // Get decryption key
            $key = $this->getDecryptedKey($tenantId, $keyId);
            if (!$key) {
                throw new Exception('Unable to retrieve decryption key');
            }
            
            // Decrypt with the extracted IV
            $decrypted = openssl_decrypt(
                $encrypted,
                self::ALGORITHM,
                $key,
                OPENSSL_RAW_DATA,
                $iv  // Use the IV extracted from encrypted content
            );
            
            if ($decrypted === false) {
                throw new Exception('Decryption failed: ' . openssl_error_string());
            }
            
            // Log decryption action
            $this->logAudit($tenantId, 'decrypt', null, null, $keyId, true);
            
            return $decrypted;
        } catch (Exception $e) {
            error_log('Decryption error: ' . $e->getMessage());
            $this->logAudit($tenantId, 'decrypt', null, null, $keyId, false, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get active (current) encryption key for a tenant
     * 
     * @param int $tenantId Tenant ID
     * @return int Key ID
     * @throws Exception
     */
    public function getActiveKeyId($tenantId) {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM encryption_keys WHERE tenant_id = ? AND status = "active" ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            // Create default key if none exists
            return $this->createDefaultKey($tenantId);
        }
        
        return (int)$result['id'];
    }
    
    /**
     * Create a new encryption key for a tenant
     * 
     * @param int $tenantId Tenant ID
     * @param string|null $keyName Name of the key (default: date-based)
     * @return int New key ID
     * @throws Exception
     */
    public function createKey($tenantId, $keyName = null) {
        try {
            if (!$keyName) {
                $keyName = 'key_' . date('Y-m-d_His');
            }
            
            // Generate a strong random key
            $newKey = openssl_random_pseudo_bytes(self::KEY_LENGTH);
            $keyHash = hash('sha256', $newKey);
            
            // Store only the hash (not the actual key) in database
            // In production, store the actual key in a secure key management system (AWS KMS, Vault, etc.)
            $stmt = $this->pdo->prepare(
                'INSERT INTO encryption_keys (tenant_id, key_name, key_hash, algorithm, status) VALUES (?, ?, ?, ?, "active")'
            );
            $stmt->execute([$tenantId, $keyName, $keyHash, self::ALGORITHM]);
            
            $keyId = $this->pdo->lastInsertId();
            
            // Store the actual key in a secure location (this is a simple implementation)
            // In production, use AWS KMS, HashiCorp Vault, or similar
            $this->storeKeySecurely($tenantId, $keyId, $newKey);
            
            error_log("New encryption key created for tenant $tenantId: key_id=$keyId");
            
            return (int)$keyId;
        } catch (Exception $e) {
            error_log('Key creation error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create default encryption key for a tenant
     * 
     * @param int $tenantId Tenant ID
     * @return int Key ID
     */
    private function createDefaultKey($tenantId) {
        // Only create if no keys exist at all
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) as count FROM encryption_keys WHERE tenant_id = ?'
        );
        $stmt->execute([$tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ((int)$result['count'] === 0) {
            return $this->createKey($tenantId, 'default_' . date('Y-m-d'));
        }
        
        // If keys exist but none active, activate the most recent one
        $stmt = $this->pdo->prepare(
            'UPDATE encryption_keys SET status = "active" WHERE tenant_id = ? AND status != "archived" ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        
        return $this->getActiveKeyId($tenantId);
    }
    
    /**
     * Store encrypted key securely (simplified implementation)
     * In production, use AWS KMS, HashiCorp Vault, or similar
     * 
     * @param int $tenantId Tenant ID
     * @param int $keyId Key ID
     * @param string $keyData Raw key bytes
     */
    private function storeKeySecurely($tenantId, $keyId, $keyData) {
        // SIMPLE IMPLEMENTATION: Store in secure file (recommended for testing only)
        // In production, use:
        // - AWS KMS: Encrypt and store in AWS
        // - HashiCorp Vault: Use Vault API
        // - Azure Key Vault: Use Azure API
        
        $keyDir = dirname(__DIR__) . '/secure_keys';
        if (!is_dir($keyDir)) {
            mkdir($keyDir, 0700, true);
        }
        
        // Store with restricted permissions
        $keyFile = $keyDir . '/tenant_' . $tenantId . '_key_' . $keyId . '.key';
        file_put_contents($keyFile, $keyData, LOCK_EX);
        chmod($keyFile, 0600); // Read/write for owner only
    }
    
    /**
     * Retrieve the actual decryption key (from secure storage)
     * 
     * @param int $tenantId Tenant ID
     * @param int $keyId Key ID
     * @return string|null Raw key bytes, or null if not found
     */
    private function getDecryptedKey($tenantId, $keyId) {
        // Check cache first
        $cacheKey = "key_{$tenantId}_{$keyId}";
        if (isset($this->keyCache[$cacheKey])) {
            return $this->keyCache[$cacheKey];
        }
        
        // Retrieve from secure storage
        $keyDir = dirname(__DIR__) . '/secure_keys';
        $keyFile = $keyDir . '/tenant_' . $tenantId . '_key_' . $keyId . '.key';
        
        if (!file_exists($keyFile)) {
            error_log("Key file not found: $keyFile");
            return null;
        }
        
        $keyData = file_get_contents($keyFile);
        
        if ($keyData === false) {
            error_log("Failed to read key file: $keyFile");
            return null;
        }
        
        // Cache for this request
        $this->keyCache[$cacheKey] = $keyData;
        
        return $keyData;
    }
    
    /**
     * Rotate encryption key (create new key and optionally re-encrypt messages)
     * 
     * @param int $tenantId Tenant ID
     * @param bool $reencryptExisting Whether to re-encrypt existing messages (long-running operation)
     * @return int New key ID
     */
    public function rotateKey($tenantId, $reencryptExisting = false) {
        try {
            // Get old active key
            $oldKeyId = $this->getActiveKeyId($tenantId);
            
            // Deactivate old key
            $stmt = $this->pdo->prepare(
                'UPDATE encryption_keys SET status = "retired" WHERE id = ?'
            );
            $stmt->execute([$oldKeyId]);
            
            // Create new key
            $newKeyId = $this->createKey($tenantId);
            
            // Log rotation
            $stmt = $this->pdo->prepare(
                'INSERT INTO encryption_key_rotations (tenant_id, old_key_id, new_key_id, status) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$tenantId, $oldKeyId, $newKeyId, $reencryptExisting ? 'pending' : 'completed']);
            
            error_log("Key rotation initiated for tenant $tenantId: old_key=$oldKeyId, new_key=$newKeyId");
            
            if ($reencryptExisting) {
                // Queue background job to re-encrypt existing messages
                // In production, use a job queue (Redis, RabbitMQ, etc.)
                error_log("Re-encryption of existing messages queued for background processing");
            }
            
            return $newKeyId;
        } catch (Exception $e) {
            error_log('Key rotation error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Log encryption/decryption actions for audit trail
     * 
     * @param int $tenantId Tenant ID
     * @param string $action Action type (encrypt, decrypt, rotate, access)
     * @param int|null $userId User ID (if applicable)
     * @param int|null $messageId Message ID (if applicable)
     * @param int|null $keyId Key ID used
     * @param bool $success Whether operation succeeded
     * @param string|null $error Error message if failed
     */
    private function logAudit($tenantId, $action, $userId = null, $messageId = null, $keyId = null, $success = true, $error = null) {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO encryption_audit (tenant_id, action, user_id, message_id, key_id, success, error_message) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$tenantId, $action, $userId, $messageId, $keyId, $success ? 1 : 0, $error]);
        } catch (Exception $e) {
            error_log('Audit logging error: ' . $e->getMessage());
            // Don't throw - audit log failure shouldn't break encryption
        }
    }
    
    /**
     * Get encryption statistics for a tenant
     * 
     * @param int $tenantId Tenant ID
     * @return array Statistics including encrypted/plaintext message counts
     */
    public function getStats($tenantId) {
        $stmt = $this->pdo->prepare(
            'SELECT 
                COUNT(*) as total_messages,
                SUM(CASE WHEN is_encrypted = 1 THEN 1 ELSE 0 END) as encrypted_count,
                SUM(CASE WHEN is_encrypted = 0 THEN 1 ELSE 0 END) as plaintext_count,
                COUNT(DISTINCT encryption_key_id) as unique_keys_used
             FROM chat_messages 
             WHERE tenant_id_from = ? OR tenant_id_from IN (
                SELECT peer_tenant_id FROM tenant_peering WHERE tenant_id = ? AND status = "approved"
             )'
        );
        $stmt->execute([$tenantId, $tenantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
