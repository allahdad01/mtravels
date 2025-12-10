-- Phase 4: Audit Logging Migration
-- Creates comprehensive audit trail for all chat operations

CREATE TABLE IF NOT EXISTS `chat_audit_log` (
  `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `branch_id` INT,
  `user_id` INT NOT NULL,
  `action` VARCHAR(50) NOT NULL COMMENT 'send_message, read_message, block_user, unblock_user, mute_user, unmute_user, encrypt_message, decrypt_message, settings_change, access_denied, etc.',
  `target_user_id` INT COMMENT 'The user affected by the action',
  `message_id` BIGINT COMMENT 'Reference to chat_messages table',
  `room_id` VARCHAR(50) COMMENT 'Chat room identifier',
  `details` JSON COMMENT 'Additional context like message_size, encrypted, key_id, etc.',
  `ip_address` VARCHAR(45) COMMENT 'Client IP address (IPv4 or IPv6)',
  `user_agent` VARCHAR(255) COMMENT 'User-Agent header',
  `status` VARCHAR(20) COMMENT 'success, failed, denied, error',
  `error_message` TEXT COMMENT 'Error details if action failed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  -- Indexes for efficient querying
  INDEX `idx_tenant_action` (`tenant_id`, `action`, `created_at`),
  INDEX `idx_user_time` (`user_id`, `created_at`),
  INDEX `idx_target_user` (`target_user_id`, `created_at`),
  INDEX `idx_message_id` (`message_id`),
  INDEX `idx_status` (`status`, `created_at`),
  INDEX `idx_action_time` (`action`, `created_at`),
  
  -- Foreign keys
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Complete audit trail for chat operations for compliance and security tracking';

-- Create archive table for old logs
CREATE TABLE IF NOT EXISTS `chat_audit_log_archive` (
  `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `branch_id` INT,
  `user_id` INT NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `target_user_id` INT,
  `message_id` BIGINT,
  `room_id` VARCHAR(50),
  `details` JSON,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(255),
  `status` VARCHAR(20),
  `error_message` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `archived_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX `idx_tenant_time` (`tenant_id`, `created_at`),
  INDEX `idx_user_time` (`user_id`, `created_at`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Archive table for audit logs older than 90 days';
