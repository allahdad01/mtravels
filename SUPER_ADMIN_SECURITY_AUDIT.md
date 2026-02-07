# Super Admin Panel - Comprehensive Security Audit Report

**Date:** February 7, 2026  
**Scope:** `/super_admin` directory and related handlers  
**Status:** Detailed audit with critical, high, and medium findings

---

## Executive Summary

The Super Admin panel has **strong foundational security** with proper authentication, CSRF protection, and prepared statements. However, **5 critical and high-priority vulnerabilities** were identified requiring immediate attention.

| Severity | Count | Status |
|----------|-------|--------|
| 🔴 Critical | 2 | Requires immediate fix |
| 🟠 High | 3 | Requires fix before production |
| 🟡 Medium | 4 | Should be addressed |
| 🟢 Low | 3 | Nice to have |

---

## Critical Issues (Fix Immediately)

### 1. 🔴 CRITICAL: Path Traversal in file_browser.php

**Location:** `super_admin/file_browser.php` lines 50-60, 80-90, 145-183

**Risk:** Attackers can delete, upload, or access files outside the uploads directory

**Code:**
```php
$currentDir = $uploadsDir;
if (isset($_GET['dir'])) {
    // No validation of $dir parameter
    $currentDir = $uploadsDir . '/' . $_GET['dir'];
}
// Later: $targetPath = $currentDir . '/' . $fileName;
unlink($fullPath); // Can delete any file
```

**Attack Scenario:**
```
GET /super_admin/file_browser.php?dir=../../../config.php
POST action=delete&path=../../config.php
```

**Fix:**
```php
// Add to file_browser.php at top of handlers
if (isset($_GET['dir'])) {
    $requestedDir = $_GET['dir'];
    
    // Normalize and validate path
    $realUploadsDir = realpath($uploadsDir);
    $fullPath = realpath($uploadsDir . '/' . $requestedDir);
    
    // Ensure path is within uploads directory
    if ($fullPath === false || strpos($fullPath, $realUploadsDir) !== 0) {
        http_response_code(403);
        die('Access denied');
    }
    
    $currentDir = $fullPath;
}
```

**Impact:**
- Unauthorized file access (config.php, database backups)
- Deletion of critical system files
- Code execution via malicious file upload

---

### 2. 🔴 CRITICAL: Command Injection in backup_management.php

**Location:** `super_admin/backup_management.php` lines 92-99

**Risk:** Shell command injection via database credentials

**Vulnerable Code:**
```php
$cmd = sprintf(
    '%s --no-tablespaces -h%s -u%s %s %s > %s', 
    escapeshellcmd($mysqldump), 
    escapeshellarg($host),       // ✓ Safe
    escapeshellarg($user),       // ✓ Safe
    $pass ? '-p' . escapeshellarg($pass) : '',  // ⚠️ VULNERABLE
    escapeshellarg($name),       // ✓ Safe
    escapeshellarg($abs_path)    // ✓ Safe
);
```

**Vulnerability:** While most args are escaped, `-p` flag placement is problematic if password contains special chars. More importantly, the output redirection `> %s` is not properly escaped.

**Attack Scenario:**
If password is `pass" && rm -rf /`, the shell might parse this incorrectly.

**Fix:**
```php
// Use array syntax for safe command execution
$cmd = [
    $mysqldump,
    '--no-tablespaces',
    '-h' . $host,
    '-u' . $user,
    $pass ? '-p' . $pass : '',
    $name,
];

// Use proc_open with pipes instead of shell redirection
$descriptorspec = [
    0 => ['pipe', 'r'],  // stdin
    1 => ['file', $abs_path, 'w'],  // stdout to file
    2 => ['pipe', 'w']   // stderr
];

$process = proc_open(implode(' ', array_map('escapeshellarg', $cmd)), $descriptorspec, $pipes);
if (is_resource($process)) {
    fclose($pipes[0]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $return_value = proc_close($process);
}
```

**Impact:**
- Remote code execution
- Database compromise
- Server-wide file system access

---

### 3. 🟠 HIGH: MIME Type Validation Missing

**Location:**
- `super_admin/create_blog_post.php` lines 75-105
- `super_admin/handlers/create_system_expense.php` lines 40-61
- `super_admin/file_browser.php` lines 145-183

**Risk:** File type spoofing (upload .exe as .jpg)

**Current Code:**
```php
$file_extension = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!in_array($file_extension, $allowed_extensions)) {
    // Reject upload
}
// But extension can be spoofed!
```

**Attack:** Upload malicious.exe renamed as image.jpg → stored as image.jpg → potentially executed

