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

// Check if it's an AJAX request
$isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

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
    $familyQuery = "SELECT * FROM families WHERE family_id = ? AND tenant_id = ? AND branch_id = ?";
    $familyStmt = $pdo->prepare($familyQuery);
    $familyStmt->execute([$familyId, $tenant_id, $branch_id]);
    $family = $familyStmt->fetch(PDO::FETCH_ASSOC);

    if (!$family) {
        die('Family not found');
    }

    // Get all family members' booking details
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
        WHERE um.family_id = ? AND um.tenant_id = ? AND um.branch_id = ?
        GROUP BY um.booking_id
        ORDER BY um.booking_id ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id'], $familyId, $tenant_id, $branch_id]);
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
        $templatePath = 'family_cancellation_template_en.php';
    }

    // Get the HTML and CSS content by capturing the output buffer
    ob_start();
    $template = include $templatePath;
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
    <title>Family Cancellation - ' . htmlspecialchars($family_name) . '</title>
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
            'message' => 'Family Umrah cancellation form generated successfully',
            'html' => $html,
            'family_members_count' => count($bookings)
        ]);
    } else {
        echo $html;
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