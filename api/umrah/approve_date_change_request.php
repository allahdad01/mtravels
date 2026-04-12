<?php
// Include security and database connections
require_once '../../admin/security.php';
require_once '../../includes/db.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

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
    // Start transaction
    $pdo->beginTransaction();

    // Get the request details
    $stmt = $pdo->prepare("
        SELECT dc.*, ub.price as current_price
        FROM date_change_umrah dc
        LEFT JOIN umrah_bookings ub ON dc.umrah_booking_id = ub.booking_id AND ub.tenant_id = ? AND ub.branch_id = ?
        WHERE dc.id = ? AND dc.tenant_id = ? AND dc.branch_id = ? AND dc.status = 'Pending'
    ");
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $id, PDO::PARAM_INT);
    $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Pending date change request not found']);
        exit;
    }

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
    $stmt = $pdo->prepare("
        UPDATE date_change_umrah
        SET status = 'Approved',
            approved_by = ?,
            approved_at = NOW(),
            supplier_penalty = ?,
            service_penalty = ?,
            total_penalty = ?,
            remarks = ?
        WHERE id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmt->bindParam(1, $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindParam(2, $supplier_penalty, PDO::PARAM_STR);
    $stmt->bindParam(3, $service_penalty, PDO::PARAM_STR);
    $stmt->bindParam(4, $total_penalty, PDO::PARAM_STR);
    $stmt->bindParam(5, $updated_remarks, PDO::PARAM_STR);
    $stmt->bindParam(6, $id, PDO::PARAM_INT);
    $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(8, $branch_id, PDO::PARAM_INT);

    if (!$stmt->execute()) {
        throw new PDOException('Failed to approve request');
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Date change request approved successfully. Total penalty: $' . number_format($total_penalty, 2)
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'An error occurred while approving the request']);
}
?>