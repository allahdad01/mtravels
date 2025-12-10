-- Migration: Create branch-level chat settings table
-- Date: 2025-12-10
-- Purpose: Move chat settings from tenant-wide to branch-specific

-- Step 1: Create new branch_chat_settings table
CREATE TABLE IF NOT EXISTS `branch_chat_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `chat_max_file_bytes` bigint(20) NOT NULL DEFAULT 26214400,
  `chat_allowed_mime_prefixes` text NOT NULL DEFAULT 'image/,video/,audio/,application/pdf,text/',
  `chat_default_auto_download` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_branch_settings` (`tenant_id`, `branch_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_branch` (`branch_id`),
  CONSTRAINT `fk_bcs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bcs_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Migrate existing tenant settings to all branches
INSERT IGNORE INTO `branch_chat_settings` (tenant_id, branch_id, chat_max_file_bytes, chat_allowed_mime_prefixes, chat_default_auto_download)
SELECT t.id, b.id, 
  COALESCE(t.chat_max_file_bytes, 26214400),
  COALESCE(t.chat_allowed_mime_prefixes, 'image/,video/,audio/,application/pdf,text/'),
  COALESCE(t.chat_default_auto_download, 0)
FROM tenants t
CROSS JOIN branches b
WHERE t.id = b.tenant_id
ON DUPLICATE KEY UPDATE
  chat_max_file_bytes = VALUES(chat_max_file_bytes),
  chat_allowed_mime_prefixes = VALUES(chat_allowed_mime_prefixes),
  chat_default_auto_download = VALUES(chat_default_auto_download);

-- Step 3: Add indexes to chat_messages for better performance
ALTER TABLE `chat_messages` 
  ADD INDEX `idx_tenant_from_user` (`tenant_id_from`, `from_user_id`),
  ADD INDEX `idx_to_user_seen` (`to_user_id`, `seen_at`),
  ADD INDEX `idx_created_at` (`created_at`);

-- Step 4: Enhance tenant_peering with branch awareness (optional, see Step 5)
-- Note: Current table allows only one peering per tenant pair
-- If you want branch-specific peering, uncomment Step 5

-- Step 5: Create branch_peering table (optional, for true branch-level peering)
-- Uncomment if you want branches to have independent peering relationships
/*
CREATE TABLE IF NOT EXISTS `branch_peering` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `peer_tenant_id` int(11) NOT NULL,
  `peer_branch_id` int(11) DEFAULT NULL,
  `status` enum('approved','pending','blocked') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `branch_peer_unique` (`branch_id`, `peer_branch_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_peer_tenant` (`peer_tenant_id`),
  CONSTRAINT `fk_bp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bp_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bp_peer_tenant` FOREIGN KEY (`peer_tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bp_peer_branch` FOREIGN KEY (`peer_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/
