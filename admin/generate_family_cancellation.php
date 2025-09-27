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

// Language handling
$lang = isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ps', 'fa']) ? $_GET['lang'] : 'en';
$lang_file = __DIR__ . '/../includes/languages/' . $lang . '/umrah_cancellation.php';

if (file_exists($lang_file)) {
    $l = require($lang_file);
} else {
    // Fallback to English
    $l = require(__DIR__ . '/../includes/languages/en/umrah_cancellation.php');
}
$isRtl = ($lang === 'ps' || $lang === 'fa');

// Create directory if it doesn't exist
$uploadsBaseDir = '../uploads';
$uploadsSubDir = 'umrah/umrah_cancellations';
$uploadsDir = $uploadsBaseDir . '/' . $uploadsSubDir;

// Ensure base uploads directory exists
if (!is_dir($uploadsBaseDir)) {
    mkdir($uploadsBaseDir, 0755, true);
}

// Ensure specific uploads subdirectory exists
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Get absolute path
$absoluteUploadsDir = realpath($uploadsDir);

if (!$absoluteUploadsDir) {
    throw new Exception('Failed to create uploads directory');
}

// Ensure the directory is writable
if (!is_writable($absoluteUploadsDir)) {
    throw new Exception('Uploads directory is not writable: ' . $absoluteUploadsDir);
}

// Check if family ID is provided (prioritize family_id over booking_id)
$familyId = null;
$bookingIds = [];

if (isset($_GET['family_id'])) {
    $familyId = intval($_GET['family_id']);
} elseif (isset($_GET['booking_id'])) {
    // If only booking_id is provided, get the family_id from that booking
    $bookingId = intval($_GET['booking_id']);
    $familyQuery = "SELECT family_id FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ?";
    $familyStmt = $pdo->prepare($familyQuery);
    $familyStmt->execute([$bookingId, $tenant_id]);
    $familyResult = $familyStmt->fetch(PDO::FETCH_ASSOC);

    if ($familyResult) {
        $familyId = $familyResult['family_id'];
    } else {
        die('Booking not found');
    }
} else {
    die('Family ID or Booking ID is required');
}

