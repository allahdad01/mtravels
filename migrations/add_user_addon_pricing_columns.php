<?php
/**
 * Database Migration: Add User Addon Pricing Columns
 * 
 * This migration adds dedicated columns for user addon pricing in the settings table.
 * Run this file to add the columns to the database.
 */

require_once __DIR__ . '/../includes/db.php';

echo "Starting migration: Add User Addon Pricing Columns\n";
echo str_repeat("-", 50) . "\n";

try {
    // Check if columns already exist
    $stmt = $pdo->query("DESCRIBE settings");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Add user_addon_monthly_price column
    if (!in_array('user_addon_monthly_price', $columns)) {
        $pdo->exec("ALTER TABLE settings ADD COLUMN user_addon_monthly_price DECIMAL(10, 2) DEFAULT 25.00");
        echo "✓ Added user_addon_monthly_price column\n";
    } else {
        echo "✓ user_addon_monthly_price column already exists\n";
    }
    
    // Re-fetch columns after adding first column
    $stmt = $pdo->query("DESCRIBE settings");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Add user_addon_quarterly_price column
    if (!in_array('user_addon_quarterly_price', $columns)) {
        $pdo->exec("ALTER TABLE settings ADD COLUMN user_addon_quarterly_price DECIMAL(10, 2) DEFAULT 75.00");
        echo "✓ Added user_addon_quarterly_price column\n";
    } else {
        echo "✓ user_addon_quarterly_price column already exists\n";
    }
    
    // Re-fetch columns after adding second column
    $stmt = $pdo->query("DESCRIBE settings");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Add user_addon_yearly_price column
    if (!in_array('user_addon_yearly_price', $columns)) {
        $pdo->exec("ALTER TABLE settings ADD COLUMN user_addon_yearly_price DECIMAL(10, 2) DEFAULT 300.00");
        echo "✓ Added user_addon_yearly_price column\n";
    } else {
        echo "✓ user_addon_yearly_price column already exists\n";
    }
    
    echo "\n" . str_repeat("-", 50) . "\n";
    echo "✓ Migration completed successfully!\n";
    echo "\n";
    echo "Summary:\n";
    echo "- Added user_addon_monthly_price column (default: $25.00)\n";
    echo "- Added user_addon_quarterly_price column (default: $75.00)\n";
    echo "- Added user_addon_yearly_price column (default: $300.00)\n";
    echo "\n";
    echo "These columns are used by the UserAddonManager to get per-tenant\n";
    echo "pricing for additional user add-ons.\n";
    echo "\n";

} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
