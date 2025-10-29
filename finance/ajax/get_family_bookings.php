<?php
require_once '../../includes/db.php';
require_once '../../includes/conn.php';
require_once '../security.php';

// Enforce authentication
enforce_auth();

// Get family ID from request
$family_id = isset($_GET['family_id']) ? intval($_GET['family_id']) : 0;

if (!$family_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid family ID']);
    exit;
}

try {
    // Get all bookings for this family
    $sql = "SELECT booking_id, name, passport_number FROM umrah_bookings WHERE family_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $family_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    
    echo json_encode(['success' => true, 'bookings' => $bookings]);
} catch (Exception $e) {
    error_log("Error fetching family bookings: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch family bookings']);
} 