-- Add encryption columns to chat_messages table
-- This migration adds support for end-to-end encryption of chat messages

-- Add encrypted_content column if it doesn't exist
SET @column_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_NAME = 'chat_messages'
  AND COLUMN_NAME = 'encrypted_content'
  AND TABLE_SCHEMA = DATABASE()
);

SET @sql = IF(@column_exists = 0,
  'ALTER TABLE `chat_messages` ADD COLUMN `encrypted_content` LONGTEXT NULL AFTER `content`',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add is_encrypted column if it doesn't exist
SET @column_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_NAME = 'chat_messages'
  AND COLUMN_NAME = 'is_encrypted'
  AND TABLE_SCHEMA = DATABASE()
);

SET @sql = IF(@column_exists = 0,
  'ALTER TABLE `chat_messages` ADD COLUMN `is_encrypted` TINYINT(1) DEFAULT 0 AFTER `encrypted_content`',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add encryption_key_id column if it doesn't exist
SET @column_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_NAME = 'chat_messages'
  AND COLUMN_NAME = 'encryption_key_id'
  AND TABLE_SCHEMA = DATABASE()
);

SET @sql = IF(@column_exists = 0,
  'ALTER TABLE `chat_messages` ADD COLUMN `encryption_key_id` INT(11) NULL AFTER `is_encrypted`',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify tenant_id_from exists (should already exist from earlier migrations)
SET @column_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_NAME = 'chat_messages'
  AND COLUMN_NAME = 'tenant_id_from'
  AND TABLE_SCHEMA = DATABASE()
);

-- If tenant_id_from doesn't exist, create it
SET @sql = IF(@column_exists = 0,
  'ALTER TABLE `chat_messages` ADD COLUMN `tenant_id_from` INT(11) NOT NULL DEFAULT 0 AFTER `to_user_id`',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes for encryption key lookup
SET @index_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_NAME = 'chat_messages'
  AND INDEX_NAME = 'idx_encryption_key'
  AND TABLE_SCHEMA = DATABASE()
);

SET @sql = IF(@index_exists = 0,
  'ALTER TABLE `chat_messages` ADD INDEX `idx_encryption_key` (`encryption_key_id`)',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for tenant_id_from lookup (for decryption context)
SET @index_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_NAME = 'chat_messages'
  AND INDEX_NAME = 'idx_tenant_from'
  AND TABLE_SCHEMA = DATABASE()
);

SET @sql = IF(@index_exists = 0,
  'ALTER TABLE `chat_messages` ADD INDEX `idx_tenant_from` (`tenant_id_from`)',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verification: Check the final structure
SELECT 'Column Structure:' as info;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'chat_messages'
AND TABLE_SCHEMA = DATABASE()
ORDER BY ORDINAL_POSITION;

SELECT 'Indexes:' as info;
SELECT INDEX_NAME, COLUMN_NAME
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_NAME = 'chat_messages'
AND TABLE_SCHEMA = DATABASE()
ORDER BY INDEX_NAME;
