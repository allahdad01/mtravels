<?php
/**
 * Migration: Add Trial Support to Tenants
 * 
 * Adds trial_days and trial_end_date columns to the tenants table,
 * and adds 'trial' to the tenant_subscriptions status enum.
 */

require_once __DIR__ . '/../includes/db.php';

echo "Starting migration: Add trial support to tenants...\n\n";

try {
    // 1. Add trial_days column to tenants table
    echo "Checking tenants table for trial_days column...\n";
    $stmt = $pdo->query("DESCRIBE tenants");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('trial_days', $columns)) {
        $pdo->exec("ALTER TABLE tenants ADD COLUMN trial_days INT(11) NOT NULL DEFAULT 0 AFTER plan");
        echo "✓ Added trial_days column to tenants table\n";
    } else {
        echo "✓ trial_days column already exists in tenants table\n";
    }

    if (!in_array('trial_end_date', $columns)) {
        $pdo->exec("ALTER TABLE tenants ADD COLUMN trial_end_date DATE NULL DEFAULT NULL AFTER trial_days");
        echo "✓ Added trial_end_date column to tenants table\n";
    } else {
        echo "✓ trial_end_date column already exists in tenants table\n";
    }

    // 2. Add 'trial' to tenant_subscriptions status enum
    echo "\nChecking tenant_subscriptions status enum...\n";
    $stmt = $pdo->query("DESCRIBE tenant_subscriptions");
    $sub_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sub_columns as $col) {
        if ($col['Field'] === 'status') {
            if (strpos($col['Type'], 'trial') === false) {
                $pdo->exec("ALTER TABLE tenant_subscriptions MODIFY COLUMN status ENUM('active','pending','trial','cancelled','expired') NOT NULL DEFAULT 'pending'");
                echo "✓ Added 'trial' to tenant_subscriptions.status enum\n";
            } else {
                echo "✓ 'trial' already exists in tenant_subscriptions.status enum\n";
            }
            break;
        }
    }

    echo "\nMigration completed successfully!\n";
    echo "- Added trial_days column (INT, default 0)\n";
    echo "- Added trial_end_date column (DATE, nullable)\n";
    echo "- Added 'trial' to tenant_subscriptions.status enum\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
