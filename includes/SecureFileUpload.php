<?php
/**
 * Secure File Upload Handler
 * Validates MIME types, file sizes, and prevents malicious uploads
 * 
 * @package MTravels
 * @author Security Team
 */

class SecureFileUpload {
    private $allowed_mimes = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'text/plain' => ['txt'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx']
    ];
    
    private $max_size = 10485760; // 10MB default
    private $upload_dir = '../uploads/';
    private $errors = [];
    
    /**
     * Constructor
     * @param int $max_size Maximum file size in bytes
     * @param string $upload_dir Base upload directory
     */
    public function __construct($max_size = null, $upload_dir = null) {
        if ($max_size !== null) {
            $this->max_size = $max_size;
        }
        if ($upload_dir !== null) {
            $this->upload_dir = $upload_dir;
        }
    }
    
    /**
     * Validate and upload a single file
     * @param string $file_input_name Name of the file input from $_FILES
     * @param string $subdirectory Subdirectory to store file
     * @return array Result array with 'success' and either 'data' or 'error'
     */
    public function upload($file_input_name, $subdirectory = 'documents') {
        $this->errors = [];
        
        // Validate input
        if (!isset($_FILES[$file_input_name])) {
            return $this->error('No file provided');
        }
        
        $file = $_FILES[$file_input_name];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $this->error('Upload failed: ' . $this->getUploadErrorMessage($file['error']));
        }
        
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return $this->error('Invalid uploaded file');
        }
        
        // Validate file size
        if ($file['size'] > $this->max_size) {
            return $this->error('File too large. Maximum ' . $this->formatBytes($this->max_size) . ' allowed');
        }
        
        if ($file['size'] == 0) {
            return $this->error('File is empty');
        }
        
        // Get actual MIME type using finfo (not $_FILES which can be spoofed)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if ($mime === false) {
            return $this->error('Could not determine file type');
        }
        
        // Validate MIME type
        if (!in_array($mime, array_keys($this->allowed_mimes))) {
            return $this->error('File type not allowed. Allowed: ' . implode(', ', array_keys($this->allowed_mimes)));
        }
        
        // Sanitize and validate filename
        $original_name = basename($file['name']);
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        
        // Validate extension matches MIME type
        if (!in_array($extension, $this->allowed_mimes[$mime])) {
            return $this->error('File extension does not match file type');
        }
        
        // Generate secure filename
        $unique_name = $this->generateSecureFilename($extension);
        
        // Ensure subdirectory is safe
        $subdirectory = preg_replace('/[^a-zA-Z0-9_-]/', '', $subdirectory);
        
        // Create upload directory if needed
        $target_dir = realpath($this->upload_dir) . DIRECTORY_SEPARATOR . $subdirectory . DIRECTORY_SEPARATOR;
        
        if (!file_exists($target_dir)) {
            if (!@mkdir($target_dir, 0755, true)) {
                return $this->error('Could not create upload directory');
            }
        }
        
        $target_path = $target_dir . $unique_name;
        
        // Prevent directory traversal
        // Use dirname to get the directory part since the file doesn't exist yet
        $real_target_dir = realpath(dirname($target_path));
        $real_upload_base = realpath($target_dir);
        if ($real_target_dir === false || $real_upload_base === false || strpos($real_target_dir, $real_upload_base) !== 0) {
            return $this->error('Invalid file path detected');
        }
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            return $this->error('Failed to save file');
        }
        
        // Set proper file permissions (readable but not executable)
        chmod($target_path, 0644);
        
        // Log successful upload
        error_log("File uploaded: $unique_name by user " . ($_SESSION['user_id'] ?? 'unknown'));
        
        return $this->success([
            'filename' => $unique_name,
            'original_name' => $original_name,
            'path' => str_replace('\\', '/', $target_path),
            'mime' => $mime,
            'size' => $file['size'],
            'extension' => $extension
        ]);
    }
    
    /**
     * Upload multiple files
     * @param string $file_input_name Name of the file input from $_FILES
     * @param string $subdirectory Subdirectory to store files
     * @param int $max_files Maximum number of files allowed
     * @return array Result array
     */
    public function uploadMultiple($file_input_name, $subdirectory = 'documents', $max_files = 5) {
        if (!isset($_FILES[$file_input_name])) {
            return $this->error('No files provided');
        }
        
        $files = $_FILES[$file_input_name];
        
        if (!is_array($files['name'])) {
            return $this->error('Invalid file input format');
        }
        
        if (count($files['name']) > $max_files) {
            return $this->error('Too many files. Maximum ' . $max_files . ' allowed');
        }
        
        $results = [];
        
        for ($i = 0; $i < count($files['name']); $i++) {
            // Create temporary $_FILES entry for each file
            $_FILES['_temp_upload_'] = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
            
            $result = $this->upload('_temp_upload_', $subdirectory);
            $results[] = $result;
            
            // Stop if any file fails (optional - remove if you want to continue on errors)
            if (!$result['success']) {
                break;
            }
        }
        
        return $this->success(['files' => $results]);
    }
    
    /**
     * Generate cryptographically secure filename
     * @param string $extension File extension
     * @return string Secure filename
     */
    private function generateSecureFilename($extension) {
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        return "file_{$timestamp}_{$random}." . strtolower($extension);
    }
    
    /**
     * Get human-readable upload error message
     * @param int $code Error code from $_FILES
     * @return string Error message
     */
    private function getUploadErrorMessage($code) {
        $errors = [
            UPLOAD_ERR_OK => 'No error',
            UPLOAD_ERR_INI_SIZE => 'File exceeds php.ini upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload'
        ];
        
        return $errors[$code] ?? 'Unknown upload error';
    }
    
    /**
     * Format bytes to human readable size
     * @param int $bytes Bytes to format
     * @return string Formatted size
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Return success response
     * @param array $data Success data
     * @return array Response
     */
    private function success($data) {
        return [
            'success' => true,
            'data' => $data
        ];
    }
    
    /**
     * Return error response
     * @param string $message Error message
     * @return array Response
     */
    private function error($message) {
        error_log("File upload error: $message");
        return [
            'success' => false,
            'error' => $message
        ];
    }
    
    /**
     * Get last errors
     * @return array Errors
     */
    public function getErrors() {
        return $this->errors;
    }
}
?>
