<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';
// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
if (!isset($_SESSION['tenant_id'])) {
    $_SESSION['error_message'] = "Tenant ID not found. Please log in again.";
    header("Location: ../login.php");
    exit();
}

require_once '../includes/db.php';
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

// Handle deposit submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_deposit'])) {
    $customer_id = $_POST['customer_id'];
    $amount = $_POST['amount'];
    $currency = $_POST['currency'];
    $notes = $_POST['notes'];
    $reference = $_POST['reference'];
    $main_account_id = $_POST['main_account_id'];
    
    try {
        $pdo->beginTransaction();

        // Get customer name
        $stmt = $pdo->prepare("SELECT name FROM customers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        // Insert the deposit transaction
        $stmt = $pdo->prepare("INSERT INTO sarafi_transactions (customer_id, amount, currency, type, notes, reference_number, tenant_id, branch_id) VALUES (?, ?, ?, 'deposit', ?, ?, ?, ?)");
        $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $amount, PDO::PARAM_STR);
        $stmt->bindParam(3, $currency, PDO::PARAM_STR);
        $stmt->bindParam(4, $notes, PDO::PARAM_STR);
        $stmt->bindParam(5, $reference, PDO::PARAM_STR);
        $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $transaction_id = $pdo->lastInsertId();
        
        // First check if wallet exists
        $stmt = $pdo->prepare("SELECT id FROM customer_wallets WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $currency, PDO::PARAM_STR);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // Update existing wallet
            $stmt = $pdo->prepare("UPDATE customer_wallets SET balance = balance + ? WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
            $stmt->bindParam(1, $amount, PDO::PARAM_STR);
            $stmt->bindParam(2, $customer_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $currency, PDO::PARAM_STR);
            $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        } else {
            // Create new wallet
            $stmt = $pdo->prepare("INSERT INTO customer_wallets (customer_id, currency, balance, tenant_id, branch_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $currency, PDO::PARAM_STR);
            $stmt->bindParam(3, $amount, PDO::PARAM_STR);
            $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        }
        $stmt->execute();

        // Get current main account balance
        $balanceField = $currency === 'USD' ? 'usd_balance' : ($currency === 'AFS' ? 'afs_balance' : ($currency === 'EUR' ? 'euro_balance' : 'darham_balance'));
        $stmt = $pdo->prepare("SELECT $balanceField as current_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $main_account_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $balanceResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $newBalance = $balanceResult['current_balance'] + $amount;

        // Update main account balance
        $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $newBalance, PDO::PARAM_STR);
        $stmt->bindParam(2, $main_account_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $transaction_of = 'deposit_sarafi';
        // Record main account transaction
        $stmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id, branch_id) VALUES (?, 'credit', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bindParam(1, $main_account_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $amount, PDO::PARAM_STR);
        $stmt->bindParam(3, $currency, PDO::PARAM_STR);
        $stmt->bindParam(4, $notes, PDO::PARAM_STR);
        $stmt->bindParam(5, $transaction_of, PDO::PARAM_STR);
        $stmt->bindParam(6, $transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $newBalance, PDO::PARAM_STR);
        $stmt->bindParam(8, $reference, PDO::PARAM_STR);
        $stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(10, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $main_transaction_id = $pdo->lastInsertId();

        // Create notification
        $notificationMessage = sprintf(
            __('new_deposit_notification'),
            $customer['name'],
            $currency,
            $amount,
            $reference
        );

        $stmt = $pdo->prepare("INSERT INTO notifications (transaction_id, transaction_type, message, status, created_at, tenant_id, branch_id) VALUES (?, ?, ?, 'Unread', NOW(), ?, ?)");
        $stmt->bindParam(1, $main_transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $transaction_of, PDO::PARAM_STR);
        $stmt->bindParam(3, $notificationMessage, PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        // Handle receipt upload if provided
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            $file_extension = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
            $new_filename = 'receipt_' . $transaction_id . '_' . time() . '.' . $file_extension;
            $upload_path = '../uploads/receipts/' . $new_filename;

            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $upload_path)) {
                // Update transaction with receipt path
                $stmt = $pdo->prepare("UPDATE sarafi_transactions SET receipt_path = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt->bindParam(1, $new_filename, PDO::PARAM_STR);
                $stmt->bindParam(2, $transaction_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                $stmt->execute();
            }
        }

        $pdo->commit();
        $_SESSION['success_message'] = __('deposit_success');
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $pdo->rollback();
        $_SESSION['error_message'] = sprintf(__('processing_error'), __('deposit'), $e->getMessage());
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Handle withdrawal submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_withdrawal'])) {
    $customer_id = $_POST['customer_id'];
    $amount = $_POST['amount'];
    $currency = $_POST['currency'];
    $notes = $_POST['notes'];
    $reference = $_POST['reference'];
    $main_account_id = $_POST['main_account_id'];
    
    try {
        $pdo->beginTransaction();

        // Get customer name
        $stmt = $pdo->prepare("SELECT name FROM customers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if customer has sufficient balance
        $stmt = $pdo->prepare("SELECT balance FROM customer_wallets WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $currency, PDO::PARAM_STR);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$wallet || $wallet['balance'] < $amount) {
            throw new Exception(__('insufficient_balance'));
        }

        // Get current main account balance
        $balanceField = $currency === 'USD' ? 'usd_balance' : ($currency === 'AFS' ? 'afs_balance' : ($currency === 'EUR' ? 'euro_balance' : 'darham_balance'));
        $stmt = $pdo->prepare("SELECT $balanceField as current_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $main_account_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $balanceResult = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$balanceResult || $balanceResult['current_balance'] < $amount) {
            throw new Exception(__('insufficient_main_account_balance'));
        }

        $newBalance = $balanceResult['current_balance'] - $amount;

        // Insert the withdrawal transaction
        $stmt = $pdo->prepare("INSERT INTO sarafi_transactions (customer_id, amount, currency, type, notes, reference_number, tenant_id, branch_id) VALUES (?, ?, ?, 'withdrawal', ?, ?, ?, ?)");
        $stmt->bindParam(1, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $amount, PDO::PARAM_STR);
        $stmt->bindParam(3, $currency, PDO::PARAM_STR);
        $stmt->bindParam(4, $notes, PDO::PARAM_STR);
        $stmt->bindParam(5, $reference, PDO::PARAM_STR);
        $stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $transaction_id = $pdo->lastInsertId();

        // Update customer wallet balance
        $stmt = $pdo->prepare("UPDATE customer_wallets SET balance = balance - ? WHERE customer_id = ? AND currency = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $amount, PDO::PARAM_STR);
        $stmt->bindParam(2, $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $currency, PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        // Update main account balance
        $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $newBalance, PDO::PARAM_STR);
        $stmt->bindParam(2, $main_account_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $transaction_of = 'withdrawal_sarafi';
        // Record main account transaction
        $stmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id, branch_id) VALUES (?, 'debit', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bindParam(1, $main_account_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $amount, PDO::PARAM_STR);
        $stmt->bindParam(3, $currency, PDO::PARAM_STR);
        $stmt->bindParam(4, $notes, PDO::PARAM_STR);
        $stmt->bindParam(5, $transaction_of, PDO::PARAM_STR);
        $stmt->bindParam(6, $transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(7, $newBalance, PDO::PARAM_STR);
        $stmt->bindParam(8, $reference, PDO::PARAM_STR);
        $stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(10, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $main_transaction_id = $pdo->lastInsertId();

        // Create notification
        $notificationMessage = sprintf(
            __('new_withdrawal_notification'),
            $customer['name'],
            $currency,
            $amount,
            $reference
        );

        $stmt = $pdo->prepare("INSERT INTO notifications (transaction_id, transaction_type, message, status, created_at, tenant_id, branch_id) VALUES (?, ?, ?, 'Unread', NOW(), ?, ?)");
        $stmt->bindParam(1, $main_transaction_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $transaction_of, PDO::PARAM_STR);
        $stmt->bindParam(3, $notificationMessage, PDO::PARAM_STR);
        $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        // Handle receipt upload if provided
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            $file_extension = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
            $new_filename = 'receipt_' . $transaction_id . '_' . time() . '.' . $file_extension;
            $upload_path = '../uploads/receipts/' . $new_filename;

            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $upload_path)) {
                // Update transaction with receipt path
                $stmt = $pdo->prepare("UPDATE sarafi_transactions SET receipt_path = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                $stmt->bindParam(1, $new_filename, PDO::PARAM_STR);
                $stmt->bindParam(2, $transaction_id, PDO::PARAM_INT);
                $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                $stmt->execute();
            }
        }

        $pdo->commit();
        $_SESSION['success_message'] = __('withdrawal_success');
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $pdo->rollback();
        $_SESSION['error_message'] = sprintf(__('processing_error'), __('withdrawal'), $e->getMessage());
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Include handlers
require_once 'includes/hawala_handler.php';
require_once 'includes/exchange_handler.php';

// Handle hawala transfer submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_hawala'])) {
    $data = [
        'sender_id' => $_POST['sender_id'],
        'send_amount' => $_POST['send_amount'],
        'send_currency' => $_POST['send_currency'],
        'notes' => $_POST['notes'],
        'reference' => uniqid('HWL'),
        'secret_code' => $_POST['secret_code'],
        'commission_amount' => $_POST['commission_amount'],
        'commission_currency' => $_POST['commission_currency'],
        'main_account_id' => $_POST['main_account_id']
    ];
    
    try {
        $pdo->beginTransaction();

        // Get sender name
        $stmt = $pdo->prepare("SELECT name FROM customers WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $data['sender_id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $sender = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify currencies match
        if ($data['send_currency'] !== $data['commission_currency']) {
            throw new Exception(__('commission_currency_mismatch'));
        }

        // Calculate net amount to deduct from main account (transfer amount - commission)
        $net_amount = $data['send_amount'] - $data['commission_amount'];

        // Get current main account balance
        $balanceField = $data['send_currency'] === 'USD' ? 'usd_balance' : ($data['send_currency'] === 'AFS' ? 'afs_balance' : ($data['send_currency'] === 'EUR' ? 'euro_balance' : 'darham_balance'));
        $stmt = $pdo->prepare("SELECT $balanceField as current_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $data['main_account_id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $stmt->execute();
        $balanceResult = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$balanceResult || $balanceResult['current_balance'] < $net_amount) {
            throw new Exception(__('insufficient_main_account_balance_hawala'));
        }

        $newBalance = $balanceResult['current_balance'] - $net_amount;

        // Update main account balance with net amount
        $stmt = $pdo->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $stmt->bindParam(1, $newBalance, PDO::PARAM_STR);
        $stmt->bindParam(2, $data['main_account_id'], PDO::PARAM_INT);
        $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
        $stmt->execute();

        // Process the hawala transfer
        $result = processHawalaTransfer($pdo, $data);

        if ($result['success']) {
            // Record main account transaction for net hawala transfer amount
            $description = sprintf(__('hawala_transfer_description'),
                $data['reference'],
                number_format($data['send_amount'], 2), $data['send_currency'],
                number_format($data['commission_amount'], 2), $data['commission_currency'],
                number_format($net_amount, 2), $data['send_currency']
            );
            $transaction_of = 'hawala_sarafi';
            $stmt = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id, branch_id)
            VALUES (?, 'debit', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bindParam(1, $data['main_account_id'], PDO::PARAM_INT);
            $stmt->bindParam(2, $net_amount, PDO::PARAM_STR);
            $stmt->bindParam(3, $data['send_currency'], PDO::PARAM_STR);
            $stmt->bindParam(4, $data['notes'], PDO::PARAM_STR);
            $stmt->bindParam(5, $transaction_of, PDO::PARAM_STR);
            $stmt->bindParam(6, $result['sender_transaction_id'], PDO::PARAM_INT);
            $stmt->bindParam(7, $newBalance, PDO::PARAM_STR);
            $stmt->bindParam(8, $data['reference'], PDO::PARAM_STR);
            $stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(10, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $main_transaction_id = $pdo->lastInsertId();

            // Create notification
            $notificationMessage = sprintf(
                __('new_hawala_transfer_notification'),
                $sender['name'],
                $data['send_currency'], $data['send_amount'],
                $data['commission_currency'], $data['commission_amount'],
                $data['send_currency'], $net_amount,
                $data['reference']
            );

            $stmt = $pdo->prepare("INSERT INTO notifications (transaction_id, transaction_type, message, status, created_at, tenant_id, branch_id) VALUES (?, ?, ?, 'Unread', NOW(), ?, ?)");
            $stmt->bindParam(1, $main_transaction_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $transaction_of, PDO::PARAM_STR);
            $stmt->bindParam(3, $notificationMessage, PDO::PARAM_STR);
            $stmt->bindParam(4, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
            $stmt->execute();

            $pdo->commit();
            $_SESSION['success_message'] = $result['message'];
        } else {
            throw new Exception($result['message']);
        }
    } catch (Exception $e) {
        $pdo->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header('Location: ' . $redirect_url);
    exit();
}

// Handle currency exchange submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_exchange'])) {
    $data = [
        'customer_id' => $_POST['customer_id'],
        'from_amount' => $_POST['from_amount'],
        'from_currency' => $_POST['from_currency'],
        'to_amount' => $_POST['to_amount'],
        'to_currency' => $_POST['to_currency'],
        'rate' => $_POST['rate'],
        'notes' => $_POST['notes']
    ];

    $result = processCurrencyExchange($pdo, $data);

    if ($result['success']) {
        $_SESSION['success_message'] = $result['message'];
    } else {
        $_SESSION['error_message'] = $result['message'];
    }
    header('Location: ' . $redirect_url);
    exit();
}

// Fetch customers
$stmt = $pdo->prepare("SELECT * FROM customers WHERE status = 'active' AND tenant_id = ? AND branch_id = ? ORDER BY created_at DESC");
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total balances by currency
$currency_totals = [];
$stmt = $pdo->prepare("SELECT currency, SUM(balance) as total FROM customer_wallets WHERE tenant_id = ? AND branch_id = ? GROUP BY currency");
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($result as $row) {
    $currency_totals[$row['currency']] = $row['total'];
}

?>




<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../css/sarfi/styles.css">


    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <!-- [ Sarafi Management ] start -->
                                <div class="col-sm-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5><?= __('sarafi') ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <!-- Add toast container after opening body tag -->
                                            <div class="toast-container"></div>

                                            <!-- Success/Error Messages -->
                                            <?php if (isset($success_message)): ?>
                                                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($error_message)): ?>
                                                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                                            <?php endif; ?>

                                            <!-- Currency Totals -->
                                            <div class="row mb-4">
                                                <?php foreach ($currency_totals as $currency => $total): ?>
                                                <div class="col-md-3 col-sm-6 mb-3">
                                                    <div class="currency-card h-100">
                                                        <div class="d-flex align-items-center">
                                                            <div class="currency-icon">
                                                                <i class="feather icon-credit-card"></i>
                                                            </div>
                                                            <div>
                                                                <h3 class="mb-1"><?php echo number_format($total, 2); ?></h3>
                                                                <p class="mb-0 text-muted"><?php echo __($currency); ?> <?= __('total') ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="mt-3">
                                                            <div class="progress" style="height: 4px;">
                                                                <div class="progress-bar bg-primary" role="progressbar" style="width: 100%"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <!-- Action Buttons -->
                                            <div class="row mb-4">
                                                <div class="col-md-12 action-buttons">
                                                    <a href="customers.php" class="btn btn-primary">
                                                        <i class="feather icon-users"></i> <?= __('view_customers') ?>
                                                    </a>
                                                    <button class="btn btn-success" data-toggle="modal" data-target="#customerModal">
                                                        <i class="feather icon-user-plus"></i> <?= __('new_customer') ?>
                                                    </button>
                                                    <button class="btn btn-info" data-toggle="modal" data-target="#depositModal">
                                                        <i class="feather icon-plus"></i> <?= __('new_deposit') ?>
                                                    </button>
                                                    <button class="btn btn-warning text-white" data-toggle="modal" data-target="#withdrawalModal">
                                                        <i class="feather icon-minus"></i> <?= __('new_withdrawal') ?>
                                                    </button>
                                                    <button class="btn btn-primary" data-toggle="modal" data-target="#hawalaModal">
                                                        <i class="feather icon-repeat"></i> <?= __('hawala_transfer') ?>
                                                    </button>
                                                    <button class="btn btn-success" data-toggle="modal" data-target="#exchangeModal">
                                                        <i class="feather icon-refresh-cw"></i> <?= __('currency_exchange') ?>
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Transactions Table -->
                                            <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="feather icon-list mr-2"></i><?= __('recent_transactions') ?>
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="sarafiTransactionsTable">
                <thead>
                    <tr>
                        <th><?= __('date') ?></th>
                        <th><?= __('customer') ?></th>
                        <th><?= __('type') ?></th>
                        <th><?= __('amount') ?></th>
                        <th><?= __('currency') ?></th>
                        <th><?= __('reference') ?></th>
                        <th><?= __('status') ?></th>
                        <th class="no-sort text-center"><?= __('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch recent transactions
                    $stmt = $pdo->prepare("
                        SELECT t.*, c.name as customer_name
                        FROM sarafi_transactions t
                        JOIN customers c ON t.customer_id = c.id
                        WHERE t.tenant_id = ? AND t.branch_id = ? AND c.branch_id = ?
                        ORDER BY t.created_at DESC
                        LIMIT 50
                    ");
                    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
                    $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
                    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($transactions as $transaction):
                        $type_class = '';
                        $type_icon = '';
                        switch ($transaction['type']) {
                            case 'deposit':
                                $type_class = 'text-success';
                                $type_icon = 'icon-plus-circle';
                                break;
                            case 'withdrawal':
                                $type_class = 'text-warning';
                                $type_icon = 'icon-minus-circle';
                                break;
                            case 'hawala_send':
                            case 'hawala_receive':
                                $type_class = 'text-info';
                                $type_icon = 'icon-repeat';
                                break;
                            case 'exchange':
                                $type_class = 'text-primary';
                                $type_icon = 'icon-refresh-cw';
                                break;
                        }
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <i class="feather icon-calendar text-muted mr-2"></i>
                                <?= date('Y-m-d H:i', strtotime($transaction['created_at'])) ?>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <i class="feather icon-user text-muted mr-2"></i>
                                <?= htmlspecialchars($transaction['customer_name']) ?>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center <?= $type_class ?>">
                                <i class="feather <?= $type_icon ?> mr-2"></i>
                                <?= __($transaction['type']) ?>
                            </div>
                        </td>
                        <td>
                            <strong><?= number_format($transaction['amount'], 2) ?></strong>
                        </td>
                        <td>
                            <span class="badge badge-light">
                                <?= __($transaction['currency']) ?>
                            </span>
                        </td>
                        <td>
                            <code><?= htmlspecialchars($transaction['reference_number']) ?></code>
                        </td>
                        <td>
                            <span class="badge badge-<?= $transaction['status'] === 'completed' ? 'success' : ($transaction['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                <?= __($transaction['status']) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <?php if (!empty($transaction['receipt_path'])): ?>
                                <a href="../uploads/receipts/<?= htmlspecialchars($transaction['receipt_path']) ?>" 
                                   class="btn btn-sm btn-info" data-toggle="tooltip" title="<?= __('view_receipt') ?>" target="_blank">
                                    <i class="feather icon-file"></i>
                                </a>
                                <?php endif; ?>
                                
                                <a href="#" class="btn btn-sm btn-info view-transaction" 
                                   data-id="<?= $transaction['id'] ?>" data-toggle="tooltip" 
                                   title="<?= __('view_details') ?>">
                                    <i class="feather icon-eye"></i>
                                </a>


                                <?php if ($transaction['type'] === 'deposit'): ?>
                                <button class="btn btn-sm btn-danger" 
                                        onclick="deleteDeposit(<?= $transaction['id'] ?>, <?= $transaction['amount'] ?>)" 
                                        data-toggle="tooltip" title="<?= __('delete') ?>">
                                    <i class="feather icon-trash-2"></i>
                                </button>
                                <?php endif; ?>

                                <?php if ($transaction['type'] === 'withdrawal'): ?>
                                <button class="btn btn-sm btn-danger" 
                                        onclick="deleteWithdrawal(<?= $transaction['id'] ?>, <?= $transaction['amount'] ?>)" 
                                        data-toggle="tooltip" title="<?= __('delete') ?>">
                                    <i class="feather icon-trash-2"></i>
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($transaction['type'] === 'hawala_send'): ?>
                                <button class="btn btn-sm btn-danger" 
                                        onclick="deleteHawala(<?= $transaction['id'] ?>, <?= $transaction['amount'] ?>)" 
                                        data-toggle="tooltip" title="<?= __('delete') ?>">
                                    <i class="feather icon-trash-2"></i>
                                </button>
                                <?php endif; ?>

                                <?php if ($transaction['type'] === 'exchange'): ?>
                                <button class="btn btn-sm btn-danger delete-exchange" 
                                        data-id="<?= $transaction['id'] ?>" 
                                        data-toggle="tooltip" title="<?= __('delete') ?>">
                                    <i class="feather icon-trash-2"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
                                        </div>
                                    </div>
                                </div>
                                <!-- [ Sarafi Management ] end -->
                            </div>
                            <!-- [ Main Content ] end -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    
    <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>

    <script>
    // Initialize Select2 for customer dropdowns
    function initializeSelect2() {
        // Initialize Select2 for all customer dropdowns
        $('select[name="customer_id"], select[name="sender_id"]').each(function() {
            $(this).select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent: $(this).closest('.modal-body'),
                placeholder: '<?= __("select_customer") ?>',
                allowClear: true
            });
        });
    }

    // Initialize DataTable for sarafi transactions
    $(document).ready(function() {
        // Initialize Select2
        initializeSelect2();

        // Reinitialize Select2 when any modal is shown
        $('.modal').on('shown.bs.modal', function() {
            initializeSelect2();
        });

        $('#sarafiTransactionsTable').DataTable({
            responsive: true,
            language: {
                search: "<?= __('search') ?>:",
                lengthMenu: "<?= __('show') ?> _MENU_ <?= __('entries') ?>",
                info: "<?= __('showing') ?> _START_ <?= __('to') ?> _END_ <?= __('of') ?> _TOTAL_ <?= __('entries') ?>",
                infoEmpty: "<?= __('showing') ?> 0 <?= __('to') ?> 0 <?= __('of') ?> 0 <?= __('entries') ?>",
                infoFiltered: "(<?= __('filtered_from') ?> _MAX_ <?= __('total_entries') ?>)",
                paginate: {
                    first: "<?= __('first') ?>",
                    last: "<?= __('last') ?>",
                    next: "<?= __('next') ?>",
                    previous: "<?= __('previous') ?>"
                }
            },
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "<?= __('all') ?>"]],
            columnDefs: [
                { targets: 'no-sort', orderable: false }
            ],
            order: [[0, 'desc']], // Sort by date (first column) in descending order
            drawCallback: function() {
                // Reinitialize tooltips after DataTable draws
                initTooltips();
            }
        });
        
        // Initialize tooltips
        function initTooltips() {
            // First destroy any existing tooltips to prevent duplicates
            $('[data-toggle="tooltip"]').tooltip('dispose');
            // Then initialize tooltips
            $('[data-toggle="tooltip"]').tooltip({
                trigger: 'hover',
                container: 'body'
            });
        }
        
        // Initial tooltip initialization
        initTooltips();
        
        $(document).off('click', '.delete-exchange').on('click', '.delete-exchange', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const transactionId = $btn.data('id');

        if (confirm('<?= __("confirm_delete_exchange") ?>')) {
            $btn.prop('disabled', true); // disable temporarily
            $.ajax({
                url: 'delete_sarafi_exchange.php',
                type: 'POST',
                data: { transaction_id: transactionId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert(response.message || '<?= __("error_deleting_exchange") ?>');
                    }
                },
                error: function() {
                    alert('<?= __("error_deleting_exchange") ?>');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        }
    });

    });

    // Handle view transaction click
    $(document).on('click', '.view-transaction', function(e) {
        e.preventDefault();
        const transactionId = $(this).data('id');
        viewTransaction(transactionId);
    });

    // Function to view transaction details
    function viewTransaction(transactionId) {
        $('#transactionDetailsModal').modal('show');
        $('.print-transaction').hide();
        
        // Show loading state
        $('#transactionDetailsContent').html(`
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only"><?= __("loading") ?></span>
                </div>
            </div>
        `);
        
        // Fetch transaction details
        $.ajax({
            url: 'view_sarafi_transaction.php',
            type: 'GET',
            data: { id: transactionId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    let typeClass = '';
                    let typeIcon = '';
                    
                    switch(data.transaction.type) {
                        case 'deposit':
                            typeClass = 'deposit';
                            typeIcon = 'icon-plus-circle';
                            break;
                        case 'withdrawal':
                            typeClass = 'withdrawal';
                            typeIcon = 'icon-minus-circle';
                            break;
                        case 'hawala_send':
                        case 'hawala_receive':
                            typeClass = 'hawala';
                            typeIcon = 'icon-repeat';
                            break;
                        case 'exchange':
                            typeClass = 'exchange';
                            typeIcon = 'icon-refresh-cw';
                            break;
                    }

                    let details = `
                        <!-- Customer Information -->
                        <div class="transaction-details-section">
                            <h5><i class="feather icon-user"></i><?= __("customer_information") ?></h5>
                            <div class="customer-info">
                                <div class="customer-avatar">
                                    ${data.customer.name.charAt(0).toUpperCase()}
                                </div>
                                <div class="customer-details">
                                    <h6>${data.customer.name}</h6>
                                    <p>${data.customer.phone || '<?= __("no_phone") ?>'}</p>
                                </div>
                            </div>
                            <table class="details-table">
                                <tr>
                                    <th><?= __("wallet_balance") ?>:</th>
                                    <td>
                                        <span class="font-weight-bold">
                                            ${data.transaction.currency} ${parseFloat(data.customer.wallet_balance).toFixed(2)}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Transaction Information -->
                        <div class="transaction-details-section">
                            <h5><i class="feather icon-file-text"></i><?= __("transaction_information") ?></h5>
                            <table class="details-table">
                                <tr>
                                    <th><?= __("type") ?>:</th>
                                    <td>
                                        <span class="transaction-badge ${typeClass}">
                                            <i class="feather ${typeIcon}"></i>
                                            ${capitalizeFirstLetter(data.transaction.type.replace('_', ' '))}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?= __("amount") ?>:</th>
                                    <td>
                                        <span class="font-weight-bold">
                                            ${data.transaction.currency} ${parseFloat(data.transaction.amount).toFixed(2)}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?= __("reference") ?>:</th>
                                    <td><code>${data.transaction.reference_number}</code></td>
                                </tr>
                                <tr>
                                    <th><?= __("status") ?>:</th>
                                    <td>
                                        <span class="badge badge-${data.transaction.status === 'completed' ? 'success' : (data.transaction.status === 'pending' ? 'warning' : 'danger')}">
                                            ${capitalizeFirstLetter(data.transaction.status)}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?= __("date") ?>:</th>
                                    <td>${formatDate(data.transaction.created_at)}</td>
                                </tr>
                                <tr>
                                    <th><?= __("notes") ?>:</th>
                                    <td>${data.transaction.notes || '<em class="text-muted"><?= __("no_notes") ?></em>'}</td>
                                </tr>
                            </table>
                        </div>`;

                        // Add Hawala Details if applicable
                        if (data.transaction.type === 'hawala_send' && data.hawala) {
                            details += `
                                <div class="transaction-details-section">
                                    <h5><i class="feather icon-repeat"></i><?= __("hawala_details") ?></h5>
                                    <table class="details-table">
                                        <tr>
                                            <th><?= __("commission") ?>:</th>
                                            <td>
                                                <span class="font-weight-bold">
                                                    ${data.hawala.commission_currency} ${parseFloat(data.hawala.commission_amount).toFixed(2)}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?= __("secret_code") ?>:</th>
                                            <td><code>${data.hawala.secret_code}</code></td>
                                        </tr>
                                        <tr>
                                            <th><?= __("status") ?>:</th>
                                            <td>
                                                <span class="badge badge-${data.hawala.status === 'completed' ? 'success' : 'warning'}">
                                                    ${capitalizeFirstLetter(data.hawala.status)}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?= __("receiver") ?>:</th>
                                            <td>${data.hawala.receiver.name}</td>
                                        </tr>
                                        <tr>
                                            <th><?= __("receiver_phone") ?>:</th>
                                            <td>${data.hawala.receiver.phone}</td>
                                        </tr>
                                    </table>
                                </div>`;
                        }

                        // Add Receipt if available
                        if (data.transaction.receipt_path) {
                            details += `
                                <div class="transaction-details-section">
                                    <h5><i class="feather icon-file"></i><?= __("receipt") ?></h5>
                                    <div class="text-center">
                                        <img src="../uploads/receipts/${data.transaction.receipt_path}" 
                                             class="receipt-preview" 
                                             alt="<?= __("receipt") ?>"
                                             onclick="window.open(this.src)">
                                    </div>
                                </div>`;
                        }

                        $('#transactionDetailsContent').html(details);
                        $('.print-transaction').show();
                    } else {
                        $('#transactionDetailsContent').html(`
                            <div class="alert alert-danger">
                                ${response.message || '<?= __("error_loading_transaction_details") ?>'}
                            </div>
                        `);
                    }
                },
                error: function() {
                    $('#transactionDetailsContent').html(`
                        <div class="alert alert-danger">
                            <?= __("error_loading_transaction_details") ?>
                        </div>
                    `);
                }
            });
        }

    // Function to delete deposit
    function deleteDeposit(transactionId, amount) {
        if (confirm('<?= __("confirm_delete_deposit") ?>')) {
            fetch('delete_sarafi_deposit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `transaction_id=${transactionId}&amount=${amount}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('<?= __("error") ?>: ' + data.message);
                }
            })
            .catch(error => {
                console.error('<?= __("error") ?>:', error);
                alert('<?= __("error_deleting_deposit") ?>');
            });
        }
    }

    // Function to delete withdrawal
    function deleteWithdrawal(transactionId, amount) {
        if (confirm('<?= __("confirm_delete_withdrawal") ?>')) {
            fetch('delete_sarafi_withdrawal.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `transaction_id=${transactionId}&amount=${amount}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('<?= __("error") ?>: ' + data.message);
                }
            })
            .catch(error => {
                console.error('<?= __("error") ?>:', error);
                alert('<?= __("error_deleting_withdrawal") ?>');
            });
        }
    }

    // Function to delete hawala transfer
    function deleteHawala(transactionId, amount) {
        if (confirm('<?= __("confirm_delete_hawala") ?>')) {
            fetch('delete_sarafi_hawala.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `transaction_id=${transactionId}&amount=${amount}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('<?= __("error") ?>: ' + data.message);
                }
            })
            .catch(error => {
                console.error('<?= __("error") ?>:', error);
                alert('<?= __("error_deleting_hawala") ?>');
            });
        }
    }

    // Handle delete exchange transaction
    $(document).on('click', '.delete-exchange', function(e) {
        e.preventDefault();
        const transactionId = $(this).data('id');
        
        if (confirm('<?= __("confirm_delete_exchange") ?>')) {
            $.ajax({
                url: 'delete_sarafi_exchange.php',
                type: 'POST',
                data: {
                    transaction_id: transactionId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert(response.message || '<?= __("error_deleting_exchange") ?>');
                    }
                },
                error: function() {
                    alert('<?= __("error_deleting_exchange") ?>');
                }
            });
        }
    });

    // Helper function to capitalize first letter
    function capitalizeFirstLetter(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    // Helper function to format date
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('<?= get_current_lang() ?>', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    </script>

    <!-- Transaction Details Modal -->
    <div class="modal fade" id="transactionDetailsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center">
                        <i class="feather icon-file-text mr-2"></i><?= __('transaction_details') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="<?= __('close') ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div id="transactionDetailsContent">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only"><?= __("loading") ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-2"></i><?= __('close') ?>
                    </button>
                    <button type="button" class="btn btn-primary print-transaction" style="display: none;">
                        <i class="feather icon-printer mr-2"></i><?= __('print') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>


    <!-- Include Modals -->
    <?php include 'includes/sarafi_modals.php'; ?>

    <!-- Enhanced Button Protection Script -->
    <script>
    // Enhanced button protection for all forms
    document.addEventListener('DOMContentLoaded', function() {
        
        // Function to protect form submission
        function protectFormSubmission(form, buttonName, loadingText) {
            form.addEventListener('submit', function(e) {
                console.log(`Form submitted with button: ${buttonName}`);
                
                const submitBtn = this.querySelector(`button[name="${buttonName}"]`);
                if (submitBtn && !submitBtn.disabled) {
                    // Disable button and show loading state
                    submitBtn.disabled = true;
                    submitBtn.classList.add('btn-loading');
                    
                    // Use Feather icons with proper spinning animation
                    const loadingHtml = `<i class="feather icon-refresh-cw mr-1" style="animation: spin 1s linear infinite;"></i>${loadingText}`;
                    submitBtn.innerHTML = loadingHtml;
                    
                    // Add CSS for spinner animation if not exists
                    if (!document.querySelector('#spinner-styles')) {
                        const style = document.createElement('style');
                        style.id = 'spinner-styles';
                        style.textContent = `
                            @keyframes spin {
                                0% { transform: rotate(0deg); }
                                100% { transform: rotate(360deg); }
                            }
                            .btn-loading {
                                pointer-events: none;
                                opacity: 0.7;
                            }
                            .spinner {
                                animation: spin 1s linear infinite;
                            }
                        `;
                        document.head.appendChild(style);
                    }
                    
                    console.log(`Button ${buttonName} disabled and loading state shown`);
                }
            });
        }

        // Protect the Deposit form
        const depositModal = document.getElementById('depositModal');
        if (depositModal) {
            const depositForm = depositModal.querySelector('form');
            if (depositForm) {
                protectFormSubmission(depositForm, 'add_deposit', 'Adding Deposit...');
            }
        }

        // Protect the Withdrawal form
        const withdrawalModal = document.getElementById('withdrawalModal');
        if (withdrawalModal) {
            const withdrawalForm = withdrawalModal.querySelector('form');
            if (withdrawalForm) {
                protectFormSubmission(withdrawalForm, 'add_withdrawal', 'Processing Withdrawal...');
            }
        }

        // Protect the Hawala Transfer form
        const hawalaModal = document.getElementById('hawalaModal');
        if (hawalaModal) {
            const hawalaForm = hawalaModal.querySelector('form');
            if (hawalaForm) {
                protectFormSubmission(hawalaForm, 'add_hawala', 'Processing Hawala...');
            }
        }

        // Protect the Currency Exchange form
        const exchangeModal = document.getElementById('exchangeModal');
        if (exchangeModal) {
            const exchangeForm = exchangeModal.querySelector('form');
            if (exchangeForm) {
                protectFormSubmission(exchangeForm, 'add_exchange', 'Processing Exchange...');
            }
        }

        // Protect the Customer form
        const customerModal = document.getElementById('customerModal');
        if (customerModal) {
            const customerForm = customerModal.querySelector('form');
            if (customerForm) {
                protectFormSubmission(customerForm, 'add_customer', 'Adding Customer...');
            }
        }

        // Enhanced click protection for all submit buttons
        const allSubmitButtons = document.querySelectorAll('button[type="submit"]');
        allSubmitButtons.forEach(button => {
            // Add single click protection
            button.addEventListener('click', function(e) {
                // Check if already processing
                if (this.disabled || this.classList.contains('processing') || this.classList.contains('btn-loading')) {
                    console.log('Button already processing, preventing double click');
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
                
                // Mark as processing immediately
                this.classList.add('processing');
                
                // Remove processing class after a short delay (in case form doesn't submit)
                setTimeout(() => {
                    this.classList.remove('processing');
                }, 3000);
            }, true);
        });

        console.log('Button protection initialized for all sarafi forms');
    });
    </script>

    <!-- Toast Notification System -->
    <script>
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            let icon = 'check-circle';
            if (type === 'error') icon = 'alert-circle';
            if (type === 'warning') icon = 'alert-triangle';
            
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="feather icon-${icon} mr-2"></i>
                    <span>${message}</span>
                </div>
                <button type="button" class="close ml-2" onclick="this.parentElement.remove();">
                    <span>&times;</span>
                </button>
            `;
            
            document.querySelector('.toast-container').appendChild(toast);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // Convert PHP alerts to toasts on page load
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($success_message)): ?>
            showToast(<?= json_encode($success_message) ?>, 'success');
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
            showToast(<?= json_encode($error_message) ?>, 'error');
            <?php endif; ?>
        });

        // Update AJAX success/error handlers to use toasts
        function handleAjaxSuccess(response, successMessage) {
            if (response.success) {
                showToast(successMessage || response.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(response.message || '<?= __("operation_failed") ?>', 'error');
            }
        }

        function handleAjaxError(error) {
            console.error('Error:', error);
            showToast('<?= __("operation_failed") ?>', 'error');
        }

        // Update delete functions to use toasts
        function deleteDeposit(transactionId, amount) {
            if (confirm('<?= __("confirm_delete_deposit") ?>')) {
                fetch('delete_sarafi_deposit.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `transaction_id=${transactionId}&amount=${amount}`
                })
                .then(response => response.json())
                .then(data => handleAjaxSuccess(data, '<?= __("deposit_deleted_successfully") ?>'))
                .catch(handleAjaxError);
            }
        }

        function deleteWithdrawal(transactionId, amount) {
            if (confirm('<?= __("confirm_delete_withdrawal") ?>')) {
                fetch('delete_sarafi_withdrawal.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `transaction_id=${transactionId}&amount=${amount}`
                })
                .then(response => response.json())
                .then(data => handleAjaxSuccess(data, '<?= __("withdrawal_deleted_successfully") ?>'))
                .catch(handleAjaxError);
            }
        }

        function deleteHawala(transactionId, amount) {
            if (confirm('<?= __("confirm_delete_hawala") ?>')) {
                fetch('delete_sarafi_hawala.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `transaction_id=${transactionId}&amount=${amount}`
                })
                .then(response => response.json())
                .then(data => handleAjaxSuccess(data, '<?= __("hawala_deleted_successfully") ?>'))
                .catch(handleAjaxError);
            }
        }

        // Update delete exchange handler
        $(document).on('click', '.delete-exchange', function(e) {
            e.preventDefault();
            const transactionId = $(this).data('id');
            
            if (confirm('<?= __("confirm_delete_exchange") ?>')) {
                $.ajax({
                    url: 'delete_sarafi_exchange.php',
                    type: 'POST',
                    data: {
                        transaction_id: transactionId
                    },
                    dataType: 'json',
                    success: function(response) {
                        handleAjaxSuccess(response, '<?= __("exchange_deleted_successfully") ?>');
                    },
                    error: handleAjaxError
                });
            }
        });
    </script>

<script>
// Print transaction details
$(document).on('click', '.print-transaction', function() {
    const content = document.getElementById('transactionDetailsContent').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title><?= __("transaction_details") ?></title>
                <link rel="stylesheet" href="../assets/css/style.css">
                <style>
                    body { padding: 20px; }
                    @media print {
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="text-center mb-4">
                        <h4><?= __("transaction_details") ?></h4>
                        <small class="text-muted"><?= __("printed_on") ?>: ${new Date().toLocaleString()}</small>
                    </div>
                    ${content}
                    <div class="text-center mt-4 no-print">
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="feather icon-printer"></i> <?= __("print") ?>
                        </button>
                    </div>
                </div>
            </body>
        </html>
    `);
    printWindow.document.close();
});
</script>

<script>
function editTransaction(transactionId, type) {
    // Fetch transaction details via AJAX
    $.ajax({
        url: 'view_sarafi_transaction.php',
        type: 'GET',
        data: { id: transactionId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const data = response.data;
                const transaction = data.transaction;
                const customer = data.customer;
                const mainAccount = data.main_account;

                // Populate edit modal
                $('#editTransactionId').val(transaction.id);
                $('#editTransactionType').val(transaction.type);

                // Set customer and main account IDs directly from response
                $('#editCustomerId').val(customer.id);
                $('#editCustomerName').val(customer.name);

                $('#editMainAccountId').val(mainAccount.id);

                $('#editAmount').val(parseFloat(transaction.amount).toFixed(2));
                $('#editOriginalAmount').val(parseFloat(transaction.amount).toFixed(2));

                $('#editReference').val(transaction.reference_number || '');
                $('#editNotes').val(transaction.notes || '');

                // Show the edit modal
                $('#editTransactionModal').modal('show');
            } else {
                showToast(response.message || '<?= __("error_loading_transaction_details") ?>', 'error');
            }
        },
        error: function() {
            showToast('<?= __("error_loading_transaction_details") ?>', 'error');
        }
    });
}

