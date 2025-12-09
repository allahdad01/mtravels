<?php
// Include security and database connections
require_once '../../admin/security.php';
require_once '../../includes/db.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

header('Content-Type: application/json');

// Get booking ID
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
    exit;
}

try {
    // Get date change history for this booking
    $stmt = $pdo->prepare("
        SELECT * FROM date_change_umrah
        WHERE umrah_booking_id = ? AND tenant_id = ? AND branch_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->bindParam(1, $booking_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $history = $result;

    echo json_encode([
        'success' => true,
        'history' => $history
    ]);

} catch (PDOException $e) {
    error_log("Get booking date changes error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to load date change history']);
}
?>