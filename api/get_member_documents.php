<?php
/**
 * Get member documents (photo and passport)
 * Automatically fixes paths for compatibility
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../includes/db.php';

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

if (!$booking_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit;
}

// Get document paths
$stmt = $pdo->prepare("SELECT photo_path, passport_path FROM umrah_bookings 
                      WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
$stmt->execute([$booking_id, $tenant_id, $branch_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    // Function to fix paths that may have been stored with incorrect format
    function fixPath($path) {
        if (!$path) return null;
        
        // Determine the base path from the current request
        $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $base_path = str_replace('/api/get_member_documents.php', '', $request_uri);
        
        // If path doesn't start with base_path, prepend it
        if (!empty($base_path) && strpos($path, $base_path) === false) {
            // Remove leading slash if it exists to avoid double slashes
            if (strpos($path, '/') === 0) {
                $path = substr($path, 1);
            }
            $path = $base_path . '/' . $path;
        }
        
        return $path;
    }
    
    $photo_path = $result['photo_path'] ? fixPath($result['photo_path']) : null;
    $passport_path = $result['passport_path'] ? fixPath($result['passport_path']) : null;
    
    echo json_encode([
        'success' => true,
        'photo_path' => $photo_path,
        'passport_path' => $passport_path
    ]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
}
?>
