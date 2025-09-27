<?php
// getMainAccounts.php
include '../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
// Use the PDO connection to fetch all main account records
$query = $pdo->query("SELECT id, name FROM main_account where status = 'active' and tenant_id = $tenant_id");
$accounts = $query->fetchAll(PDO::FETCH_ASSOC);

// Set the response content type to JSON
header('Content-Type: application/json');

// Output the accounts as a JSON response
echo json_encode($accounts);
?>
