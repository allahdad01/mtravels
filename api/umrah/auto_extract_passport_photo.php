<?php
/**
 * Automatic Afghan Passport Photo Extractor
 * Automatically detects and extracts photo from Afghan passport
 * Photo position: Left-bottom area of passport
 */

require_once '../../includes/db.php';
require_once '../../admin/security.php';

enforce_auth();
require_permission('umrah.member_edit');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['image_data'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No image data provided']);
    exit;
}

$bookingId = $input['booking_id'] ?? null;
$familyIdFromRequest = $input['family_id'] ?? null;  // Family ID passed from frontend
$imageData = $input['image_data'];

try {
    // Decode base64
    if (strpos($imageData, 'data:') === 0) {
        $parts = explode(',', $imageData);
        if (count($parts) !== 2) {
            throw new Exception('Invalid image data format');
        }
        $imageData = base64_decode($parts[1]);
    } else {
        $imageData = base64_decode($imageData);
    }
    
    if ($imageData === false) {
        throw new Exception('Failed to decode image data');
    }
    
    // Create image from data
    $image = imagecreatefromstring($imageData);
    if ($image === false) {
        throw new Exception('Invalid image data');
    }
    
    $origWidth = imagesx($image);
    $origHeight = imagesy($image);
    
    // Afghan Passport Photo Position Detection
    // Photo is typically in left area, positioned lower
    // Standard Afghan passport photo position:
    // - Left: ~8-10% from left edge
    // - Top: ~28-32% from top (moved down from 20%)
    // - Width: ~35-40% of total width
    // - Height: ~50-55% of total height
    
    // Auto-detect photo area based on passport dimensions
    $photoX = (int)($origWidth * 0.04);        // ~8% from left
    $photoY = (int)($origHeight * 0.60);       // ~62% from top (moved down more)
    $photoWidth = (int)($origWidth * 0.27);    // ~22% width
    $photoHeight = (int)($origHeight * 0.30);  // ~30% height
    
    // Validate boundaries
    if ($photoX < 0) $photoX = 0;
    if ($photoY < 0) $photoY = 0;
    if ($photoX + $photoWidth > $origWidth) {
        $photoWidth = $origWidth - $photoX;
    }
    if ($photoY + $photoHeight > $origHeight) {
        $photoHeight = $origHeight - $photoY;
    }
    
    // Ensure minimum size
    if ($photoWidth < 100 || $photoHeight < 150) {
        throw new Exception('Detected photo area too small. Try a higher resolution image.');
    }
    
    // Crop photo area
    $croppedImage = imagecrop($image, [
        'x' => $photoX,
        'y' => $photoY,
        'width' => $photoWidth,
        'height' => $photoHeight
    ]);
    
    if ($croppedImage === false) {
        throw new Exception('Failed to crop image');
    }
    
    // Optimize: Resize to standard photo width (400px max)
    $maxWidth = 400;
    $newWidth = min($maxWidth, imagesx($croppedImage));
    $newHeight = (int)(imagesy($croppedImage) * ($newWidth / imagesx($croppedImage)));
    
    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled(
        $resizedImage, $croppedImage,
        0, 0, 0, 0,
        $newWidth, $newHeight,
        imagesx($croppedImage), imagesy($croppedImage)
    );
    
    // Save to temp directory — moved to final location only when member is saved
    $uploadBase = __DIR__ . '/../../uploads';
    $tempDir = $uploadBase . '/temp';
    
    if (!is_dir($tempDir)) {
        if (!@mkdir($tempDir, 0755, true)) {
            throw new Exception('Could not create temp upload directory');
        }
    }
    
    // Generate secure filename
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    $filename = "passport_photo_{$timestamp}_{$random}.jpg";
    $filepath = $tempDir . '/' . $filename;
    $relativePath = '/uploads/temp/' . $filename;
    
    if (!imagejpeg($resizedImage, $filepath, 85)) {
        throw new Exception('Failed to save image');
    }
    
    // Clean up
    imagedestroy($image);
    imagedestroy($croppedImage);
    imagedestroy($resizedImage);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Photo extracted automatically from Afghan passport',
        'photo_path' => $relativePath,
        'filename' => $filename,
        'width' => $newWidth,
        'height' => $newHeight,
        'detected_area' => [
            'x' => $photoX,
            'y' => $photoY,
            'width' => $photoWidth,
            'height' => $photoHeight
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
