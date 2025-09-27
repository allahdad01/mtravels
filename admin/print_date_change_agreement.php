<?php
use Dompdf\Dompdf;
use Dompdf\Options;

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

// Check if date change ID is provided
if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Date Change ID is required']);
    exit;
}

$dateChangeId = intval($_GET['id']);

try {
    // Get date change details with related information
    $query = "
        SELECT dc.*, tb.departure_date as old_departure_date,
               s.name as supplier_name, c.name as client_name,
               m.name as account_name
        FROM date_change_tickets dc
        LEFT JOIN suppliers s ON dc.supplier = s.id
        LEFT JOIN clients c ON dc.sold_to = c.id
        LEFT JOIN main_account m ON dc.paid_to = m.id
        LEFT JOIN ticket_bookings tb ON dc.ticket_id = tb.id
        WHERE dc.id = ? AND dc.tenant_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$dateChangeId, $tenant_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Date change ticket not found']);
        exit;
    }

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
    include('templates/date_change_agreement_template.php');

} catch (Exception $e) {
    error_log('Error generating date change agreement: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate agreement: ' . $e->getMessage()
    ]);
} 