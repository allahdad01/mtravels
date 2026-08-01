<?php
// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

require_once('../../includes/db.php');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
header('Content-Type: application/json');

try {
    if (!isset($_GET['umrah_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Umrah ID is required'
        ]);
        exit;
    }
    
    $umrah_id = intval($_GET['umrah_id']);
    
    // Get supplier_id from umrah_booking_services where service_type is 'all' or 'visa'
    $stmt_fetch_supplier_id = $pdo->prepare("
        SELECT supplier_id FROM umrah_booking_services 
        WHERE booking_id = ? AND tenant_id = ? AND branch_id = ? 
        AND (service_type = 'all' OR FIND_IN_SET('visa', REPLACE(service_type, '+', ',')) > 0) LIMIT 1
    ");
    $stmt_fetch_supplier_id->bindParam(1, $umrah_id, PDO::PARAM_INT);
    $stmt_fetch_supplier_id->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_fetch_supplier_id->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt_fetch_supplier_id->execute();
    $supplier_result = $stmt_fetch_supplier_id->fetch(PDO::FETCH_ASSOC);

    if (!$supplier_result) {
        echo json_encode([
            'success' => false,
            'message' => 'Supplier not found for this booking'
        ]);
        exit;
    }

    $supplier_id = $supplier_result['supplier_id'];
    
    // Fetch Supplier Type
    $stmt_fetch_supplier = $pdo->prepare("
        SELECT supplier_type FROM suppliers 
        WHERE id = ? AND tenant_id = ? AND branch_id = ?
    ");
    $stmt_fetch_supplier->bindParam(1, $supplier_id, PDO::PARAM_INT);
    $stmt_fetch_supplier->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt_fetch_supplier->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt_fetch_supplier->execute();
    $supplier_data = $stmt_fetch_supplier->fetch(PDO::FETCH_ASSOC);

    if (!$supplier_data) {
        echo json_encode([
            'success' => false,
            'message' => 'Supplier details not found'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'supplier_id' => $supplier_id,
        'supplier_type' => $supplier_data['supplier_type']
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch supplier type'
    ]);
}
