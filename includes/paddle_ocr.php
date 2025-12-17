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
 */
function extractTextWithFallback($imagePath) {
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
    $tesseractPath = trim(shell_exec('which tesseract 2>/dev/null') ?: '');
    if (!empty($tesseractPath)) {
        $tempOutput = sys_get_temp_dir() . '/ocr_' . uniqid();
        $command = escapeshellcmd($tesseractPath) . ' ' . escapeshellarg($imagePath) . ' ' . escapeshellarg($tempOutput);
        exec($command . ' 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            $outputFile = $tempOutput . '.txt';
            if (file_exists($outputFile)) {
                $text = file_get_contents($outputFile);
                @unlink($outputFile);
                @unlink($tempOutput);
                
                return [
                    'status' => 'success',
                    'text' => $text,
                    'method' => 'tesseract',
                    'confidence_note' => 'Moderate accuracy - Tesseract'
                ];
            }
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

?>
