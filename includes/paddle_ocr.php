<?php
/**
 * PaddleOCR Integration for Better Document Extraction
 * PaddleOCR provides superior accuracy compared to Tesseract
 * Install: pip install paddlepaddle paddleocr
 */

/**
 * DEPRECATED: Use the version in document_patterns.php instead
 * This file kept for backwards compatibility with fallback methods
 */

/**
 * Try multiple OCR methods in order of accuracy
 * PaddleOCR > Tesseract > Client-side Tesseract.js
 * SECURITY: Uses safe command execution to prevent injection
 */
function extractTextWithFallback($imagePath) {
    // Validate image path exists and is readable
    if (!is_file($imagePath) || !is_readable($imagePath)) {
        return [
            'status' => 'error',
            'message' => 'Image file not accessible',
            'text' => '',
            'method' => 'none'
        ];
    }
    
    $results = [];
    
    // Try 1: PaddleOCR (best accuracy)
    // Note: extractTextViaPaddleOCR returns a string directly (from document_patterns.php)
    $paddleResult = extractTextViaPaddleOCR($imagePath);
    if (is_string($paddleResult) && !empty($paddleResult)) {
        return [
            'status' => 'success',
            'text' => $paddleResult,
            'method' => 'paddleocr',
            'confidence_note' => 'High accuracy - PaddleOCR'
        ];
    }
    
    // Try 2: Tesseract (moderate accuracy)
    // SECURITY: Use safe Tesseract execution
    $tesseractPath = getTesseractPath();
    
    if ($tesseractPath) {
        $result = executeTesseractSafely($tesseractPath, $imagePath);
        if ($result['status'] === 'success') {
            return $result;
        }
    }
    
    // Fallback: Client-side OCR required
    return [
        'status' => 'ocr_required',
        'message' => 'Server-side OCR unavailable. Will use browser-based Tesseract.js',
        'text' => '',
        'method' => 'client-side'
    ];
}

/**
 * Get Tesseract path safely (no shell execution)
 * @return string|null Path to tesseract or null if not found
 */
function getTesseractPath() {
    // Define known Tesseract locations for different operating systems
    $known_paths = [
        '/usr/bin/tesseract',           // Linux
        '/usr/local/bin/tesseract',     // macOS
        '/opt/homebrew/bin/tesseract',  // macOS M1/M2
        'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',     // Windows default
        'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe' // Windows 32-bit
    ];
    
    // Check known paths
    foreach ($known_paths as $path) {
        if (file_exists($path) && is_executable($path)) {
            error_log("Found Tesseract at: $path");
            return $path;
        }
    }
    
    // Check PATH environment variable (safer than shell_exec)
    // But only for known executable names
    $path_env = explode(PATH_SEPARATOR, getenv('PATH') ?? '');
    foreach ($path_env as $dir) {
        $potential_path = $dir . DIRECTORY_SEPARATOR . 'tesseract';
        if (file_exists($potential_path) && is_executable($potential_path)) {
            error_log("Found Tesseract in PATH: $potential_path");
            return $potential_path;
        }
    }
    
    error_log("Tesseract not found in known locations");
    return null;
}

/**
 * Execute Tesseract safely using proc_open
 * @param string $tesseract_path Path to tesseract executable
 * @param string $image_path Path to image file
 * @return array Result array
 */
function executeTesseractSafely($tesseract_path, $image_path) {
    // Create temporary output file in system temp directory
    $temp_output = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_' . bin2hex(random_bytes(8));
    
    // Use proc_open for safer execution (no shell interpretation)
    $descriptorspec = [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w']   // stderr
    ];
    
    // Build command array (array form prevents shell injection)
    $process = proc_open(
        [$tesseract_path, $image_path, $temp_output],
        $descriptorspec,
        $pipes,
        null,
        null
    );
    
    if (!is_resource($process)) {
        error_log("Failed to execute Tesseract");
        return [
            'status' => 'error',
            'message' => 'Failed to execute OCR',
            'text' => '',
            'method' => 'none'
        ];
    }
    
    // Close stdin (not needed)
    fclose($pipes[0]);
    
    // Read output and error
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    // Get return code
    $return_code = proc_close($process);
    
    // Check if successful
    if ($return_code !== 0) {
        error_log("Tesseract error (code $return_code): $error");
        // Cleanup
        @unlink($temp_output);
        @unlink($temp_output . '.txt');
        
        return [
            'status' => 'error',
            'message' => 'OCR processing failed',
            'text' => '',
            'method' => 'tesseract'
        ];
    }
    
    // Read the output file
    $output_file = $temp_output . '.txt';
    if (file_exists($output_file)) {
        $text = file_get_contents($output_file);
        
        // Cleanup temporary files
        @unlink($output_file);
        @unlink($temp_output);
        
        return [
            'status' => 'success',
            'text' => $text,
            'method' => 'tesseract',
            'confidence_note' => 'Moderate accuracy - Tesseract'
        ];
    }
    
    error_log("Tesseract output file not created: $output_file");
    @unlink($temp_output);
    
    return [
        'status' => 'error',
        'message' => 'OCR output file not generated',
        'text' => '',
        'method' => 'tesseract'
    ];
}

?>
