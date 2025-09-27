<?php
// Include database security module for input validation
require_once '../includes/db_security.php';

// Include security module
require_once '../security.php';

// Enforce authentication
enforce_auth();

// Initialize response array
$response = [
    'success' => false,
    'currency' => ''
];
$tenant_id = $_SESSION['tenant_id'];
// Check if supplier_id was provided
if (isset($_POST['supplier_id']) && !empty($_POST['supplier_id'])) {
    $supplierId = intval($_POST['supplier_id']);
    
    // Connect to database
    require_once '../../includes/conn.php';

// Validate supplier_id
$supplier_id = isset($_POST['supplier_id']) ? DbSecurity::validateInput($_POST['supplier_id'], 'int', ['min' => 0]) : null;

    
    // Check connection
    if ($conn->connect_error) {
        $response['error'] = 'Database connection failed: ' . $conn->connect_error;
        echo json_encode($response);
        exit;
    }
    
    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT currency FROM suppliers WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param("ii", $supplierId, $tenant_id);
    
    // Execute query
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check if supplier exists
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $response['success'] = true;
        $response['currency'] = $row['currency'];
    }
    
    // Close connection
    $stmt->close();
    $conn->close();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?> 