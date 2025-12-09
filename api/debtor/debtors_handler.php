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
?>