<?php
// Include necessary files
require_once('../../includes/db.php');
require_once('../../admin/security.php');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

// Set header for JSON response
header('Content-Type: application/json');


// Check if refund ID is provided
if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Refund ID is required']);
    exit;
}

$refundId = intval($_GET['id']);

try {
    // Get refund details with related information
    $query = "
        SELECT r.*, v.applicant_name, v.passport_number, v.country, v.currency as visa_currency,
               m.name as account_name
        FROM visa_refunds r
        LEFT JOIN visa_applications v ON r.visa_id = v.id
        LEFT JOIN main_account m ON v.paid_to = m.id
        LEFT JOIN users u ON r.processed_by = u.id
        WHERE r.id = ? AND r.tenant_id = ? AND r.branch_id = ?
    ";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $refundId, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    // Get the result
    $result = $stmt->fetchAll();
    $refund = count($result) > 0 ? $result[0] : null;
    
    if ($refund) {
        echo json_encode([
            'success' => true,
            'refund' => $refund
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Refund not found'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}

?> 