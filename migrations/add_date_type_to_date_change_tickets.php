<?php
// Migration to add date_type column to date_change_tickets table
require_once __DIR__ . '/../includes/db.php';

try {
    // Check if column already exists
    $checkStmt = $pdo->query("SHOW COLUMNS FROM date_change_tickets LIKE 'date_type'");
    $columnExists = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$columnExists) {
        $pdo->exec("ALTER TABLE date_change_tickets ADD COLUMN date_type ENUM('departure', 'return', 'both') DEFAULT 'departure' AFTER return_date");
        echo "✓ Successfully added date_type column to date_change_tickets table\n";
    } else {
        echo "✓ date_type column already exists\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
