-- Migration for Umrah Multiple Suppliers Support
-- Date: 2025-09-27

-- Create new table for umrah booking services
CREATE TABLE `umrah_booking_services` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add total fields to umrah_bookings table
ALTER TABLE `umrah_bookings`
ADD COLUMN `total_base_price` decimal(10,3) DEFAULT 0.000 AFTER `remarks`,
ADD COLUMN `total_sold_price` decimal(10,3) DEFAULT 0.000 AFTER `total_base_price`,
ADD COLUMN `total_profit` decimal(10,3) DEFAULT 0.000 AFTER `total_sold_price`;

-- Migration script to move existing data
-- This will create service records for existing bookings
INSERT INTO umrah_booking_services (
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
    'ticket' as service_type, -- Default to ticket for migration
    ub.supplier,
    COALESCE(ub.price, 0),
    COALESCE(ub.sold_price, 0),
    COALESCE(ub.profit, 0),
    COALESCE(ub.currency, 'USD')
FROM umrah_bookings ub
WHERE ub.supplier IS NOT NULL;

-- Update total fields for existing bookings
UPDATE umrah_bookings ub
SET
    total_base_price = COALESCE(ub.price, 0),
    total_sold_price = COALESCE(ub.sold_price, 0),
    total_profit = COALESCE(ub.profit, 0)
WHERE total_base_price = 0;

-- Note: After migration, the old supplier, price, sold_price, profit, currency columns
-- can be removed from umrah_bookings table if desired, but keeping them for now
-- for backward compatibility during transition period