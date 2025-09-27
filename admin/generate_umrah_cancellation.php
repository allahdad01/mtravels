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
$lang_file = __DIR__ . '/../includes/languages/' . $lang . '/umrah_cancellation.php';

if (file_exists($lang_file)) {
    $l = require($lang_file);
} else {
    // Fallback to English
    $l = require(__DIR__ . '/../includes/languages/en/umrah_cancellation.php');
}
$isRtl = ($lang === 'ps' || $lang === 'fa');

// Create directory if it doesn't exist with more robust error handling
$uploadsDir = '../uploads/umrah/umrah_cancellations';
$absoluteUploadsDir = realpath(__DIR__ . '/' . $uploadsDir);

if (!$absoluteUploadsDir) {
    try {
        // Attempt to create the directory with full path
        $absoluteUploadsDir = mkdir($uploadsDir, 0755, true) ? realpath(__DIR__ . '/' . $uploadsDir) : false;

        if (!$absoluteUploadsDir) {
            throw new Exception('Failed to create uploads directory');
        }
    } catch (Exception $dirError) {
        error_log('Directory Creation Error: ' . $dirError->getMessage());

        if ($isAjaxRequest) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create uploads directory: ' . $dirError->getMessage()
            ]);
            exit;
        } else {
            die('Failed to create uploads directory: ' . $dirError->getMessage());
        }
    }
}

// Ensure the directory is writable
if (!is_writable($absoluteUploadsDir)) {
    $errorMessage = 'Uploads directory is not writable: ' . $absoluteUploadsDir;
    error_log($errorMessage);

    if ($isAjaxRequest) {
        echo json_encode([
            'success' => false,
            'message' => $errorMessage
        ]);
        exit;
    } else {
        die($errorMessage);
    }
}

// Check if booking ID is provided
if (!isset($_GET['booking_id'])) {
    die('Booking ID is required');
}

$bookingId = intval($_GET['booking_id']);

try {
    // Get booking details with related information
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
        WHERE um.booking_id = ? AND um.tenant_id = ?
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $bookingId, $tenant_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        die('Booking not found');
    }
    $pilgrim_name = $booking['name'];

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

    // Get the HTML and CSS content by capturing the output buffer
    ob_start();
    $template = include 'templates/umrah_cancellation_form.php';
    ob_end_clean();

    // Write CSS first
    $mpdf->WriteHTML($template['css'], \Mpdf\HTMLParserMode::HEADER_CSS);

    // Then write HTML
    $mpdf->WriteHTML($template['html'], \Mpdf\HTMLParserMode::HTML_BODY);

    // Generate unique filename
    $filename = 'umrah_cancellation_' . $pilgrim_name . '_' . date('Y-m-d_His') . '.pdf';
    $filepath = $absoluteUploadsDir . '/' . $filename;

    // Relative URL for web access
    $relativeUrl = 'uploads/umrah/umrah_cancellations/' . $filename;

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
            'message' => 'Umrah cancellation form generated successfully',
            'file_url' => $relativeUrl
        ]);
    } else {
        // Output PDF directly for download
        $mpdf->Output($filename, 'I');
    }
    exit;

} catch (Exception $e) {
    if ($isAjaxRequest) {
        echo json_encode(['success' => false, 'message' => 'Error generating cancellation form: ' . $e->getMessage()]);
    } else {
        die('Error generating cancellation form: ' . $e->getMessage());
    }
} 