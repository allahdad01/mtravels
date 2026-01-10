<?php
/**
 * Database Migration: Create Attendance Tables
 *
 * This migration creates the necessary tables for employee attendance tracking.
 * Run this file to create the tables in the database.
 */

require_once '../includes/db.php';

echo "Starting migration: Create Attendance Tables\n";
echo str_repeat("-", 50) . "\n";

try {
    // 1. Create attendance table
    echo "Creating attendance table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            branch_id INT NOT NULL,
            user_id INT NOT NULL,
            date DATE NOT NULL,
            check_in_time TIME NULL,
            check_out_time TIME NULL,
            working_minutes INT DEFAULT 0,
            status ENUM('present','late','half_day','absent') DEFAULT 'absent',
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant_branch (tenant_id, branch_id),
            INDEX idx_user_date (user_id, date),
            INDEX idx_date (date),
            INDEX idx_status (status),
            UNIQUE KEY unique_attendance (tenant_id, branch_id, user_id, date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ attendance table created successfully\n\n";

    // 2. Create attendance_settings table
    echo "Creating attendance_settings table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_settings (
            tenant_id INT NOT NULL,
            branch_id INT NOT NULL,
            office_start_time TIME NOT NULL DEFAULT '09:00:00',
            office_end_time TIME NOT NULL DEFAULT '17:00:00',
            late_after_minutes INT NOT NULL DEFAULT 15,
            half_day_minutes INT NOT NULL DEFAULT 240,
            working_days VARCHAR(50) NOT NULL DEFAULT 'Mon-Fri',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (tenant_id, branch_id),
            INDEX idx_tenant_branch (tenant_id, branch_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ attendance_settings table created successfully\n\n";

    echo str_repeat("-", 50) . "\n";
    echo "✓ Migration completed successfully!\n";
    echo "\n";
    echo "Summary:\n";
    echo "- Created attendance table (tracks daily check-in/check-out)\n";
    echo "- Created attendance_settings table (branch-specific attendance rules)\n";
    echo "\n";

} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}