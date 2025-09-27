<?php
// Include necessary files
require_once('../includes/db.php');
require_once('../includes/conn.php');
require_once('security.php');
require_once('../vendor/autoload.php');
require_once('../includes/language_helpers.php');
$tenant_id = $_SESSION['tenant_id'];
// Define a fallback translation function in case the language helper doesn't provide it
if (!function_exists('__')) {
    function __($text) {
        return $text; // Simply return the original text if no translation is available
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

// Fetch agency settings
$settingsQuery = "SELECT * FROM settings WHERE tenant_id = ?";
$settingsStmt = $pdo->prepare($settingsQuery);
$settingsStmt->execute([$tenant_id]);
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
$agencyName = $settings['agency_name'] ?? 'Travel Agency';
$agencyLogo = '../uploads/logo/' . ($settings['logo'] ?? 'assets/images/logo.png');
$agencyContact = $settings['phone'] ?? '';

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
        families f ON b.family_id = f.family_id AND f.tenant_id = ?
    WHERE 
        b.booking_id IN ({$placeholders}) AND b.tenant_id = ?
";

$stmt = $pdo->prepare($sql);

// Parameters must match placeholder order: [tenant_id, pilgrimIds..., tenant_id]
$params = array_merge([$tenant_id], $pilgrimIds, [$tenant_id]);

$stmt->execute($params);

$pilgrims = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if we have pilgrim data
if (empty($pilgrims)) {
    die('No pilgrim data found');
}


// Create PDF using mPDF
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 10,
    'margin_right' => 10,
    'margin_top' => 10,
    'margin_bottom' => 10
]);

// Set document title
$mpdf->SetTitle('Umrah Pilgrim ID Cards');

