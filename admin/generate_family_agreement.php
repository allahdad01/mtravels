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

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$user_id = $_SESSION['user_id'];
// Language handling
$lang = isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ps', 'fa']) ? $_GET['lang'] : 'en';
$lang_file = __DIR__ . '/../includes/languages/' . $lang . '/family_agreement.php';

if (file_exists($lang_file)) {
    $l = require($lang_file);
} else {
    // Fallback to English
    $l = require(__DIR__ . '/../includes/languages/en/umrah_agreement.php');
}
$isRtl = ($lang === 'ps' || $lang === 'fa');

// Create directory if it doesn't exist
$uploadsDir = '../uploads/umrah/family_agreements';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Check if family ID is provided
if (!isset($_GET['family_id'])) {
    die('Family ID is required');
}

$familyId = intval($_GET['family_id']);

try {
    // Get family details with related information
    $query = "
        SELECT f.*, u.name as processed_by_name
        FROM families f
        LEFT JOIN users u ON u.id = ?
        WHERE f.family_id = ? AND f.tenant_id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $familyId, $tenant_id]);
    $family = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$family) {
        die('Family not found');
    }

    // Get family members
    $membersQuery = "
        SELECT ub.*, c.name as client_name, s.name as supplier_name
        FROM umrah_bookings ub
        LEFT JOIN clients c ON ub.sold_to = c.id
        LEFT JOIN suppliers s ON ub.supplier = s.id
        WHERE ub.family_id = ? AND ub.tenant_id = ?
    ";
    $membersStmt = $pdo->prepare($membersQuery);
    $membersStmt->execute([$familyId, $tenant_id]);
    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

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
    $template = include 'templates/family_agreement_template_' . $lang . '.php';
    ob_end_clean();

    // Write CSS first
    $mpdf->WriteHTML($template['css'], \Mpdf\HTMLParserMode::HEADER_CSS);
    
    // Then write HTML
    $mpdf->WriteHTML($template['html'], \Mpdf\HTMLParserMode::HTML_BODY);

    // Generate unique filename
    $filename = 'family_agreement_' . $family['head_of_family'] . '_' . date('Y-m-d_His') . '.pdf';

    if ($isAjaxRequest) {
        // Save PDF to file and return JSON response
        $mpdf->Output($filename, 'D');
        echo json_encode([
            'success' => true, 
            'message' => 'Family agreement generated successfully'
        ]);
    } else {
        // Output PDF directly for download
        $mpdf->Output($filename, 'I');
    }
    exit;
    
} catch (Exception $e) {
    if ($isAjaxRequest) {
        echo json_encode(['success' => false, 'message' => 'Error generating agreement: ' . $e->getMessage()]);
    } else {
        die('Error generating agreement: ' . $e->getMessage());
    }
} 