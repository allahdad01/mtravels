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
        hb.first_name,
        hb.last_name,
        hb.title,
        hb.order_id,
        hb.check_in_date,
        hb.check_out_date,
        hb.issue_date,
        hb.contact_no,
        hb.exchange_rate,
        hb.sold_amount,
        hb.currency,
        
        
        s.name as supplier_name,
        c.name as client_name,
        ma.name as paid_to_name
        FROM hotel_bookings hb
        LEFT JOIN suppliers s ON hb.supplier_id = s.id
        LEFT JOIN main_account_transactions t ON hb.id = t.reference_id
        LEFT JOIN clients c ON hb.sold_to = c.id
        LEFT JOIN main_account ma ON hb.paid_to = ma.id
        WHERE hb.id = :id AND hb.tenant_id = :tenant_id";
        
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $booking_id, 'tenant_id' => $tenant_id]);
    
    // Fetch the booking
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($bookings) {
        // Return success response with booking data
        echo json_encode([
            'bookings' => $bookings
        ]);
    } else {
        // Return error if booking not found
        echo json_encode([
            'success' => false,
            'message' => 'Booking not found'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error in get_hotel_bookings_tran.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching booking'
    ]);
}
?> 