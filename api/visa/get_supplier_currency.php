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
    require_once '../../includes/db.php';

    // Validate supplier_id
    $supplier_id = intval($_REQUEST['supplier_id']);


    // Check connection
    if (!$pdo) {
        $response['error'] = 'Database connection failed';
        echo json_encode($response);
        exit;
    }

    // Prepare statement to prevent SQL injection
    $stmt = $pdo->prepare("SELECT currency FROM suppliers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->bindParam(1, $supplier_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);

    // Execute query
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if supplier exists
    if ($result) {
        $response['success'] = true;
        $response['currency'] = $result['currency'];
    }

    // Close connection
    $stmt->closeCursor();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>