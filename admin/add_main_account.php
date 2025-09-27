<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
require_once '../includes/conn.php';

// Validate status
$status = isset($_POST['status']) ? DbSecurity::validateInput($_POST['status'], 'string', ['maxlength' => 255]) : null;

// Validate afs_balance
$afs_balance = isset($_POST['afs_balance']) ? DbSecurity::validateInput($_POST['afs_balance'], 'float', ['min' => 0]) : null;

// Validate usd_balance
$usd_balance = isset($_POST['usd_balance']) ? DbSecurity::validateInput($_POST['usd_balance'], 'float', ['min' => 0]) : null;

// Validate bank_name
$bank_name = isset($_POST['bank_name']) ? DbSecurity::validateInput($_POST['bank_name'], 'string', ['maxlength' => 255]) : null;

// Validate bank_account_number
$bank_account_number = isset($_POST['bank_account_number']) ? DbSecurity::validateInput($_POST['bank_account_number'], 'string', ['maxlength' => 255]) : null;
$bank_account_afs_number = isset($_POST['bank_account_afs_number']) ? DbSecurity::validateInput($_POST['bank_account_afs_number'], 'string', ['maxlength' => 255]) : null;
// Validate account_type
$account_type = isset($_POST['account_type']) ? DbSecurity::validateInput($_POST['account_type'], 'string', ['maxlength' => 255]) : null;

// Validate account_name
$account_name = isset($_POST['account_name']) ? DbSecurity::validateInput($_POST['account_name'], 'string', ['maxlength' => 255]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accountName = $_POST['account_name'];
    $accountType = $_POST['account_type'];
    $bankAccountNumber = ($accountType === 'bank') ? $_POST['bank_account_number'] : null;
    $bankAccountAfsNumber = ($accountType === 'bank') ? $_POST['bank_account_afs_number'] : null;
    $bankName = ($accountType === 'bank') ? $_POST['bank_name'] : null;
    $usdBalance = $_POST['usd_balance'];
    $afsBalance = $_POST['afs_balance'];
    $status = isset($_POST['status']) ? $_POST['status'] : 'active';

    // Updated query to include tenant_id
    $query = "INSERT INTO main_account (name, account_type, bank_account_number, bank_account_afs_number, bank_name, usd_balance, afs_balance, last_updated, status, tenant_id) 
              VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";

    $stmt = $conn->prepare($query);
    // Updated bind_param to include tenant_id (added 'i' for integer and $tenant_id parameter)
    $stmt->bind_param("sssssssss", $accountName, $accountType, $bankAccountNumber, $bankAccountAfsNumber, $bankName, $usdBalance, $afsBalance, $status, $tenant_id);

    if ($stmt->execute()) {
        // Get the insert ID
        $account_id = $conn->insert_id;
        
        // Log the activity
        $old_values = json_encode([]);
        $new_values = json_encode([
            'name' => $accountName,
            'account_type' => $accountType,
            'bank_account_number' => $bankAccountNumber,
            'bank_name' => $bankName,
            'usd_balance' => $usdBalance,
            'afs_balance' => $afsBalance,
            'status' => $status,
            'tenant_id' => $tenant_id
        ]);
        
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt_log = $conn->prepare("
            INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
            VALUES (?, 'add', 'main_account', ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt_log->bind_param("iissssi", $user_id, $account_id, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
        $stmt_log->execute();
        $stmt_log->close();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add account.']);
    }

    $stmt->close();
    $conn->close();
}
?>