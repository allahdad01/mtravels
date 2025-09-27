<?php
require_once('../includes/db.php');

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT id, name FROM suppliers ORDER BY name");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($suppliers);
} catch (PDOException $e) {
    error_log("Error in fetch_suppliers.php: " . $e->getMessage());
    echo json_encode([]);
}
?>
