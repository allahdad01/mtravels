<?php
/**
 * Database migration script for Advanced Rate Limiting
 * Run this once to create the advanced rate limiting tables
 */

require_once __DIR__ . '/../includes/db.php';

try {
    // Create advanced rate limits table (for token bucket states)
    $sql1 = "
        CREATE TABLE IF NOT EXISTS `advanced_rate_limits` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `limit_key` varchar(255) NOT NULL,
            `bucket_data` json DEFAULT NULL,
            `last_updated` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_limit_key` (`limit_key`),
            KEY `idx_last_updated` (`last_updated`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql1);
    echo "✅ Advanced rate limits table created successfully!\n";

    // Create advanced rate limit requests table (for sliding window timestamps)
    $sql2 = "
        CREATE TABLE IF NOT EXISTS `advanced_rate_limit_requests` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `limit_key` varchar(255) NOT NULL,
            `timestamp` int(11) NOT NULL,

            PRIMARY KEY (`id`),
            KEY `idx_limit_key_timestamp` (`limit_key`, `timestamp`),
            KEY `idx_timestamp` (`timestamp`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql2);
    echo "✅ Advanced rate limit requests table created successfully!\n";

    // Create API versions table for versioning support
    $sql3 = "
        CREATE TABLE IF NOT EXISTS `api_versions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `version` varchar(10) NOT NULL,
            `is_active` tinyint(1) DEFAULT 1,
            `description` text,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_version` (`version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql3);
    echo "✅ API versions table created successfully!\n";

    // Insert default API versions
    $versions = [
        ['version' => 'v1', 'description' => 'Legacy API version'],
        ['version' => 'v2', 'description' => 'Current API version with advanced rate limiting']
    ];

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO api_versions (version, description, is_active)
        VALUES (?, ?, 1)
    ");

    foreach ($versions as $version) {
        $stmt->execute([$version['version'], $version['description']]);
        echo "✅ Added API version: {$version['version']}\n";
    }

    echo "\n🎉 Advanced Rate Limiting setup complete!\n";
    echo "📍 Features now available:\n";
    echo "   • Token Bucket algorithm with burst handling\n";
    echo "   • Sliding Window algorithm for accurate rate limiting\n";
    echo "   • API versioning support\n";
    echo "   • Redis support for distributed environments\n";
    echo "   • Database fallback storage\n";

} catch (Exception $e) {
    echo "❌ Error creating advanced rate limiting tables: " . $e->getMessage() . "\n";
    exit(1);
}
?>