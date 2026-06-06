<?php
require_once '../includes/db.php';

// Include security module
require_once '../admin/security.php';

// Enforce authentication
enforce_auth();

// ✅ CSRF Token Validation (only for POST requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Security validation failed. Please try again.']);
    exit;
}

// Initialize messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear session messages after retrieving them
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Build redirect URL with current query parameters
$redirect_url = $_SERVER['PHP_SELF'];
if (!empty($_GET)) {
    $redirect_url .= '?' . http_build_query($_GET);
}


$userName = $user['name'] ?? 'Guest';
$userRole = $user['role'] ?? 'User';
$userEmail = $user['email'] ?? '';
$userPhone = $user['phone'] ?? '';
$userAddress = $user['address'] ?? '';
$userHireDate = isset($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : 'Not Set';
$userCreatedAt = isset($user['created_at']) ? date('M d, Y H:i A', strtotime($user['created_at'])) : 'Not Available';

// Handle new creditor submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_creditor'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $balance = $_POST['balance'];
    $currency = $_POST['currency'];
    $main_account_id = $_POST['main_account_id']; // This is now always required
    $skip_main_account = isset($_POST['skip_main_account']);
    
    try {
        $pdo->beginTransaction();
        
        // Insert the creditor
        $stmt = $pdo->prepare("INSERT INTO creditors (name, email, phone, address, balance, currency, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bindParam(1, $name, PDO::PARAM_STR);
        $stmt->bindParam(2, $email, PDO::PARAM_STR);
        $stmt->bindParam(3, $phone, PDO::PARAM_STR);
        $stmt->bindParam(4, $address, PDO::PARAM_STR);
        $stmt->bindParam(5, $balance, PDO::PARAM_STR);
        $stmt->bindParam(6, $currency, PDO::PARAM_STR);
        $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(8, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $creditor_id = $pdo->lastInsertId();

        // Only add to main account balance and create transaction if not skipped
        if (!$skip_main_account) {
            // Get main account balance column name based on currency
            $balance_column = strtolower($currency) . '_balance';
            if ($currency == 'DARHAM') {
                $balance_column = 'darham_balance';
            } elseif ($currency == 'EUR') {
                $balance_column = 'euro_balance';
            } elseif ($currency == 'USD') {
                $balance_column = 'usd_balance';
            } elseif ($currency == 'AFS') {
                $balance_column = 'afs_balance';
            }

            // Get current main account balance
            $stmt = $pdo->prepare("SELECT $balance_column FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->bindParam(1, $main_account_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $main_account = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$main_account) {
                throw new Exception(__("main_account_not_found"));
            }

            // Update main account balance (add the creditor amount)
            $new_main_balance = $main_account[$balance_column] + $balance;
            $stmt = $pdo->prepare("UPDATE main_account SET $balance_column = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->bindParam(1, $new_main_balance, PDO::PARAM_STR);
            $stmt->bindParam(2, $main_account_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt->execute();

            // Create main account transaction record
            $transaction_type = 'credit';
            $description = "Initial credit balance for creditor: $name";
            $tranasction_of = 'creditor';

            // Create creditor transaction record with type 'credit'
            $reference_number = ''; // No reference number for initial transaction
            $stmt = $pdo->prepare("INSERT INTO creditor_transactions (creditor_id, amount, currency, transaction_type, description, payment_date, reference_number, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)");
            $stmt->bindParam(1, $creditor_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $balance, PDO::PARAM_STR);
            $stmt->bindParam(3, $currency, PDO::PARAM_STR);
            $stmt->bindParam(4, $transaction_type, PDO::PARAM_STR);
            $stmt->bindParam(5, $description, PDO::PARAM_STR);
            $stmt->bindParam(6, $reference_number, PDO::PARAM_STR);
            $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(8, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $creditor_transaction_id = $pdo->lastInsertId();
            
            // Create main account transaction record
            $stmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, amount, balance, currency, type, description, transaction_of, reference_id, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bindParam(1, $main_account_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $balance, PDO::PARAM_STR);
            $stmt->bindParam(3, $new_main_balance, PDO::PARAM_STR);
            $stmt->bindParam(4, $currency, PDO::PARAM_STR);
            $stmt->bindParam(5, $transaction_type, PDO::PARAM_STR);
            $stmt->bindParam(6, $description, PDO::PARAM_STR);
            $stmt->bindParam(7, $tranasction_of, PDO::PARAM_STR);
            $stmt->bindParam(8, $creditor_transaction_id, PDO::PARAM_INT);
            $stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(10, $branch_id, PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['success_message'] = __("creditor_added_successfully_with_main_account_transaction");
        } else {
            $_SESSION['success_message'] = __("creditor_added_successfully_skipped_main_account_transaction");
        }
        
        $pdo->commit();
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = __("error_adding_creditor") . ": " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay'])) {
    $creditor_id = $_POST['creditor_id'];
    $amount = $_POST['amount'];
    $currency = $_POST['currency'];
    $creditor_currency = isset($_POST['creditor_currency']) ? $_POST['creditor_currency'] : $currency;
    $payment_date = $_POST['payment_date'];
    $receipt = $_POST['receipt'];
    $transaction_type = 'debit';
    $description = $_POST['description'];
    $paid_to = $_POST['paid_to'];

    try {
        $pdo->beginTransaction();
        
        // Get creditor information
        $stmt = $pdo->prepare("SELECT balance, currency, name FROM creditors WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $creditor_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $creditor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If the payment currency is different from the creditor's currency, apply exchange rate
        $converted_amount = $amount;
        $exchange_info = '';
        if ($currency !== $creditor_currency) {
            if (!isset($_POST['exchange_rate']) || empty($_POST['exchange_rate'])) {
                throw new Exception(__("exchange_rate_is_required_when_currencies_are_different"));
            }
            $exchange_rate = floatval($_POST['exchange_rate']);
            if ($creditor_currency === 'AFS') {
                // 1 [payment] = X AFS → multiply
                $converted_amount = $amount * $exchange_rate;
            } elseif ($currency === 'AFS') {
                // 1 [creditor] = X AFS → divide
                $converted_amount = $amount / $exchange_rate;
            } else {
                // 1 [creditor] = X [payment] → divide
                $converted_amount = $amount / $exchange_rate;
            }
            $exchange_info = " (Converted from $amount $currency at rate $exchange_rate to $converted_amount $creditor_currency)";
            $description .= $exchange_info;
        }
        
        if ($creditor['balance'] >= $converted_amount) {
            // Create creditor transaction record
            $stmt = $pdo->prepare("INSERT INTO creditor_transactions (creditor_id, amount, currency, transaction_type, description, payment_date, reference_number, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bindParam(1, $creditor_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $converted_amount, PDO::PARAM_STR);
            $stmt->bindParam(3, $creditor_currency, PDO::PARAM_STR);
            $stmt->bindParam(4, $transaction_type, PDO::PARAM_STR);
            $stmt->bindParam(5, $description, PDO::PARAM_STR);
            $stmt->bindParam(6, $payment_date, PDO::PARAM_STR);
            $stmt->bindParam(7, $receipt, PDO::PARAM_STR);
            $stmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(9, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $creditor_transaction_id = $pdo->lastInsertId();
            
            // Update creditor balance
            $new_balance = $creditor['balance'] - $converted_amount;
            $stmt = $pdo->prepare("UPDATE creditors SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->bindParam(1, $new_balance, PDO::PARAM_STR);
            $stmt->bindParam(2, $creditor_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt->execute();

            // Check if balance is 0 and update status to inactive
            if ($new_balance == 0) {
                $stmt = $pdo->prepare("UPDATE creditors SET status = 'inactive' WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt->bindParam(1, $creditor_id, PDO::PARAM_INT);
                $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                $stmt->execute();
            }
            
            // Get main account balance column name for the specific currency
            $balance_column = strtolower($currency) . '_balance';
            if ($currency == 'DARHAM') {
                $balance_column = 'darham_balance';
            } elseif ($currency == 'EUR') {
                $balance_column = 'euro_balance';
            } elseif ($currency == 'USD') {
                $balance_column = 'usd_balance';
            } elseif ($currency == 'AFS') {
                $balance_column = 'afs_balance';
            }
            
            // Get current main account balance
            $stmt = $pdo->prepare("SELECT $balance_column FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->bindParam(1, $paid_to, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $main_account = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$main_account) {
                throw new Exception(__("main_account_not_found"));
            }
            
            // Update main account balance (subtract original amount)
            $new_main_balance = $main_account[$balance_column] - $amount;
            $stmt = $pdo->prepare("UPDATE main_account SET $balance_column = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->bindParam(1, $new_main_balance, PDO::PARAM_STR);
            $stmt->bindParam(2, $paid_to, PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $tranasction_of = 'creditor';
            // Create main account transaction
            $main_transaction_description = $description;
            $stmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, amount, balance, currency, type, description, transaction_of, reference_id, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bindParam(1, $paid_to, PDO::PARAM_INT);
            $stmt->bindParam(2, $amount, PDO::PARAM_STR);
            $stmt->bindParam(3, $new_main_balance, PDO::PARAM_STR);
            $stmt->bindParam(4, $currency, PDO::PARAM_STR);
            $stmt->bindParam(5, $transaction_type, PDO::PARAM_STR);
            $stmt->bindParam(6, $main_transaction_description, PDO::PARAM_STR);
            $stmt->bindParam(7, $tranasction_of, PDO::PARAM_STR);
            $stmt->bindParam(8, $creditor_transaction_id, PDO::PARAM_INT);
            $stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(10, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Get the last inserted ID for the main account transaction
            $main_transaction_id = $pdo->lastInsertId();

            // Create notification
            $notificationMessage = sprintf(
                "Payment made to creditor: %s - Amount %s %.2f",
                $creditor['name'],
                $currency,
                $amount
            );

            $notifStmt = $pdo->prepare("
                INSERT INTO notifications
                (transaction_id, transaction_type, message, status, created_at, tenant_id, branch_id)
                VALUES (?, 'creditor', ?, 'Unread', NOW(), ?, ?)
            ");

            if (!$notifStmt->execute([$main_transaction_id, $notificationMessage, $tenant_id, $branch_id])) {
                throw new Exception("Failed to create notification");
            }
            
            // Send email notification to creditor
            require_once '../includes/functions.php';
    
            // Get creditor email
            $stmt_creditor_email = $pdo->prepare("SELECT email FROM creditors WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt_creditor_email->bindParam(1, $creditor_id, PDO::PARAM_INT);
            $stmt_creditor_email->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt_creditor_email->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt_creditor_email->execute();
            $creditor_email_data = $stmt_creditor_email->fetch(PDO::FETCH_ASSOC);
            $creditor_email = $creditor_email_data['email'];
    
            if (!empty($creditor_email)) {
                $message = "Payment of {$converted_amount} {$creditor_currency} has been processed. " . (!empty($exchange_info) ? $exchange_info : "");
                sendAccountNotification($creditor_email, $creditor['name'], 'creditor', $new_balance, $creditor_currency, $message);
            }
    
            $pdo->commit();
            $_SESSION['success_message'] = __("payment_processed_successfully");
        } else {
            throw new Exception(__("insufficient_balance"));
        }
        
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = __("error_processing_payment") . ": " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle transaction deletion and reversal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_transaction'])) {
    $transaction_id = $_POST['transaction_id'];
    $creditor_id = $_POST['creditor_id'];
    $amount = $_POST['amount'];
    $currency = $_POST['currency'];
    
    try {
        $pdo->beginTransaction();
        
        // Get transaction details
        $stmt = $pdo->prepare("SELECT * FROM creditor_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            throw new Exception(__("transaction_not_found"));
        }
        
        // Get the linked main account transaction
        $stmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'creditor' AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $main_transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$main_transaction) {
            throw new Exception(__("main_account_transaction_not_found"));
        }

        // Use main account transaction's amount and currency for all main account updates
         $main_amount = $main_transaction['amount'];
         $main_currency = $main_transaction['currency'];
         
         // Check if this is a credit or debit transaction
         $is_credit = $transaction['transaction_type'] === 'credit';
         
         // For credit transactions, we need to SUBTRACT from subsequent balances
         // For debit transactions, we need to ADD to subsequent balances
         $balance_adjustment = $is_credit ? $main_amount * -1 : $main_amount;

         // Update balances of all subsequent transactions
         $updateSubsequentStmt = $pdo->prepare("
             UPDATE main_account_transactions
             SET balance = balance + ?
             WHERE main_account_id = ?
             AND currency = ?
             AND id > ?
             AND id != ?
             AND tenant_id = ?
             AND branch_id = ?
         ");
         $updateSubsequentStmt->bindParam(1, $balance_adjustment, PDO::PARAM_STR);
         $updateSubsequentStmt->bindParam(2, $main_transaction['main_account_id'], PDO::PARAM_INT);
         $updateSubsequentStmt->bindParam(3, $main_currency, PDO::PARAM_STR);
         $updateSubsequentStmt->bindParam(4, $main_transaction['id'], PDO::PARAM_INT);
         $updateSubsequentStmt->bindParam(5, $main_transaction['id'], PDO::PARAM_INT);
         $updateSubsequentStmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
         $updateSubsequentStmt->bindParam(7, $branch_id, PDO::PARAM_INT);
         $updateSubsequentStmt->execute();
         
         // Get creditor information
         $stmt = $pdo->prepare("SELECT balance FROM creditors WHERE id = ? AND tenant_id = ? AND branch_id = ?");
         $stmt->bindParam(1, $creditor_id, PDO::PARAM_INT);
         $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
         $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
         $stmt->execute();
         $creditor = $stmt->fetch(PDO::FETCH_ASSOC);
         
         // Update creditor balance
         // For credit: subtract the amount (reverse the credit)
         // For debit: add the amount back (reverse the debit)
         if ($is_credit) {
             $new_balance = $creditor['balance'] - $transaction['amount'];
         } else {
             $new_balance = $creditor['balance'] + $transaction['amount'];
         }
         $stmt = $pdo->prepare("UPDATE creditors SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
         $stmt->bindParam(1, $new_balance, PDO::PARAM_STR);
         $stmt->bindParam(2, $creditor_id, PDO::PARAM_INT);
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
             throw new Exception(__("main_account_not_found"));
         }
         
         // Update main account balance
         // For credit: subtract the amount (reverse the credit)
         // For debit: add the amount back (reverse the debit)
         if ($is_credit) {
             $new_main_balance = $main_account[$balance_column] - $main_amount;
         } else {
             $new_main_balance = $main_account[$balance_column] + $main_amount;
         }
        $stmt = $pdo->prepare("UPDATE main_account SET $balance_column = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $new_main_balance, PDO::PARAM_STR);
        $stmt->bindParam(2, $main_transaction['main_account_id'], PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Delete the transactions
        $stmt = $pdo->prepare("DELETE FROM creditor_transactions WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("DELETE FROM main_account_transactions WHERE reference_id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $pdo->commit();
        $_SESSION['success_message'] = __("transaction_reversed_and_deleted_successfully");
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = __("error_reversing_transaction") . ": " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle creditor editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_creditor'])) {
    $creditor_id = $_POST['creditor_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    
    try {
        $pdo->beginTransaction();
        
        // Get current creditor information
        $stmt = $pdo->prepare("SELECT balance, currency FROM creditors WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $creditor_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $current_creditor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_creditor) {
            throw new Exception(__("creditor_not_found"));
        }

        // Update creditor information (name, email, phone, address only - balance and currency are not editable)
        $stmt = $pdo->prepare("UPDATE creditors SET name = ?, email = ?, phone = ?, address = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $name, PDO::PARAM_STR);
        $stmt->bindParam(2, $email, PDO::PARAM_STR);
        $stmt->bindParam(3, $phone, PDO::PARAM_STR);
        $stmt->bindParam(4, $address, PDO::PARAM_STR);
        $stmt->bindParam(5, $creditor_id, PDO::PARAM_INT);
        $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['success_message'] = __("creditor_information_updated_successfully");
        
        $pdo->commit();
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = __("error_updating_creditor") . ": " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Determine which status to display
$status_filter = isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'inactive' : 'active';

// Fetch creditors based on status filter
$stmt = $pdo->prepare("SELECT * FROM creditors WHERE status = ? AND tenant_id = ? AND branch_id = ? ORDER BY created_at DESC");
$stmt->bindParam(1, $status_filter, PDO::PARAM_STR);
$stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$creditors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total credits by currency
$currency_totals = [];
if (count($creditors) > 0) {
    foreach ($creditors as $creditor) {
        $currency = $creditor['currency'];
        $balance = $creditor['balance'];
        
        if (!isset($currency_totals[$currency])) {
            $currency_totals[$currency] = 0;
        }
        
        $currency_totals[$currency] += $balance;
    }
}

// Fetch main accounts for payment form
$stmt = $pdo->prepare("SELECT id, name FROM main_account where status = 'active' AND tenant_id = ? AND branch_id = ?");
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$main_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);



// Validate transaction_id
$transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;

// Validate delete_transaction
$delete_transaction = isset($_POST['delete_transaction']) ? DbSecurity::validateInput($_POST['delete_transaction'], 'string', ['maxlength' => 255]) : null;

// Validate paid_to
$paid_to = isset($_POST['paid_to']) ? DbSecurity::validateInput($_POST['paid_to'], 'string', ['maxlength' => 255]) : null;

// Validate description
$description = isset($_POST['description']) ? DbSecurity::validateInput($_POST['description'], 'string', ['maxlength' => 255]) : null;

// Validate receipt
$receipt = isset($_POST['receipt']) ? DbSecurity::validateInput($_POST['receipt'], 'string', ['maxlength' => 255]) : null;

// Validate payment_date
$payment_date = isset($_POST['payment_date']) ? DbSecurity::validateInput($_POST['payment_date'], 'date') : null;

// Validate amount
$amount = isset($_POST['amount']) ? DbSecurity::validateInput($_POST['amount'], 'float', ['min' => 0]) : null;

// Validate creditor_id
$creditor_id = isset($_POST['creditor_id']) ? DbSecurity::validateInput($_POST['creditor_id'], 'int', ['min' => 0]) : null;

// Validate pay
$pay = isset($_POST['pay']) ? DbSecurity::validateInput($_POST['pay'], 'string', ['maxlength' => 255]) : null;

// Validate currency
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : null;

// Validate balance
$balance = isset($_POST['balance']) ? DbSecurity::validateInput($_POST['balance'], 'float', ['min' => 0]) : null;

// Validate address
$address = isset($_POST['address']) ? DbSecurity::validateInput($_POST['address'], 'string', ['maxlength' => 255]) : null;

// Validate phone
$phone = isset($_POST['phone']) ? DbSecurity::validateInput($_POST['phone'], 'string', ['maxlength' => 255]) : null;

// Validate email
$email = isset($_POST['email']) ? DbSecurity::validateInput($_POST['email'], 'email') : null;

// Validate name
$name = isset($_POST['name']) ? DbSecurity::validateInput($_POST['name'], 'string', ['maxlength' => 255]) : null;

// Validate add_creditor
$add_creditor = isset($_POST['add_creditor']) ? DbSecurity::validateInput($_POST['add_creditor'], 'string', ['maxlength' => 255]) : null;

// Add the delete creditor handler at the end of the file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_creditor'])) {
    $creditor_id = $_POST['creditor_id'];
    $creditor_balance = $_POST['creditor_balance'];
    $creditor_currency = $_POST['creditor_currency'];
    
    try {
        $pdo->beginTransaction();
        
        // Get the initial creditor transaction to check the main account
        $stmt = $pdo->prepare("SELECT mt.id as initial_transaction_id, mt.main_account_id
                              FROM main_account_transactions mt
                              WHERE mt.transaction_of = 'creditor'
                              AND mt.reference_id = ?
                              AND mt.type = 'credit'
                              AND mt.tenant_id = ?
                              AND mt.branch_id = ?
                              ORDER BY mt.created_at ASC LIMIT 1");
        $stmt->bindParam(1, $creditor_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $initial_transaction_check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if there are any OTHER transactions in this main account besides the initial creditor transaction
        if ($initial_transaction_check) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as transaction_count
                                  FROM main_account_transactions mt
                                  WHERE mt.main_account_id = ?
                                  AND mt.id != ?
                                  AND mt.tenant_id = ?
                                  AND mt.branch_id = ?");
            $stmt->bindParam(1, $initial_transaction_check['main_account_id'], PDO::PARAM_INT);
            $stmt->bindParam(2, $initial_transaction_check['initial_transaction_id'], PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['transaction_count'] > 0) {
                $pdo->rollBack();
                $_SESSION['error_message'] = "Cannot delete creditor. Please delete all main account transactions first, then try deleting the creditor.";
                header('Location: ' . $redirect_url);
                exit();
            }
        }
        
        // Check if creditor has any main account transactions
        $stmt = $pdo->prepare("SELECT mt.*, ma.id as main_account_id
                              FROM main_account_transactions mt
                              JOIN main_account ma ON mt.main_account_id = ma.id
                              WHERE mt.transaction_of = 'creditor'
                              AND mt.reference_id = ?
                              AND mt.type = 'credit'
                              AND mt.tenant_id = ?
                              AND mt.branch_id = ?
                              ORDER BY mt.created_at ASC LIMIT 1");
        $stmt->bindParam(1, $creditor_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $initial_transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($initial_transaction) {
            // Get main account balance column based on currency
            $balance_column = strtolower($creditor_currency) . '_balance';
            if ($creditor_currency == 'DARHAM') {
                $balance_column = 'darham_balance';
            } elseif ($creditor_currency == 'EUR') {
                $balance_column = 'euro_balance';
            } elseif ($creditor_currency == 'USD') {
                $balance_column = 'usd_balance';
            } elseif ($creditor_currency == 'AFS') {
                $balance_column = 'afs_balance';
            }

            // Update all subsequent transaction balances to remove the creditor's balance
            $stmt = $pdo->prepare("
                UPDATE main_account_transactions
                SET balance = balance - ?
                WHERE main_account_id = ?
                AND currency = ?
                AND id > ?
                AND id != ?
                AND tenant_id = ?
                AND branch_id = ?
            ");
            $stmt->bindParam(1, $creditor_balance, PDO::PARAM_STR);
            $stmt->bindParam(2, $initial_transaction['main_account_id'], PDO::PARAM_INT);
            $stmt->bindParam(3, $creditor_currency, PDO::PARAM_STR);
            $stmt->bindParam(4, $initial_transaction['id'], PDO::PARAM_INT);
            $stmt->bindParam(5, $initial_transaction['id'], PDO::PARAM_INT);
            $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
            $stmt->execute();

            // Update main account balance
            $stmt = $pdo->prepare("UPDATE main_account SET $balance_column = $balance_column - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->bindParam(1, $creditor_balance, PDO::PARAM_STR);
            $stmt->bindParam(2, $initial_transaction['main_account_id'], PDO::PARAM_INT);
            $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $stmt->execute();

            // Delete all transactions related to this creditor
             $stmt = $pdo->prepare("DELETE FROM main_account_transactions WHERE transaction_of = 'creditor' AND reference_id = ? AND tenant_id = ? AND branch_id = ?");
             $stmt->bindParam(1, $creditor_id, PDO::PARAM_INT);
             $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
             $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
             $stmt->execute();
            }

            // Delete all creditor transactions first
            $stmt = $pdo->prepare("DELETE FROM creditor_transactions WHERE creditor_id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->bindParam(1, $creditor_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt->execute();

            // Delete the creditor
            $stmt = $pdo->prepare("DELETE FROM creditors WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $creditor_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        $pdo->commit();
        $_SESSION['success_message'] = __("creditor_deleted_successfully");
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = __("error_deleting_creditor") . ": " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}




// Validate transaction_id
$transaction_id = isset($_POST['transaction_id']) ? DbSecurity::validateInput($_POST['transaction_id'], 'int', ['min' => 0]) : null;

// Validate delete_transaction
$delete_transaction = isset($_POST['delete_transaction']) ? DbSecurity::validateInput($_POST['delete_transaction'], 'string', ['maxlength' => 255]) : null;

// Validate paid_to
$paid_to = isset($_POST['paid_to']) ? DbSecurity::validateInput($_POST['paid_to'], 'string', ['maxlength' => 255]) : null;

// Validate description
$description = isset($_POST['description']) ? DbSecurity::validateInput($_POST['description'], 'string', ['maxlength' => 255]) : null;

// Validate receipt
$receipt = isset($_POST['receipt']) ? DbSecurity::validateInput($_POST['receipt'], 'string', ['maxlength' => 255]) : null;

// Validate payment_date
$payment_date = isset($_POST['payment_date']) ? DbSecurity::validateInput($_POST['payment_date'], 'date') : null;

// Validate amount
$amount = isset($_POST['amount']) ? DbSecurity::validateInput($_POST['amount'], 'float', ['min' => 0]) : null;

// Validate creditor_id
$creditor_id = isset($_POST['creditor_id']) ? DbSecurity::validateInput($_POST['creditor_id'], 'int', ['min' => 0]) : null;

// Validate pay
$pay = isset($_POST['pay']) ? DbSecurity::validateInput($_POST['pay'], 'string', ['maxlength' => 255]) : null;

// Validate currency
$currency = isset($_POST['currency']) ? DbSecurity::validateInput($_POST['currency'], 'currency') : null;

// Validate balance
$balance = isset($_POST['balance']) ? DbSecurity::validateInput($_POST['balance'], 'float', ['min' => 0]) : null;

// Validate address
$address = isset($_POST['address']) ? DbSecurity::validateInput($_POST['address'], 'string', ['maxlength' => 255]) : null;

// Validate phone
$phone = isset($_POST['phone']) ? DbSecurity::validateInput($_POST['phone'], 'string', ['maxlength' => 255]) : null;

// Validate email
$email = isset($_POST['email']) ? DbSecurity::validateInput($_POST['email'], 'email') : null;

// Validate name
$name = isset($_POST['name']) ? DbSecurity::validateInput($_POST['name'], 'string', ['maxlength' => 255]) : null;

// Validate add_creditor
$add_creditor = isset($_POST['add_creditor']) ? DbSecurity::validateInput($_POST['add_creditor'], 'string', ['maxlength' => 255]) : null;

// Handle deactivate creditor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_creditor'])) {
    $creditor_id = isset($_POST['creditor_id']) ? intval($_POST['creditor_id']) : null;
    
    if (!$creditor_id) {
        $_SESSION['error_message'] = __("invalid_creditor");
        header('Location: ' . $redirect_url);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE creditors SET status = 'inactive' WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $creditor_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $_SESSION['success_message'] = __("creditor_deactivated_successfully");
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = __("error_deactivating_creditor") . ": " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle activate creditor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_creditor'])) {
    $creditor_id = isset($_POST['creditor_id']) ? intval($_POST['creditor_id']) : null;
    
    if (!$creditor_id) {
        $_SESSION['error_message'] = __("invalid_creditor");
        header('Location: ' . $redirect_url);
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE creditors SET status = 'active' WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $creditor_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $_SESSION['success_message'] = __("creditor_activated_successfully");
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = __("error_activating_creditor") . ": " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

?>