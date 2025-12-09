# Security Audit Report - MTravels SaaS Platform

**Date:** December 9, 2025  
**Status:** Multiple Critical Vulnerabilities Identified  
**Risk Level:** 🔴 CRITICAL

---

## Executive Summary

A comprehensive security audit of the MTravels SaaS platform has identified **15+ significant security vulnerabilities** ranging from critical to medium severity. The most critical issues involve SQL injection vulnerabilities, unauthenticated webhook handlers, disabled CSRF protection, and weak file upload validation.

**Immediate action required** to prevent data breaches, unauthorized access, and system compromise.

---

## Table of Contents

1. [Critical Vulnerabilities](#critical-vulnerabilities)
2. [High Priority Issues](#high-priority-issues)
3. [Medium Priority Issues](#medium-priority-issues)
4. [Authentication & Session Issues](#authentication--session-issues)
5. [File Upload Vulnerabilities](#file-upload-vulnerabilities)
6. [API Security Issues](#api-security-issues)
7. [Data Exposure Issues](#data-exposure-issues)
8. [Remediation Roadmap](#remediation-roadmap)

---

## CRITICAL VULNERABILITIES

### 1. SQL Injection in Admin Supplier Query

**File:** `admin/ajax/get_suppliers.php:21`  
**Severity:** 🔴 CRITICAL  
**CVSS Score:** 9.8

**Vulnerable Code:**
```php
$result = $conn->query("SELECT id, name, currency FROM suppliers 
                       WHERE tenant_id = $tenant_id AND branch_id = $branch_id");
```

**Issue:**
- Direct string concatenation with user session variables
- No prepared statements or parameterization
- Attacker can manipulate `$tenant_id` or `$branch_id` through session hijacking

**Impact:**
- Unauthorized data access
- Data modification/deletion
- Potential privilege escalation

**Remediation:**
```php
$stmt = $conn->prepare("SELECT id, name, currency FROM suppliers 
                       WHERE tenant_id = ? AND branch_id = ?");
$stmt->bind_param("ii", $tenant_id, $branch_id);
$stmt->execute();
$result = $stmt->get_result();
```

---

### 2. SQL Injection with Dynamic Table Names

**File:** `admin/ajax/get_passenger_data.php:49, 55`  
**Severity:** 🔴 CRITICAL  
**CVSS Score:** 9.8

**Vulnerable Code:**
```php
$tableExists = $conn->query("SHOW TABLES LIKE '$tableName'")->num_rows > 0;
// Line 55:
$stmt = $conn->prepare("SELECT * FROM $tableName WHERE passenger_name LIKE ? ...");
```

**Issues:**
- Table names dynamically concatenated without validation
- `$tableName` comes from unvalidated loop variable
- Even though line 55 uses prepared statement, table name is still directly interpolated

**Impact:**
- SQL injection via table name manipulation
- Access to arbitrary tables
- Information leakage

**Remediation:**
```php
$allowed_tables = ['tickets', 'ticket_bookings', 'ticket', 'bookings'];

if (!in_array($tableName, $allowed_tables, true)) {
    continue; // Whitelist approach
}

// For dynamic queries, use identifier quoting:
$stmt = $conn->prepare("SELECT * FROM `" . $conn->real_escape_string($tableName) . "` 
                       WHERE passenger_name LIKE ? AND tenant_id = ? AND branch_id = ?");
```

---

### 3. Unauthenticated WhatsApp Webhook Handler

**File:** `api/whatsapp/index.php:510-632`  
**Severity:** 🔴 CRITICAL  
**CVSS Score:** 9.9

**Vulnerable Code:**
```php
// Line 510-512
if (isset($_GET['webhook'])) {
    handleWebhook();  // No authentication check!
    exit;
}

// Line 624 & 646 - Hardcoded tenant ID
function handleReceivedMessage($data) {
    global $pdo;
    $tenant_id = 1; // Hardcoded! Should identify dynamically
    // ... inserts webhook data without verification
}
```

**Issues:**
- Webhook endpoint accessible to anyone on the internet
- No signature verification from WhatsApp provider
- No authentication checks
- Hardcoded `tenant_id = 1` means all webhooks affect first tenant
- Raw webhook data inserted into database without validation

**Impact:**
- Malicious webhook injection
- Denial of service attacks
- Data corruption
- Cross-tenant data poisoning

**Remediation:**
```php
function handleWebhook() {
    // 1. Verify webhook signature from WhatsApp
    $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
    $payload = file_get_contents('php://input');
    
    if (!verifyWebhookSignature($payload, $signature, WHATSAPP_WEBHOOK_SECRET)) {
        http_response_code(401);
        exit('Unauthorized');
    }
    
    // 2. Identify tenant from phone number or webhook metadata
    $data = json_decode($payload, true);
    $tenant_id = identifyTenantFromWebhook($data);
    
    if (!$tenant_id) {
        http_response_code(400);
        exit('Invalid webhook data');
    }
    
    // 3. Validate data before processing
    validateWebhookData($data);
    
    // 4. Process webhook
    processWebhookData($data, $tenant_id);
}
```

---

### 4. Disabled CSRF Protection in Payment Handler

**File:** `api/additional_payment/update_additional_payment_base.php`  
**Severity:** 🔴 CRITICAL  
**CVSS Score:** 8.7

**Vulnerable Code:**
```php
// Lines 33, 38 (commented out)
// if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
//     die('CSRF token validation failed');
// }
```

**Issues:**
- CSRF protection explicitly disabled/commented out
- Payment operations unprotected against cross-site request forgery
- Critical financial transactions vulnerable

**Impact:**
- Unauthorized payment modifications
- Financial loss
- Account takeover scenarios

**Remediation:**
```php
// Enable CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || 
        !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die(json_encode(['error' => 'CSRF token validation failed']));
    }
}
```

---

## HIGH PRIORITY ISSUES

### 5. SQL Injection in Dynamic WHERE Clauses

**File:** `api/whatsapp/index.php:294-310`  
**Severity:** 🟠 HIGH  
**CVSS Score:** 8.6

**Vulnerable Code:**
```php
$where_clause = implode(' AND ', $where_conditions);

$stmt = $GLOBALS['pdo']->prepare("
    SELECT * FROM whatsapp_messages 
    WHERE $where_clause 
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset
");
```

**Issues:**
- `$limit` and `$offset` directly interpolated into SQL
- While `$where_clause` is built with bound parameters, `LIMIT`/`OFFSET` are not
- Integer injection possible

**Impact:**
- Unauthorized message access
- Data leakage
- Pagination bypass

**Remediation:**
```php
// Ensure limit and offset are integers
$limit = (int)$limit;
$offset = (int)$offset;

// Validate ranges
if ($limit < 1 || $limit > 1000) $limit = 50;
if ($offset < 0) $offset = 0;

$stmt = $GLOBALS['pdo']->prepare("
    SELECT * FROM whatsapp_messages 
    WHERE $where_clause 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
```

---

### 6. Unauthenticated API Access in WhatsApp Manager

**File:** `api/whatsapp/WhatsAppManager.php`  
**Severity:** 🟠 HIGH  
**CVSS Score:** 8.2

**Issues:**
- API tokens stored in plaintext in database
- Bearer tokens exposed in HTTP headers
- No token rotation mechanism
- No rate limiting per API key

**Impact:**
- Token theft from logs/monitoring systems
- Unauthorized API access
- Account compromise

**Remediation:**
```php
// 1. Hash API tokens in database
$hashed_token = password_hash($api_token, PASSWORD_BCRYPT);

// 2. Use environment variables for production tokens
$api_token = getenv('WHATSAPP_API_TOKEN');

// 3. Implement token rotation
function rotateApiToken($tenant_id) {
    $new_token = bin2hex(random_bytes(32));
    $hashed = password_hash($new_token, PASSWORD_BCRYPT);
    // Update database
    // Return new token to user once
}

// 4. Use Authorization header properly
$headers = [
    'Authorization: Bearer ' . $api_token,
    'Content-Type: application/json'
];
```

---

### 7. Path Traversal in File Operations

**File:** `admin/customer_detail.php:98-99, 181-182`  
**Severity:** 🟠 HIGH  
**CVSS Score:** 8.3

**Vulnerable Code:**
```php
// File extension extracted without proper validation
$file_extension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
$target_file = $upload_dir . basename($_FILES['file']['name']);
```

**Issues:**
- `basename()` not sufficient against path traversal
- No validation of actual file content
- Attacker can upload files with path traversal sequences in filename
- No file size limits enforced

**Impact:**
- Arbitrary file upload to any directory
- Remote code execution potential
- Server compromise

**Remediation:**
```php
function validateFileUpload($file) {
    // 1. Whitelist allowed extensions
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions, true)) {
        throw new Exception('File type not allowed');
    }
    
    // 2. Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'image/jpeg', 'image/png', 'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    if (!in_array($mime_type, $allowed_mimes, true)) {
        throw new Exception('Invalid file content');
    }
    
    // 3. Check file size
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        throw new Exception('File too large');
    }
    
    // 4. Generate safe filename
    $safe_filename = uniqid('file_') . '.' . $file_extension;
    
    // 5. Validate upload path is within designated directory
    $upload_dir = realpath(__DIR__ . '/uploads/');
    $target_file = $upload_dir . DIRECTORY_SEPARATOR . $safe_filename;
    $target_realpath = realpath(dirname($target_file)) . DIRECTORY_SEPARATOR . basename($target_file);
    
    if (strpos($target_realpath, $upload_dir) !== 0) {
        throw new Exception('Invalid upload path');
    }
    
    return $target_file;
}
```

---

### 8. Weak File Upload Validation in Multiple Handlers

**Files with Issues:**
- `super_admin/file_browser.php:155`
- `tenant_super_admin/updateSettings.php:51`
- `admin/assets.php`
- `admin/sarafi.php`

**Severity:** 🟠 HIGH  
**CVSS Score:** 8.0

**Issues:**
- Extension-only validation (easily spoofed)
- No actual MIME type verification
- No file size limits
- No scan for malware
- Direct filename usage without sanitization

**Impact:**
- Malicious file upload
- Remote code execution
- Website defacement
- Data theft

**Remediation:**
See detailed remediation in Issue #7 above.

---

## MEDIUM PRIORITY ISSUES

### 9. Missing Content Security Policy (CSP)

**File:** `admin/security.php:34-35`  
**Severity:** 🟡 MEDIUM  
**CVSS Score:** 6.1

**Vulnerable Code:**
```php
// Temporarily disabled CSP header
// header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; ...");
```

**Issues:**
- CSP explicitly disabled
- Inline scripts allowed (vulnerable to XSS)
- No protection against style injection

**Impact:**
- Cross-site scripting attacks
- Code injection
- Data theft via XSS

**Remediation:**
```php
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self';");
```

---

### 10. Hardcoded Database Fallback

**File:** `config.php:3-6`  
**Severity:** 🟡 MEDIUM  
**CVSS Score:** 5.9

**Vulnerable Code:**
```php
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');  // Empty fallback!
```

**Issues:**
- Empty password fallback if environment variable not set
- Could lead to unexpected database access
- Not secure by default principle

**Impact:**
- Unintended database access
- Missing security configuration
- Hard to debug production issues

**Remediation:**
```php
$db_password = getenv('DB_PASSWORD');
if ($db_password === false) {
    die("ERROR: DB_PASSWORD environment variable not set. Please configure server environment.");
}
define('DB_PASSWORD', $db_password);
```

---

### 11. Information Disclosure via Error Logs

**File:** Multiple locations using `error_log()`  
**Severity:** 🟡 MEDIUM  
**CVSS Score:** 5.3

**Issues:**
- Detailed error messages logged to server error logs
- Stack traces may contain sensitive information
- Log files may be accessible to attackers
- Database connection errors logged with connection details

**Impact:**
- Information leakage about system architecture
- Exposure of file paths
- Potential credential exposure in error messages

**Remediation:**
```php
// Use generic messages for users
throw new Exception("An error occurred. Please contact support.");

// Log detailed information to separate secure log file
function logSecurely($message, $level = 'error') {
    $log_file = '/var/log/mtravels.log'; // Outside web root
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message\n";
    error_log($log_entry, 3, $log_file);
}
```

---

### 12. Session Timeout Configuration

**File:** `admin/security.php:47`, `includes/session_check.php`  
**Severity:** 🟡 MEDIUM  
**CVSS Score:** 5.7

**Current Implementation:**
```php
$sessionTimeout = 30 * 60; // 30 minutes
```

**Issues:**
- 30 minutes is quite long for sensitive financial operations
- No warning before session expires
- No inactivity logging

**Remediation:**
```php
// Implement tiered approach
$sessionTimeout = 15 * 60; // 15 minutes for sensitive pages
$warningTime = 10 * 60;    // Warn user at 5 minutes remaining

if (isset($_SESSION['last_activity'])) {
    $inactive_duration = time() - $_SESSION['last_activity'];
    
    if ($inactive_duration > $sessionTimeout) {
        session_unset();
        session_destroy();
        header('Location: ../login.php?timeout=1');
        exit;
    }
    
    // Show warning at 5 minutes remaining
    if ($inactive_duration > ($sessionTimeout - $warningTime)) {
        $_SESSION['session_warning'] = true;
    }
}
```

---

### 13. SMTP Password Exposure

**File:** `includes/functions.php:22-25`  
**Severity:** 🟡 MEDIUM  
**CVSS Score:** 5.8

**Vulnerable Code:**
```php
$mail->Host = $smtpSettings['smtp_host'];
$mail->SMTPAuth = true;
$mail->Username = $smtpSettings['smtp_username'];
$mail->Password = $smtpSettings['smtp_password'];  // From database!
```

**Issues:**
- SMTP passwords stored in database
- Retrieved and used in plaintext
- Could be exposed in logs/backups
- No encryption for stored credentials

**Impact:**
- Email account compromise
- Unauthorized email sending
- Spam abuse
- Email spoofing

**Remediation:**
```php
// 1. Use environment variables for platform SMTP
define('SMTP_HOST', getenv('SMTP_HOST'));
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD'));

// 2. Encrypt stored tenant SMTP passwords
function encryptSMTPPassword($password) {
    return openssl_encrypt($password, 'aes-256-cbc', ENCRYPTION_KEY, false, ENCRYPTION_IV);
}

function decryptSMTPPassword($encrypted) {
    return openssl_decrypt($encrypted, 'aes-256-cbc', ENCRYPTION_KEY, false, ENCRYPTION_IV);
}

// 3. Never log credentials
$mail->Password = decryptSMTPPassword($smtpSettings['smtp_password']);
// Avoid logging $smtpSettings after this point
```

---

### 14. Missing Rate Limiting on Critical Endpoints

**File:** `admin/security.php:163-195`  
**Severity:** 🟡 MEDIUM  
**CVSS Score:** 5.4

**Issues:**
- Rate limiting exists but session-based (not persistent)
- Can be bypassed by clearing session
- No database-backed rate limiting
- No IP-based rate limiting

**Impact:**
- Brute force attacks on login
- API abuse
- Denial of service

**Remediation:**
```php
// Implement Redis/Database backed rate limiting
function checkRateLimit($identifier, $limit = 30, $window = 60) {
    global $pdo;
    
    $now = time();
    $window_start = $now - $window;
    
    // Clean old entries
    $pdo->prepare("DELETE FROM rate_limit WHERE identifier = ? AND timestamp < ?")->execute([$identifier, $window_start]);
    
    // Count recent attempts
    $stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM rate_limit WHERE identifier = ? AND timestamp > ?");
    $stmt->execute([$identifier, $window_start]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['attempts'] >= $limit) {
        return false;
    }
    
    // Record this attempt
    $pdo->prepare("INSERT INTO rate_limit (identifier, timestamp) VALUES (?, ?)")->execute([$identifier, $now]);
    
    return true;
}
```

---

### 15. Inadequate Input Validation in Search Functions

**File:** `admin/search.php:68-326`  
**Severity:** 🟡 MEDIUM  
**CVSS Score:** 6.2

**Issues:**
- Dynamic LIKE queries with pattern matching
- Potential for logic bypass despite prepared statements
- Complex WHERE clause construction with OR operators
- Search parameters not validated for length/format

**Impact:**
- Unexpected query results
- Information leakage through error messages
- Potential bypass of access controls

**Remediation:**
```php
function validateSearchInput($search_term) {
    // 1. Check length
    if (strlen($search_term) > 255) {
        throw new Exception("Search term too long");
    }
    
    // 2. Check for SQL wildcard abuse
    $search_term = str_replace(['%', '_'], '\%', '_'], $search_term);
    
    // 3. Limit special characters
    if (!preg_match('/^[a-zA-Z0-9\s\-\.@,]*$/', $search_term)) {
        throw new Exception("Invalid search term format");
    }
    
    return trim($search_term);
}
```

---

## AUTHENTICATION & SESSION ISSUES

### 16. Session Fixation Risk

**File:** `php_login.php:20-23`  
**Status:** Partially Mitigated

**Current Implementation:**
```php
if (!isset($_SESSION["last_regeneration"]) || (time() - $_SESSION["last_regeneration"] > 300)) {
    session_regenerate_id(true);
    $_SESSION["last_regeneration"] = time();
}
```

**Remaining Issues:**
- Regeneration only every 5 minutes
- Should regenerate on every privilege elevation
- Should regenerate after authentication

**Recommendation:**
```php
// Regenerate immediately after login
function completeLogin() {
    // ... set user data ...
    
    // CRITICAL: Regenerate session immediately
    session_regenerate_id(true);
    $_SESSION["authenticated"] = true;
    $_SESSION["login_time"] = time();
}
```

---

### 17. Weak TOTP Implementation

**File:** `includes/totp_helper.php`  
**Severity:** 🟡 MEDIUM  
**CVSS Score:** 5.5

**Issues:**
- Recovery codes stored without hashing
- No backup code rotation
- No lockout after multiple failed TOTP attempts
- No audit logging for 2FA failures

**Remediation:**
```php
// Hash recovery codes
function storeRecoveryCodes($user_id, $codes) {
    global $pdo;
    
    foreach ($codes as $code) {
        $hashed = password_hash($code, PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO recovery_codes (user_id, code_hash, used) VALUES (?, ?, 0)")
            ->execute([$user_id, $hashed]);
    }
}

// Track failed TOTP attempts
function recordFailedTOTPAttempt($user_id) {
    global $pdo;
    $pdo->prepare("UPDATE totp_settings SET failed_attempts = failed_attempts + 1, last_attempt = NOW() WHERE user_id = ?")
        ->execute([$user_id]);
    
    // Lock after 5 failed attempts
    $stmt = $pdo->prepare("SELECT failed_attempts FROM totp_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['failed_attempts'] >= 5) {
        $pdo->prepare("UPDATE totp_settings SET locked = 1 WHERE user_id = ?")
            ->execute([$user_id]);
        throw new Exception("Too many failed attempts. Please contact support.");
    }
}
```

---

## FILE UPLOAD VULNERABILITIES

### 18. Multiple Unprotected Upload Endpoints

**Affected Files:**
- `api/upload.php` - Chat file uploads
- `api/expense/expense_actions.php` - Receipt uploads
- `api/employee/edit_employee.php` - Document uploads
- `admin/customer_detail.php` - Transaction receipts
- `super_admin/manage_testimonials.php` - Photo uploads
- `super_admin/create_blog_post.php` - Featured images

**Severity:** 🟠 HIGH  
**CVSS Score:** 8.1

**Common Issues:**
1. Extension-only validation
2. No MIME type verification
3. Files stored in web-accessible directories
4. No file size enforcement
5. Original filenames used (information disclosure)
6. No antivirus scanning

**Comprehensive Remediation:**

Create a centralized file upload handler:

```php
// includes/FileUploadValidator.php
class FileUploadValidator {
    private $allowed_extensions = [];
    private $allowed_mimes = [];
    private $max_size;
    private $upload_dir;
    
    public function __construct($type = 'document') {
        $this->max_size = 5 * 1024 * 1024; // 5MB default
        
        switch ($type) {
            case 'image':
                $this->allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                $this->allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
                $this->upload_dir = '/uploads/images/';
                break;
            case 'document':
                $this->allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
                $this->allowed_mimes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $this->upload_dir = '/uploads/documents/';
                break;
            case 'receipt':
                $this->allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
                $this->allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf'];
                $this->upload_dir = '/uploads/receipts/';
                break;
        }
    }
    
    public function validate($file) {
        // Check file exists
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Invalid file upload');
        }
        
        // Check size
        if ($file['size'] > $this->max_size) {
            throw new Exception('File exceeds maximum size');
        }
        
        // Check extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowed_extensions, true)) {
            throw new Exception('File type not allowed');
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $this->allowed_mimes, true)) {
            throw new Exception('Invalid file content');
        }
        
        return true;
    }
    
    public function save($file, $custom_name = null) {
        $this->validate($file);
        
        // Generate safe filename
        $filename = $custom_name ?: uniqid('file_') . '.' . strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $target = realpath(__DIR__ . '/..' . $this->upload_dir) . DIRECTORY_SEPARATOR . $filename;
        
        // Verify path is within upload directory
        if (strpos(realpath(dirname($target)), realpath(__DIR__ . '/..' . $this->upload_dir)) !== 0) {
            throw new Exception('Invalid upload path');
        }
        
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new Exception('Failed to save file');
        }
        
        return $this->upload_dir . $filename;
    }
}
```

---

## API SECURITY ISSUES

### 19. Missing Authorization Checks

**File:** Multiple API endpoints  
**Severity:** 🟠 HIGH  
**CVSS Score:** 8.2

**Issues:**
- Some endpoints only check session existence, not permissions
- No role-based access control (RBAC) validation
- No tenant isolation verification
- No operation-level authorization

**Affected Endpoints:**
- `api/debtor/debtors_handler.php`
- `api/creditor/creditor_handler.php`
- `api/additional_payment/*`

**Remediation:**
```php
class AuthorizationMiddleware {
    public static function requireRole($allowed_roles = []) {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
            http_response_code(401);
            die(json_encode(['error' => 'Unauthorized']));
        }
        
        if (!in_array($_SESSION['role'], $allowed_roles, true)) {
            http_response_code(403);
            die(json_encode(['error' => 'Insufficient permissions']));
        }
    }
    
    public static function verifyTenantOwnership($resource_id, $resource_type) {
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT tenant_id FROM {$resource_type} WHERE id = ?");
        $stmt->execute([$resource_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || $result['tenant_id'] != $_SESSION['tenant_id']) {
            http_response_code(403);
            die(json_encode(['error' => 'Access denied']));
        }
    }
}

// Usage in API:
AuthorizationMiddleware::requireRole(['admin', 'finance']);
AuthorizationMiddleware::verifyTenantOwnership($booking_id, 'bookings');
```

---

## DATA EXPOSURE ISSUES

### 20. Email Tracking Pixel Vulnerability

**File:** `includes/functions.php:68`  
**Severity:** 🟡 MEDIUM  
**CVSS Score:** 5.2

**Vulnerable Code:**
```php
$trackingPixel = "<img src=\"" . getBaseUrl() . "/email_tracking.php?email_id={$emailId}\" ...>";
```

**Issues:**
- Email IDs exposed in plain text
- Could allow tracking user emails
- No token verification in tracking endpoint
- Unencrypted tracking data

**Remediation:**
```php
// Encrypt email tracking tokens
function generateTrackingToken($email_id) {
    $token = hash_hmac('sha256', $email_id, TRACKING_SECRET);
    return substr($token, 0, 32); // Truncate to reasonable length
}

// Tracking pixel with token
$token = generateTrackingToken($emailId);
$trackingPixel = "<img src=\"" . getBaseUrl() . "/email_tracking.php?token={$token}\" ...>";

// Verify in tracking endpoint
if (!hash_equals(generateTrackingToken($email_id), $provided_token)) {
    http_response_code(400);
    exit;
}
```

---

## REMEDIATION ROADMAP

### Phase 1: Immediate Actions (Within 24 Hours)

Priority tasks that should be completed immediately:

1. **Enable CSRF Protection**
   - Uncomment CSRF validation in `api/additional_payment/update_additional_payment_base.php`
   - File: `api/additional_payment/update_additional_payment_base.php`

2. **Secure WhatsApp Webhook**
   - Add authentication check to webhook handler
   - Implement signature verification
   - Remove hardcoded tenant_id
   - File: `api/whatsapp/index.php:510-648`

3. **Fix Critical SQL Injection**
   - Convert direct query to prepared statement in `admin/ajax/get_suppliers.php`
   - File: `admin/ajax/get_suppliers.php:21`

4. **Restrict Admin AJAX Endpoints**
   - Add authentication enforcement to AJAX handlers
   - File: `admin/ajax/*.php`

---

### Phase 2: High Priority Fixes (Within 1 Week)

1. **Convert All Dynamic Queries to Prepared Statements**
   - `admin/ajax/get_passenger_data.php`
   - `api/whatsapp/index.php` (LIMIT/OFFSET)
   - `admin/search.php`
   - Files listed in Issues #2, #5

2. **Implement Secure File Upload System**
   - Create centralized upload validator class
   - Apply to all upload endpoints
   - Files listed in Issue #18

3. **Enable Content Security Policy**
   - Uncomment and configure CSP header
   - File: `admin/security.php:34-35`

4. **Implement Database-Backed Rate Limiting**
   - Replace session-based rate limiting with persistent storage
   - File: `admin/security.php:163-195`

---

### Phase 3: Medium Priority Fixes (Within 1 Month)

1. **Encrypt Sensitive Data**
   - Implement encryption for SMTP passwords
   - Encrypt stored API tokens
   - File: `includes/functions.php`, `api/whatsapp/WhatsAppManager.php`

2. **Strengthen Authentication**
   - Implement TOTP code hashing
   - Add recovery code rotation
   - Add TOTP attempt limiting
   - File: `includes/totp_helper.php`

3. **Improve Session Security**
   - Reduce session timeout to 15 minutes
   - Add pre-expiration warning
   - File: `admin/security.php:47`

4. **Add Comprehensive Logging**
   - Implement secure audit logging
   - Add security event tracking
   - Monitor failed authentication attempts
   - File: `admin/security.php` + new `includes/audit_log.php`

5. **Add Authorization Middleware**
   - Implement role-based access control
   - Add tenant isolation verification
   - File: Create new `includes/AuthorizationMiddleware.php`

---

### Phase 4: Enhanced Security (Ongoing)

1. **Web Application Firewall (WAF)**
   - Deploy ModSecurity or similar
   - Monitor and block malicious patterns

2. **Security Headers**
   - Implement all OWASP recommended headers
   - Add HSTS
   - Add SRI (Subresource Integrity) for external resources

3. **API Security Enhancements**
   - Implement OAuth 2.0 for API authentication
   - Add API rate limiting per key
   - Implement request signing

4. **Vulnerability Management**
   - Regular security assessments
   - Dependency scanning for vendor libraries
   - Penetration testing

---

## Testing Recommendations

### Unit Tests to Add

```php
// tests/SecurityTest.php
class SecurityTest extends TestCase {
    
    public function testSQLInjectionProtection() {
        // Verify prepared statements are used
        // Test with malicious input: ' OR '1'='1
    }
    
    public function testCSRFProtection() {
        // Verify CSRF tokens are validated
        // Test missing/invalid tokens
    }
    
    public function testFileUploadValidation() {
        // Test with various file types
        // Test with oversized files
        // Test path traversal attempts
    }
    
    public function testAuthenticationRequired() {
        // Verify endpoints require authentication
        // Test with invalid session
    }
    
    public function testAuthorizationEnforced() {
        // Verify users can only access their tenant's data
        // Test unauthorized operations
    }
}
```

---

## Compliance Notes

This audit identified vulnerabilities that may violate:

- **OWASP Top 10 2021**
  - A01:2021 – Broken Access Control
  - A02:2021 – Cryptographic Failures
  - A03:2021 – Injection
  - A07:2021 – Cross-Site Scripting (XSS)

- **PCI DSS** (if handling payments)
  - Requirement 1: Secure network
  - Requirement 2: Default security parameters
  - Requirement 6: Secure development
  - Requirement 7: Access control

- **GDPR** (if storing EU user data)
  - Security of processing
  - Data breach notification requirements

---

## Contact & Escalation

For immediate security concerns, contact your security team or hosting provider.

**Security Hotline:** [Add your contact]  
**Report:**  December 9, 2025  
**Auditor:** Amp Security Audit

---

## Appendix: Quick Reference Links

- OWASP Top 10: https://owasp.org/Top10/
- PHP Security: https://www.php.net/manual/en/security.php
- CVSS Calculator: https://www.first.org/cvss/calculator/3.1
- CWE Reference: https://cwe.mitre.org/

---

**END OF REPORT**
