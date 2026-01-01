<?php
/**
 * Application Configuration
 * Centralized constants and configuration values
 */

// Tenant Configuration
define('DEFAULT_TENANT_ID', 1);

// Plan Configuration
define('PLAN_ORDER', [
    'umrah' => 1,
    'basic' => 2,
    'pro' => 3,
    'professional' => 3,
    'enterprise' => 4
]);

// Data Limits
define('DEFAULT_DESTINATIONS_LIMIT', 6);
define('DEFAULT_DEALS_LIMIT', 3);
define('DEFAULT_BLOG_POSTS_LIMIT', 3);
define('DEFAULT_TESTIMONIALS_LIMIT', 6);

// Asset Paths
define('LOGO_UPLOAD_PATH', 'uploads/logo/');
define('DEFAULT_LOGO', 'default-logo.png');

// Currency
define('DEFAULT_CURRENCY', 'AFN');

// Debug Mode
define('ENABLE_DEBUG_MODE', getenv('APP_DEBUG') === 'true');

// Cache Configuration
define('CACHE_DURATION_SETTINGS', 3600);      // 1 hour
define('CACHE_DURATION_PLANS', 3600);         // 1 hour
define('CACHE_DURATION_DESTINATIONS', 7200);  // 2 hours
define('CACHE_DURATION_TESTIMONIALS', 3600);  // 1 hour
define('CACHE_DURATION_DEALS', 1800);         // 30 minutes
define('CACHE_DURATION_BLOG', 3600);          // 1 hour
