<?php
// Include security and database connections
require_once '../security.php';
require_once '../../includes/db.php';
require_once '../../includes/conn.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];

header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$new_flight_date = isset($_POST['new_flight_date']) ? $_POST['new_flight_date'] : '';
$new_return_date = isset($_POST['new_return_date']) ? $_POST['new_return_date'] : '';
$new_duration = isset($_POST['new_duration']) ? $_POST['new_duration'] : '';
$new_price = isset($_POST['new_price']) ? (float)$_POST['new_price'] : 0;
$change_reason = isset($_POST['change_reason']) ? trim($_POST['change_reason']) : '';
$additional_remarks = isset($_POST['additional_remarks']) ? trim($_POST['additional_remarks']) : '';

// Validate required fields
if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
    exit;
}

if (empty($new_flight_date) || empty($new_return_date) || empty($new_duration)) {
    echo json_encode(['success' => false, 'message' => 'New flight date, return date, and duration are required']);
    exit;
}

if (empty($change_reason)) {
    echo json_encode(['success' => false, 'message' => 'Reason for change is required']);
    exit;
}

// Validate dates
$flight_date_obj = DateTime::createFromFormat('Y-m-d', $new_flight_date);
$return_date_obj = DateTime::createFromFormat('Y-m-d', $new_return_date);

if (!$flight_date_obj || !$return_date_obj) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

if ($return_date_obj <= $flight_date_obj) {
    echo json_encode(['success' => false, 'message' => 'Return date must be after flight date']);
    exit;
}

try {
    // Get current booking details
    $stmt = $conn->prepare("
        SELECT ub.*, f.family_id, f.head_of_family, ubs.supplier_id as supplier,
               s.name as supplier_name, c.name as client_name, ma.name as main_account_name
        FROM umrah_bookings ub
        LEFT JOIN families f ON ub.family_id = f.family_id
        LEFT JOIN umrah_booking_services ubs ON ub.booking_id = ubs.booking_id AND ubs.service_type IN ('all', 'ticket')
        LEFT JOIN suppliers s ON ubs.supplier_id = s.id
        LEFT JOIN clients c ON ub.sold_to = c.id
        LEFT JOIN main_account ma ON ub.paid_to = ma.id
        WHERE ub.booking_id = ? AND ub.tenant_id = ?
    ");
    $stmt->bind_param("ii", $booking_id, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }

    $booking = $result->fetch_assoc();

    // Check if there's already a pending date change request for this booking
    $stmt = $conn->prepare("
        SELECT id FROM date_change_umrah
        WHERE umrah_booking_id = ? AND status = 'Pending' AND tenant_id = ?
    ");
    $stmt->bind_param("ii", $booking_id, $tenant_id);
    $stmt->execute();
    $pending_result = $stmt->get_result();

    if ($pending_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'A pending date change request already exists for this booking']);
        exit;
    }

    // Calculate price difference if new price is provided
    $price_difference = 0;
    if ($new_price > 0) {
        $price_difference = $new_price - $booking['sold_price'];
    }

    // Insert date change request
    $stmt = $conn->prepare("
        INSERT INTO date_change_umrah (
            umrah_booking_id, family_id, supplier, sold_to, paid_to,
            passenger_name, old_flight_date, new_flight_date,
            old_return_date, new_return_date, old_duration, new_duration,
            old_price, new_price, price_difference, currency,
            remarks, created_by, tenant_id
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $remarks = $change_reason;
    if (!empty($additional_remarks)) {
        $remarks .= "\n\nAdditional Remarks: " . $additional_remarks;
    }

    $created_by = $_SESSION['user_id'];
    $currency = $booking['currency'] ?: 'USD';

    $stmt->bind_param(
        "iiiiissssssssddssii",
        $booking_id,
        $booking['family_id'],
        $booking['supplier'],
        $booking['sold_to'],
        $booking['paid_to'],
        $booking['name'],
        $booking['flight_date'],
        $new_flight_date,
        $booking['return_date'],
        $new_return_date,
        $booking['duration'],
        $new_duration,
        $booking['sold_price'],
        $new_price,
        $price_difference,
        $currency,
        $remarks,
        $created_by,
        $tenant_id
    );

    if ($stmt->execute()) {
        $request_id = $conn->insert_id;

        // Log the action
        error_log("Date change request submitted - ID: $request_id, Booking: $booking_id, User: {$_SESSION['user_id']}");

        echo json_encode([
            'success' => true,
            'message' => 'Date change request submitted successfully. It will be reviewed by an administrator.',
            'request_id' => $request_id
        ]);
    } else {
        throw new Exception('Failed to insert date change request');
    }

} catch (Exception $e) {
    error_log("Date change request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing the request']);
}
?>