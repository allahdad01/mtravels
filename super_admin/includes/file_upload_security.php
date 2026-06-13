<?php
/**
 * File Upload Security Library
 * 
 * Provides secure file upload validation with MIME type checking,
 * image verification, and safe filename generation.
 */

class FileUploadSecurity {
    
    // Allowed MIME types for different file categories
    const ALLOWED_MIMES = [
        'image' => [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp']
        ],
        'document' => [
            'application/pdf' => ['pdf'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/vnd.ms-excel' => ['xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
            'text/plain' => ['txt'],
            'text/csv' => ['csv']
        ],
        'all' => [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'application/pdf' => ['pdf'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/vnd.ms-excel' => ['xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
            'text/plain' => ['txt'],
            'text/csv' => ['csv']
        ]
    ];
    
    /**
     * Validate uploaded file
     * 
     * @param array $file $_FILES array element
     * @param string $category File category (image, document, all)
     * @param int $maxSize Maximum file size in bytes
     * @return array ['success' => bool, 'error' => string, 'safe_name' => string]
     */
    public static function validateUpload($file, $category = 'all', $maxSize = 5242880) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'error' => self::getUploadErrorMessage($file['error'])
            ];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            return [
                'success' => false,
                'error' => 'File size exceeds maximum allowed size (' . 
                          self::formatBytes($maxSize) . ')'
            ];
        }
        
        // Check file size is not zero
        if ($file['size'] === 0) {
            return [
                'success' => false,
                'error' => 'File is empty'
            ];
        }
        
        // Get file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate MIME type
        $mimeResult = self::validateMimeType($file['tmp_name'], $ext, $category);
        if (!$mimeResult['success']) {
            return $mimeResult;
        }
        
        // For images, verify they are actually images
        if ($category === 'image' || in_array($mimeResult['mime'], array_keys(self::ALLOWED_MIMES['image']))) {
            if (!self::verifyImage($file['tmp_name'])) {
                return [
                    'success' => false,
                    'error' => 'File is not a valid image'
                ];
            }
        }
        
        // Generate safe filename
        $safeName = self::generateSafeFilename($ext);
        
