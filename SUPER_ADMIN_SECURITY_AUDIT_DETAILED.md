# Super Admin Security Audit Report

**Date:** February 7, 2026
**Status:** Multiple Critical and High-Risk Vulnerabilities Identified

---

## Executive Summary

The super_admin directory contains numerous critical security vulnerabilities that require immediate remediation. While some security measures are implemented (CSRF protection, secure headers, input validation), several high-risk issues can allow attackers to:
- Access sensitive payment information (IDOR vulnerability)
- Bypass file upload restrictions  
- Execute arbitrary SQL queries
- Perform directory traversal attacks
- Upload and execute malicious files

---

## Critical Vulnerabilities

### 1. **CRITICAL: Insecure Direct Object Reference (IDOR) in PDF Generation**
**File:** `generate_invoice_pdf.php` (Lines 23-63)
**Severity:** CRITICAL
**Impact:** Unauthorized access to sensitive payment information

**Issue:**
```php
$payment_id = intval($_GET['payment_id'] ?? 0);
// ... later in code
if ($payment_id > 0) {
    $stmt = $pdo->prepare("
        SELECT sp.*, ts.id as subscription_id
        FROM subscription_payments sp
        LEFT JOIN tenant_subscriptions ts ON sp.subscription_id = ts.id
        WHERE sp.id = ?
    ");
    $stmt->execute([$payment_id]);
    $payment_record = $stmt->fetch(PDO::FETCH_ASSOC);
}
```

**Problem:**
- The script only checks if the user is a super_admin
- Does NOT verify that the super_admin owns/manages the payment being accessed
- A malicious super_admin can access ANY payment record from ANY tenant
- No row-level access control implemented

**Recommendation:**
Add ownership verification:
```php
// After fetching payment, verify ownership
$stmt = $pdo->prepare("
    SELECT sp.*, ts.tenant_id
    FROM subscription_payments sp
    LEFT JOIN tenant_subscriptions ts ON sp.subscription_id = ts.id
    WHERE sp.id = ? AND ts.tenant_id IN (
        SELECT id FROM tenants WHERE created_by = ? OR owner_id = ?
    )
");
$stmt->execute([$payment_id, $_SESSION['user_id'], $_SESSION['user_id']]);
```

---

### 2. **CRITICAL: SQL Injection in Backup Management (PDO Fallback)**
**File:** `backup_management.php` (Lines 188-193)
**Severity:** CRITICAL
**Impact:** Database compromise, data extraction

**Issue:**
```php
foreach ($tables as $table) {
    // Get table structure
    $create_table = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
    // Get table data
    $stmt = $pdo->query("SELECT * FROM `{$table}`");
```

**Problem:**
- `$table` variable comes directly from `SHOW TABLES` output
- While this is safer than raw input, the backtick escaping can be bypassed
- If table names contain backticks, they can be exploited
- No validation of table names against whitelist

**Recommendation:**
```php
// Validate table name against system tables
$allowed_tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?", [$dbname])->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    if (!in_array($table, $allowed_tables)) {
        error_log("Invalid table name attempted: " . $table);
        continue;
    }
    // Use prepared statements or properly quoted identifiers
    $create_table = $pdo->query("SHOW CREATE TABLE " . DbSecurity::sanitizeIdentifier($table))->fetch(PDO::FETCH_NUM)[1];
```

---

### 3. **HIGH: Weak File Upload Validation**
**File:** `file_browser.php` (Lines 155-215)
**Severity:** HIGH  
**Impact:** Arbitrary file upload, potential RCE

**Issue:**
```php
// Whitelist characters only (alphanumeric, underscore, dash, dot)
if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $fileName)) {
    $errors[] = "Invalid characters in: " . htmlspecialchars($fileName);
    continue;
}

// No file type validation - only checking filename pattern
```

**Problems:**
- No MIME type validation
- No file extension whitelist
- Can upload `.php`, `.phtml`, `.php3` files by changing upload folder location
- `$currentDir` validation uses `realpath()` which can behave unpredictably on Windows
- No virus/malware scanning
- File permissions set to 0644 (readable by web server - dangerous)

