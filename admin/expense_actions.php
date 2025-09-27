<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
// Enforce authentication
enforce_auth();

require_once('../includes/db.php');
header('Content-Type: application/json');

try {
    $action = $_POST['action'] ?? '';

    switch($action) {
        case 'save_category':
            $categoryId = $_POST['categoryId'] ?? '';
            $categoryName = $_POST['categoryName'] ?? '';

            if($categoryId) {
                // Update
                $stmt = $pdo->prepare("UPDATE expense_categories SET name = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$categoryName, $categoryId, $tenant_id]);
                
                // Log the activity
                $old_values = json_encode([
                    'category_id' => $categoryId
                ], JSON_UNESCAPED_UNICODE);
                $new_values = json_encode([
                    'name' => $categoryName
                ], JSON_UNESCAPED_UNICODE);
                
                $user_id = $_SESSION['user_id'] ?? 0;
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                $activityStmt = $pdo->prepare("
                    INSERT INTO activity_log 
                    (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
                    VALUES (?, 'update', 'expense_categories', ?, ?, ?, ?, ?, NOW(), ?)
                ");
                $activityStmt->execute([$user_id, $categoryId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id]);
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO expense_categories (name, tenant_id) VALUES (?, ?)");
                $stmt->execute([$categoryName, $tenant_id]);
                $newCategoryId = $pdo->lastInsertId();
                
                // Log the activity
                $old_values = json_encode([], JSON_UNESCAPED_UNICODE);
                $new_values = json_encode([
                    'name' => $categoryName
                ], JSON_UNESCAPED_UNICODE);
                
                $user_id = $_SESSION['user_id'] ?? 0;
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                $activityStmt = $pdo->prepare("
                    INSERT INTO activity_log 
                    (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
                    VALUES (?, 'add', 'expense_categories', ?, ?, ?, ?, ?, NOW(), ?)
                ");
                $activityStmt->execute([$user_id, $newCategoryId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete_category':
            $categoryId = $_POST['categoryId'] ?? '';
            
            // Get category details before deleting
            $getStmt = $pdo->prepare("SELECT name FROM expense_categories WHERE id = ? AND tenant_id = ?");
            $getStmt->execute([$categoryId, $tenant_id]);
            $category = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("DELETE FROM expense_categories WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$categoryId, $tenant_id]);
            
            // Log the activity
            $old_values = json_encode([
                'category_id' => $categoryId,
                'name' => $category['name'] ?? 'Unknown'
            ], JSON_UNESCAPED_UNICODE);
            $new_values = json_encode([], JSON_UNESCAPED_UNICODE);
            
            $user_id = $_SESSION['user_id'] ?? 0;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $activityStmt = $pdo->prepare("
                INSERT INTO activity_log 
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
                VALUES (?, 'delete', 'expense_categories', ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $activityStmt->execute([$user_id, $categoryId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id]);
            
            echo json_encode(['success' => true]);
            break;

        case 'save_expense':
            // Extract form fields
            $expenseId = $_POST['expenseId'] ?? '';
            $categoryId = $_POST['expenseCategory'] ?? '';
            $date = $_POST['expenseDate'] ?? '';
            
            // Format date - ensure it's in YYYY-MM-DD format
            if (!empty($date)) {
                $date = date('Y-m-d', strtotime($date));
            }
            
            $description = $_POST['expenseDescription'] ?? '';
            $amount = $_POST['expenseAmount'] ?? '';
            $currency = $_POST['expenseCurrency'] ?? 'USD'; // Default to USD if not specified
            $mainAccountId = $_POST['expenseMainAccount'] ?? '';
            $allocationId = $_POST['expenseAllocation'] ?? null; // New parameter for allocation ID
            
            // Get receipt number (optional)
            $receiptNumber = $_POST['expenseReceiptNumber'] ?? '';
            
            // Handle receipt file upload (optional)
            $receiptFile = null;
            if (!empty($_FILES['expenseReceiptFile']['name'])) {
                // Create directory if it doesn't exist
                $uploadDir = '../uploads/expense_receipt/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Generate unique filename
                $fileExtension = pathinfo($_FILES['expenseReceiptFile']['name'], PATHINFO_EXTENSION);
                $receiptFile = uniqid('receipt_') . '.' . $fileExtension;
                $targetFile = $uploadDir . $receiptFile;
                
                // Move uploaded file
                if (!move_uploaded_file($_FILES['expenseReceiptFile']['tmp_name'], $targetFile)) {
                    throw new Exception("Failed to upload receipt file");
                }
            }
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Determine which balance column to update based on currency
            $balanceColumn = 'usd_balance'; // Default
            if ($currency == 'AFS') {
                $balanceColumn = 'afs_balance';
            } elseif ($currency == 'EUR') {
                $balanceColumn = 'euro_balance';
            } elseif ($currency == 'DARHAM') {
                $balanceColumn = 'darham_balance';
            }

            if($expenseId) {
                // Get previous expense details if this is an update
                $prevStmt = $pdo->prepare("SELECT amount, currency, main_account_id, allocation_id, receipt_file FROM expenses WHERE id = ? AND tenant_id = ?");
                $prevStmt->execute([$expenseId, $tenant_id]);
                $prevExpense = $prevStmt->fetch(PDO::FETCH_ASSOC);
                
                // If there's a previous receipt file and we're uploading a new one, delete the old file
                if (!empty($prevExpense['receipt_file']) && $receiptFile) {
                    $oldFile = '../uploads/expense_receipt/' . $prevExpense['receipt_file'];
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }
                
                // If no new file uploaded, keep the old one
                if (!$receiptFile && !empty($prevExpense['receipt_file'])) {
                    $receiptFile = $prevExpense['receipt_file'];
                }
                
                // Handle previous allocation if it exists
                if ($prevExpense && $prevExpense['allocation_id']) {
                    // Return the amount to the previous allocation
                    $updatePrevAllocationStmt = $pdo->prepare("
                        UPDATE budget_allocations 
                        SET remaining_amount = remaining_amount + ? 
                        WHERE id = ? AND tenant_id = ?
                    ");
                    $updatePrevAllocationStmt->execute([$prevExpense['amount'], $prevExpense['allocation_id'], $tenant_id]);
                }
                
                // Get allocation details without checking remaining amount
                if ($allocationId) {
                    $allocationCheckStmt = $pdo->prepare("
                        SELECT remaining_amount, currency, category_id, main_account_id FROM budget_allocations WHERE id = ? AND tenant_id = ?
                    ");
                    $allocationCheckStmt->execute([$allocationId, $tenant_id]);
                    $allocation = $allocationCheckStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$allocation) {
                        throw new Exception("Allocation not found");
                    }
                    
                    if ($allocation['currency'] != $currency) {
                        throw new Exception("Currency mismatch between expense and allocation");
                    }
                    
                    // Removed check for remaining_amount to allow negative balance
                    
                    // Get main_account_id from the allocation
                    $mainAccountId = $allocation['main_account_id'];
                    
                    // Deduct from the allocation (allowing negative balance)
                    $updateAllocationStmt = $pdo->prepare("
                        UPDATE budget_allocations 
                        SET remaining_amount = remaining_amount - ? 
                        WHERE id = ? AND tenant_id = ?
                    ");
                    $updateAllocationStmt->execute([$amount, $allocationId, $tenant_id]);
                    
                    // When using allocation, set main_account_id to NULL explicitly
                    $mainAccountId = null;
                } else {
                    // Not using allocation - ensure main account is provided
                    if (empty($mainAccountId)) {
                        throw new Exception("Main account is required when not using a budget allocation");
                    }
                }
                
                // Update expense with receipt fields
                $stmt = $pdo->prepare("UPDATE expenses SET date = ?, description = ?, amount = ?, currency = ?, main_account_id = ?, allocation_id = ?, receipt_file = ? WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$date, $description, $amount, $currency, $mainAccountId, $allocationId, $receiptFile, $expenseId, $tenant_id]);
                
                // If we're not using an allocation, we need to handle main account changes
                if (!$allocationId) {
                    // If amount, currency, or account changed, update main account balance
                    if ($prevExpense && ($prevExpense['amount'] != $amount || $prevExpense['currency'] != $currency || $prevExpense['main_account_id'] != $mainAccountId)) {
                        // If account changed or currency changed, handle differently
                        if ($prevExpense['main_account_id'] != $mainAccountId || $prevExpense['currency'] != $currency) {
                            // If account or currency changed, refund previous account and deduct from new account
                            if ($prevExpense['main_account_id']) {
                                // Determine previous balance column
                                $prevBalanceColumn = 'usd_balance'; // Default
                                if ($prevExpense['currency'] == 'AFS') {
                                    $prevBalanceColumn = 'afs_balance';
                                } elseif ($prevExpense['currency'] == 'EUR') {
                                    $prevBalanceColumn = 'euro_balance';
                                } elseif ($prevExpense['currency'] == 'DARHAM') {
                                    $prevBalanceColumn = 'darham_balance';
                                }
                                
                                // Refund previous account's appropriate currency balance
                                $refundStmt = $pdo->prepare("UPDATE main_account SET $prevBalanceColumn = $prevBalanceColumn + ? WHERE id = ? AND tenant_id = ?");
                                $refundStmt->execute([$prevExpense['amount'], $prevExpense['main_account_id'], $tenant_id]);
                                
                                // Get transaction details to find created_at timestamp for subsequent balance updates
                                $getTxnStmt = $pdo->prepare("SELECT id, created_at FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'expense' AND tenant_id = ?");
                                $getTxnStmt->execute([$expenseId, $tenant_id]);
                                $transaction = $getTxnStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($transaction) {
                                    // Update balances of all subsequent transactions in the previous account
                                    $updateSubsequentStmt = $pdo->prepare("
                                        UPDATE main_account_transactions 
                                        SET balance = balance + ?
                                        WHERE main_account_id = ? 
                                        AND currency = ? 
                                        AND created_at > ? 
                                        AND tenant_id = ?
                                    ");
                                    $updateSubsequentStmt->execute([
                                        $prevExpense['amount'], 
                                        $prevExpense['main_account_id'], 
                                        $prevExpense['currency'], 
                                        $transaction['created_at'],
                                        $tenant_id
                                    ]);
                                    
                                    // Delete the original transaction since we're moving to a new account/currency
                                    $deleteStmt = $pdo->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'expense' AND tenant_id = ?");
                                    $deleteStmt->execute([$expenseId, $tenant_id]);
                                }
                            }
                            
                            // Deduct from current account's appropriate currency balance
                            if ($mainAccountId) {
                                $updateBalanceStmt = $pdo->prepare("UPDATE main_account SET $balanceColumn = $balanceColumn - ? WHERE id = ? AND tenant_id = ?");
                                $updateBalanceStmt->execute([$amount, $mainAccountId, $tenant_id]);
                                
                                // Get updated balance for transaction record
                                $balanceStmt = $pdo->prepare("SELECT $balanceColumn FROM main_account WHERE id = ? AND tenant_id = ?");
                                $balanceStmt->execute([$mainAccountId, $tenant_id]);
                                $updatedBalance = $balanceStmt->fetchColumn();
                                
                                // Add new transaction record with correct reference and receipt number
                                $txnStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, description, balance, currency, transaction_of, reference_id, receipt, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $txnStmt->execute([
                                    $mainAccountId, 
                                    'debit',
                                    $amount, 
                                    $description, 
                                    $updatedBalance,
                                    $currency,
                                    'expense',
                                    $expenseId,
                                    $receiptNumber,
                                    $tenant_id
                                ]);
                            }
                        } else {
                            // Same account and currency, just update by the difference
                            $amountDifference = $amount - $prevExpense['amount'];
                            
                            // Update main account balance by the difference
                            if ($amountDifference != 0) {
                                $updateBalanceStmt = $pdo->prepare("UPDATE main_account SET $balanceColumn = $balanceColumn - ? WHERE id = ? AND tenant_id = ?");
                                $updateBalanceStmt->execute([$amountDifference, $mainAccountId, $tenant_id]);
                                
                                // Get transaction details
                                $getTxnStmt = $pdo->prepare("SELECT id, created_at FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'expense' AND tenant_id = ?");
                                $getTxnStmt->execute([$expenseId, $tenant_id]);
                                $transaction = $getTxnStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($transaction) {
                                    // Update this transaction's amount, description and receipt number
                                    $updateTxnStmt = $pdo->prepare("UPDATE main_account_transactions SET amount = ?, description = ?, receipt = ? WHERE id = ? AND tenant_id = ?");
                                    $updateTxnStmt->execute([$amount, $description, $receiptNumber, $transaction['id'], $tenant_id]);
                                    
                                    // Get current balance for this transaction
                                    $currentBalanceStmt = $pdo->prepare("SELECT balance FROM main_account_transactions WHERE id = ? AND tenant_id = ?");
                                    $currentBalanceStmt->execute([$transaction['id'], $tenant_id]);
                                    $currentBalance = $currentBalanceStmt->fetchColumn();
                                    
                                    // Update this transaction's balance
                                    $newBalance = $currentBalance - $amountDifference;
                                    $updateBalanceStmt = $pdo->prepare("UPDATE main_account_transactions SET balance = ? WHERE id = ? AND tenant_id = ?");
                                    $updateBalanceStmt->execute([$newBalance, $transaction['id'], $tenant_id]);
                                    
                                    // Update balances of all subsequent transactions
                                    $updateSubsequentStmt = $pdo->prepare("
                                        UPDATE main_account_transactions 
                                        SET balance = balance - ?
                                        WHERE main_account_id = ? 
                                        AND currency = ? 
                                        AND created_at > ? 
                                        AND id != ?
                                        AND tenant_id = ?
                                    ");
                                    $updateSubsequentStmt->execute([
                                        $amountDifference, 
                                        $mainAccountId, 
                                        $currency, 
                                        $transaction['created_at'],
                                        $transaction['id'],
                                        $tenant_id
                                    ]);
                                }
                            } else {
                                // Only receipt number changed, update that
                                $getTxnStmt = $pdo->prepare("SELECT id FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'expense' AND tenant_id = ?");
                                $getTxnStmt->execute([$expenseId, $tenant_id]);
                                $transaction = $getTxnStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($transaction) {
                                    $updateTxnStmt = $pdo->prepare("UPDATE main_account_transactions SET receipt = ? WHERE id = ? AND tenant_id = ?");
                                    $updateTxnStmt->execute([$receiptNumber, $transaction['id'], $tenant_id]);
                                }
                            }
                        }
                    } else {
                        // Only receipt number changed, update that
                        $getTxnStmt = $pdo->prepare("SELECT id FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'expense' AND tenant_id = ?");
                        $getTxnStmt->execute([$expenseId, $tenant_id]);
                        $transaction = $getTxnStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($transaction) {
                            $updateTxnStmt = $pdo->prepare("UPDATE main_account_transactions SET receipt = ? WHERE id = ? AND tenant_id = ?");
                            $updateTxnStmt->execute([$receiptNumber, $transaction['id'], $tenant_id]);
                        }
                    }
                    
                    // Add notification for expense update if transaction exists
                    $txnIdStmt = $pdo->prepare("SELECT id FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'expense' AND tenant_id = ? ORDER BY id DESC LIMIT 1");
                    $txnIdStmt->execute([$expenseId, $tenant_id]);
                    $transaction_id = $txnIdStmt->fetchColumn();
                    
                    // Get category name
                    $categoryStmt = $pdo->prepare("SELECT name FROM expense_categories WHERE id = ? AND tenant_id = ?");
                    $categoryStmt->execute([$categoryId, $tenant_id]);
                    $categoryName = $categoryStmt->fetchColumn();
                    
                    // Create notification message
                    $notificationMessage = sprintf(
                        "Expense updated for category %s: Amount %s %.2f - %s", 
                        $categoryName,
                        $currency,
                        $amount,
                        $description
                    );
                    
                    // Insert notification
                    if ($transaction_id) {
                        $notifStmt = $pdo->prepare("
                            INSERT INTO notifications 
                            (transaction_id, transaction_type, message, status, created_at, tenant_id) 
                            VALUES (?, 'expense_update', ?, 'Unread', NOW(), ?)
                        ");
                        $notifStmt->execute([$transaction_id, $notificationMessage, $tenant_id]);
                    } else {
                        // If no transaction ID (when using allocation), still create notification
                        $notifStmt = $pdo->prepare("
                            INSERT INTO notifications 
                            (transaction_type, message, status, created_at, tenant_id) 
                            VALUES ('expense_update', ?, 'Unread', NOW(), ?)
                        ");
                        $notifStmt->execute([$notificationMessage, $tenant_id]);
                    }
                }
            } else {
                // INSERTING A NEW EXPENSE
                
                // If using an allocation, get allocation details without checking remaining amount
                    if ($allocationId) {
                        $allocationCheckStmt = $pdo->prepare("
                            SELECT remaining_amount, currency, category_id, main_account_id FROM budget_allocations WHERE id = ? AND tenant_id = ?
                        ");
                        $allocationCheckStmt->execute([$allocationId, $tenant_id]);
                        $allocation = $allocationCheckStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$allocation) {
                            throw new Exception("Allocation not found");
                        }
                        
                        if ($allocation['currency'] != $currency) {
                            throw new Exception("Currency mismatch between expense and allocation");
                        }
                        
                        // Removed check for remaining_amount to allow negative balance
                        
                        // Ensure category matches allocation's category
                        $categoryId = $allocation['category_id'];
                        
                        // Get main_account_id from the allocation
                        $mainAccountId = $allocation['main_account_id'];
                        
                        // Deduct from the allocation (allowing negative balance)
                        $updateAllocationStmt = $pdo->prepare("
                            UPDATE budget_allocations 
                            SET remaining_amount = remaining_amount - ? 
                            WHERE id = ? AND tenant_id = ?
                        ");
                        $updateAllocationStmt->execute([$amount, $allocationId, $tenant_id]);
                } else {
                    // Not using allocation - ensure main account is provided
                    if (empty($mainAccountId)) {
                        throw new Exception("Main account is required when not using a budget allocation");
                    }
                }
                
                // Insert new expense with receipt fields
                $stmt = $pdo->prepare("INSERT INTO expenses (category_id, date, description, amount, currency, main_account_id, allocation_id, receipt_file, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$categoryId, $date, $description, $amount, $currency, $mainAccountId, $allocationId, $receiptFile, $tenant_id]);
                $ExpenseId = $pdo->lastInsertId();
                
                // If not using an allocation, deduct from main account if specified
                if (!$allocationId && $mainAccountId) {
                    // Update appropriate currency balance in main account
                    $updateBalanceStmt = $pdo->prepare("UPDATE main_account SET $balanceColumn = $balanceColumn - ? WHERE id = ? AND tenant_id = ?");
                    $updateBalanceStmt->execute([$amount, $mainAccountId, $tenant_id]);
                    
                    // Get updated balance for transaction record
                    $balanceStmt = $pdo->prepare("SELECT $balanceColumn FROM main_account WHERE id = ? AND tenant_id = ?");
                    $balanceStmt->execute([$mainAccountId, $tenant_id]);
                    $updatedBalance = $balanceStmt->fetchColumn();
                    
                    // Add transaction record with updated balance and receipt number
                    $txnStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, description, balance, currency, transaction_of, reference_id, receipt, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $txnStmt->execute([
                        $mainAccountId, 
                        'debit',
                        $amount, 
                        $description, 
                        $updatedBalance,
                        $currency,
                        'expense',
                        $ExpenseId,
                        $receiptNumber,
                        $tenant_id
                    ]);
                }
            }
            
            // Get the last inserted transaction ID for notification (if applicable)
            $transaction_id = null;
            if (!$allocationId && $mainAccountId) {
                $txnIdStmt = $pdo->prepare("SELECT id FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'expense' AND tenant_id = ? ORDER BY id DESC LIMIT 1");
                $txnIdStmt->execute([$expenseId ?: $ExpenseId, $tenant_id]);
                $transaction_id = $txnIdStmt->fetchColumn();
            }
            
            // Create notification for admin
            $categoryStmt = $pdo->prepare("SELECT name FROM expense_categories WHERE id = ? AND tenant_id = ?");
            $categoryStmt->execute([$categoryId, $tenant_id]);
            $categoryName = $categoryStmt->fetchColumn();
            
            $notificationMessage = sprintf(
                "New expense added for category %s: Amount %s %.2f - %s", 
                $categoryName,
                $currency,
                $amount,
                $description
            );
            
                            // Insert notification
                if ($transaction_id) {
                    $notifStmt = $pdo->prepare("
                        INSERT INTO notifications 
                        (transaction_id, transaction_type, message, status, created_at, tenant_id) 
                        VALUES (?, 'expense', ?, 'Unread', NOW(), ?)
                    ");
                    $notifStmt->execute([$transaction_id, $notificationMessage, $tenant_id]);
                } else {
                    // If no transaction ID (when using allocation), still create notification
                    $notifStmt = $pdo->prepare("
                        INSERT INTO notifications 
                        (transaction_type, message, status, created_at, tenant_id) 
                        VALUES ('expense', ?, 'Unread', NOW(), ?)
                    ");
                    $notifStmt->execute([$notificationMessage, $tenant_id]);
                }
            
            // Commit transaction
            $pdo->commit();
            
            // Log the activity
            if ($expenseId) {
                // This was an update
                $old_values = json_encode([
                    'expense_id' => $expenseId,
                    'previous_values' => isset($prevExpense) ? $prevExpense : []
                ], JSON_UNESCAPED_UNICODE);
                $action = 'update';
            } else {
                // This was an insert
                $old_values = json_encode([], JSON_UNESCAPED_UNICODE);
                $action = 'add';
                $expenseId = $ExpenseId ?? $pdo->lastInsertId();
            }
            
            $new_values = json_encode([
                'category_id' => $categoryId,
                'date' => $date,
                'description' => $description,
                'amount' => $amount,
                'currency' => $currency,
                'main_account_id' => $mainAccountId,
                'allocation_id' => $allocationId,
                'receipt_number' => $receiptNumber,
                'receipt_file' => $receiptFile
            ], JSON_UNESCAPED_UNICODE);
            
            $user_id = $_SESSION['user_id'] ?? 0;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $activityStmt = $pdo->prepare("
                INSERT INTO activity_log 
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
                VALUES (?, ?, 'expenses', ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $activityStmt->execute([$user_id, $action, $expenseId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id]);
            
            echo json_encode(['success' => true, 'message' => 'Expense saved successfully']);
            break;

        case 'delete_expense':
            $expenseId = $_POST['expenseId'] ?? '';
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Get expense details before deleting
            $getExpenseStmt = $pdo->prepare("SELECT amount, currency, main_account_id, description, date, allocation_id, receipt_file FROM expenses WHERE id = ? AND tenant_id = ?");
            $getExpenseStmt->execute([$expenseId, $tenant_id]);
            $expense = $getExpenseStmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete associated receipt file if exists
            if (!empty($expense['receipt_file'])) {
                $filePath = '../uploads/expense_receipt/' . $expense['receipt_file'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            // If expense has an associated allocation, return the amount to the allocation
            if ($expense && $expense['allocation_id']) {
                $updateAllocationStmt = $pdo->prepare("
                    UPDATE budget_allocations 
                    SET remaining_amount = remaining_amount + ? 
                    WHERE id = ? AND tenant_id = ?
                ");
                $updateAllocationStmt->execute([$expense['amount'], $expense['allocation_id'], $tenant_id]);
            }
            // If expense has an associated main account, refund the amount
            elseif ($expense && $expense['main_account_id']) {
                // Determine which balance column to update based on currency
                $balanceColumn = 'usd_balance'; // Default
                if ($expense['currency'] == 'AFS') {
                    $balanceColumn = 'afs_balance';
                } elseif ($expense['currency'] == 'EUR') {
                    $balanceColumn = 'euro_balance';
                } elseif ($expense['currency'] == 'DARHAM') {
                    $balanceColumn = 'darham_balance';
                }
                
                // Get transaction details to find created_at timestamp
                $getTxnStmt = $pdo->prepare("SELECT created_at FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'expense' AND tenant_id = ?");
                $getTxnStmt->execute([$expenseId, $tenant_id]);
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
                        AND tenant_id = ?
                    ");
                    $updateSubsequentStmt->execute([
                        $expense['amount'], 
                        $expense['main_account_id'], 
                        $expense['currency'], 
                        $transaction['created_at'],
                        $expenseId,
                        $tenant_id
                    ]);
                }
                
                // Add amount back to the main account balance
                $updateBalanceStmt = $pdo->prepare("UPDATE main_account SET $balanceColumn = $balanceColumn + ? WHERE id = ? AND tenant_id = ?");
                $updateBalanceStmt->execute([$expense['amount'], $expense['main_account_id'], $tenant_id]);
                
                // Delete the associated transaction
                $deleteStmt = $pdo->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'expense' AND tenant_id = ?");
                $deleteStmt->execute([$expenseId, $tenant_id]);
            }
            
            // Now delete the expense
            $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$expenseId, $tenant_id]);
            
            // Get category name for notification
            $categoryStmt = $pdo->prepare("SELECT ec.name FROM expenses e JOIN expense_categories ec ON e.category_id = ec.id WHERE e.id = ? AND e.tenant_id = ?");
            $categoryStmt->execute([$expenseId, $tenant_id]);
            $categoryName = $categoryStmt->fetchColumn() ?: 'Unknown';
            
            // Create notification message
            $notificationMessage = sprintf(
                "Expense deleted for category %s: Amount %s %.2f - %s", 
                $categoryName,
                $expense['currency'] ?? 'USD',
                $expense['amount'] ?? 0,
                $expense['description'] ?? ''
            );
            
            // Insert notification
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications 
                (transaction_type, message, status, created_at, tenant_id) 
                VALUES ('expense_delete', ?, 'Unread', NOW(), ?)
            ");
            $notifStmt->execute([$notificationMessage, $tenant_id]);
            
            // Commit transaction
            $pdo->commit();
            
            // Log the activity
            $old_values = json_encode([
                'expense_id' => $expenseId,
                'amount' => $expense['amount'] ?? 0,
                'currency' => $expense['currency'] ?? '',
                'main_account_id' => $expense['main_account_id'] ?? null,
                'allocation_id' => $expense['allocation_id'] ?? null,
                'description' => $expense['description'] ?? '',
                'date' => $expense['date'] ?? '',
                'receipt_file' => $expense['receipt_file'] ?? null
            ], JSON_UNESCAPED_UNICODE);
            $new_values = json_encode([], JSON_UNESCAPED_UNICODE);
            
            $user_id = $_SESSION['user_id'] ?? 0;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $activityStmt = $pdo->prepare("
                INSERT INTO activity_log 
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id) 
                VALUES (?, 'delete', 'expenses', ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $activityStmt->execute([$user_id, $expenseId, $old_values, $new_values, $ip_address, $user_agent, $tenant_id]);
            
            echo json_encode(['success' => true, 'message' => 'Expense deleted successfully and balances adjusted']);
            break;

        case 'get_expense':
            try {
                $expenseId = $_POST['expenseId'] ?? '';
                
                if (!$expenseId) {
                    throw new Exception('Expense ID is required');
                }
                
                $expenseStmt = $pdo->prepare("
                    SELECT e.*, t.receipt as receipt_number 
                    FROM expenses e 
                    LEFT JOIN main_account_transactions t ON e.id = t.reference_id AND t.transaction_of = 'expense'
                    WHERE e.id = ? AND e.tenant_id = ?
                ");
                $expenseStmt->execute([$expenseId, $tenant_id]);
                $expense = $expenseStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$expense) {
                    throw new Exception('Expense not found');
                }
                
                echo json_encode([
                    'success' => true, 
                    'expense' => $expense
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false, 
                    'message' => $e->getMessage()
                ]);
            }
            break;
            
        case 'get_expense_details':
            try {
                $expenseId = $_POST['expenseId'] ?? '';
                
                if (!$expenseId) {
                    throw new Exception('Expense ID is required');
                }
                
                $expenseStmt = $pdo->prepare("
                    SELECT e.*, t.receipt as receipt_number 
                    FROM expenses e 
                    LEFT JOIN main_account_transactions t ON e.id = t.reference_id AND t.transaction_of = 'expense'
                    WHERE e.id = ? AND e.tenant_id = ?
                ");
                $expenseStmt->execute([$expenseId, $tenant_id]);
                $expense = $expenseStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$expense) {
                    throw new Exception('Expense not found');
                }
                
                echo json_encode([
                    'success' => true, 
                    'expense' => $expense
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false, 
                    'message' => $e->getMessage()
                ]);
            }
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch(Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}