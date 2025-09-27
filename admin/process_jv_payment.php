<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    // Session expired, destroy session and redirect to login
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time(); // Update last activity time

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to process_jv_payment.php - IP: " . $_SERVER['REMOTE_ADDR']);
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Verify user role
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'finance') {
    // Log unauthorized role access attempt
    error_log("Unauthorized role access attempt to process_jv_payment.php: " . $_SESSION['role'] . " - User ID: " . $_SESSION['user_id'] . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit();
}

// Get the username
$username = $_SESSION['name'] ?? 'Unknown';
$user_id = $_SESSION['user_id'];

// Database connection
require_once('../includes/db.php');
require_once('../includes/conn.php');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method';
    header('Location: jv_payments.php');
    exit();
}

// CSRF Protection
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    // Log potential CSRF attack
    error_log("CSRF attack detected in process_jv_payment.php: " . $_SERVER['REMOTE_ADDR']);
    $_SESSION['error'] = 'Invalid security token. Please try again.';
    header('Location: jv_payments.php');
    exit();
}

// Process based on action
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
if (!$action) {
    $_SESSION['error'] = 'Invalid action specified';
    header('Location: jv_payments.php');
    exit();
}

switch ($action) {
    case 'add':
        addJvPayment($pdo, $conn, $user_id, $username);
        break;
    case 'edit':
        editJvPayment($pdo, $conn, $user_id, $username);
        break;
    case 'delete':
        deleteJvPayment($pdo, $conn);
        break;
    default:
        $_SESSION['error'] = 'Invalid action specified';
        header('Location: jv_payments.php');
        exit();
}

/**
 * Function to add a new JV payment
 */
