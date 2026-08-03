<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once('../../includes/db.php');
require_once('../../admin/security.php');
require_once('../../vendor/autoload.php');

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Language handling
$lang = isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ps', 'fa']) ? $_GET['lang'] : 'en';
$lang_file = __DIR__ . '/../includes/languages/' . $lang . '/family_receipt.php';

if (file_exists($lang_file)) {
    $l = require($lang_file);
} else {
    // Fallback to English
    $l = require(__DIR__ . '/../includes/languages/en/umrah_receipt.php');
}
$isRtl = ($lang === 'ps' || $lang === 'fa');

// Create directory if it doesn't exist
$uploadsDir = '../uploads/umrah/family_receipts';
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
        WHERE f.family_id = ? AND f.tenant_id = ? AND f.branch_id = ?
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id'], $familyId, $tenant_id, $branch_id]);
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
        WHERE ub.family_id = ? AND ub.tenant_id = ? AND ub.branch_id = ?
    ";
    $membersStmt = $pdo->prepare($membersQuery);
    $membersStmt->execute([$familyId, $tenant_id, $branch_id]);
    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

    // Keep original names for the output filename
    $originalHeadOfFamily = $family['head_of_family'];

    // Auto-translate names into the document language (MyMemory - free)
    require_once __DIR__ . '/../../includes/translate_helper.php';
    translate_name_fields($family, $lang, ['head_of_family']);
    translate_name_fields($members, $lang, ['name', 'client_name']);

// Fetch settings data (using PDO connection)
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $settingStmt->execute();
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        // Fallback defaults if no settings row found
        $settings = ['agency_name' => 'Travel Agency'];
    }
} catch (Exception $e) {
    $settings = ['agency_name' => 'Travel Agency'];
}

// Fetch branch data (from branches table)
try {
    $branchStmt = $pdo->prepare("SELECT name, code, phone, address, email FROM branches WHERE id = ? AND tenant_id = ?");
    $branchStmt->bindParam(1, $branch_id, PDO::PARAM_INT);
    $branchStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $branchStmt->execute();
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branch = null;
}
    

    // Process document details from POST data
    $documentDetails = [
        'status' => []
    ];

    // Process member documents status
    foreach ($members as $member) {
        $bookingId = $member['booking_id'];
        $documentDetails['status'][$bookingId] = [];

        // Passport status
        if (isset($_POST['passport_status_' . $bookingId])) {
            $documentDetails['status'][$bookingId]['passport'] = $_POST['passport_status_' . $bookingId];
        }

        // ID Card/Tazkira status
        if (isset($_POST['id_card_status_' . $bookingId])) {
            $documentDetails['status'][$bookingId]['id_card'] = $_POST['id_card_status_' . $bookingId];
        }

        // Other document status
        if (isset($_POST['other_doc_check_' . $bookingId]) && $_POST['other_doc_check_' . $bookingId]) {
            $documentDetails['status'][$bookingId]['other_doc'] = [
                'name' => $_POST['other_doc_name_' . $bookingId] ?? '',
                'status' => $_POST['other_doc_status_' . $bookingId] ?? ''
            ];
        }
    }

    // Add additional notes if provided
    if (isset($_POST['additional_notes'])) {
        $documentDetails['additional_notes'] = $_POST['additional_notes'];
    }

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
    $template = include 'family_receipt_template_' . $lang . '.php';
    ob_end_clean();

    // Write CSS first
    $mpdf->WriteHTML($template['css'], \Mpdf\HTMLParserMode::HEADER_CSS);
    
    // Then write HTML
    $mpdf->WriteHTML($template['html'], \Mpdf\HTMLParserMode::HTML_BODY);

    // Generate unique filename
    $filename = 'family_receipt_' . $originalHeadOfFamily . '_' . date('Y-m-d_His') . '.pdf';

    if ($isAjaxRequest) {
        // Save PDF to file and return JSON response
        $mpdf->Output($filename, 'D');
        echo json_encode([
            'success' => true, 
            'message' => 'Family receipt generated successfully'
        ]);
    } else {
        // Output PDF directly for download
        $mpdf->Output($filename, 'I');
    }
    exit;
    
} catch (Exception $e) {
    if ($isAjaxRequest) {
        echo json_encode(['success' => false, 'message' => 'Error generating receipt: ' . $e->getMessage()]);
    } else {
        die('Error generating receipt: ' . $e->getMessage());
    }
} 