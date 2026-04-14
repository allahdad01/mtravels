# Central Color System Implementation Guide

## Overview

A unified color system has been implemented across the MTravels application. All colors are now defined in a **central point** and can be easily accessed from PHP, CSS, and JavaScript.

## Color Palette

### Primary Brand Colors (Cyan/Blue)

| Color | Hex Code | Usage |
|-------|----------|-------|
| **Primary Blue** | `#0d6cf5` | Main buttons, links, primary UI elements |
| **Primary Dark** | `#0a4fa8` | Hover states, active states |
| **Primary Light** | `#3f8cff` | Disabled states, variants |
| **Primary Lighter** | `#e6f2ff` | Light backgrounds |
| **Secondary (Cyan)** | `#08fdd1` | Accents, highlights, badges |
| **Secondary Dark** | `#06c4a6` | Dark cyan variants |
| **Accent Blue** | `#1699f9` | Secondary buttons, alternative states |
| **Accent Aqua** | `#04d0e7` | Info messages, secondary accents |
| **Accent Mint** | `#06ecd1` | Success indicators, tertiary accents |

### Status Colors

| Status | Color | Hex Code |
|--------|-------|----------|
| Success | Green | `#10b981` |
| Error/Danger | Red | `#ef4444` |
| Warning | Amber | `#f59e0b` |
| Info | Cyan | `#06b6d4` |

## Files Structure

### Central Configuration Files

1. **CSS: `/css/color-system.css`**
   - Defines all CSS custom properties (variables)
   - Utility classes for quick color access
   - Gradient definitions

2. **PHP: `/includes/theme-config.php`**
   - Static class for server-side color access
   - JSON export for frontend
   - Theme management

3. **JavaScript: `/js/theme-config.js`**
   - Client-side color access
   - DOM manipulation helpers
   - Color conversion utilities

4. **Design System: `/css/design-system.css`**
   - Imports color-system.css
   - Uses centralized colors throughout

## How to Use

### In CSS Files

```css
/* Use CSS variables directly */
.button {
    background-color: var(--color-primary);
    color: white;
}

.card {
    border: 1px solid var(--color-border);
    background: var(--color-bg-light);
}

/* Use gradient classes */
.hero-section {
    background: var(--gradient-blue-cyan);
}
```

### In PHP Files

```php
<?php
// Include the theme config
require_once dirname(__FILE__) . '/includes/theme-config.php';

// Get single colors
$primary = ThemeConfig::getPrimary();      // #0d6cf5
$secondary = ThemeConfig::getSecondary();  // #08fdd1

// Get any color by key
$color = ThemeConfig::getColor('accent-blue');  // #1699f9

// Get gradients
$gradient = ThemeConfig::getGradient('blue-cyan');

// Use in inline styles
echo '<div style="background-color: ' . ThemeConfig::getPrimary() . '">';

// Export colors to JavaScript
echo ThemeConfig::getInlineStyles();

// Get all colors as JSON
$colorsJSON = ThemeConfig::getColorsJSON();
?>
```

### In HTML Templates

```html
<!-- Include the color system CSS -->
<link rel="stylesheet" href="/css/color-system.css">

<!-- Include design system which imports color-system -->
<link rel="stylesheet" href="/css/design-system.css">

<!-- Include theme config JavaScript -->
<script src="/js/theme-config.js"></script>
```

### In JavaScript Files

```javascript
// Access colors directly
const primaryColor = ThemeConfig.colors.primary;  // #0d6cf5
const secondary = ThemeConfig.getColor('secondary');

// Apply colors to elements
ThemeConfig.applyColor('.button', 'primary', 'backgroundColor');
ThemeConfig.applyGradient('.hero', 'blue-cyan');

// Convert colors
const rgba = ThemeConfig.toRgba('primary', 0.5);  // rgba(13, 108, 245, 0.5)

// Initialize CSS variables (auto-called on page load)
ThemeConfig.initializeCSSVariables();

// Debug: show all colors
ThemeConfig.logColors();

// Create elements with theme colors
const html = ThemeConfig.createButton('Click Me', '', 'primary');
```

## Utility Classes

### Quick CSS Classes

```html
<!-- Text colors -->
<p class="text-cyan">Cyan text</p>
<p class="text-blue-primary">Blue text</p>

<!-- Background colors -->
<div class="bg-cyan">Cyan background</div>
<div class="bg-blue-primary">Blue background</div>

<!-- Border colors -->
<div class="border-cyan" style="border: 1px solid;">Cyan border</div>

<!-- Gradients -->
<div class="bg-gradient-blue-cyan">Gradient background</div>
```

## Implementation Examples

### 1. Basic Button with Primary Color

**HTML:**
```html
<button class="btn btn-primary">Click Me</button>
```