// Add event listener for edit transaction form submission
$(document).on('submit', '#editTransactionForm', function(e) {
    e.preventDefault();
    const form = $(this);
    const submitBtn = form.find('button[type="submit"]');
    const originalBtnText = submitBtn.html();
    const transactionType = $('#editTransactionType').val();

    // Determine the correct endpoint based on transaction type
    let updateUrl;
    switch(transactionType) {
        case 'deposit':
            updateUrl = 'update_sarafi_deposit_transaction.php';
            break;
        case 'withdrawal':
            updateUrl = 'update_sarafi_withdrawal_transaction.php';
            break;
        default:
            showToast('<?= __("unsupported_transaction_type") ?>', 'error');
            return;
    }

    // Disable submit button and show loading state
    submitBtn.prop('disabled', true);
    submitBtn.html('<i class="feather icon-loader spinner"></i> <?= __("saving") ?>');

    const formData = new FormData(this);

    // Log form data for debugging
    for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
    }

    $.ajax({
        url: updateUrl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            submitBtn.prop('disabled', false);
            submitBtn.html(originalBtnText);

            try {
                const result = typeof response === 'string' ? JSON.parse(response) : response;
                
                if (result.success) {
                    showToast('<?= __("transaction_updated_successfully") ?>', 'success');
                    $('#editTransactionModal').modal('hide');
                    location.reload(); // Reload to reflect changes
                } else {
                    showToast(result.message || '<?= __("error_updating_transaction") ?>', 'error');
                }
            } catch (e) {
                console.error('Error processing response:', e);
                showToast('<?= __("error_processing_response") ?>', 'error');
            }
        },
        error: function(xhr, status, error) {
            submitBtn.prop('disabled', false);
            submitBtn.html(originalBtnText);
            
            console.error('AJAX Error:', error);
            showToast('<?= __("error_updating_transaction") ?>', 'error');
        }
    });
});
</script>

<!-- Edit Transaction Modal -->
<div class="modal fade" id="editTransactionModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-edit mr-2"></i><?= __('edit_transaction') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="editTransactionForm">
                <div class="modal-body">
                    <!-- Hidden Inputs for Complete Transaction Context -->
                    <input type="hidden" id="editTransactionId" name="transaction_id">
                    <input type="hidden" id="editTransactionType" name="transaction_type">
                    <input type="hidden" id="editCustomerId" name="customer_id">
                    <input type="hidden" id="editMainAccountId" name="main_account_id">
                    <input type="hidden" id="editOriginalAmount" name="original_amount">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('customer') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="editCustomerName" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('amount') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">$</span>
                                    </div>
                                    <input type="number" step="0.01" min="0" class="form-control" 
                                           id="editAmount" name="amount" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('reference') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-hash"></i></span>
                                    </div>
                                    <input type="text" class="form-control" 
                                           id="editReference" name="reference">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?= __('notes') ?></label>
                        <textarea class="form-control" id="editNotes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save mr-2"></i><?= __('save_changes') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
</body>
</html> 