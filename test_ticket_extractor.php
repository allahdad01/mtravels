<?php
/**
 * Test Page for Ticket Info Extractor
 * Tests the ticket_patterns.php extraction system
 * Supports PDF uploads and text input
 */

require_once 'includes/ticket_patterns.php';
require_once 'vendor/autoload.php';

use Smalot\PdfParser\Parser;

/**
 * Extract text from PDF file
 */
function extractTextFromPDF($filePath) {
    try {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $text = '';
        
        foreach ($pdf->getPages() as $page) {
            $text .= $page->getText() . "\n";
        }
        
        return trim($text);
    } catch (Exception $e) {
        throw new Exception('Error reading PDF: ' . $e->getMessage());
    }
}

// Sample test data
$testCases = [
    // Test Case 1: Kam Air Single Passenger
    'kamair_single' => [
        'name' => 'Kam Air - Single Passenger',
        'text' => <<<'TEXT'
ALMOQADAS_TRAVEL - TICKET BOOKING
TICKET NUMBER 2250123456789
BOOKING REFERENCE ABC123
PASSENGER NAME AHMADI / MOHAMMAD (02AUG96)
CARRIER CODE RQ
FLIGHT NO. 101

FLIGHT 1 - Kabul (KBL) to Herat (HEA)
DEP. TIME Kabul (KBL) Kabul 14/12/2025 09:30
ARR. TIME Herat (HEA) Herat 14/12/2025 11:00
DEP TERMINAL 1
ARR TERMINAL A
REZ. CLASS Y
TICKET STATUS OK
BAG 20 kg
SEAT 12A
FARE BASIS YBEW001
NVB 14DEC2025
NVA 20DEC2025
9DEC2025 12345678
ISSUED BY ALMOQADAS TRAVEL
TEXT
    ],
    
    // Test Case 2: Kam Air Multiple Passengers
    'kamair_group' => [
        'name' => 'Kam Air - Group Booking (2 passengers)',
        'text' => <<<'TEXT'
ALMOQADAS_TRAVEL - GROUP BOOKING
BOOKING REFERENCE XYZ789

TICKET NUMBER 2250123456790
BOOKING REFERENCE XYZ789
PASSENGER NAME KHAN / FATIMA (15JAN00)
CARRIER CODE RQ
FLIGHT NO. 101

FLIGHT 1 - Kabul (KBL) to Dubai (DXB)
DEP. TIME Kabul (KBL) Kabul 20/12/2025 14:30
ARR. TIME Dubai (DXB) Dubai 20/12/2025 18:15
DEP TERMINAL 1
ARR TERMINAL 3
REZ. CLASS Y
TICKET STATUS OK
BAG 25 kg
SEAT 14B
FARE BASIS YBEW001
NVB 20DEC2025
NVA 27DEC2025
10DEC2025 12345679
ISSUED BY ALMOQADAS TRAVEL

TICKET NUMBER 2250123456791
BOOKING REFERENCE XYZ789
PASSENGER NAME KHAN / AHMED (18MAR97)
CARRIER CODE RQ
FLIGHT NO. 101

FLIGHT 1 - Kabul (KBL) to Dubai (DXB)
DEP. TIME Kabul (KBL) Kabul 20/12/2025 14:30
ARR. TIME Dubai (DXB) Dubai 20/12/2025 18:15
DEP TERMINAL 1
ARR TERMINAL 3
REZ. CLASS Y
TICKET STATUS OK
BAG 23 kg
SEAT 14C
FARE BASIS YBEW001
NVB 20DEC2025
NVA 27DEC2025
10DEC2025 12345680
ISSUED BY ALMOQADAS TRAVEL
TEXT
    ],
    
    // Test Case 3: Ariana Afghan Airlines
    'ariana_format' => [
        'name' => 'Ariana Afghan Airlines E-Ticket',
        'text' => <<<'TEXT'
e-ticket
BOOKING #
QYCJIV
Passengers
MRS GEETA MOHIBZADA
255 1019 951 835
MR MANSOUR KARIMEE
255 1019 951 836
Travel Itinerary
Thursday
18-DEC
FG-252 ECONOMY 737

Ticket Details
ticket # / coupon flight route date fare family fare basis price status
MRS GEETA MOHIBZADA
255 1019 951 835 / 1 FG-252 HEA-KBL 18 Dec 2025 Basic (20kg) 20kg LBASIC20 AFN 6,542 OK
MR MANSOUR KARIMEE
255 1019 951 836 / 1 FG-252 HEA-KBL 18 Dec 2025 Basic (20kg) 20kg LBASIC20 AFN 6,542 OK

HEA-KBL
Seat: Any
HEA-KBL
Seat: Any
HEA
14:15
terminal: i
KBL
15:30
terminal: d
Herat to Kabul

Reserved on December 9, 2025 at 11:44 AM
Ticketed on December 9, 2025 at 11:46 AM
Booked By:
Al Moqadas Tourist & Travel Agency
TEXT
    ],
    
    // Test Case 4: Standard Format
    'standard_format' => [
        'name' => 'Standard IATA Format',
        'text' => <<<'TEXT'
BOOKING CONFIRMATION
PNR: QWE456
Ticket Number: 1175123456789
Passenger: AKBAR SHAHID
Flight: EK 102
Route: KBL - DXB
Departure: 25/12/2025
TEXT
    ],
];