        return [
            'success' => true,
            'safe_name' => $safeName,
            'mime' => $mimeResult['mime'],
            'ext' => $ext
        ];
    }
    
    /**
     * Validate MIME type of uploaded file
     * 
     * @param string $tmpPath Path to uploaded file
     * @param string $ext File extension
     * @param string $category File category
     * @return array ['success' => bool, 'error' => string, 'mime' => string]
     */
    private static function validateMimeType($tmpPath, $ext, $category = 'all') {
        // Use fileinfo to get actual MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
        
        if ($mimeType === false) {
            return [
                'success' => false,
                'error' => 'Could not determine file type'
            ];
        }
        
        // Check if category exists
        if (!isset(self::ALLOWED_MIMES[$category])) {
            $category = 'all';
        }
        
        $allowedMimes = self::ALLOWED_MIMES[$category];
        
        // Check if MIME type is allowed
        if (!isset($allowedMimes[$mimeType])) {
            return [
                'success' => false,
                'error' => 'File type not allowed: ' . htmlspecialchars($mimeType)
            ];
        }
        
        // Check if extension matches MIME type
        $allowedExts = $allowedMimes[$mimeType];
        if (!in_array($ext, $allowedExts)) {
            return [
                'success' => false,
                'error' => 'File extension does not match MIME type'
            ];
        }
        
        return [
            'success' => true,
            'mime' => $mimeType
        ];
    }
    
    /**
     * Verify that uploaded file is actually a valid image
     * 
     * @param string $tmpPath Path to uploaded file
     * @return bool True if valid image
     */
    private static function verifyImage($tmpPath) {
        // Use getimagesize to verify it's a real image
        $imageinfo = @getimagesize($tmpPath);
        
        if ($imageinfo === false) {
            return false;
        }
        
        // Additional check: verify MIME type from getimagesize
        $allowedTypes = [
            IMAGETYPE_JPEG,
            IMAGETYPE_PNG,
            IMAGETYPE_GIF,
            IMAGETYPE_WEBP
        ];
        
        return in_array($imageinfo[2], $allowedTypes);
    }
    
    /**
     * Generate cryptographically secure filename
     * 
     * @param string $extension File extension
     * @return string Safe filename
     */
    private static function generateSafeFilename($extension) {
        $hash = bin2hex(random_bytes(16));
        $timestamp = time();
        return 'file_' . $timestamp . '_' . $hash . '.' . $extension;
    }
    
    /**
     * Get user-friendly upload error message
     * 
     * @param int $errorCode Upload error code
     * @return string Error message
     */
    private static function getUploadErrorMessage($errorCode) {
        $errors = [
            UPLOAD_ERR_OK => 'No error',
            UPLOAD_ERR_INI_SIZE => 'File exceeds ini_set size limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form size limit',
            UPLOAD_ERR_PARTIAL => 'File upload was incomplete',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporary directory missing',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        return $errors[$errorCode] ?? 'Unknown upload error';
    }
    
    /**
     * Format bytes to human-readable format
     * 
     * @param int $bytes Number of bytes
     * @return string Formatted size
     */
    private static function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Safely move uploaded file to destination
     * 
     * @param string $tmpPath Path to temporary file
     * @param string $destPath Destination path
     * @param string $safeName Safe filename to use
     * @param int $webpQuality WebP quality (0=off)
     * @return array ['success' => bool, 'error' => string, 'path' => string]
     */
    public static function moveUploadedFile($tmpPath, $destPath, $safeName, $webpQuality = 85) {
        // Ensure destination directory exists
        if (!is_dir($destPath)) {
            if (!@mkdir($destPath, 0755, true)) {
                return [
                    'success' => false,
                    'error' => 'Failed to create upload directory'
                ];
            }
        }
        
        $fullPath = $destPath . '/' . $safeName;
        
        // Move the file
        if (!move_uploaded_file($tmpPath, $fullPath)) {
            return [
                'success' => false,
                'error' => 'Failed to move uploaded file'
            ];
        }
        
        // Verify file was moved
        if (!file_exists($fullPath)) {
            return [
                'success' => false,
                'error' => 'Uploaded file verification failed'
            ];
        }
        
        // Set proper permissions
        chmod($fullPath, 0644);
        
        // Auto-convert images to WebP
        $finalPath = $fullPath;
        $imageMimes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];
        $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        
        if ($webpQuality > 0 && in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) && function_exists('imagewebp')) {
            $imageinfo = @getimagesize($fullPath);
            if ($imageinfo !== false && in_array($imageinfo[2], $imageMimes)) {
                $webpPath = $destPath . '/' . pathinfo($safeName, PATHINFO_FILENAME) . '.webp';
                
                try {
                    switch ($imageinfo[2]) {
                        case IMAGETYPE_JPEG:
                            $img = @imagecreatefromjpeg($fullPath);
                            break;
                        case IMAGETYPE_PNG:
                            $img = @imagecreatefrompng($fullPath);
                            break;
                        case IMAGETYPE_GIF:
                            $img = @imagecreatefromgif($fullPath);
                            break;
                    }
                    
                    if (isset($img) && $img !== false) {
                        if ($imageinfo[2] === IMAGETYPE_PNG) {
                            imagepalettetotruecolor($img);
                            imagealphablending($img, true);
                            imagesavealpha($img, false);
                        }
                        
                        if (imagewebp($img, $webpPath, $webpQuality)) {
                            chmod($webpPath, 0644);
                            unlink($fullPath);
                            $finalPath = $webpPath;
                        }
                        imagedestroy($img);
                    }
                } catch (Exception $e) {
                    // Fallback: keep original
                }
            }
        }
        
        return [
            'success' => true,
            'path' => $finalPath,
            'url' => str_replace($destPath, '', $finalPath)
        ];
    }
}

?>
