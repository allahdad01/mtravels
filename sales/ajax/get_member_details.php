<?php
require_once '../../includes/db.php';
require_once '../../includes/conn.php';
require_once '../security.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];

// Get booking ID from request
$bookingId = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit();
}

// Fetch member details
$sql = "SELECT ub.*, 
               c.name as client_name, 
               ma.name as main_account_name, 
               u.name as created_by
        FROM umrah_bookings ub
        LEFT JOIN clients c ON ub.sold_to = c.id
        LEFT JOIN main_account ma ON ub.paid_to = ma.id
        LEFT JOIN users u ON ub.created_by = u.id
        WHERE ub.booking_id = ? AND ub.tenant_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $bookingId, $tenant_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $member = $result->fetch_assoc();

    // Fetch services
    $servicesSql = "SELECT * FROM umrah_booking_services WHERE booking_id = ?";
    $servicesStmt = $conn->prepare($servicesSql);
    $servicesStmt->bind_param('i', $bookingId);
    $servicesStmt->execute();
    $servicesResult = $servicesStmt->get_result();
    $services = [];
    while ($service = $servicesResult->fetch_assoc()) {
        $services[] = $service;
    }
    $member['services'] = $services;

    // Format dates for display
    $member['entry_date'] = date('Y-m-d', strtotime($member['entry_date']));
    $member['dob'] = date('Y-m-d', strtotime($member['dob']));
    $member['flight_date'] = $member['flight_date'] ? date('Y-m-d', strtotime($member['flight_date'])) : '';
    $member['return_date'] = $member['return_date'] ? date('Y-m-d', strtotime($member['return_date'])) : '';
    $member['passport_expiry'] = date('Y-m-d', strtotime($member['passport_expiry']));

    // Add additional information
    $member['client_details'] = [
        'name' => $member['client_name'],
        'main_account' => $member['main_account_name'],
        'created_by' => $member['created_by']
    ];

    echo json_encode(['success' => true, 'member' => $member]);
} else {
    echo json_encode(['success' => false, 'message' => 'Member not found']);
}

$stmt->close();
$conn->close(); 