**Recommendation:**
```php
// Define whitelist of allowed extensions
$allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip'];
$file_ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($file_ext, $allowed_extensions)) {
    $errors[] = "File type not allowed: " . htmlspecialchars($file_ext);
    continue;
}

// Validate MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $tmpPath);
finfo_close($finfo);

$allowed_mimes = [
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'image/jpeg' => 'jpg',
    'image/png' => 'png'
];

if (!isset($allowed_mimes[$mime_type])) {
    $errors[] = "Invalid file MIME type: " . htmlspecialchars($mime_type);
    continue;
}
```

---

### 4. **HIGH: Missing CSRF Protection on PDF Generation**
**File:** `generate_invoice_pdf.php`
**Severity:** HIGH
**Impact:** Cross-site request forgery, unauthorized PDF generation

**Issue:**
```php
$payment_id = intval($_GET['payment_id'] ?? 0);
```

**Problem:**
- GET parameter used for sensitive operation
- No CSRF token validation
- Attacker can craft link: `<img src="generate_invoice_pdf.php?payment_id=999" />`
- Would generate and download sensitive PDFs without user interaction

**Recommendation:**
```php
// Verify CSRF token and use POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
    !isset($_POST['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    die("Invalid request");
}

$payment_id = intval($_POST['payment_id'] ?? 0);
```

---

### 5. **HIGH: Directory Traversal in File Browser**
**File:** `file_browser.php` (Lines 77-84)
**Severity:** HIGH  
**Impact:** Access to sensitive files outside upload directory

**Issue:**
```php
$realUploadsPath = realpath($uploadsDir);
$requestedPath = realpath($currentDir);

if ($requestedPath === false || strpos($requestedPath, $realUploadsPath) !== 0) {
    // Directory traversal attempt or invalid directory
    $currentDir = $uploadsDir;
    $currentFolder = '';
}
```

**Problems:**
- `realpath()` returns `false` if path doesn't exist yet
- On Windows, path separator inconsistency (forward slash vs backslash)
- `strpos()` can match partial paths: `/uploads_backup/` contains `/uploads/`
- Symlink traversal not prevented

**Recommendation:**
```php
function validateUploadPath($requested_path, $base_path) {
    // Normalize paths
    $base_path = str_replace('\\', '/', realpath($base_path));
    $requested_normalized = str_replace('\\', '/', $requested_path);
    
    // Must start with base path AND have path separator after
    if (strpos($requested_normalized, $base_path) !== 0) {
        return false;
    }
    
    $remainder = substr($requested_normalized, strlen($base_path));
    if ($remainder && $remainder[0] !== '/') {
        return false;
    }
    
    // Check for directory traversal patterns
    if (strpos($remainder, '..') !== false) {
        return false;
    }
    
    return true;
}
```

---

## High-Risk Issues

### 6. **HIGH: Weak Input Validation in User Management**
**File:** `edit_user.php`, `manage_users.php`
**Severity:** HIGH
**Impact:** Role escalation, unauthorized privilege changes

**Issue:**
```php
if (!in_array($role, ['super_admin', 'tenant_admin', 'user'])) {
    $errors[] = "Invalid role.";
}
```

**Problems:**
- Role validation only checks string list
- No verification that current user can assign that role
- No verification that user isn't elevating own privileges
- No audit trail for role changes
- No approval workflow for privilege escalation

**Recommendation:**
```php
// Define role hierarchy
$role_hierarchy = [
    'user' => 0,
    'tenant_admin' => 1,
    'super_admin' => 2
];

// Only allow downgrade or same level
if ($role_hierarchy[$role] > $role_hierarchy[$_SESSION['role']]) {
    $errors[] = "Cannot assign higher privilege level than your own.";
}

// Log all privilege changes
if ($new_role !== $old_role) {
    error_log("PRIVILEGE_CHANGE: User {$_SESSION['user_id']} changed {$user_id}'s role from {$old_role} to {$new_role}");
}
```

