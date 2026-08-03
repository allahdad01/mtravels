<?php
// Include necessary files
require_once('../../includes/db.php');
require_once('../../admin/security.php');
require_once('../../includes/language_helpers.php');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Define a fallback translation function in case the language helper doesn't provide it
if (!function_exists('__')) {
    function __($text) {
        return $text;
    }
}

// Enforce authentication
enforce_auth();

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['selected_pilgrims'])) {
    die('Invalid request');
}

// Decode selected pilgrims
$selectedPilgrims = json_decode($_POST['selected_pilgrims'], true);
if (!is_array($selectedPilgrims) || empty($selectedPilgrims)) {
    die('No pilgrims selected');
}

// Get ID card settings
$idCardTitle = isset($_POST['id_card_title']) ? $_POST['id_card_title'] : 'Umrah Pilgrim ID';
$validityDays = isset($_POST['id_card_validity_days']) ? intval($_POST['id_card_validity_days']) : 45;
$cardColor = isset($_POST['id_card_color']) ? $_POST['id_card_color'] : 'primary';

// Get guide contact information
$guideMakkahName = isset($_POST['guide_makkah_name']) ? $_POST['guide_makkah_name'] : '';
$guideMakkahPhone = isset($_POST['guide_makkah_phone']) ? $_POST['guide_makkah_phone'] : '';
$guideMadinaName = isset($_POST['guide_madina_name']) ? $_POST['guide_madina_name'] : '';
$guideMadinaPhone = isset($_POST['guide_madina_phone']) ? $_POST['guide_madina_phone'] : '';
$groupName = isset($_POST['group_name']) ? $_POST['group_name'] : '';

// Calculate validity date
$validFrom = date('Y-m-d');
$validUntil = date('Y-m-d', strtotime("+{$validityDays} days"));

// Map color to hex code
$colorMap = [
    'primary' => '#007bff',
    'success' => '#28a745',
    'danger' => '#dc3545',
    'warning' => '#ffc107',
    'info' => '#17a2b8',
    'dark' => '#343a40'
];
$borderColor = $colorMap[$cardColor] ?? $colorMap['primary'];

// Fetch settings data
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $settingStmt->execute();
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        $settings = ['agency_name' => 'Travel Agency'];
    }
} catch (Exception $e) {
    $settings = ['agency_name' => 'Travel Agency'];
}

// Fetch branch data
try {
    $branchStmt = $pdo->prepare("SELECT name, code, phone, address, email FROM branches WHERE id = ? AND tenant_id = ?");
    $branchStmt->bindParam(1, $branch_id, PDO::PARAM_INT);
    $branchStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $branchStmt->execute();
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branch = null;
}

$agencyName = $settings['agency_name'] ?? 'Travel Agency';
$agencyContact = $branch['phone'] ?? '';

// Build full base URL for images
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];

// Get the base path from the REQUEST_URI
// The script is at /api/umrah/generate_id_cards.php
// We need to go back to /
$scriptPath = dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))); // Remove /api/umrah/
if ($scriptPath === '.' || $scriptPath === '/') {
    $scriptPath = '';
}

$baseUrl = $protocol . '://' . $host . rtrim($scriptPath, '/');

// Construct full image URLs
$agencyLogoUrl = isset($settings['logo']) ? 
    $baseUrl . '/uploads/logo/' . htmlspecialchars($settings['logo']) : 
    $baseUrl . '/assets/images/logo.png';

// Fetch pilgrim details
$pilgrimIds = array_map(function($pilgrim) {
    return $pilgrim['id'];
}, $selectedPilgrims);

if (empty($pilgrimIds)) {
    die('No pilgrim IDs provided');
}

$placeholders = str_repeat('?,', count($pilgrimIds) - 1) . '?';

$sql = "
    SELECT
        b.*,
        f.head_of_family,
        f.contact as family_contact,
        f.package_type
    FROM
        umrah_bookings b
    LEFT JOIN
        families f ON b.family_id = f.family_id AND f.tenant_id = ? AND f.branch_id = ?
    WHERE
        b.booking_id IN ({$placeholders}) AND b.tenant_id = ? AND b.branch_id = ?