function addJvPayment($pdo, $conn, $userId, $username) {
    // Get form data with proper sanitization
    $jvName = filter_input(INPUT_POST, 'jv_name', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $currency = filter_input(INPUT_POST, 'currency', FILTER_SANITIZE_STRING);
    if (!in_array($currency, ['USD', 'AFS'])) {
        $currency = 'USD'; // Default to USD if invalid currency
    }
    
    // Validate numeric inputs
    $totalAmount = filter_input(INPUT_POST, 'total_amount', FILTER_VALIDATE_FLOAT);
    $usdAmount = filter_input(INPUT_POST, 'usd_amount', FILTER_VALIDATE_FLOAT);
    $afsAmount = filter_input(INPUT_POST, 'afs_amount', FILTER_VALIDATE_FLOAT);
    $exchangeRate = filter_input(INPUT_POST, 'exchange_rate', FILTER_VALIDATE_FLOAT);
    $mainAccountId = filter_input(INPUT_POST, 'main_account_id', FILTER_VALIDATE_INT);
    
    $receipt = filter_input(INPUT_POST, 'receipt', FILTER_SANITIZE_STRING);
    $remarks = filter_input(INPUT_POST, 'remarks', FILTER_SANITIZE_STRING);
    
    // Validate required fields
    if (empty($jvName) || empty($description) || !$totalAmount || $totalAmount <= 0 || 
        !$mainAccountId || $mainAccountId <= 0 || empty($receipt)) {
        $_SESSION['error'] = 'All required fields must be filled out';
        header('Location: jv_payments.php');
        exit();
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Get main account details
        $mainAccountQuery = "SELECT name, usd_balance, afs_balance FROM main_account WHERE id = ? AND tenant_id = ?";
        $mainAccountStmt = $pdo->prepare($mainAccountQuery);
        $mainAccountStmt->execute([$mainAccountId, $tenant_id]);
        $mainAccount = $mainAccountStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$mainAccount) {
            throw new Exception('Main account not found');
        }
        
        $mainAccountName = $mainAccount['name'];
        
        // Prepare balance fields based on currency
        if ($currency === 'USD') {
            $currentBalance = $mainAccount['usd_balance'];
            $updatedBalance = $currentBalance - $totalAmount;
            $balanceField = 'usd_balance';
        } else {
            $currentBalance = $mainAccount['afs_balance'];
            $updatedBalance = $currentBalance - $totalAmount;
            $balanceField = 'afs_balance';
        }
        
        // Check if sufficient balance exists
        if ($currentBalance < $totalAmount) {
            throw new Exception("Insufficient funds in the main account ({$currency})");
        }
        
        // Update main account balance using prepared statement (not string concatenation)
        $updateMainQuery = ($currency === 'USD') 
            ? "UPDATE main_account SET usd_balance = ? WHERE id = ? AND tenant_id = ?"
            : "UPDATE main_account SET afs_balance = ? WHERE id = ? AND tenant_id = ?";
            
        $updateMainStmt = $pdo->prepare($updateMainQuery);
        $updateMainStmt->execute([$updatedBalance, $mainAccountId, $tenant_id]);
        
        // Insert into jv_payments table
        $insertJvQuery = "INSERT INTO jv_payments (
            jv_name, description, usd_amount, afs_amount, exchange_rate,
            total_amount, currency, receipt, remarks, main_account_id, created_by, tenant_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $insertJvStmt = $pdo->prepare($insertJvQuery);
        $insertJvStmt->execute([
            $jvName, $description, $usdAmount, $afsAmount, $exchangeRate,
            $totalAmount, $currency, $receipt, $remarks, $mainAccountId, $userId, $tenant_id
        ]);
        
        $jvPaymentId = $pdo->lastInsertId();
        
        // Create the full remark for transaction logs
        $fullRemark = "JV: " . htmlspecialchars($jvName) . ", processed by: " . htmlspecialchars($username) . 
                      ". Remarks: " . htmlspecialchars($remarks);
        
        // Record JV transaction
        $jvTransactionQuery = "INSERT INTO jv_transactions (
            jv_payment_id, transaction_type, amount, balance, currency, 
            description, receipt, tenant_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $jvTransactionStmt = $pdo->prepare($jvTransactionQuery);
        $jvTransactionStmt->execute([
            $jvPaymentId, 'Debit', $totalAmount, $totalAmount, $currency,
            $fullRemark, $receipt, $tenant_id
        ]);
        
        $jvTransactionId = $pdo->lastInsertId();
        
        // Record main account transaction
        $mainTransactionQuery = "INSERT INTO main_account_transactions (
            main_account_id, type, amount, balance, currency, description,
            created_at, transaction_of, reference_id, receipt, tenant_id
        ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)";

        $mainTransactionDesc = "JV Payment: " . htmlspecialchars($jvName) . ", processed by: " .
                               htmlspecialchars($username) . ". Remarks: " . htmlspecialchars($remarks);

        $mainTransactionStmt = $pdo->prepare($mainTransactionQuery);
        $mainTransactionStmt->execute([
            $mainAccountId, 'debit', $totalAmount, $updatedBalance, $currency,
            $mainTransactionDesc, 'jv_payment', $jvTransactionId, $receipt, $tenant_id
        ]);
        
        // Log successful transaction
        error_log("JV Payment added: ID {$jvPaymentId}, Amount {$totalAmount} {$currency}, User: {$userId}");
        
        // Add activity logging
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Prepare new values data
        $new_values = [
            'jv_payment_id' => $jvPaymentId,
            'jv_name' => $jvName,
            'description' => $description,
            'currency' => $currency,
            'total_amount' => $totalAmount,
            'usd_amount' => $usdAmount,
            'afs_amount' => $afsAmount,
            'exchange_rate' => $exchangeRate,
            'main_account_id' => $mainAccountId,
            'main_account_name' => $mainAccountName,
            'receipt' => $receipt,
            'remarks' => $remarks
        ];
        
        // Insert activity log
        $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");

        $new_values_json = json_encode($new_values);
        $activity_log_stmt->execute([$userId, 'add', 'jv_payments', $jvPaymentId, '{}', $new_values_json, $ip_address, $user_agent, $tenant_id]);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = 'JV payment added successfully';
    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollBack();
        
        // Log the error with details
        error_log("JV Payment Error: " . $e->getMessage() . " - User: {$userId}");
        
        // Return generic error to user
        $_SESSION['error'] = 'Error processing payment. Please try again or contact support.';
    }
    
    header('Location: jv_payments.php');
    exit();
}

/**
 * Function to edit an existing JV payment
 */
