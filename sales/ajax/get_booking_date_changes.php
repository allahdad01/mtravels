<?php
// Include security and database connections
require_once '../security.php';
require_once '../../includes/db.php';
require_once '../../includes/conn.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];

header('Content-Type: application/json');

// Get booking ID
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
    exit;
}

try {
    // Get date change history for this booking
    $stmt = $conn->prepare("
        SELECT * FROM date_change_umrah
        WHERE umrah_booking_id = ? AND tenant_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("ii", $booking_id, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }

    echo json_encode([
        'success' => true,
        'history' => $history
    ]);

} catch (Exception $e) {
    error_log("Get booking date changes error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to load date change history']);
}
?>