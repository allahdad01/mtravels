<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database security module for input validation
require_once '../../admin/includes/db_security.php';

// Include security module
require_once '../../admin/security.php';

// Enforce authentication
enforce_auth();

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Database connection
require_once('../../includes/db.php');

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

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

// Validate category_id
$category_id = isset($_POST['category_id']) ? DbSecurity::validateInput($_POST['category_id'], 'int', ['min' => 0]) : null;

// Validate main_account_id
$main_account_id = isset($_POST['main_account_id']) ? DbSecurity::validateInput($_POST['main_account_id'], 'int', ['min' => 0]) : null;

// Validate action
$action = isset($_POST['action']) ? DbSecurity::validateInput($_POST['action'], 'string', ['maxlength' => 255]) : null;

// Handle different actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'create_allocation':
            createAllocation($pdo);
            break;
        case 'update_allocation':
            updateAllocation($pdo);
            break;
        case 'delete_allocation':
            deleteAllocation($pdo);
            break;
        case 'get_allocations':
            getAllocations($pdo);
            break;
        case 'get_allocation_details':
            getAllocationDetails($pdo);
            break;
        case 'add_funds':
            addFunds($pdo);
            break;
        case 'get_fund_transactions':
            getFundTransactions($pdo);
            break;
        case 'delete_fund_transaction':
            deleteFundTransaction($pdo);
            break;
        case 'update_fund_transaction':
            updateFundTransaction($pdo);
            break;
        case 'filter_allocations_by_month':
            filterAllocationsByMonth($pdo);
            break;
        case 'add_allocation_expense':
            addAllocationExpense($pdo);
            break;
        case 'add_auto_allocation_expense':
            addAutoAllocationExpense($pdo);
            break;
        case 'update_allocation_expense':
            updateAllocationExpense($pdo);
            break;
        case 'delete_allocation_expense':
            deleteAllocationExpense($pdo);
            break;
        default:
            sendResponse(false, 'Invalid action');
            break;
    }
} else {
    sendResponse(false, 'No action specified');
}

