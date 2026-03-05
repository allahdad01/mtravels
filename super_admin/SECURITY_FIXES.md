# Super Admin Security Fixes

## Overview
This document outlines all security enhancements made to the super admin panel to ensure proper authorization, prevent common web vulnerabilities, and enforce system administrator access control.

---

## Key Principle
**Super Admin is a SYSTEM ADMINISTRATOR role, NOT a tenant-based role.**

All super admins must have:
- `role = 'super_admin'`
- `tenant_id = NULL` (no tenant affiliation)

---

## 1. Authorization Enhancement

### New Functions Added

#### `check_super_admin()`
Comprehensive check for system administrator status:
```php
function check_super_admin() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['role']) && 
           $_SESSION['role'] === 'super_admin' && 
           (empty($_SESSION['tenant_id']) || is_null($_SESSION['tenant_id']));
}
```

#### `enforce_super_admin()`
Enforces super admin access with proper error handling:
```php
function enforce_super_admin() {
    if (!check_super_admin()) {
        // Log unauthorized attempt
        // Redirect to access denied page
        exit();
    }
}
```

### Files Updated with Proper Authorization

1. **get_demo_request_details.php**
   - Now uses `check_super_admin()` instead of basic role check
   - Properly validates system admin status

2. **user_addon_payments.php**
   - Uses `enforce_super_admin()` for complete authorization check
   - Prevents tenant admin bypass

3. **subscription_payments.php**
   - Implements proper super admin validation
   - Rejects any tenant-affiliated users

4. **get_subscription_payments.php**
   - Added complete authorization validation
   - Ensures only system admins can access

5. **branch_addon_payments.php**
   - Updated with proper super admin enforcement
   - Validates `tenant_id` is NULL

6. **generate_invoice_pdf.php**
   - Restricted to super admin only
   - Removed tenant admin access
   - Proper error logging for unauthorized attempts

---

## 2. CSRF Protection Improvements

### Timing-Safe Comparison
Changed from simple string comparison to `hash_equals()`:

```php
// BEFORE (vulnerable to timing attacks)
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) { }

// AFTER (timing-safe)
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) { }
```

**Impact:** Prevents attackers from using timing analysis to forge CSRF tokens.

---

## 3. Content Security Policy (CSP) Hardening

### Changes Made
- **Removed `'unsafe-inline'`** from script and style directives
- **Removed external CDN allowlist** (unpkg, jsdelivr)
- **Restricted to trusted sources only:**
  - `script-src: 'self' https://cdn.datatables.net https://cdnjs.cloudflare.com`
  - `style-src: 'self' https://fonts.googleapis.com https://cdn.datatables.net https://cdnjs.cloudflare.com`
- **Added `form-action 'self'`** to prevent form submissions to external domains

**Impact:** Significantly reduces XSS attack surface and external dependency risks.

---

## 4. Rate Limiting Improvement

### Atomic Operations
Implemented file-locking mechanism to prevent race conditions:

```php
// Uses LOCK_EX for atomic file operations
// Prevents concurrent requests from bypassing limits
// Fail-open approach: if lock fails, allow request
```

**Changes:**
- Uses dedicated cache directory with restricted permissions (0700)
- Atomic increment with locking
- Prevents race condition exploits

---

## 5. Path Traversal Prevention

### File Browser Fix
Fixed undefined variable in path validation:

```php
// BEFORE
if ($realItemPath === false || strpos($realItemPath, $realUploadsPath) !== 0)

// AFTER  
$realUploadsPath = realpath($uploadsDir);
if ($realItemPath === false || $realUploadsPath === false || strpos(...) !== 0)
```

**Impact:** Properly validates path traversal attempts are within upload directory.

---

## 6. Input Validation & Sanitization

### New Security Functions