---

### 7. **MEDIUM: No Rate Limiting on Sensitive Operations**
**Files:** All super_admin/*.php
**Severity:** MEDIUM
**Impact:** Brute force attacks, DoS

**Issue:**
- No rate limiting on login attempts
- No rate limiting on API calls
- No throttling on file downloads/backup operations
- No limits on backup size or frequency

**Recommendation:**
Implement rate limiting:
```php
function checkRateLimit($key, $max_attempts, $time_window) {
    $cache_key = "rate_limit_" . $key;
    $attempts = apcu_fetch($cache_key) ?: 0;
    
    if ($attempts >= $max_attempts) {
        http_response_code(429);
        die("Rate limit exceeded. Try again later.");
    }
    
    apcu_store($cache_key, $attempts + 1, $time_window);
}

// In backup handler
checkRateLimit('backup_' . $_SESSION['user_id'], 5, 3600); // 5 backups per hour
```

---

### 8. **MEDIUM: Insufficient Logging and Monitoring**
**Files:** All super_admin/*.php
**Severity:** MEDIUM  
**Impact:** Audit trail gaps, detection of attacks

**Issues:**
- Backup operations not logged
- File operations not logged with full paths
- No alerting on suspicious activities
- Log files not protected from deletion
- No integration with SIEM

**Recommendation:**
```php
function logSuperAdminAction($action, $entity_type, $entity_id, $details, $severity = 'INFO') {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'action' => $action,
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'severity' => $severity
    ];
    
    // Log to database AND syslog
    error_log(json_encode($log_entry), 0);
    syslog(LOG_WARNING, "SUPER_ADMIN: " . json_encode($log_entry));
}

// Log all backup operations
logSuperAdminAction('backup_created', 'database', null, [
    'file' => $filename,
    'size' => filesize($abs_path),
    'duration' => time() - $start_time
], 'WARNING');
```

---

## Medium-Risk Issues

### 9. **MEDIUM: Inconsistent Authorization Checks**
**Files:** Multiple files
**Severity:** MEDIUM

**Issue:**
Authorization checks vary across files:
- Some check `$_SESSION['role']`
- Some check `!is_null($_SESSION['tenant_id'])`  
- Some rely on implicit route protection
- No consistent authorization framework

**Recommendation:**
Create centralized auth check:
```php
// In includes/auth.php
function requireSuperAdmin() {
    if (!isset($_SESSION['user_id']) || 
        $_SESSION['role'] !== 'super_admin' || 
        !is_null($_SESSION['tenant_id'])) {
        error_log("Unauthorized access attempt: " . $_SERVER['REQUEST_URI']);
        header('Location: ../login.php');
        exit();
    }
}

// Use in all super_admin files
require_once '../includes/auth.php';
requireSuperAdmin();
```

---

### 10. **MEDIUM: Sensitive Data Exposure in Logs**
**File:** `backup_management.php` (Line 237)
**Severity:** MEDIUM
**Impact:** Exposure of database credentials

**Issue:**
```php
error_log("Backup Creation Error: " . $e->getMessage());
```

**Problem:**
- PDOException messages may contain database host/username
- Password might be visible in error messages
- Logs stored in accessible location

**Recommendation:**
```php
try {
    // ...
} catch (PDOException $e) {
    // Log safely without exposing credentials
    $safe_message = "Database connection failed";
    if (strpos($e->getMessage(), 'SQLSTATE') !== false) {
        $safe_message = "Database query failed";
    }
    error_log($safe_message . " [Full error logged to secure location]");
    
    // Log full error to non-web-accessible file
    file_put_contents('/var/log/secure/mtravels_errors.log', 
        date('Y-m-d H:i:s') . " | " . $e->getMessage() . "\n", 
        FILE_APPEND
    );
}
```

---

### 11. **MEDIUM: No Session Fixation Protection**
**Files:** Multiple files
**Severity:** MEDIUM
**Impact:** Session hijacking

**Issue:**
- Session ID not regenerated on privilege elevation
- No session fingerprinting (User-Agent, IP validation)
- Session timeout implemented but not enforced consistently

**Recommendation:**
```php
// After successful login/auth
session_regenerate_id(true);

// Add session fingerprinting
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
}

if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    session_destroy();
    die("Session validation failed");
}
```

---

## Low-Risk Issues

### 12. **LOW: Missing Security Headers Inconsistency**
**Files:** Some files have headers, some don't
**Severity:** LOW

**Issues:**
- `generate_invoice_pdf.php` missing security headers
- `file_browser.php` missing CSP header
- CSP header allows `'unsafe-inline'` for scripts

**Recommendation:**
Move headers to centralized include:
```php
// includes/secure_headers.php
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");  // Or DENY
header("X-Permitted-Cross-Domain-Policies: none");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
```

---

## Summary Table

| # | Vulnerability | File | Severity | Fix Complexity |
|---|---|---|---|---|
| 1 | IDOR in PDF Generation | generate_invoice_pdf.php | CRITICAL | Medium |
| 2 | SQL Injection (PDO Backup) | backup_management.php | CRITICAL | High |
| 3 | Weak File Upload Validation | file_browser.php | HIGH | Medium |
| 4 | Missing CSRF on Sensitive GET | generate_invoice_pdf.php | HIGH | Low |
| 5 | Directory Traversal | file_browser.php | HIGH | Medium |
| 6 | Weak Role Validation | edit_user.php, manage_users.php | HIGH | Low |
| 7 | No Rate Limiting | All | MEDIUM | Medium |
| 8 | Insufficient Logging | All | MEDIUM | High |
| 9 | Inconsistent Auth Checks | All | MEDIUM | Medium |
| 10 | Sensitive Data in Logs | backup_management.php | MEDIUM | Low |
| 11 | No Session Fixation Protection | All | MEDIUM | Low |
| 12 | Missing Security Headers | Multiple | LOW | Low |

---

## Immediate Action Items (Priority Order)

### **Week 1 - CRITICAL:**
1. Fix IDOR vulnerability in `generate_invoice_pdf.php` - Add ownership verification
2. Fix SQL Injection in `backup_management.php` - Use sanitized table names
3. Add CSRF protection to `generate_invoice_pdf.php` - Convert to POST

### **Week 2 - HIGH:**
4. Enhance file upload validation - Add MIME type checking and whitelist
5. Fix directory traversal - Improve path validation logic
6. Strengthen role validation - Add role hierarchy checks

### **Week 3 - MEDIUM:**
7. Implement rate limiting on sensitive operations
8. Add comprehensive audit logging
9. Create centralized auth module
10. Implement session fixation protection

### **Week 4 - LOW:**
11. Standardize security headers across all files
12. Review and sanitize error messages
13. Secure log file storage

---

## Testing Recommendations

After fixes are applied:

1. **OWASP ZAP Scanning** - Full automatic security scan
2. **Manual Penetration Testing** - Focus on authorization checks
3. **SQL Injection Testing** - Using SQLMap on all dynamic queries
4. **File Upload Testing** - Try uploading various file types
5. **Session Testing** - Verify fixation and hijacking protections
6. **Authorization Testing** - Try accessing resources as different roles

---

## Compliance Notes

These vulnerabilities may violate:
- **OWASP Top 10:** A01:2021 - Broken Access Control, A02:2021 - Cryptographic Failures, A03:2021 - Injection
- **CWE:** CWE-639 (Authorization), CWE-89 (SQL Injection), CWE-22 (Path Traversal)
- **PCI-DSS:** 6.5.1 (Injection), 6.5.8 (Broken Access Control), 6.5.9 (File Upload)
- **GDPR:** Articles 5, 32, 33 (Data protection and breach notification)

---

**Generated:** 2026-02-07  
**Auditor:** Security Review System
**Next Review:** 2026-03-07 (After remediation)