try {
    // Get family information
    $familyQuery = "SELECT * FROM families WHERE family_id = ?";
    $familyStmt = $pdo->prepare($familyQuery);
    $familyStmt->execute([$familyId]);
    $family = $familyStmt->fetch(PDO::FETCH_ASSOC);

    if (!$family) {
        die('Family not found');
    }

    // Get all family members' booking details
    $query = "
        SELECT um.*, f.package_type, f.head_of_family as family_name,
               u.name as processed_by_name, m.name as account_name,
               s.name as supplier_name, c.name as client_name
        FROM umrah_bookings um
        LEFT JOIN families f ON um.family_id = f.family_id
        LEFT JOIN users u ON u.id = ?
        LEFT JOIN main_account m ON um.paid_to = m.id
        LEFT JOIN suppliers s ON um.supplier = s.id
        LEFT JOIN clients c ON um.sold_to = c.id
        WHERE um.family_id = ? AND um.tenant_id = ?
        ORDER BY um.booking_id ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id'], $familyId, $tenant_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$bookings) {
        die('No bookings found for this family');
    }

    // Extract booking IDs for later use
    $bookingIds = array_column($bookings, 'booking_id');

    // Use family name for filename, or first member's name as fallback
    $family_name = $family['head_of_family'] ?: $bookings[0]['name'];

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
    $mpdf->SetWatermarkText($l['cancelled']);
    $mpdf->showWatermarkText = true;
    $mpdf->watermarkTextAlpha = 0.1;

    // Check if it's an AJAX request
    $isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    // Prepare cancellation details
    $cancellationDetails = [
        'status' => [],
        'notes' => [],
        'additional_notes' => $_GET['cancellation_reason'] ?? null
    ];

    // Parse returned items and conditions
    $returnedItems = isset($_GET['returned_items']) ? json_decode($_GET['returned_items'], true) : [];
    $itemConditions = isset($_GET['item_condition']) ? json_decode($_GET['item_condition'], true) : [];
    $itemNotes = isset($_GET['item_notes']) ? json_decode($_GET['item_notes'], true) : [];

    // Process returned documents for each member
    foreach ($bookings as $booking) {
        $memberId = $booking['booking_id'];
        $memberPrefix = 'member_' . $memberId . '_';

        // Initialize status for this member
        $cancellationDetails['status'][$memberId] = [];
        $cancellationDetails['notes'][$memberId] = '';

        // Check document types
        $docTypes = ['passport', 'id_card', 'photos', 'other_docs'];
        foreach ($docTypes as $docType) {
            $returnKey = $memberPrefix . $docType;

            // Check if document is returned
            if (isset($returnedItems[$returnKey]) && $returnedItems[$returnKey] === '1') {
                $cancellationDetails['status'][$memberId][$docType] = 'returned';
            }

            // Get condition and notes
            $conditionKey = $memberPrefix . $docType;
            if (isset($itemConditions[$conditionKey])) {
                $cancellationDetails['status'][$memberId][$docType . '_condition'] = $itemConditions[$conditionKey];
            }

            // Get notes
            if (isset($itemNotes[$conditionKey])) {
                $cancellationDetails['notes'][$memberId] .=
                    ucfirst($docType) . ': ' . $itemNotes[$conditionKey] . "\n";
            }
        }
    }

    // Prepare template variables
    $templateVars = [
        'family' => $family,
        'bookings' => $bookings,
        'members' => $bookings,
        'settings' => $settings,
        'cancellationDetails' => $cancellationDetails
    ];

    // Select template based on language
    $templatePath = 'templates/family_cancellation_template_' . $lang . '.php';

    // Check if language-specific template exists, fallback to English
    if (!file_exists(__DIR__ . '/' . $templatePath)) {
        error_log("Language template not found: $templatePath. Falling back to English.");
        $templatePath = 'templates/family_cancellation_template_en.php';
    }

    // Get the HTML and CSS content by capturing the output buffer
    ob_start();
    $template = include $templatePath;
    ob_end_clean();

    // Write CSS first
    $mpdf->WriteHTML($template['css'], \Mpdf\HTMLParserMode::HEADER_CSS);

    // Then write HTML
    $mpdf->WriteHTML($template['html'], \Mpdf\HTMLParserMode::HTML_BODY);

    // Generate unique filename
    $filename = 'family_umrah_cancellation_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $family_name) . '_' . date('Y-m-d_His') . '.pdf';
    $filepath = $absoluteUploadsDir . '/' . $filename;

    // Determine the correct relative URL
    $currentScriptPath = $_SERVER['PHP_SELF'];
    $adminIndex = strpos($currentScriptPath, '/admin/');
    $salesIndex = strpos($currentScriptPath, '/sales/');
    $financeIndex = strpos($currentScriptPath, '/finance/');
    $umrahIndex = strpos($currentScriptPath, '/umrah/');
    
    $directoryIndex = $adminIndex !== false ? $adminIndex : 
                      ($salesIndex !== false ? $salesIndex : 
                      ($financeIndex !== false ? $financeIndex : 
                      ($umrahIndex !== false ? $umrahIndex : false)));
    
    if ($directoryIndex !== false) {
        // If the script is in one of these directories, remove the directory name from the path
        $relativeUrl = substr($currentScriptPath, 0, $directoryIndex + 1) . 'uploads/' . $uploadsSubDir . '/' . $filename;
    } else {
        // Fallback to default relative URL
        $relativeUrl = 'uploads/' . $uploadsSubDir . '/' . $filename;
    }

    // Verify the file can be created
    try {
        // Attempt to create the file
        $mpdf->Output($filepath, 'F');
        
        // Verify file was created
        if (!file_exists($filepath)) {
            throw new Exception('Failed to save PDF file');
        }
    } catch (Exception $fileError) {
        error_log('PDF Generation Error: ' . $fileError->getMessage());
        
        if ($isAjaxRequest) {
            echo json_encode([
                'success' => false, 
                'message' => 'Failed to generate PDF: ' . $fileError->getMessage()
            ]);
            exit;
        } else {
            die('Failed to generate PDF: ' . $fileError->getMessage());
        }
    }

    if ($isAjaxRequest) {
        // Save PDF to file and return JSON response
        echo json_encode([
            'success' => true, 
            'message' => 'Family Umrah cancellation form generated successfully', 
            'file_url' => $relativeUrl,
            'family_members_count' => count($bookings)
        ]);
    } else {
        // Output PDF directly for download
        $mpdf->Output($filename, 'I');
    }
    exit;

} catch (Exception $e) {
    if ($isAjaxRequest) {
        echo json_encode(['success' => false, 'message' => 'Error generating family cancellation form: ' . $e->getMessage()]);
    } else {
        die('Error generating family cancellation form: ' . $e->getMessage());
    }
}
?>