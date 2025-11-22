-- Create cancellation_reapply_log table for tracking booking cancellations and re-applications
CREATE TABLE IF NOT EXISTS cancellation_reapply_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    action ENUM('cancellation', 'reapplication') NOT NULL,
    base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    sold_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    previous_profit DECIMAL(10,2) NOT NULL DEFAULT 0,
    new_profit DECIMAL(10,2) NOT NULL DEFAULT 0,
    reason TEXT NOT NULL,
    tenant_id INT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_booking_id (booking_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (booking_id) REFERENCES umrah_bookings(booking_id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;