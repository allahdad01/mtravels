<?php
/**
 * Set secure headers for all admin pages
 * This ensures consistent CSP and security headers across the admin section
 */

function setSecureHeaders() {
    header("X-XSS-Protection: 1; mode=block");
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.datatables.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.datatables.net https://maxcdn.bootstrapcdn.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self' https:;");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

// Automatically call the function
setSecureHeaders();
?>
