<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();



// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');

// Get tenant_id and branch_id from session
$tenant_id = $_SESSION['tenant_id'] ?? null;
$branch_id = $_SESSION['branch_id'] ?? null;

// Validate allocation_id
$allocation_id = isset($_POST['allocation_id']) ? DbSecurity::validateInput($_POST['allocation_id'], 'int', ['min' => 0]) : null;

// Validate description
$description = isset($_POST['description']) ? DbSecurity::validateInput($_POST['description'], 'string', ['maxlength' => 255]) : null;

// Validate date
$date = isset($_POST['date']) ? DbSecurity::validateInput($_POST['date'], 'date') : null;

// Validate currency
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : null;

// Validate amount
$amount = isset($_POST['amount']) ? DbSecurity::validateInput($_POST['amount'], 'float', ['min' => 0]) : null;

// Validate main_account_id
$main_account_id = isset($_POST['main_account_id']) ? DbSecurity::validateInput($_POST['main_account_id'], 'int', ['min' => 0]) : null;

// Validate action
$action = isset($_POST['action']) ? DbSecurity::validateInput($_POST['action'], 'string', ['maxlength' => 255]) : null;

// Handle different actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    switch ($action) {
         case 'create_global_allocation':
             createGlobalAllocation($pdo, $tenant_id, $branch_id);
             break;
         case 'add_funds_global':
             addFundsGlobal($pdo, $tenant_id, $branch_id);
             break;
         case 'delete_global_allocation':
             deleteGlobalAllocation($pdo, $tenant_id, $branch_id);
             break;
         case 'add_expense_global':
             addExpenseGlobal($pdo, $tenant_id, $branch_id);
             break;
         case 'get_global_expenses':
              getGlobalExpenses($pdo, $tenant_id, $branch_id);
              break;
           case 'delete_global_expense':
                deleteGlobalExpense($pdo, $tenant_id, $branch_id);
                break;
           case 'edit_global_expense':
                editGlobalExpense($pdo, $tenant_id, $branch_id);
                break;
          case 'get_fund_transactions':
               getFundTransactions($pdo, $tenant_id, $branch_id);
               break;
           case 'delete_fund_transaction':
                deleteFundTransaction($pdo, $tenant_id, $branch_id);
                break;
           case 'edit_fund_transaction':
                editFundTransaction($pdo, $tenant_id, $branch_id);
                break;
          default:
              sendResponse(false, 'Invalid action');
              break;
    }
} else {
    sendResponse(false, 'No action specified');
}

