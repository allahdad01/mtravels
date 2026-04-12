<?php
/**
 * Path Validation Utilities
 * 
 * Provides secure functions for validating file paths to prevent directory traversal attacks
 */

/**
 * Safely validate that a requested path is within a base directory
 * 
 * @param string $requested_path The path to validate
 * @param string $base_dir The base directory that must contain the path
 * @return bool True if path is valid, false otherwise
 */
function validateUploadPath($requested_path, $base_dir) {
    // Normalize paths to handle Windows/Unix differences
    $base_dir = str_replace('\\', '/', realpath($base_dir));
    if ($base_dir === false) {
        return false;
    }
    
    // For paths that don't exist yet, validate the parent directory
    $requested_real = str_replace('\\', '/', realpath($requested_path));
    if ($requested_real === false) {
        $parent = dirname($requested_path);
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
 * Get safe uploads directory, with optional subdirectory
 * 
 * @param string $subfolder Optional subfolder within uploads
 * @return string|bool Absolute path to uploads directory or false if invalid
 */
function getSafeUploadsDir($subfolder = '') {
    // Get absolute path to uploads directory
    $base = realpath(__DIR__ . '/../../uploads');
    if ($base === false) {
        return false;
    }
    
    if (empty($subfolder)) {
        return $base;
    }
    
    // Build requested path
    $requested = $base . DIRECTORY_SEPARATOR . $subfolder;
    
    // Validate path is within uploads
    if (!validateUploadPath($requested, $base)) {
        return false;
    }
    
    return str_replace('\\', '/', $requested);
}

/**
 * Validate input length to prevent buffer overflow and injection attacks
 * 
 * @param string $input The input string to validate
 * @param int $max_length Maximum allowed length
 * @return string|null The sanitized input or null if too long
 */
function validate_input_length($input, $max_length = 255) {
    if (empty($input)) {
        return '';
    }
    
    if (strlen($input) > $max_length) {
        return null; // Input exceeds max length
    }
    
    return trim($input);
}

/**
 * Validate a filename for safe upload
 * 
 * @param string $filename The filename to validate
 * @return bool True if filename is safe, false otherwise
 */
function isValidFilename($filename) {
    // Get base filename only (no paths)
    $filename = basename($filename);
    
    // Reject hidden files (starting with dot)
    if (substr($filename, 0, 1) === '.') {
        return false;
    }
    
    // Reject path components
    if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        return false;
    }
    
    // Reject directory traversal attempts
    if (strpos($filename, '..') !== false) {
        return false;
    }
    
    // Only allow alphanumeric, underscore, dash, and dot
    if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $filename)) {
        return false;
    }
    
    return true;
}

/**
 * Validate file extension against whitelist
 * 
 * @param string $filename The filename to check
 * @param array $allowed_extensions List of allowed extensions (without dots)
 * @return bool True if extension is allowed, false otherwise
 */
function isAllowedExtension($filename, $allowed_extensions = []) {
    if (empty($allowed_extensions)) {
        // Default whitelist
        $allowed_extensions = [
            'pdf', 'doc', 'docx', 'xls', 'xlsx',
            'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip'
        ];
    }
    
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowed_extensions);
}

/**
 * Validate MIME type against whitelist
 * 
 * @param string $file_path Path to uploaded file
 * @param array $allowed_mimes Associative array of MIME type => extension
 * @return bool True if MIME type is allowed, false otherwise
 */
function isAllowedMimeType($file_path, $allowed_mimes = []) {
    if (!file_exists($file_path)) {
        return false;
    }
    
    if (empty($allowed_mimes)) {
        // Default MIME type whitelist
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
    }
    
    // Use finfo for reliable MIME type detection
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return true; // Fail open if finfo not available
    }
    
    $mime_type = @finfo_file($finfo, $file_path);
    @finfo_close($finfo);
    
    if ($mime_type === false) {
        return false;
    }
    
    return isset($allowed_mimes[$mime_type]);
}

/**
 * Generate a safe filename for upload
 * 
 * @param string $original_filename Original filename from upload
 * @return string Safe filename with timestamp and random component
 */
function generateSafeFilename($original_filename) {
    $ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    $random = bin2hex(random_bytes(8));
    $timestamp = time();
    
    return "upload_{$timestamp}_{$random}.{$ext}";
}

/**
 * Validate that uploaded file matches its extension and MIME type
 * 
 * @param string $file_path Path to file
 * @param string $filename Original filename
 * @param array $allowed_extensions List of allowed extensions
 * @param array $allowed_mimes Associative array of MIME type => extension
 * @return array Array with 'valid' bool and 'error' message if invalid
 */
function validateUploadedFile($file_path, $filename, $allowed_extensions = [], $allowed_mimes = []) {
    // Validate filename format
    if (!isValidFilename($filename)) {
        return [
            'valid' => false,
            'error' => 'Invalid filename format'
        ];
    }
    
    // Validate extension is allowed
    if (!isAllowedExtension($filename, $allowed_extensions)) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        return [
            'valid' => false,
            'error' => "File type not allowed: {$ext}"
        ];
    }
    
    // Validate MIME type
    if (!isAllowedMimeType($file_path, $allowed_mimes)) {
        return [
            'valid' => false,
            'error' => 'File MIME type does not match extension'
        ];
    }
    
    return [
        'valid' => true,
        'error' => null
    ];
}
?>
