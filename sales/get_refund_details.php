<?php
// Include necessary files
require_once('../includes/db.php');
require_once('../includes/conn.php');
require_once('security.php');
$tenant_id = $_SESSION['tenant_id'];
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
               t.amount as transaction_amount, t.currency as transaction_currency,
               m.name as account_name, u.name as processed_by_name
        FROM visa_refunds r
        LEFT JOIN visa_applications v ON r.visa_id = v.id
        LEFT JOIN main_account_transactions t ON r.transaction_id = t.id
        LEFT JOIN main_account m ON t.main_account_id = m.id
        LEFT JOIN users u ON r.processed_by = u.id
        WHERE r.id = ? AND r.tenant_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $refundId, $tenant_id);
    $stmt->execute();
    
    // Get the result
    $result = $stmt->get_result();
    $refund = $result->fetch_assoc();
    
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
    error_log("Database Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}

// Close the prepared statement and result
if (isset($result)) {
    $result->close();
}
if (isset($stmt)) {
    $stmt->close();
}
?> 