<?php
// Include security and database connections
require_once '../security.php';
require_once '../../includes/db.php';
require_once '../../includes/conn.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];

header('Content-Type: application/json');

// Get request ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit;
}

try {
    // Get date change request details
    $stmt = $conn->prepare("
        SELECT dc.*, f.head_of_family, f.contact, f.address,
               s.name as supplier_name, c.name as client_name, ma.name as main_account_name,
               u1.name as created_by_name, u2.name as approved_by_name, u3.name as processed_by_name
        FROM date_change_umrah dc
        LEFT JOIN families f ON dc.family_id = f.family_id
        LEFT JOIN suppliers s ON dc.supplier = s.id
        LEFT JOIN clients c ON dc.sold_to = c.id
        LEFT JOIN main_account ma ON dc.paid_to = ma.id
        LEFT JOIN users u1 ON dc.created_by = u1.id
        LEFT JOIN users u2 ON dc.approved_by = u2.id
        LEFT JOIN users u3 ON dc.processed_by = u3.id
        WHERE dc.id = ? AND dc.tenant_id = ?
    ");
    $stmt->bind_param("ii", $id, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Date change request not found']);
        exit;
    }

    $request = $result->fetch_assoc();

    // Generate HTML content
    $html = '
        <div class="row">
            <!-- Request Information -->
            <div class="col-md-12 mb-4">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="feather icon-info mr-2"></i>Request Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Request ID:</strong> #' . $request['id'] . '</p>
                                <p><strong>Status:</strong> <span class="badge badge-' . getStatusBadgeClass($request['status']) . '">' . $request['status'] . '</span></p>
                                <p><strong>Requested On:</strong> ' . date('M d, Y H:i', strtotime($request['created_at'])) . '</p>
                                <p><strong>Requested By:</strong> ' . htmlspecialchars($request['created_by_name']) . '</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Passenger:</strong> ' . htmlspecialchars($request['passenger_name']) . '</p>
                                <p><strong>Family:</strong> ' . htmlspecialchars($request['head_of_family']) . '</p>
                                <p><strong>Booking ID:</strong> #' . $request['umrah_booking_id'] . '</p>
                                <p><strong>Contact:</strong> ' . htmlspecialchars($request['contact']) . '</p>
                            </div>
                        </div>
                        ' . (!empty($request['remarks']) ? '<div class="mt-3"><strong>Remarks:</strong><br><div class="alert alert-info">' . nl2br(htmlspecialchars($request['remarks'])) . '</div></div>' : '') . '
                    </div>
                </div>
            </div>

            <!-- Date Comparison -->
            <div class="col-md-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="feather icon-calendar mr-2"></i>Date Changes</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted">Current Dates</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Flight Date:</strong></td>
                                        <td>' . ($request['old_flight_date'] ?: 'Not set') . '</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Return Date:</strong></td>
                                        <td>' . ($request['old_return_date'] ?: 'Not set') . '</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Duration:</strong></td>
                                        <td>' . ($request['old_duration'] ?: 'Not set') . '</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-success">Requested Dates</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Flight Date:</strong></td>
                                        <td class="text-success">' . $request['new_flight_date'] . '</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Return Date:</strong></td>
                                        <td class="text-success">' . $request['new_return_date'] . '</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Duration:</strong></td>
                                        <td class="text-success">' . $request['new_duration'] . '</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Information -->
            <div class="col-md-12 mb-4">
                <div class="card border-success">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="feather icon-dollar-sign mr-2"></i>Financial Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Current Price:</strong></td>
                                        <td>' . number_format($request['old_price'], 2) . ' ' . $request['currency'] . '</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Requested Price:</strong></td>
                                        <td>' . ($request['new_price'] > 0 ? number_format($request['new_price'], 2) . ' ' . $request['currency'] : 'No change') . '</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Price Difference:</strong></td>
                                        <td class="' . ($request['price_difference'] >= 0 ? 'text-danger' : 'text-success') . '">
                                            ' . ($request['price_difference'] != 0 ? ($request['price_difference'] > 0 ? '+' : '') . number_format($request['price_difference'], 2) . ' ' . $request['currency'] : 'No change') . '
                                        </td>
                                    </tr>
                                    ' . ($request['supplier_penalty'] > 0 ? '<tr><td><strong>Supplier Penalty:</strong></td><td class="text-warning">' . number_format($request['supplier_penalty'], 2) . ' ' . $request['currency'] . '</td></tr>' : '') . '
                                    ' . ($request['service_penalty'] > 0 ? '<tr><td><strong>Service Penalty:</strong></td><td class="text-warning">' . number_format($request['service_penalty'], 2) . ' ' . $request['currency'] . '</td></tr>' : '') . '
                                    ' . ($request['total_penalty'] > 0 ? '<tr><td><strong>Total Penalty:</strong></td><td class="text-danger"><strong>' . number_format($request['total_penalty'], 2) . ' ' . $request['currency'] . '</strong></td></tr>' : '') . '
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Approval Information -->
            ' . generateApprovalSection($request) . '
        </div>
    ';

    // Generate action buttons based on status
    $action_buttons = generateActionButtons($request);

    echo json_encode([
        'success' => true,
        'html' => $html,
        'action_buttons' => $action_buttons
    ]);

} catch (Exception $e) {
    error_log("Get date change details error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to load request details']);
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Pending': return 'warning';
        case 'Approved': return 'info';
        case 'Rejected': return 'danger';
        case 'Completed': return 'success';
        default: return 'secondary';
    }
}

function generateApprovalSection($request) {
    if ($request['status'] === 'Pending') {
        return '';
    }

    $html = '
        <div class="col-md-12 mb-4">
            <div class="card border-secondary">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="feather icon-check-circle mr-2"></i>Approval Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
    ';

    if ($request['approved_by']) {
        $html .= '
            <div class="col-md-6">
                <p><strong>Approved By:</strong> ' . htmlspecialchars($request['approved_by_name']) . '</p>
                <p><strong>Approved On:</strong> ' . date('M d, Y H:i', strtotime($request['approved_at'])) . '</p>
            </div>
        ';
    }

    if ($request['processed_by']) {
        $html .= '
            <div class="col-md-6">
                <p><strong>Processed By:</strong> ' . htmlspecialchars($request['processed_by_name']) . '</p>
                <p><strong>Processed On:</strong> ' . date('M d, Y H:i', strtotime($request['processed_at'])) . '</p>
            </div>
        ';
    }

    $html .= '
                    </div>
                </div>
            </div>
        </div>
    ';

    return $html;
}

function generateActionButtons($request) {
    $buttons = '';

    if ($request['status'] === 'Pending') {
        $buttons .= '
            <button type="button" class="btn btn-success mr-2" onclick="approveDateChangeRequest(' . $request['id'] . ')">
                <i class="feather icon-check mr-2"></i>Approve
            </button>
            <button type="button" class="btn btn-danger mr-2" onclick="rejectDateChangeRequest(' . $request['id'] . ')">
                <i class="feather icon-x mr-2"></i>Reject
            </button>
        ';
    } elseif ($request['status'] === 'Approved') {
        $buttons .= '
            <button type="button" class="btn btn-info mr-2" onclick="processDateChangeRequest(' . $request['id'] . ')">
                <i class="feather icon-play mr-2"></i>Process Changes
            </button>
        ';
    }

    return $buttons;
}
?>