**Fix - Add MIME type validation:**
```php
// Validate MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $_FILES['featured_image']['tmp_name']);
finfo_close($finfo);

$allowed_mimes = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp'
];

if (!in_array($mime_type, $allowed_mimes)) {
    header('Location: manage_blog_posts.php?error=invalid_mime_type');
    exit();
}

// Additionally, verify file is actually an image
$imageinfo = @getimagesize($_FILES['featured_image']['tmp_name']);
if ($imageinfo === false) {
    header('Location: manage_blog_posts.php?error=not_valid_image');
    exit();
}
```

**Impact:**
- Malware distribution
- Server compromise if executable is uploaded to web-accessible path
- Code execution (if uploaded to assets and accessed via browser)

---

## High Priority Issues

### 4. 🟠 HIGH: CSRF Validation Using == Instead of hash_equals

**Location:** `super_admin/handlers/create_system_expense.php` line 11, `delete_system_expense.php` line 9

**Risk:** Timing attack against CSRF tokens

**Current Code:**
```php
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    // Using !== (comparison) not hash_equals()
}
```

**Issue:** `!==` comparison is vulnerable to timing attacks. Attackers can measure response time to brute-force tokens.

**Fix:**
```php
if (!isset($_POST['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'CSRF token validation failed']));
}
```

**Impact:**
- CSRF token brute forcing
- Unauthorized actions on behalf of admin

---

### 5. 🟠 HIGH: File Upload Directory Traversal in Filename

**Location:** `super_admin/file_browser.php` lines 152-169

**Risk:** Filename can contain path separators: `../../../shell.php`

**Current Code:**
```php
$fileName = $uploadedFiles['name'][$i];
$targetPath = $currentDir . '/' . $fileName;

// Only checks for / and \\ in filename
if (preg_match('/[\/\\\\]/', $fileName)) {
    // Reject
}
// But doesn't check for .. (directory traversal)
move_uploaded_file($tmpPath, $targetPath);
```

**Attack:** Upload filename `..%2f..%2fshell.php` → might bypass regex → creates file outside intended directory

**Fix:**
```php
$fileName = $uploadedFiles['name'][$i];

// Remove any path components
$fileName = basename($fileName);

// Whitelist allowed characters
if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $fileName)) {
    $errors[] = "Invalid filename: " . htmlspecialchars($fileName);
    continue;
}

// Generate safe filename
$ext = pathinfo($fileName, PATHINFO_EXTENSION);
$safeFileName = uniqid('upload_') . '.' . $ext;

$targetPath = $currentDir . '/' . $safeFileName;
```

**Impact:**
- Arbitrary file upload
- Directory traversal
- Code execution

---

### 6. 🟠 HIGH: SQL Error Details Leaked in delete_system_expense.php

**Location:** `super_admin/handlers/delete_system_expense.php` lines 102-104

**Risk:** SQL syntax errors exposed to frontend

**Current Code:**
```php
catch (Exception $e) {
    error_log("Delete expense error: " . $e->getMessage());
    exit(json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]));
    // Exposes: "Column 'x' doesn't exist" etc.
}
```

**Fix:**
```php
catch (Exception $e) {
    error_log("Delete expense error: " . $e->getMessage());
    exit(json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']));
    // Never expose details to client
}
```

**Impact:**
- Information disclosure
- Database schema enumeration

---

## Medium Priority Issues

### 7. 🟡 MEDIUM: Duplicate Database Include

**Location:** `super_admin/backup_management.php` lines 30-31

**Code:**
```php
require_once '../includes/db.php';
require_once '../includes/db.php';  // Duplicate!
```

**Fix:** Remove one line

**Impact:** Code maintainability, minor performance issue

---

### 8. 🟡 MEDIUM: No Session Timeout Validation in AJAX Handlers

**Location:** `super_admin/handlers/create_system_expense.php`, `delete_system_expense.php`

**Risk:** AJAX handlers don't check session timeout like page handlers do

**Current:** Missing `$_SESSION['last_activity']` check

**Fix:** Add to each handler:
```php
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Session expired']));
}
$_SESSION['last_activity'] = time();
```

**Impact:** Session security consistency

---

### 9. 🟡 MEDIUM: Weak File Upload Randomization

**Location:** `super_admin/create_blog_post.php` line 96

**Code:**
```php
$filename = uniqid('blog_') . '.' . $file_extension;
// uniqid() generates predictable names
```

**Risk:** File paths are guessable (uniqid() is not cryptographically secure for sensitive use)

**Fix:**
```php
$filename = 'blog_' . bin2hex(random_bytes(16)) . '.' . $file_extension;
```

