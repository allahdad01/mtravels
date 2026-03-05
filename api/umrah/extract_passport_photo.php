<?php
/**
 * Extract Passport Photo API
 * Extracts and crops photo from passport image or PDF for Umrah members
 * Supports:
 * - Binary image/PDF upload
 * - Canvas-based photo extraction from client
 * - Auto-crop and optimize photo
 */

require_once '../../includes/db.php';
require_once '../../admin/security.php';

enforce_auth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['image_data'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No image data provided']);
    exit;
}

$bookingId = $input['booking_id'] ?? null;
$imageData = $input['image_data']; // Base64 data URL
$cropData = $input['crop_data'] ?? null; // Crop coordinates

try {
    // Decode base64 image
    if (strpos($imageData, 'data:') === 0) {
        // Data URL format
        $parts = explode(',', $imageData);
        if (count($parts) !== 2) {
            throw new Exception('Invalid image data format');
        }
        $imageData = base64_decode($parts[1]);
    } else {
        // Raw base64
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
    
    // Apply crop if provided
    $croppedImage = $image;
    if ($cropData && isset($cropData['x'], $cropData['y'], $cropData['width'], $cropData['height'])) {
        $x = (int)$cropData['x'];
        $y = (int)$cropData['y'];
        $width = (int)$cropData['width'];
        $height = (int)$cropData['height'];
        
        // Validate crop coordinates
        if ($x >= 0 && $y >= 0 && $width > 0 && $height > 0 && 
            ($x + $width) <= $origWidth && ($y + $height) <= $origHeight) {
            
            $croppedImage = imagecrop($image, [
                'x' => $x,
                'y' => $y,
                'width' => $width,
                'height' => $height
            ]);
            
            if ($croppedImage === false) {
                throw new Exception('Failed to crop image');
            }
        }
    }
    
    // Optimize: resize if too large (max width 400px for passport photo)
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
    
    // Get tenant and branch info from session
    $tenantId = $_SESSION['tenant_id'] ?? null;
    $branchId = $_SESSION['branch_id'] ?? null;
    
    if (!$tenantId || !$branchId) {
        throw new Exception('Missing tenant or branch information');
    }
    
    // Get family_id from booking if available
    $familyId = null;
    if ($bookingId) {
        $sql = "SELECT f.family_id FROM umrah_bookings ub 
                LEFT JOIN families f ON ub.family_id = f.family_id
                WHERE ub.booking_id = ? AND ub.tenant_id = ? AND ub.branch_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$bookingId, $tenantId, $branchId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['family_id']) {
            $familyId = intval($result['family_id']);
        }
    }
    
    // If family_id not found, use booking_id as fallback folder
    if (!$familyId && $bookingId) {
        $familyId = intval($bookingId);
    }
    
    // Create upload directory structure: uploads/tenant_id/branch_id/umrah/family_id/
    $uploadBase = __DIR__ . '/../../uploads';
    $uploadDir = $uploadBase . '/' . $tenantId . '/' . $branchId . '/umrah/';
    if ($familyId) {
        $uploadDir .= $familyId . '/';
    }
    
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0755, true)) {
            throw new Exception('Could not create upload directory');
        }
    }
    
    // Generate secure filename
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    $filename = "passport_photo_{$timestamp}_{$random}.jpg";
    $filepath = $uploadDir . $filename;
    $relativePath = '/uploads/' . $tenantId . '/' . $branchId . '/umrah/' . ($familyId ? $familyId . '/' : '') . $filename;
    
    // Save image
    if (!imagejpeg($resizedImage, $filepath, 85)) {
        throw new Exception('Failed to save image');
    }
    
    // Clean up
    imagedestroy($image);
    imagedestroy($croppedImage);
    imagedestroy($resizedImage);
    
    // If booking_id provided, save to database
    if ($bookingId) {
        $tenantId = $_SESSION['tenant_id'] ?? null;
        $branchId = $_SESSION['branch_id'] ?? null;
        
        if ($tenantId && $branchId) {
            $sql = "UPDATE umrah_bookings SET photo_path = ?, photo_uploaded_at = NOW() WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$relativePath, $bookingId, $tenantId, $branchId]);
        }
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Photo extracted and saved successfully',
        'photo_path' => $relativePath,
        'filename' => $filename,
        'width' => $newWidth,
        'height' => $newHeight
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
