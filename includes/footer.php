<?php
/**
 * Centralized Footer Component
 * Include this in all pages: <?php require_once 'includes/footer.php'; ?>
 */

// Ensure $platform_settings is available
if (!isset($platform_settings)) {
    require_once 'includes/db.php';
    require_once 'includes/cache.php';
    require_once 'includes/helpers.php';
    
    function getPlatformSettings($pdo) {
        $cache_key = 'platform_settings_' . md5('platform_settings');
        
        if (function_exists('getCachedData') && $cached = getCachedData($cache_key)) {
            return $cached;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT `key`, `value` FROM platform_settings ORDER BY id");
            $stmt->execute();
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['key']] = $row['value'];
            }
            
            if (function_exists('setCachedData')) {
                setCachedData($cache_key, $settings);
            }
            return $settings;
        } catch (PDOException $e) {
            return [];
        }
    }
    
    $platform_settings = getPlatformSettings($pdo);
}
?>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h3><?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></h3>
                <p style="color: var(--gray-300); line-height: 1.6;">
                    <?php echo getSetting($platform_settings, 'platform_description', 'Professional travel agency management platform providing comprehensive solutions for booking management, financial operations, customer service, and business intelligence.'); ?>
                </p>
            </div>
            <div class="footer-section">
                <h3><?php echo getSetting($platform_settings, 'footer_product_title', 'Product'); ?></h3>
                <ul>
                    <li><a href="features.php"><?php echo getSetting($platform_settings, 'footer_features', 'Features'); ?></a></li>
                    <li><a href="index.php#pricing"><?php echo getSetting($platform_settings, 'footer_pricing', 'Pricing'); ?></a></li>
                    <li><a href="security.php"><?php echo getSetting($platform_settings, 'footer_security', 'Security'); ?></a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3><?php echo getSetting($platform_settings, 'footer_company_title', 'Company'); ?></h3>
                <ul>
                    <li><a href="about.php"><?php echo getSetting($platform_settings, 'footer_about', 'About Us'); ?></a></li>
                    <li><a href="blog.php"><?php echo getSetting($platform_settings, 'footer_blog', 'Blog'); ?></a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3><?php echo getSetting($platform_settings, 'footer_support_title', 'Support'); ?></h3>
                <ul>
                    <li><a href="index.php#contact"><?php echo getSetting($platform_settings, 'footer_contact', 'Contact Support'); ?></a></li>
                    <li><a href="status.php"><?php echo getSetting($platform_settings, 'footer_status', 'System Status'); ?></a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-bottom-content">
                <p>&copy; <?php echo date('Y'); ?> <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?>. <?php echo getSetting($platform_settings, 'footer_copyright', 'All rights reserved.'); ?></p>
                <div class="footer-legal-links">
                    <a href="privacy-policy.php"><?php echo getSetting($platform_settings, 'footer_privacy', 'Privacy Policy'); ?></a>
                    <span class="separator">|</span>
                    <a href="terms-conditions.php"><?php echo getSetting($platform_settings, 'footer_terms', 'Terms & Conditions'); ?></a>
                    <span class="separator">|</span>
                    <a href="data-protection.php"><?php echo getSetting($platform_settings, 'footer_data_protection', 'Data Protection'); ?></a>
                </div>
            </div>
        </div>
    </div>
</footer>
