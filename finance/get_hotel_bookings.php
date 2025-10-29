<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once('../includes/db.php');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No ID provided']);
    exit;
}

$booking_id = intval($_GET['id']);

try {
    // Prepare and execute the query with all necessary joins
    $stmt = $pdo->prepare("
        SELECT 
            hb.*,
            s.name as supplier_name,
            c.name as client_name,
            ma.name as paid_to_name
        FROM hotel_bookings hb
        LEFT JOIN suppliers s ON hb.supplier_id = s.id
        LEFT JOIN clients c ON hb.sold_to = c.id
        LEFT JOIN main_account ma ON hb.paid_to = ma.id
        WHERE hb.id = ? AND hb.tenant_id = ?
    ");
    
    $stmt->execute([$booking_id, $tenant_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($bookings)) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
    } else {
        echo json_encode(['success' => true, 'bookings' => $bookings]);
    }
    
} catch (PDOException $e) {
    error_log("Error in get_hotel_bookings.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?> 