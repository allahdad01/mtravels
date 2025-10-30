<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];

require_once('../includes/db.php');

// Check if required parameters are present
if (!isset($_POST['payment_id']) || !isset($_POST['amount']) || !isset($_POST['main_account_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$payment_id = intval($_POST['payment_id']);
$amount = floatval($_POST['amount']);
$main_account_id = intval($_POST['main_account_id']);

try {
    // Start transaction
    $pdo->beginTransaction();

    // First get the payment details
    $getPaymentStmt = $pdo->prepare("
        SELECT sp.*, sp.currency as payment_currency, sp.created_at as payment_date,
               ma.usd_balance, ma.afs_balance
        FROM salary_payments sp
        JOIN main_account ma ON sp.main_account_id = ma.id
        WHERE sp.id = ? AND sp.tenant_id = ?
    ");
    $getPaymentStmt->execute([$payment_id, $tenant_id]);
    $payment = $getPaymentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception('Payment not found');
    }

    // Determine which balance to update based on currency
    $balanceColumn = strtoupper($payment['currency']) === 'USD' ? 'usd_balance' : 'afs_balance';

    // Update balances of all subsequent transactions
    $updateSubsequentStmt = $pdo->prepare("
        UPDATE main_account_transactions 
        SET balance = balance + ?
        WHERE main_account_id = ? 
        AND currency = ? 
        AND created_at > ? 
        AND tenant_id = ?
    ");
    $updateSubsequentResult = $updateSubsequentStmt->execute([
        $amount, 
        $main_account_id, 
        $payment['currency'], 
        $payment['payment_date'],
        $tenant_id
    ]);

    if (!$updateSubsequentResult) {
        throw new Exception('Failed to update subsequent transaction balances');
    }

    // Delete the transaction record
    $deleteTransactionStmt = $pdo->prepare("
        DELETE FROM main_account_transactions 
        WHERE transaction_of = 'salary_payment' AND reference_id = ? AND tenant_id = ?
    ");
    $deleteTransactionResult = $deleteTransactionStmt->execute([$payment_id, $tenant_id]);

    if (!$deleteTransactionResult) {
        throw new Exception('Failed to delete transaction record');
    }

    // If this is an advance payment, delete the corresponding salary advance record
    if ($payment['payment_type'] === 'advance') {
        $deleteAdvanceStmt = $pdo->prepare("
            DELETE FROM salary_advances 
            WHERE user_id = ? 
            AND main_account_id = ? 
            AND amount = ? 
            AND currency = ? 
            AND receipt = ?
            AND tenant_id = ?
        ");
        $deleteAdvanceResult = $deleteAdvanceStmt->execute([
            $payment['user_id'],
            $payment['main_account_id'],
            $payment['amount'],
            $payment['currency'],
            $payment['receipt'],
            $tenant_id
        ]);

        if (!$deleteAdvanceResult) {
            throw new Exception('Failed to delete salary advance record');
        }
    }

    // Delete the salary payment
    $deletePaymentStmt = $pdo->prepare("DELETE FROM salary_payments WHERE id = ? AND tenant_id = ?");
    $deletePaymentResult = $deletePaymentStmt->execute([$payment_id, $tenant_id]);

    if ($deletePaymentResult) {
        // Update the main account balance
        $updateAccountStmt = $pdo->prepare("
            UPDATE main_account 
            SET $balanceColumn = $balanceColumn + ?
            WHERE id = ? AND tenant_id = ?
        ");
        $updateAccountResult = $updateAccountStmt->execute([$amount, $main_account_id, $tenant_id]);

        if ($updateAccountResult) {
            // If this was a regular payment that deducted from advances, reverse those deductions
            if ($payment['payment_type'] === 'regular') {
                $getAdvanceDeductionsStmt = $pdo->prepare("
                    SELECT id, amount_paid, amount 
                    FROM salary_advances 
                    WHERE user_id = ? AND currency = ? AND repayment_status IN ('partially_paid', 'paid')
                    ORDER BY created_at DESC
                ");
                $getAdvanceDeductionsStmt->execute([$payment['user_id'], $payment['currency']]);
                $advances = $getAdvanceDeductionsStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($advances as $advance) {
                    $updateAdvanceStmt = $pdo->prepare("
                        UPDATE salary_advances 
                        SET amount_paid = ?, repayment_status = ? 
                        WHERE id = ?
                    ");
                    $newAmountPaid = max(0, $advance['amount_paid'] - $amount);
                    $newStatus = $newAmountPaid >= $advance['amount'] ? 'paid' : 
                               ($newAmountPaid > 0 ? 'partially_paid' : 'unpaid');
                    $updateAdvanceStmt->execute([$newAmountPaid, $newStatus, $advance['id']]);
                }
            }

            // Log the activity
            $old_values = json_encode([
                'payment_id' => $payment_id,
                'amount' => $amount,
                'currency' => $payment['currency'],
                'main_account_id' => $main_account_id,
                'payment_date' => $payment['payment_date'],
                'payment_type' => $payment['payment_type']
            ]);
            
            $activityStmt = $pdo->prepare("
                INSERT INTO activity_log 
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
                VALUES (?, 'delete', 'salary_payments', ?, ?, '{}', ?, ?, NOW(), ?)
            ");
            $activityStmt->execute([
                $_SESSION['user_id'],
                $payment_id,
                $old_values,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'],
                $tenant_id
            ]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Payment deleted successfully']);
        } else {
            throw new Exception('Failed to update main account balance');
        }
    } else {
        throw new Exception('Failed to delete payment record');
    }
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error deleting salary payment: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error deleting payment: ' . $e->getMessage()]);
}
?> 