// Start HTML content
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Umrah Pilgrim ID Cards</title>
    <style>
        @page {
            margin: 0;
            padding: 0;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }
        .page {
            width: 210mm;
            height: 297mm;
            padding: 2.5mm;
            box-sizing: border-box;
        }
        .id-cards-container {
            width: 100%;
        }
        .id-cards-container::after {
            content: "";
            clear: both;
            display: table;
        }
        .id-card {
            float: left;
            position: relative;
            width: 58mm;
            height: 86mm;
            border: 1px solid ' . $borderColor . ';
            background-repeat: no-repeat;
            background-position: bottom;
            background-size: 90mm;
            opacity: 0.05;
            border-radius: 8px;
            overflow: hidden;
            background-color: rgba(255, 255, 255, 0);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 5mm;
            margin-left: 8mm;
            box-sizing: border-box;
        }
        .id-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: inherit;
            background-position: bottom;
            background-repeat: no-repeat;
            background-size: 120mm;
            opacity: 0.05;
            z-index: 1;
        }
        .id-card:nth-child(3n+1) {
            margin-left: 2mm;  /* First card in each row gets smaller left margin */
        }
        .id-card:nth-child(3n) {
            margin-right: 2mm;  /* Last card in each row gets right margin */
        }
        .page-break {
            clear: both;
            page-break-after: always;
        }
        .id-card-content {
            position: relative;
            z-index: 2;
            background: transparent;
        }
        .id-card-header {
            background-color: ' . $borderColor . ';
            color: white;
            padding: 2mm;
            text-align: center;
            font-weight: bold;
            font-size: 8pt;
            position: relative;
            z-index: 2;
        }
        .id-card-header img {
            max-height: 10px;
            margin-right: 5px;
            vertical-align: middle;
        }
        .id-card-body-table {
            width: 100%;
            padding: 2mm;
            border-collapse: collapse;
        }
        .id-card-body-table td {
            padding: 1mm;
            vertical-align: top;
        }
        .id-card-photo {
            width: 25mm;
            height: 25mm;
            border-radius: 2px;
            overflow: hidden;
            position: relative;
            margin: 2mm;
            background-color: white;
            z-index: 2;
            float: left;
        }
        .id-card-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .id-card-logo-container {
            display: block;
            margin: 2mm;
            z-index: 2;
            float: left;
        }
        .id-card-logo {
            width: 25mm;
            height: 25mm;
            border-radius: 2px;
            overflow: hidden;
            background-color: white;
        }
        .id-card-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        // Ensure the container clears the floated elements
        .id-card-content::after {
            content: "";
            display: table;
            clear: both;
        }

        .id-card-details {
            text-align: center;
            font-size: 6pt;
            padding: 1mm 2mm;
            position: relative;
            z-index: 2;
            background-color: rgba(255, 255, 255, 0.95);
        }
        .id-card-details .id-card-field {
            margin-bottom: 0.8mm;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 0 2mm;
        }
        .id-card-details .id-card-field strong, .id-card-details .id-card-field span {
            display: inline;
        }
        .id-card-details .id-card-field strong {
            color: black;
            width: 40%;
        }
        .id-card-details .id-card-field span {
            width: 58%;
            text-align: right;
        }
    
        .agency-contact {
            text-align: center;
            margin-top: 1mm;
            padding: 0 2mm;
            font-size: 6pt;
            font-weight: bold;
            line-height: 1.3;
            color: black;
            position: relative;
            z-index: 2;

        }
        .agency-contact div {
            margin-bottom: 0.5mm;
        }
        .id-card-footer {

            border-top: 1px solid #ddd;
            padding: 1mm;
            padding-bottom: 0;
            text-align: center;
            font-size: 5pt;
            font-weight: bold;
            position: absolute;
            bottom: 0;
            width: 100%;
            z-index: 2;

        }
        .pilgrim-name {
            font-size: 8pt;
            font-weight: bold;
            text-align: center;
            margin: 1mm 0;
            color: ' . $borderColor . ';
            padding: 0 2mm;
        }
        .passport-number {
            font-size: 7pt;
            text-align: center;
            margin: 1mm 0;
            font-family: monospace;
            letter-spacing: 0.5px;
            color: #444;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 0 2mm;
        }
        .agency-info {
            font-size: 8pt;
            text-align: center;
            margin-top: 3px;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.15;
            pointer-events: none;
            z-index: 0;
        }
        @media print {
            body {
                background: none;
            }
            .page {
                page-break-after: always;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="id-cards-container">
';

// Generate ID cards
foreach ($pilgrims as $index => $pilgrim) {
    // Get pilgrim photo if available, otherwise use placeholder
    $photoPath = '../assets/images/user/avatar-2.jpg'; // Default placeholder
    
    // Check if a photo was uploaded for this pilgrim
    $pilgrimId = $pilgrim['booking_id'];
    $photoKey = 'photo_' . $pilgrimId;
    
    if (isset($_FILES[$photoKey]) && $_FILES[$photoKey]['error'] === UPLOAD_ERR_OK) {
        $tempFile = $_FILES[$photoKey]['tmp_name'];
        
        // Check if it's a valid image
        $imageInfo = getimagesize($tempFile);
        if ($imageInfo !== false) {
            // Create a unique filename
            $photoFilename = 'pilgrim_' . $pilgrimId . '_' . time() . '.jpg';
            $uploadDir = '../uploads/pilgrim_photos/';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $targetPath = $uploadDir . $photoFilename;
            
            // Process the image - resize and crop to make it square and rounded
            try {
                // Load the image based on its type
                switch ($imageInfo[2]) {
                    case IMAGETYPE_JPEG:
                        $image = imagecreatefromjpeg($tempFile);
                        break;
                    case IMAGETYPE_PNG:
                        $image = imagecreatefrompng($tempFile);
                        break;
                    case IMAGETYPE_GIF:
                        $image = imagecreatefromgif($tempFile);
                        break;
                    default:
                        throw new Exception('Unsupported image type');
                }

                // Get dimensions
                $width = imagesx($image);
                $height = imagesy($image);

                // Make it square by cropping to the smallest dimension
                $size = min($width, $height);
                $x = ($width - $size) / 2;
                $y = ($height - $size) / 2;

                // Create a square image
                $squareImage = imagecreatetruecolor(100, 100);

                // Preserve transparency for PNG
                if ($imageInfo[2] === IMAGETYPE_PNG) {
                    imagealphablending($squareImage, false);
                    imagesavealpha($squareImage, true);
                    $transparent = imagecolorallocatealpha($squareImage, 255, 255, 255, 127);
                    imagefilledrectangle($squareImage, 0, 0, 100, 100, $transparent);
                } else {
                    // For non-PNG images, fill with white background
                    $white = imagecolorallocate($squareImage, 255, 255, 255);
                    imagefill($squareImage, 0, 0, $white);
                }

                // Copy and resize the image
                imagecopyresampled($squareImage, $image, 0, 0, $x, $y, 100, 100, $size, $size);

                // Create a mask for rounded corners.
                $mask = imagecreatetruecolor(100, 100);
                $magenta = imagecolorallocate($mask, 255, 0, 255); // A color that is unlikely to be in the image
                $black = imagecolorallocate($mask, 0, 0, 0);
                imagefill($mask, 0, 0, $magenta);
                imagefilledellipse($mask, 50, 50, 100, 100, $black);
                imagecolortransparent($mask, $black); // Make the circle transparent

                // Apply the mask to the original image
                imagecopymerge($squareImage, $mask, 0, 0, 0, 0, 100, 100, 100);

                // Make the magenta color transparent
                imagecolortransparent($squareImage, $magenta);

                // Create a new true color image with a transparent background.
                $rounded_image = imagecreatetruecolor(100, 100);
                imagesavealpha($rounded_image, true);
                $trans_background = imagecolorallocatealpha($rounded_image, 0, 0, 0, 127);
                imagefill($rounded_image, 0, 0, $trans_background);
                imagecopy($rounded_image, $squareImage, 0, 0, 0, 0, 100, 100);


                // Save the processed image as PNG to preserve transparency
                imagepng($rounded_image, $targetPath);

                // Free up memory
                imagedestroy($rounded_image);
                imagedestroy($image);
                imagedestroy($squareImage);
                imagedestroy($mask);

                // Use the processed image
                $photoPath = $targetPath;
            } catch (Exception $e) {
                // Log the error but continue with default image
                error_log('Error processing pilgrim photo: ' . $e->getMessage());
            }
        }
    }
    
    // Generate QR code content
    $qrContent = "Name: {$pilgrim['name']}\n";
    $qrContent .= "Passport: {$pilgrim['passport_number']}\n";
    $qrContent .= "Family: {$pilgrim['head_of_family']}\n";
    $qrContent .= "Package: {$pilgrim['package_type']}\n";
    $qrContent .= "Valid until: {$validUntil}\n";
    
    // Add guide contact information to QR code if available
    if (!empty($guideMakkahName) && !empty($guideMakkahPhone)) {
        $qrContent .= "Guide Makkah: {$guideMakkahName} - {$guideMakkahPhone}\n";
    }
    
    // Add emergency contact information to QR code if available
    if (!empty($guideMadinaPhone)) {
        $qrContent .= "Guide Madina: {$guideMadinaName} - {$guideMadinaPhone}\n";
    }
    
    // Generate QR code using a data URI
    try {
        // Create writer - simplified version without logo and label
        $writer = new \Endroid\QrCode\Writer\PngWriter();
        
        // Create QR code with appropriate size
        $qrCode = \Endroid\QrCode\QrCode::create($qrContent)
            ->setSize(75)  // Set to 150px for better readability
            ->setMargin(2); // Set to 2px margin
        
        // Write QR code without logo or label
        $result = $writer->write($qrCode);
        
        // Get data URI
        $qrDataUri = $result->getDataUri();
    } catch (\Exception $e) {
        // If QR code generation fails, use a placeholder image
        $qrDataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }
    
    // Format dates
    $formattedDob = date('d M Y', strtotime($pilgrim['dob']));
    $formattedFlightDate = !empty($pilgrim['flight_date']) ? date('d M Y', strtotime($pilgrim['flight_date'])) : 'N/A';
    $formattedReturnDate = !empty($pilgrim['return_date']) ? date('d M Y', strtotime($pilgrim['return_date'])) : 'N/A';
    
    // Add a class to the second card in a row to handle margins
    $cardClass = 'id-card';
    if (($index + 1) % 2 == 0) {
        $cardClass .= ' id-card-last';
    }

    // Add ID card to HTML
    $html .= '
            <div class="' . $cardClass . '">
                <div class="id-card-content">
                    <div class="id-card-header">
                        ' . htmlspecialchars($idCardTitle) . '
                    </div>
                    <div class="id-card-photo">
                        <img src="' . $photoPath . '" alt="Pilgrim Photo">
                    </div>
                    <div class="id-card-logo-container">
                        <div class="id-card-logo">
                            <img src="' . $agencyLogo . '" alt="Agency Logo">
                        </div>
                    </div>
                    <div class="pilgrim-name">' . htmlspecialchars($pilgrim['name']) . '</div>
                    <div class="passport-number">' . htmlspecialchars($pilgrim['passport_number']) . ' | ' . htmlspecialchars($groupName) . '</div>
                    <div class="id-card-details">
                        <div class="id-card-field">
                            <strong>Family:</strong> <span>' . htmlspecialchars($pilgrim['head_of_family']) . '</span> -
                            <strong>Package:</strong> <span>' . htmlspecialchars($pilgrim['package_type']) . '</span>
                        </div>
                        
                        <div class="id-card-field">
                            <strong>Duration:</strong> <span>' . htmlspecialchars($pilgrim['duration']) . '</span> -
                            <strong>Room:</strong> <span>' . htmlspecialchars($pilgrim['room_type']) . '</span> 
                        </div>
                        
                        <div class="id-card-field">
                            <strong>Guide Makkah:</strong> <span>' . htmlspecialchars($guideMakkahName) . '</span> -
                            <strong>Phone:</strong> <span>' . htmlspecialchars($guideMakkahPhone) . '</span>
                        </div>
                       
                        <div class="id-card-field">
                            <strong>Guide Madina:</strong> <span>' . htmlspecialchars($guideMadinaName) . '</span> -
                            <strong>Phone:</strong> <span>' . htmlspecialchars($guideMadinaPhone) . '</span>
                        </div>
                    </div>
                    <div class="agency-contact">
                        <div>Phone: ' . htmlspecialchars($settings['phone'] ?? '') . '</div>
                        <div>Email: ' . htmlspecialchars($settings['email'] ?? '') . '</div>
                        <div>' . htmlspecialchars($settings['address'] ?? '') . '</div>
                    </div>
                    <div class="id-card-footer">
                        <div class="agency-info">
                            Valid: ' . date('d M Y', strtotime($validFrom)) . ' to ' . date('d M Y', strtotime($validUntil)) . ' <br>
                            Website: www.almoqadas.com
                        </div>
                    </div>
                </div>
            </div>
    ';
    
    // Add page break after every 9 cards (3x3 grid)
    if (($index + 1) % 9 === 0 && ($index + 1) < count($pilgrims)) {
        $html .= '
        </div>
        <div class="page-break"></div>
        <div class="id-cards-container">
        ';
    }
}

// Close HTML
$html .= '
        </div>
    </div>
</body>
</html>
';

// Write HTML to PDF
$mpdf->WriteHTML($html);

// Output PDF
$mpdf->Output('umrah_pilgrim_id_cards.pdf', \Mpdf\Output\Destination::INLINE);
?> 