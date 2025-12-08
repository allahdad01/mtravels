<?php
// Include security module
require_once '../../admin/security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Enforce authentication
enforce_auth();

require_once '../../includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Account ID is required']);
    exit;
}

$accountId = intval($_GET['id']);

try {
    // Prepare the SQL query
    $query = "SELECT id, name,
                     account_type, bank_account_number account_details,
                     usd_balance, afs_balance, euro_balance, darham_balance,
                     status, last_updated
               FROM main_account
               WHERE id = ? AND tenant_id = ? AND branch_id = ?";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(1, $accountId, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($account) {
        echo json_encode(['success' => true, 'account' => $account]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Account not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

?> 