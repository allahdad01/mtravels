<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

require '../vendor/autoload.php';
require_once '../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

$response = ['success' => false, 'accounts' => []];

$query = "SELECT id, name, usd_balance, afs_balance FROM main_account where status = 'active' AND tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($query);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchAll();

if ($result && count($result) > 0) {
    $response['accounts'] = $result;
    $response['success'] = true;
}

echo json_encode($response);  // Send as JSON
?>
