<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

require_once '../../includes/db.php';

// Get JSON data and verify CSRF token
$data = json_decode(file_get_contents("php://input"), true);
if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

// Assuming you have the logged-in user's username stored in session
$username = isset($_SESSION['name']) ? $_SESSION['name'] : null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
if (!$username) {
    echo json_encode(['success' => false, 'message' => 'User is not logged in.']);
    exit;
}

$accountId = $data['accountId'];
$currency = $data['currency'];
$amount = (float)$data['amount'];
$userRemarks = $data['userRemarks']; // Custom remarks from the user
$receipt = $data['receipt'];






// Validate amount
if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Amount must be greater than zero.']);
    exit;
}

// Fetch the main account balances (USD and AFS) based on account ID
$mainAccountQuery = "SELECT usd_balance, afs_balance, darham_balance, euro_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($mainAccountQuery);
$stmt->bindParam(1, $accountId, PDO::PARAM_INT);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$mainAccount = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mainAccount) {
    echo json_encode(['success' => false, 'message' => 'Main account not found.']);
    exit;
}

// Generate full remark with the user name, date, and custom message

$fullRemark = "Account funded by $username. Remarks: $userRemarks. Receipt: $receipt";

// Update the main account balance based on the selected currency
if ($currency === 'USD') {
    $newUsdBalance = $mainAccount['usd_balance'] + $amount;
    $updateQuery = "UPDATE main_account SET usd_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($updateQuery);
    $stmt->bindParam(1, $newUsdBalance, PDO::PARAM_STR);
    $stmt->bindParam(2, $accountId, PDO::PARAM_INT);
    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    // Log the transaction (funding)
    $transactionStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id, branch_id)
                                       VALUES (?, 'credit', ?, ?, ?, 'fund', ?, ?, ?, ?, ?)");
    $transactionStmt->bindParam(1, $accountId, PDO::PARAM_INT);
    $transactionStmt->bindParam(2, $amount, PDO::PARAM_STR);
    $transactionStmt->bindParam(3, $currency, PDO::PARAM_STR);
    $transactionStmt->bindParam(4, $fullRemark, PDO::PARAM_STR);
    $transactionStmt->bindParam(5, $user_id, PDO::PARAM_INT);
    $transactionStmt->bindParam(6, $newUsdBalance, PDO::PARAM_STR);
    $transactionStmt->bindParam(7, $receipt, PDO::PARAM_STR);
    $transactionStmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
    $transactionStmt->bindParam(9, $branch_id, PDO::PARAM_INT);
    $transactionStmt->execute();

    // Get the transaction ID
    $transactionId = $pdo->lastInsertId();

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

    $activityStmt = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, 'fund', 'main_account', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $activityStmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $activityStmt->bindParam(2, $accountId, PDO::PARAM_INT);
    $activityStmt->bindParam(3, $old_values, PDO::PARAM_STR);
    $activityStmt->bindParam(4, $new_values, PDO::PARAM_STR);
    $activityStmt->bindParam(5, $ip_address, PDO::PARAM_STR);
    $activityStmt->bindParam(6, $user_agent, PDO::PARAM_STR);
    $activityStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $activityStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
    $activityStmt->execute();

    echo json_encode(['success' => true, 'message' => 'Main account funded with USD.']);
} elseif ($currency === 'AFS') {
    $newAfsBalance = $mainAccount['afs_balance'] + $amount;
    $updateQuery = "UPDATE main_account SET afs_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($updateQuery);
    $stmt->bindParam(1, $newAfsBalance, PDO::PARAM_STR);
    $stmt->bindParam(2, $accountId, PDO::PARAM_INT);
    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    // Log the transaction (funding)
    $transactionStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id, branch_id)
                                       VALUES (?, 'credit', ?, ?, ?, 'fund', ?, ?, ?, ?, ?)");
    $transactionStmt->bindParam(1, $accountId, PDO::PARAM_INT);
    $transactionStmt->bindParam(2, $amount, PDO::PARAM_STR);
    $transactionStmt->bindParam(3, $currency, PDO::PARAM_STR);
    $transactionStmt->bindParam(4, $fullRemark, PDO::PARAM_STR);
    $transactionStmt->bindParam(5, $user_id, PDO::PARAM_INT);
    $transactionStmt->bindParam(6, $newAfsBalance, PDO::PARAM_STR);
    $transactionStmt->bindParam(7, $receipt, PDO::PARAM_STR);
    $transactionStmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
    $transactionStmt->bindParam(9, $branch_id, PDO::PARAM_INT);
    $transactionStmt->execute();

    // Get the transaction ID
    $transactionId = $pdo->lastInsertId();

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

    $activityStmt = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, 'fund', 'main_account', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $activityStmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $activityStmt->bindParam(2, $accountId, PDO::PARAM_INT);
    $activityStmt->bindParam(3, $old_values, PDO::PARAM_STR);
    $activityStmt->bindParam(4, $new_values, PDO::PARAM_STR);
    $activityStmt->bindParam(5, $ip_address, PDO::PARAM_STR);
    $activityStmt->bindParam(6, $user_agent, PDO::PARAM_STR);
    $activityStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $activityStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
    $activityStmt->execute();

    echo json_encode(['success' => true, 'message' => 'Main account funded with AFS.']);
} elseif ($currency === 'DARHAM') {
    $newDarhamBalance = $mainAccount['darham_balance'] + $amount;
    $updateQuery = "UPDATE main_account SET darham_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($updateQuery);
    $stmt->bindParam(1, $newDarhamBalance, PDO::PARAM_STR);
    $stmt->bindParam(2, $accountId, PDO::PARAM_INT);
    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    // Log the transaction (funding)
    $transactionStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id, branch_id)
                                       VALUES (?, 'credit', ?, ?, ?, 'fund', ?, ?, ?, ?, ?)");
    $transactionStmt->bindParam(1, $accountId, PDO::PARAM_INT);
    $transactionStmt->bindParam(2, $amount, PDO::PARAM_STR);
    $transactionStmt->bindParam(3, $currency, PDO::PARAM_STR);
    $transactionStmt->bindParam(4, $fullRemark, PDO::PARAM_STR);
    $transactionStmt->bindParam(5, $user_id, PDO::PARAM_INT);
    $transactionStmt->bindParam(6, $newDarhamBalance, PDO::PARAM_STR);
    $transactionStmt->bindParam(7, $receipt, PDO::PARAM_STR);
    $transactionStmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
    $transactionStmt->bindParam(9, $branch_id, PDO::PARAM_INT);
    $transactionStmt->execute();

    // Get the transaction ID
    $transactionId = $pdo->lastInsertId();

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

    $activityStmt = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, 'fund', 'main_account', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $activityStmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $activityStmt->bindParam(2, $accountId, PDO::PARAM_INT);
    $activityStmt->bindParam(3, $old_values, PDO::PARAM_STR);
    $activityStmt->bindParam(4, $new_values, PDO::PARAM_STR);
    $activityStmt->bindParam(5, $ip_address, PDO::PARAM_STR);
    $activityStmt->bindParam(6, $user_agent, PDO::PARAM_STR);
    $activityStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $activityStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
    $activityStmt->execute();

    echo json_encode(['success' => true, 'message' => 'Main account funded with DARHAM.']);
} elseif ($currency === 'EUR') {
    $newEuroBalance = $mainAccount['euro_balance'] + $amount;
    $updateQuery = "UPDATE main_account SET euro_balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($updateQuery);
    $stmt->bindParam(1, $newEuroBalance, PDO::PARAM_STR);
    $stmt->bindParam(2, $accountId, PDO::PARAM_INT);
    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $stmt->execute();

    // Log the transaction (funding)
    $transactionStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id, branch_id)
                                       VALUES (?, 'credit', ?, ?, ?, 'fund', ?, ?, ?, ?, ?)");
    $transactionStmt->bindParam(1, $accountId, PDO::PARAM_INT);
    $transactionStmt->bindParam(2, $amount, PDO::PARAM_STR);
    $transactionStmt->bindParam(3, $currency, PDO::PARAM_STR);
    $transactionStmt->bindParam(4, $fullRemark, PDO::PARAM_STR);
    $transactionStmt->bindParam(5, $user_id, PDO::PARAM_INT);
    $transactionStmt->bindParam(6, $newEuroBalance, PDO::PARAM_STR);
    $transactionStmt->bindParam(7, $receipt, PDO::PARAM_STR);
    $transactionStmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
    $transactionStmt->bindParam(9, $branch_id, PDO::PARAM_INT);
    $transactionStmt->execute();

    // Get the transaction ID
    $transactionId = $pdo->lastInsertId();

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

    $activityStmt = $pdo->prepare("
        INSERT INTO activity_log
        (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
        VALUES (?, 'fund', 'main_account', ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    $activityStmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $activityStmt->bindParam(2, $accountId, PDO::PARAM_INT);
    $activityStmt->bindParam(3, $old_values, PDO::PARAM_STR);
    $activityStmt->bindParam(4, $new_values, PDO::PARAM_STR);
    $activityStmt->bindParam(5, $ip_address, PDO::PARAM_STR);
    $activityStmt->bindParam(6, $user_agent, PDO::PARAM_STR);
    $activityStmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
    $activityStmt->bindParam(8, $branch_id, PDO::PARAM_INT);
    $activityStmt->execute();

    echo json_encode(['success' => true, 'message' => 'Main account funded with EUR.']);
} else  {
    echo json_encode(['success' => false, 'message' => 'Invalid currency type.']);
}

?>
