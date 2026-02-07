# Super Admin Security - Quick Fix Reference

## Critical Fixes (Copy-Paste Ready)

### Fix 1: Path Traversal Protection

**File:** `super_admin/file_browser.php`

**Add this function at the top:**
```php
function validateUploadPath($uploadsDir, $requestedPath) {
    $realUploadsDir = realpath($uploadsDir);
    if ($realUploadsDir === false) return false;
    
    if (empty($requestedPath)) return $realUploadsDir;
    
    $fullPath = realpath($uploadsDir . '/' . $requestedPath);
    if ($fullPath === false) return false;
    
    return (strpos($fullPath, $realUploadsDir) === 0) ? $fullPath : false;
}
```

**Replace the directory handling with:**
```php
$validatedPath = validateUploadPath($uploadsDir, $_GET['dir'] ?? '');
if ($validatedPath === false) {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied']));
}
$currentDir = $validatedPath;
```

---

### Fix 2: CSRF Token Safety

**Files:** `handlers/create_system_expense.php`, `handlers/delete_system_expense.php`

**Change:**
```php
// ❌ Old (vulnerable to timing attacks)
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
```

**To:**
```php
// ✅ New (safe timing-attack resistant)
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
```

---

### Fix 3: Add MIME Validation

**Files:** `create_blog_post.php`, `handlers/create_system_expense.php`

**First, add at the top:**
```php
require_once 'includes/file_upload_security.php';
```

**Then replace file upload handling with:**
```php
if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
    $validation = FileUploadSecurity::validateUpload($_FILES['featured_image'], 'image', 5242880);
    
    if (!$validation['success']) {
        // Handle error
        die(json_encode(['success' => false, 'message' => $validation['error']]));
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
        die(json_encode(['success' => false, 'message' => $moveResult['error']]));
    }
}
```

---

### Fix 4: Session Timeout for AJAX

**Files:** All `handlers/*.php`

**Add after session_start():**
```php
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Session expired']));
}
$_SESSION['last_activity'] = time();
```

---

### Fix 5: Error Message Cleanup

**Files:** `handlers/delete_system_expense.php`, `handlers/create_system_expense.php`

**Change:**
```php
// ❌ Old (exposes error details)
exit(json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]));
```

**To:**
```php
// ✅ New (safe)
error_log("Error details: " . $e->getMessage());
exit(json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']));
```

---

### Fix 6: Filename Sanitization

**File:** `file_browser.php` (in upload handler)

**Change:**
```php
// ❌ Old
$targetPath = $currentDir . '/' . $fileName;
```

**To:**
```php
// ✅ New
$fileName = basename($fileName);
if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $fileName)) {
    $errors[] = "Invalid filename";
    continue;
}
$ext = pathinfo($fileName, PATHINFO_EXTENSION);
$safeFileName = 'upload_' . bin2hex(random_bytes(8)) . '.' . $ext;
$targetPath = $currentDir . '/' . $safeFileName;
```

---

### Fix 7: CSRF on File Delete

**File:** `file_browser.php`

**Change:**
```php
// ❌ Old
if ($_POST['action'] === 'delete') {
    // Directly deletes
}
```

**To:**
```php
// ✅ New
if ($_POST['action'] === 'delete') {
    if (!isset($_POST['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        exit(json_encode(['success' => false, 'message' => 'CSRF validation failed']));
    }
    // Now safe to delete
}
```

---

### Fix 8: Remove Duplicates

**File:** `backup_management.php` lines 30-31

```php
require_once '../includes/db.php';  // Keep
require_once '../includes/db.php';  // DELETE THIS LINE
```

---

### Fix 9: Better File Randomization

**Files:** All upload handlers

**Change:**
```php
// ❌ Old (predictable)
$filename = uniqid('blog_') . '.' . $file_extension;
```

**To:**
```php
// ✅ New (cryptographically secure)
$filename = 'file_' . bin2hex(random_bytes(16)) . '.' . $file_extension;
```

---

### Fix 10: Rate Limiting

**Files:** Any file operation (optional but recommended)

**Add after CSRF validation:**
```php
enforce_rate_limit('file_upload', 10, 60);  // 10 files per minute
```

---

## Verification Commands

### Test Path Traversal Protection
```bash
curl "http://localhost/super_admin/file_browser.php?dir=../../" \
     -H "Cookie: PHPSESSID=your_session_id"
# Should return 403 or empty
```

### Test MIME Validation
```bash
# Upload an executable disguised as image
cp /bin/ls image.jpg
curl -F "featured_image=@image.jpg" http://localhost/super_admin/...
# Should reject if MIME is detected as binary
```

### Test CSRF Protection
```bash
# Try deleting without CSRF token
curl -X POST http://localhost/super_admin/file_browser.php \
     -d "action=delete&csrf_token=wrong_token"
# Should fail
```

---

## File Reference

| Issue | Fix File | Location |
|-------|----------|----------|
| Path Traversal | SECURITY_FIXES_FILE_BROWSER.php | Issue #1 |
| Command Injection | SECURITY_FIXES_BACKUP.php | Issue #2 |
| MIME Validation | file_upload_security.php | Issue #3 |
| CSRF Token | This file | Issue #4, #7 |
| Session Timeout | This file | Issue #9 |
| Error Handling | This file | Issue #6 |
| Filename Safety | This file | Issue #5 |
| File Randomization | This file | Issue #8 |

---

## Implementation Order

1. **First:** Copy `file_upload_security.php` to `super_admin/includes/`
2. **Second:** Fix path traversal in `file_browser.php`
3. **Third:** Fix command injection in `backup_management.php`
4. **Fourth:** Add MIME validation to all upload handlers
5. **Fifth:** Fix CSRF comparisons (all `!==` to `hash_equals()`)
6. **Sixth:** Add error message cleanup
7. **Seventh:** Add session timeout to AJAX handlers
8. **Eighth:** Test everything

---

## Quick Test Checklist

- [ ] Path traversal blocked (403 on `?dir=../`)
- [ ] MIME validation rejects .exe files
- [ ] CSRF token required for all POST
- [ ] File delete requires CSRF token
- [ ] Session timeout checked in AJAX
- [ ] Error messages don't expose details
- [ ] Backup completes without shell output
- [ ] File randomization uses random_bytes()

---

## Support Files

All fixes are provided in:
- SECURITY_AUDIT_ACTION_PLAN.md (Full details)
- SUPER_ADMIN_SECURITY_AUDIT.md (Why these matter)
- SECURITY_FIXES_FILE_BROWSER.php (Complete implementation)
- SECURITY_FIXES_BACKUP.php (Complete implementation)
