<?php
// Run maktobs migration
require_once('includes/db.php');

try {
    // Alter maktobs status enum
    $pdo->exec("ALTER TABLE `maktobs` MODIFY COLUMN `status` enum('draft','sent','archived') NOT NULL DEFAULT 'draft'");

    // Create maktob_logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `maktob_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `tenant_id` int(11) NOT NULL,
      `maktob_id` int(11) NOT NULL,
      `user_id` int(11) NOT NULL,
      `action` enum('create','edit','delete','send','archive') NOT NULL,
      `old_values` text DEFAULT NULL,
      `new_values` text DEFAULT NULL,
      `ip_address` varchar(45) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `branch_id` bigint(20) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `maktob_id` (`maktob_id`),
      KEY `user_id` (`user_id`),
      KEY `tenant_id` (`tenant_id`),
      KEY `action` (`action`),
      CONSTRAINT `fk_maktob_logs_maktob` FOREIGN KEY (`maktob_id`) REFERENCES `maktobs` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_maktob_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_maktob_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "Migration completed successfully!";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage();
}
?>