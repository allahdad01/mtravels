# Security Fixes Implementation Guide
**Priority:** CRITICAL - Implement immediately

---

## FIX #1: Secure File Download (CRITICAL)

**File:** `/api/download.php`

**Current Vulnerable Code:**
```php
<?php
$fileName = $_GET['file'] ?? '';
$inline = isset($_GET['inline']) && $_GET['inline'] === '1';
// ... directly serves file
?>
```

**SECURE REPLACEMENT:**
```php
<?php
require_once '../includes/session_check.php';

if (!checkSessionValid()) {
    http_response_code(401);
    die('Unauthorized');
}

$fileName = isset($_GET['file']) ? basename($_GET['file']) : '';

if (empty($fileName)) {
    http_response_code(400);
    die('File not specified');
}

// Whitelist of allowed directories
$allowed_dirs = [
    'uploads/tickets/',
    'uploads/documents/',
    'uploads/receipts/',
    'uploads/agreements/',
    'uploads/invoices/'
];

// Validate file is in allowed directory
$file_found = false;
foreach ($allowed_dirs as $dir) {
    $file_path = realpath('../' . $dir . $fileName);
    $allowed_path = realpath('../' . $dir);
    
    if ($file_path && $allowed_path && strpos($file_path, $allowed_path) === 0 && file_exists($file_path)) {
        $file_found = true;
        break;
    }
}

if (!$file_found) {
    http_response_code(403);
    die('Access denied or file not found');
}

// Additional security: verify file type
$allowed_types = [
    'application/pdf' => '.pdf',
    'image/jpeg' => '.jpg',
    'image/png' => '.png',
    'application/msword' => '.doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file_path);
finfo_close($finfo);

if (!in_array($mime, array_keys($allowed_types))) {
    http_response_code(403);
    die('File type not allowed');
}

// Serve the file
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . (isset($_GET['inline']) && $_GET['inline'] === '1' ? 'inline' : 'attachment') . '; filename="' . basename($file_path) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));

readfile($file_path);
exit;
?>
```

---

## FIX #2: Secure File Upload (CRITICAL)

**Files:** `/admin/assets.php`, `/admin/save_user.php`, `/admin/customer_detail.php`, etc.

**Create New File:** `/includes/SecureFileUpload.php`

```php
<?php
class SecureFileUpload {
    private $allowed_mimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain'
    ];
    
    private $max_size = 10485760; // 10MB
    private $upload_dir = '../uploads/';
    
    public function __construct($max_size = null) {
        if ($max_size) {
            $this->max_size = $max_size;
        }
    }
    
    /**
     * Validate and upload file
     */
    public function upload($file_input_name, $subdirectory = 'documents') {
        // Check if file was uploaded
        if (!isset($_FILES[$file_input_name])) {
            return ['success' => false, 'error' => 'No file provided'];
        }
        
        $file = $_FILES[$file_input_name];
        
        // 1. Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Upload failed: ' . $this->getUploadError($file['error'])];
        }
        
        // 2. Validate file size
        if ($file['size'] > $this->max_size) {
            return ['success' => false, 'error' => 'File too large. Maximum ' . ($this->max_size / 1048576) . 'MB allowed'];
        }
        
        // 3. Get actual MIME type (not from $_FILES, which can be spoofed)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        // 4. Validate MIME type
        if (!in_array($mime, $this->allowed_mimes)) {
            return ['success' => false, 'error' => 'File type not allowed. Only: ' . implode(', ', $this->allowed_mimes)];
        }
        
        // 5. Sanitize filename
        $original_name = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        
        // Whitelist extensions
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'];
        if (!in_array($extension, $allowed_extensions)) {
            return ['success' => false, 'error' => 'File extension not allowed'];
        }
        
        // 6. Generate unique filename
        $unique_name = uniqid() . '_' . md5(time() . $original_name) . '.' . $extension;
        
        // 7. Create upload directory if needed
        $target_dir = $this->upload_dir . $subdirectory . '/';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        
        $target_path = $target_dir . $unique_name;
        
        // 8. Move file
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            return ['success' => false, 'error' => 'Failed to save file'];
        }
        
        // 9. Set proper permissions
        chmod($target_path, 0644);
        
        return [
            'success' => true,
            'filename' => $unique_name,
            'path' => $target_path,
            'mime' => $mime,
            'size' => $file['size']
        ];
    }
    
    /**
     * Upload multiple files
     */
    public function uploadMultiple($file_input_name, $subdirectory = 'documents', $max_files = 5) {
        if (!isset($_FILES[$file_input_name])) {
            return ['success' => false, 'error' => 'No files provided'];
        }
        
        $files = $_FILES[$file_input_name];
        
        if (!is_array($files['name'])) {
            return ['success' => false, 'error' => 'Invalid file input'];
        }
        
        if (count($files['name']) > $max_files) {
            return ['success' => false, 'error' => 'Too many files. Maximum ' . $max_files . ' allowed'];
        }
        
        $results = [];
        
        for ($i = 0; $i < count($files['name']); $i++) {
            $_FILES['temp_file'] = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
            
            $result = $this->upload('temp_file', $subdirectory);
            $results[] = $result;
        }
        
        return ['success' => true, 'files' => $results];
    }
    
    private function getUploadError($code) {
        $errors = [
            UPLOAD_ERR_OK => 'No error',
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'No temporary directory',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        return $errors[$code] ?? 'Unknown error';
    }
}
?>
```

