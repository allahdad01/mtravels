<?php
/**
 * Global Helper Functions
 * Shared utilities used across the application
 */

/**
 * Get a setting value from settings array
 * 
 * @param array $settings Settings array
 * @param string $key Setting key
 * @param string $default Default value if not found
 * @return string Escaped setting value
 */
function getSetting($settings, $key, $default = '') {
    return isset($settings[$key]) ? htmlspecialchars($settings[$key]) : $default;
}

/**
 * Format amount as currency string
 * 
 * @param float $amount Amount to format
 * @param string $currency Currency code (default: AFN)
 * @return string Formatted currency string
 */
function formatCurrency($amount, $currency = DEFAULT_CURRENCY) {
    return $currency . ' ' . number_format($amount, 0);
}

/**
 * Convert snake_case to Title Case
 * Useful for feature names and labels
 * 
 * @param string $feature Feature name in snake_case
 * @return string Formatted feature name
 */
function formatFeatureName($feature) {
    return ucwords(str_replace('_', ' ', $feature));
}

/**
 * Get safe asset URL with optional cache busting
 * 
 * @param string $path Relative path to asset
 * @param bool $cacheBust Include file modification time for cache busting
 * @return string Full asset URL
 */
function getAssetUrl($path, $cacheBust = false) {
    if ($cacheBust && file_exists($path)) {
        $mtime = filemtime($path);
        return $path . '?v=' . $mtime;
    }
    return $path;
}

/**
 * Get logo URL with fallback
 * 
 * @param array $settings Platform settings
 * @return string Logo URL
 */
function getLogoUrl($settings) {
    $logo = getSetting($settings, 'platform_logo', DEFAULT_LOGO);
    return LOGO_UPLOAD_PATH . htmlspecialchars($logo);
}

/**
 * Get platform name with fallback
 * 
 * @param array $settings Platform settings
 * @return string Platform name
 */
function getPlatformName($settings) {
    return getSetting($settings, 'platform_name', 'MTravels');
}

/**
 * Get platform description with fallback
 * 
 * @param array $settings Platform settings
 * @return string Platform description
 */
function getPlatformDescription($settings) {
    $default = 'The most advanced SaaS platform for modern travel agencies. Streamline operations, boost sales, and delight customers.';
    return getSetting($settings, 'platform_description', $default);
}

/**
 * Truncate text to specified length
 * 
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix to append (default: ...)
 * @return string Truncated text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length - strlen($suffix)) . $suffix;
}

/**
 * Log debug information if debug mode is enabled
 * 
 * @param string $message Message to log
 * @param string $level Log level (info, warning, error)
 * @return void
 */
function logDebug($message, $level = 'info') {
    if (ENABLE_DEBUG_MODE) {
        $timestamp = date('Y-m-d H:i:s');
    }
}

/**
 * Measure execution time
 * 
 * @param string $label Label for the timing
 * @param float $startTime Start time from microtime(true)
 * @return float Elapsed time in milliseconds
 */
function getElapsedTime($label, $startTime) {
    $elapsed = (microtime(true) - $startTime) * 1000;
    logDebug("$label: {$elapsed}ms");
    return $elapsed;
}

/**
 * Get currency symbol from currency code
 *
 * @param string $currency Currency code (e.g. USD, AFN, EUR)
 * @return string Currency symbol
 */
if (!function_exists('getCurrencySymbol')) {
    function getCurrencySymbol($currency) {
        $symbols = [
            'USD' => '$',
            'AFN' => '؋',
            'AFS' => '؋',  // Legacy support
            'EUR' => '€',
            'GBP' => '£',
            'INR' => '₹',
            'JPY' => '¥',
            'CNY' => '¥',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'CHF' => 'CHF',
            'SEK' => 'kr',
            'NZD' => 'NZ$',
        ];
        return $symbols[strtoupper($currency)] ?? '$';
    }
}