$testMode = $_GET['test'] ?? null;
$pdfError = null;
$pdfText = null;
$pdfResult = null;

// Handle PDF upload - process at the top level
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_pdf'])) {
    try {
        if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No file uploaded. Error code: ' . ($_FILES['pdf_file']['error'] ?? 'unknown'));
        }
        
        $file = $_FILES['pdf_file'];
        
        // Validate file type by extension and mime
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($fileExt !== 'pdf') {
            throw new Exception('File must be a PDF (.' . htmlspecialchars($fileExt) . ' detected)');
        }
        
        $mimeType = mime_content_type($file['tmp_name']);
        if ($mimeType !== 'application/pdf' && $mimeType !== 'application/x-pdf') {
            throw new Exception('File must be a PDF. Detected MIME: ' . htmlspecialchars($mimeType));
        }
        
        // Validate file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('File size (' . round($file['size'] / 1024 / 1024, 2) . 'MB) exceeds 10MB limit');
        }
        
        // Create temp directory if needed
        $uploadDir = sys_get_temp_dir() . '/ticket_uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Move file to temp location
        $fileName = uniqid('ticket_') . '.pdf';
        $filePath = $uploadDir . $fileName;
        
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        // Extract text from PDF
        $pdfText = extractTextFromPDF($filePath);
        $pdfResult = extractTicketData($pdfText);
        
        // Clean up
        unlink($filePath);
        
    } catch (Exception $e) {
        $pdfError = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Extractor Test Page</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .test-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .test-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            text-decoration: none;
            color: #333;
            border: 2px solid transparent;
        }
        
        .test-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
            border-color: #667eea;
        }
        
        .test-card h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .test-card p {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .results-container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .back-button {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border-radius: 4px;
            text-decoration: none;
            transition: background 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .back-button:hover {
            background: #764ba2;
        }
        
        .test-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }
        
        .input-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 25px;
        }
        
        .input-section h3 {
            font-size: 16px;
            color: #333;
            margin-bottom: 15px;
        }
        
        .input-section textarea {
            width: 100%;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.5;
            background: white;
            resize: vertical;
            min-height: 200px;
        }
        
        .output-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-top: 25px;
        }
        
        .output-section h3 {
            font-size: 16px;
            color: #333;
            margin-bottom: 15px;
        }
        
        .output-panel {
            background: white;
            padding: 20px;
            border-radius: 4px;
            border-left: 4px solid #667eea;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            color: #333;
            max-height: 600px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: white;
        }
        
        .data-table th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
        }
        
        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .data-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .confidence-score {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .confidence-high {
            background: #d4edda;
            color: #155724;
        }
        
        .confidence-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .confidence-low {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-ok {
            background: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            background: #e7f3ff;
            color: #004085;
            border-radius: 20px;
            font-size: 12px;
            margin-right: 8px;
        }
        
        .json-output {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 4px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .json-key { color: #9cdcfe; }
        .json-string { color: #ce9178; }
        .json-number { color: #b5cea8; }
        .json-null { color: #569cd6; }
        .json-bool { color: #569cd6; }
        
        .tab-container {
            margin: 25px 0;
        }
        
        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        
        .tab-button {
            padding: 10px 20px;
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            margin-bottom: -2px;
        }
        
        .tab-button.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #f5c6cb;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #c3e6cb;
        }
        
        .info-box {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            border-left: 4px solid #bee5eb;
        }
        
        .form-group {
            margin: 20px 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            resize: vertical;
            min-height: 150px;
        }
        
        .submit-button {
            padding: 12px 30px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        
        .submit-button:hover {
            background: #764ba2;
        }
        
        .footer {
            margin-top: 50px;
            padding: 20px;
            text-align: center;
            color: white;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($testMode): ?>
            <!-- Results View -->
            <div class="results-container">
                <a href="test_ticket_extractor.php" class="back-button">← Back to Tests</a>
                
                <?php
                    $testData = $testCases[$testMode] ?? null;
                    
                    if (!$testData) {
                        echo '<div class="error-message">Test case not found.</div>';
                    } else {
                        echo '<h2 class="test-title">' . htmlspecialchars($testData['name']) . '</h2>';
                        
                        // Extract data
                        $result = extractTicketData($testData['text']);
                        $confidence = isset($result['extraction_confidence']) 
                            ? $result['extraction_confidence'] 
                            : (isset($result['passengers'][0]['extraction_confidence']) 
                                ? $result['passengers'][0]['extraction_confidence'] 
                                : 0);
                        
                        // Confidence badge
                        $confidencePercent = round($confidence * 100);
                        $confidenceClass = $confidencePercent >= 80 ? 'confidence-high' : ($confidencePercent >= 50 ? 'confidence-medium' : 'confidence-low');
                        
                        echo '<div class="info-box">
                            <strong>Extraction Confidence:</strong> 
                            <span class="confidence-score ' . $confidenceClass . '">' . $confidencePercent . '%</span>
                        </div>';
                        
                        // Tab Interface
                        echo '<div class="tab-container">
                            <div class="tab-buttons">
                                <button class="tab-button active" onclick="switchTab(this, \'formatted\')">Formatted View</button>
                                <button class="tab-button" onclick="switchTab(this, \'raw\')">Raw Data</button>
                                <button class="tab-button" onclick="switchTab(this, \'json\')">JSON Output</button>
                                <button class="tab-button" onclick="switchTab(this, \'input\')">Input Text</button>
                            </div>
                            
                            <!-- Formatted View Tab -->
                            <div id="formatted" class="tab-content active">
                                ' . renderFormattedOutput($result) . '
                            </div>
                            
                            <!-- Raw Data Tab -->
                            <div id="raw" class="tab-content">
                                ' . renderRawOutput($result) . '
                            </div>
                            
                            <!-- JSON Output Tab -->
                            <div id="json" class="tab-content">
                                <div class="json-output" id="json-pretty"></div>
                                <script>
                                    document.getElementById("json-pretty").textContent = JSON.stringify(' . json_encode($result) . ', null, 2);
                                </script>
                            </div>
                            
                            <!-- Input Text Tab -->
                            <div id="input" class="tab-content">
                                <div class="output-panel">' . htmlspecialchars($testData['text']) . '</div>
                            </div>
                        </div>';
                    }
                ?>
            </div>
        <?php else: ?>
            <!-- Main Menu View -->
            <div class="header">
                <h1>🎫 Ticket Extractor Test Suite</h1>
                <p>Test the ticket info extraction system with various ticket formats and scenarios</p>
            </div>
            
            <div class="test-selector">
                <?php foreach ($testCases as $key => $test): ?>
                    <a href="?test=<?php echo urlencode($key); ?>" class="test-card">
                        <h3><?php echo htmlspecialchars($test['name']); ?></h3>
                        <p>Click to run this test case and view extraction results</p>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <!-- Custom Test Section -->
            <div class="results-container">
                <h2 class="test-title">Custom Ticket Input</h2>
                
                <!-- PDF Upload Section -->
                <div style="background: #f0f7ff; border: 2px dashed #667eea; border-radius: 8px; padding: 30px; text-align: center; margin-bottom: 30px;">
                    <h3 style="color: #667eea; margin-bottom: 10px;">📄 Upload E-Ticket PDF</h3>
                    <p style="color: #666; margin-bottom: 20px; font-size: 14px;">Upload a PDF file to automatically extract ticket information</p>
                    
                    <form method="POST" enctype="multipart/form-data" id="pdf-form">
                        <div style="position: relative; display: inline-block; width: 100%; max-width: 400px;">
                            <input type="file" id="pdf_file" name="pdf_file" accept=".pdf" style="display: none;" onchange="handleFileSelect()">
                            <label for="pdf_file" style="
                                display: block;
                                padding: 20px;
                                background: white;
                                border: 2px solid #667eea;
                                border-radius: 6px;
                                cursor: pointer;
                                transition: all 0.3s ease;
                                font-weight: 500;
                                color: #667eea;
                            " id="file-label" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='white'">
                                📁 Click to select PDF or drag & drop
                            </label>
                        </div>
                        <div id="file-name" style="margin-top: 10px; color: #666; font-size: 13px;"></div>
                        <button type="submit" name="upload_pdf" class="submit-button" style="margin-top: 15px; display: none;" id="pdf-submit">Extract from PDF</button>
                    </form>
                    
                    <script>
                        const pdfInput = document.getElementById('pdf_file');
                        const pdfForm = document.getElementById('pdf-form');
                        const fileLabel = document.getElementById('file-label');
                        const fileNameDiv = document.getElementById('file-name');
                        const pdfSubmit = document.getElementById('pdf-submit');
                        
                        // Drag and drop
                        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                            pdfForm.addEventListener(eventName, preventDefaults, false);
                        });
                        
                        function preventDefaults(e) {
                            e.preventDefault();
                            e.stopPropagation();
                        }
                        
                        ['dragenter', 'dragover'].forEach(eventName => {
                            pdfForm.addEventListener(eventName, () => {
                                fileLabel.style.background = '#e7f3ff';
                                fileLabel.style.borderColor = '#004085';
                            });
                        });
                        
                        ['dragleave', 'drop'].forEach(eventName => {
                            pdfForm.addEventListener(eventName, () => {
                                fileLabel.style.background = 'white';
                                fileLabel.style.borderColor = '#667eea';
                            });
                        });
                        
                        pdfForm.addEventListener('drop', e => {
                            const dt = e.dataTransfer;
                            const files = dt.files;
                            pdfInput.files = files;
                            handleFileSelect();
                        });
                        
                        function handleFileSelect() {
                            const file = pdfInput.files[0];
                            if (file) {
                                if (file.type === 'application/pdf') {
                                    fileNameDiv.textContent = '✓ Selected: ' + file.name + ' (' + (file.size / 1024).toFixed(2) + ' KB)';
                                    fileNameDiv.style.color = '#28a745';
                                    pdfSubmit.style.display = 'inline-block';
                                } else {
                                    fileNameDiv.textContent = '✗ Please select a PDF file';
                                    fileNameDiv.style.color = '#dc3545';
                                    pdfSubmit.style.display = 'none';
                                }
                            }
                        }
                    </script>
                </div>
                
                <!-- Text Input Section -->
                <h3 style="margin: 30px 0 15px 0; color: #333;">Or paste ticket text directly:</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="custom_text">Ticket Text:</label>
                        <textarea id="custom_text" name="custom_text" placeholder="Paste ticket text here..."></textarea>
                    </div>
                    <button type="submit" name="test_custom" class="submit-button">Extract Ticket Info</button>
                </form>
                
                <?php
                    // Display PDF processing results
                    if ($pdfError) {
                        echo '<div class="error-message"><strong>PDF Error:</strong> ' . htmlspecialchars($pdfError) . '</div>';
                    }
                    
                    if ($pdfResult) {
                        $customConfidence = isset($pdfResult['extraction_confidence']) 
                            ? $pdfResult['extraction_confidence'] 
                            : (isset($pdfResult['passengers'][0]['extraction_confidence']) 
                                ? $pdfResult['passengers'][0]['extraction_confidence'] 
                                : 0);
                        
                        $confidencePercent = round($customConfidence * 100);
                        $confidenceClass = $confidencePercent >= 80 ? 'confidence-high' : ($confidencePercent >= 50 ? 'confidence-medium' : 'confidence-low');
                        
                        echo '
                        <div style="margin-top: 30px; padding-top: 30px; border-top: 2px solid #eee;">
                            <h3 style="margin-bottom: 15px; color: #333;">📄 PDF Extraction Results</h3>
                            <div class="badge">PDF UPLOAD</div>
                            <div class="info-box">
                                <strong>Confidence Score:</strong> 
                                <span class="confidence-score ' . $confidenceClass . '">' . $confidencePercent . '%</span>
                            </div>
                            
                            <div class="tab-container">
                                <div class="tab-buttons">
                                    <button class="tab-button active" onclick="switchTab(this, \'pdf-formatted\')">Formatted View</button>
                                    <button class="tab-button" onclick="switchTab(this, \'pdf-raw\')">Raw Data</button>
                                    <button class="tab-button" onclick="switchTab(this, \'pdf-json\')">JSON Output</button>
                                    <button class="tab-button" onclick="switchTab(this, \'pdf-text\')">Extracted Text</button>
                                </div>
                                
                                <div id="pdf-formatted" class="tab-content active">
                                    ' . renderFormattedOutput($pdfResult) . '
                                </div>
                                
                                <div id="pdf-raw" class="tab-content">
                                    ' . renderRawOutput($pdfResult) . '
                                </div>
                                
                                <div id="pdf-json" class="tab-content">
                                    <div class="json-output" id="pdf-json-pretty"></div>
                                    <script>
                                        document.getElementById("pdf-json-pretty").textContent = JSON.stringify(' . json_encode($pdfResult) . ', null, 2);
                                    </script>
                                </div>
                                
                                <div id="pdf-text" class="tab-content">
                                    <div class="output-panel">' . htmlspecialchars($pdfText) . '</div>
                                </div>
                            </div>
                        </div>';
                    }
                    
                    if ($_POST['test_custom'] ?? false) {
                        $customText = $_POST['custom_text'] ?? '';
                        if (trim($customText)) {
                            $customResult = extractTicketData($customText);
                            $customConfidence = isset($customResult['extraction_confidence']) 
                                ? $customResult['extraction_confidence'] 
                                : (isset($customResult['passengers'][0]['extraction_confidence']) 
                                    ? $customResult['passengers'][0]['extraction_confidence'] 
                                    : 0);
                            
                            $confidencePercent = round($customConfidence * 100);
                            $confidenceClass = $confidencePercent >= 80 ? 'confidence-high' : ($confidencePercent >= 50 ? 'confidence-medium' : 'confidence-low');
                            
                            echo '
                            <div style="margin-top: 30px; padding-top: 30px; border-top: 2px solid #eee;">
                                <h3 style="margin-bottom: 15px;">Extraction Results</h3>
                                <div class="info-box">
                                    <strong>Confidence Score:</strong> 
                                    <span class="confidence-score ' . $confidenceClass . '">' . $confidencePercent . '%</span>
                                </div>
                                
                                <div class="tab-container">
                                    <div class="tab-buttons">
                                        <button class="tab-button active" onclick="switchTab(this, \'custom-formatted\')">Formatted View</button>
                                        <button class="tab-button" onclick="switchTab(this, \'custom-raw\')">Raw Data</button>
                                        <button class="tab-button" onclick="switchTab(this, \'custom-json\')">JSON Output</button>
                                    </div>
                                    
                                    <div id="custom-formatted" class="tab-content active">
                                        ' . renderFormattedOutput($customResult) . '
                                    </div>
                                    
                                    <div id="custom-raw" class="tab-content">
                                        ' . renderRawOutput($customResult) . '
                                    </div>
                                    
                                    <div id="custom-json" class="tab-content">
                                        <div class="json-output" id="custom-json-pretty"></div>
                                        <script>
                                            document.getElementById("custom-json-pretty").textContent = JSON.stringify(' . json_encode($customResult) . ', null, 2);
                                        </script>
                                    </div>
                                </div>
                            </div>';
                        } else {
                            echo '<div class="error-message">Please enter some ticket text to extract.</div>';
                        }
                    }
                ?>
            </div>
            
            <div class="footer">
                <p>Ticket Extractor Test Suite v1.0 | Supports Kam Air and IATA formats</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function switchTab(button, tabName) {
            // Hide all tabs in this container
            const container = button.closest('.tab-container');
            const tabs = container.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Remove active class from all buttons
            const buttons = container.querySelectorAll('.tab-button');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            // Show selected tab and activate button
            document.getElementById(tabName).classList.add('active');
            button.classList.add('active');
        }
    </script>
</body>
</html>

<?php

/**
 * Render formatted output
 */
function renderFormattedOutput($result) {
    $html = '';
    
    // Handle multi-segment bookings
    if (isset($result['is_multi_segment']) && $result['is_multi_segment']) {
        // Multi-segment booking view
        $html .= '<div class="badge">MULTI-SEGMENT</div>';
        $html .= '<div class="badge">Total: ' . $result['total_segments'] . ' segments</div><br><br>';
        
        // Booking overview
        $overviewData = [
            'booking_reference' => $result['booking_reference'] ?? null,
            'airline' => $result['airline'] ?? null,
            'cabin_class' => $result['cabin_class'] ?? null,
            'payment_reference' => $result['payment_reference'] ?? null,
            'total_segments' => $result['total_segments'] ?? null,
        ];
        
        $html .= '<h4 style="margin: 20px 0 15px 0; color: #333;">Booking Details</h4>';
        $html .= renderDataTable(array_filter($overviewData));
        
        // Passenger details
        if (!empty($result['passengers'])) {
            $first = $result['passengers'][0];
            // Only show if we have passenger name or PNR
            if (!empty($first['passenger_name']) || !empty($first['pnr'])) {
                $html .= '<h4 style="margin: 30px 0 15px 0; color: #333;">Passenger Details</h4>';
                $passengerInfo = [];
                if (!empty($first['passenger_name'])) {
                    $passengerInfo['passenger_name'] = $first['passenger_name'];
                }
                if (!empty($first['pnr'])) {
                    $passengerInfo['pnr'] = $first['pnr'];
                }
                $html .= renderDataTable($passengerInfo);
            }
        }
        
        // Flight segments
        $html .= '<h4 style="margin: 30px 0 15px 0; color: #333;">Flight Segments</h4>';
        foreach ($result['passengers'] as $idx => $segment) {
            $html .= '<div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #667eea;">';
            $html .= '<h5 style="margin-bottom: 15px; color: #667eea;">Segment ' . ($idx + 1) . ': ' . ($segment['origin'] ?? 'N/A') . ' → ' . ($segment['destination'] ?? 'N/A') . '</h5>';
            $html .= renderDataTable($segment);
            $html .= '</div>';
        }
    } elseif (isset($result['is_group_booking']) && $result['is_group_booking']) {
        // Group booking view
        $html .= '<div class="badge">GROUP BOOKING</div>';
        $html .= '<div class="badge">Total: ' . $result['total_passengers'] . ' passengers</div><br><br>';
        
        // Flight info
        $html .= '<h4 style="margin: 20px 0 15px 0; color: #333;">Flight Information</h4>';
        $html .= renderDataTable($result['flight_info']);
        
        // Passengers
        $html .= '<h4 style="margin: 30px 0 15px 0; color: #333;">Passengers</h4>';
        foreach ($result['passengers'] as $idx => $passenger) {
            $html .= '<div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 6px;">';
            $html .= '<h5 style="margin-bottom: 15px; color: #667eea;">Passenger ' . ($idx + 1) . '</h5>';
            $html .= renderDataTable($passenger);
            $html .= '</div>';
        }
    } else {
        // Single passenger view
        $format = $result['format_detected'] ?? 'unknown';
        $html .= '<div class="badge">' . strtoupper($format) . ' FORMAT</div><br><br>';
        $html .= renderDataTable($result);
    }
    
    return $html;
}

/**
 * Render raw output
 */
function renderRawOutput($result) {
    ob_start();
    echo '<div class="output-panel">';
    print_r($result);
    echo '</div>';
    return ob_get_clean();
}

/**
 * Render data as table
 */
function renderDataTable($data) {
    if (empty($data)) {
        return '<p style="color: #999;">No data available</p>';
    }
    
    $html = '<table class="data-table">';
    $html .= '<tr><th style="width: 35%;">Field</th><th>Value</th></tr>';
    
    foreach ($data as $key => $value) {
        if (is_array($value) || is_object($value)) {
            continue;
        }
        
        if (empty($value) && $value !== 0 && $value !== false) {
            continue;
        }
        
        $label = ucwords(str_replace('_', ' ', $key));
        $displayValue = $value;
        
        // Format specific values
        if ($key === 'extraction_confidence') {
            $percent = round($value * 100);
            $class = $percent >= 80 ? 'confidence-high' : ($percent >= 50 ? 'confidence-medium' : 'confidence-low');
            $displayValue = '<span class="confidence-score ' . $class . '">' . $percent . '%</span>';
        } elseif ($key === 'is_confirmed') {
            $displayValue = $value ? '<span class="status-ok">Confirmed</span>' : 'Not Confirmed';
        }
        
        $html .= '<tr><td><strong>' . htmlspecialchars($label) . '</strong></td><td>' . htmlspecialchars($displayValue, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }
    
    $html .= '</table>';
    return $html;
}
?>