function editJvPayment($pdo, $conn, $userId, $username) {
    // Get form data
    $jvId = intval($_POST['id'] ?? 0);
    $jvName = $_POST['jv_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $currency = $_POST['currency'] ?? 'USD';
    $totalAmount = floatval($_POST['total_amount'] ?? 0);
    $usdAmount = floatval($_POST['usd_amount'] ?? 0);
    $afsAmount = floatval($_POST['afs_amount'] ?? 0);
    $exchangeRate = floatval($_POST['exchange_rate'] ?? 0);
    $mainAccountId = intval($_POST['main_account_id'] ?? 0);
    $receipt = $_POST['receipt'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    // Validate required fields
    if ($jvId <= 0 || empty($jvName) || empty($description) || $totalAmount <= 0 || $mainAccountId <= 0 || empty($receipt)) {
        $_SESSION['error'] = 'All required fields must be filled out';
        header('Location: jv_payments.php');
        exit();
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Get existing JV payment details
        $existingQuery = "SELECT * FROM jv_payments WHERE id = ? AND tenant_id = ?";
        $existingStmt = $pdo->prepare($existingQuery);
        $existingStmt->execute([$jvId, $tenant_id]);
        $existingJv = $existingStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existingJv) {
            throw new Exception('JV payment not found');
        }
        
        // Update jv_payments table
        $updateJvQuery = "UPDATE jv_payments SET 
            jv_name = ?, description = ?, usd_amount = ?, afs_amount = ?, 
            exchange_rate = ?, total_amount = ?, currency = ?, receipt = ?, 
            remarks = ?, main_account_id = ?, updated_at = NOW(), tenant_id = ?
            WHERE id = ? AND tenant_id = ?";
            
        $updateJvStmt = $pdo->prepare($updateJvQuery);
        $updateJvStmt->execute([
            $jvName, $description, $usdAmount, $afsAmount, $exchangeRate,
            $totalAmount, $currency, $receipt, $remarks, $mainAccountId, $jvId, $tenant_id
        ]);
        
        // Add activity logging
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Prepare old values data
        $old_values = $existingJv;
        
        // Prepare new values data
        $new_values = [
            'jv_id' => $jvId,
            'jv_name' => $jvName,
            'description' => $description,
            'usd_amount' => $usdAmount,
            'afs_amount' => $afsAmount,
            'exchange_rate' => $exchangeRate,
            'total_amount' => $totalAmount,
            'currency' => $currency,
            'receipt' => $receipt,
            'remarks' => $remarks,
            'main_account_id' => $mainAccountId
        ];
        
        // Insert activity log
        $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");

        $old_values_json = json_encode($old_values);
        $new_values_json = json_encode($new_values);
        $activity_log_stmt->execute([$userId, 'update', 'jv_payments', $jvId, $old_values_json, $new_values_json, $ip_address, $user_agent, $tenant_id]);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = 'JV payment updated successfully';
    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollBack();
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
    
    header('Location: jv_payments.php');
    exit();
}

/**
 * Function to delete a JV payment
 */
function deleteJvPayment($pdo, $conn) {
    $jvId = intval($_POST['id'] ?? 0);
    
    if ($jvId <= 0) {
        $_SESSION['error'] = 'Invalid JV payment ID';
        header('Location: jv_payments.php');
        exit();
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Get JV payment details
        $jvQuery = "SELECT * FROM jv_payments WHERE id = ? AND tenant_id = ?";
        $jvStmt = $pdo->prepare($jvQuery);
        $jvStmt->execute([$jvId, $tenant_id]);
        $jvPayment = $jvStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$jvPayment) {
            throw new Exception('JV payment not found');
        }
        
        // Get main account details
        $mainAccountQuery = "SELECT * FROM main_account WHERE id = ? AND tenant_id = ?";
        $mainAccountStmt = $pdo->prepare($mainAccountQuery);
        $mainAccountStmt->execute([$jvPayment['main_account_id'], $tenant_id]);
        $mainAccount = $mainAccountStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$mainAccount) {
            throw new Exception('Main account not found');
        }
        
        // Determine which balance to refund based on currency
        $totalAmount = $jvPayment['total_amount'];
        $currency = $jvPayment['currency'];
        
        if ($currency === 'USD') {
            $updatedBalance = $mainAccount['usd_balance'] + $totalAmount;
            $balanceField = 'usd_balance';
        } else {
            $updatedBalance = $mainAccount['afs_balance'] + $totalAmount;
            $balanceField = 'afs_balance';
        }
        
        // Refund the amount to main account
        $updateMainQuery = "UPDATE main_account SET {$balanceField} = ? WHERE id = ? AND tenant_id = ?";
        $updateMainStmt = $pdo->prepare($updateMainQuery);
        $updateMainStmt->execute([$updatedBalance, $jvPayment['main_account_id'], $tenant_id]);
        
        // Delete JV payment (this will cascade to delete related transactions due to foreign key constraint)
        $deleteQuery = "DELETE FROM jv_payments WHERE id = ?";
        $deleteStmt = $pdo->prepare($deleteQuery);
        $deleteStmt->execute([$jvId]);
        
        // Add activity logging
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Prepare old values data
        $old_values = $jvPayment;
        
        // Insert activity log
        $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");

        $old_values_json = json_encode($old_values);
        $activity_log_stmt->execute([$user_id, 'delete', 'jv_payments', $jvId, $old_values_json, '{}', $ip_address, $user_agent, $tenant_id]);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = 'JV payment deleted successfully and funds returned to main account';
    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollBack();
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
    
    header('Location: jv_payments.php');
    exit();
} 