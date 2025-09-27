<?php
// Migration script for Umrah Multiple Suppliers Support
require_once 'config.php';
require_once 'includes/conn.php';

echo "Starting Umrah Services Migration...\n";

try {
    // Create new table for umrah booking services
    $sql1 = "CREATE TABLE IF NOT EXISTS `umrah_booking_services` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `tenant_id` int(11) NOT NULL,
      `booking_id` int(11) NOT NULL,
      `service_type` enum('all','ticket','visa','hotel','transport') NOT NULL,
      `supplier_id` int(11) NOT NULL,
      `base_price` decimal(10,3) NOT NULL DEFAULT 0.000,
      `sold_price` decimal(10,3) NOT NULL DEFAULT 0.000,
      `profit` decimal(10,3) NOT NULL DEFAULT 0.000,
      `currency` enum('USD','AFS') NOT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `booking_id` (`booking_id`),
      KEY `supplier_id` (`supplier_id`),
      KEY `tenant_id` (`tenant_id`),
      KEY `service_type` (`service_type`),
      CONSTRAINT `fk_ub_services_booking` FOREIGN KEY (`booking_id`) REFERENCES `umrah_bookings` (`booking_id`) ON DELETE CASCADE,
      CONSTRAINT `fk_ub_services_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
      CONSTRAINT `fk_ub_services_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    if ($conn->query($sql1)) {
        echo "✓ Created umrah_booking_services table\n";
    } else {
        throw new Exception("Failed to create table: " . $conn->error);
    }

    // Check if total fields already exist
    $result = $conn->query("SHOW COLUMNS FROM umrah_bookings LIKE 'total_base_price'");
    if ($result->num_rows == 0) {
        // Add total fields to umrah_bookings table
        $sql2 = "ALTER TABLE `umrah_bookings`
        ADD COLUMN `total_base_price` decimal(10,3) DEFAULT 0.000 AFTER `remarks`,
        ADD COLUMN `total_sold_price` decimal(10,3) DEFAULT 0.000 AFTER `total_base_price`,
        ADD COLUMN `total_profit` decimal(10,3) DEFAULT 0.000 AFTER `total_sold_price`;";

        if ($conn->query($sql2)) {
            echo "✓ Added total fields to umrah_bookings table\n";
        } else {
            throw new Exception("Failed to add columns: " . $conn->error);
        }
    } else {
        echo "✓ Total fields already exist in umrah_bookings table\n";
    }

    // Migrate existing data
    $sql3 = "INSERT INTO umrah_booking_services (
        tenant_id,
        booking_id,
        service_type,
        supplier_id,
        base_price,
        sold_price,
        profit,
        currency
    )
    SELECT
        ub.tenant_id,
        ub.booking_id,
        'ticket' as service_type,
        ub.supplier,
        COALESCE(ub.price, 0),
        COALESCE(ub.sold_price, 0),
        COALESCE(ub.profit, 0),
        COALESCE(ub.currency, 'USD')
    FROM umrah_bookings ub
    WHERE ub.supplier IS NOT NULL
    AND NOT EXISTS (
        SELECT 1 FROM umrah_booking_services ubs
        WHERE ubs.booking_id = ub.booking_id
    );";

    if ($conn->query($sql3)) {
        $affected = $conn->affected_rows;
        echo "✓ Migrated $affected existing bookings to services table\n";
    } else {
        throw new Exception("Failed to migrate data: " . $conn->error);
    }

    // Update total fields for existing bookings
    $sql4 = "UPDATE umrah_bookings ub
    SET
        total_base_price = COALESCE(ub.price, 0),
        total_sold_price = COALESCE(ub.sold_price, 0),
        total_profit = COALESCE(ub.profit, 0)
    WHERE total_base_price = 0 AND ub.supplier IS NOT NULL;";

    if ($conn->query($sql4)) {
        $affected = $conn->affected_rows;
        echo "✓ Updated total fields for $affected existing bookings\n";
    } else {
        throw new Exception("Failed to update totals: " . $conn->error);
    }

    echo "\n✅ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
?>