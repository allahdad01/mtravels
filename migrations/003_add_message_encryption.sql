-- Migration: Add message encryption support
-- Date: 2025-12-10
-- Purpose: Enable encrypted storage of chat messages
-- Status: Phase 3 Implementation

-- Step 1: Create encryption_keys table for key management
CREATE TABLE IF NOT EXISTS `encryption_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `key_name` varchar(100) NOT NULL,
  `key_hash` varchar(255) NOT NULL COMMENT 'SHA256 hash of the actual key',
  `algorithm` varchar(50) DEFAULT 'aes-256-cbc',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `rotated_at` timestamp NULL,
  `status` enum('active','retired','archived') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_key_name` (`tenant_id`, `key_name`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_ek_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Add encryption fields to chat_messages table
ALTER TABLE `chat_messages` 
  ADD COLUMN `encrypted_content` longblob COMMENT 'Encrypted message content' AFTER `content`,
  ADD COLUMN `encryption_key_id` int(11) COMMENT 'Reference to encryption_keys table' AFTER `encrypted_content`,
  ADD COLUMN `is_encrypted` tinyint(1) DEFAULT 0 COMMENT 'Flag: 1 = encrypted, 0 = plaintext' AFTER `encryption_key_id`;

-- Step 3: Add foreign key for encryption_key_id
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `fk_cm_encryption_key` FOREIGN KEY (`encryption_key_id`) REFERENCES `encryption_keys` (`id`) ON DELETE SET NULL;

-- Step 4: Add indexes for encrypted queries
ALTER TABLE `chat_messages`
  ADD KEY `idx_is_encrypted` (`is_encrypted`),
  ADD KEY `idx_encryption_key` (`encryption_key_id`);

-- Step 5: Create key rotation log table
CREATE TABLE IF NOT EXISTS `encryption_key_rotations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `old_key_id` int(11),
  `new_key_id` int(11) NOT NULL,
  `messages_rotated` int(11) DEFAULT 0 COMMENT 'Number of messages re-encrypted',
  `status` enum('pending','in_progress','completed','failed') DEFAULT 'pending',
  `error_message` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_ekr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 6: Create encryption audit trail table
CREATE TABLE IF NOT EXISTS `encryption_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL COMMENT 'encrypt, decrypt, rotate, access',
  `user_id` int(11),
  `message_id` int(11),
  `key_id` int(11),
  `success` tinyint(1) DEFAULT 1,
  `error_message` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_ea_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ea_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
