<?php
require_once '../../includes/db.php';
require_once '../security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Customer ID not provided']);
    exit;
}

$customer_id = intval($_GET['id']);

try {
    // Prepare and execute the query
    $stmt = $pdo->prepare("SELECT id, name, phone, email, address FROM customers WHERE id = ? AND status = 'active' AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$customer_id, $tenant_id, $branch_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($customer) {
        echo json_encode(['success' => true, 'customer' => $customer]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} 