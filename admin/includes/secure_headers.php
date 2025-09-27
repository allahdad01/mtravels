<?php
/**
 * Apply security headers to protect against common web vulnerabilities
 */

// Prevent clickjacking attacks
header("X-Frame-Options: DENY");

// Prevent MIME-type sniffing
header("X-Content-Type-Options: nosniff");

// Enable XSS protection
header("X-XSS-Protection: 1; mode=block");

// Content Security Policy
// Adjust based on your site's specific needs
$csp = "default-src 'self'; ";
$csp .= "script-src 'self' 'unsafe-inline'; ";
$csp .= "style-src 'self' 'unsafe-inline'; ";
$csp .= "img-src 'self' data:; ";
$csp .= "font-src 'self'; ";
$csp .= "connect-src 'self'; ";
$csp .= "frame-src 'none'; ";
$csp .= "object-src 'none'; ";
$csp .= "base-uri 'self';";
header("Content-Security-Policy: $csp");

// Referrer Policy
header("Referrer-Policy: strict-origin-when-cross-origin");

// HSTS (HTTP Strict Transport Security)
// Only enable this if your site is fully HTTPS
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}

// Disable cache for sensitive pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Prevent MIME sniffing
header("X-Content-Type-Options: nosniff");

// Permissions Policy (formerly Feature Policy)
// Restrict access to browser features
$permissions = "geolocation=(), ";
$permissions .= "microphone=(), ";
$permissions .= "camera=(), ";
$permissions .= "payment=(), ";
$permissions .= "usb=()";
header("Permissions-Policy: $permissions");

// Set secure cookie flag
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

// Set HTTP-only cookie flag (prevents JavaScript access)
ini_set('session.cookie_httponly', 1);

// Set SameSite cookie attribute
ini_set('session.cookie_samesite', 'Strict');
?> 