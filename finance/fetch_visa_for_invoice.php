<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

include '../includes/conn.php';

// Check if the user is logged in
if (!isset($_SESSION['name'])) {
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in to access this resource']);
    exit;
}

try {
    // Query to get tickets
    $query = "SELECT ap.id, ap.applicant_name, ap.passport_number, ap.visa_type, ap.country, ap.applied_date, ap.issued_date, ap.sold
              FROM visa_applications ap
              WHERE ap.tenant_id = ?
              ORDER BY ap.id DESC
              LIMIT 100";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $tickets = [];
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
    
    echo json_encode(['status' => 'success', 'tickets' => $tickets]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?> 