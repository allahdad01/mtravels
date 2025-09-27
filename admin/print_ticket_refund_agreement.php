<?php
// Include necessary files
require_once('../includes/db.php');
require_once('../includes/conn.php');
require_once('security.php');
require_once('../vendor/autoload.php');

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
$ticketId = intval($_GET['id']);

try {
    // Get ticket details with related information
    $query = "
        SELECT rt.*, s.name as supplier_name, c.name as client_name
        FROM refunded_tickets rt
        LEFT JOIN suppliers s ON rt.supplier = s.id
        LEFT JOIN clients c ON rt.sold_to = c.id
        WHERE rt.id = ? AND rt.tenant_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $ticketId, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Ticket not found']);
        exit;
    }
    
    $ticket = $result->fetch_assoc();
    
    // Get agency settings
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC) ?: ['agency_name' => 'Default Name'];
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
    $settings = ['agency_name' => 'Default Name'];
}
    
    // Include the template to output the HTML directly
    include 'templates/ticket_refund_agreement_template.php';
    
} catch (Exception $e) {
    error_log('Error generating refund agreement: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate refund agreement: ' . $e->getMessage()
    ]);
} 