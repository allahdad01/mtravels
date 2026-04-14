/**
 * Central Theme & Color System Configuration
 * 
 * Provides JavaScript access to the centralized color palette.
 * This matches the PHP and CSS color definitions for consistency.
 * 
 * Usage:
 *   ThemeConfig.colors.primary              // '#0d6cf5'
 *   ThemeConfig.getColor('primary')         // '#0d6cf5'
 *   ThemeConfig.getGradient('blue-cyan')    // 'linear-gradient(...)'
 *   ThemeConfig.applyTheme('card', 'primary') // Apply color to element
 */

const ThemeConfig = {
    // Primary brand colors
    colors: {
        primary: '#0d6cf5',           // Deep blue
        'primary-dark': '#0a4fa8',    // Darker blue
        'primary-light': '#3f8cff',   // Lighter blue
        'primary-lighter': '#e6f2ff', // Very light blue
        
        secondary: '#08fdd1',         // Bright cyan
        'secondary-dark': '#06c4a6',  // Darker cyan
        'secondary-light': '#4bfde5', // Lighter cyan
        'secondary-lighter': '#e0fdf9', // Very light cyan
        
        // Accent colors
        'accent-blue': '#1699f9',     // Sky blue
        'accent-aqua': '#04d0e7',     // Aqua cyan
        'accent-mint': '#06ecd1',     // Mint cyan
        
        // Status colors
        success: '#10b981',
        danger: '#ef4444',
        warning: '#f59e0b',
        info: '#06b6d4',
        
        // Neutral colors
        'text-primary': '#1a1a1a',
        'text-secondary': '#6c757d',
        'text-muted': '#adb5bd',
        'text-light': '#e9ecef',
        
        border: '#dee2e6',
        'border-light': '#e9ecef',
        'border-dark': '#adb5bd',
        
        'bg-light': '#f8f9fa',
        'bg-lighter': '#ffffff',
        'bg-dark': '#f1f5f9',
        'bg-panel': '#f8f9fb',
    },
    
    // Gradient definitions
    gradients: {
        'blue-cyan': 'linear-gradient(45deg, #0d6cf5, #08fdd1)',
        'sky-aqua': 'linear-gradient(45deg, #1699f9, #04d0e7)',
        'mint-blue': 'linear-gradient(45deg, #06ecd1, #0d6cf5)',
        'cyan-sky': 'linear-gradient(45deg, #08fdd1, #1699f9)',
    },
    
    /**
     * Get a color by key
     * @param {string} key - Color key
     * @param {string} defaultColor - Default color if key not found
     * @returns {string} HEX color code
     */
    getColor(key, defaultColor = '#0d6cf5') {
        return this.colors[key] || defaultColor;
    },
    
    /**
     * Get primary color
     */
    getPrimary() {
        return this.colors.primary;
    },
    
    /**
     * Get secondary color
     */
    getSecondary() {
        return this.colors.secondary;
    },
    
    /**
     * Get a gradient by key
     * @param {string} key - Gradient key
     * @returns {string} CSS gradient value
     */
    getGradient(key) {
        return this.gradients[key] || '';
    },
    
    /**
     * Apply color to an element
     * @param {string|Element} selector - CSS selector or DOM element
     * @param {string} colorKey - Color key from palette
     * @param {string} property - CSS property to apply (default: 'color')
     */
    applyColor(selector, colorKey, property = 'color') {
        const element = typeof selector === 'string' 
            ? document.querySelector(selector) 
            : selector;
        
        if (element) {
            const color = this.getColor(colorKey);
            element.style[property] = color;
        }
    },
    
    /**
     * Apply gradient to an element
     * @param {string|Element} selector - CSS selector or DOM element
     * @param {string} gradientKey - Gradient key from palette
     */
    applyGradient(selector, gradientKey = 'blue-cyan') {
        const element = typeof selector === 'string' 
            ? document.querySelector(selector) 
            : selector;
        
        if (element) {
            const gradient = this.getGradient(gradientKey);
            element.style.background = gradient;
        }
    },
    
    /**
     * Get CSS variable reference (for use in inline styles)
     * @param {string} colorKey - Color key from palette
     * @returns {string} CSS variable string
     */
    getCSSVar(colorKey) {
        return `var(--color-${colorKey})`;
    },
    
    /**
     * Convert color to RGB format
     * @param {string} hex - Hex color code
     * @returns {string} RGB color code
     */
    hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result 
            ? `${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)}`
            : '0, 0, 0';
    },
    
    /**
     * Convert color to RGBA format
     * @param {string} colorKey - Color key or hex value
     * @param {number} opacity - Opacity value (0-1)
     * @returns {string} RGBA color code
     */
    toRgba(colorKey, opacity = 0.5) {
        const hex = this.getColor(colorKey, colorKey);
        const rgb = this.hexToRgb(hex);
        return `rgba(${rgb}, ${opacity})`;
    },
    
    /**
     * Get all colors as object
     */
    getAllColors() {
        return { ...this.colors };
    },
    
    /**
     * Get all gradients as object
     */
    getAllGradients() {
        return { ...this.gradients };
    },
    
    /**
     * Initialize CSS custom properties from this config
     * Useful for ensuring CSS variables are set correctly
     */
    initializeCSSVariables() {
        const root = document.documentElement;
        
        // Set color variables
        Object.entries(this.colors).forEach(([key, value]) => {
            root.style.setProperty(`--color-${key}`, value);
        });
        
        // Set gradient variables
        Object.entries(this.gradients).forEach(([key, value]) => {
            root.style.setProperty(`--gradient-${key}`, value);
        });
    },
    
    /**
     * Get themed button HTML with primary color
     * @param {string} text - Button text
     * @param {string} onClick - Optional onclick handler
     * @param {string} colorKey - Color key (default: primary)
     * @returns {string} HTML button element
     */
    createButton(text, onClick = '', colorKey = 'primary') {
        const color = this.getColor(colorKey);
        const clickHandler = onClick ? `onclick="${onClick}"` : '';
        return `<button style="background-color: ${color}; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;" ${clickHandler}>${text}</button>`;
    },
    
    /**
     * Create a color palette display for development
     * Shows all available colors
     * @returns {string} HTML palette display
     */
    createPaletteDisplay() {
        let html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; padding: 20px;">';
        
        Object.entries(this.colors).forEach(([key, value]) => {
            html += `
                <div style="border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div style="background-color: ${value}; height: 100px; display: flex; align-items: center; justify-content: center; font-weight: bold; color: ${this.isLightColor(value) ? '#000' : '#fff'};">${key}</div>
                    <div style="padding: 10px; background: white; font-family: monospace; font-size: 12px;">${value}</div>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    },
    
    /**
     * Check if a color is light or dark
     * Helps determine text color contrast
     * @param {string} color - Hex color code
     * @returns {boolean} True if color is light
     */
    isLightColor(color) {
        const hex = color.replace('#', '');
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);
        const brightness = (r * 299 + g * 587 + b * 114) / 1000;
        return brightness > 155;
    },
    
    /**
     * Log all colors to console for debugging
     */
    logColors() {
        console.group('🎨 Theme Colors');
        Object.entries(this.colors).forEach(([key, value]) => {
            console.log(`%c${key}: ${value}`, `background: ${value}; color: ${this.isLightColor(value) ? '#000' : '#fff'}; padding: 4px 8px; border-radius: 4px; font-weight: bold;`);
        });
        console.groupEnd();
    },
};

// Auto-initialize CSS variables when script loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ThemeConfig.initializeCSSVariables());
} else {
    ThemeConfig.initializeCSSVariables();
}
