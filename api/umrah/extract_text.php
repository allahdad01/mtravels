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
require_once '../../includes/gemini_passport.php';
require_once '../../includes/translation_engine.php';
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
            // Try Gemini AI first if API key is configured
            $geminiKey = getGeminiApiKey();
            if (!empty($geminiKey)) {
                $geminiResult = extractPassportWithGemini($file['tmp_name'], $mimeType);
                if ($geminiResult['success'] && $documentType === 'passport') {
                    // Gemini succeeded — build response directly
                    $gd = $geminiResult['data'];
                    // Parse MRZ lines if Gemini found them, for cross-validation
                    $mrzData = null;
                    if (!empty($gd['mrz_line1']) && !empty($gd['mrz_line2'])) {
                        $mrzData = parseMRZLines($gd['mrz_line1'] . "\n" . $gd['mrz_line2']);
                    }
                    $validation = $mrzData ? crossValidatePassport($gd, $mrzData) : null;
                    $data = [
                        'full_name'            => trim(($gd['given_names'] ?? '') . ' ' . ($gd['surname'] ?? '')),
                        'surname'              => $gd['surname'] ?? null,
                        'given_names'          => $gd['given_names'] ?? null,
                        'passport_number'      => $gd['passport_number'] ?? null,
                        'date_of_birth'        => $gd['date_of_birth'] ?? null,
                        'expiry_date'          => $gd['date_of_expiry'] ?? null,
                        'gender'               => $gd['gender'] ?? null,
                        'nationality'          => $gd['nationality'] ?? null,
                        'place_of_birth'       => $gd['place_of_birth'] ?? null,
                        'father_name'          => $gd['father_name'] ?? null,
                        'occupation'           => $gd['occupation'] ?? null,
                        'date_of_issue'        => $gd['date_of_issue'] ?? null,
                        'extraction_method'    => 'gemini-ai',
                        'extraction_confidence'=> $validation ? ($validation['confidence'] === 'high' ? 95 : ($validation['confidence'] === 'medium' ? 80 : 60)) : 70,
                        'mrz_valid'            => $mrzData ? ($mrzData['mrz_valid'] ?? false) : false,
                        'gemini_raw'           => $gd,
                        'mrz_cross_validation' => $validation,
                    ];
                    $response = [
                        'success'           => true,
                        'message'           => 'Passport extracted via Gemini AI',
                        'data'              => $data,
                        'document_type'     => $documentType,
                        'extraction_method' => 'gemini-ai',
                        'ocr_method'        => 'gemini-2.5-flash',
                        'confidence'        => $data['extraction_confidence'],
                        'mrz_valid'         => $data['mrz_valid'],
                        'cross_validation'  => $validation,
                    ];
                    http_response_code(200);
                    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                    // Pre-learn: save English ↔ Arabic name pair to dictionary
                    // Arabic script from passport applies to both Dari and Pashto
                    $engName = trim(($gd['given_names'] ?? '') . ' ' . ($gd['surname'] ?? ''));
                    $arabName = $gd['name_in_script'] ?? null;
                    if ($engName !== '' && $arabName !== null && $arabName !== '') {
                        save_learned($engName, $arabName, $arabName, 'passport');
                    }

                    exit;
                }
                // Gemini failed — fall through to PaddleOCR
            }
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
