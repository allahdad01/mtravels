-- Simple migration: Add encryption columns to chat_messages
-- Run this directly in phpMyAdmin or MySQL command line

-- Add encrypted_content column
ALTER TABLE `chat_messages` 
ADD COLUMN `encrypted_content` LONGTEXT NULL AFTER `content`;

-- Add is_encrypted column
ALTER TABLE `chat_messages` 
ADD COLUMN `is_encrypted` TINYINT(1) DEFAULT 0 AFTER `encrypted_content`;

-- Add encryption_key_id column
ALTER TABLE `chat_messages` 
ADD COLUMN `encryption_key_id` INT(11) NULL AFTER `is_encrypted`;

-- Verify tenant_id_from exists (if not, add it)
ALTER TABLE `chat_messages` 
ADD COLUMN `tenant_id_from` INT(11) NOT NULL DEFAULT 0 AFTER `to_user_id`;

-- Create indexes for better performance
CREATE INDEX `idx_encryption_key` ON `chat_messages` (`encryption_key_id`);
CREATE INDEX `idx_tenant_from` ON `chat_messages` (`tenant_id_from`);
CREATE INDEX `idx_is_encrypted` ON `chat_messages` (`is_encrypted`);

-- Verify the result
DESCRIBE `chat_messages`;
