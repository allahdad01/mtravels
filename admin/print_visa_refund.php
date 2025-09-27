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
        SELECT r.*, v.applicant_name, v.passport_number, v.country, v.applied_date,
               m.name as account_name
        FROM visa_refunds r
        LEFT JOIN visa_applications v ON r.visa_id = v.id
        LEFT JOIN main_account m ON v.paid_to = m.id
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
    require_once('templates/visa_refund_agreement_template.php');

} catch (Exception $e) {
    error_log("Error generating refund agreement: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error generating refund agreement: ' . $e->getMessage()
    ]);
} 