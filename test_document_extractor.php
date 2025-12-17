<?php
/**
 * Test Page for Document Info Extractor
 * Tests the document_patterns.php extraction system
 * Supports PDF and Image uploads for Passport and ID documents
 */

require_once 'includes/document_patterns.php';
require_once 'vendor/autoload.php';

use Smalot\PdfParser\Parser;

/**
 * Extract text from PDF file
 * Returns text if text-based PDF, empty string if image-based
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
        // PDF parsing failed - might be image-based
        return '';
    }
}

/**
 * Render formatted output for documents
 */
function renderFormattedOutput($data) {
    $html = '<div class="output-panel">';
    
    // Show extraction method and confidence
    if (isset($data['extraction_method'])) {
        $methodLabel = ucfirst($data['extraction_method']);
        $confidence = isset($data['extraction_confidence']) ? round($data['extraction_confidence'] * 100) : 0;
        $html .= '<div class="data-row info-row" style="background: #f0f4ff; padding: 10px; border-radius: 4px; margin-bottom: 15px;">';
        $html .= '<strong>Extraction Method:</strong> ' . htmlspecialchars($methodLabel) . ' ';
        $html .= '<span style="background: ' . ($confidence >= 90 ? '#4caf50' : ($confidence >= 75 ? '#ff9800' : '#f44336')) . '; color: white; padding: 2px 8px; border-radius: 3px; font-weight: bold;">' . $confidence . '%</span>';
        $html .= '</div>';
    }
    
    // Show MRZ debug info if available
    if (isset($data['mrz_debug']) && !empty($data['mrz_debug'])) {
        $html .= '<div class="data-row" style="background: #fff3cd; padding: 8px; border-radius: 4px; margin-bottom: 10px; font-size: 12px;">';
        $html .= '<strong>MRZ Debug:</strong> ' . htmlspecialchars(json_encode($data['mrz_debug'])) . '</div>';
    }
    
    if (isset($data['full_name']) && $data['full_name']) {
        $html .= '<div class="data-row"><strong>Full Name:</strong> ' . htmlspecialchars($data['full_name']) . '</div>';
    }
    
    if (isset($data['date_of_birth']) && $data['date_of_birth']) {
        $html .= '<div class="data-row"><strong>Date of Birth:</strong> ' . htmlspecialchars($data['date_of_birth']) . '</div>';
    }
    
    if (isset($data['passport_number']) && $data['passport_number']) {
        $html .= '<div class="data-row"><strong>Passport Number:</strong> ' . htmlspecialchars($data['passport_number']) . '</div>';
    }
    
    if (isset($data['issue_date']) && $data['issue_date']) {
        $html .= '<div class="data-row"><strong>Issue Date:</strong> ' . htmlspecialchars($data['issue_date']) . '</div>';
    }
    
    if (isset($data['expiry_date']) && $data['expiry_date']) {
        $html .= '<div class="data-row"><strong>Expiry Date:</strong> ' . htmlspecialchars($data['expiry_date']) . '</div>';
    }
    
    if (isset($data['place_of_birth']) && $data['place_of_birth']) {
        $html .= '<div class="data-row"><strong>Place of Birth:</strong> ' . htmlspecialchars($data['place_of_birth']) . '</div>';
    }
    
    if (isset($data['father_name']) && $data['father_name']) {
        $html .= '<div class="data-row"><strong>Father Name:</strong> ' . htmlspecialchars($data['father_name']) . '</div>';
    }
    
    if (isset($data['gender']) && $data['gender']) {
        $html .= '<div class="data-row"><strong>Gender:</strong> ' . htmlspecialchars($data['gender']) . '</div>';
    }
    
    if (isset($data['nationality']) && $data['nationality']) {
        $html .= '<div class="data-row"><strong>Nationality:</strong> ' . htmlspecialchars($data['nationality']) . '</div>';
    }
    
    if (isset($data['id_number']) && $data['id_number']) {
        $html .= '<div class="data-row"><strong>ID Number:</strong> ' . htmlspecialchars($data['id_number']) . '</div>';
    }
    
    if (isset($data['guardian_name']) && $data['guardian_name']) {
        $html .= '<div class="data-row"><strong>Guardian Name:</strong> ' . htmlspecialchars($data['guardian_name']) . '</div>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Render raw output
 */
function renderRawOutput($data) {
    $html = '<div class="output-panel"><pre>';
    foreach ($data as $key => $value) {
        if ($key !== 'extraction_confidence') {
            $displayValue = is_array($value) ? json_encode($value) : ($value ?? 'N/A');
            $html .= htmlspecialchars($key) . ': ' . htmlspecialchars($displayValue) . "\n";
        }
    }
    $html .= '</pre></div>';
    return $html;
}

// Sample test data for Passport
$passportTests = [
    'passport_afghani_mrzformat' => [
        'name' => 'Afghan Passport (MRZ Format)',
        'type' => 'passport',
        'text' => <<<'TEXT'
ISLAMIC REPUBLIC OF AFGHANISTAN
PASSPORT

Given Names
JANA GUL
AFGHAN
Dato of Birth
02 MAY 1992 1371 9 12
Place of Birth
PAKTIA
Date of lssue
26 Nov 2025 1406 ugd 05
Date of bl
5% 'NOV 2030 i 1409 "ogs 05
P<AFGMUHABAT<<JANA<GUL<CLLLLLLLLLLLLLLLLLLLLL
P098220721AFG9205026M3011261<<<<<<<<<<<K<<<04
TEXT
    ],
    'passport_afghani' => [
        'name' => 'Afghan Passport',
        'type' => 'passport',
        'text' => <<<'TEXT'
ISLAMIC REPUBLIC OF AFGHANISTAN
PASSPORT

Name: AHMADI / MOHAMMAD
Date of Birth: 02 AUG 1996
Nationality: Afghan
Gender: Male
Passport Number: AP1234567
Expiry Date: 15 JAN 2034
Father Name: MOHAMMAD HASSAN

Place of Issue: Kabul
Date of Issue: 15 JAN 2024
TEXT
    ],
    'passport_pakistan' => [
        'name' => 'Pakistan Passport',
        'type' => 'passport',
        'text' => <<<'TEXT'
ISLAMIC REPUBLIC OF PAKISTAN
PASSPORT

Name: KHAN / FATIMA
Date of Birth: 15 JAN 2000
Nationality: Pakistani
Gender: Female
Passport Number: EP1987654
Expiry Date: 20 MAR 2030
Father Name: KHAN AHMED

Issued: 20 MAR 2020
Place of Issue: Islamabad
TEXT
    ],
];

// Sample test data for ID Documents
$idTests = [
    'id_afghan_mrzformat' => [
        'name' => 'Afghan ID (MRZ Format - Actual e-Tazkira)',
        'type' => 'id',
        'text' => <<<'TEXT'
جمهوری اسلامی افغانستان
اداره ملی احصائیه و معلومات
تذکره تابعیت

د افغانستان اسلامي جمهوريت
د احصايې او معلوماتو ملي اداره
د تابعيت تذکره

۱۴۰۰-۰۳۰۲-۵۴۸۶۳

نوم / نام (تخلص):
طریقه نورزی

د پلار نوم / نام پدر:
غلام ربی

د نیکه نوم / نام پدرکلان:
غلام حضرت

د زیږېدو نېټه / تاریخ تولد:
۱۳۷۷ / ۰۸ / ۰۱

د اعتبار نېټه / تاریخ اعتبار:
۱۴۰۰ / ۰۳ / ۱۱

دین: اسلام
قوم: پشتون

اصلي هستوګنځای / سکونت اصلی:
کنر

اوسنی هستوګنځای / سکونت فعلی:
کنر / اسعد آباد – مرکز

ملت: افغان

Islamic Republic of Afghanistan / National Identity Card

ID Number: 1400-0302-54863
Name / SURNAME: Tariqa NOORZAI
Place of Birth: Kunar
Date of Birth: 23/10/1998
Nationality: Afghan
Gender: Female
Date of Issue: 01/06/2021
Date of Expiry: 01/06/2031

I<AFG14000302<854863<<<<<<<<<<<<
9810237F3106017AFG<<<<<<<<<<2
NOORZAI<<TARIQA<<<<<<<<<<<<<<<<
TEXT
    ],
    'id_afghan' => [
        'name' => 'Afghan National ID (Tazkira - Dari Format)',
        'type' => 'id',
        'text' => <<<'TEXT'
جمهوری اسلامی افغانستان
اداره ملی احصائیه و معلومات
تذکره تابعیت

د پلار نوم / نام پدر:
محمد علی

د نیکه نوم / نام پدرکلان:
عبدالله خان

نوم / نام (تخلص):
احمد شاه

تاریخ تولد:
12 / 06 / 1995

شماره تذکره:
1395-0612-12345

Islamic Republic of Afghanistan
NATIONAL ID CARD - TAZKIRA

Full Name: Ahmad Shah
Date of Birth: 12/06/1995
Father's Name: Mohammad Ali
Grandfather's Name: Abdullah Khan
Gender: Male
ID Number: 1395-0612-12345
Nationality: Afghan

Issued: 10 JAN 2022
Valid Until: 10 JAN 2027
TEXT
    ],
    'id_pakistan' => [
        'name' => 'Pakistan CNIC (National ID)',
        'type' => 'id',
        'text' => <<<'TEXT'
GOVERNMENT OF PAKISTAN
COMPUTERISED NATIONAL IDENTITY CARD

Full Name: MALIK / HASSAN
Date of Birth: 22 JUL 1992
Father Name: MALIK HUSSAIN
Gender: Male
ID Number: 35201-1234567-8
Nationality: Pakistan

Issue Date: 12 NOV 2020
Expiry Date: 12 NOV 2025
Place of Issue: Lahore
TEXT
    ],
    'id_dari' => [
        'name' => 'Afghan ID (Dari - داری)',
        'type' => 'id',
        'text' => <<<'TEXT'
جمهوری اسلامی افغانستان
کارت شناسنامه ملی

نام و تخلص: احمد علی
تاریخ تولد: 15 مه 1990
نام پدر: علی اکبر
نام مادر: فاطمه
جنسیت: مرد
شماره شناسنامه: 123456789012
تابعیت: افغانستان

صادر شده: 10 جنوری 2020
معتبر تا: 10 جنوری 2025
TEXT
    ],
    'id_pashto' => [
        'name' => 'Afghan ID (Pashto - پښتو)',
        'type' => 'id',
        'text' => <<<'TEXT'
د افغانستان اسلامی جمهوریت
ملی پیژندون کارت

نوم: محمود حسن
د زیزې نیته: 22 اپریل 1988
د پلار نوم: حسن خان
د مور نوم: عائشه
د جنس: نارینه
شماره: 987654321098
ملت: افغان

صادر: 05 فروری 2021
معتبره تر: 05 فروری 2026
TEXT
    ],
];

$testMode = $_GET['test'] ?? null;
$documentType = $_GET['type'] ?? null;
$uploadError = null;
$uploadText = null;
$uploadResult = null;

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    try {
        if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No file uploaded. Error code: ' . ($_FILES['document_file']['error'] ?? 'unknown'));
        }
        
        if (!isset($_POST['document_type']) || !in_array($_POST['document_type'], ['passport', 'id'])) {
            throw new Exception('Invalid document type specified');
        }
        
        $file = $_FILES['document_file'];
        $docType = $_POST['document_type'];
        
        // Validate file type
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($fileExt, $allowedExts)) {
            throw new Exception('File must be PDF or image (.jpg, .jpeg, .png). Got: .' . htmlspecialchars($fileExt));
        }
        
        $mimeType = mime_content_type($file['tmp_name']);
        $allowedMimes = ['application/pdf', 'application/x-pdf', 'image/jpeg', 'image/png'];
        if (!in_array($mimeType, $allowedMimes)) {
            throw new Exception('Invalid file type. Detected MIME: ' . htmlspecialchars($mimeType));
        }
        
        // Validate file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('File size (' . round($file['size'] / 1024 / 1024, 2) . 'MB) exceeds 10MB limit');
        }
        
        // Create temp directory
        $uploadDir = sys_get_temp_dir() . '/document_uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Move file to temp location
        $fileName = uniqid('document_') . '.' . $fileExt;
        $filePath = $uploadDir . $fileName;
        
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        // Extract text from document
        $uploadText = '';
        $ocrMethod = 'none';
        $uploadResult = null;
        $needsClientOCR = false;
        
        if ($fileExt === 'pdf') {
            // Try text extraction first
            $uploadText = extractTextFromPDF($filePath);
            
            // If PDF is image-based (no text extracted), try PaddleOCR
            if (empty($uploadText)) {
                // Try server-side PaddleOCR (faster and more accurate than Tesseract)
                $uploadText = extractTextViaPaddleOCR($filePath);
                if (!empty($uploadText)) {
                    $ocrMethod = 'paddleocr-server';
                } else {
                    // PaddleOCR not available, mark for client-side OCR
                    $needsClientOCR = true;
                    $ocrMethod = 'client-side-ready';
                    // Create a placeholder result indicating client OCR is needed
                    $uploadResult = [
                        'full_name' => null,
                        'date_of_birth' => null,
                        'passport_number' => null,
                        'id_number' => null,
                        'father_name' => null,
                        'gender' => null,
                        'extraction_confidence' => 0,
                        'extraction_method' => 'client-side-pending',
                        'ocr_status' => 'image-based-pdf-needs-browser-ocr'
                    ];
                }
            } else {
                $ocrMethod = 'text-extraction';
            }
        } else {
            // For images, try server-side PaddleOCR first
            $uploadText = extractTextViaPaddleOCR($filePath);
            if (!empty($uploadText)) {
                $ocrMethod = 'paddleocr-server';
            } else {
                // Mark for client-side OCR
                $needsClientOCR = true;
                $ocrMethod = 'client-side-ready';
                $uploadResult = [
                    'full_name' => null,
                    'date_of_birth' => null,
                    'passport_number' => null,
                    'id_number' => null,
                    'father_name' => null,
                    'gender' => null,
                    'extraction_confidence' => 0,
                    'extraction_method' => 'client-side-pending',
                    'ocr_status' => 'image-needs-browser-ocr'
                ];
            }
        }
        
        // Extract document data only if text was extracted
        if (!empty($uploadText) && empty($uploadResult)) {
            if ($docType === 'passport') {
                $uploadResult = extractPassportData($uploadText);
            } else {
                $uploadResult = extractIDData($uploadText);
            }
        }
        
        // Clean up
        unlink($filePath);
        
    } catch (Exception $e) {
        $uploadError = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Extractor Test Page</title>
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
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2.5em;
        }
        
        .header p {
            color: #666;
            font-size: 1.1em;
        }
        
        .test-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .test-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-decoration: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
        }
        
        .test-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border-left-color: #764ba2;
        }
        
        .test-card h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .test-card p {
            color: #666;
            font-size: 0.9em;
        }
        
        .results-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .test-title {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group textarea,
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 150px;
        }
        
        .form-group textarea:focus,
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .submit-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .submit-button:active {
            transform: translateY(0);
        }
        
        .badge {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .info-box {
            background: #f5f7fa;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 4px solid #667eea;
        }
        
        .confidence-score {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 600;
            margin-left: 10px;
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
        
        .tab-container {
            margin-top: 20px;
        }
        
        .tab-buttons {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid #eee;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .tab-button {
            background: none;
            border: none;
            padding: 12px 20px;
            cursor: pointer;
            color: #666;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .tab-button.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-button:hover {
            color: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .output-panel {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #ddd;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            max-height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }
        
        .data-row {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .data-row:last-child {
            border-bottom: none;
        }
        
        .data-row strong {
            color: #667eea;
            min-width: 180px;
            display: inline-block;
        }
        
        .json-output {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #f5c6cb;
            margin: 20px 0;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #c3e6cb;
            margin: 20px 0;
        }
        
        .footer {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            color: #666;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .back-button {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .back-button:hover {
            background: #764ba2;
        }
        
        .upload-section {
            background: #f0f7ff;
            border: 2px dashed #667eea;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .upload-section h3 {
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .upload-section p {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
            max-width: 400px;
        }
        
        .file-input-label {
            display: block;
            padding: 20px;
            background: white;
            border: 2px solid #667eea;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color: #667eea;
        }
        
        .file-input-label:hover {
            background: #f8f9fa;
        }
        
        .file-name-display {
            margin-top: 10px;
            color: #666;
            font-size: 13px;
        }
        
        .form-group-inline {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .form-group-inline > div {
            flex: 1;
            min-width: 200px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($testMode && in_array($documentType, ['passport', 'id'])): ?>
            <!-- Test Result View -->
            <?php
                $testData = $passportTests[$testMode] ?? null;
                
                if ($testData && $testData['type'] === 'passport'):
                    $result = extractPassportData($testData['text']);
                    
                    $confidence = $result['extraction_confidence'] ?? 0;
                    $confidencePercent = round($confidence * 100);
                    $confidenceClass = $confidencePercent >= 80 ? 'confidence-high' : ($confidencePercent >= 50 ? 'confidence-medium' : 'confidence-low');
            ?>
            <a href="test_document_extractor.php" class="back-button">← Back to Tests</a>
            
            <div class="header">
                <h1>📄 <?php echo htmlspecialchars($testData['name']); ?></h1>
                <p><?php echo $documentType === 'passport' ? 'Passport Document' : 'ID Document'; ?> Test Case</p>
            </div>
            
            <div class="results-container">
                <h2 class="test-title">Extraction Results</h2>
                <div class="badge"><?php echo strtoupper($documentType); ?></div>
                <div class="info-box">
                    <strong>Confidence Score:</strong> 
                    <span class="confidence-score <?php echo $confidenceClass; ?>"><?php echo $confidencePercent; ?>%</span>
                </div>
                
                <div class="tab-container">
                    <div class="tab-buttons">
                        <button class="tab-button active" onclick="switchTab(this, 'formatted')">Formatted View</button>
                        <button class="tab-button" onclick="switchTab(this, 'raw')">Raw Data</button>
                        <button class="tab-button" onclick="switchTab(this, 'json')">JSON Output</button>
                        <button class="tab-button" onclick="switchTab(this, 'input')">Input Text</button>
                    </div>
                    
                    <div id="formatted" class="tab-content active">
                        <?php echo renderFormattedOutput($result); ?>
                    </div>
                    
                    <div id="raw" class="tab-content">
                        <?php echo renderRawOutput($result); ?>
                    </div>
                    
                    <div id="json" class="tab-content">
                        <div class="json-output" id="json-pretty"></div>
                        <script>
                            document.getElementById("json-pretty").textContent = JSON.stringify(<?php echo json_encode($result); ?>, null, 2);
                        </script>
                    </div>
                    
                    <div id="input" class="tab-content">
                        <div class="output-panel"><?php echo htmlspecialchars($testData['text']); ?></div>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <div class="error-message">Invalid test case or document type mismatch.</div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Main Menu View -->
            <div class="header">
                <h1>📄 Document Extractor Test Suite</h1>
                <p>Test the document info extraction system for Passport and ID documents</p>
            </div>
            
            <h2 style="color: white; margin: 30px 0 20px 0;">Passport Tests</h2>
            <div class="test-selector">
                <?php foreach ($passportTests as $key => $test): ?>
                    <a href="?test=<?php echo urlencode($key); ?>&type=passport" class="test-card">
                        <h3>🛂 <?php echo htmlspecialchars($test['name']); ?></h3>
                        <p>Click to run this test case and view extraction results</p>
                    </a>
                <?php endforeach; ?>
            </div>
            

            
            <!-- Custom Upload Section -->
            <div class="results-container">
                <h2 class="test-title">Custom Document Upload & Test</h2>
                
                <!-- Document Upload Section -->
                <div class="upload-section">
                    <h3>📄 Upload Document (PDF or Image)</h3>
                    <p>Upload a Passport or ID document in PDF or image format to automatically extract information</p>
                    
                    <form method="POST" enctype="multipart/form-data" id="document-form">
                        <div class="form-group-inline">
                            <div>
                                <label for="document_type">Document Type:</label>
                                <select name="document_type" id="document_type" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                                    <option value="passport" selected>Passport</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="file-input-wrapper">
                            <input type="file" id="document_file" name="document_file" accept=".pdf,.jpg,.jpeg,.png" style="display: none;" onchange="handleFileSelect()">
                            <label for="document_file" class="file-input-label" id="file-label">
                                📁 Click to select PDF/Image or drag & drop
                            </label>
                        </div>
                        <div class="file-name-display" id="file-name"></div>
                        <button type="submit" name="upload_document" class="submit-button" style="margin-top: 15px; display: none;" id="document-submit">Extract from Document</button>
                        <button type="button" class="submit-button" style="margin-top: 15px; display: none; background: #764ba2; margin-left: 10px;" id="ocr-submit">Extract via OCR (Image)</button>
                    </form>
                    
                    <script>
                        const docInput = document.getElementById('document_file');
                        const docForm = document.getElementById('document-form');
                        const fileLabel = document.getElementById('file-label');
                        const fileNameDiv = document.getElementById('file-name');
                        const docSubmit = document.getElementById('document-submit');
                        const ocrSubmit = document.getElementById('ocr-submit');
                        
                        // Drag and drop
                        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                            docForm.addEventListener(eventName, preventDefaults, false);
                        });
                        
                        function preventDefaults(e) {
                            e.preventDefault();
                            e.stopPropagation();
                        }
                        
                        ['dragenter', 'dragover'].forEach(eventName => {
                            docForm.addEventListener(eventName, () => {
                                fileLabel.style.background = '#e7f3ff';
                                fileLabel.style.borderColor = '#004085';
                            });
                        });
                        
                        ['dragleave', 'drop'].forEach(eventName => {
                            docForm.addEventListener(eventName, () => {
                                fileLabel.style.background = 'white';
                                fileLabel.style.borderColor = '#667eea';
                            });
                        });
                        
                        docForm.addEventListener('drop', e => {
                            const dt = e.dataTransfer;
                            const files = dt.files;
                            docInput.files = files;
                            handleFileSelect();
                        });
                        
                        function handleFileSelect() {
                            const file = docInput.files[0];
                            const docType = document.getElementById('document_type').value;
                            if (file && docType) {
                                const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
                                const isImage = file.type.startsWith('image/');
                                
                                if (allowedTypes.includes(file.type)) {
                                    fileNameDiv.textContent = '✓ Selected: ' + file.name + ' (' + (file.size / 1024).toFixed(2) + ' KB)';
                                    fileNameDiv.style.color = '#28a745';
                                    docSubmit.style.display = 'inline-block';
                                    
                                    // Show OCR button for images
                                    if (isImage) {
                                        ocrSubmit.style.display = 'inline-block';
                                    } else {
                                        ocrSubmit.style.display = 'none';
                                    }
                                } else {
                                    fileNameDiv.textContent = '✗ Please select a PDF or image file';
                                    fileNameDiv.style.color = '#dc3545';
                                    docSubmit.style.display = 'none';
                                    ocrSubmit.style.display = 'none';
                                }
                            } else if (!docType) {
                                fileNameDiv.textContent = '⚠ Please select a document type first';
                                fileNameDiv.style.color = '#ff9800';
                                docSubmit.style.display = 'none';
                                ocrSubmit.style.display = 'none';
                            }
                        }
                        
                        // OCR extraction handler
                        ocrSubmit.addEventListener('click', async (e) => {
                            e.preventDefault();
                            const file = docInput.files[0];
                            const docType = document.getElementById('document_type').value;
                            
                            if (!file || !docType) {
                                alert('Please select a document and document type');
                                return;
                            }
                            
                            await performDocumentOCR(file, docType);
                        });
                        
                        async function performDocumentOCR(file, docType) {
                            try {
                                if (typeof Tesseract === 'undefined') {
                                    alert('Tesseract.js library not loaded');
                                    return;
                                }
                                
                                fileNameDiv.textContent = '⏳ Performing OCR (this may take a moment)...';
                                fileNameDiv.style.color = '#ff9800';
                                
                                const fileUrl = URL.createObjectURL(file);
                                const { data: { text } } = await Tesseract.recognize(fileUrl, 'eng');
                                URL.revokeObjectURL(fileUrl);
                                
                                if (!text || text.trim().length === 0) {
                                    throw new Error('No text detected in image');
                                }
                                
                                // Send OCR text to server for proper extraction with MRZ parsing
                                fileNameDiv.textContent = '⏳ Extracting document data (server-side)...';
                                const extractionResult = await sendOCRTextToServer(text, docType);
                                
                                const confidence = extractionResult.extraction_confidence || extractionResult.confidence || 0;
                                const confidencePercent = typeof confidence === 'number' ? Math.round(confidence * 100) : 0;
                                fileNameDiv.textContent = '✓ Document extracted! (' + confidencePercent + '% confidence)';
                                fileNameDiv.style.color = '#28a745';
                                
                                // Display results with full metadata
                                displayOCRResults(extractionResult, text, docType);
                                
                            } catch (error) {
                                fileNameDiv.textContent = '✗ OCR Error: ' + error.message;
                                fileNameDiv.style.color = '#dc3545';
                                console.error('OCR Error:', error);
                            }
                        }
                        
                        // Send OCR text to server for proper extraction
                        async function sendOCRTextToServer(ocrText, docType) {
                            try {
                                const response = await fetch('/almoqadas/mtravels/api/umrah/extract_text.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify({
                                        text: ocrText,
                                        document_type: docType
                                    })
                                });
                                
                                if (!response.ok) throw new Error('Server extraction failed');
                                const result = await response.json();
                                console.log('Server extraction result:', result);
                                
                                if (!result.success) throw new Error(result.message || 'Extraction failed');
                                
                                // Ensure confidence is a number
                                const confidence = typeof result.data.extraction_confidence === 'number' 
                                    ? result.data.extraction_confidence 
                                    : (result.confidence || 0);
                                
                                return {
                                    ...result.data,
                                    extraction_method: result.extraction_method || result.data.extraction_method,
                                    extraction_confidence: confidence,
                                    confidence: confidence  // Fallback key
                                };
                            } catch (error) {
                                console.warn('Server extraction failed, using client-side:', error);
                                // Fallback to client-side extraction
                                const documentData = extractDocumentFromOCRText(ocrText, docType);
                                return {
                                    ...documentData,
                                    extraction_method: 'client-side',
                                    extraction_confidence: calculateConfidence(documentData)
                                };
                            }
                        }
                        
                        function extractDocumentFromOCRText(text, docType) {
                            const data = {};
                            
                            const nameMatch = text.match(/(?:Name|Surname)[:\s]*([A-Za-z\s]+?)(?:\n|Date|DOB|Gender|Father)/i);
                            if (nameMatch) data.full_name = nameMatch[1].trim();
                            
                            const dobMatch = text.match(/(?:Date\s+of\s+(?:Birth|Issue)|DOB)[:\s]*(\d{1,2})[\/\-\s]+([A-Za-z]+|\d{1,2})[\/\-\s]+(\d{4})/i) ||
                                            text.match(/(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\s+(\d{4})/i);
                            if (dobMatch) data.date_of_birth = formatTestDate(dobMatch[1], dobMatch[2], dobMatch[3]);
                            
                            if (docType === 'passport') {
                                const passportMatch = text.match(/(?:Passport\s+(?:No|Number)|[A-Z])[:\s]*([A-Z0-9]+)/i);
                                if (passportMatch) data.passport_number = passportMatch[1].trim();
                                
                                const expiryMatch = text.match(/(?:Expiry|Expires|Valid\s+Until)[:\s]*(\d{1,2})[\/\-\s]+([A-Za-z]+|\d{1,2})[\/\-\s]+(\d{4})/i);
                                if (expiryMatch) data.expiry_date = formatTestDate(expiryMatch[1], expiryMatch[2], expiryMatch[3]);
                            }
                            
                            const fatherMatch = text.match(/(?:Father|Parent)[:\s]*([A-Za-z\s]+?)(?:\n|$|Guardian|Mother)/i);
                            if (fatherMatch) data.father_name = fatherMatch[1].trim();
                            
                            const genderMatch = text.match(/(?:Gender|Sex)[:\s]*(M|F|Male|Female)/i);
                            if (genderMatch) {
                                const g = genderMatch[1].toUpperCase();
                                data.gender = (g === 'M' || g === 'MALE') ? 'Male' : (g === 'F' || g === 'FEMALE') ? 'Female' : genderMatch[1];
                            }
                            
                            if (docType === 'id') {
                                const guardianMatch = text.match(/(?:Guardian|Mother)[:\s]*([A-Za-z\s]+?)(?:\n|$)/i);
                                if (guardianMatch) data.guardian_name = guardianMatch[1].trim();
                            }
                            
                            return data;
                        }
                        
                        function formatTestDate(day, month, year) {
                            const months = {
                                'JAN': '01', 'FEB': '02', 'MAR': '03', 'APR': '04',
                                'MAY': '05', 'JUN': '06', 'JUL': '07', 'AUG': '08',
                                'SEP': '09', 'OCT': '10', 'NOV': '11', 'DEC': '12'
                            };
                            const m = !month.match(/^\d+$/) ? months[month.toUpperCase()] || month : month;
                            return year + '-' + String(m).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                        }
                        
                        function calculateConfidence(data) {
                            let filled = 0, total = 4;
                            if (data.full_name) filled++;
                            if (data.date_of_birth) filled++;
                            if (data.father_name) filled++;
                            if (data.gender) filled++;
                            return filled / total;
                        }
                        
                        function displayOCRResults(data, text, docType) {
                            // Remove existing results container if present
                            const existingContainer = document.getElementById('ocr-results-container');
                            if (existingContainer) {
                                existingContainer.remove();
                            }
                            
                            // Extract metadata
                            const method = data.extraction_method || 'unknown';
                            const confidence = data.extraction_confidence !== undefined ? data.extraction_confidence : (data.confidence || 0);
                            const confidencePercent = typeof confidence === 'number' ? Math.round(confidence * 100) : 0;
                            const confidenceColor = confidencePercent >= 90 ? '#4caf50' : (confidencePercent >= 75 ? '#ff9800' : '#f44336');
                            
                            // Create metadata box
                            let metadataHtml = `
                                <div style="background: #f0f4ff; padding: 15px; border-radius: 4px; margin-bottom: 15px; border-left: 4px solid #007bff;">
                                    <div><strong>Extraction Method:</strong> <span style="background: ${confidenceColor}; color: white; padding: 3px 10px; border-radius: 3px; font-weight: bold;">${method.toUpperCase()}</span></div>
                                    <div style="margin-top: 8px;"><strong>Confidence:</strong> <span style="background: ${confidenceColor}; color: white; padding: 3px 10px; border-radius: 3px; font-weight: bold;">${confidencePercent}%</span></div>
                            `;
                            
                            // Show MRZ debug if available
                            if (data.mrz_debug && Object.keys(data.mrz_debug).length > 0) {
                                metadataHtml += `
                                    <div style="margin-top: 8px; padding: 10px; background: white; border-radius: 3px;">
                                        <strong style="color: #ff9800;">MRZ Debug Info:</strong><br>
                                        <code style="font-size: 12px; color: #666;">${JSON.stringify(data.mrz_debug)}</code>
                                    </div>
                                `;
                            }
                            
                            // Show why extraction failed if low confidence
                            if (confidencePercent < 75) {
                                metadataHtml += `
                                    <div style="margin-top: 8px; padding: 10px; background: #fff3cd; border-radius: 3px; border: 1px solid #ffc107;">
                                        <strong style="color: #ff6b00;">⚠️ Low Confidence - Why?</strong><br>
                                        <ul style="margin: 5px 0; padding-left: 20px; font-size: 12px;">
                                `;
                                
                                // Analyze what failed
                                if (!data.passport_number && docType === 'passport') {
                                    metadataHtml += `<li>✗ Passport Number not extracted (MRZ lines not found or OCR quality poor)</li>`;
                                }
                                if (!data.id_number && docType === 'id') {
                                    metadataHtml += `<li>✗ ID Number not extracted (MRZ lines not found or OCR quality poor)</li>`;
                                }
                                if (!data.full_name) {
                                    metadataHtml += `<li>✗ Full Name not extracted</li>`;
                                }
                                if (!data.date_of_birth) {
                                    metadataHtml += `<li>✗ Date of Birth not extracted</li>`;
                                }
                                if (!data.gender) {
                                    metadataHtml += `<li>✗ Gender not extracted</li>`;
                                }
                                
                                // Show MRZ debug info if available
                                if (method === 'pattern' && data.mrz_debug) {
                                    const mrzStatus = data.mrz_debug.status || 'Unknown';
                                    const linesFound = data.mrz_debug.lines_found || 0;
                                    metadataHtml += `
                                        <li><strong>MRZ Parse Debug:</strong>
                                            <ul style="margin: 5px 0; padding-left: 20px; font-size: 11px;">
                                                <li>Lines found: ${linesFound}</li>
                                                <li>Status: ${mrzStatus}</li>
                                            </ul>
                                        </li>
                                    `;
                                }
                                
                                if (method === 'pattern') {
                                    metadataHtml += `
                                        <li><strong>Reason: MRZ parsing failed</strong> - The standardized document lines were not readable:
                                            <ul style="margin: 5px 0; padding-left: 20px; font-size: 12px;">
                                                <li>Passport MRZ format: 2 lines starting with "P&lt;" (P followed by country code)</li>
                                                <li>ID Card MRZ format: 2 lines starting with "I&lt;" (I followed by country code)</li>
                                            </ul>
                                        </li>
                                        <li><strong>Solution:</strong> 
                                           <ul style="margin: 5px 0; padding-left: 20px; font-size: 12px;">
                                               <li>Improve document scan quality (high resolution, good lighting)</li>
                                               <li>Ensure MRZ lines at bottom are clearly visible and readable</li>
                                               <li>Try re-scanning with better angle/alignment</li>
                                           </ul>
                                       </li>
                                    `;
                                }
                                if (method === 'client-side') {
                                    metadataHtml += `
                                        <li><strong>Reason: Server extraction unavailable</strong> - Fell back to browser-based pattern matching</li>
                                    `;
                                }
                                
                                metadataHtml += `</ul></div>`;
                            }
                            
                            metadataHtml += `</div>`;
                            
                            // Build data display (filter out internal fields)
                            const displayData = Object.entries(data).filter(([k]) => 
                                !['extraction_confidence', 'extraction_method', 'mrz_debug', 'mrz_valid'].includes(k)
                            );
                            
                            const html = `
                                <div id="ocr-results-container" style="margin-top: 30px; padding-top: 30px; border-top: 2px solid #eee;">
                                    <h3 style="margin-bottom: 15px;">📋 OCR Extraction Results</h3>
                                    ${metadataHtml}
                                    <div class="tab-container">
                                        <div class="tab-buttons">
                                            <button class="tab-button active" onclick="switchTab(this, 'ocr-formatted')">Formatted View</button>
                                            <button class="tab-button" onclick="switchTab(this, 'ocr-json')">JSON Output</button>
                                            <button class="tab-button" onclick="switchTab(this, 'ocr-text')">OCR Text</button>
                                        </div>
                                        
                                        <div id="ocr-formatted" class="tab-content active">` +
                                            displayData.map(([k, v]) => 
                                                `<div class="data-row"><strong>${k.replace(/_/g, ' ').toUpperCase()}:</strong> <span style="color: ${v ? '#333' : '#999'};">${v || 'N/A'}</span></div>`
                                            ).join('') +
                                        `</div>
                                        
                                        <div id="ocr-json" class="tab-content">
                                            <div class="json-output" id="ocr-json-pretty"></div>
                                        </div>
                                        
                                        <div id="ocr-text" class="tab-content">
                                            <div class="output-panel">` + text.substring(0, 1000).replace(/</g, '&lt;').replace(/>/g, '&gt;') + `...</div>
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            // Insert after the form
                            const docForm = document.getElementById('document-form');
                            docForm.insertAdjacentHTML('afterend', html);
                            
                            // Populate JSON after DOM is ready
                            setTimeout(() => {
                                const jsonPrettyDiv = document.getElementById('ocr-json-pretty');
                                if (jsonPrettyDiv) {
                                    jsonPrettyDiv.textContent = JSON.stringify(data, null, 2);
                                }
                            }, 0);
                        }
                    </script>
                </div>
                
                <!-- Text Input Section -->
                <h3 style="margin: 30px 0 15px 0; color: #333;">Or paste document text directly:</h3>
                <form method="POST">
                    <div class="form-group-inline">
                        <div>
                            <label for="document_type_text">Document Type:</label>
                            <select name="document_type_text" id="document_type_text" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                                <option value="">-- Select Document Type --</option>
                                <option value="passport">Passport</option>
                                <option value="id">ID Document</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="custom_text">Document Text:</label>
                        <textarea id="custom_text" name="custom_text" placeholder="Paste document text here..."></textarea>
                    </div>
                    <button type="submit" name="test_custom" class="submit-button">Extract Document Info</button>
                </form>
                
                <?php
                    // Display upload results
                    if ($uploadError) {
                        echo '<div class="error-message"><strong>Upload Error:</strong> ' . htmlspecialchars($uploadError) . '</div>';
                    }
                    
                    if ($uploadResult) {
                         $customConfidence = $uploadResult['extraction_confidence'] ?? 0;
                         $confidencePercent = round($customConfidence * 100);
                         $confidenceClass = $confidencePercent >= 80 ? 'confidence-high' : ($confidencePercent >= 50 ? 'confidence-medium' : 'confidence-low');
                         $ocrStatus = $uploadResult['ocr_status'] ?? null;
                         
                         echo '
                         <div style="margin-top: 30px; padding-top: 30px; border-top: 2px solid #eee;">
                             <h3 style="margin-bottom: 15px; color: #333;">📄 Upload Extraction Results</h3>';
                         
                         // Show OCR status if client OCR is needed
                         if ($ocrStatus === 'image-based-pdf-needs-browser-ocr' || $ocrStatus === 'image-needs-browser-ocr') {
                             echo '
                             <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 15px; margin-bottom: 20px;">
                                 <strong style="color: #ff6b00;">⚠️ Image-Based Document Detected</strong><br>
                                 <p style="margin: 10px 0 0 0; font-size: 14px;">This document contains images instead of text. Please use the <strong>OCR Extract</strong> button below to extract text from the image using your browser.</p>
                                 <p style="margin: 8px 0 0 0; font-size: 12px; color: #666;">Note: This uses free, browser-based OCR (Tesseract.js) and requires no server installation.</p>
                             </div>';
                         }
                         
                         echo '
                             <div class="info-box">
                                 <strong>Confidence Score:</strong> 
                                 <span class="confidence-score ' . $confidenceClass . '">' . $confidencePercent . '%</span>
                             </div>
                            
                            <div class="tab-container">
                                <div class="tab-buttons">
                                    <button class="tab-button active" onclick="switchTab(this, \'upload-formatted\')">Formatted View</button>
                                    <button class="tab-button" onclick="switchTab(this, \'upload-raw\')">Raw Data</button>
                                    <button class="tab-button" onclick="switchTab(this, \'upload-json\')">JSON Output</button>
                                    <button class="tab-button" onclick="switchTab(this, \'upload-text\')">Extracted Text</button>
                                </div>
                                
                                <div id="upload-formatted" class="tab-content active">
                                    ' . renderFormattedOutput($uploadResult) . '
                                </div>
                                
                                <div id="upload-raw" class="tab-content">
                                    ' . renderRawOutput($uploadResult) . '
                                </div>
                                
                                <div id="upload-json" class="tab-content">
                                    <div class="json-output" id="upload-json-pretty"></div>
                                    <script>
                                        document.getElementById("upload-json-pretty").textContent = JSON.stringify(' . json_encode($uploadResult) . ', null, 2);
                                    </script>
                                </div>
                                
                                <div id="upload-text" class="tab-content">
                                    <div class="output-panel"><strong>Extracted Text (' . strlen($uploadText) . ' characters):</strong><br><br>' . htmlspecialchars(substr($uploadText, 0, 2000)) . (strlen($uploadText) > 2000 ? '...' : '') . '</div>
                                </div>
                            </div>
                        </div>';
                    }
                    
                    if (isset($_POST['test_custom']) && isset($_POST['document_type_text'])) {
                        $customText = $_POST['custom_text'] ?? '';
                        $customDocType = $_POST['document_type_text'];
                        
                        if (trim($customText) && in_array($customDocType, ['passport', 'id'])) {
                            if ($customDocType === 'passport') {
                                $customResult = extractPassportData($customText);
                            } else {
                                $customResult = extractIDData($customText);
                            }
                            
                            $customConfidence = $customResult['extraction_confidence'] ?? 0;
                            $confidencePercent = round($customConfidence * 100);
                            $confidenceClass = $confidencePercent >= 80 ? 'confidence-high' : ($confidencePercent >= 50 ? 'confidence-medium' : 'confidence-low');
                            
                            echo '
                            <div style="margin-top: 30px; padding-top: 30px; border-top: 2px solid #eee;">
                                <h3 style="margin-bottom: 15px;">Text Extraction Results</h3>
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
                            echo '<div class="error-message">Please enter document text and select a document type.</div>';
                        }
                    }
                ?>
            </div>
            
            <div class="footer">
                <p>Document Extractor Test Suite v1.0 | Supports Passport and ID documents</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Tesseract.js for client-side OCR -->
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@4.1.1/dist/tesseract.min.js"></script>
    
    <script>
        function switchTab(button, tabName) {
            // Hide all tabs in this container
            const container = button.closest('.tab-container');
            const tabs = container.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Remove active class from all buttons
            const buttons = container.querySelectorAll('.tab-button');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            // Show selected tab
            const selectedTab = container.querySelector('#' + tabName);
            if (selectedTab) {
                selectedTab.classList.add('active');
                button.classList.add('active');
            }
        }
    </script>
</body>
</html>
