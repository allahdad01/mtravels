<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once('../includes/db.php');

header('Content-Type: application/json');

try {
    // Prepare and execute the query
    $stmt = $pdo->prepare("SELECT *, COALESCE(status, 'active') as status FROM suppliers WHERE tenant_id = ? ORDER BY name");
    $stmt->execute([$tenant_id]);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response
    $response = [
        'suppliers' => $suppliers
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Error fetching suppliers: " . $e->getMessage());
    echo json_encode([
        'suppliers' => [],
        'error' => 'Error fetching suppliers'
    ]);
}
?>