**Usage in admin/assets.php:**
```php
<?php
require_once '../includes/SecureFileUpload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $uploader = new SecureFileUpload(5 * 1024 * 1024); // 5MB limit
    $result = $uploader->upload('document', 'assets');
    
    if ($result['success']) {
        // Save to database
        $stmt = $pdo->prepare("INSERT INTO assets (tenant_id, filename, path, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$tenant_id, $result['filename'], $result['path'], $result['mime']]);
        echo json_encode(['success' => true, 'message' => 'File uploaded']);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
    exit;
}
?>
```

---

## FIX #3: Add CSRF Protection to All API Endpoints

**Create:** `/includes/CsrfProtection.php`

```php
<?php
class CsrfProtection {
    public static function generateToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function getToken() {
        return self::generateToken();
    }
    
    public static function validateToken($token = null) {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }
        
        if (!$token || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }
        
        // Regenerate after validation
        self::regenerateToken();
        return true;
    }
    
    public static function regenerateToken() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    public static function validateRequest($required_method = 'POST') {
        if ($_SERVER['REQUEST_METHOD'] !== $required_method) {
            return false;
        }
        
        return self::validateToken();
    }
}
?>
```

**Update all API handlers:**
```php
<?php
require_once '../includes/session_check.php';
require_once '../includes/CsrfProtection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!CsrfProtection::validateRequest('POST')) {
        http_response_code(403);
        die(json_encode(['error' => 'CSRF token validation failed']));
    }
}

// ... rest of handler
?>
```

---

## FIX #4: Remove Shell Execution (CRITICAL)

**File:** `/includes/paddle_ocr.php` (Line 33)

**BEFORE:**
```php
$tesseractPath = trim(shell_exec('which tesseract 2>/dev/null') ?: '');
exec($command . ' 2>&1', $output, $returnCode);
```

**AFTER:**
```php
<?php
// Safer approach: Use system detection at install time, not runtime
function findTesseract() {
    // Define known paths for different OS
    $paths = [
        '/usr/bin/tesseract',      // Linux
        '/usr/local/bin/tesseract', // macOS
        'C:\\Program Files\\Tesseract-OCR\\tesseract.exe', // Windows
        'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe'
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    // Return null if not found
    error_log("Tesseract not found in known paths");
    return null;
}

$tesseractPath = findTesseract();
if (!$tesseractPath) {
    throw new Exception("Tesseract OCR not installed");
}

// Use proc_open for safer execution
$descriptorspec = array(
   0 => array("pipe", "r"),
   1 => array("pipe", "w"),
   2 => array("pipe", "w")
);

$command = escapeshellcmd($tesseractPath) . ' ' . escapeshellarg($imagePath) . ' ' . escapeshellarg($outputFile);

$process = proc_open($command, $descriptorspec, $pipes);

if (is_resource($process)) {
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    $returnCode = proc_close($process);
    
    if ($returnCode !== 0) {
        error_log("Tesseract error: $error");
        return false;
    }
    
    return true;
}

return false;
?>
```

---

## FIX #5: Validate All Query Parameters

**Create validation helper:** `/includes/InputValidator.php`

```php
<?php
class InputValidator {
    
    public static function validateDate($value, $format = 'Y-m-d') {
        if (empty($value)) return null;
        
        $d = DateTime::createFromFormat($format, $value);
        return $d && $d->format($format) === $value ? $value : null;
    }
    
    public static function validateEmail($value) {
        if (empty($value)) return null;
        
        $sanitized = filter_var($value, FILTER_SANITIZE_EMAIL);
        if (filter_var($sanitized, FILTER_VALIDATE_EMAIL)) {
            return $sanitized;
        }
        return null;
    }
    
    public static function validateInt($value, $min = null, $max = null) {
        $sanitized = filter_var($value, FILTER_VALIDATE_INT);
        if ($sanitized === false) return null;
        
        if ($min !== null && $sanitized < $min) return null;
        if ($max !== null && $sanitized > $max) return null;
        
        return $sanitized;
    }
    
    public static function validateEnum($value, array $allowed_values, $default = null) {
        if (in_array($value, $allowed_values, true)) {
            return $value;
        }
        return $default;
    }
    
    public static function validateUrl($value) {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        return null;
    }
    
    public static function sanitizeString($value, $max_length = 255) {
        $value = trim($value);
        if (strlen($value) > $max_length) {
            $value = substr($value, 0, $max_length);
        }
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    public static function validatePhone($value) {
        // Remove non-digit characters
        $clean = preg_replace('/\D/', '', $value);
        
        // Validate length (10-15 digits typical for international)
        if (strlen($clean) >= 10 && strlen($clean) <= 15) {
            return $clean;
        }
        return null;
    }
}
?>
```

