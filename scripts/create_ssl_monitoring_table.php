<?php
/**
 * Database migration script for SSL Certificate Monitoring
 * Run this once to create the ssl_certificates table
 */

require_once __DIR__ . '/../includes/db.php';

try {
    // Create ssl_certificates table (system-wide, not tenant-specific)
    $sql = "
        CREATE TABLE IF NOT EXISTS `ssl_certificates` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `domain` varchar(255) NOT NULL,
            `port` int(11) DEFAULT 443,
            `description` text,
            `is_valid` tinyint(1) DEFAULT 0,
            `issuer` varchar(255) DEFAULT NULL,
            `subject` varchar(255) DEFAULT NULL,
            `valid_from` datetime DEFAULT NULL,
            `valid_to` datetime DEFAULT NULL,
            `days_until_expiry` int(11) DEFAULT NULL,
            `alert_level` enum('ok','info','warning','critical','expired','unknown') DEFAULT 'unknown',
            `serial_number` varchar(255) DEFAULT NULL,
            `error_message` text,
            `last_checked` datetime DEFAULT NULL,
            `last_alert_sent` datetime DEFAULT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_domain` (`domain`),
            KEY `idx_alert_level` (`alert_level`),
            KEY `idx_last_checked` (`last_checked`),
            KEY `idx_days_until_expiry` (`days_until_expiry`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "✅ SSL certificates table created successfully!\n";

    // Insert default domains to monitor (you can customize this)
    $defaultDomains = [
        ['domain' => 'almoqadas.com', 'description' => 'Main website domain'],
        ['domain' => 'www.almoqadas.com', 'description' => 'WWW subdomain'],
        // Add more domains as needed
    ];

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO ssl_certificates (domain, description, created_at, updated_at)
        VALUES (?, ?, NOW(), NOW())
    ");

    foreach ($defaultDomains as $domain) {
        $stmt->execute([$domain['domain'], $domain['description']]);
        echo "✅ Added domain: {$domain['domain']}\n";
    }

    echo "\n🎉 SSL Certificate Monitoring setup complete!\n";
    echo "📍 Access the SSL monitoring dashboard at: super_admin/ssl_monitoring.php\n";

} catch (Exception $e) {
    echo "❌ Error creating SSL monitoring table: " . $e->getMessage() . "\n";
    exit(1);
}
?>