<?php
/**
 * Delete member document (photo or passport)
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

$booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
$document_type = isset($_POST['document_type']) ? trim($_POST['document_type']) : '';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

if (!$booking_id || !in_array($document_type, ['photo', 'passport', 'visa'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Check booking exists
$stmt = $pdo->prepare("SELECT * FROM umrah_bookings WHERE booking_id = ? AND tenant_id = ? AND branch_id = ?");
$stmt->execute([$booking_id, $tenant_id, $branch_id]);

if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit;
}

// Get file path
$column_name = $document_type . '_path';
$stmt = $pdo->prepare("SELECT $column_name FROM umrah_bookings WHERE booking_id = ?");
$stmt->execute([$booking_id]);
$file_info = $stmt->fetch(PDO::FETCH_ASSOC);

if ($file_info && $file_info[$column_name]) {
    $file_path = __DIR__ . '/..' . $file_info[$column_name];
    
    // Delete physical file
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

// Update database
$uploaded_at_column = $document_type . '_uploaded_at';
$stmt = $pdo->prepare("UPDATE umrah_bookings 
                      SET $column_name = NULL, $uploaded_at_column = NULL 
                      WHERE booking_id = ?");

if ($stmt->execute([$booking_id])) {
    echo json_encode([
        'success' => true,
        'message' => ucfirst($document_type) . ' deleted successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete file']);
}
?>
