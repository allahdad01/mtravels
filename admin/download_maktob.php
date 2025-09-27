<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];

// Include database connection
include '../includes/db.php';
include '../includes/conn.php';

// Get maktob ID from URL
$maktob_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($maktob_id <= 0) {
    die("Invalid maktob ID");
}

// Query to get maktob details
$query = "SELECT m.*, u.name as sender_name 
          FROM maktobs m 
          JOIN users u ON m.sender_id = u.id 
          WHERE m.id = $maktob_id AND m.tenant_id = $tenant_id";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    die("Maktob not found");
}

$maktob = mysqli_fetch_assoc($result);

// Get company information from settings table
$settings_query = "SELECT * FROM settings WHERE id = 1 AND tenant_id = $tenant_id";
$settings_result = mysqli_query($conn, $settings_query);
$settings = [];

if ($settings_result && mysqli_num_rows($settings_result) > 0) {
    $settings = mysqli_fetch_assoc($settings_result);
} else {
    // Default values if settings not found
    $settings = [
        'agency_name' => 'AL MOQADAS TRAVEL & TOURS',
        'address' => 'End of Jadayi Maiwand Road [Pashtoon Tower, Kabul Afghanistan]',
        'phone' => '+93 785 555 551',
        'email' => 'Almoqadas_travel@yahoo.com',
        'logo' => 'log.png'
    ];
}

// Include Composer autoloader (adjust path as needed)
require_once '../vendor/autoload.php';

// Set language from maktob data
$lang = isset($maktob['language']) ? strtolower($maktob['language']) : 'english';

// Create new mPDF instance with different settings based on language
if ($lang == 'dari' || $lang == 'pashto') {
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
        ]
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
        'margin_footer' => 5
    ]);
}

