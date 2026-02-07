# Super Admin Security Fixes - Quick Implementation Guide

## Critical Fixes Required

### 1. Fix IDOR in generate_invoice_pdf.php

**Replace lines 23-63 with:**

```php
<?php
// Check CSRF token first
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
    !isset($_POST['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    die('Invalid request - CSRF token validation failed');
}

// Get parameters
$payment_id = intval($_POST['payment_id'] ?? 0);
$subscription_id = intval($_POST['subscription_id'] ?? 0);
$amount = floatval($_POST['amount'] ?? 0);
$currency = $_POST['currency'] ?? 'USD';
$payment_date = $_POST['payment_date'] ?? date('Y-m-d');
$payment_method = $_POST['payment_method'] ?? '';
$transaction_id = $_POST['transaction_id'] ?? '';
$receipt_number = $_POST['receipt_number'] ?? '';
$notes = $_POST['notes'] ?? '';

// If payment_id is provided, fetch from database with ownership verification
if ($payment_id > 0) {
    try {
        // IMPORTANT: Verify access control
        // Check if current admin has authority over this payment's tenant
        $stmt = $pdo->prepare("
            SELECT sp.*, ts.id as subscription_id, ts.tenant_id
            FROM subscription_payments sp
            LEFT JOIN tenant_subscriptions ts ON sp.subscription_id = ts.id
            WHERE sp.id = ?
        ");
        $stmt->execute([$payment_id]);
        $payment_record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment_record) {
            die('Payment record not found');
        }
        
        // SECURITY: Verify admin access
        // For super_admin: allow access (they manage all tenants)
        // For tenant_admin: verify they own the tenant
        if ($_SESSION['role'] === 'tenant_admin') {
            if ($payment_record['tenant_id'] != $_SESSION['tenant_id']) {
                error_log("SECURITY: Unauthorized payment access attempt - User: {$_SESSION['user_id']}, Payment: {$payment_id}, Tenant: {$payment_record['tenant_id']}");
                die('Unauthorized access to this payment');
            }
        }
        
        // Use payment record data
        $subscription_id = $payment_record['subscription_id'];
        $amount = $payment_record['amount'];
        $currency = $payment_record['currency'];
        $payment_date = $payment_record['payment_date'];
        $payment_method = $payment_record['payment_method'];
        $transaction_id = $payment_record['transaction_id'];
        $receipt_number = $payment_record['receipt_number'];
        $notes = $payment_record['notes'];
    } catch (PDOException $e) {
        error_log("Error fetching payment: " . $e->getMessage());
        die("Error fetching payment");
    }
} elseif (!$subscription_id || !$amount) {
    die('Invalid parameters');
}
?>
```

---

### 2. Fix SQL Injection in backup_management.php

**Replace lines 186-212 with:**