// Create a new budget allocation
function createAllocation($pdo) {
    $tenant_id = $_SESSION['tenant_id'];
    $branch_id = $_SESSION['branch_id'];
    try {
        // Validate inputs
        $mainAccountId = isset($_POST['main_account_id']) ? intval($_POST['main_account_id']) : 0;
        $categoryId = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $subCategoryId = isset($_POST['sub_category_id']) ? intval($_POST['sub_category_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $currency = isset($_POST['currency']) ? $_POST['currency'] : 'USD';
        $date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
        $description = isset($_POST['description']) ? $_POST['description'] : '';

        if ($mainAccountId <= 0 || $categoryId <= 0 || $amount <= 0) {
            sendResponse(false, 'Invalid input data');
            return;
        }

        // Validate sub-category belongs to the selected category
        if ($subCategoryId > 0) {
            $subCatStmt = $pdo->prepare("SELECT id FROM expense_categories WHERE id = ? AND parent_id = ? AND tenant_id = ? AND branch_id = ?");
            $subCatStmt->execute([$subCategoryId, $categoryId, $tenant_id, $branch_id]);
            if ($subCatStmt->rowCount() === 0) {
                sendResponse(false, 'Invalid sub-category for the selected category');
                return;
            }
        } else {
            $subCategoryId = null;
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
        } elseif ($currency == 'SAR') {
            $balanceColumn = 'sar_balance';
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

        // Create allocation
         $allocationStmt = $pdo->prepare("
             INSERT INTO budget_allocations
             (main_account_id, category_id, sub_category_id, allocated_amount, remaining_amount, currency, allocation_date, description, created_by, tenant_id, branch_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ");
         $allocationStmt->execute([
             $mainAccountId,
             $categoryId,
             $subCategoryId,
             $amount,
             $amount, // Initially, remaining amount equals allocated amount
             $currency,
             $date,
             $description,
             $_SESSION['user_id'],
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
            (main_account_id, type, amount, description, balance, currency, transaction_of, reference_id, tenant_id, branch_id, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $transactionStmt->execute([
            $mainAccountId,
            'debit',
            $amount,
            $description,
            
            $updatedBalance,
            $currency,
            'budget_allocation',
            $allocationId,
            $tenant_id,
            $branch_id,
            $_SESSION['user_id'] ?? null
        ]);

        // Commit transaction
        $pdo->commit();

        // Log the activity
        $old_values = json_encode([]);
        $new_values = json_encode([
            'main_account_id' => $mainAccountId,
            'category_id' => $categoryId,
            'sub_category_id' => $subCategoryId,
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
            VALUES (?, 'add', 'budget_allocations', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $activityStmt->execute([$user_id, $allocationId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

        sendResponse(true, 'Budget allocation created successfully');
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// Update an existing budget allocation
function updateAllocation($pdo) {
    $tenant_id = $_SESSION['tenant_id'];
    try {
        $allocationId = isset($_POST['allocation_id']) ? intval($_POST['allocation_id']) : 0;
        $description = isset($_POST['description']) ? $_POST['description'] : '';
        
        if ($allocationId <= 0) {
            sendResponse(false, 'Invalid allocation ID');
            return;
        }

        // Only allow updating the description
        $updateStmt = $pdo->prepare("UPDATE budget_allocations SET description = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $updateStmt->execute([$description, $allocationId, $tenant_id, $branch_id]);

        if ($updateStmt->rowCount() === 0) {
            sendResponse(false, 'Allocation not found or no changes made');
            return;
        }

        // Log the activity
        // Get the current allocation data for old_values
        $stmt = $pdo->prepare("SELECT * FROM budget_allocations WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$allocationId, $tenant_id, $branch_id]);
        $allocation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $old_values = json_encode(['description' => $allocation['description'] ?? '']);
        $new_values = json_encode(['description' => $description]);
        
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $activityStmt = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, 'update', 'budget_allocations', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $activityStmt->execute([$user_id, $allocationId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

        sendResponse(true, 'Budget allocation updated successfully');
    } catch (PDOException $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// Delete a budget allocation
function deleteAllocation($pdo) {
    $tenant_id = $_SESSION['tenant_id'];
    try {
        $allocationId = isset($_POST['allocation_id']) ? intval($_POST['allocation_id']) : 0;
        
        if ($allocationId <= 0) {
            sendResponse(false, 'Invalid allocation ID');
            return;
        }

        // Check if there are any expenses associated with this allocation
        $expenseStmt = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE allocation_id = ? AND tenant_id = ? AND branch_id = ?");
        $expenseStmt->execute([$allocationId, $tenant_id, $branch_id]);
        $expenseCount = $expenseStmt->fetchColumn();

        if ($expenseCount > 0) {
            sendResponse(false, 'Cannot delete allocation with associated expenses');
            return;
        }

        // Get allocation details before deletion
        $allocationStmt = $pdo->prepare("SELECT * FROM budget_allocations WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $allocationStmt->execute([$allocationId, $tenant_id, $branch_id]);
        $allocation = $allocationStmt->fetch(PDO::FETCH_ASSOC);

        if (!$allocation) {
            sendResponse(false, 'Allocation not found');
            return;
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Return remaining amount back to main account
        if ($allocation['remaining_amount'] > 0) {
            // Determine which balance column to update based on currency
            $balanceColumn = 'usd_balance'; // Default
            if ($allocation['currency'] == 'AFS') {
                $balanceColumn = 'afs_balance';
            } elseif ($allocation['currency'] == 'EUR') {
                $balanceColumn = 'euro_balance';
            } elseif ($allocation['currency'] == 'DARHAM') {
                $balanceColumn = 'darham_balance';
            } elseif ($allocation['currency'] == 'SAR') {
                $balanceColumn = 'sar_balance';
            }
            
            $updateAccountStmt = $pdo->prepare("
                UPDATE main_account
                SET $balanceColumn = $balanceColumn + ?
                WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ");
            $updateAccountStmt->execute([
                $allocation['remaining_amount'],
                $allocation['main_account_id'],
                $tenant_id,
                $branch_id
            ]);
            
            
                // Get transaction details to find created_at timestamp
                $getTxnStmt = $pdo->prepare("SELECT created_at FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'budget_allocation' AND tenant_id = ? AND branch_id = ?");
                $getTxnStmt->execute([$allocationId, $tenant_id, $branch_id]);
                $transaction = $getTxnStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($transaction) {
                    // Update balances of all subsequent transactions
                    $updateSubsequentStmt = $pdo->prepare("
                        UPDATE main_account_transactions
                        SET balance = balance + ?
                        WHERE main_account_id = ?
                        AND currency = ?
                        AND created_at > ?
                        AND reference_id != ?
                        AND tenant_id = ? AND branch_id = ?
                    ");
                    $updateSubsequentStmt->execute([
                        $allocation['remaining_amount'],
                        $allocation['main_account_id'],
                        $allocation['currency'],
                        $transaction['created_at'],
                        $allocationId,
                        $tenant_id,
                        $branch_id
                    ]);
                }
            // Get updated balance for transaction record
            $balanceStmt = $pdo->prepare("SELECT $balanceColumn FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $balanceStmt->execute([$allocation['main_account_id'], $tenant_id, $branch_id]);
            $updatedBalance = $balanceStmt->fetchColumn();

            // Delete the associated transaction
            $deleteStmt = $pdo->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'budget_allocation' AND tenant_id = ? AND branch_id = ?");
            $deleteStmt->execute([$allocationId, $tenant_id, $branch_id]);
            
           
        }

        // Delete allocation
        $deleteStmt = $pdo->prepare("DELETE FROM budget_allocations WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $deleteStmt->execute([$allocationId, $tenant_id, $branch_id]);


        // Commit transaction
        $pdo->commit();

        // Log the activity
        $old_values = json_encode([
            'main_account_id' => $allocation['main_account_id'],
            'category_id' => $allocation['category_id'],
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
            VALUES (?, 'delete', 'budget_allocations', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $activityStmt->execute([$user_id, $allocationId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

        sendResponse(true, 'Budget allocation deleted successfully');
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// Get all allocations for a specific category
function getAllocations($pdo) {
    try {
        $tenant_id = $_SESSION['tenant_id'];
        $branch_id = $_SESSION['branch_id'];
        $categoryId = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $currency = isset($_POST['currency']) ? $_POST['currency'] : null;
        
        $query = "
            SELECT ba.*, ma.name as account_name, ec.name as category_name, esc.name as sub_category_name
            FROM budget_allocations ba
            JOIN main_account ma ON ba.main_account_id = ma.id
            JOIN expense_categories ec ON ba.category_id = ec.id
            LEFT JOIN expense_categories esc ON ba.sub_category_id = esc.id
            WHERE ba.tenant_id = ? AND ba.branch_id = ?
        ";
        $params = [$tenant_id, $branch_id];

        if ($categoryId > 0) {
            $query .= " AND ba.category_id = ?";
            $params[] = $categoryId;
        }

        if ($currency) {
            $query .= " AND ba.currency = ?";
            $params[] = $currency;
        }

        $query .= " ORDER BY ba.allocation_date DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(true, 'Allocations retrieved successfully', ['allocations' => $allocations]);
    } catch (PDOException $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// Get details for a specific allocation
function getAllocationDetails($pdo) {
    try {
        $tenant_id = $_SESSION['tenant_id'];
        $branch_id = $_SESSION['branch_id'];
        $allocationId = isset($_POST['allocation_id']) ? intval($_POST['allocation_id']) : 0;
        
        if ($allocationId <= 0) {
            sendResponse(false, 'Invalid allocation ID');
            return;
        }
        
        // Get allocation details
        $allocationStmt = $pdo->prepare("
            SELECT ba.*, ma.name as account_name, ec.name as category_name, esc.name as sub_category_name
            FROM budget_allocations ba
            JOIN main_account ma ON ba.main_account_id = ma.id
            JOIN expense_categories ec ON ba.category_id = ec.id
            LEFT JOIN expense_categories esc ON ba.sub_category_id = esc.id
            WHERE ba.id = ? AND ba.tenant_id = ? AND ba.branch_id = ?
        ");
        $allocationStmt->execute([$allocationId, $tenant_id, $branch_id]);
        $allocation = $allocationStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$allocation) {
            sendResponse(false, 'Allocation not found');
            return;
        }
        
        // Get expenses associated with this allocation
        $expenseStmt = $pdo->prepare("
            SELECT e.*, esc.name as sub_category_name
            FROM expenses e
            LEFT JOIN expense_categories esc ON e.sub_category_id = esc.id
            WHERE e.allocation_id = ? AND e.tenant_id = ? AND e.branch_id = ?
            ORDER BY e.date DESC
        ");
        $expenseStmt->execute([$allocationId, $tenant_id, $branch_id]);
        $expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(true, 'Allocation details retrieved successfully', [
            'allocation' => $allocation,
            'expenses' => $expenses
        ]);
    } catch (PDOException $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// Helper function to get category name
function getCategoryName($pdo, $categoryId) {
    $tenant_id = $_SESSION['tenant_id'];
    $branch_id = $_SESSION['branch_id'];
    $stmt = $pdo->prepare("SELECT name FROM expense_categories WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$categoryId, $tenant_id, $branch_id]);
    return $stmt->fetchColumn() ?: 'Unknown Category';
}

// Add funds to an existing allocation
function addFunds($pdo) {
    $tenant_id = $_SESSION['tenant_id'];
    $branch_id = $_SESSION['branch_id'];
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
            SELECT ba.*, ma.name as account_name, ec.name as category_name,
                   ma.id as main_account_id,
                   CASE
                       WHEN ba.currency = 'USD' THEN ma.usd_balance
                       WHEN ba.currency = 'EUR' THEN ma.euro_balance
                       WHEN ba.currency = 'AFS' THEN ma.afs_balance
                       WHEN ba.currency = 'DARHAM' THEN ma.darham_balance
                       WHEN ba.currency = 'SAR' THEN ma.sar_balance
                       ELSE 0
                   END as account_balance
            FROM budget_allocations ba
            JOIN main_account ma ON ba.main_account_id = ma.id
            JOIN expense_categories ec ON ba.category_id = ec.id
            WHERE ba.id = ? AND ba.tenant_id = ? AND ba.branch_id = ?
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
            UPDATE budget_allocations
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
        } elseif ($currency == 'SAR') {
            $balanceColumn = 'sar_balance';
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
            (main_account_id, type, amount, description, balance, currency, transaction_of, reference_id, tenant_id, branch_id, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $allocDesc = $allocation['description'] ?: $allocation['category_name'];
        $fundDesc = $allocDesc . ($note ? " - " . $note : "");
        $transactionStmt->execute([
            $mainAccountId,
            'debit',
            $amount,
            $fundDesc,
            $updatedBalance,
            $currency,
            'budget_allocation',
            $allocationId,
            $tenant_id,
            $branch_id,
            $_SESSION['user_id'] ?? null
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
            VALUES (?, 'add_funds', 'budget_allocations', ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $activityStmt->execute([$user_id, $allocationId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

        // Commit transaction
        $pdo->commit();

        sendResponse(true, 'Funds added to allocation successfully', [
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

// Get fund transactions for an allocation
function getFundTransactions($pdo) {
    $tenant_id = $_SESSION['tenant_id'];
    $branch_id = $_SESSION['branch_id'];
    try {
        $allocationId = isset($_POST['allocation_id']) ? intval($_POST['allocation_id']) : 0;
        
        if ($allocationId <= 0) {
            sendResponse(false, 'Invalid allocation ID');
            return;
        }
        
        // Get allocation details
        $allocationStmt = $pdo->prepare("
            SELECT ba.*, ma.name as account_name, ec.name as category_name, esc.name as sub_category_name
            FROM budget_allocations ba
            JOIN main_account ma ON ba.main_account_id = ma.id
            JOIN expense_categories ec ON ba.category_id = ec.id
            LEFT JOIN expense_categories esc ON ba.sub_category_id = esc.id
            WHERE ba.id = ? AND ba.tenant_id = ? AND ba.branch_id = ?
        ");
        $allocationStmt->execute([$allocationId, $tenant_id, $branch_id]);
        $allocation = $allocationStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$allocation) {
            sendResponse(false, 'Allocation not found');
            return;
        }
        
        // Get transactions associated with this allocation
        $transactionStmt = $pdo->prepare("
            SELECT * FROM main_account_transactions
            WHERE transaction_of = 'budget_allocation' AND reference_id = ? AND tenant_id = ? AND branch_id = ?
            ORDER BY created_at DESC
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

// Delete a fund transaction
function deleteFundTransaction($pdo) {
    $tenant_id = $_SESSION['tenant_id'];
    $branch_id = $_SESSION['branch_id'];
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
            WHERE id = ? AND transaction_of = 'budget_allocation' AND reference_id = ? AND tenant_id = ? AND branch_id = ?
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
            SELECT ba.*, ma.id as main_account_id
            FROM budget_allocations ba
            JOIN main_account ma ON ba.main_account_id = ma.id
            WHERE ba.id = ? AND ba.tenant_id = ? AND ba.branch_id = ?
        ");
        $allocationStmt->execute([$allocationId, $tenant_id, $branch_id]);
        $allocation = $allocationStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$allocation) {
            $pdo->rollBack();
            sendResponse(false, 'Allocation not found');
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
        } elseif ($currency == 'SAR') {
            $balanceColumn = 'sar_balance';
        }
        
        // Update main account balance (reverse the transaction)
        if ($type === 'debit') {
            // If the transaction was a debit (money taken from account), return the money
            $sql = "UPDATE main_account SET `{$balanceColumn}` = `{$balanceColumn}` + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $updateAccountStmt = $pdo->prepare($sql);
            $updateAccountStmt->execute([$amount, $mainAccountId, $tenant_id, $branch_id]);
        } else {
            // If the transaction was a credit (money added to account), remove the money
            $sql = "UPDATE main_account SET `{$balanceColumn}` = `{$balanceColumn}` - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $updateAccountStmt = $pdo->prepare($sql);
            $updateAccountStmt->execute([$amount, $mainAccountId, $tenant_id, $branch_id]);
        }
        
        // Update allocation amounts
        if ($type === 'debit') {
            // If the transaction was a debit, reduce the allocation amounts
            $updateAllocationStmt = $pdo->prepare("
                UPDATE budget_allocations
                SET allocated_amount = allocated_amount - ?,
                    remaining_amount = remaining_amount - ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ");
            $updateAllocationStmt->execute([$amount, $amount, $allocationId, $tenant_id, $branch_id]);
        } else {
            // If the transaction was a credit, increase the allocation amounts
            $updateAllocationStmt = $pdo->prepare("
                UPDATE budget_allocations
                SET allocated_amount = allocated_amount + ?,
                    remaining_amount = remaining_amount + ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ");
            $updateAllocationStmt->execute([$amount, $amount, $allocationId, $tenant_id, $branch_id]);
        }
        
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
                AND tenant_id = ? AND branch_id = ?
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
                AND tenant_id = ? AND branch_id = ?
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

// Update a fund transaction
function updateFundTransaction($pdo) {
    $tenant_id = $_SESSION['tenant_id'];
    $branch_id = $_SESSION['branch_id'];
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
            WHERE id = ? AND transaction_of = 'budget_allocation' AND reference_id = ? AND tenant_id = ? AND branch_id = ?
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
            SELECT ba.*, ma.id as main_account_id
            FROM budget_allocations ba
            JOIN main_account ma ON ba.main_account_id = ma.id
            WHERE ba.id = ? AND ba.tenant_id = ? AND ba.branch_id = ?
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
        elseif ($currency == 'SAR') $balanceColumn = 'sar_balance';

        if ($type === 'debit') {
            $colQ = "`{$balanceColumn}`";
            $pdo->prepare("UPDATE main_account SET {$colQ} = {$colQ} - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                ->execute([$diff, $mainAccountId, $tenant_id, $branch_id]);

            $pdo->prepare("
                UPDATE budget_allocations
                SET allocated_amount = allocated_amount + ?,
                    remaining_amount = remaining_amount + ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ")->execute([$diff, $diff, $allocationId, $tenant_id, $branch_id]);
        } else {
            $colQ = "`{$balanceColumn}`";
            $pdo->prepare("UPDATE main_account SET {$colQ} = {$colQ} + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?")
                ->execute([$diff, $mainAccountId, $tenant_id, $branch_id]);

            $pdo->prepare("
                UPDATE budget_allocations
                SET allocated_amount = allocated_amount - ?,
                    remaining_amount = remaining_amount - ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ? AND branch_id = ?
            ")->execute([$diff, $diff, $allocationId, $tenant_id, $branch_id]);
        }

        // Calculate new balance
        $newBalance = $type === 'debit' ? $oldBalance - $diff : $oldBalance + $diff;

        // Update transaction
        $pdo->prepare("
            UPDATE main_account_transactions
            SET amount = ?, description = ?, balance = ?
            WHERE id = ? AND tenant_id = ? AND branch_id = ?
        ")->execute([$newAmount, $newDescription, $newBalance, $transactionId, $tenant_id, $branch_id]);

        // Update subsequent balances
        $balanceAdjustment = $type === 'debit' ? -$diff : $diff;
        $pdo->prepare("
            UPDATE main_account_transactions
            SET balance = balance + ?
            WHERE main_account_id = ? AND currency = ? AND id > ? AND tenant_id = ? AND branch_id = ?
        ")->execute([$balanceAdjustment, $mainAccountId, $currency, $transactionId, $tenant_id, $branch_id]);

        // Log activity
        $old_values = json_encode($transaction);
        $new_values = json_encode(['amount' => $newAmount, 'description' => $newDescription, 'balance' => $newBalance]);
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $pdo->prepare("INSERT INTO activity_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id) VALUES (?, 'update', 'main_account_transactions', ?, ?, ?, ?, ?, NOW(), ?, ?)")
            ->execute([$user_id, $transactionId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

        $pdo->commit();
        sendResponse(true, 'Fund transaction updated successfully');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// Filter allocations by month and year
function filterAllocationsByMonth($pdo) {
    $tenant_id = $_SESSION['tenant_id'];
    $branch_id = $_SESSION['branch_id'];
    try {
        $month = isset($_POST['month']) ? $_POST['month'] : date('m');
        $year = isset($_POST['year']) ? $_POST['year'] : date('Y');
        $includePrevious = isset($_POST['include_previous']) ? $_POST['include_previous'] == '1' : true;
        
        // Create date range
        $startDate = $year . '-' . $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        
        // Fetch allocations for the specified month and year
        if ($includePrevious) {
            $stmt = $pdo->prepare("
                SELECT ba.*, ma.name as account_name, ec.name as category_name 
                FROM budget_allocations ba
                JOIN main_account ma ON ba.main_account_id = ma.id
                JOIN expense_categories ec ON ba.category_id = ec.id
                WHERE (ba.allocation_date BETWEEN ? AND ?) OR (ba.remaining_amount > 0)
                AND ba.tenant_id = ? AND ba.branch_id = ?
                ORDER BY ba.allocation_date DESC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT ba.*, ma.name as account_name, ec.name as category_name 
                FROM budget_allocations ba
                JOIN main_account ma ON ba.main_account_id = ma.id
                JOIN expense_categories ec ON ba.category_id = ec.id
                WHERE ba.allocation_date BETWEEN ? AND ?
                AND ba.tenant_id = ? AND ba.branch_id = ?
                ORDER BY ba.allocation_date DESC
            ");
        }
        
        $stmt->execute([$startDate, $endDate, $tenant_id, $branch_id]);
        $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count allocations from current month vs previous months
        $currentMonthCount = 0;
        $previousMonthsCount = 0;
        foreach($allocations as $alloc) {
            if(date('Y-m', strtotime($alloc['allocation_date'])) == "$year-$month") {
                $currentMonthCount++;
            } else {
                $previousMonthsCount++;
            }
        }
        
        // Calculate totals
        $totalUSD = 0;
        $totalAFS = 0;
        $availableUSD = 0;
        $availableAFS = 0;
        
        foreach($allocations as $alloc) {
            if($alloc['currency'] === 'USD') {
                $totalUSD += $alloc['allocated_amount'];
                $availableUSD += $alloc['remaining_amount'];
            } else if($alloc['currency'] === 'AFS') {
                $totalAFS += $alloc['allocated_amount'];
                $availableAFS += $alloc['remaining_amount'];
            }
        }
        
        $usedUSD = $totalUSD - $availableUSD;
        $usedAFS = $totalAFS - $availableAFS;
        
        // Return data
        sendResponse(true, 'Allocations filtered successfully', [
            'allocations' => $allocations,
            'summary' => [
                'totalUSD' => $totalUSD,
                'totalAFS' => $totalAFS,
                'availableUSD' => $availableUSD,
                'availableAFS' => $availableAFS,
                'usedUSD' => $usedUSD,
                'usedAFS' => $usedAFS,
                'month' => $month,
                'year' => $year,
                'monthName' => date('F', strtotime($startDate)),
                'currentMonthCount' => $currentMonthCount,
                'previousMonthsCount' => $previousMonthsCount,
                'includePrevious' => $includePrevious
            ]
        ]);
    } catch (PDOException $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// ── Auto-find allocation & add expense (for top-level modal) ──────────────
function addAutoAllocationExpense($pdo) {
    $tenant_id = $_SESSION['tenant_id'];
    $branch_id = $_SESSION['branch_id'];
    try {
        $categoryId  = isset($_POST['categoryId']) ? intval($_POST['categoryId']) : 0;
        $subCategoryId = isset($_POST['subCategoryId']) ? intval($_POST['subCategoryId']) : 0;
        $date        = isset($_POST['date'])        ? $_POST['date']               : date('Y-m-d');
        $description = isset($_POST['description']) ? $_POST['description']        : '';
        $amount      = isset($_POST['amount'])       ? floatval($_POST['amount'])   : 0;
        $currency    = isset($_POST['currency'])     ? $_POST['currency']           : 'USD';

        if ($categoryId <= 0 || $amount <= 0) {
            sendResponse(false, 'Invalid input data');
            return;
        }

        // Validate sub-category belongs to the selected category
        if ($subCategoryId > 0) {
            $subStmt = $pdo->prepare("SELECT id FROM expense_categories WHERE id = ? AND parent_id = ? AND tenant_id = ? AND branch_id = ?");
            $subStmt->execute([$subCategoryId, $categoryId, $tenant_id, $branch_id]);
            if ($subStmt->rowCount() === 0) {
                sendResponse(false, 'Invalid sub-category for the selected category');
                return;
            }
        }

        // Find matching budget allocation for this category + currency + month
        $monthStart = date('Y-m-01', strtotime($date));
        $monthEnd   = date('Y-m-t', strtotime($date));

        if ($subCategoryId > 0) {
            // Prefer the sub-category-specific allocation, fall back to the general pool
            $allocStmt = $pdo->prepare("
                SELECT id, remaining_amount, main_account_id, currency
                FROM budget_allocations
                WHERE currency = ? AND allocation_date BETWEEN ? AND ? AND tenant_id = ? AND branch_id = ?
                  AND (sub_category_id = ? OR (category_id = ? AND sub_category_id IS NULL))
                ORDER BY (sub_category_id = ?) DESC
                LIMIT 1
            ");
            $allocStmt->execute([$currency, $monthStart, $monthEnd, $tenant_id, $branch_id, $subCategoryId, $categoryId, $subCategoryId]);
        } else {
            // Prefer the general (no sub-category) allocation
            $allocStmt = $pdo->prepare("
                SELECT id, remaining_amount, main_account_id, currency
                FROM budget_allocations
                WHERE category_id = ? AND currency = ? AND allocation_date BETWEEN ? AND ? AND tenant_id = ? AND branch_id = ?
                ORDER BY (sub_category_id IS NULL) DESC
                LIMIT 1
            ");
            $allocStmt->execute([$categoryId, $currency, $monthStart, $monthEnd, $tenant_id, $branch_id]);
        }
        $allocation = $allocStmt->fetch(PDO::FETCH_ASSOC);

        if (!$allocation) {
            sendResponse(false, 'No budget allocation found for this category and currency in the selected month');
            return;
        }

        // Delegate to addAllocationExpense logic
        $_POST['allocation_id'] = $allocation['id'];
        $_POST['category_id']   = $categoryId;
        if ($subCategoryId > 0) {
            $_POST['sub_category_id'] = $subCategoryId;
        }
        // Re-call the standard add function
        addAllocationExpense($pdo);
    } catch (PDOException $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// ── Add expense to a budget allocation ─────────────────────────────────────
function addAllocationExpense($pdo) {
    $tenant_id = $_SESSION['tenant_id'];
    $branch_id = $_SESSION['branch_id'];
    try {
        $allocationId = isset($_POST['allocation_id']) ? intval($_POST['allocation_id']) : 0;
        $categoryId   = isset($_POST['category_id'])   ? intval($_POST['category_id'])   : 0;
        $postedSubId  = isset($_POST['sub_category_id']) ? intval($_POST['sub_category_id']) : 0;
        $date         = isset($_POST['date'])           ? $_POST['date']                  : date('Y-m-d');
        $description  = isset($_POST['description'])    ? $_POST['description']           : '';
        $amount       = isset($_POST['amount'])          ? floatval($_POST['amount'])      : 0;
        $currency     = isset($_POST['currency'])        ? $_POST['currency']              : 'USD';

        if ($allocationId <= 0 || $amount <= 0) {
            sendResponse(false, 'Invalid input data');
            return;
        }

        // Get allocation details
        $allocStmt = $pdo->prepare("SELECT * FROM budget_allocations WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $allocStmt->execute([$allocationId, $tenant_id, $branch_id]);
        $allocation = $allocStmt->fetch(PDO::FETCH_ASSOC);

        if (!$allocation) {
            sendResponse(false, 'Allocation not found');
            return;
        }

        if ($allocation['currency'] != $currency) {
            sendResponse(false, 'Currency mismatch between expense and allocation');
            return;
        }

        // Validate sub-category belongs to the allocation's category
        if ($postedSubId > 0) {
            $subCheck = $pdo->prepare("SELECT id FROM expense_categories WHERE id = ? AND parent_id = ? AND tenant_id = ? AND branch_id = ?");
            $subCheck->execute([$postedSubId, $categoryId, $tenant_id, $branch_id]);
            if ($subCheck->rowCount() === 0) {
                sendResponse(false, 'Invalid sub-category for the selected category');
                return;
            }
        }

        $pdo->beginTransaction();

        // Deduct from allocation (allowing negative)
        $updateAlloc = $pdo->prepare("UPDATE budget_allocations SET remaining_amount = remaining_amount - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $updateAlloc->execute([$amount, $allocationId, $tenant_id, $branch_id]);

        // Use the user-selected sub-category when given, otherwise inherit the allocation's
        $expenseSubCategoryId = $postedSubId > 0 ? $postedSubId : ($allocation['sub_category_id'] ?? null);

        // Insert expense
        $stmt = $pdo->prepare("INSERT INTO expenses (category_id, sub_category_id, date, description, amount, currency, main_account_id, allocation_id, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$categoryId, $expenseSubCategoryId, $date, $description, $amount, $currency, $allocation['main_account_id'], $allocationId, $tenant_id, $branch_id]);
        $expenseId = $pdo->lastInsertId();

        // Notification
        $catStmt = $pdo->prepare("SELECT name FROM expense_categories WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $catStmt->execute([$categoryId, $tenant_id, $branch_id]);
        $categoryName = $catStmt->fetchColumn() ?: 'Unknown';

        $notifMsg = sprintf("New expense added from budget allocation for category %s: Amount %s %.2f - %s", $categoryName, $currency, $amount, $description);
        $notifStmt = $pdo->prepare("INSERT INTO notifications (transaction_id, transaction_type, message, status, created_at, tenant_id, branch_id) VALUES (?, 'expense', ?, 'Unread', NOW(), ?, ?)");
        $notifStmt->execute([$expenseId, $notifMsg, $tenant_id, $branch_id]);

        $pdo->commit();

        // Activity log
        $old_values = json_encode([]);
        $new_values = json_encode(['category_id' => $categoryId, 'date' => $date, 'description' => $description, 'amount' => $amount, 'currency' => $currency, 'allocation_id' => $allocationId]);
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $activityStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id) VALUES (?, 'add', 'expenses', ?, ?, ?, ?, ?, NOW(), ?, ?)");
        $activityStmt->execute([$user_id, $expenseId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

        sendResponse(true, 'Expense added successfully from budget allocation');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        sendResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// ── Update expense in a budget allocation ──────────────────────────────────
function updateAllocationExpense($pdo) {
    $tenant_id = $_SESSION['tenant_id'];
    $branch_id = $_SESSION['branch_id'];
    try {
        $expenseId    = isset($_POST['expense_id'])    ? intval($_POST['expense_id'])    : 0;
        $allocationId = isset($_POST['allocation_id']) ? intval($_POST['allocation_id']) : 0;
        $date         = isset($_POST['date'])           ? $_POST['date']                  : '';
        $description  = isset($_POST['description'])    ? $_POST['description']           : '';
        $amount       = isset($_POST['amount'])          ? floatval($_POST['amount'])      : 0;
        $currency     = isset($_POST['currency'])        ? $_POST['currency']              : 'USD';

        if ($expenseId <= 0 || $allocationId <= 0 || $amount <= 0) {
            sendResponse(false, 'Invalid input data');
            return;
        }

        // Get previous expense
        $prevStmt = $pdo->prepare("SELECT amount, currency, allocation_id FROM expenses WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $prevStmt->execute([$expenseId, $tenant_id, $branch_id]);
        $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);

        if (!$prev) {
            sendResponse(false, 'Expense not found');
            return;
        }

        // Get allocation
        $allocStmt = $pdo->prepare("SELECT * FROM budget_allocations WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $allocStmt->execute([$allocationId, $tenant_id, $branch_id]);
        $allocation = $allocStmt->fetch(PDO::FETCH_ASSOC);

        if (!$allocation) {
            sendResponse(false, 'Allocation not found');
            return;
        }

        if ($allocation['currency'] != $currency) {
            sendResponse(false, 'Currency mismatch');
            return;
        }

        $pdo->beginTransaction();

        // Refund old amount to old allocation
        if ($prev['allocation_id']) {
            $refundStmt = $pdo->prepare("UPDATE budget_allocations SET remaining_amount = remaining_amount + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $refundStmt->execute([$prev['amount'], $prev['allocation_id'], $tenant_id, $branch_id]);
        }

        // Deduct new amount from new allocation
        $deductStmt = $pdo->prepare("UPDATE budget_allocations SET remaining_amount = remaining_amount - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $deductStmt->execute([$amount, $allocationId, $tenant_id, $branch_id]);

        // Update expense
        $updateStmt = $pdo->prepare("UPDATE expenses SET date = ?, description = ?, amount = ?, currency = ?, allocation_id = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $updateStmt->execute([$date, $description, $amount, $currency, $allocationId, $expenseId, $tenant_id, $branch_id]);

        $pdo->commit();

        // Activity log
        $old_values = json_encode(['expense_id' => $expenseId, 'previous' => $prev]);
        $new_values = json_encode(['date' => $date, 'description' => $description, 'amount' => $amount, 'currency' => $currency, 'allocation_id' => $allocationId]);
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

// ── Delete expense from a budget allocation ────────────────────────────────
function deleteAllocationExpense($pdo) {
    $tenant_id = $_SESSION['tenant_id'];
    $branch_id = $_SESSION['branch_id'];
    try {
        $expenseId = isset($_POST['expense_id']) ? intval($_POST['expense_id']) : 0;

        if ($expenseId <= 0) {
            sendResponse(false, 'Invalid expense ID');
            return;
        }

        // Get expense
        $expStmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $expStmt->execute([$expenseId, $tenant_id, $branch_id]);
        $expense = $expStmt->fetch(PDO::FETCH_ASSOC);

        if (!$expense) {
            sendResponse(false, 'Expense not found');
            return;
        }

        $allocationId = $expense['allocation_id'];
        $amount = $expense['amount'];

        $pdo->beginTransaction();

        // Refund allocation
        if ($allocationId) {
            $refundStmt = $pdo->prepare("UPDATE budget_allocations SET remaining_amount = remaining_amount + ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $refundStmt->execute([$amount, $allocationId, $tenant_id, $branch_id]);
        }

        // Delete notification
        $delNotifStmt = $pdo->prepare("DELETE FROM notifications WHERE transaction_id = ? AND transaction_type = 'expense' AND tenant_id = ? AND branch_id = ?");
        $delNotifStmt->execute([$expenseId, $tenant_id, $branch_id]);

        // Delete expense
        $deleteStmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $deleteStmt->execute([$expenseId, $tenant_id, $branch_id]);

        $pdo->commit();

        // Activity log
        $old_values = json_encode($expense);
        $new_values = json_encode([]);
        $user_id = $_SESSION['user_id'] ?? 0;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $activityStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id) VALUES (?, 'delete', 'expenses', ?, ?, ?, ?, ?, NOW(), ?, ?)");
        $activityStmt->execute([$user_id, $expenseId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id, $branch_id]);

        sendResponse(true, 'Expense deleted successfully and allocation refunded');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
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