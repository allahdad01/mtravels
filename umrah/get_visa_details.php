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



// Include database connection
require_once('../includes/conn.php');



// Validate input
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid visa ID']);
    exit;
}

$visaId = intval($_GET['id']);

try {
    // Prepare the query to fetch visa details
    $query = "SELECT v.* 
                    
              FROM visa_applications v
              
              WHERE v.id = ? AND v.tenant_id = ?";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $visaId, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Visa not found']);
        exit;
    }
    
    // Fetch the visa data
    $visa = $result->fetch_assoc();
    
    
    // Return success response with visa data
    echo json_encode([
        'success' => true,
        'visa' => $visa
    ]);
    
} catch (Exception $e) {
    // Log error (adjust this according to your logging system)
    error_log('Error fetching visa details: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching visa details'
    ]);
}
?> 