```php
// Validate table names first
$allowed_tables = [];
$table_result = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
while ($row = $table_result->fetch(PDO::FETCH_ASSOC)) {
    $allowed_tables[] = $row['TABLE_NAME'];
}

// Export each table
foreach ($tables as $table) {
    // SECURITY: Validate table name against allowed list
    if (!in_array($table, $allowed_tables)) {
        error_log("WARNING: Invalid table name attempted: " . $table);
        continue;
    }
    
    // Use prepared statement with identifier sanitization
    $table_identifier = '`' . str_replace('`', '``', $table) . '`';
    
    try {
        // Get table structure
        $create_result = $pdo->query("SHOW CREATE TABLE {$table_identifier}");
        $create_table = $create_result->fetch(PDO::FETCH_NUM)[1];
        $create_table_sql = "DROP TABLE IF EXISTS {$table_identifier};\n" . $create_table . ";\n\n";
        fwrite($fh, $create_table_sql);
        
        // Get table data with prepared statement
        $select_stmt = $pdo->prepare("SELECT * FROM {$table_identifier}");
        $select_stmt->execute();
        
        // Only proceed if there are rows
        while ($row = $select_stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map(function($k) { return "`" . str_replace('`', '``', $k) . "`"; }, array_keys($row));
            $values = array_map(function($v) {
                if ($v === null) return 'NULL';
                return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$v) . "'";
            }, array_values($row));
            
            $insert_sql = sprintf(
                "INSERT INTO %s (%s) VALUES (%s);\n", 
                $table_identifier,
                implode(',', $columns), 
                implode(',', $values)
            );
            fwrite($fh, $insert_sql);
        }
        fwrite($fh, "\n");
    } catch (PDOException $e) {
        error_log("Error exporting table {$table}: " . $e->getMessage());
        continue;
    }
}
```

---

### 3. Fix Missing CSRF on PDF Generation

**Add to top of generate_invoice_pdf.php (after includes):**

```php
// Regenerate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// For display in HTML forms
$csrf_token = $_SESSION['csrf_token'];
```

**Create HTML form for PDF download (instead of GET parameter):**

```html
<form method="POST" action="generate_invoice_pdf.php" target="_blank">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="payment_id" value="<?= $payment_id ?>">
    <button type="submit" class="btn btn-primary">
        <i class="feather icon-download"></i> Download PDF Invoice
    </button>
