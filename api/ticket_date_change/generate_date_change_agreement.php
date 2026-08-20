<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../includes/permissions.php';
require_permission('tickets.date_change');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Include necessary files
require_once '../../includes/db.php';
require_once '../../vendor/autoload.php';

// Check if ticket ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid ticket ID');
}

$ticketId = intval($_GET['id']);

// Fetch ticket details
$query = "
    SELECT 
        dc.*,
        tb.passenger_name,
        tb.title,
        s.name AS supplier_name,
        tb.departure_date AS old_departure_date
    FROM 
        date_change_tickets dc
    LEFT JOIN 
        suppliers s ON dc.supplier = s.id
    LEFT JOIN
        ticket_bookings tb ON dc.ticket_id = tb.id
    WHERE
        dc.id = ? AND dc.tenant_id = ? AND dc.branch_id = ?
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute([$ticketId, $tenant_id, $branch_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        die('Ticket not found');
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
    $branchStmt = $pdo->prepare("SELECT name, code, phone, address FROM branches WHERE id = ? AND tenant_id = ?");
    $branchStmt->bindParam(1, $branch_id, PDO::PARAM_INT);
    $branchStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $branchStmt->execute();
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $branch = null;
}

    // Format the current date
    $agreement_date = date('F d, Y');

    // Create new PDF instance
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15
    ]);

    // Get the template content
    ob_start();
    include 'date_change_agreement_template.php';
    $html = ob_get_clean();

      // Create directory if it doesn't exist
      if (!file_exists($directory)) {
        mkdir($directory, 0777, true);
    }

    // Write PDF content
    $mpdf->WriteHTML($html);

    // Get full passenger name
    $full_name = trim($ticket['title'] . ' ' . $ticket['passenger_name']);
    $passenger_name = !empty($full_name) ? $full_name : $ticketId;
    $sanitized_name = preg_replace('/[^a-zA-Z0-9]/', '_', $passenger_name);
    $sanitized_name = trim($sanitized_name, '_');
    
    // Set filename
    $filename = 'date_change_agreement_' . $sanitized_name . '_' . date('Y-m-d_His') . '.pdf';

    // Output PDF
    $mpdf->Output($filename, 'D');

} catch (Exception $e) {
    die('Error generating agreement. Please try again later.');
} 