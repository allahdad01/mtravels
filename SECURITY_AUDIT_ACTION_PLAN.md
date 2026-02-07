# Super Admin Security Audit - Action Plan

**Date Generated:** February 7, 2026  
**Risk Assessment:** 🔴 Critical vulnerabilities requiring immediate remediation

---

## Executive Summary

**Audit Findings:** 13 vulnerabilities identified
- **Critical:** 2 (Requires immediate fix)
- **High:** 3 (Must fix before production)
- **Medium:** 4 (Should address)
- **Low:** 3 (Nice to have)

**Estimated Remediation Time:** 6-8 hours for critical/high priority

---

## Phase 1: Critical Fixes (48 hours)

### Issue 1: Path Traversal in file_browser.php ⚠️ CRITICAL

**Vulnerability:** Directory traversal allows accessing files outside `/uploads`

**Files Provided:**
- `SECURITY_FIXES_FILE_BROWSER.php` - Contains complete fix

**Implementation Steps:**
1. Add `validateUploadPath()` function from provided file
2. Replace directory handling at lines ~50-60
3. Add path validation to all POST operations
4. Test with: `?dir=../../config.php`

**Time:** 30 minutes

---

### Issue 2: Command Injection in backup_management.php ⚠️ CRITICAL

**Vulnerability:** Shell command injection via mysqldump execution

**Files Provided:**
- `SECURITY_FIXES_BACKUP.php` - Safe `proc_open()` implementation

**Implementation Steps:**
1. Copy `executeMysqldumpSafely()` function
2. Replace lines 86-122 in backup_management.php
3. Update error handling for safe error messages
4. Test backup functionality

**Time:** 45 minutes

---

### Issue 3: Missing MIME Type Validation ⚠️ HIGH

**Vulnerability:** File type spoofing (rename .exe to .jpg)

**Files Provided:**
- `super_admin/includes/file_upload_security.php` - Complete solution

**Files to Update:**
- `create_blog_post.php` (lines 75-105)
- `handlers/create_system_expense.php` (lines 40-61)
- `file_browser.php` (lines 145-183)

**Implementation Steps:**

**For create_blog_post.php:**
```php
// Add at top
require_once 'includes/file_upload_security.php';

// Replace upload handling (lines 74-105)
$featured_image = '';
if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
    $validation = FileUploadSecurity::validateUpload($_FILES['featured_image'], 'image', 5242880);
    
    if (!$validation['success']) {
        header('Location: manage_blog_posts.php?error=' . urlencode($validation['error']));
        exit();
    }
    
    $upload_dir = '../uploads/blog/';
    $moveResult = FileUploadSecurity::moveUploadedFile(
        $_FILES['featured_image']['tmp_name'],
        $upload_dir,
        $validation['safe_name']
    );
    
    if ($moveResult['success']) {
        $featured_image = '/uploads/blog/' . $validation['safe_name'];
    } else {
        header('Location: manage_blog_posts.php?error=' . urlencode($moveResult['error']));
        exit();
    }
}
```

**For handlers/create_system_expense.php:**
```php
// Similar implementation for expense receipts
$validation = FileUploadSecurity::validateUpload($file, 'document', 5242880);
if (!$validation['success']) {
    exit(json_encode(['success' => false, 'message' => $validation['error']]));
}
```

**Time:** 1.5 hours (for all files)

---

## Phase 2: High Priority Fixes (1 week)

### Issue 4: Unsafe CSRF Token Comparison

**Location:** `handlers/create_system_expense.php` and `delete_system_expense.php`

**Current (vulnerable):**
```php
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
```

**Fixed:**
```php
if (!isset($_POST['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
```

**Files to Update:**
- handlers/create_system_expense.php (line 11)
- handlers/delete_system_expense.php (line 9)

**Time:** 15 minutes

---

### Issue 5: Filename Traversal in Upload

**Location:** `file_browser.php` lines 152-169

**Vulnerability:** Filename can contain `..` patterns to write outside upload directory

**Add sanitization:**
```php
$fileName = basename($uploadedFiles['name'][$i]);

// Whitelist characters only
if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $fileName)) {
    $errors[] = "Invalid filename";
    continue;
}

// Generate safe name
$ext = pathinfo($fileName, PATHINFO_EXTENSION);
$safeFileName = 'upload_' . bin2hex(random_bytes(8)) . '.' . $ext;
```

**Time:** 20 minutes

---

### Issue 6: Error Details Leakage

**Locations:**
- `handlers/delete_system_expense.php` lines 102-104
- `handlers/create_system_expense.php` line 104

**Current:**
```php
exit(json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]));
```

**Fixed:**
```php
error_log("Detailed error: " . $e->getMessage());
exit(json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']));
```

**Time:** 10 minutes

---

### Issue 7: Missing CSRF in file_browser.php DELETE

**Location:** `file_browser.php` delete handler

**Fix:**
```php
if ($_POST['action'] === 'delete') {
    // Add CSRF validation
    if (!isset($_POST['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        exit(json_encode(['success' => false, 'message' => 'CSRF validation failed']));
    }
    // ... rest of delete code
}
```

**Time:** 10 minutes

---

## Phase 3: Medium Priority (2 weeks)

### Issue 8: Duplicate Database Include

**Location:** `backup_management.php` lines 30-31

**Fix:** Delete one of the duplicate `require_once` lines

**Time:** 1 minute

---

### Issue 9: Missing Session Timeout in AJAX Handlers