</form>
```

---

### 4. Fix File Upload Validation

**Replace file upload section in file_browser.php (lines 155-215):**

```php
if ($_POST['action'] === 'upload_file') {
    if (!empty($_FILES['files'])) {
        $uploadedFiles = $_FILES['files'];
        $successCount = 0;
        $errors = [];
        
        // Define whitelist of allowed file extensions
        $allowed_extensions = [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 
            'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip'
        ];
        
        // Define allowed MIME types
        $allowed_mimes = [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'text/plain' => 'txt',
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip'
        ];
        
        // Maximum file size: 50MB
        $max_file_size = 50 * 1024 * 1024;
        
        // Handle multiple files
        for ($i = 0; $i < count($uploadedFiles['name']); $i++) {
            $originalName = $uploadedFiles['name'][$i];
            $tmpPath = $uploadedFiles['tmp_name'][$i];
            $error = $uploadedFiles['error'][$i];
            $fileSize = $uploadedFiles['size'][$i];
            
            // Check for upload errors
            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = "Upload error for: " . htmlspecialchars($originalName);
                continue;
            }
            
            // Check file size
            if ($fileSize > $max_file_size) {
                $errors[] = "File exceeds 50MB limit: " . htmlspecialchars($originalName);
                continue;
            }
            
            // Sanitize filename - remove path components
            $fileName = basename($originalName);
            
            // Remove dots at start (hidden files) and check for path traversal
            if (preg_match('/^[\.]|\/|\\\\|\.\./', $fileName)) {
                $errors[] = "Invalid filename: " . htmlspecialchars($fileName);
                continue;
            }
            
            // Whitelist characters only (alphanumeric, underscore, dash, dot)
            if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $fileName)) {
                $errors[] = "Invalid characters in: " . htmlspecialchars($fileName);
                continue;
            }
            
            // Check file extension
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_extensions)) {
                $errors[] = "File type not allowed: " . htmlspecialchars($ext);
                continue;
            }
            
            // Validate MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);
            
            if (!isset($allowed_mimes[$mime_type])) {
                $errors[] = "Invalid file MIME type for: " . htmlspecialchars($originalName) . 
                           " (detected: {$mime_type})";
                continue;
            }
            
            // Verify extension matches MIME type
            if ($allowed_mimes[$mime_type] !== $ext) {
                $errors[] = "File extension doesn't match MIME type: " . 
                           htmlspecialchars($originalName);
                continue;
            }
            
            // Generate safe filename with random component
            $safeFileName = 'upload_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $targetPath = $currentDir . '/' . $safeFileName;
            
            // Move uploaded file
            if (move_uploaded_file($tmpPath, $targetPath)) {
                // Set proper permissions (readable only)
                @chmod($targetPath, 0644);
                $successCount++;
                
                // Log successful upload
                error_log("FILE_UPLOAD: User {$_SESSION['user_id']} uploaded {$originalName} to {$targetPath}");
            } else {
                $errors[] = "Failed to upload: " . htmlspecialchars($fileName);
            }
        }
        
        $response['success'] = $successCount > 0;
        $response['message'] = $successCount . " file(s) uploaded successfully. ";
        if (!empty($errors)) {
            $response['message'] .= "Errors: " . implode(", ", $errors);
        }
    } else {
        $response['message'] = 'No files uploaded';
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
```

---

### 5. Fix Directory Traversal Vulnerability

**Create new file: includes/path_validation.php**

```php
<?php
/**
 * Safely validate that a requested path is within a base directory
 */
function validateUploadPath($requested_path, $base_dir) {
    // Normalize paths to handle Windows/Unix differences
    $base_dir = str_replace('\\', '/', realpath($base_dir));
    $requested = str_replace('\\', '/', $requested_path);
    
    // Resolve the requested path
    $requested_real = str_replace('\\', '/', realpath($requested));
    
    // If path doesn't exist, check parent
    if ($requested_real === false) {
        $parent = dirname($requested);
        $requested_real = str_replace('\\', '/', realpath($parent));
        if ($requested_real === false) {
            return false;
        }
    }
    
    // Ensure base dir ends without slash for comparison
    $base_dir = rtrim($base_dir, '/');
    
    // Check if requested path is within base directory
    // Must either be equal or start with base_dir/
    if ($requested_real === $base_dir) {
        return true;
    }
    
    if (strpos($requested_real, $base_dir . '/') === 0) {
        return true;
    }
    
    return false;
}

/**
 * Get safe uploads directory
 */
function getSafeUploadsDir($subfolder = '') {
    $base = realpath('../uploads');
    if ($base === false) {
        return false;
    }
    
    if (empty($subfolder)) {
        return $base;
    }
    
    $requested = $base . '/' . $subfolder;
    if (!validateUploadPath($requested, $base)) {
        return false;
    }
    
    return $requested;
}
?>
```

**Update file_browser.php to use this:**

```php
// At top of file_browser.php, replace path validation section
require_once '../includes/path_validation.php';

$uploadsDir = getSafeUploadsDir();
if ($uploadsDir === false) {
    die('Invalid uploads directory');
}

$currentFolder = isset($_GET['folder']) ? trim($_GET['folder']) : '';
$currentDir = $uploadsDir;

if (!empty($currentFolder)) {
    $currentDir = getSafeUploadsDir($currentFolder);
    if ($currentDir === false) {
        $currentDir = $uploadsDir;
        $currentFolder = '';
    }
}
```

---

### 6. Strengthen Role Validation

**Create new file: includes/role_security.php**

```php
<?php
/**
 * Role-based access control
 */

// Define role hierarchy (higher number = more privilege)
define('ROLE_HIERARCHY', [
    'user' => 0,
    'tenant_admin' => 1,
    'super_admin' => 2
]);

/**
 * Check if current user can assign a role
 */
function canAssignRole($new_role, $current_user_role) {
    if (!isset(ROLE_HIERARCHY[$new_role]) || !isset(ROLE_HIERARCHY[$current_user_role])) {
        return false;
    }
    
    // Can only assign roles equal or lower than your own
    return ROLE_HIERARCHY[$new_role] <= ROLE_HIERARCHY[$current_user_role];
}

/**
 * Validate role is in allowed list
 */
function isValidRole($role) {
    return isset(ROLE_HIERARCHY[$role]);
}

/**
 * Get current user role level
 */
function getCurrentRoleLevel() {
    if (!isset($_SESSION['role']) || !isset(ROLE_HIERARCHY[$_SESSION['role']])) {
        return -1;
    }
    return ROLE_HIERARCHY[$_SESSION['role']];
}

/**
 * Check if user has minimum role level
 */
function hasMinimumRole($required_level) {
    return getCurrentRoleLevel() >= $required_level;
}
?>
```

**Use in create_user.php (replace role validation section):**

```php
require_once '../includes/role_security.php';

// Validate role
if (!isValidRole($role)) {
    $errors[] = "Invalid role.";
} elseif (!canAssignRole($role, $_SESSION['role'])) {
    $errors[] = "You cannot assign a role with higher privileges than your own.";
}

if ($role !== 'super_admin' && empty($tenant_id)) {
    $errors[] = "Tenant is required for non-super admin roles.";
}
if ($role === 'super_admin' && !empty($tenant_id)) {
    $errors[] = "Super admins cannot be assigned to a tenant.";
}

// ... after successful creation, log the action
logAuditTrail('create_user', 'user', $user_id, [
    'name' => $name,
    'email' => $email,
    'role' => $role,
    'tenant_id' => $tenant_id
]);
```

---

### 7. Add Rate Limiting (Example)

**Create new file: includes/rate_limit.php**

```php
<?php
/**
 * Simple rate limiting using file-based cache
 */

function checkRateLimit($key, $max_attempts, $time_window) {
    $cache_dir = '../cache/rate_limit';
    
    // Create cache directory if needed
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }
    
    $cache_file = $cache_dir . '/' . md5($key) . '.json';
    $now = time();
    
    // Get existing attempt data
    $attempts = [];
    if (file_exists($cache_file)) {
        $data = json_decode(file_get_contents($cache_file), true);
        if ($data && $data['expire'] > $now) {
            $attempts = $data['attempts'] ?? [];
        }
    }
    
    // Clean old attempts outside window
    $attempts = array_filter($attempts, function($time) use ($now, $time_window) {
        return $time > ($now - $time_window);
    });
    
    // Check if limit exceeded
    if (count($attempts) >= $max_attempts) {
        http_response_code(429);
        die("Rate limit exceeded. Please try again later.");
    }
    
    // Add new attempt
    $attempts[] = $now;
    
    // Save updated attempt data
    file_put_contents($cache_file, json_encode([
        'attempts' => $attempts,
        'expire' => $now + $time_window
    ]));
    
    return true;
}
?>
```

**Use in backup_management.php:**

```php
require_once '../includes/rate_limit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup_database'])) {
    // Rate limit: 5 backups per hour per user
    checkRateLimit('backup_' . $_SESSION['user_id'], 5, 3600);
    
    // ... rest of backup code
}
```

---

## Summary of Changes

| Vulnerability | Fix | Priority |
|---|---|---|
| IDOR | Add ownership verification in PDF generation | CRITICAL |
| SQL Injection | Validate table names, use prepared statements | CRITICAL |
| CSRF on GET | Convert to POST with CSRF token | CRITICAL |
| Weak file upload | Add MIME type validation, extension whitelist | HIGH |
| Directory traversal | Improve path validation logic | HIGH |
| Weak role validation | Add role hierarchy checks | HIGH |
| No rate limiting | Implement rate limit checks | MEDIUM |

## Testing After Fixes

```bash
# Test CSRF protection
curl -X POST http://localhost/super_admin/generate_invoice_pdf.php \
  -d "payment_id=1&csrf_token=invalid"

# Test file upload blocking
# Try uploading .php file (should fail)
curl -F "files=@shell.php" \
  -F "csrf_token=<token>" \
  -F "action=upload_file" \
  http://localhost/super_admin/file_browser.php

# Test rate limiting
for i in {1..6}; do
  curl -X POST http://localhost/super_admin/backup_management.php \
    -d "backup_database=1&csrf_token=<token>"
done
# 6th attempt should return 429 Too Many Requests
```
