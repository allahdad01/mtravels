<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once('../includes/db.php');

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE tenant_id = ? ORDER BY name");
    $stmt->execute([$tenant_id]);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($clients);
} catch (PDOException $e) {
    error_log("Error in fetch_clients.php: " . $e->getMessage());
    echo json_encode([]);
}
