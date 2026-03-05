<?php
// Migration to add return route columns to date_change_tickets table
require_once __DIR__ . '/../includes/db.php';

try {
    // Check if columns already exist
    $checkStmt = $pdo->query("SHOW COLUMNS FROM date_change_tickets LIKE 'return_origin'");
    $columnExists = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$columnExists) {
        $pdo->exec("ALTER TABLE date_change_tickets ADD COLUMN return_origin VARCHAR(255) NULL AFTER destination");
        $pdo->exec("ALTER TABLE date_change_tickets ADD COLUMN return_destination VARCHAR(255) NULL AFTER return_origin");
        echo "✓ Successfully added return_origin and return_destination columns to date_change_tickets table\n";
    } else {
        echo "✓ Return route columns already exist\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