**Usage in API handlers:**
```php
<?php
require_once '../includes/InputValidator.php';

// In dashboard.php
$selected_date = InputValidator::validateDate(
    $_GET['departure_date'] ?? null,
    'Y-m-d'
) ?? date('Y-m-d');

// In budget_allocations.php
$selectedMonth = InputValidator::validateDate(
    $_GET['month'] ?? null,
    'Y-m'
) ?? date('Y-m');

// In whatsapp endpoint
$status = InputValidator::validateEnum(
    $_GET['status'] ?? '',
    ['pending', 'sent', 'delivered', 'failed']
);

// In message reactions
$emoji = InputValidator::sanitizeString(
    $_GET['emoji'] ?? '',
    50
);
?>
```

---

## FIX #6: Add Session Security Binding

**Update:** `/includes/session_check.php`

```php
<?php
function checkSessionValid() {
    // Existing checks...
    
    // NEW: Verify IP address hasn't changed
    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        error_log("Session IP mismatch: Expected {$_SESSION['ip_address']}, got {$_SERVER['REMOTE_ADDR']}");
        session_unset();
        session_destroy();
        return false;
    }
    
    // NEW: Verify User-Agent hasn't changed (basic protection)
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        error_log("Session User-Agent mismatch - possible hijacking attempt");
        session_unset();
        session_destroy();
        return false;
    }
    
    // ... rest of validation
    return true;
}
?>
```

**Update:** `/php_login.php` (after successful authentication)

```php
<?php
// After setting $_SESSION["loggedin"] = true
$_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
$_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
?>
```

---

## FIX #7: Enforce Password Strength

**Create:** `/includes/PasswordValidator.php`

```php
<?php
class PasswordValidator {
    
    const MIN_LENGTH = 12;
    const REQUIRE_UPPERCASE = true;
    const REQUIRE_LOWERCASE = true;
    const REQUIRE_NUMBERS = true;
    const REQUIRE_SPECIAL = true;
    
    public static function validate($password) {
        $errors = [];
        
        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = "Password must be at least " . self::MIN_LENGTH . " characters";
        }
        
        if (self::REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain uppercase letters";
        }
        
        if (self::REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain lowercase letters";
        }
        
        if (self::REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain numbers";
        }
        
        if (self::REQUIRE_SPECIAL && !preg_match('/[!@#$%^&*()_+\-=\[\]{};:"\\|,.<>\/?]/', $password)) {
            $errors[] = "Password must contain special characters (!@#$%^&*)";
        }
        
        return $errors;
    }
    
    public static function isValid($password) {
        return count(self::validate($password)) === 0;
    }
    
    public static function getStrengthScore($password) {
        $score = 0;
        $max_score = 5;
        
        if (strlen($password) >= self::MIN_LENGTH) $score++;
        if (preg_match('/[A-Z]/', $password)) $score++;
        if (preg_match('/[a-z]/', $password)) $score++;
        if (preg_match('/[0-9]/', $password)) $score++;
        if (preg_match('/[!@#$%^&*()_+\-=\[\]{};:"\\|,.<>\/?]/', $password)) $score++;
        
        return ['score' => $score, 'max' => $max_score, 'percentage' => ($score / $max_score) * 100];
    }
}
?>
```

---

## Implementation Timeline

**Day 1 (Critical):**
- [ ] Fix file download vulnerability (#1)
- [ ] Implement secure file upload (#2)
- [ ] Fix shell injection (#4)

**Day 2:**
- [ ] Add CSRF protection to all API endpoints (#3)
- [ ] Add input validation throughout (#5)

**Day 3-5:**
- [ ] Session security binding (#6)
- [ ] Password strength enforcement (#7)
- [ ] Content-Security-Policy header
- [ ] Rate limiting on sensitive endpoints

**Testing:**
- [ ] Run penetration tests
- [ ] Verify all fixes with automated scanners
- [ ] User acceptance testing

---
