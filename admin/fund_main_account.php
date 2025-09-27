<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

require_once '../includes/conn.php';
// Assuming you have the logged-in user's username stored in session
$username = isset($_SESSION['name']) ? $_SESSION['name'] : null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$tenant_id = $_SESSION['tenant_id'];
if (!$username) {
    echo json_encode(['success' => false, 'message' => 'User is not logged in.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$accountId = $data['accountId'];
$currency = $data['currency'];
$amount = (float)$data['amount'];
$userRemarks = $data['userRemarks']; // Custom remarks from the user
$receipt = $data['receipt'];





if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit;
}

// Validate amount
if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Amount must be greater than zero.']);
    exit;
}

// Fetch the main account balances (USD and AFS) based on account ID
$mainAccountQuery = "SELECT usd_balance, afs_balance, darham_balance, euro_balance FROM main_account WHERE id = ? AND tenant_id = ?";
$stmt = $conn->prepare($mainAccountQuery);
$stmt->bind_param('ii', $accountId, $tenant_id);
$stmt->execute();
$mainAccount = $stmt->get_result()->fetch_assoc();

if (!$mainAccount) {
    echo json_encode(['success' => false, 'message' => 'Main account not found.']);
    exit;
}

// Generate full remark with the user name, date, and custom message

$fullRemark = "Account funded by $username. Remarks: $userRemarks. Receipt: $receipt";

// Update the main account balance based on the selected currency
if ($currency === 'USD') {
    $newUsdBalance = $mainAccount['usd_balance'] + $amount;
    $updateQuery = "UPDATE main_account SET usd_balance = ? WHERE id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('dii', $newUsdBalance, $accountId, $tenant_id);
    $stmt->execute();
    
    // Log the transaction (funding)
    $transactionStmt = $conn->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id)
                                       VALUES (?, 'credit', ?, ?, ?, 'fund', ?, ?, ?, ?)");
    $transactionStmt->bind_param('ssssssss', $accountId, $amount, $currency, $fullRemark, $user_id, $newUsdBalance, $receipt, $tenant_id);
    $transactionStmt->execute();
    
    // Get the transaction ID
    $transactionId = $conn->insert_id;
    
    // Log the activity
    $old_values = json_encode([
        'account_id' => $accountId,
        'usd_balance' => $mainAccount['usd_balance']
    ]);
    $new_values = json_encode([
        'account_id' => $accountId,
        'usd_balance' => $newUsdBalance,
        'amount' => $amount,
        'currency' => $currency,
        'description' => $fullRemark
    ]);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $activityStmt = $conn->prepare("
        INSERT INTO activity_log 
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
        VALUES (?, 'fund', 'main_account', ?, ?, ?, ?, ?, NOW(), ?)
    ");
    $activityStmt->bind_param("iissssi", $user_id, $accountId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
    $activityStmt->execute();

    echo json_encode(['success' => true, 'message' => 'Main account funded with USD.']);
} elseif ($currency === 'AFS') {
    $newAfsBalance = $mainAccount['afs_balance'] + $amount;
    $updateQuery = "UPDATE main_account SET afs_balance = ? WHERE id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('dii', $newAfsBalance, $accountId, $tenant_id);
    $stmt->execute();
    
    // Log the transaction (funding)
    $transactionStmt = $conn->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id)
                                       VALUES (?, 'credit', ?, ?, ?, 'fund', ?, ?, ?, ?)");
    $transactionStmt->bind_param('isssissi', $accountId, $amount, $currency, $fullRemark, $user_id, $newAfsBalance, $receipt, $tenant_id);
    $transactionStmt->execute();
    
    // Get the transaction ID
    $transactionId = $conn->insert_id;
    
    // Log the activity
    $old_values = json_encode([
        'account_id' => $accountId,
        'afs_balance' => $mainAccount['afs_balance']
    ]);
    $new_values = json_encode([
        'account_id' => $accountId,
        'afs_balance' => $newAfsBalance,
        'amount' => $amount,
        'currency' => $currency,
        'description' => $fullRemark
    ]);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $activityStmt = $conn->prepare("
        INSERT INTO activity_log 
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
        VALUES (?, 'fund', 'main_account', ?, ?, ?, ?, ?, NOW(), ?)
    ");
    $activityStmt->bind_param("iissssi", $user_id, $accountId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
    $activityStmt->execute();

    echo json_encode(['success' => true, 'message' => 'Main account funded with AFS.']);
} elseif ($currency === 'DARHAM') {
    $newDarhamBalance = $mainAccount['darham_balance'] + $amount;
    $updateQuery = "UPDATE main_account SET darham_balance = ? WHERE id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('dii', $newDarhamBalance, $accountId, $tenant_id);
    $stmt->execute();

    // Log the transaction (funding)
    $transactionStmt = $conn->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id)
                                       VALUES (?, 'credit', ?, ?, ?, 'fund', ?, ?, ?, ?)");
    $transactionStmt->bind_param('isssissi', $accountId, $amount, $currency, $fullRemark, $user_id, $newDarhamBalance, $receipt, $tenant_id);
    $transactionStmt->execute();
    
    // Get the transaction ID
    $transactionId = $conn->insert_id;
    
    // Log the activity
    $old_values = json_encode([
        'account_id' => $accountId,
        'darham_balance' => $mainAccount['darham_balance']
    ]);
    $new_values = json_encode([
        'account_id' => $accountId,
        'darham_balance' => $newDarhamBalance,
        'amount' => $amount,
        'currency' => $currency,
        'description' => $fullRemark
    ]);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $activityStmt = $conn->prepare("
        INSERT INTO activity_log 
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
        VALUES (?, 'fund', 'main_account', ?, ?, ?, ?, ?, NOW(), ?)
    ");
    $activityStmt->bind_param("iissss", $user_id, $accountId, $old_values, $new_values, $ip_address, $user_agent);
    $activityStmt->execute();

    echo json_encode(['success' => true, 'message' => 'Main account funded with DARHAM.']);
} elseif ($currency === 'EUR') {
    $newEuroBalance = $mainAccount['euro_balance'] + $amount;
    $updateQuery = "UPDATE main_account SET euro_balance = ? WHERE id = ? AND tenant_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('dii', $newEuroBalance, $accountId, $tenant_id);
    $stmt->execute();

    // Log the transaction (funding)
    $transactionStmt = $conn->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id)
                                       VALUES (?, 'credit', ?, ?, ?, 'fund', ?, ?, ?, ?)");
    $transactionStmt->bind_param('isssissi', $accountId, $amount, $currency, $fullRemark, $user_id, $newEuroBalance, $receipt, $tenant_id);
    $transactionStmt->execute();
    
    // Get the transaction ID
    $transactionId = $conn->insert_id;
    
    // Log the activity
    $old_values = json_encode([
        'account_id' => $accountId,
        'euro_balance' => $mainAccount['euro_balance']
    ]);
    $new_values = json_encode([
        'account_id' => $accountId,
        'euro_balance' => $newEuroBalance,
        'amount' => $amount,
        'currency' => $currency,
        'description' => $fullRemark
    ]);
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $activityStmt = $conn->prepare("
        INSERT INTO activity_log 
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
        VALUES (?, 'fund', 'main_account', ?, ?, ?, ?, ?, NOW(), ?)
    ");
    $activityStmt->bind_param("iissssi", $user_id, $accountId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id);
    $activityStmt->execute();

    echo json_encode(['success' => true, 'message' => 'Main account funded with EUR.']);
} else  {
    echo json_encode(['success' => false, 'message' => 'Invalid currency type.']);
}

$conn->close();
?>
