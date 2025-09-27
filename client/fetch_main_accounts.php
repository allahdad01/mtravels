<?php
require_once('../includes/db.php');

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT id, name FROM main_account ORDER BY name");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($accounts);
} catch (PDOException $e) {
    error_log("Error in fetch_main_accounts.php: " . $e->getMessage());
    echo json_encode([]);
}
