<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

// Include database connection
include '../includes/db.php';
include '../includes/conn.php';

// Initialize response array
$response = array(
    'success' => false,
    'umrah' => null,
    'message' => ''
);

try {
    // Get umrah ID from the request
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Umrah ID is required');
    }
    
    $umrahId = intval($_GET['id']);
    
    // Query to get umrah details
    $query = "SELECT 
                u.*, 
                f.head_of_family AS family_name
              FROM 
                umrah_bookings u
              LEFT JOIN 
                families f ON u.family_id = f.family_id
              WHERE 
                u.booking_id = ? AND u.tenant_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$umrahId, $tenant_id]);
    
    if ($stmt->rowCount() > 0) {
        $umrah = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Format data as needed
        $response['success'] = true;
        $response['umrah'] = $umrah;
    } else {
        throw new Exception('Umrah not found');
    }
} catch (Exception $e) {
    $response['success'] = false;
    error_log("Error fetching umrah details: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

// Set content type to JSON
header('Content-Type: application/json');

// Return the response
echo json_encode($response);
exit;
?> 