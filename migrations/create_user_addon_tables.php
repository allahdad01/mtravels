<?php
/**
 * Database Migration: Create User Add-on Tables
 * 
 * This migration creates the necessary tables for managing user add-ons.
 * Run this file to create the tables in the database.
 */

require_once '../includes/db.php';

echo "Starting migration: Create User Add-on Tables\n";
echo str_repeat("-", 50) . "\n";

try {
    // 1. Create user_addons table
    echo "Creating user_addons table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_addons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            plan_id INT NOT NULL,
            base_users INT NOT NULL DEFAULT 0,
            additional_users INT NOT NULL DEFAULT 0,
            addon_price_per_user DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            currency VARCHAR(10) NOT NULL DEFAULT 'USD',
            total_addon_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            billing_cycle ENUM('monthly', 'quarterly', 'yearly') NOT NULL DEFAULT 'monthly',
            status ENUM('active', 'inactive', 'cancelled') NOT NULL DEFAULT 'active',
            next_renewal_date DATE NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant_id (tenant_id),
            INDEX idx_status (status),
            INDEX idx_tenant_status (tenant_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ user_addons table created successfully\n\n";

    // 2. Create user_addon_payments table
    echo "Creating user_addon_payments table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_addon_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            addon_id INT NOT NULL,
            tenant_id INT NOT NULL,
            amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            currency VARCHAR(10) NOT NULL DEFAULT 'USD',
            payment_method VARCHAR(50) NULL,
            transaction_id VARCHAR(255) NULL,
            status ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
            payment_date TIMESTAMP NULL,
            period_start DATE NULL,
            period_end DATE NULL,
            receipt_url VARCHAR(500) NULL,
            notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_addon_id (addon_id),
            INDEX idx_tenant_id (tenant_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ user_addon_payments table created successfully\n\n";

    // 3. Create user_addon_requests table
    echo "Creating user_addon_requests table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_addon_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            requested_additional_users INT NOT NULL DEFAULT 0,
            estimated_monthly_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            currency VARCHAR(10) NOT NULL DEFAULT 'USD',
            status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
            approval_notes TEXT NULL,
            approved_by INT NULL,
            approved_at TIMESTAMP NULL,
            rejection_reason TEXT NULL,
            rejected_by INT NULL,
            rejected_at TIMESTAMP NULL,
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant_id (tenant_id),
            INDEX idx_status (status),
            INDEX idx_tenant_status (tenant_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ user_addon_requests table created successfully\n\n";

    // 4. Update plans table if max_users column doesn't exist
    echo "Checking plans table for max_users column...\n";
    $stmt = $pdo->query("DESCRIBE plans");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('max_users', $columns)) {
        $pdo->exec("ALTER TABLE plans ADD COLUMN max_users INT(11) NOT NULL DEFAULT 5 AFTER price");
        echo "✓ Added max_users column to plans table\n";
    } else {
        echo "✓ max_users column already exists in plans table\n";
    }
    echo "\n";

    echo str_repeat("-", 50) . "\n";
    echo "✓ Migration completed successfully!\n";
    echo "\n";
    echo "Summary:\n";
    echo "- Created user_addons table (tracks active user subscriptions)\n";
    echo "- Created user_addon_payments table (tracks payments)\n";
    echo "- Created user_addon_requests table (tracks approval workflow)\n";
    echo "- Verified/Added max_users column in plans table\n";
    echo "\n";

} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
