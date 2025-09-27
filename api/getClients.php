<?php
// getClients.php
include '../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
$query = $pdo->query("SELECT id, name FROM clients where status = 'active' and tenant_id = $tenant_id");
$clients = $query->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($clients);
