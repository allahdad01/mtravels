<?php
// Include database connection
require_once '../../includes/db.php';

// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Include language helper
require_once '../../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_permission('finance.delete');

// Initialize response array
$response = ['success' => false, 'message' => 'Invalid request'];

// Check if it's a POST request and delete_transaction is set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_transaction'])) {
    // Get and validate input
    $transaction_id = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
    $debtor_id = isset($_POST['debtor_id']) ? intval($_POST['debtor_id']) : 0;
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $currency = isset($_POST['currency']) ? sanitize_input($_POST['currency']) : '';
    
    // Validate required fields
    if ($transaction_id <= 0 || $debtor_id <= 0) {
        $response = ['success' => false, 'message' => 'Invalid transaction or debtor ID'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    try {
        $pdo->beginTransaction();

        // Get transaction details
        $stmt = $pdo->prepare("SELECT * FROM debtor_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            throw new Exception("Transaction not found");
        }
        
        // Get the linked main account transaction
        $stmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'debtor' AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $main_transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$main_transaction) {
            throw new Exception("Main account transaction not found");
        }

        // Use main account transaction's amount and currency for all main account updates
        $main_amount = $main_transaction['amount'];
        $main_currency = $main_transaction['currency'];

        // Determine adjustment sign based on transaction type: credit deducts, debit adds
        $adjustment = ($main_transaction['type'] == 'credit') ? -$main_amount : $main_amount;

        // Update balances of all subsequent transactions
        $updateSubsequentStmt = $pdo->prepare("
            UPDATE main_account_transactions
            SET balance = balance + ?
            WHERE main_account_id = ?
            AND currency = ?
            AND id > ?
            AND id != ? AND tenant_id = ?
            AND branch_id = ?
        ");
        $updateSubsequentStmt->bindParam(1, $adjustment, PDO::PARAM_STR);
        $updateSubsequentStmt->bindParam(2, $main_transaction['main_account_id'], PDO::PARAM_INT);
        $updateSubsequentStmt->bindParam(3, $main_currency, PDO::PARAM_STR);
        $updateSubsequentStmt->bindParam(4, $main_transaction['id'], PDO::PARAM_INT);
        $updateSubsequentStmt->bindParam(5, $main_transaction['id'], PDO::PARAM_INT);
        $updateSubsequentStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $updateSubsequentStmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        $updateSubsequentStmt->execute();
        
        // Get debtor information
        $stmt = $pdo->prepare("SELECT balance FROM debtors WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $debtor_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $debtor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update debtor balance based on transaction type
        if ($transaction['transaction_type'] == 'credit') {
            $new_balance = $debtor['balance'] + $transaction['amount'];
        } elseif ($transaction['transaction_type'] == 'debit') {
            $new_balance = $debtor['balance'] - $transaction['amount'];
        } else {
            throw new Exception("Invalid transaction type");
        }
        $stmt = $pdo->prepare("UPDATE debtors SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $new_balance, PDO::PARAM_STR);
        $stmt->bindParam(2, $debtor_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Get main account info and update the correct currency balance
        $balance_column = strtolower($main_currency) . '_balance';
        if ($main_currency == 'DARHAM') {
            $balance_column = 'darham_balance';
        } elseif ($main_currency == 'EUR') {
            $balance_column = 'euro_balance';
        } elseif ($main_currency == 'USD') {
            $balance_column = 'usd_balance';
        } elseif ($main_currency == 'AFS') {
            $balance_column = 'afs_balance';
        }
        
        // Get current main account balance
        $stmt = $pdo->prepare("SELECT $balance_column FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $main_transaction['main_account_id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $main_account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$main_account) {
            throw new Exception("Main account not found");
        }
        
        // Update main account balance based on transaction type: credit deducts, debit adds
        $balance_adjustment = ($main_transaction['type'] == 'credit') ? -$main_amount : $main_amount;
        $new_main_balance = $main_account[$balance_column] + $balance_adjustment;
        $stmt = $pdo->prepare("UPDATE main_account SET $balance_column = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $new_main_balance, PDO::PARAM_STR);
        $stmt->bindParam(2, $main_transaction['main_account_id'], PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Delete the transactions
        $stmt = $pdo->prepare("DELETE FROM debtor_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("DELETE FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $main_transaction['id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
       
        
        $pdo->commit();
        $response = [
            'success' => true, 
            'message' => 'Transaction reversed and deleted successfully!',
            'transaction_id' => $transaction_id,
            'debtor_id' => $debtor_id
        ];
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
}

// Set session messages and redirect instead of JSON
if (!empty($response)) {
    if ($response['success']) {
        $_SESSION['success_message'] = $response['message'];
    } else {
        $_SESSION['error_message'] = $response['message'];
    }
    
    // Redirect back to debtors page
    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'debtors.php';
    header('Location: ' . $redirect_url);
    exit();
}

// Return JSON response if no form data
header('Content-Type: application/json');
echo json_encode($response);
exit();
?> 