**Files:**
- `handlers/create_system_expense.php`
- `handlers/delete_system_expense.php`
- `handlers/get_system_expense.php`
- `handlers/update_system_expense.php`

**Add to each handler after session_start():**
```php
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Session expired']));
}
$_SESSION['last_activity'] = time();
```

**Time:** 45 minutes

---

### Issue 10: Weak File Randomization

**Location:** Multiple upload handlers

**Change from:**
```php
$filename = uniqid('blog_') . '.' . $file_extension;
```

**Change to:**
```php
$filename = 'file_' . bin2hex(random_bytes(16)) . '.' . $file_extension;
```

**Time:** 20 minutes

---

### Issue 11: MySQLi/PDO Inconsistency

**Location:** `create_blog_post.php` line 68

**Current:**
```php
$result = $stmt->get_result();  // MySQLi method
if ($result->num_rows > 0) {
```

**Fixed:**
```php
if ($stmt->rowCount() > 0) {
```

**Time:** 10 minutes

---

### Issue 12 & 13: Rate Limiting & CSP Consolidation

See existing SECURITY_FIXES_APPLIED.md

---

## Testing Checklist

### Automated Tests

```bash
# Test path traversal
curl "http://localhost/super_admin/file_browser.php?dir=../../config.php" \
     -H "Cookie: PHPSESSID=your_session"

# Test MIME bypass
curl -F "featured_image=@malware.exe" \
     -F "title=Test" \
     -F "csrf_token=token" \
     http://localhost/super_admin/create_blog_post.php

# Test CSRF token timing
time curl -X POST http://localhost/super_admin/handlers/create_system_expense.php \
     -d "csrf_token=wrong123"
time curl -X POST http://localhost/super_admin/handlers/create_system_expense.php \
     -d "csrf_token=wrong456"
# Response times should be identical
```

### Manual Tests

1. **Path Traversal:**
   - Try `?dir=../../../`
   - Try `?dir=../config.php`
   - Verify 403 Forbidden response

2. **MIME Validation:**
   - Upload image.jpg with PNG data → Should accept
   - Upload image.png with EXE data → Should reject
   - Upload shell.php.jpg → Should reject

3. **CSRF Protection:**
   - Delete file without CSRF token → Should fail
   - Modify CSRF token slightly → Should fail
   - Use valid token → Should succeed

4. **Backup Function:**
   - Verify backup completes without shell output
   - Check file size > 1MB
   - Verify integrity with `gunzip` or database restore

---

## Deployment Checklist

- [ ] All Phase 1 fixes applied and tested
- [ ] No console errors in browser DevTools
- [ ] File uploads work with proper validation
- [ ] Backup function completes successfully
- [ ] No SQL errors exposed to frontend
- [ ] CSRF tokens working on all forms
- [ ] Rate limiting activated for login/API
- [ ] Session timeout functioning
- [ ] Audit logs recording all actions
- [ ] Security headers configured correctly
- [ ] php.ini has restrictive settings

---

## php.ini Security Settings

Add or update in php.ini for production:

```ini
; Disable dangerous functions
disable_functions = exec,system,shell_exec,passthru,show_source,proc_open,popen

; File upload restrictions
upload_tmp_dir = /var/tmp/php_uploads
upload_max_filesize = 32M
post_max_size = 64M

; Session security
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = Strict
session.gc_maxlifetime = 1800

; Error handling
display_errors = 0
log_errors = 1
error_log = /var/log/php/error.log

; File access
open_basedir = /var/www/html:/tmp

; Misc
expose_php = 0
allow_url_fopen = 0
```

---

## Monitoring & Logging

### Events to Monitor

1. Frequent file deletions from file_browser
2. Failed CSRF token validation attempts
3. Path traversal attempts (403 errors)
4. Backup execution failures
5. Session timeout violations
6. Unauthorized access attempts

### Log Format

All security events should log:
- Timestamp
- User ID
- IP Address
- Action
- Resource affected
- Result (success/failure)

---

## References

- OWASP Path Traversal: https://owasp.org/www-community/attacks/Path_Traversal
- File Upload Vulnerabilities: https://owasp.org/www-community/vulnerabilities/Unrestricted_File_Upload
- Command Injection: https://owasp.org/www-community/attacks/Command_Injection
- CSRF Prevention: https://owasp.org/www-community/attacks/csrf

---

## Support Files Generated

1. `SUPER_ADMIN_SECURITY_AUDIT.md` - Detailed vulnerability report
2. `SECURITY_FIXES_FILE_BROWSER.php` - Path traversal fixes
3. `SECURITY_FIXES_BACKUP.php` - Command injection fixes
4. `super_admin/includes/file_upload_security.php` - MIME validation library
5. `SECURITY_FIXES_APPLIED.md` - Previous security enhancements
6. `SECURITY_AUDIT_ACTION_PLAN.md` - This document

---

## Timeline

| Phase | Priority | Duration | Deadline |
|-------|----------|----------|----------|
| Phase 1 | Critical | 2-3 hours | Within 48 hours |
| Phase 2 | High | 2-3 hours | Within 7 days |
| Phase 3 | Medium | 2-3 hours | Within 14 days |
| **Total** | - | **6-8 hours** | **Production Ready** |

---

## Sign-Off

**Audit Completed By:** Amp Security Review  
**Date:** February 7, 2026  
**Status:** Actionable remediation plan provided

**Next Steps:**
1. Assign team members to each phase
2. Create feature branches for changes
3. Implement and test fixes
4. Perform security code review
5. Deploy to production

