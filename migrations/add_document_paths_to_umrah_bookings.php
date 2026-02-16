<?php
/**
 * Migration: Add document path columns to umrah_bookings table
 * Adds photo_path, passport_path, and visa_path columns with timestamps
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

require_once __DIR__ . '/../includes/db.php';

try {
    // Check if columns already exist
    $checkStmt = $pdo->prepare("SHOW COLUMNS FROM umrah_bookings LIKE 'photo_path'");
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        // Add photo_path column
        $pdo->exec("ALTER TABLE umrah_bookings ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL AFTER remarks");
        echo "Added photo_path column\n";
    } else {
        echo "photo_path column already exists\n";
    }
    
    // Check and add passport_path column
    $checkStmt = $pdo->prepare("SHOW COLUMNS FROM umrah_bookings LIKE 'passport_path'");
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE umrah_bookings ADD COLUMN passport_path VARCHAR(255) DEFAULT NULL AFTER photo_path");
        echo "Added passport_path column\n";
    } else {
        echo "passport_path column already exists\n";
    }
    
    // Check and add visa_path column
    $checkStmt = $pdo->prepare("SHOW COLUMNS FROM umrah_bookings LIKE 'visa_path'");
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE umrah_bookings ADD COLUMN visa_path VARCHAR(255) DEFAULT NULL AFTER passport_path");
        echo "Added visa_path column\n";
    } else {
        echo "visa_path column already exists\n";
    }
    
    // Check and add photo_uploaded_at column
    $checkStmt = $pdo->prepare("SHOW COLUMNS FROM umrah_bookings LIKE 'photo_uploaded_at'");
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE umrah_bookings ADD COLUMN photo_uploaded_at TIMESTAMP NULL DEFAULT NULL AFTER visa_path");
        echo "Added photo_uploaded_at column\n";
    } else {
        echo "photo_uploaded_at column already exists\n";
    }
    
    // Check and add passport_uploaded_at column
    $checkStmt = $pdo->prepare("SHOW COLUMNS FROM umrah_bookings LIKE 'passport_uploaded_at'");
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE umrah_bookings ADD COLUMN passport_uploaded_at TIMESTAMP NULL DEFAULT NULL AFTER photo_uploaded_at");
        echo "Added passport_uploaded_at column\n";
    } else {
        echo "passport_uploaded_at column already exists\n";
    }
    
    // Check and add visa_uploaded_at column
    $checkStmt = $pdo->prepare("SHOW COLUMNS FROM umrah_bookings LIKE 'visa_uploaded_at'");
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE umrah_bookings ADD COLUMN visa_uploaded_at TIMESTAMP NULL DEFAULT NULL AFTER passport_uploaded_at");
        echo "Added visa_uploaded_at column\n";
    } else {
        echo "visa_uploaded_at column already exists\n";
    }
    
    echo "Migration completed successfully!\n";
    
} catch (Exception $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>