**Impact:** Low (files aren't directly executable), but improves security

---

### 10. 🟡 MEDIUM: No CSRF Token in file_browser.php DELETE Action

**Location:** `super_admin/file_browser.php` lines 87-95

**Risk:** CSRF token not validated for file deletion

**Current Code:**
```php
if ($_POST['action'] === 'delete') {
    // No CSRF check!
    if (unlink($fullPath)) {
        // File deleted
    }
}
```

**Fix:**
```php
if ($_POST['action'] === 'delete') {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        exit(json_encode(['success' => false, 'message' => 'CSRF validation failed']));
    }
    
    if (unlink($fullPath)) {
        $response['success'] = true;
    }
}
```

**Impact:** CSRF vulnerability for file operations

---

## Low Priority Issues

### 11. 🟢 LOW: Inconsistent Error Handling in create_blog_post.php

**Location:** Lines 68-72

**Code:**
```php
$stmt = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ?");
$stmt->execute([$slug]);
$result = $stmt->get_result();  // ⚠️ get_result() is MySQLi, not PDO
```

**Issue:** `get_result()` is MySQLi method, not PDO. Should be:
```php
$stmt = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ?");
$stmt->execute([$slug]);
if ($stmt->rowCount() > 0) {
    // Slug exists
}
```

**Impact:** Fatal error in execution

---

### 12. 🟢 LOW: Multiple CSP Headers in Admin Pages

**Location:** Multiple files have duplicate CSP headers

**Fix:** All should use global `security.php` as implemented

---

### 13. 🟢 LOW: No Rate Limiting on File Uploads

**Location:** `super_admin/file_browser.php`

**Risk:** Attackers can upload unlimited files

**Fix:** Integrate rate limiting:
```php
// At top of file upload handler
enforce_rate_limit('file_upload', 10, 60);  // 10 files per minute
```

---

## Security Best Practices Implemented ✓

- ✓ Session-based authentication with timeout
- ✓ CSRF token generation with `random_bytes()`
- ✓ Prepared statements (mostly)
- ✓ XSS prevention with `htmlspecialchars()`
- ✓ Secure logging with sensitive field masking
- ✓ File upload size limits
- ✓ Audit logging for critical actions
- ✓ IP address logging
- ✓ Error logging without exposing details

---

## Remediation Priority

### Phase 1 (Critical - Within 48 hours)
1. Fix path traversal in `file_browser.php` - Use `realpath()` validation
2. Fix command injection in `backup_management.php` - Use `proc_open()`
3. Add MIME type validation to all file uploads

### Phase 2 (High - Within 1 week)
4. Replace `!==` with `hash_equals()` in CSRF validation
5. Fix filename sanitization (remove `..` patterns)
6. Stop exposing error details to clients
7. Add CSRF token validation to file operations
8. Add session timeout checks to AJAX handlers

### Phase 3 (Medium - Within 2 weeks)
9. Fix MySQLi/PDO inconsistencies
10. Add rate limiting to file operations
11. Improve file randomization (use `random_bytes()`)
12. Consolidate CSP headers

---

## Testing Recommendations

### Automated Testing
```bash
# SAST scan for SQL injection
sqlmap -u "http://localhost/super_admin/..." --forms

# Check for path traversal
python3 -c "
import requests
for dir in ['..', '../../', '../../../', '..%2f', '..%252f']:
    r = requests.get(f'http://localhost/super_admin/file_browser.php?dir={dir}')
    print(f'{dir}: {r.status_code}')
"

# Test MIME type bypass
curl -F "featured_image=@malicious.exe" \
     -F "title=Test" \
     http://localhost/super_admin/create_blog_post.php
```

### Manual Testing
1. Try uploading `shell.php.jpg` (double extension)
2. Try `../config.php` in file delete operation
3. Try CSRF using Burp Suite CSRF PoC generator
4. Measure response times for CSRF token comparison

---

## Configuration Checklist

- [ ] Disable `exec()`, `system()`, `shell_exec()` in production php.ini
- [ ] Set `upload_tmp_dir` to directory outside web root
- [ ] Set `open_basedir` to restrict file access
- [ ] Enable `disable_functions` in php.ini: `exec,system,shell_exec,passthru,proc_open,proc_get_status,popen`
- [ ] Regular backups outside of web-accessible directory
- [ ] Regular security audits and code reviews
- [ ] Monitor file_browser.php access logs for suspicious patterns

---

## Conclusion

The Super Admin panel requires **immediate attention to critical vulnerabilities** before production deployment. The foundation is solid, but file handling and command execution vulnerabilities pose severe risks. Follow the Phase 1 remediation plan immediately.

**Estimated Fix Time:** 4-6 hours for critical issues, 2-3 days for all issues.
