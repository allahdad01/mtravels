<?php
// Include necessary files
require_once('../../includes/db.php');
require_once('../../admin/security.php');
require_once('../../vendor/autoload.php');

// Enforce authentication
enforce_auth();

// Set header for HTML response
header('Content-Type: text/html; charset=UTF-8');

// Check if ticket ID is provided
if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Ticket ID is required']);
    exit;
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$ticketId = intval($_GET['id']);

try {
    // Get ticket details with related information
    $query = "
        SELECT rt.*, s.name as supplier_name, c.name as client_name
        FROM refunded_tickets rt
        LEFT JOIN suppliers s ON rt.supplier = s.id
        LEFT JOIN clients c ON rt.sold_to = c.id
        WHERE rt.id = ? AND rt.tenant_id = ? AND rt.branch_id = ?";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $ticketId, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Ticket not found']);
        exit;
    }

    // Get agency settings
    try {
        $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
        $settingStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $settingStmt->execute();
        $settings = $settingStmt->fetch(PDO::FETCH_ASSOC) ?: ['agency_name' => 'Default Name'];
    } catch (PDOException $e) {
        error_log("Settings Error: " . $e->getMessage());
        $settings = ['agency_name' => 'Default Name'];
    }

    // Include the template to output the HTML directly
    include 'ticket_refund_agreement_template.php';

} catch (PDOException $e) {
    error_log('Error generating refund agreement: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate refund agreement: ' . $e->getMessage()
    ]);
}
?>