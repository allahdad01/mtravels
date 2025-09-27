<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

require_once('../includes/db.php');
$tenant_id = $_SESSION['tenant_id'];
header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("
        SELECT id, name, afs_balance, usd_balance 
        FROM main_account 
        WHERE status = 'active' AND tenant_id = ? 
        ORDER BY name
    ");
    $stmt->execute([$tenant_id]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($accounts);
} catch (PDOException $e) {
    error_log("Error in fetch_main_accounts.php: " . $e->getMessage());
    echo json_encode([]);
}
