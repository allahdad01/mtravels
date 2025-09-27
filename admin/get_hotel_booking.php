<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once('../includes/db.php');

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    try {
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
        
        $stmt->execute([$id, $tenant_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking) {
            echo json_encode($booking);
        } else {
            echo json_encode(['error' => 'Booking not found']);
        }
    } catch (PDOException $e) {
        error_log("Error in get_hotel_booking.php: " . $e->getMessage());
        echo json_encode(['error' => 'Database error']);
    }
} else {
    echo json_encode(['error' => 'No ID provided']);
}
?> 