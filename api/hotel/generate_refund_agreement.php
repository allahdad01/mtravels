<?php
// Initialize the session
session_start();
require_once __DIR__ . '/../../includes/permissions.php';
require_permission('hotels.refund');
$tenant_id = $_SESSION['tenant_id'] ?? 0;
$branch_id = $_SESSION['branch_id'] ?? 0;

if (!$tenant_id) {
    die("Tenant ID not found in session");
}

// Include config file
require_once "../../includes/db.php"; // Make sure this defines $pdo as PDO
require_once '../../vendor/autoload.php'; // For DOMPDF

// Ensure uploads directory exists
$uploadsDir = '../../uploads/hotel/refund_agreements';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Set header for HTML response
header('Content-Type: text/html; charset=UTF-8');

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

// Get refund_id from GET or JSON payload
$refund_id = 0;

if (isset($_GET['refund_id'])) {
    $refund_id = intval($_GET['refund_id']);
} else {
    $json = file_get_contents('php://input');
    if ($json) {
        $data = json_decode($json, true);
        if (isset($data['refund_id'])) {
            $refund_id = intval($data['refund_id']);
        }
    }
}

if (!$refund_id) {
    die("Refund ID is required");
}

// Fetch refund details
try {
    $refundQuery = "
        SELECT
            r.*,
            h.title,
            h.first_name,
            h.last_name,
            h.check_in_date,
            h.check_out_date,
            h.accommodation_details,
            h.order_id,
            h.currency as booking_currency,
            h.supplier_id,
            COALESCE(s.name, '') as supplier_name,
            COALESCE(c.name, '') as client_name,
            COALESCE(c.client_type, '') as client_type,
            COALESCE(u.name, '') as processed_by_name
        FROM hotel_refunds r
        LEFT JOIN hotel_bookings h ON r.booking_id = h.id
        LEFT JOIN suppliers s ON h.supplier_id = s.id
        LEFT JOIN clients c ON h.sold_to = c.id
        LEFT JOIN users u ON r.processed_by = u.id
        WHERE r.id = ? AND r.tenant_id = ? AND r.branch_id = ?
    ";

    $stmt = $pdo->prepare($refundQuery);
    $stmt->execute([$refund_id, $tenant_id, $branch_id]);
    $refund = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$refund) {
        die("Refund not found");
    }

    // Format dates
    $check_in_date = date('F d, Y', strtotime($refund['check_in_date']));
    $check_out_date = date('F d, Y', strtotime($refund['check_out_date']));
    $refund_date = date('F d, Y');

    // Include the template to output the HTML directly
    include 'refund_agreement_template.php';

} catch (PDOException $e) {
    die("Error fetching refund details");
}

// If not JSON request, continue with normal HTML output
?>
