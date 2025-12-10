<?php
/**
 * Create message_reactions table if it doesn't exist
 */

require_once __DIR__ . '/includes/db.php';

try {
    // Check if table exists
    $checkStmt = $pdo->query("SHOW TABLES LIKE 'message_reactions'");
    $tableExists = $checkStmt && $checkStmt->rowCount() > 0;
    
    if ($tableExists) {
        echo "Table 'message_reactions' already exists.\n";
        exit;
    }
    
    // Create the table
    $sql = "
    CREATE TABLE IF NOT EXISTS message_reactions (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        message_id BIGINT NOT NULL,
        user_id BIGINT NOT NULL,
        emoji VARCHAR(10) NOT NULL,
        tenant_id BIGINT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_reaction (message_id, user_id, emoji),
        KEY idx_message_id (message_id),
        KEY idx_user_id (user_id),
        KEY idx_tenant_id (tenant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql);
    echo "Table 'message_reactions' created successfully.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
