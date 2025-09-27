<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once('../includes/db.php');

if (!isset($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Booking ID is required'
    ]);
    exit;
}

$booking_id = intval($_GET['id']);

try {
    // Prepare and execute the query with all necessary joins
    $sql = "SELECT 
        tb.*,
        s.name as supplier_name,
        c.name as client_name,
        ma.name as paid_to_name
        FROM ticket_reservations tb
        LEFT JOIN suppliers s ON tb.supplier = s.id
        LEFT JOIN clients c ON tb.sold_to = c.id
        LEFT JOIN main_account ma ON tb.paid_to = ma.id
        WHERE tb.id = :id AND tb.tenant_id = :tenant_id";
        
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $booking_id, 'tenant_id' => $tenant_id]);
    
    // Fetch the booking
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($booking) {
        // Return success response with booking data
        echo json_encode([
            'success' => true,
            'booking' => $booking
        ]);
    } else {
        // Return error if booking not found
        echo json_encode([
            'success' => false,
            'message' => 'Reservation not found'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error in get_ticket_reservation.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching reservation'
    ]);
}
?> 