";

$stmt = $pdo->prepare($sql);
$params = array_merge([$tenant_id, $branch_id], $pilgrimIds, [$tenant_id, $branch_id]);
$stmt->execute($params);
$pilgrims = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pilgrims)) {
    die('No pilgrim data found');
}

// Start HTML output
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umrah Pilgrim ID Cards</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
            font-family: 'Arial', sans-serif;
        }

        body {
            background-color: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 24px;
        }

        .print-button {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .print-button:hover {
            background-color: #0056b3;
        }

        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
            background: white;
            padding: 20px;
            border-radius: 8px;
        }

        .card-pair {
            display: flex;
            gap: 15px;
            page-break-inside: avoid;
            border: 1px solid #e0e0e0;
            padding: 15px;
            border-radius: 8px;
            background: white;
        }

        .id-card {
            flex: 0 0 58mm;
            width: 58mm;
            height: 86mm;
            border: 2px solid <?php echo $borderColor; ?>;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .id-card-header {
            background-color: <?php echo $borderColor; ?>;
            color: white;
            padding: 1.5mm 2mm;
            text-align: left;
            font-weight: bold;
            font-size: 5.5pt;
            line-height: 1.2;
            flex-shrink: 0;
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            gap: 1mm;
        }

        .id-card-header-text {
            flex: 1;
            overflow-wrap: break-word;
            word-wrap: break-word;
            hyphens: auto;
            font-size: 5.5pt;
            line-height: 1.2;
        }

        .id-card-header-logo {
            width: 8mm;
            height: 8mm;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .id-card-header-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .id-card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 2mm;
            overflow: hidden;
            font-size: 6pt;
        }

        .id-card-photo-container {
            flex: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 1mm 0;
        }

        .id-card-photo {
            width: 24mm;
            height: 28mm;
            border-radius: 2px;
            overflow: hidden;
            background: #f0f0f0;
            flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .id-card-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .pilgrim-info {
            text-align: center;
            margin-bottom: 0.5mm;
        }

        .pilgrim-name {
            font-weight: bold;
            font-size: 6.5pt;
            margin-bottom: 0.3mm;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: block;
        }

        .passport-number {
            font-size: 4.5pt;
            color: #666;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: block;
        }

        .id-card-details {
            flex: 0;
            font-size: 4.5pt;
            line-height: 1.15;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5mm;
        }

        .id-card-section {
            grid-column: 1 / -1;
            border-top: 0.8px solid <?php echo $borderColor; ?>;
            padding-top: 0.5mm;
            margin-top: 0.3mm;
        }

        .id-card-section:first-child {
            border-top: none;
            margin-top: 0;
            padding-top: 0;
        }

        .id-card-section-title {
            font-weight: bold;
            font-size: 4.8pt;
            color: <?php echo $borderColor; ?>;
            margin-bottom: 0.4mm;
            letter-spacing: 0.2px;
        }

        .id-card-field {
            margin-bottom: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding: 0.25mm 0;
            display: flex;
            justify-content: space-between;
            gap: 0.5mm;
        }

        .id-card-field-label {
            font-weight: 600;
            color: #333;
            flex-shrink: 0;
            min-width: 20px;
        }

        .id-card-field-value {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            text-align: right;
            color: #555;
        }

        .id-card-field-full {
            grid-column: 1 / -1;
            margin-bottom: 0;
            padding: 0.25mm 0;
            display: flex;
            justify-content: space-between;
            gap: 0.5mm;
            overflow: hidden;
        }

        .id-card-field-full .id-card-field-label {
            flex-shrink: 0;
            min-width: 30px;
        }

        .id-card-field-full .id-card-field-value {
            flex: 1;
            text-align: left;
        }

        .agency-info {
            font-size: 4.5pt;
            color: #666;
            margin-top: auto;
            padding-top: 0.5mm;
            border-top: 0.5px solid #ddd;
            text-align: center;
        }

        .visa-container {
            flex: 0 0 58mm;
            width: 58mm;
            height: 86mm;
            border: 2px solid #ccc;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .visa-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 4mm;
        }

        .visa-container.no-visa {
            background: linear-gradient(135deg, #fff3cd 0%, #fffbea 100%);
            color: #856404;
            font-weight: bold;
            text-align: center;
            padding: 10mm;
        }

        .visa-label {
            font-size: 10pt;
            line-height: 1.4;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .header {
                display: none;
            }

            .container {
                max-width: 100%;
                margin: 0;
            }

            .cards-container {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15mm;
                padding: 10mm;
                background: white;
            }

            .card-pair {
                page-break-inside: avoid;
                border: none;
                padding: 0;
                margin: 0;
            }

            @page {
                size: A4;
                margin: 10mm;
            }
        }

        @media screen and (max-width: 900px) {
            .cards-container {
                grid-template-columns: 1fr;
            }

            .card-pair {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo htmlspecialchars($idCardTitle); ?></h1>
            <div style="text-align: right;">
                <button class="print-button" onclick="window.print()">
                    <i style="margin-right: 5px;">🖨️</i>Print ID Cards
                </button>
                <!-- Debug: Show base URL -->
                <div style="font-size: 10px; color: #999; margin-top: 5px;">
                    Base URL: <?php echo htmlspecialchars($baseUrl); ?>
                </div>
            </div>
        </div>

        <div class="cards-container">
            <?php foreach ($pilgrims as $index => $pilgrim): ?>
                <?php
                // Determine photo path - use full URL
                $photoUrl = $baseUrl . '/assets/images/user/avatar-2.jpg'; // Default
                if (!empty($pilgrim['photo_path'])) {
                    $storedPhotoPath = trim($pilgrim['photo_path']);
                    
                    // Skip if it's already a full URL
                    if (strpos($storedPhotoPath, 'http://') === 0 || strpos($storedPhotoPath, 'https://') === 0) {
                        $photoUrl = $storedPhotoPath;
                    } else {
                        // Ensure path starts with /
                        if (strpos($storedPhotoPath, '/') !== 0) {
                            $storedPhotoPath = '/' . $storedPhotoPath;
                        }
                        $photoUrl = $baseUrl . $storedPhotoPath;
                    }
                    
                }

                // Check if uploaded photo exists
                if (isset($_FILES['photo_' . $pilgrim['booking_id']]) && 
                    $_FILES['photo_' . $pilgrim['booking_id']]['error'] === UPLOAD_ERR_OK) {
                    // Process and use uploaded photo as data URI
                    $tempFile = $_FILES['photo_' . $pilgrim['booking_id']]['tmp_name'];
                    $imageData = file_get_contents($tempFile);
                    $photoUrl = 'data:image/jpeg;base64,' . base64_encode($imageData);
                }

                // Determine visa path - use full URL
                $visaUrl = null;
                if (!empty($pilgrim['visa_path'])) {
                    $storedVisaPath = trim($pilgrim['visa_path']);
                    
                    // Skip if it's already a full URL
                    if (strpos($storedVisaPath, 'http://') === 0 || strpos($storedVisaPath, 'https://') === 0) {
                        $visaUrl = $storedVisaPath;
                    } else {
                        // Ensure path starts with /
                        if (strpos($storedVisaPath, '/') !== 0) {
                            $storedVisaPath = '/' . $storedVisaPath;
                        }
                        $visaUrl = $baseUrl . $storedVisaPath;
                    }
                }

                // Format dates
                $formattedDob = !empty($pilgrim['dob']) ? date('d M Y', strtotime($pilgrim['dob'])) : 'N/A';
                ?>
                <div class="card-pair">
                    <!-- ID Card -->
                    <div class="id-card">
                        <div class="id-card-header">
                            <div class="id-card-header-text">
                                <?php echo htmlspecialchars($idCardTitle); ?>
                            </div>
                            <?php if (!empty($agencyLogoUrl) && $agencyLogoUrl !== '#'): ?>
                            <div class="id-card-header-logo">
                                <img src="<?php echo htmlspecialchars($agencyLogoUrl); ?>" alt="Logo" onerror="this.style.display='none'">
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="id-card-body">
                            <div class="id-card-photo-container">
                                <div class="id-card-photo">
                                    <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="Photo" onerror="this.src='<?php echo $baseUrl; ?>/assets/images/user/avatar-2.jpg'">
                                </div>
                            </div>
                            
                            <div class="pilgrim-info">
                                <span class="pilgrim-name"><?php echo htmlspecialchars($pilgrim['name']); ?></span>
                                <span class="passport-number">
                                    <?php echo htmlspecialchars($pilgrim['passport_number']); ?>
                                </span>
                            </div>
                            
                            <div class="id-card-details">
                                <!-- Family Information Section -->
                                <div class="id-card-section">
                                    <?php if (!empty($pilgrim['head_of_family'])): ?>
                                    <div class="id-card-field-full">
                                        <span class="id-card-field-label">Family</span>
                                        <span class="id-card-field-value"><?php echo substr(htmlspecialchars($pilgrim['head_of_family']), 0, 24); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Travel Details Section -->
                                <div class="id-card-section">
                                    <div class="id-card-section-title">TRAVEL</div>
                                    
                                    <?php if (!empty($pilgrim['package_type'])): ?>
                                    <div class="id-card-field">
                                        <span class="id-card-field-label">Pkg</span>
                                        <span class="id-card-field-value"><?php echo substr(htmlspecialchars($pilgrim['package_type']), 0, 12); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($pilgrim['duration'])): ?>
                                    <div class="id-card-field">
                                        <span class="id-card-field-label">Days</span>
                                        <span class="id-card-field-value"><?php echo htmlspecialchars($pilgrim['duration']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($pilgrim['room_type'])): ?>
                                    <div class="id-card-field">
                                        <span class="id-card-field-label">Room</span>
                                        <span class="id-card-field-value"><?php echo substr(htmlspecialchars($pilgrim['room_type']), 0, 10); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Guides Section -->
                                <div class="id-card-section">
                                    <div class="id-card-section-title">GUIDES</div>
                                    
                                    <?php if (!empty($guideMakkahName)): ?>
                                    <div class="id-card-field-full">
                                        <span class="id-card-field-label">Makkah</span>
                                        <span class="id-card-field-value"><?php echo substr(htmlspecialchars($guideMakkahName), 0, 18); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($guideMakkahPhone)): ?>
                                    <div class="id-card-field-full">
                                        <span class="id-card-field-label">Ph</span>
                                        <span class="id-card-field-value"><?php echo substr(htmlspecialchars($guideMakkahPhone), 0, 22); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($guideMadinaName)): ?>
                                    <div class="id-card-field-full">
                                        <span class="id-card-field-label">Madina</span>
                                        <span class="id-card-field-value"><?php echo substr(htmlspecialchars($guideMadinaName), 0, 18); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($guideMadinaPhone)): ?>
                                    <div class="id-card-field-full">
                                        <span class="id-card-field-label">Ph</span>
                                        <span class="id-card-field-value"><?php echo substr(htmlspecialchars($guideMadinaPhone), 0, 22); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="agency-info">
                                Valid: <?php echo date('d M', strtotime($validFrom)); ?> - <?php echo date('d M Y', strtotime($validUntil)); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Visa Container -->
                    <div class="visa-container <?php echo empty($visaUrl) ? 'no-visa' : ''; ?>">
                        <?php if (!empty($visaUrl)): ?>
                            <img src="<?php echo htmlspecialchars($visaUrl); ?>" alt="Visa" class="visa-image" onerror="this.parentElement.classList.add('no-visa'); this.style.display='none'; this.parentElement.innerHTML += '<div class=visa-label>⚠️<br>VISA<br>ISSUES</div>'">
                        <?php else: ?>
                            <div class="visa-label">
                                ⚠️<br>VISA<br>ISSUES
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // Auto-print on load (optional)
        // window.print();
    </script>
    <script src="../../js/umrah/document-editor.js"></script>
</body>
</html>
