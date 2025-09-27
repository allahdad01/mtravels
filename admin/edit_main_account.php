<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Database connection
require_once('../includes/db.php');
require_once('../includes/conn.php');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get the form data
$accountId = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;
$accountName = isset($_POST['account_name']) ? trim($_POST['account_name']) : '';
$accountType = isset($_POST['account_type']) ? trim($_POST['account_type']) : '';
$bankAccountNumber = ($accountType === 'bank') ? isset($_POST['bank_account_number']) ? $_POST['bank_account_number'] : null : null;
$bankAccountAfsNumber = ($accountType === 'bank') ? isset($_POST['bank_account_afs_number']) ? $_POST['bank_account_afs_number'] : null : null;
$status = isset($_POST['status']) ? $_POST['status'] : 'active';

// Validate the input
if ($accountId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid account ID']);
    exit();
}

if (empty($accountName)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Account name is required']);
    exit();
}

// Validate status
$status = isset($_POST['status']) ? DbSecurity::validateInput($_POST['status'], 'string', ['maxlength' => 255]) : null;

// Validate bank_account_number
$bank_account_number = isset($_POST['bank_account_number']) ? DbSecurity::validateInput($_POST['bank_account_number'], 'string', ['maxlength' => 255]) : null;
$bank_account_afs_number = isset($_POST['bank_account_afs_number']) ? DbSecurity::validateInput($_POST['bank_account_afs_number'], 'string', ['maxlength' => 255]) : null;
// Validate account_type
$account_type = isset($_POST['account_type']) ? DbSecurity::validateInput($_POST['account_type'], 'string', ['maxlength' => 255]) : null;

// Validate account_name
$account_name = isset($_POST['account_name']) ? DbSecurity::validateInput($_POST['account_name'], 'string', ['maxlength' => 255]) : null;

// Validate account_id
$account_id = isset($_POST['account_id']) ? DbSecurity::validateInput($_POST['account_id'], 'int', ['min' => 0]) : null;

try {
    // Check if the account exists
    $stmt = $pdo->prepare("SELECT * FROM main_account WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$accountId, $tenant_id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Account not found']);
        exit();
    }

    // Update the account in the database
    $stmt = $pdo->prepare("UPDATE main_account SET name = ?, account_type = ?, bank_account_number = ?, bank_account_afs_number = ?, status = ?, last_updated = NOW() WHERE id = ? AND tenant_id = ?");
    $result = $stmt->execute([$accountName, $accountType, $bankAccountNumber, $bankAccountAfsNumber, $status, $accountId, $tenant_id]);

    if ($result) {
        // Log the activity
        $old_values = json_encode([
            'name' => $account['name'],
            'account_type' => $account['account_type'],
            'bank_account_number' => $account['bank_account_number'],
            'status' => $account['status']
        ]);
        $new_values = json_encode([
            'name' => $accountName,
            'account_type' => $accountType,
            'bank_account_number' => $bankAccountNumber,
            'status' => $status
        ]);
        
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $activityStmt = $pdo->prepare("
            INSERT INTO activity_log 
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
            VALUES (?, 'update', 'main_account', ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $activityStmt->execute([$user_id, $accountId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Main account updated successfully']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to update main account']);
    }
} catch (PDOException $e) {
    // Log the error
    error_log("Database Error: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 