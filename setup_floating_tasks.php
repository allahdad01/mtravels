<?php
/**
 * Setup Floating Tasks Database
 * 
 * Visit this file in your browser to create the floating_tasks table
 * After successful creation, delete this file or comment it out
 * 
 * URL: http://localhost/mtravels/setup_floating_tasks.php
 */

// Security check - only allow access from localhost or admin
$allowed_ips = ['127.0.0.1', 'localhost', '::1'];
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

// Simple check - in production, add more security
if (!in_array($client_ip, $allowed_ips) && $_GET['key'] !== 'setup_key_12345') {
    die('Access denied');
}

header('Content-Type: application/json');

require_once 'includes/db.php';

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
        'message' => 'floating_tasks table created successfully',
        'next_step' => 'Delete this setup file and start using floating tasks widget'
    ]);
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo json_encode([
            'success' => true,
            'message' => 'Table already exists',
            'next_step' => 'Delete this setup file and start using floating tasks widget'
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
