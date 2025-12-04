<?php
session_start();

// Initialize response array
$response = [
    'success' => false,
    'currency' => ''
];
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Check if supplier_id was provided
if (isset($_REQUEST['supplier_id']) && !empty($_REQUEST['supplier_id'])) {
    $supplierId = intval($_REQUEST['supplier_id']);

    // Connect to database
    require_once '../../includes/conn.php';

// Validate supplier_id
$supplier_id = intval($_REQUEST['supplier_id']);

    
    // Check connection
    if ($conn->connect_error) {
        $response['error'] = 'Database connection failed: ' . $conn->connect_error;
        echo json_encode($response);
        exit;
    }
    
    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT currency FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bind_param("iii", $supplier_id, $tenant_id, $branch_id);
    
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