// Create a new global budget allocation
function createGlobalAllocation($pdo, $tenant_id, $branch_id) {
    try {
        // Validate inputs
        $mainAccountId = isset($_POST['main_account_id']) ? intval($_POST['main_account_id']) : 0;
        $amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
        $currency = isset($_POST['currency']) ? $_POST['currency'] : 'USD';
        $date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
        $description = isset($_POST['description']) ? $_POST['description'] : '';

        if ($mainAccountId <= 0 || $amount <= 0) {
            sendResponse(false, 'Invalid input data');
            return;
        }

        // Check if the main account exists for the specified currency
         $accountExistsStmt = $pdo->prepare("SELECT id FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
         $accountExistsStmt->execute([$mainAccountId, $tenant_id, $branch_id]);

        if ($accountExistsStmt->rowCount() === 0) {
            sendResponse(false, 'Main account not found');
            return;
        }

        // Check if the main account has enough balance based on currency
        $balanceColumn = 'usd_balance'; // Default
        if ($currency == 'AFS') {
            $balanceColumn = 'afs_balance';
        } elseif ($currency == 'EUR') {
            $balanceColumn = 'euro_balance';
        } elseif ($currency == 'DARHAM') {
            $balanceColumn = 'darham_balance';
        }

        $accountStmt = $pdo->prepare("SELECT $balanceColumn FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
         $accountStmt->execute([$mainAccountId, $tenant_id, $branch_id]);
        $account = $accountStmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            sendResponse(false, 'Main account not found');
            return;
        }

        if ($account[$balanceColumn] < $amount) {
            sendResponse(false, "Insufficient funds in the main account. Available: " . $account[$balanceColumn]);
            return;
        }

        // Begin transaction
         $pdo->beginTransaction();

         // Deduct from appropriate balance column in main account
         $updateAccountStmt = $pdo->prepare("UPDATE main_account SET $balanceColumn = $balanceColumn - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
         $updateAccountStmt->execute([$amount, $mainAccountId, $tenant_id, $branch_id]);

        // Create global allocation
        $allocationStmt = $pdo->prepare("
            INSERT INTO global_budget_allocations
            (main_account_id, allocated_amount, remaining_amount, currency, allocation_date, description, tenant_id, branch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $allocationStmt->execute([
            $mainAccountId,
            $amount,
            $amount, // Initially, remaining amount equals allocated amount
            $currency,
            $date,
            $description,
            $tenant_id,
            $branch_id
        ]);

        $allocationId = $pdo->lastInsertId();

        // Get updated balance for transaction record
        $balanceStmt = $pdo->prepare("SELECT $balanceColumn FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $balanceStmt->execute([$mainAccountId, $tenant_id, $branch_id]);
        $updatedBalance = $balanceStmt->fetchColumn();

        // Add transaction record
         $transactionStmt = $pdo->prepare("
             INSERT INTO main_account_transactions
             (main_account_id, type, amount, description, balance, currency, transaction_of, reference_id, tenant_id, branch_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ");
         $transactionStmt->execute([
             $mainAccountId,
             'debit',
             $amount,
              $description,
             $updatedBalance,
             $currency,
             'global_budget_allocation',
             $allocationId,
             $tenant_id,
             $branch_id
         ]);

        // Commit transaction
        $pdo->commit();

        // Log the activity
        $old_values = json_encode([]);
        $new_values = json_encode([
            'main_account_id' => $mainAccountId,
            'allocated_amount' => $amount,
            'remaining_amount' => $amount,
            'currency' => $currency,
            'allocation_date' => $date,
            'description' => $description
        ]);

        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $activityStmt = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'add', 'global_budget_allocations', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $activityStmt->execute([$user_id, $allocationId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

        sendResponse(true, 'Global budget allocation created successfully');
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// Delete a global budget allocation
function deleteGlobalAllocation($pdo, $tenant_id, $branch_id) {
    try {
        $allocationId = isset($_POST['allocation_id']) ? intval($_POST['allocation_id']) : 0;

        if ($allocationId <= 0) {
            sendResponse(false, 'Invalid allocation ID');
            return;
        }

        // Get allocation details before deletion
        $allocationStmt = $pdo->prepare("SELECT * FROM global_budget_allocations WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $allocationStmt->execute([$allocationId, $tenant_id, $branch_id]);
         $allocation = $allocationStmt->fetch(PDO::FETCH_ASSOC);

         if (!$allocation) {
             sendResponse(false, 'Allocation not found or access denied');
             return;
         }

        $allocatedAmount = (float) $allocation['allocated_amount'];
        $remainingAmount = (float) $allocation['remaining_amount'];

        if (abs($allocatedAmount - $remainingAmount) > 0.00001) {
            sendResponse(false, 'This allocation has used funds. Delete its related expenses first.');
            return;
        }

        $transactionStmt = $pdo->prepare("
            SELECT id, main_account_id, amount, currency, type, created_at
            FROM main_account_transactions
            WHERE reference_id = ?
            AND transaction_of = 'global_budget_allocation'
            AND tenant_id = ?
            AND branch_id = ?
            ORDER BY created_at ASC, id ASC
        ");
        $transactionStmt->execute([$allocationId, $tenant_id, $branch_id]);
        $allocationTransactions = $transactionStmt->fetchAll(PDO::FETCH_ASSOC);

        // Begin transaction
        $pdo->beginTransaction();

        // Return remaining amount back to main account
        if ($remainingAmount > 0) {
            // Determine which balance column to update based on currency
            $balanceColumn = 'usd_balance'; // Default
            if ($allocation['currency'] == 'AFS') {
                $balanceColumn = 'afs_balance';
            } elseif ($allocation['currency'] == 'EUR') {
                $balanceColumn = 'euro_balance';
            } elseif ($allocation['currency'] == 'DARHAM') {
                $balanceColumn = 'darham_balance';
            }

            $updateAccountStmt = $pdo->prepare("
                 UPDATE main_account
                 SET $balanceColumn = $balanceColumn + ?
                 WHERE id = ? AND tenant_id = ? AND branch_id = ?
             ");
             $updateAccountStmt->execute([
                 $remainingAmount,
                 $allocation['main_account_id'],
                 $tenant_id,
                 $branch_id
             ]);
        }

        foreach ($allocationTransactions as $transaction) {
            $balanceAdjustment = $transaction['type'] === 'debit'
                ? (float) $transaction['amount']
                : -(float) $transaction['amount'];

            $updateSubsequentStmt = $pdo->prepare("
                UPDATE main_account_transactions
                SET balance = balance + ?
                WHERE main_account_id = ?
                AND currency = ?
                AND (
                    created_at > ?
                    OR (created_at = ? AND id > ?)
                )
                AND tenant_id = ?
                AND branch_id = ?
            ");
            $updateSubsequentStmt->execute([
                $balanceAdjustment,
                $transaction['main_account_id'],
                $transaction['currency'],
                $transaction['created_at'],
                $transaction['created_at'],
                $transaction['id'],
                $tenant_id,
                $branch_id
            ]);
        }

        $deleteTransactionStmt = $pdo->prepare("
            DELETE FROM main_account_transactions
            WHERE reference_id = ?
            AND transaction_of = 'global_budget_allocation'
            AND tenant_id = ?
            AND branch_id = ?
        ");
        $deleteTransactionStmt->execute([$allocationId, $tenant_id, $branch_id]);

        // Delete allocation
        $deleteStmt = $pdo->prepare("DELETE FROM global_budget_allocations WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $deleteStmt->execute([$allocationId, $tenant_id, $branch_id]);

        // Commit transaction
        $pdo->commit();

        // Log the activity
        $old_values = json_encode([
            'main_account_id' => $allocation['main_account_id'],
            'allocated_amount' => $allocation['allocated_amount'],
            'remaining_amount' => $allocation['remaining_amount'],
            'currency' => $allocation['currency'],
            'allocation_date' => $allocation['allocation_date'],
            'description' => $allocation['description']
        ]);
        $new_values = json_encode([]);

        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $activityStmt = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'delete', 'global_budget_allocations', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $activityStmt->execute([$user_id, $allocationId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

        sendResponse(true, 'Global budget allocation deleted successfully');
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// Add funds to an existing global allocation
function addFundsGlobal($pdo, $tenant_id, $branch_id) {
    try {
        // Validate inputs
        $allocationId = isset($_POST['allocation_id']) ? intval($_POST['allocation_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $note = isset($_POST['note']) ? $_POST['note'] : '';

        if ($allocationId <= 0 || $amount <= 0) {
            sendResponse(false, 'Invalid input data. Allocation ID and amount must be positive values.');
            return;
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Get current allocation information
        $stmt = $pdo->prepare("
            SELECT ga.*, ma.name as account_name,
                   ma.id as main_account_id,
                   CASE
                       WHEN ga.currency = 'USD' THEN ma.usd_balance
                       WHEN ga.currency = 'EUR' THEN ma.euro_balance
                       WHEN ga.currency = 'AFS' THEN ma.afs_balance
                       WHEN ga.currency = 'DARHAM' THEN ma.darham_balance
                       ELSE 0
                   END as account_balance
            FROM global_budget_allocations ga
            JOIN main_account ma ON ga.main_account_id = ma.id
            WHERE ga.id = ? AND ga.tenant_id = ? AND ga.branch_id = ?
        ");
        $stmt->execute([$allocationId, $tenant_id, $branch_id]);
        $allocation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$allocation) {
            $pdo->rollBack();
            sendResponse(false, 'Allocation not found');
            return;
        }

        $mainAccountId = $allocation['main_account_id'];
        $currency = $allocation['currency'];
        $currentAllocatedAmount = $allocation['allocated_amount'];
        $currentRemainingAmount = $allocation['remaining_amount'];
        $accountBalance = $allocation['account_balance'];

        // Check if account has enough balance
        if ($accountBalance < $amount) {
            $pdo->rollBack();
            sendResponse(false, "Insufficient funds in the main account. Available balance: {$accountBalance} {$currency}");
            return;
        }

        // Update allocation amounts
        $newAllocatedAmount = $currentAllocatedAmount + $amount;
        $newRemainingAmount = $currentRemainingAmount + $amount;

        $updateStmt = $pdo->prepare("
            UPDATE global_budget_allocations
            SET allocated_amount = ?, remaining_amount = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $updateStmt->execute([$newAllocatedAmount, $newRemainingAmount, $allocationId, $tenant_id, $branch_id]);

        if ($updateStmt->rowCount() === 0) {
            $pdo->rollBack();
            sendResponse(false, 'Failed to update allocation');
            return;
        }

        // Determine which balance column to update based on currency
        $balanceColumn = 'usd_balance'; // Default
        if ($currency == 'AFS') {
            $balanceColumn = 'afs_balance';
        } elseif ($currency == 'EUR') {
            $balanceColumn = 'euro_balance';
        } elseif ($currency == 'DARHAM') {
            $balanceColumn = 'darham_balance';
        }

        // Deduct the amount from the appropriate balance column in main account
        $updateAccountStmt = $pdo->prepare("
            UPDATE main_account
            SET $balanceColumn = $balanceColumn - ?
            WHERE id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $updateAccountStmt->execute([$amount, $mainAccountId, $tenant_id, $branch_id]);

        // Get the updated balance
        $balanceStmt = $pdo->prepare("SELECT $balanceColumn FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $balanceStmt->execute([$mainAccountId, $tenant_id, $branch_id]);
        $updatedBalance = $balanceStmt->fetchColumn();

        // Log the transaction in main_account_transactions
        $transactionStmt = $pdo->prepare("
            INSERT INTO main_account_transactions
            (main_account_id, type, amount, description, balance, currency, transaction_of, reference_id, tenant_id, branch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $globalAllocDesc = $allocation['description'] ?? "Additional funding to global budget allocation";
        $finalDesc = $globalAllocDesc . ($note ? " - " . $note : "");
        $transactionStmt->execute([
            $mainAccountId,
            'debit',
            $amount,
            $finalDesc,
            $updatedBalance,
            $currency,
            'global_budget_allocation',
            $allocationId,
            $tenant_id,
            $branch_id
        ]);

        // Log the activity
        $old_values = json_encode([
            'allocated_amount' => $currentAllocatedAmount,
            'remaining_amount' => $currentRemainingAmount
        ]);
        $new_values = json_encode([
            'allocated_amount' => $newAllocatedAmount,
            'remaining_amount' => $newRemainingAmount
        ]);

        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $activityStmt = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'add_funds', 'global_budget_allocations', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $activityStmt->execute([$user_id, $allocationId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

        // Commit transaction
        $pdo->commit();

        sendResponse(true, 'Funds added to global allocation successfully', [
            'allocation_id' => $allocationId,
            'new_allocated_amount' => $newAllocatedAmount,
            'new_remaining_amount' => $newRemainingAmount,
            'currency' => $currency
        ]);
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// Add expense from global allocation
function addExpenseGlobal($pdo, $tenant_id, $branch_id) {
    try {
        // Extract form fields
        $categoryId = isset($_POST['categoryId']) ? intval($_POST['categoryId']) : 0;
        $date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
        $description = isset($_POST['description']) ? $_POST['description'] : '';
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $currency = isset($_POST['currency']) ? $_POST['currency'] : 'USD';

        if ($categoryId <= 0 || $amount <= 0) {
            sendResponse(false, 'Invalid input data');
            return;
        }

        // Find global allocation for the month
        $monthStart = date('Y-m-01', strtotime($date));
        $monthEnd = date('Y-m-t', strtotime($date));
        $globalAllocStmt = $pdo->prepare("SELECT id, remaining_amount, main_account_id FROM global_budget_allocations WHERE currency = ? AND allocation_date BETWEEN ? AND ? AND tenant_id = ? AND branch_id = ? LIMIT 1");
        $globalAllocStmt->execute([$currency, $monthStart, $monthEnd, $tenant_id, $branch_id]);
        $globalAlloc = $globalAllocStmt->fetch(PDO::FETCH_ASSOC);

        if (!$globalAlloc) {
            sendResponse(false, 'No global allocation found for this month and currency');
            return;
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Deduct from global allocation
        $updateGlobalStmt = $pdo->prepare("UPDATE global_budget_allocations SET remaining_amount = remaining_amount - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $updateGlobalStmt->execute([$amount, $globalAlloc['id'], $tenant_id, $branch_id]);

        // Insert expense
        $expenseStmt = $pdo->prepare("INSERT INTO expenses (category_id, date, description, amount, currency, main_account_id, global_allocation_id, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $expenseStmt->execute([$categoryId, $date, $description, $amount, $currency, $globalAlloc['main_account_id'], $globalAlloc['id'], $tenant_id, $branch_id]);
        $expenseId = $pdo->lastInsertId();

        // Create notification for admin
        $categoryStmt = $pdo->prepare("SELECT name FROM expense_categories WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $categoryStmt->execute([$categoryId, $tenant_id, $branch_id]);
        $categoryName = $categoryStmt->fetchColumn();

        $notificationMessage = sprintf(
            "New expense added from global allocation for category %s: Amount %s %.2f - %s",
            $categoryName,
            $currency,
            $amount,
            $description
        );

        $notifStmt = $pdo->prepare("
            INSERT INTO notifications
            (transaction_id, transaction_type, message, status, created_at, tenant_id, branch_id)
            VALUES (?, 'expense', ?, 'Unread', NOW(), ?, ?)
        ");
        $notifStmt->execute([$expenseId, $notificationMessage, $tenant_id, $branch_id]);

        // Commit transaction
        $pdo->commit();

        // Log the activity
        $old_values = json_encode([]);
        $new_values = json_encode([
            'category_id' => $categoryId,
            'date' => $date,
            'description' => $description,
            'amount' => $amount,
            'currency' => $currency,
            'allocation_id' => $globalAlloc['id']
        ]);

        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $activityStmt = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'add', 'expenses', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $activityStmt->execute([$user_id, $expenseId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

        sendResponse(true, 'Expense added successfully from global allocation');
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// Get expenses for a global allocation
function getGlobalExpenses($pdo, $tenant_id, $branch_id) {
    try {
        $allocationId = isset($_POST['allocation_id']) ? intval($_POST['allocation_id']) : 0;

        if ($allocationId <= 0) {
            sendResponse(false, 'Invalid allocation ID');
            return;
        }

        // Get expenses associated with this global allocation
        $expenseStmt = $pdo->prepare("
            SELECT e.*, ec.name as category_name
            FROM expenses e
            LEFT JOIN expense_categories ec ON e.category_id = ec.id
            WHERE e.global_allocation_id = ? AND e.tenant_id = ? AND e.branch_id = ?
            ORDER BY e.date DESC
        ");
        $expenseStmt->execute([$allocationId, $tenant_id, $branch_id]);
        $expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);

        sendResponse(true, 'Global expenses retrieved successfully', ['expenses' => $expenses]);
    } catch (PDOException $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// Delete expense from global allocation
function deleteGlobalExpense($pdo, $tenant_id, $branch_id) {
    try {
        $expenseId = isset($_POST['expense_id']) ? intval($_POST['expense_id']) : 0;

        if ($expenseId <= 0) {
            sendResponse(false, 'Invalid expense ID');
            return;
        }

        // Get expense details
        $expenseStmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $expenseStmt->execute([$expenseId, $tenant_id, $branch_id]);
        $expense = $expenseStmt->fetch(PDO::FETCH_ASSOC);

        if (!$expense) {
            sendResponse(false, 'Expense not found or access denied');
            return;
        }

        $allocationId = $expense['global_allocation_id'];
        $amount = $expense['amount'];

        // Begin transaction
        $pdo->beginTransaction();

        // Return amount to global allocation
        $updateStmt = $pdo->prepare("UPDATE global_budget_allocations SET remaining_amount = remaining_amount + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $updateStmt->execute([$amount, $allocationId, $tenant_id, $branch_id]);

        // Delete notification
        $delNotifStmt = $pdo->prepare("DELETE FROM notifications WHERE transaction_id = ? AND transaction_type = 'expense' AND tenant_id = ? AND branch_id = ?");
        $delNotifStmt->execute([$expenseId, $tenant_id, $branch_id]);

        // Delete expense
        $deleteStmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $deleteStmt->execute([$expenseId, $tenant_id, $branch_id]);

        // Commit
        $pdo->commit();

        // Log activity
        $old_values = json_encode([
            'category_id' => $expense['category_id'],
            'date' => $expense['date'],
            'description' => $expense['description'],
            'amount' => $expense['amount'],
            'currency' => $expense['currency'],
            'allocation_id' => $allocationId
        ]);
        $new_values = json_encode([]);

        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $activityStmt = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'delete', 'expenses', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $activityStmt->execute([$user_id, $expenseId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

        sendResponse(true, 'Expense deleted successfully');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// Edit expense from global allocation
function editGlobalExpense($pdo, $tenant_id, $branch_id) {
    try {
        $expenseId   = isset($_POST['expense_id'])   ? intval($_POST['expense_id'])   : 0;
        $categoryId  = isset($_POST['category_id'])  ? intval($_POST['category_id'])  : 0;
        $date        = isset($_POST['date'])          ? $_POST['date']                  : '';
        $description = isset($_POST['description'])   ? $_POST['description']           : '';
        $amount      = isset($_POST['amount'])         ? floatval($_POST['amount'])      : 0;

        if ($expenseId <= 0 || $categoryId <= 0 || $amount <= 0) {
            sendResponse(false, 'Invalid input data');
            return;
        }

        // Get old expense
        $prevStmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $prevStmt->execute([$expenseId, $tenant_id, $branch_id]);
        $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);

        if (!$prev) {
            sendResponse(false, 'Expense not found');
            return;
        }

        $allocationId = $prev['global_allocation_id'];
        $oldAmount = (float) $prev['amount'];
        $diff = $amount - $oldAmount;

        $pdo->beginTransaction();

        // Adjust allocation remaining_amount
        $updateAllocStmt = $pdo->prepare("
            UPDATE global_budget_allocations
            SET remaining_amount = remaining_amount - ?
            WHERE id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $updateAllocStmt->execute([$diff, $allocationId, $tenant_id, $branch_id]);

        // Update expense
        $updateStmt = $pdo->prepare("
            UPDATE expenses
            SET category_id = ?, date = ?, description = ?, amount = ?
            WHERE id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $updateStmt->execute([$categoryId, $date, $description, $amount, $expenseId, $tenant_id, $branch_id]);

        $pdo->commit();

        // Activity log
        $old_values = json_encode($prev);
        $new_values = json_encode([
            'category_id' => $categoryId,
            'date' => $date,
            'description' => $description,
            'amount' => $amount
        ]);
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $activityStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id) VALUES (?, 'update', 'expenses', ?, ?, ?, ?, ?, NOW(), ?, ?)");
        $activityStmt->execute([$user_id, $expenseId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

        sendResponse(true, 'Expense updated successfully');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// Delete a fund transaction for global allocation
function deleteFundTransaction($pdo, $tenant_id, $branch_id) {
    try {
        $transactionId = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
        $allocationId = isset($_POST['allocation_id']) ? intval($_POST['allocation_id']) : 0;

        if ($transactionId <= 0 || $allocationId <= 0) {
            sendResponse(false, 'Invalid transaction ID or allocation ID');
            return;
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Get transaction details
        $transactionStmt = $pdo->prepare("
            SELECT * FROM main_account_transactions
            WHERE id = ? AND transaction_of = 'global_budget_allocation' AND reference_id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $transactionStmt->execute([$transactionId, $allocationId, $tenant_id, $branch_id]);
        $transaction = $transactionStmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            $pdo->rollBack();
            sendResponse(false, 'Transaction not found or does not belong to this allocation');
            return;
        }

        // Get allocation details
        $allocationStmt = $pdo->prepare("
            SELECT ga.*, ma.id as main_account_id
            FROM global_budget_allocations ga
            JOIN main_account ma ON ga.main_account_id = ma.id
            WHERE ga.id = ? AND ga.tenant_id = ? AND ga.branch_id = ?
        ");
        $allocationStmt->execute([$allocationId, $tenant_id, $branch_id]);
        $allocation = $allocationStmt->fetch(PDO::FETCH_ASSOC);

        if (!$allocation) {
            $pdo->rollBack();
            sendResponse(false, 'Allocation not found or access denied');
            return;
        }

        $mainAccountId = $transaction['main_account_id'];
        $amount = $transaction['amount'];
        $currency = $transaction['currency'];
        $type = $transaction['type']; // 'debit' or 'credit'

        // Determine which balance column to update based on currency
        $balanceColumn = 'usd_balance'; // Default
        if ($currency == 'AFS') {
            $balanceColumn = 'afs_balance';
        } elseif ($currency == 'EUR') {
            $balanceColumn = 'euro_balance';
        } elseif ($currency == 'DARHAM') {
            $balanceColumn = 'darham_balance';
        }

        // Update main account balance (reverse the transaction)
        if ($type === 'debit') {
            // If the transaction was a debit (money taken from account), return the money
            $updateAccountStmt = $pdo->prepare("
                UPDATE main_account
                SET $balanceColumn = $balanceColumn + ?
                WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ");
        } else {
            // If the transaction was a credit (money added to account), remove the money
            $updateAccountStmt = $pdo->prepare("
                UPDATE main_account
                SET $balanceColumn = $balanceColumn - ?
                WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ");
        }
        $updateAccountStmt->execute([$amount, $mainAccountId, $tenant_id, $branch_id]);

        // Update allocation amounts
        if ($type === 'debit') {
            // If the transaction was a debit, reduce the allocation amounts
            $updateAllocationStmt = $pdo->prepare("
                UPDATE global_budget_allocations
                SET allocated_amount = allocated_amount - ?,
                    remaining_amount = remaining_amount - ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ");
        } else {
            // If the transaction was a credit, increase the allocation amounts
            $updateAllocationStmt = $pdo->prepare("
                UPDATE global_budget_allocations
                SET allocated_amount = allocated_amount + ?,
                    remaining_amount = remaining_amount + ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ");
        }
        $updateAllocationStmt->execute([$amount, $amount, $allocationId, $tenant_id, $branch_id]);

        // Delete the transaction
        $deleteStmt = $pdo->prepare("DELETE FROM main_account_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $deleteStmt->execute([$transactionId, $tenant_id, $branch_id]);

        // Update balance of all subsequent transactions
        if ($type === 'debit') {
            // For a debit transaction being deleted, increase all subsequent transaction balances
            $updateSubsequentStmt = $pdo->prepare("
                UPDATE main_account_transactions
                SET balance = balance + ?
                WHERE main_account_id = ?
                AND currency = ?
                AND created_at > ?
                AND tenant_id = ?
                AND branch_id = ?
            ");
            $updateSubsequentStmt->execute([
                $amount,
                $mainAccountId,
                $currency,
                $transaction['created_at'],
                $tenant_id,
                $branch_id
            ]);
        } else {
            // For a credit transaction being deleted, decrease all subsequent transaction balances
            $updateSubsequentStmt = $pdo->prepare("
                UPDATE main_account_transactions
                SET balance = balance - ?
                WHERE main_account_id = ?
                AND currency = ?
                AND created_at > ?
                AND tenant_id = ?
                AND branch_id = ?
            ");
            $updateSubsequentStmt->execute([
                $amount,
                $mainAccountId,
                $currency,
                $transaction['created_at'],
                $tenant_id,
                $branch_id
            ]);
        }

        // Log the activity
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $old_values = json_encode($transaction);
        $new_values = json_encode([]);

        $activityStmt = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'delete', 'main_account_transactions', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $activityStmt->execute([$user_id, $transactionId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

        // Commit transaction
        $pdo->commit();

        sendResponse(true, 'Fund transaction deleted successfully');
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// Edit a fund transaction for global allocation
function editFundTransaction($pdo, $tenant_id, $branch_id) {
    try {
        $transactionId = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
        $allocationId = isset($_POST['allocation_id']) ? intval($_POST['allocation_id']) : 0;
        $newAmount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $newDescription = isset($_POST['description']) ? $_POST['description'] : '';

        if ($transactionId <= 0 || $allocationId <= 0 || $newAmount <= 0) {
            sendResponse(false, 'Invalid input data');
            return;
        }

        $pdo->beginTransaction();

        // Get old transaction
        $txStmt = $pdo->prepare("
            SELECT * FROM main_account_transactions
            WHERE id = ? AND transaction_of = 'global_budget_allocation' AND reference_id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $txStmt->execute([$transactionId, $allocationId, $tenant_id, $branch_id]);
        $transaction = $txStmt->fetch(PDO::FETCH_ASSOC);

        if (!$transaction) {
            $pdo->rollBack();
            sendResponse(false, 'Transaction not found');
            return;
        }

        // Get allocation
        $allocStmt = $pdo->prepare("
            SELECT ga.*, ma.id as main_account_id
            FROM global_budget_allocations ga
            JOIN main_account ma ON ga.main_account_id = ma.id
            WHERE ga.id = ? AND ga.tenant_id = ? AND ga.branch_id = ?
        ");
        $allocStmt->execute([$allocationId, $tenant_id, $branch_id]);
        $allocation = $allocStmt->fetch(PDO::FETCH_ASSOC);

        if (!$allocation) {
            $pdo->rollBack();
            sendResponse(false, 'Allocation not found');
            return;
        }

        $mainAccountId = $transaction['main_account_id'];
        $currency = $transaction['currency'];
        $oldAmount = (float) $transaction['amount'];
        $oldBalance = (float) $transaction['balance'];
        $type = $transaction['type'];
        $diff = $newAmount - $oldAmount;

        // Determine balance column
        $balanceColumn = 'usd_balance';
        if ($currency == 'AFS') $balanceColumn = 'afs_balance';
        elseif ($currency == 'EUR') $balanceColumn = 'euro_balance';
        elseif ($currency == 'DARHAM') $balanceColumn = 'darham_balance';

        if ($type === 'debit') {
            // Net main account change: -diff (more debit = lower balance)
            $updateAccountStmt = $pdo->prepare("
                UPDATE main_account SET $balanceColumn = $balanceColumn - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ");
            $updateAccountStmt->execute([$diff, $mainAccountId, $tenant_id, $branch_id]);

            // Allocation amounts change by +diff
            $updateAllocStmt = $pdo->prepare("
                UPDATE global_budget_allocations
                SET allocated_amount = allocated_amount + ?,
                    remaining_amount = remaining_amount + ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ");
            $updateAllocStmt->execute([$diff, $diff, $allocationId, $tenant_id, $branch_id]);
        } else {
            $updateAccountStmt = $pdo->prepare("
                UPDATE main_account SET $balanceColumn = $balanceColumn + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ");
            $updateAccountStmt->execute([$diff, $mainAccountId, $tenant_id, $branch_id]);

            $updateAllocStmt = $pdo->prepare("
                UPDATE global_budget_allocations
                SET allocated_amount = allocated_amount - ?,
                    remaining_amount = remaining_amount - ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ");
            $updateAllocStmt->execute([$diff, $diff, $allocationId, $tenant_id, $branch_id]);
        }

        // Calculate new balance for this transaction
        $newBalance = $type === 'debit' ? $oldBalance - $diff : $oldBalance + $diff;

        // Update the transaction
        $updateTxStmt = $pdo->prepare("
            UPDATE main_account_transactions
            SET amount = ?, description = ?, balance = ?
            WHERE id = ? AND tenant_id = ? AND branch_id = ?
        ");
        $updateTxStmt->execute([$newAmount, $newDescription, $newBalance, $transactionId, $tenant_id, $branch_id]);

        // Update subsequent transaction balances
        $balanceAdjustment = $type === 'debit' ? -$diff : $diff;
        $updateSubseqStmt = $pdo->prepare("
            UPDATE main_account_transactions
            SET balance = balance + ?
            WHERE main_account_id = ? AND currency = ? AND id > ? AND tenant_id = ? AND branch_id = ?
        ");
        $updateSubseqStmt->execute([$balanceAdjustment, $mainAccountId, $currency, $transactionId, $tenant_id, $branch_id]);

        // Log activity
        $old_values = json_encode($transaction);
        $new_values = json_encode([
            'amount' => $newAmount,
            'description' => $newDescription,
            'balance' => $newBalance
        ]);

        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $activityStmt = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'edit', 'main_account_transactions', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $activityStmt->execute([$user_id, $transactionId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

        $pdo->commit();

        sendResponse(true, 'Fund transaction updated successfully');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// Get fund transactions for a global allocation
function getFundTransactions($pdo, $tenant_id, $branch_id) {
    try {
        $allocationId = isset($_POST['allocation_id']) ? intval($_POST['allocation_id']) : 0;

        if ($allocationId <= 0) {
            sendResponse(false, 'Invalid allocation ID');
            return;
        }

        // Get allocation details
        $allocationStmt = $pdo->prepare("
            SELECT ga.*, ma.name as account_name
            FROM global_budget_allocations ga
            JOIN main_account ma ON ga.main_account_id = ma.id
            WHERE ga.id = ? AND ga.tenant_id = ? AND ga.branch_id = ?
        ");
        $allocationStmt->execute([$allocationId, $tenant_id, $branch_id]);
        $allocation = $allocationStmt->fetch(PDO::FETCH_ASSOC);

        if (!$allocation) {
            sendResponse(false, 'Allocation not found or access denied');
            return;
        }

        // Get fund transactions
        $transactionStmt = $pdo->prepare("
            SELECT mat.*, ma.name as account_name
            FROM main_account_transactions mat
            JOIN main_account ma ON mat.main_account_id = ma.id
            WHERE mat.transaction_of = 'global_budget_allocation' AND mat.reference_id = ? AND mat.tenant_id = ? AND mat.branch_id = ?
            ORDER BY mat.created_at DESC
        ");
        $transactionStmt->execute([$allocationId, $tenant_id, $branch_id]);
        $transactions = $transactionStmt->fetchAll(PDO::FETCH_ASSOC);

        sendResponse(true, 'Fund transactions retrieved successfully', [
            'allocation' => $allocation,
            'transactions' => $transactions
        ]);
    } catch (PDOException $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// Helper function to send JSON response
function sendResponse($success, $message, $data = []) {
    $response = [
        'success' => $success,
        'message' => $message
    ];

    if (!empty($data)) {
        $response = array_merge($response, $data);
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