#### `validate_input_length()`
Prevents DoS attacks via excessively long inputs:
```php
function validate_input_length($input, $max_length = 1000) {
    if (strlen($input) > $max_length) {
        error_log("Input length exceeded limit");
        return null;
    }
    return $input;
}
```

#### `sanitize_search_input()`
Detects and blocks SQL injection patterns:
```php
function sanitize_search_input($input, $max_length = 255) {
    // Detects patterns like: UNION SELECT, INSERT INTO, DROP TABLE, etc.
    // Returns null for suspicious input
}
```

### Files Updated

1. **manage_tenants.php**
   - Search input validated for length and suspicious patterns
   - Status filter properly sanitized
   - Prevents both DoS and injection attempts

2. **file_browser.php**
   - Search and folder parameters validated
   - Maximum lengths enforced
   - Prevents excessive string processing

---

## 7. Authorization Consistency

### Standardized Pattern
All super admin endpoints now follow this pattern:

```php
// 1. Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Include database and security
require_once '../config.php';
require_once '../includes/db.php';
require_once 'security.php';

// 3. Enforce authorization
enforce_super_admin();

// 4. Proceed with business logic
```

---

## 8. Vulnerability Fixes Summary

| Issue | Severity | Fix | Files |
|-------|----------|-----|-------|
| Missing tenant_id check | HIGH | Added check_super_admin() | 5 files |
| Timing-safe CSRF | MEDIUM | Updated to hash_equals() | security.php |
| CSP too permissive | MEDIUM | Removed unsafe-inline | security.php |
| Race condition rate limit | MEDIUM | Implemented locking | security.php |
| Path traversal | MEDIUM | Define realUploadsPath | file_browser.php |
| Unlimited input length | MEDIUM | validate_input_length() | manage_tenants.php, file_browser.php |
| SQL injection in search | MEDIUM | sanitize_search_input() | manage_tenants.php |

---

## 9. Recommended Additional Steps

### For Production Deployment

1. **Enable HTTPS only**
   - Ensure `session.cookie_secure = 1` in production

2. **Remove debug logging of sensitive data**
   - Review error_log calls for sensitive information

3. **Consider nonce-based CSP**
   - Generate nonce per request for inline scripts (if absolutely necessary)

4. **Database-backed rate limiting**
   - File-based rate limiting works but Redis/Memcached is more robust

5. **Regular security audits**
   - Test all authorization paths
   - Verify super admin isolation

---

## 10. Testing Checklist

- [ ] Super admin without tenant_id can access all protected pages
- [ ] User with tenant_id cannot access super admin pages (even if role=super_admin)
- [ ] CSRF token is required and validated for all POST requests
- [ ] Search inputs > 255 chars are rejected
- [ ] Suspicious SQL patterns in search are logged and rejected
- [ ] Rate limiting blocks excessive requests
- [ ] File browser cannot traverse outside uploads directory
- [ ] CSP headers are properly set
- [ ] Unauthorized access attempts are logged with IP addresses

---

## 11. Security Headers Verification

Run the following to verify headers:

```bash
curl -I https://your-domain.com/super_admin/dashboard.php
```

Expected headers:
- `X-XSS-Protection: 1; mode=block`
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Content-Security-Policy: ...`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Strict-Transport-Security: max-age=31536000; includeSubDomains` (HTTPS only)

---

## 12. Logging & Monitoring

All unauthorized access attempts are logged with:
- Timestamp
- User ID (if authenticated)
- IP Address
- Role and Tenant ID
- Requested resource

Review logs regularly:
```bash
tail -f /var/log/php-errors.log | grep "Unauthorized"
```

---

## Version History

- **v1.0** (2026-03-05): Initial security hardening
  - Added check_super_admin() and enforce_super_admin()
  - Implemented timing-safe CSRF validation
  - Hardened CSP policy
  - Improved rate limiting with atomic operations
  - Added input validation functions
  - Fixed path traversal vulnerability
  - Applied fixes to 6+ vulnerable endpoints
