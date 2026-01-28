<?php
require_once('../includes/db.php');

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("SELECT id, name FROM clients ORDER BY name");
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($clients);
} catch (PDOException $e) {
    error_log("Error in fetch_clients.php: " . $e->getMessage());
    echo json_encode([]);
}
