<?php
// Migration to add return_date column to date_change_tickets table
require_once __DIR__ . '/../includes/db.php';

try {
    // Check if column already exists
    $checkStmt = $pdo->query("SHOW COLUMNS FROM date_change_tickets LIKE 'return_date'");
    $columnExists = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$columnExists) {
        $pdo->exec("ALTER TABLE date_change_tickets ADD COLUMN return_date DATE NULL AFTER departure_date");
        echo "✓ Successfully added return_date column to date_change_tickets table\n";
    } else {
        echo "✓ return_date column already exists\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
