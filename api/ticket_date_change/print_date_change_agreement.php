<?php
use Dompdf\Dompdf;
use Dompdf\Options;

// Include necessary files
require_once('../../includes/db.php');
require_once('../../admin/security.php');
require_once('../../vendor/autoload.php');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
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
        WHERE dc.id = ? AND dc.tenant_id = ? AND dc.branch_id = ?";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$dateChangeId, $tenant_id, $branch_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Date change ticket not found']);
        exit;
    }

// Fetch settings data (using PDO connection)
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $settingStmt->execute();
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        // Fallback defaults if no settings row found
        $settings = ['agency_name' => 'Travel Agency'];
    }
} catch (Exception $e) {
    $settings = ['agency_name' => 'Travel Agency'];
}

// Fetch branch data (from branches table)
try {
    $branchStmt = $pdo->prepare("SELECT name, code, phone, address, email FROM branches WHERE id = ? AND tenant_id = ?");
    $branchStmt->bindParam(1, $branch_id, PDO::PARAM_INT);
    $branchStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $branchStmt->execute();
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branch = null;
}

    // Include the template to output the HTML directly
    include('date_change_agreement_template.php');

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate agreement: ' . $e->getMessage()
    ]);
} 