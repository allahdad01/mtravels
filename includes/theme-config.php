<?php
/**
 * Central Theme & Color Configuration
 * 
 * This file manages all color and theme variables for the application.
 * All colors are defined here and can be used throughout the system.
 * 
 * Usage:
 *   echo ThemeConfig::getPrimary();      // #0d6cf5
 *   echo ThemeConfig::getSecondary();    // #08fdd1
 *   echo ThemeConfig::getCSSVariable();  // Returns <link> tag
 */

class ThemeConfig {
    /**
     * Modern Cyan/Blue Palette - Primary Colors
     */
    private static $colors = [
        // Primary brand colors
        'primary' => '#0d6cf5',           // Deep blue
        'primary-dark' => '#0a4fa8',      // Darker blue
        'primary-light' => '#3f8cff',     // Lighter blue
        'primary-lighter' => '#e6f2ff',   // Very light blue
        
        // Secondary brand colors
        'secondary' => '#08fdd1',         // Bright cyan
        'secondary-dark' => '#06c4a6',    // Darker cyan
        'secondary-light' => '#4bfde5',   // Lighter cyan
        'secondary-lighter' => '#e0fdf9', // Very light cyan
        
        // Accent colors
        'accent-blue' => '#1699f9',       // Sky blue
        'accent-aqua' => '#04d0e7',       // Aqua cyan
        'accent-mint' => '#06ecd1',       // Mint cyan
        
        // Status colors
        'success' => '#10b981',
        'danger' => '#ef4444',
        'warning' => '#f59e0b',
        'info' => '#06b6d4',
        
        // Neutral colors
        'text-primary' => '#1a1a1a',
        'text-secondary' => '#6c757d',
        'text-muted' => '#adb5bd',
        'text-light' => '#e9ecef',
        
        'border' => '#dee2e6',
        'border-light' => '#e9ecef',
        'border-dark' => '#adb5bd',
        
        'bg-light' => '#f8f9fa',
        'bg-lighter' => '#ffffff',
        'bg-dark' => '#f1f5f9',
        'bg-panel' => '#f8f9fb',
    ];
    
    /**
     * Gradient definitions
     */
    private static $gradients = [
        'blue-cyan' => 'linear-gradient(45deg, #0d6cf5, #08fdd1)',
        'sky-aqua' => 'linear-gradient(45deg, #1699f9, #04d0e7)',
        'mint-blue' => 'linear-gradient(45deg, #06ecd1, #0d6cf5)',
        'cyan-sky' => 'linear-gradient(45deg, #08fdd1, #1699f9)',
    ];
    
    /**
     * Get a color by key
     * 
     * @param string $key Color key
     * @param string $default Default color if not found
     * @return string HEX color code
     */
    public static function getColor($key, $default = '#0d6cf5') {
        return self::$colors[$key] ?? $default;
    }
    
    /**
     * Get primary color
     */
    public static function getPrimary() {
        return self::$colors['primary'];
    }
    
    /**
     * Get secondary (accent) color
     */
    public static function getSecondary() {
        return self::$colors['secondary'];
    }
    
    /**
     * Get primary dark variant
     */
    public static function getPrimaryDark() {
        return self::$colors['primary-dark'];
    }
    
    /**
     * Get secondary dark variant
     */
    public static function getSecondaryDark() {
        return self::$colors['secondary-dark'];
    }
    
    /**
     * Get a gradient by key
     */
    public static function getGradient($key) {
        return self::$gradients[$key] ?? '';
    }
    
    /**
     * Get all colors as array (useful for JSON output)
     */
    public static function getAllColors() {
        return self::$colors;
    }
    
    /**
     * Get all gradients as array
     */
    public static function getAllGradients() {
        return self::$gradients;
    }
    
    /**
     * Get colors as JSON (for JavaScript use)
     */
    public static function getColorsJSON() {
        return json_encode(self::$colors);
    }
    
    /**
     * Get style tag with inline CSS variables
     * Useful for dynamic theming
     */
    public static function getInlineStyles() {
        $css = ':root {' . "\n";
        foreach (self::$colors as $key => $value) {
            $css .= "    --color-{$key}: {$value};\n";
        }
        foreach (self::$gradients as $key => $value) {
            $css .= "    --gradient-{$key}: {$value};\n";
        }
        $css .= '}' . "\n";
        return "<style>\n" . $css . "</style>\n";
    }
    
    /**
     * Get reference to CSS file
     */
    public static function getCSSLink() {
        return '<link rel="stylesheet" href="/css/color-system.css">';
    }
    
    /**
     * Get Bootstrap/Tailwind style palette for export
     */
    public static function getPaletteForFrontend() {
        return [
            'primary' => [
                'base' => self::$colors['primary'],
                'dark' => self::$colors['primary-dark'],
                'light' => self::$colors['primary-light'],
            ],
            'secondary' => [
                'base' => self::$colors['secondary'],
                'dark' => self::$colors['secondary-dark'],
                'light' => self::$colors['secondary-light'],
            ],
            'status' => [
                'success' => self::$colors['success'],
                'danger' => self::$colors['danger'],
                'warning' => self::$colors['warning'],
                'info' => self::$colors['info'],
            ],
            'gradients' => self::$gradients,
        ];
    }
}
?>
