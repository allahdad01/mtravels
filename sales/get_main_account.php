<?php
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once '../includes/conn.php';

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
              WHERE id = ? AND tenant_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $accountId, $tenant_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $account = $result->fetch_assoc();
    
    if ($account) {
        echo json_encode(['success' => true, 'account' => $account]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Account not found']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?> 