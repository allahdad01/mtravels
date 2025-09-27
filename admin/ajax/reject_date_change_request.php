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
$rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit;
}

if (empty($rejection_reason)) {
    echo json_encode(['success' => false, 'message' => 'Rejection reason is required']);
    exit;
}

try {
    // Check if request exists and is pending
    $stmt = $conn->prepare("
        SELECT id FROM date_change_umrah
        WHERE id = ? AND tenant_id = ? AND status = 'Pending'
    ");
    $stmt->bind_param("ii", $id, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Pending date change request not found']);
        exit;
    }

    // Update the request with rejection
    $stmt = $conn->prepare("
        UPDATE date_change_umrah
        SET status = 'Rejected',
            remarks = CONCAT(remarks, '\n\nRejection Reason: ', ?),
            approved_by = ?,
            approved_at = NOW()
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->bind_param("siii", $rejection_reason, $_SESSION['user_id'], $id, $tenant_id);

    if ($stmt->execute()) {
        // Log the rejection
        error_log("Date change request rejected - ID: $id, Rejected by: {$_SESSION['user_id']}, Reason: $rejection_reason");

        echo json_encode([
            'success' => true,
            'message' => 'Date change request rejected successfully'
        ]);
    } else {
        throw new Exception('Failed to reject request');
    }

} catch (Exception $e) {
    error_log("Reject date change request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while rejecting the request']);
}
?>