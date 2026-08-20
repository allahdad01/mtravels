<?php
/**
 * Text Extraction API
 * Extracts document data from OCR text using MRZ parsing and pattern matching
 * Supports:
 * - JSON POST: {"text": "ocr_text", "document_type": "passport"}
 * - Form Upload: document_file (binary), document_type
 * Called by client-side OCR (Tesseract.js in browser) or server-side extraction
 */

require_once '../../includes/document_patterns.php';
require_once '../../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/permissions.php';
require_permission('umrah.view');

use Smalot\PdfParser\Parser;

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$ocrText = null;
$documentType = null;
$ocrMethod = 'unknown';

// Check if JSON body (client-side OCR result)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    // Get JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['text']) || !isset($input['document_type'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing text or document_type in JSON']);
        exit;
    }
    
    $ocrText = $input['text'];
    $documentType = $input['document_type'];
    $ocrMethod = 'client-side-ocr';
    
} elseif (isset($_FILES['document_file']) && isset($_POST['document_type'])) {
    // File upload (multipart form data)
    $file = $_FILES['document_file'];
    $documentType = $_POST['document_type'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File upload error']);
        exit;
    }
    
    $mimeType = mime_content_type($file['tmp_name']);
    
    try {
        if ($mimeType === 'application/pdf' || $mimeType === 'application/x-pdf') {
            // Extract from PDF
            $parser = new Parser();
            $pdf = $parser->parseFile($file['tmp_name']);
            $ocrText = '';
            foreach ($pdf->getPages() as $page) {
                $ocrText .= $page->getText() . "\n";
            }
            $ocrMethod = 'pdf-parser';
        } elseif (in_array($mimeType, ['image/jpeg', 'image/png', 'image/jpg'])) {
            // For images, use server-side PaddleOCR (same as test_document_extractor.php)
            $ocrText = extractTextViaPaddleOCR($file['tmp_name']);
            if (empty($ocrText)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Could not extract text from image. PaddleOCR may not be available.']);
                exit;
            }
            $ocrMethod = 'paddleocr-server';
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unsupported file type']);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File processing error: ' . $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing text or document_file and document_type']);
    exit;
}

// Validate document type
if (!in_array($documentType, ['passport', 'id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid document type']);
    exit;
}

try {
    // Validate OCR text
    if (empty(trim($ocrText))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No text extracted from document']);
        exit;
    }
    
    // Extract data using appropriate function (MRZ parsing is primary method)
    if ($documentType === 'passport') {
        $data = extractPassportData($ocrText);
    } else {
        $data = extractIDData($ocrText);
    }
    
    // Log extraction method for debugging
    $extractionMethod = $data['extraction_method'] ?? 'unknown';
    $confidence = $data['extraction_confidence'] ?? 0;
    
    // Return response with metadata
    $response = [
        'success' => true,
        'message' => 'Document extracted successfully',
        'data' => $data,
        'document_type' => $documentType,
        'extraction_method' => $extractionMethod,
        'ocr_method' => $ocrMethod,
        'confidence' => $confidence,
        'mrz_valid' => $data['mrz_valid'] ?? false,
    ];
    
    // Include MRZ debug info if available
    if (isset($data['mrz_debug']) && !empty($data['mrz_debug'])) {
        $response['mrz_debug'] = $data['mrz_debug'];
    }
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Extraction error: ' . $e->getMessage()
    ]);
}
?>
