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
    
    // Get tenant and branch info from session
    $tenantId = $_SESSION['tenant_id'] ?? null;
    $branchId = $_SESSION['branch_id'] ?? null;
    
    if (!$tenantId || !$branchId) {
        throw new Exception('Missing tenant or branch information');
    }
    
    // Use family_id from request first (passed from frontend during add member)
    $familyId = null;
    
    if ($familyIdFromRequest) {
        $familyId = intval($familyIdFromRequest);
    } elseif ($bookingId) {
        // Fallback: get family_id from booking
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
    
    // If family_id still not found, use booking_id as fallback
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
    
    // Clean up old photo if re-extracting for the same family
    if ($familyId && $tenantId && $branchId) {
        $sql = "SELECT photo_path FROM umrah_bookings WHERE family_id = ? AND tenant_id = ? AND branch_id = ? AND photo_path IS NOT NULL AND photo_path != '' LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$familyId, $tenantId, $branchId]);
        $old = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($old && $old['photo_path']) {
            $oldFile = $uploadBase . $old['photo_path'];
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }
    }
    
    if (!imagejpeg($resizedImage, $filepath, 85)) {
        throw new Exception('Failed to save image');
    }
    
    // Clean up
    imagedestroy($image);
    imagedestroy($croppedImage);
    imagedestroy($resizedImage);
    
    // Save to database if booking_id provided
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
