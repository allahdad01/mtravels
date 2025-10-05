-- HR Management Migration: Employee Terminations Table
-- This migration adds proper HR termination tracking separate from user management

CREATE TABLE IF NOT EXISTS `employee_terminations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `terminated_by` int(11) NOT NULL,
  `termination_reason` text NOT NULL,
  `termination_date` datetime NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `terminated_by` (`terminated_by`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `fk_employee_terminations_employee` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_terminations_terminator` FOREIGN KEY (`terminated_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_employee_terminations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for better performance
ALTER TABLE `employee_terminations` ADD INDEX `idx_termination_date` (`termination_date`);

-- Insert sample data if needed (uncomment if you want to add sample terminations)
-- INSERT INTO `employee_terminations` (`employee_id`, `terminated_by`, `termination_reason`, `termination_date`, `tenant_id`)
-- SELECT u.id, 1, 'Migrated from user management system', u.fired_at, u.tenant_id
-- FROM users u
-- WHERE u.fired = 1 AND u.fired_at IS NOT NULL;