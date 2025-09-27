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
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$supplier_penalty = isset($_POST['supplier_penalty']) ? (float)$_POST['supplier_penalty'] : 0;
$service_penalty = isset($_POST['service_penalty']) ? (float)$_POST['service_penalty'] : 0;
$penalty_remarks = isset($_POST['penalty_remarks']) ? trim($_POST['penalty_remarks']) : '';

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit;
}

try {
    // Get the request details
    $stmt = $conn->prepare("
        SELECT dc.*, ub.price as current_price
        FROM date_change_umrah dc
        LEFT JOIN umrah_bookings ub ON dc.umrah_booking_id = ub.booking_id
        WHERE dc.id = ? AND dc.tenant_id = ? AND dc.status = 'Pending'
    ");
    $stmt->bind_param("ii", $id, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Pending date change request not found']);
        exit;
    }

    $request = $result->fetch_assoc();

    // Use manually entered penalties
    $total_penalty = $supplier_penalty + $service_penalty;

    // Update remarks if penalty remarks were provided
    $updated_remarks = $request['remarks'];
    if (!empty($penalty_remarks)) {
        $updated_remarks .= "\n\nPenalty Details:\n";
        $updated_remarks .= "Supplier Penalty: $" . number_format($supplier_penalty, 2) . "\n";
        $updated_remarks .= "Service Penalty: $" . number_format($service_penalty, 2) . "\n";
        $updated_remarks .= "Total Penalty: $" . number_format($total_penalty, 2) . "\n";
        $updated_remarks .= "Penalty Remarks: " . $penalty_remarks;
    }

    // Update the request with approval and penalties
    $stmt = $conn->prepare("
        UPDATE date_change_umrah
        SET status = 'Approved',
            approved_by = ?,
            approved_at = NOW(),
            supplier_penalty = ?,
            service_penalty = ?,
            total_penalty = ?,
            remarks = ?
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->bind_param("ddddsii", $_SESSION['user_id'], $supplier_penalty, $service_penalty, $total_penalty, $updated_remarks, $id, $tenant_id);

    if ($stmt->execute()) {
        // Log the approval
        error_log("Date change request approved - ID: $id, Approved by: {$_SESSION['user_id']}");

        echo json_encode([
            'success' => true,
            'message' => 'Date change request approved successfully. Total penalty: $' . number_format($total_penalty, 2)
        ]);
    } else {
        throw new Exception('Failed to approve request');
    }

} catch (Exception $e) {
    error_log("Approve date change request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while approving the request']);
}
?>