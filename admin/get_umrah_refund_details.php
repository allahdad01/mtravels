<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

// Database connection
require_once('../includes/db.php');
require_once('../includes/conn.php');

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'refund' => null
];

// Check if refund ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $response['message'] = 'Invalid refund ID';
    echo json_encode($response);
    exit;
}

$refundId = intval($_GET['id']);

try {
    // First check if the refund exists
    $checkQuery = "SELECT id FROM umrah_refunds WHERE id = ? AND tenant_id = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$refundId, $tenant_id]);
    
    if (!$checkStmt->fetch()) {
        $response['message'] = 'Refund not found';
        echo json_encode($response);
        exit;
    }

    // Fetch refund details with related information
    $query = "
        SELECT 
            r.*,
            um.name,
            um.flight_date,
            um.return_date,
            um.room_type,
            um.duration,
            f.package_type,
            um.currency as booking_currency,
            um.supplier,
            COALESCE(s.name, '') as supplier_name,
            COALESCE(c.name, '') as client_name,
            COALESCE(c.client_type, '') as client_type,
            COALESCE(u.name, '') as processed_by_name
        FROM umrah_refunds r
        LEFT JOIN umrah_bookings um ON r.booking_id = um.booking_id
        LEFT JOIN families f ON um.family_id = f.family_id
        LEFT JOIN suppliers s ON um.supplier = s.id
        LEFT JOIN clients c ON um.sold_to = c.id
        LEFT JOIN users u ON r.processed_by = u.id
        WHERE r.id = ? AND r.tenant_id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$refundId, $tenant_id]);
    $refund = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($refund) {

        // Format dates
        if (!empty($refund['flight_date'])) {
            $refund['flight_date'] = date('Y-m-d', strtotime($refund['flight_date']));
        }
        if (!empty($refund['return_date'])) {
            $refund['return_date'] = date('Y-m-d', strtotime($refund['return_date']));
        }
        if (!empty($refund['created_at'])) {
            $refund['created_at'] = date('Y-m-d H:i:s', strtotime($refund['created_at']));
        }
        if (!empty($refund['processed_at'])) {
            $refund['processed_at'] = date('Y-m-d H:i:s', strtotime($refund['processed_at']));
        }

        $response['success'] = true;
        $response['refund'] = $refund;
    } else {
        $response['message'] = 'Failed to fetch refund details';
    }
} catch (PDOException $e) {
    error_log("Database Error in get_umrah_refund_details.php: " . $e->getMessage());
    $response['message'] = 'Database error occurred: ' . $e->getMessage();
} catch (Exception $e) {
    error_log("General Error in get_umrah_refund_details.php: " . $e->getMessage());
    $response['message'] = 'An error occurred while processing your request';
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response); 