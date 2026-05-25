<?php
/**
 * Migration: Add category column to suppliers table
 * 
 * Categories: ticket, visa, umrah, all
 * This allows specifying what services a supplier offers.
 */

require_once __DIR__ . '/../includes/db.php';

try {
    // Check if column already exists
    $check = $pdo->query("SHOW COLUMNS FROM suppliers LIKE 'category'");
    
    if ($check->rowCount() === 0) {
        // Add the category column with default value 'all'
        $pdo->exec("ALTER TABLE suppliers ADD COLUMN category VARCHAR(50) DEFAULT 'all' AFTER supplier_type");
        
        echo "✅ Column 'category' added to suppliers table successfully.\n";
    } else {
        echo "ℹ️ Column 'category' already exists in suppliers table. Skipping.\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}