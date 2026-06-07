<?php
/**
 * Ticket Extraction API
 * Extracts ticket information from PDF files using pattern matching
 * Supports: Kam Air, Ariana Afghan Airlines, and standard IATA formats
 */

require_once '../../vendor/autoload.php';
require_once '../../includes/ticket_patterns.php';

use Smalot\PdfParser\Parser;

header('Content-Type: application/json');

/**
 * Ensure all data is JSON-serializable
 */
function ensureJsonSerializable(&$data) {
    if (is_array($data)) {
        foreach ($data as &$value) {
            if (is_array($value)) {
                ensureJsonSerializable($value);
            } elseif (is_object($value)) {
                // Convert objects to arrays
                $value = (array)$value;
                ensureJsonSerializable($value);
            } elseif ($value === null || is_scalar($value)) {
                // Keep as is - these are JSON-serializable
            } else {
                // For anything else, convert to string
                $value = (string)$value;
            }
        }
    }
    return $data;
}

/**
 * Sanitize UTF-8 strings in array
 */
function sanitizeUtf8(&$data) {
    if (is_array($data)) {
        foreach ($data as &$value) {
            if (is_array($value)) {
                sanitizeUtf8($value);
            } elseif (is_string($value)) {
                // Remove invalid UTF-8 sequences
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                // Alternative: use iconv
                // $value = iconv('UTF-8', 'UTF-8//IGNORE', $value);
            }
        }
    }
    return $data;
}

// Handle PDF file upload
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No PDF file uploaded or upload error']);
    exit;
}

$file = $_FILES['pdf_file'];

// Validate file type
$fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($fileExt !== 'pdf') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File must be a PDF']);
    exit;
}

$mimeType = mime_content_type($file['tmp_name']);
if ($mimeType !== 'application/pdf' && $mimeType !== 'application/x-pdf') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid PDF file']);
    exit;
}

// Validate file size (max 10MB)
if ($file['size'] > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
    exit;
}

try {
    // Extract text from PDF — prefer pdftotext -layout for column-aligned output
    $extractedText = '';
    
    // Find pdftotext binary (common Windows + Linux paths)
    $pdftotextBin = '';
    $candidates = ['C:\\xampp\\php\\pdftotext.exe', 'pdftotext', '/usr/bin/pdftotext', '/usr/local/bin/pdftotext'];
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) { $pdftotextBin = $candidate; break; }
        $test = trim(shell_exec("where $candidate 2>NUL || which $candidate 2>/dev/null"));
        if (!empty($test)) { $pdftotextBin = $candidate; break; }
    }
    
    if (!empty($pdftotextBin)) {
        $tmpFile = $file['tmp_name'];
        $escapedFile = escapeshellarg($tmpFile);
        $output = shell_exec("$pdftotextBin -layout $escapedFile - 2>/dev/null");
        if ($output !== null && strlen(trim($output)) > 0) {
            $extractedText = $output;
        }
    }
    
    // Fallback: Smalot\PdfParser
    if (empty(trim($extractedText))) {
        $parser = new Parser();
        $pdf = $parser->parseFile($file['tmp_name']);
        foreach ($pdf->getPages() as $page) {
            $extractedText .= $page->getText() . "\n";
        }
    }
    
    if (empty(trim($extractedText))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Could not extract text from PDF']);
        exit;
    }
    
     // Extract ticket data using patterns
      $ticketData = extractTicketData($extractedText);
     
     // Ensure all values are JSON-serializable
     $ticketData = ensureJsonSerializable($ticketData);
     
     // Prepare response with consistent format
     $response = [
         'success' => true,
         'message' => 'Ticket extracted successfully',
         'data' => $ticketData,
         'format_detected' => $ticketData['format_detected'] ?? 'unknown',
         'extracted_text_preview' => substr($extractedText, 0, 300),
     ];
     
     // Calculate confidence score from data
     $confidence = 0;
     if (isset($ticketData['extraction_confidence'])) {
         $confidence = $ticketData['extraction_confidence'];
     } elseif (isset($ticketData['passengers']) && !empty($ticketData['passengers'])) {
         // Use average confidence from all passengers
         $totalConfidence = 0;
         foreach ($ticketData['passengers'] as $passenger) {
             if (isset($passenger['extraction_confidence'])) {
                 $totalConfidence += $passenger['extraction_confidence'];
             }
         }
         $confidence = !empty($ticketData['passengers']) ? $totalConfidence / count($ticketData['passengers']) : 0;
     }
     $response['confidence'] = round($confidence, 2);
     
     // Verify JSON can be encoded
     $json = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
     if ($json === false) {
         // Fallback: sanitize UTF-8 and retry
         $response = sanitizeUtf8($response);
         $json = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
         if ($json === false) {
             throw new Exception('JSON encoding error: ' . json_last_error_msg());
         }
     }
     
     http_response_code(200);
     echo $json;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'PDF processing error: ' . $e->getMessage()
    ]);
}
?>
