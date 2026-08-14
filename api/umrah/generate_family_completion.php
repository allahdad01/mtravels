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
$lang_file = '../../includes/languages/' . $lang . '/family_completion.php';

if (file_exists($lang_file)) {
    $l = require($lang_file);
} else {
    // Fallback to English
    $l = require('..//../includes/languages/en/umrah_completion.php');
}
$isRtl = ($lang === 'ps' || $lang === 'fa');

// Create directory if it doesn't exist
$uploadsDir = '../../uploads/umrah/family_completions';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Check if family ID is provided
if (!isset($_GET['family_id'])) {
    die('Family ID is required');
}

$familyId = intval($_GET['family_id']);

// Get selected member IDs if provided
$selectedMemberIds = [];
if (isset($_GET['member_ids'])) {
    $memberIds = $_GET['member_ids'];
    if (!is_array($memberIds)) {
        $memberIds = [$memberIds];
    }
    $selectedMemberIds = array_map(function($id) { return intval($id); }, $memberIds);
}

try {
     // Get family details with related information
     $query = "
         SELECT f.*, u.name as processed_by_name
         FROM families f
         LEFT JOIN users u ON u.id = ?
         WHERE f.family_id = ? AND f.tenant_id = ? AND f.branch_id = ?
     ";

     $stmt = $pdo->prepare($query);
     $stmt->execute([$user_id, $familyId, $tenant_id, $branch_id]);
     $family = $stmt->fetch(PDO::FETCH_ASSOC);
     
     if (!$family) {
         die('Family not found');
     }

     // Get family members
     $membersQuery = "
         SELECT ub.*, c.name as client_name, GROUP_CONCAT(DISTINCT s.name) as supplier_name
         FROM umrah_bookings ub
         LEFT JOIN clients c ON ub.sold_to = c.id
         LEFT JOIN umrah_booking_services ubs ON ub.booking_id = ubs.booking_id
         LEFT JOIN umrah_fulfillments uff ON uff.booking_service_id = ubs.id AND uff.fulfillment_type = 'flight' AND uff.status <> 'cancelled' AND uff.id = (SELECT MIN(uff2.id) FROM umrah_fulfillments uff2 WHERE uff2.booking_service_id = ubs.id)
         LEFT JOIN suppliers s ON s.id = COALESCE(uff.supplier_id, ubs.supplier_id)
         WHERE ub.family_id = ? AND ub.tenant_id = ? AND ub.branch_id = ?
     ";
     
     // Filter by selected member IDs if provided
     if (!empty($selectedMemberIds)) {
         $placeholders = implode(',', array_fill(0, count($selectedMemberIds), '?'));
         $membersQuery .= " AND ub.booking_id IN ($placeholders)";
         $params = array_merge([$familyId, $tenant_id, $branch_id], $selectedMemberIds);
     } else {
         $params = [$familyId, $tenant_id, $branch_id];
     }
     
     $membersQuery .= " GROUP BY ub.booking_id";
     
     $membersStmt = $pdo->prepare($membersQuery);
     $membersStmt->execute($params);
     $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);

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
    

    // Get completion details from POST data
    $completionDetails = $_POST;

    // Check if it's an AJAX request
    $isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    // Get the HTML and CSS content by capturing the output buffer
    ob_start();
    $template = include 'family_completion_template_' . $lang . '.php';
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
    <title>Family Completion - ' . htmlspecialchars($family['head_of_family']) . '</title>
    <style>' . $template['css'] . $printStyles . '</style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Print</button>
    ' . $template['html'] . '
    <script src="../../js/umrah/document-editor.js"></script>
</body>
</html>';

    if ($isAjaxRequest) {
        echo json_encode([
            'success' => true,
            'message' => 'Family completion form generated successfully',
            'html' => $html
        ]);
    } else {
        echo $html;
    }
    exit;
    
} catch (Exception $e) {
    if ($isAjaxRequest) {
        echo json_encode(['success' => false, 'message' => 'Error generating completion form: ' . $e->getMessage()]);
    } else {
        die('Error generating completion form: ' . $e->getMessage());
    }
} 