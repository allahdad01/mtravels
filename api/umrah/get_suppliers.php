<?php
// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

require_once '../../includes/db.php';

// Fetch suppliers with currency
$suppliersQuery = "SELECT id, name, currency FROM suppliers WHERE tenant_id = ? AND (branch_id = ? OR (branch_id IS NULL AND ? IS NULL)) AND status = 'active' AND category IN ('umrah', 'all')";
$suppliersStmt = $pdo->prepare($suppliersQuery);
$suppliersStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$suppliersStmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$suppliersStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$suppliersStmt->execute();
$suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch main account details
$mainAccountQuery = "SELECT id, name FROM main_account WHERE tenant_id = ? AND (branch_id = ? OR (branch_id IS NULL AND ? IS NULL))";
$mainAccountStmt = $pdo->prepare($mainAccountQuery);
$mainAccountStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$mainAccountStmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$mainAccountStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$mainAccountStmt->execute();
$mainAccount = $mainAccountStmt->fetch(PDO::FETCH_ASSOC);

// Combine data into a single response
$response = [
    'main_account' => $mainAccount, // Single main account
    'suppliers' => $suppliers       // Array of suppliers
];

echo json_encode($response);
?>
