<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();


// Database connection
require_once('../includes/db.php');
require_once('../includes/conn.php');

// Check if booking_id is provided
if (!isset($_GET['booking_id']) || empty($_GET['booking_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
    exit();
}

$bookingId = intval($_GET['booking_id']);

try {
    // Prepare the SQL query
    $sql = "SELECT 
                b.*, 
                s.name as supplier_name, 
                c.name as client_name, 
                m.name as account_name 
            FROM 
                umrah_bookings b
            LEFT JOIN 
                suppliers s ON b.supplier = s.id
            LEFT JOIN 
                clients c ON b.sold_to = c.id
            LEFT JOIN 
                main_account m ON b.paid_to = m.id
            WHERE 
                b.booking_id = ? AND b.tenant_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$bookingId, $tenant_id]);
    
    if ($stmt->rowCount() > 0) {
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'member' => $member]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Member not found']);
    }
} catch (PDOException $e) {
    // Log the error
    error_log("Database Error in get_umrah_member.php: " . $e->getMessage());
    
    // Return error message
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 