**CSS:**
```css
.btn-primary {
    background-color: var(--color-primary);
    color: white;
}

.btn-primary:hover {
    background-color: var(--color-primary-dark);
}
```

### 2. Card with Border and Background

**HTML:**
```html
<div class="card">
    <h3>Card Title</h3>
    <p>Card content</p>
</div>
```

**CSS:**
```css
.card {
    background-color: var(--color-bg-panel);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 20px;
}
```

### 3. Gradient Accent

**HTML:**
```html
<div class="hero-section">
    <h1>Welcome</h1>
</div>
```

**CSS:**
```css
.hero-section {
    background: var(--gradient-blue-cyan);
    color: white;
    padding: 60px 20px;
}
```

### 4. Status Messages

**HTML:**
```html
<div class="alert alert-success">Success message</div>
<div class="alert alert-danger">Error message</div>
<div class="alert alert-warning">Warning message</div>
<div class="alert alert-info">Info message</div>
```

**CSS:**
```css
.alert {
    padding: 12px;
    border-radius: 4px;
    margin-bottom: 16px;
}

.alert-success {
    background-color: rgba(16, 185, 129, 0.1);
    border: 1px solid var(--color-success);
    color: var(--color-success);
}

.alert-danger {
    background-color: rgba(239, 68, 68, 0.1);
    border: 1px solid var(--color-danger);
    color: var(--color-danger);
}
```

## Updating Colors in the Future

To change the color scheme globally:

1. **Edit `/css/color-system.css`** - Update the CSS custom properties
2. **Edit `/includes/theme-config.php`** - Update the PHP color array
3. **Edit `/js/theme-config.js`** - Update the JavaScript colors object

All changes will automatically propagate throughout the system.

## Color Accessibility

### Text Contrast

- Dark text (`#1a1a1a`) on light backgrounds
- Light text (`#ffffff`) on dark backgrounds
- Minimum contrast ratio of 4.5:1 for AA compliance

### Use Cases

| Component | Color |
|-----------|-------|
| Primary buttons | `--color-primary` |
| Links | `--color-primary` with underline |
| Hover states | `--color-primary-dark` |
| Disabled states | `--color-text-muted` |
| Focus states | `--color-primary` with outline |
| Success messages | `--color-success` |
| Error messages | `--color-danger` |
| Borders | `--color-border` |

## Dark Mode Support (Future)

Variables are prepared for dark mode:
- `--color-bg-dark-primary`
- `--color-bg-dark-secondary`
- `--color-text-dark-primary`
- `--color-text-dark-secondary`

Toggle with:
```css
@media (prefers-color-scheme: dark) {
    :root {
        --color-bg-light: var(--color-bg-dark-primary);
        --color-text-primary: var(--color-text-dark-primary);
    }
}
```

## Quick Reference

### Most Used Colors

```
Primary Blue:     #0d6cf5  (Use for main elements)
Cyan Accent:      #08fdd1  (Use for highlights)
Success Green:    #10b981  (Use for success states)
Danger Red:       #ef4444  (Use for errors)
Warning Amber:    #f59e0b  (Use for warnings)
Text Primary:     #1a1a1a  (Use for main text)
Border Light:     #dee2e6  (Use for borders)
```

### CSS Variables Cheat Sheet

```css
var(--color-primary)          /* #0d6cf5 */
var(--color-secondary)        /* #08fdd1 */
var(--color-success)          /* #10b981 */
var(--color-danger)           /* #ef4444 */
var(--color-warning)          /* #f59e0b */
var(--color-text-primary)     /* #1a1a1a */
var(--color-border)           /* #dee2e6 */
var(--color-bg-light)         /* #f8f9fa */
var(--gradient-blue-cyan)     /* gradient */
```

## Testing the System

### View Color Palette in Browser

1. Create a test file `/color-palette.php`:
```php
<?php
require_once 'includes/theme-config.php';
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="/css/color-system.css">
</head>
<body>
    <?php echo ThemeConfig::getInlineStyles(); ?>
    <div style="padding: 20px;">
        <h1>Color Palette</h1>
        <?php
        foreach (ThemeConfig::getAllColors() as $name => $color) {
            echo '<div style="margin-bottom: 10px;">';
            echo '<strong>' . $name . ':</strong> ';
            echo '<span style="display: inline-block; width: 50px; height: 30px; background-color: ' . $color . '; border: 1px solid #ccc; vertical-align: middle;"></span> ';
            echo '<code>' . $color . '</code>';
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>
```

2. Open in browser and verify all colors are correctly displayed

## Support

For questions or to modify the color system, refer to:
- CSS Variables: `/css/color-system.css`
- PHP Config: `/includes/theme-config.php`
- JavaScript Config: `/js/theme-config.js`
