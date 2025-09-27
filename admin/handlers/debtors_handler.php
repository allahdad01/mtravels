<?php
require_once '../includes/conn.php';
require_once '../includes/db.php';
$tenant_id = $_SESSION['tenant_id'];
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



// Handle new debtor submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_debtor'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $balance = $_POST['balance'];
    $currency = $_POST['currency'];
    $main_account_id = $_POST['main_account_id'];
    $agreement_terms = isset($_POST['agreement_terms']) ? $_POST['agreement_terms'] : '';
    $skip_deduction = isset($_POST['skip_deduction']) ? true : false;
    
    try {
        $conn->begin_transaction();
        
        // Insert the debtor
        $stmt = $conn->prepare("INSERT INTO debtors (name, email, phone, address, balance, currency, main_account_id, agreement_terms, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssdsisi", $name, $email, $phone, $address, $balance, $currency, $main_account_id, $agreement_terms, $tenant_id);
        $stmt->execute();
        $debtor_id = $conn->insert_id;
        
        // Only process main account transactions if skip_deduction is false
        if (!$skip_deduction) {
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
            $stmt = $conn->prepare("SELECT $balance_column FROM main_account WHERE id = ? AND tenant_id = ?");
            $stmt->bind_param("ii", $main_account_id, $tenant_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $main_account = $result->fetch_assoc();
            
            if (!$main_account) {
                throw new Exception("Main account not found");
            }
            
            // Update main account balance (deduct amount)
            $new_main_balance = $main_account[$balance_column] - $balance;
            $stmt = $conn->prepare("UPDATE main_account SET $balance_column = ? WHERE id = ? AND tenant_id = ?");
            $stmt->bind_param("dii", $new_main_balance, $main_account_id, $tenant_id);
            $stmt->execute();
            
            // Create transaction records
            $transaction_type = 'debit';
            $description = "Initial debt balance for " . $name;
            $reference_number = 'DEBT-' . date('YmdHis') . '-' . $debtor_id;
            
            // Create debtor transaction record
            $stmt = $conn->prepare("INSERT INTO debtor_transactions (debtor_id, amount, currency, transaction_type, description, payment_date, reference_number, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $current_date = date('Y-m-d');
            $stmt->bind_param("idsssssi", $debtor_id, $balance, $currency, $transaction_type, $description, $current_date, $reference_number, $tenant_id);
            $stmt->execute();
            $debtor_transaction_id = $conn->insert_id;
            
            // Create main account transaction
            $tranasction_of = 'debtor';
            $stmt = $conn->prepare("INSERT INTO main_account_transactions (main_account_id, amount, balance, currency, type, description, transaction_of, reference_id, receipt, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("idsssssiss", $main_account_id, $balance, $new_main_balance, $currency, $transaction_type, $description, $tranasction_of, $debtor_transaction_id, $reference_number, $tenant_id);
            $stmt->execute();
        } else {
            // If skip_deduction is true, still create a transaction record for the debtor but not for main account
            $transaction_type = 'debit';
            $description = "Initial debt balance for " . $name . " (No deduction from main account)";
            $reference_number = 'DEBT-NODEDUCT-' . date('YmdHis') . '-' . $debtor_id;
            
            // Create debtor transaction record only
            $stmt = $conn->prepare("INSERT INTO debtor_transactions (debtor_id, amount, currency, transaction_type, description, payment_date, reference_number, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $current_date = date('Y-m-d');
            $stmt->bind_param("idsssssi", $debtor_id, $balance, $currency, $transaction_type, $description, $current_date, $reference_number, $tenant_id);
            $stmt->execute();
        }
        
        $conn->commit();
        $_SESSION['success_message'] = "Debtor added successfully!";
        $_SESSION['last_debtor_id'] = $debtor_id;
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error adding debtor: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay'])) {
    $debtor_id = $_POST['debtor_id'];
    $amount = $_POST['amount'];
    $currency = $_POST['currency'];
    $debtor_currency = $_POST['debtor_currency'];
    $payment_date = $_POST['payment_date'];
    $receipt = $_POST['receipt'];
    $transaction_type = 'credit';
    $description = $_POST['description'];
    $paid_to = $_POST['paid_to'];

    try {
        $conn->begin_transaction();
        
        // Get debtor information
        $stmt = $conn->prepare("SELECT balance, currency FROM debtors WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $debtor_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $debtor = $result->fetch_assoc();
        
        // If the payment currency is different from the debtor's currency, apply exchange rate
        $converted_amount = $amount;
        $exchange_info = '';
        if ($currency !== $debtor_currency) {
            if (!isset($_POST['exchange_rate']) || empty($_POST['exchange_rate'])) {
                throw new Exception("Exchange rate is required when currencies are different");
            }
            $exchange_rate = floatval($_POST['exchange_rate']);
            // Conversion logic: if payment is in AFS and debtor is USD, divide. If payment is USD and debtor is AFS, multiply.
            if ($currency == 'AFS' && $debtor_currency == 'USD') {
                $converted_amount = $amount / $exchange_rate;
                $exchange_info = " (Converted from $amount $currency at rate $exchange_rate to $converted_amount $debtor_currency)";
            } elseif ($currency == 'USD' && $debtor_currency == 'AFS') {
                $converted_amount = $amount * $exchange_rate;
                $exchange_info = " (Converted from $amount $currency at rate $exchange_rate to $converted_amount $debtor_currency)";
            } else {
                // General rule: if payment currency is not debtor currency, decide multiply or divide
                // If debtor currency is AFS, multiply; if payment currency is AFS, divide
                if ($debtor_currency == 'AFS') {
                    $converted_amount = $amount * $exchange_rate;
                    $exchange_info = " (Converted from $amount $currency at rate $exchange_rate to $converted_amount $debtor_currency)";
                } elseif ($currency == 'AFS') {
                    $converted_amount = $amount / $exchange_rate;
                    $exchange_info = " (Converted from $amount $currency at rate $exchange_rate to $converted_amount $debtor_currency)";
                } else {
                    // Fallback: multiply
                    $converted_amount = $amount * $exchange_rate;
                    $exchange_info = " (Converted from $amount $currency at rate $exchange_rate to $converted_amount $debtor_currency)";
                }
            }
            $description .= $exchange_info;
        }
        
        if ($debtor['balance'] >= $converted_amount) {
            // Create debtor transaction record
            $stmt = $conn->prepare("INSERT INTO debtor_transactions (debtor_id, amount, currency, transaction_type, description, payment_date, reference_number, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("idsssssi", $debtor_id, $converted_amount, $debtor_currency, $transaction_type, $description, $payment_date, $receipt, $tenant_id);
            $stmt->execute();
            $debtor_transaction_id = $conn->insert_id;
            
            // Update debtor balance
            $new_balance = $debtor['balance'] - $converted_amount;
            $stmt = $conn->prepare("UPDATE debtors SET balance = ? WHERE id = ? AND tenant_id = ?");
            $stmt->bind_param("dii", $new_balance, $debtor_id, $tenant_id);
            $stmt->execute();
            
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
            $stmt = $conn->prepare("SELECT $balance_column FROM main_account WHERE id = ? AND tenant_id = ?");
            $stmt->bind_param("ii", $paid_to, $tenant_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $main_account = $result->fetch_assoc();
            
            if (!$main_account) {
                throw new Exception("Main account not found");
            }
            
            // Update main account balance (add amount) - use original amount, not converted amount
            $new_main_balance = $main_account[$balance_column] + $amount;
            $stmt = $conn->prepare("UPDATE main_account SET $balance_column = ? WHERE id = ? AND tenant_id = ?");
            $stmt->bind_param("dii", $new_main_balance, $paid_to, $tenant_id);
            $stmt->execute();
            $tranasction_of = 'debtor';
            
            // Create main account transaction
            $main_transaction_description = $description;
            if ($currency !== $debtor_currency && empty($exchange_info)) {
                $main_transaction_description .= " (Payment in $currency for debtor in $debtor_currency)";
            }
            
            $stmt = $conn->prepare("INSERT INTO main_account_transactions (main_account_id, amount, balance, currency, type, description, transaction_of, reference_id, tenant_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("idsssssii", $paid_to, $amount, $new_main_balance, $currency, $transaction_type, $main_transaction_description, $tranasction_of, $debtor_transaction_id, $tenant_id);
            $stmt->execute();
            $main_transaction_id = $conn->insert_id;
            
            // Create notification for the payment
            $notificationMessage = sprintf(
                "Payment received from debtor: Amount %s %.2f - %s",
                $currency,
                $amount,
                $description
            );

            $notifStmt = $conn->prepare("
                INSERT INTO notifications 
                (transaction_id, transaction_type, message, status, created_at, tenant_id) 
                VALUES (?, 'debtor', ?, 'Unread', NOW(), ?)
            ");
            
            if (!$notifStmt->execute([$main_transaction_id, $notificationMessage, $tenant_id])) {
                throw new Exception("Failed to create notification");
            }
            
            $conn->commit();
            $_SESSION['success_message'] = "Payment processed successfully!";
        } else {
            throw new Exception("Insufficient balance");
        }
        
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error processing payment: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle debtor editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_debtor'])) {
    $debtor_id = $_POST['debtor_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $balance = $_POST['balance'];
    $currency = $_POST['currency'];
    $main_account_id = $_POST['main_account_id'];
    $agreement_terms = isset($_POST['agreement_terms']) ? $_POST['agreement_terms'] : '';
    
    try {
        $stmt = $conn->prepare("UPDATE debtors SET name = ?, email = ?, phone = ?, address = ?, balance = ?, currency = ?, main_account_id = ?, agreement_terms = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ssssdsssii", $name, $email, $phone, $address, $balance, $currency, $main_account_id, $agreement_terms, $debtor_id, $tenant_id);
        $stmt->execute();
        $_SESSION['success_message'] = "Debtor updated successfully!";
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error updating debtor: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle transaction deletion and reversal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_transaction'])) {
    $transaction_id = $_POST['transaction_id'];
    $debtor_id = $_POST['debtor_id'];
    $amount = $_POST['amount'];
    $currency = $_POST['currency'];
    
    try {
        $conn->begin_transaction();
        
        // Get transaction details
        $stmt = $conn->prepare("SELECT * FROM debtor_transactions WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $transaction_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $transaction = $result->fetch_assoc();
        
        if (!$transaction) {
            throw new Exception("Transaction not found");
        }
        
        // Get the linked main account transaction
        $stmt = $conn->prepare("SELECT * FROM main_account_transactions WHERE reference_id = ? AND transaction_of = 'debtor' AND tenant_id = ?");
        $stmt->bind_param("ii", $transaction_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $main_transaction = $result->fetch_assoc();
        
        if (!$main_transaction) {
            throw new Exception("Main account transaction not found");
        }

        // Use main account transaction's amount and currency for all main account updates
        $main_amount = $main_transaction['amount'];
        $main_currency = $main_transaction['currency'];

        // Update balances of all subsequent transactions
        $updateSubsequentStmt = $conn->prepare("
            UPDATE main_account_transactions 
            SET balance = balance - ?
            WHERE main_account_id = ? 
            AND currency = ? 
            AND created_at > ? 
            AND id != ?
        ");
        $updateSubsequentStmt->bind_param("dsssi", $main_amount, $main_transaction['main_account_id'], $main_currency, $main_transaction['created_at'], $main_transaction['id']);
        $updateSubsequentStmt->execute();
        
        // Get debtor information
        $stmt = $conn->prepare("SELECT balance FROM debtors WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $debtor_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $debtor = $result->fetch_assoc();
        
        // Update debtor balance (add amount back)
        $new_balance = $debtor['balance'] + $transaction['amount'];
        $stmt = $conn->prepare("UPDATE debtors SET balance = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("dii", $new_balance, $debtor_id, $tenant_id);
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
        $stmt = $conn->prepare("SELECT $balance_column FROM main_account WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $main_transaction['main_account_id'], $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $main_account = $result->fetch_assoc();
        
        if (!$main_account) {
            throw new Exception("Main account not found");
        }
        
        // Update main account balance (subtract main transaction amount)
        $new_main_balance = $main_account[$balance_column] - $main_amount;
        $stmt = $conn->prepare("UPDATE main_account SET $balance_column = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("dii", $new_main_balance, $main_transaction['main_account_id'], $tenant_id);
        $stmt->execute();
        
        // Delete the transactions
        $stmt = $conn->prepare("DELETE FROM debtor_transactions WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $transaction_id, $tenant_id);
        $stmt->execute();
        
        $stmt = $conn->prepare("DELETE FROM main_account_transactions WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $main_transaction['id'], $tenant_id);
        $stmt->execute();
        
        $conn->commit();
        $_SESSION['success_message'] = "Transaction reversed and deleted successfully!";
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error reversing transaction: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle debtor deactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_debtor'])) {
    $debtor_id = $_POST['debtor_id'];
    
    try {
        $stmt = $conn->prepare("UPDATE debtors SET status = 'inactive' WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $debtor_id, $tenant_id);
        $stmt->execute();
        $_SESSION['success_message'] = "Debtor deactivated successfully!";
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error deactivating debtor: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle debtor reactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_debtor'])) {
    $debtor_id = $_POST['debtor_id'];
    
    try {
        $stmt = $conn->prepare("UPDATE debtors SET status = 'active' WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $debtor_id, $tenant_id);
        $stmt->execute();
        $_SESSION['success_message'] = "Debtor reactivated successfully!";
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error reactivating debtor: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Determine which status to display
$status_filter = isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'inactive' : 'active';

// Pagination settings
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $items_per_page;

// Count total debtors for pagination
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM debtors WHERE status = ? AND tenant_id = ?");
$count_stmt->bind_param("si", $status_filter, $tenant_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_count = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_count / $items_per_page);

// Fetch debtors based on status filter with pagination
$stmt = $conn->prepare("SELECT * FROM debtors WHERE status = ? AND tenant_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("siii", $status_filter, $tenant_id, $items_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
$debtors = $result->fetch_all(MYSQLI_ASSOC);

// Calculate total debts by currency (using all debtors, not just the paginated ones)
$currency_totals = [];
$all_debtors_stmt = $conn->prepare("SELECT currency, SUM(balance) as total FROM debtors WHERE status = ? AND tenant_id = ? GROUP BY currency");
$all_debtors_stmt->bind_param("si", $status_filter, $tenant_id);
$all_debtors_stmt->execute();
$all_debtors_result = $all_debtors_stmt->get_result();
while ($row = $all_debtors_result->fetch_assoc()) {
    $currency_totals[$row['currency']] = $row['total'];
}

// Fetch main accounts
$stmt = $pdo->prepare("SELECT id, name FROM main_account where status = 'active' AND tenant_id = ?");
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->execute();
$main_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>