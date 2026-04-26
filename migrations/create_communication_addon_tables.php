<?php
/**
 * Migration: create communication add-on tables and pricing columns.
 *
 * Usage:
 *   php migrations/create_communication_addon_tables.php
 */
require_once __DIR__ . '/../includes/db.php';

echo "Creating communication_addons table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS communication_addons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL,
        addon_type ENUM('whatsapp','smtp') NOT NULL,
        addon_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        billing_cycle ENUM('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
        currency VARCHAR(10) NOT NULL DEFAULT 'USD',
        status ENUM('active','inactive','cancelled') NOT NULL DEFAULT 'active',
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tenant_type_status (tenant_id, addon_type, status),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "✓ communication_addons table ready\n";

echo "Creating communication_addon_requests table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS communication_addon_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL,
        addon_type ENUM('whatsapp','smtp') NOT NULL,
        billing_cycle ENUM('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
        estimated_monthly_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        currency VARCHAR(10) NOT NULL DEFAULT 'USD',
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        approved_by INT NULL,
        approved_at TIMESTAMP NULL,
        approval_notes TEXT NULL,
        rejected_by INT NULL,
        rejected_at TIMESTAMP NULL,
        rejection_reason TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tenant_type_status (tenant_id, addon_type, status),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "✓ communication_addon_requests table ready\n";

echo "Ensuring pricing columns on settings table...\n";
$columns = [
    "ALTER TABLE settings ADD COLUMN whatsapp_addon_monthly_price DECIMAL(10,2) NULL AFTER user_addon_yearly_price",
    "ALTER TABLE settings ADD COLUMN whatsapp_addon_quarterly_price DECIMAL(10,2) NULL AFTER whatsapp_addon_monthly_price",
    "ALTER TABLE settings ADD COLUMN whatsapp_addon_yearly_price DECIMAL(10,2) NULL AFTER whatsapp_addon_quarterly_price",
    "ALTER TABLE settings ADD COLUMN smtp_addon_monthly_price DECIMAL(10,2) NULL AFTER whatsapp_addon_yearly_price",
    "ALTER TABLE settings ADD COLUMN smtp_addon_quarterly_price DECIMAL(10,2) NULL AFTER smtp_addon_monthly_price",
    "ALTER TABLE settings ADD COLUMN smtp_addon_yearly_price DECIMAL(10,2) NULL AFTER smtp_addon_quarterly_price",
];

foreach ($columns as $sql) {
    try {
        $pdo->exec($sql);
        echo "✓ Added column: {$sql}\n";
    } catch (PDOException $e) {
        // 1060 = duplicate column name
        if (intval($e->errorInfo[1] ?? 0) === 1060) {
            echo "- Column already exists, skipping\n";
        } else {
            throw $e;
        }
    }
}

echo "Communication add-on migration completed.\n";

