<?php
/**
 * Migration: Create floating_tasks table
 * Run this file once to create the database table for floating tasks
 * 
 * Usage: Navigate to this file in browser or run via command line
 * Then delete or comment out the code
 */

require_once '../includes/db.php';

try {
    // Create floating_tasks table
    $sql = "
    CREATE TABLE IF NOT EXISTS `floating_tasks` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `tenant_id` INT NOT NULL DEFAULT 1,
        `task_text` VARCHAR(255) NOT NULL,
        `completed` BOOLEAN DEFAULT FALSE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        KEY `idx_user_tenant` (`user_id`, `tenant_id`),
        KEY `idx_created_at` (`created_at`),
        CONSTRAINT `fk_floating_tasks_user` FOREIGN KEY (`user_id`) 
            REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql);
    
    echo json_encode([
        'success' => true,
        'message' => 'floating_tasks table created successfully'
    ]);
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo json_encode([
            'success' => true,
            'message' => 'Table already exists'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>