// Define the HTML content with styling to match the agreement layout
$html = '
<!DOCTYPE html>
<html ' . (($lang == 'dari' || $lang == 'pashto') ? 'dir="rtl"' : '') . '>
<head>
    <meta charset="UTF-8">
    <title>Maktob ' . htmlspecialchars($maktob['maktob_number']) . '</title>
    <style>
        body {
            font-family: ' . (($lang == 'dari' || $lang == 'pashto') ? 'xwzar' : 'Arial, Helvetica, sans-serif') . ';
            line-height: 1.6;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .container {
            position: relative;
            padding-bottom: 30px;
        }
        .header {
            margin-bottom: 20px;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .logo-section {
            width: 20%;
            text-align: left;
            vertical-align: top;
        }
        .logo {
            max-height: 80px;
        }
        .company-section {
            width: 60%;
            text-align: center;
            vertical-align: top;
        }
        .company-section h1 {
            margin: 0;
            color: #333;
            font-size: 18px;
            white-space: nowrap;
        }
        .company-info {
            font-size: 12px;
            margin-top: 5px;
        }
        .date-section {
            width: 20%;
            text-align: ' . (($lang == 'dari' || $lang == 'pashto') ? 'left' : 'right') . ';
            font-size: 12px;
            line-height: 1.4;
            vertical-align: top;
        }
        .title-row {
            margin: 10px 0;
            text-align: center;
        }
        .document-title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            color: #d32f2f;
        }
        .content-section {
            margin-bottom: 20px;
        }
        .content-section h2 {
            font-size: 16px;
            margin-bottom: 10px;
            padding-bottom: 5px;
            color: #333;
        }
        .detail-row {
            margin-bottom: 8px;
        }
        .detail-label {
            font-weight: bold;
            ' . (($lang == 'dari' || $lang == 'pashto') ? 'text-align: right;' : '') . '
        }
        .maktob-body {
            margin: 20px 0;
            text-align: justify;
            line-height: 1.5;
        }
        .signature-section {
            margin-top: 30px;
            text-align: ' . (($lang == 'dari' || $lang == 'pashto') ? 'left' : 'right') . ';
        }
        .signature-box {
            width: 40%;
            float: ' . (($lang == 'dari' || $lang == 'pashto') ? 'left' : 'right') . ';
        }
        .signature-line {
            border-top: 1px solid #333;
            padding-top: 5px;
            text-align: center;
        }
        .footer {
            text-align: center;
            font-size: 10px;
            color: #555;
            border-top: 1px solid #eee;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <table class="header-table">
                <tr>
                    <td class="' . (($lang == 'dari' || $lang == 'pashto') ? 'date-section' : 'logo-section') . '">
                        ' . (($lang == 'dari' || $lang == 'pashto') ? 
                          '<div>Date: ' . date('M j, Y', strtotime($maktob['maktob_date'])) . '</div>
                           <div>Ref: MAKTOB-' . htmlspecialchars($maktob['maktob_number']) . '</div>' : 
                          '<img src="../uploads/logo/' . htmlspecialchars($settings['logo']) . '" alt="Company Logo" class="logo">') . '
                    </td>
                    <td class="company-section">
                        <h1>' . htmlspecialchars($settings['title']) . '</h1>
                        <div class="company-info">
                            ' . htmlspecialchars($settings['address']) . '<br>
                            Tel: ' . htmlspecialchars($settings['phone']) . ' | Email: ' . 
                            htmlspecialchars($settings['email']) . '
                        </div>
                    </td>
                    <td class="' . (($lang == 'dari' || $lang == 'pashto') ? 'logo-section' : 'date-section') . '">
                        ' . (($lang == 'dari' || $lang == 'pashto') ? 
                          '<img src="../uploads/' . htmlspecialchars($settings['logo']) . '" alt="Company Logo" class="logo">' : 
                          '<div>Date: ' . date('M j, Y', strtotime($maktob['maktob_date'])) . '</div>
                           <div>Ref: Letter-' . htmlspecialchars($maktob['maktob_number']) . '</div>') . '
                    </td>
                </tr>
            </table>
            <div class="title-row">
                <div class="document-title">' . (($lang == 'dari' || $lang == 'pashto') ? 'مکتوب رسمی' : 'OFFICIAL LETTER') . '</div>
            </div>
        </div>
        
        <div class="content-section">
            <h2>' . (($lang == 'dari' || $lang == 'pashto') ? 'معلوماتی دریافت کننده' : 'Recipient Information') . '</h2>
            <div class="detail-row">
                <div class="detail-label">' . (($lang == 'dari' || $lang == 'pashto') ? 'به:' : 'To:') . '</div>
                <div class="detail-value">' . htmlspecialchars($maktob['company_name']) . '</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">' . (($lang == 'dari' || $lang == 'pashto') ? 'موضوع:' : 'Subject:') . '</div>
                <div class="detail-value">' . htmlspecialchars($maktob['subject']) . '</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">' . (($lang == 'dari' || $lang == 'pashto') ? 'نمبر مکتوب:' : 'Reference:') . '</div>
                <div class="detail-value">Al Moqadas/' . htmlspecialchars($maktob['maktob_number']) . '/' . date('Y', strtotime($maktob['maktob_date'])) . '</div>
            </div>
        </div>
        
        <div class="maktob-body">
            ' . nl2br(htmlspecialchars($maktob['content'])) . '
        </div>
        
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">' . htmlspecialchars($maktob['sender_name']) . '</div>
                <div style="text-align: center; margin-top: 5px;">Authorized Signatory</div>
            </div>
        </div>
    </div>
';

// Define the footer HTML
$footerHTML = '
<div class="footer">
    <p>' . htmlspecialchars($settings['agency_name']) . ' | ' . htmlspecialchars($settings['address']) . ' | Tel: ' . htmlspecialchars($settings['phone']) . ' | Email: ' . htmlspecialchars($settings['email']) . '</p>
    <p>This document is officially issued on ' . date('M j, Y', strtotime($maktob['maktob_date'])) . ' | Ref: LETTER-' . htmlspecialchars($maktob['maktob_number']) . '</p>
</div>
';

// Set the footer
$mpdf->SetHTMLFooter($footerHTML);

// Write the HTML content to the PDF
$mpdf->WriteHTML($html);

// Create letters directory if it doesn't exist
$letters_dir = "../uploads/letters";
if (!is_dir($letters_dir)) {
    mkdir($letters_dir, 0755, true);
}

// Generate filename with timestamp to ensure uniqueness
$timestamp = date('Y-m-d_His');
$filename = "letter_{$maktob['maktob_number']}_ {$maktob['company_name']}_{$maktob['subject']}_ {$timestamp}.pdf";
$file_path = "{$letters_dir}/{$filename}";

// Save a copy to the letters folder
$mpdf->Output($file_path, 'F');

// Update database with PDF file path if it doesn't already have one
$file_path_db = "uploads/letters/{$filename}";
$update_query = "UPDATE maktobs SET pdf_path = '{$file_path_db}' WHERE id = {$maktob_id} AND (pdf_path IS NULL OR pdf_path = '')";
mysqli_query($conn, $update_query);

// Output the PDF for download
$mpdf->Output('Maktob_' . $maktob['maktob_number'] . '.pdf', 'I');
exit; 