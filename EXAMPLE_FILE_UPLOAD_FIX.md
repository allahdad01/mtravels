# Example: Secure File Upload Implementation

## Before (VULNERABLE) - admin/assets.php Lines 59-77

```php
// Handle document upload
$document = '';
if(isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
    $allowed = array('jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx');
    $filename = $_FILES['document']['name'];
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    
    if(in_array(strtolower($ext), $allowed)) {
        $new_filename = 'asset_doc_' . time() . '.' . $ext;
        $destination = '../uploads/assets/' . $new_filename;
        
        // Create directory if it doesn't exist
        if (!file_exists('../uploads/assets/')) {
            mkdir('../uploads/assets/', 0777, true);
        }
        
        if(move_uploaded_file($_FILES['document']['tmp_name'], $destination)) {
            $document = $new_filename;
        }
    }
}
```

### Problems:
- ❌ Only checks file extension (can be spoofed)
- ❌ No MIME type validation
- ❌ No file size limit
- ❌ No filename sanitization
- ❌ Files stored in web-accessible directory

---

## After (SECURE)

### Step 1: Add to top of admin/assets.php (after the other require_once statements):

```php
// Add this line after the other requires
require_once '../includes/SecureFileUpload.php';
```

### Step 2: Replace the vulnerable file upload code:

```php
// Handle document upload - SECURE VERSION
$document = '';
if(isset($_FILES['document'])) {
    // Use SecureFileUpload class
    $uploader = new SecureFileUpload(
        10 * 1024 * 1024, // 10MB max size
        '../uploads/'
    );
    
    $result = $uploader->upload('document', 'assets');
    
    if ($result['success']) {
        $document = $result['data']['filename'];
        // Optionally log the upload
        error_log("Asset document uploaded: {$result['data']['filename']} by user {$_SESSION['user_id']}");
    } else {
        $_SESSION['error_message'] = "File upload failed: " . $result['error'];
        header('Location: ' . $redirect_url);
        exit();
    }
}
```

### Step 3: Update the edit section similarly (lines 136-162):

```php
// Handle document upload - SECURE VERSION (for edit)
$document = $current_document;
if(isset($_FILES['document'])) {
    $uploader = new SecureFileUpload(
        10 * 1024 * 1024, // 10MB max size
        '../uploads/'
    );
    
    $result = $uploader->upload('document', 'assets');
    
    if ($result['success']) {
        $document = $result['data']['filename'];
        
        // Delete old document if it exists
        if(!empty($current_document)) {
            $old_file = '../uploads/assets/' . $current_document;
            // Verify old file path is safe before deleting
            if(file_exists($old_file) && strpos(realpath($old_file), realpath('../uploads/assets/')) === 0) {
                @unlink($old_file);
            }
        }
    } else {
        $_SESSION['error_message'] = "File upload failed: " . $result['error'];
        header('Location: ' . $redirect_url);
        exit();
    }
}
```

---

## What Changed?

### Security Improvements:

1. **MIME Type Validation** ✅
   - Uses `finfo_file()` to detect real MIME type
   - Can't be spoofed by file extension

2. **File Size Checking** ✅
   - Enforces maximum size (10MB in this example)
   - Rejects oversized files before processing

3. **Filename Sanitization** ✅
   - Generates unique, random filename
   - Prevents directory traversal attacks
   - Example output: `file_1707334800_a1b2c3d4.pdf`

4. **Extension Whitelist** ✅
   - Only allows specific safe extensions
   - Matches extension to MIME type

5. **Safe File Validation** ✅
   - Verifies `is_uploaded_file()` before moving
   - Prevents bypassing upload function

6. **Path Traversal Prevention** ✅
   - Uses `realpath()` to validate final path
   - Ensures file stays in upload directory

---

## Testing the Fix

### Test Case 1: Valid PDF Upload ✅
```
- Upload: report.pdf
- Result: file_1707334800_a1b2c3d4.pdf (SUCCESS)
```

### Test Case 2: Malicious .PHP Upload ❌
```
- Upload: shell.php (as .php)
- Result: "File type not allowed" (BLOCKED)
```

### Test Case 3: Spoofed Extension ❌
```
- Upload: shell.exe named as shell.pdf
- Result: Detected as exe, rejected (BLOCKED)
```

### Test Case 4: Oversized File ❌
```
- Upload: huge_file.pdf (15MB)
- Result: "File too large. Maximum 10MB allowed" (BLOCKED)
```

### Test Case 5: Directory Traversal ❌
```
- Upload: ../../etc/passwd
- Result: Path validated, prevented (BLOCKED)
```

---

## Applying to Other Files

The same pattern applies to all file upload handlers:

### Files to Update:
1. `/admin/assets.php` - Document uploads
2. `/admin/save_user.php` - Profile pictures, user documents
3. `/admin/customer_detail.php` - Receipt uploads
4. `/admin/support_ticket_detail.php` - Ticket attachments
5. `/admin/support_ticket_create.php` - Ticket attachments
6. `/admin/manage_maktobs.php` - PDF and attachments
7. `/admin/sarafi.php` - Receipt uploads
8. `/admin/update_profile.php` - Profile image
9. `/client/*` files - Any client-side uploads

### Generic Pattern:
```php
// At top of file
require_once '../includes/SecureFileUpload.php';

// In upload handler
$uploader = new SecureFileUpload(5 * 1024 * 1024, '../uploads/'); // 5MB limit
$result = $uploader->upload('file_input_name', 'upload_subdirectory');

if (!$result['success']) {
    // Handle error
    die('Error: ' . $result['error']);
}

// Use the filename
$filename = $result['data']['filename'];
$mime = $result['data']['mime'];
$size = $result['data']['size'];
```

---

## Next Steps

1. Apply this pattern to all file upload endpoints
2. Test each one with valid and malicious files
3. Verify files can't be executed from upload directory (configure .htaccess)
4. Add additional .htaccess rule:

```apache
<FilesMatch "\.(php|phtml|php3|php4|php5|phps|phar|pht|phpt|pgif|pjpeg|pjpg|plzh)$">
    Deny from all
</FilesMatch>
```

---
