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

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
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

/**
 * Support JSON/alternate payloads from programmatic clients.
 * When the request body is JSON (common with fetch/AJAX) PHP leaves $_POST empty,
 * so we decode it and hydrate $_POST to let the existing form-based code run.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST)) {
    $raw_body = file_get_contents('php://input');
    $json_payload = json_decode($raw_body, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json_payload)) {
        foreach ($json_payload as $key => $value) {
            if (!isset($_POST[$key])) {
                $_POST[$key] = $value;
            }
        }
    }
}

// Allow external callers to pass CSRF token as f_token (legacy field name)
if (isset($_POST['f_token']) && !isset($_POST['csrf_token'])) {
    $_POST['csrf_token'] = $_POST['f_token'];
}

// If an explicit action key is provided (API usage), mirror it to the expected flag name
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && is_string($_POST['action'])
) {
    $normalized_action = preg_replace('/[^a-z0-9_]/i', '', $_POST['action']);
    if ($normalized_action !== '' && !isset($_POST[$normalized_action])) {
        $_POST[$normalized_action] = true;
    }
}

// Auto-flag add_debtor for API-style payloads so the main handler executes
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !isset($_POST['add_debtor'])
    && !isset($_POST['pay'])
    && !isset($_POST['edit_debtor'])
    && !isset($_POST['delete_transaction'])
    && !isset($_POST['deactivate_debtor'])
    && !isset($_POST['reactivate_debtor'])
    && !isset($_POST['delete_debtor'])
    && isset($_POST['name'], $_POST['balance'], $_POST['currency'], $_POST['main_account_id'])
) {
    $_POST['add_debtor'] = true;
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
        $pdo->beginTransaction();

        // Insert the debtor
         $stmt = $pdo->prepare("INSERT INTO debtors (name, email, phone, address, balance, currency, main_account_id, agreement_terms, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
         $stmt->execute([$name, $email, $phone, $address, $balance, $currency, $main_account_id, $agreement_terms, $tenant_id, $branch_id]);
        $debtor_id = $pdo->lastInsertId();

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
            $stmt = $pdo->prepare("SELECT $balance_column FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->execute([$main_account_id, $tenant_id, $branch_id]);
            $main_account = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$main_account) {
                throw new Exception("Main account not found");
            }

            // Update main account balance (deduct amount)
            $new_main_balance = $main_account[$balance_column] - $balance;
            $stmt = $pdo->prepare("UPDATE main_account SET $balance_column = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->execute([$new_main_balance, $main_account_id, $tenant_id, $branch_id]);

            // Create transaction records
            $transaction_type = 'debit';
            $description = "Initial debt balance for " . $name;
            $reference_number = 'DEBT-' . date('YmdHis') . '-' . $debtor_id;

            // Create debtor transaction record
            $stmt = $pdo->prepare("INSERT INTO debtor_transactions (debtor_id, amount, currency, transaction_type, description, payment_date, reference_number, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $current_date = date('Y-m-d');
            $stmt->execute([$debtor_id, $balance, $currency, $transaction_type, $description, $current_date, $reference_number, $tenant_id, $branch_id]);
            $debtor_transaction_id = $pdo->lastInsertId();

            // Create main account transaction
            $tranasction_of = 'debtor';
            $stmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, amount, balance, currency, type, description, transaction_of, reference_id, receipt, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$main_account_id, $balance, $new_main_balance, $currency, $transaction_type, $description, $tranasction_of, $debtor_transaction_id, $reference_number, $tenant_id, $branch_id]);
        } else {
            // If skip_deduction is true, still create a transaction record for the debtor but not for main account
            $transaction_type = 'debit';
            $description = "Initial debt balance for " . $name . " (No deduction from main account)";
            $reference_number = 'DEBT-NODEDUCT-' . date('YmdHis') . '-' . $debtor_id;

            // Create debtor transaction record only
            $stmt = $pdo->prepare("INSERT INTO debtor_transactions (debtor_id, amount, currency, transaction_type, description, payment_date, reference_number, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $current_date = date('Y-m-d');
            $stmt->execute([$debtor_id, $balance, $currency, $transaction_type, $description, $current_date, $reference_number, $tenant_id, $branch_id]);
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Debtor added successfully!";
        $_SESSION['last_debtor_id'] = $debtor_id;
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error adding debtor: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle debtor deactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_debtor'])) {
    $debtor_id = $_POST['debtor_id'];
    
    try {
        // Check if debtor exists and belongs to current tenant/branch
        $stmt = $pdo->prepare("SELECT * FROM debtors WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$debtor_id, $tenant_id, $branch_id]);
        $debtor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$debtor) {
            throw new Exception("Debtor not found");
        }
        
        if ($debtor['status'] === 'inactive') {
            throw new Exception("Debtor is already inactive");
        }
        
        // Check if debtor has zero balance (required for deactivation)
        if ($debtor['balance'] > 0) {
            throw new Exception("Cannot deactivate debtor with outstanding balance. Please settle the balance first.");
        }
        
        // Deactivate the debtor
        $updateStmt = $pdo->prepare("UPDATE debtors SET status = 'inactive' WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $updateStmt->execute([$debtor_id, $tenant_id, $branch_id]);
        
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
        // Check if debtor exists and belongs to current tenant/branch
        $stmt = $pdo->prepare("SELECT * FROM debtors WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->execute([$debtor_id, $tenant_id, $branch_id]);
        $debtor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$debtor) {
            throw new Exception("Debtor not found");
        }
        
        if ($debtor['status'] === 'active') {
            throw new Exception("Debtor is already active");
        }
        
        // Reactivate the debtor
        $updateStmt = $pdo->prepare("UPDATE debtors SET status = 'active' WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $updateStmt->execute([$debtor_id, $tenant_id, $branch_id]);
        
        $_SESSION['success_message'] = "Debtor reactivated successfully!";
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error reactivating debtor: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay'])) {
    $debtor_id = $_POST['debtor_id'];
    
    // Validate amount is numeric
    if (!isset($_POST['amount']) || !is_numeric($_POST['amount']) || floatval($_POST['amount']) <= 0) {
        throw new Exception("Invalid payment amount: must be a positive number");
    }
    $amount = floatval($_POST['amount']);
    
    $currency = $_POST['currency'];
    $payment_date = $_POST['payment_date'];
    $description = $_POST['description'];
    $paid_to = $_POST['paid_to'];
    
    // Validate exchange rate is numeric
    $exchange_rate = isset($_POST['exchange_rate']) && !empty($_POST['exchange_rate']) ? $_POST['exchange_rate'] : 1;
    if (!is_numeric($exchange_rate) || floatval($exchange_rate) <= 0) {
        throw new Exception("Invalid exchange rate: must be a positive number");
    }
    $exchange_rate = floatval($exchange_rate);

    try {
        $pdo->beginTransaction();

        // Get debtor info
        $debtorStmt = $pdo->prepare("SELECT * FROM debtors WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $debtorStmt->execute([$debtor_id, $tenant_id, $branch_id]);
        $debtor = $debtorStmt->fetch(PDO::FETCH_ASSOC);

        if (!$debtor) {
            throw new Exception("Debtor not found");
        }

        // Calculate amount in debtor's currency if different
        $amount_in_debtor_currency = $amount;
        if ($currency !== $debtor['currency']) {
            $amount_in_debtor_currency = $amount * $exchange_rate;
        }

        // Update debtor balance
        $new_balance = $debtor['balance'] - $amount_in_debtor_currency;
        $updateStmt = $pdo->prepare("UPDATE debtors SET balance = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $updateStmt->execute([$new_balance, $debtor_id, $tenant_id, $branch_id]);

        // Create transaction record
        $reference_number = 'PAY-' . date('YmdHis') . '-' . $debtor_id;
        $transStmt = $pdo->prepare("INSERT INTO debtor_transactions (debtor_id, amount, currency, transaction_type, description, payment_date, reference_number, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $transStmt->execute([$debtor_id, $amount_in_debtor_currency, $debtor['currency'], 'credit', $description, $payment_date, $reference_number, $tenant_id, $branch_id]);
        $transaction_id = $pdo->lastInsertId();

        // Update main account balance
        $balance_column = strtolower($debtor['currency']) . '_balance';
        if ($debtor['currency'] == 'DARHAM') {
            $balance_column = 'darham_balance';
        } elseif ($debtor['currency'] == 'EUR') {
            $balance_column = 'euro_balance';
        } elseif ($debtor['currency'] == 'USD') {
            $balance_column = 'usd_balance';
        } elseif ($debtor['currency'] == 'AFS') {
            $balance_column = 'afs_balance';
        }

        $mainAcctStmt = $pdo->prepare("SELECT $balance_column FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $mainAcctStmt->execute([$paid_to, $tenant_id, $branch_id]);
        $main_account = $mainAcctStmt->fetch(PDO::FETCH_ASSOC);

        if (!$main_account) {
            throw new Exception("Main account not found");
        }

        $new_main_balance = $main_account[$balance_column] + $amount_in_debtor_currency;
        $updateMainStmt = $pdo->prepare("UPDATE main_account SET $balance_column = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $updateMainStmt->execute([$new_main_balance, $paid_to, $tenant_id, $branch_id]);

        // Create main account transaction
         $mainTransStmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, amount, balance, currency, type, description, transaction_of, reference_id, receipt, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
         $mainTransStmt->execute([$paid_to, $amount_in_debtor_currency, $new_main_balance, $debtor['currency'], 'credit', $description, 'debtor', $transaction_id, $reference_number, $tenant_id, $branch_id]);
         $main_transaction_id = $pdo->lastInsertId();

         // Create notification
         $notificationMessage = sprintf(
             "Payment of %s %s received from debtor %s. Remaining balance: %s %s",
             number_format($amount_in_debtor_currency, 2),
             $debtor['currency'],
             $debtor['name'],
             number_format($new_balance, 2),
             $debtor['currency']
         );

         $notifStmt = $pdo->prepare("INSERT INTO notifications (transaction_id, transaction_type, message, status, created_at, tenant_id, branch_id) VALUES (?, 'debtor', ?, 'Unread', NOW(), ?, ?)");
         $notifStmt->execute([$main_transaction_id, $notificationMessage, $tenant_id, $branch_id]);

        $pdo->commit();
        $_SESSION['success_message'] = "Payment processed successfully!";
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error processing payment: " . $e->getMessage();
        header('Location: ' . $redirect_url);
        exit();
    }
}
?>