<?php
// Include necessary files
require_once('../includes/db.php');
require_once('../includes/conn.php');
require_once('security.php');
require_once('../vendor/autoload.php');
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

// Set header for HTML response
header('Content-Type: text/html; charset=UTF-8');

// Check if refund ID is provided
if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Refund ID is required']);
    exit;
}

$refundId = intval($_GET['id']);

try {
    // Get refund details with related information
    $query = "
        SELECT r.*, um.name, um.flight_date, um.return_date, um.room_type, um.duration,
               f.package_type, um.currency as booking_currency,
               u.name as processed_by_name, m.name as account_name,
               s.name as supplier_name, c.name as client_name
        FROM umrah_refunds r
        LEFT JOIN umrah_bookings um ON r.booking_id = um.booking_id
        LEFT JOIN families f ON um.family_id = f.family_id
        LEFT JOIN users u ON r.processed_by = u.id
        LEFT JOIN main_account m ON um.paid_to = m.id
        LEFT JOIN suppliers s ON um.supplier = s.id
        LEFT JOIN clients c ON um.sold_to = c.id
        WHERE r.id = ? AND r.tenant_id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$refundId, $tenant_id]);
    $refund = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$refund) {
        throw new Exception('Refund not found');
    }

    // Get settings for company info
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC) ?: ['agency_name' => 'Default Name'];
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
    $settings = ['agency_name' => 'Default Name'];
}

    // Include the template to output the HTML directly
    require_once('templates/umrah_refund_agreement_template.php');

} catch (Exception $e) {
    error_log("Error generating refund agreement: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error generating refund agreement: ' . $e->getMessage()
    ]);
} 