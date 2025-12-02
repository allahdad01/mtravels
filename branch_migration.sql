-- Multi-branch support migration for travel agency SaaS
-- Generated on: 2025-11-24

-- Create branches table
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_branch_code` (`tenant_id`,`code`),
  KEY `tenant_id` (`tenant_id`),
  KEY `manager_id` (`manager_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_branches_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_branches_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_branches_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add branch_id column to users table
ALTER TABLE `users` ADD COLUMN `branch_id` int(11) DEFAULT NULL AFTER `tenant_id`;
ALTER TABLE `users` ADD CONSTRAINT `fk_users_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

-- Create index for better performance
ALTER TABLE `users` ADD KEY `branch_id` (`branch_id`);

-- Add branch auditing to activity_log table (extend existing table)
-- The activity_log table already exists and tracks tenant-level activities
-- We'll extend it to also track branch-level activities

-- Update existing activity_log table to include branch_id
ALTER TABLE `activity_log` ADD COLUMN `branch_id` int(11) DEFAULT NULL AFTER `tenant_id`;
ALTER TABLE `activity_log` ADD CONSTRAINT `fk_activity_log_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE;
ALTER TABLE `activity_log` ADD KEY `branch_id` (`branch_id`);

-- Insert sample data for testing (optional - remove in production)
-- This will create a default branch for each existing tenant
INSERT INTO `branches` (`tenant_id`, `name`, `code`, `status`, `created_by`, `created_at`, `updated_at`)
SELECT
  t.id as tenant_id,
  CONCAT(t.name, ' - Main Branch') as name,
  CONCAT('MAIN-', t.id) as code,
  'active' as status,
  (SELECT u.id FROM users u WHERE u.tenant_id = t.id AND u.role = 'admin' LIMIT 1) as created_by,
  NOW() as created_at,
  NOW() as updated_at
FROM `tenants` t
WHERE t.status = 'active';

-- Update existing users to be assigned to their tenant's main branch
UPDATE `users` u
INNER JOIN `branches` b ON b.tenant_id = u.tenant_id AND b.code LIKE 'MAIN-%'
SET u.branch_id = b.id
WHERE u.tenant_id IS NOT NULL AND u.role IN ('admin', 'sales', 'finance', 'umrah', 'visa');

-- Create branch_audit_log table for detailed branch-specific auditing
CREATE TABLE `branch_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `branch_audit_log_branch_id_index` (`branch_id`),
  KEY `branch_audit_log_user_id_index` (`user_id`),
  KEY `branch_audit_log_table_name_index` (`table_name`),
  KEY `branch_audit_log_action_index` (`action`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_branch_audit_log_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_branch_audit_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;