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
    // Extract text from PDF
    $parser = new Parser();
    $pdf = $parser->parseFile($file['tmp_name']);
    
    // Extract text from all pages
    $extractedText = '';
    foreach ($pdf->getPages() as $page) {
        $extractedText .= $page->getText() . "\n";
    }
    
    if (empty(trim($extractedText))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Could not extract text from PDF']);
        exit;
    }
    
    // Extract ticket data using patterns
    $ticketData = extractTicketData($extractedText);
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => 'Ticket extracted successfully',
        'data' => $ticketData,
        'format_detected' => $ticketData['format_detected'] ?? 'unknown',
        'extracted_text_preview' => substr($extractedText, 0, 200) . '...',
    ];
    
    // Add confidence score
    if (isset($ticketData['extraction_confidence'])) {
        $response['confidence'] = $ticketData['extraction_confidence'];
    } elseif (isset($ticketData['passengers'][0]['extraction_confidence'])) {
        $response['confidence'] = $ticketData['passengers'][0]['extraction_confidence'];
    } else {
        $response['confidence'] = 0;
    }
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'PDF processing error: ' . $e->getMessage()
    ]);
}
?>
