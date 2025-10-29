<?php
// Include database connection
require_once '../includes/conn.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
require_once 'security.php';
enforce_auth();

// Set JSON header
header('Content-Type: application/json');

// Validate input
if (!isset($_GET['name']) || empty($_GET['name'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid customer name']);
    exit;
}

try {
    // Prepare and execute query to find customer ID by name
    $stmt = $conn->prepare("SELECT id FROM customers WHERE name = ? AND tenant_id = ? LIMIT 1");
    $stmt->bind_param("si", $_GET['name'], $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Customer found
        echo json_encode([
            'success' => true, 
            'customer_id' => $row['id']
        ]);
    } else {
        // No customer found
        echo json_encode([
            'success' => false, 
            'message' => 'Customer not found',
            'customer_id' => ''
        ]);
    }
} catch (Exception $e) {
    // Error handling
    echo json_encode([
        'success' => false, 
        'message' => 'Error fetching customer ID: ' . $e->getMessage(),
        'customer_id' => ''
    ]);
}
exit;
?> 