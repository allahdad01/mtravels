<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once('../../includes/db.php');
require_once('../../admin/security.php');

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'];

// Language handling
$lang = isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ps', 'fa']) ? $_GET['lang'] : 'en';
$lang_file = '..//../includes/languages/' . $lang . '/umrah_agreement.php';

if (file_exists($lang_file)) {
    $l = require($lang_file);
} else {
    // Fallback to English
    $l = require('../../includes/languages/en/umrah_agreement.php');
}
$isRtl = ($lang === 'ps' || $lang === 'fa');

// Create directory if it doesn't exist
$uploadsDir = '../../uploads/umrah/umrah_agreements';
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
        SELECT um.*, f.package_type, f.head_of_family as family_name,
               u.name as processed_by_name, m.name as account_name,
               GROUP_CONCAT(DISTINCT s.name) as supplier_name, c.name as client_name
        FROM umrah_bookings um
        LEFT JOIN families f ON um.family_id = f.family_id
        LEFT JOIN users u ON u.id = ?
        LEFT JOIN main_account m ON um.paid_to = m.id
        LEFT JOIN umrah_booking_services ubs ON um.booking_id = ubs.booking_id
        LEFT JOIN suppliers s ON ubs.supplier_id = s.id
        LEFT JOIN clients c ON um.sold_to = c.id
        WHERE um.booking_id = ? AND um.tenant_id = ? AND um.branch_id = ?
        GROUP BY um.booking_id
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $bookingId, $tenant_id, $branch_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        die('Booking not found');
    }
    $pilgrim_name = $booking['name'];
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
    error_log("Settings Error: " . $e->getMessage());
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
    error_log("Branch Error: " . $e->getMessage());
    $branch = null;
}

    // Check if it's an AJAX request
    $isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    // Get the HTML and CSS content by capturing the output buffer
    ob_start();
    $template = include 'umrah_agreement_template.php';
    ob_end_clean();
    
    // Output HTML directly with print styles
    $printStyles = '
            @media print {
                @page {
                    size: A4 portrait;
                    margin: 15mm;
                }
                body { margin: 0; font-size: 9pt; }
                .container { max-width: none; width: auto; padding: 0; }
                .no-print { display: none !important; }
                .print-button { display: none !important; }
                .header { margin-bottom: 5px; padding-bottom: 5px; }
                .section-header { padding: 2px 5px; margin-bottom: 3px; font-size: 9pt; }
                .details-table td { padding: 2px 4px; font-size: 8pt; }
                .members-table th, .members-table td { padding: 3px 4px; font-size: 7pt; }
                .terms-container { padding: 5px; margin-top: 5px; }
                .terms-list { font-size: 7pt; }
                .signatures { margin-top: 10px; }
                .footer { margin-top: 10px; font-size: 6pt; }
                * { page-break-inside: avoid; }
            }
            .print-button {
                position: fixed;
                top: 20px;
                right: 20px;
                background-color: #2c3e50;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 12pt;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                z-index: 1000;
            }
            .print-button:hover {
                background-color: #34495e;
            }
        ';
    
        $html = '<!DOCTYPE html>
    <html lang="' . ($isRtl ? ($lang === 'fa' ? 'fa' : 'ps') : 'en') . '"' . ($isRtl ? ' dir="rtl"' : '') . '>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Umrah Agreement - ' . htmlspecialchars($pilgrim_name) . '</title>
        <style>' . $template['css'] . $printStyles . '</style>
    </head>
    <body>
        <button class="print-button no-print" onclick="window.print()">🖨️ Print</button>
        ' . $template['html'] . '
    </body>
    </html>';
    
    
        if ($isAjaxRequest) {
            echo json_encode([
                'success' => true,
                'message' => 'Umrah agreement generated successfully',
                'html' => $html
            ]);
        } else {
            echo $html;
        }
        exit;
    
} catch (Exception $e) {
    if ($isAjaxRequest) {
        echo json_encode(['success' => false, 'message' => 'Error generating agreement: ' . $e->getMessage()]);
    } else {
        die('Error generating agreement: ' . $e->getMessage());
    }
} 