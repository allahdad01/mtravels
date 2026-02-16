<?php
/**
 * Set secure headers for all admin pages
 * This ensures consistent CSP and security headers across the admin section
 */

function setSecureHeaders() {
    header("X-XSS-Protection: 1; mode=block");
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    // CSP is now managed by .htaccess (removed to avoid conflicts)
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

// Automatically call the function
setSecureHeaders();
?>
