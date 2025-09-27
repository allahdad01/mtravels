<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once('../includes/db.php');
require_once('../includes/conn.php');
require_once('security.php');
require_once('../vendor/autoload.php');
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();


// Language handling
$lang = isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ps', 'fa']) ? $_GET['lang'] : 'en';
$lang_file = __DIR__ . '/../includes/languages/' . $lang . '/umrah_document_receipt.php';

if (file_exists($lang_file)) {
    $l = require($lang_file);
} else {
    // Fallback to English
    $l = require(__DIR__ . '/../includes/languages/en/umrah_document_receipt.php');
}
$isRtl = ($lang === 'ps' || $lang === 'fa');

// Create directory if it doesn't exist
$uploadsDir = '../uploads/umrah/umrah_documents';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Check if booking ID is provided
if (!isset($_GET['booking_id'])) {
    die('Booking ID is required');
}

$bookingId = intval($_GET['booking_id']);

try {
    // Get booking details with related information
    $query = "
        SELECT um.*, f.package_type, f.head_of_family as family_name, f.contact,
               u.name as processed_by_name, m.name as account_name,
               s.name as supplier_name, c.name as client_name
        FROM umrah_bookings um
        LEFT JOIN families f ON um.family_id = f.family_id
        LEFT JOIN users u ON u.id = ?
        LEFT JOIN main_account m ON um.paid_to = m.id
        LEFT JOIN suppliers s ON um.supplier = s.id
        LEFT JOIN clients c ON um.sold_to = c.id
        WHERE um.booking_id = ? AND um.tenant_id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id'], $bookingId, $tenant_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        die('Booking not found');
    }
    $pilgrim_name = $booking['name'];

    // Get family information
    $familyQuery = "SELECT * FROM families WHERE family_id = ?";
    $familyStmt = $pdo->prepare($familyQuery);
    $familyStmt->execute([$booking['family_id']]);
    $family = $familyStmt->fetch(PDO::FETCH_ASSOC);

    // Get settings for company info
    $settingsQuery = "SELECT * FROM settings WHERE tenant_id = ?";
    $settingsStmt = $pdo->prepare($settingsQuery);
    $settingsStmt->execute([$tenant_id]);
    $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

    // Create mPDF instance with language-specific settings
    if ($isRtl) {
        // For Dari and Pashto, use XW Zar font with RTL support
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_footer' => 5,
            'default_font' => 'xwzar',
            'fontDir' => ['../assets/fonts/'],
            'fontdata' => [
                'xwzar' => [
                    'R' => 'XW Zar Bd_0.ttf',
                    'useOTL' => 0xFF,
                ]
            ],
            'orientation' => 'P'
        ]);
        
        // Set right-to-left direction
        $mpdf->SetDirectionality('rtl');
    } else {
        // For English, use default Arial font
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_footer' => 5,
            'orientation' => 'P'
        ]);
    }

    // Set watermark
    $mpdf->SetWatermarkText($settings['agency_name']);
    $mpdf->showWatermarkText = true;
    $mpdf->watermarkTextAlpha = 0.1;

    // Check if it's an AJAX request
    $isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    // Get the HTML and CSS content by capturing the output buffer
    ob_start();
    $template = include 'templates/umrah_document_receipt_form.php';
    ob_end_clean();

    // Write CSS first
    $mpdf->WriteHTML($template['css'], \Mpdf\HTMLParserMode::HEADER_CSS);
    
    // Then write HTML
    $mpdf->WriteHTML($template['html'], \Mpdf\HTMLParserMode::HTML_BODY);

    // Generate unique filename
    $filename = 'umrah_documents_' . $pilgrim_name . '_' . date('Y-m-d_His') . '.pdf';
    $filepath = $uploadsDir . '/' . $filename;

    // Save document receipt record in database
    $saveQuery = "INSERT INTO umrah_document_receipts 
                  (booking_id, filename, created_by, created_at) 
                  VALUES (?, ?, ?, NOW())";
    $saveStmt = $pdo->prepare($saveQuery);
    $saveStmt->execute([$bookingId, $filename, $_SESSION['user_id']]);

    if ($isAjaxRequest) {
        // Save PDF to file and return JSON response
        $mpdf->Output($filepath, 'F');
        echo json_encode([
            'success' => true, 
            'message' => 'Umrah document receipt generated successfully', 
            'file_url' => 'uploads/umrah/umrah_documents/' . $filename
        ]);
    } else {
        // Output PDF directly for download
        $mpdf->Output($filename, 'I');
    }
    exit;
    
} catch (Exception $e) {
    if ($isAjaxRequest) {
        echo json_encode(['success' => false, 'message' => 'Error generating document receipt: ' . $e->getMessage()]);
    } else {
        die('Error generating document receipt: ' . $e->getMessage());
    }
} 