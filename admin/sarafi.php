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

// Handle deposit submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_deposit'])) {
    $customer_id = $_POST['customer_id'];
    $amount = $_POST['amount'];
    $currency = $_POST['currency'];
    $notes = $_POST['notes'];
    $reference = $_POST['reference'];
    $main_account_id = $_POST['main_account_id'];
    
    try {
        $conn->begin_transaction();
        
        // Get customer name
        $stmt = $conn->prepare("SELECT name FROM customers WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $customer_id, $tenant_id);
        $stmt->execute();
        $customer = $stmt->get_result()->fetch_assoc();
        
        // Insert the deposit transaction
        $stmt = $conn->prepare("INSERT INTO sarafi_transactions (customer_id, amount, currency, type, notes, reference_number, tenant_id) VALUES (?, ?, ?, 'deposit', ?, ?, ?)");
        $stmt->bind_param("idsssi", $customer_id, $amount, $currency, $notes, $reference, $tenant_id);
        $stmt->execute();
        $transaction_id = $conn->insert_id;
        
        // First check if wallet exists
        $stmt = $conn->prepare("SELECT id FROM customer_wallets WHERE customer_id = ? AND currency = ? AND tenant_id = ?");
        $stmt->bind_param("isi", $customer_id, $currency, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing wallet
            $stmt = $conn->prepare("UPDATE customer_wallets SET balance = balance + ? WHERE customer_id = ? AND currency = ? AND tenant_id = ?");
            $stmt->bind_param("disi", $amount, $customer_id, $currency, $tenant_id);
        } else {
            // Create new wallet
            $stmt = $conn->prepare("INSERT INTO customer_wallets (customer_id, currency, balance, tenant_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isdi", $customer_id, $currency, $amount, $tenant_id);
        }
        $stmt->execute();

        // Get current main account balance
        $balanceField = $currency === 'USD' ? 'usd_balance' : ($currency === 'AFS' ? 'afs_balance' : ($currency === 'EUR' ? 'euro_balance' : 'darham_balance'));
        $stmt = $conn->prepare("SELECT $balanceField as current_balance FROM main_account WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $main_account_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $balanceResult = $result->fetch_assoc();
        $newBalance = $balanceResult['current_balance'] + $amount;

        // Update main account balance
        $stmt = $conn->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("dii", $newBalance, $main_account_id, $tenant_id);
        $stmt->execute();
        $transaction_of = 'deposit_sarafi';
        // Record main account transaction
        $stmt = $conn->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id) VALUES (?, 'credit', ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("idsssissi", $main_account_id, $amount, $currency, $notes, $transaction_of, $transaction_id, $newBalance, $reference, $tenant_id);
        $stmt->execute();
        $main_transaction_id = $conn->insert_id;

        // Create notification
        $notificationMessage = sprintf(
            __('new_deposit_notification'),
            $customer['name'],
            $currency,
            $amount,
            $reference
        );
        
        $stmt = $conn->prepare("INSERT INTO notifications (transaction_id, transaction_type, message, status, created_at, tenant_id) VALUES (?, ?, ?, 'Unread', NOW(), ?)");
        $stmt->bind_param("issi", $main_transaction_id, $transaction_of, $notificationMessage, $tenant_id);
        $stmt->execute();
        
        // Handle receipt upload if provided
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            $file_extension = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
            $new_filename = 'receipt_' . $transaction_id . '_' . time() . '.' . $file_extension;
            $upload_path = '../uploads/receipts/' . $new_filename;
            
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $upload_path)) {
                // Update transaction with receipt path
                $stmt = $conn->prepare("UPDATE sarafi_transactions SET receipt_path = ? WHERE id = ? AND tenant_id = ?");
                $stmt->bind_param("si", $new_filename, $transaction_id, $tenant_id);
                $stmt->execute();
            }
        }
        
        $conn->commit();
        $_SESSION['success_message'] = __('deposit_success');
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
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
        $conn->begin_transaction();
        
        // Get customer name
        $stmt = $conn->prepare("SELECT name FROM customers WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $customer_id, $tenant_id);
        $stmt->execute();
        $customer = $stmt->get_result()->fetch_assoc();
        
        // Check if customer has sufficient balance
        $stmt = $conn->prepare("SELECT balance FROM customer_wallets WHERE customer_id = ? AND currency = ? AND tenant_id = ?");
        $stmt->bind_param("isi", $customer_id, $currency, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $wallet = $result->fetch_assoc();
        
        if (!$wallet || $wallet['balance'] < $amount) {
            throw new Exception(__('insufficient_balance'));
        }

        // Get current main account balance
        $balanceField = $currency === 'USD' ? 'usd_balance' : ($currency === 'AFS' ? 'afs_balance' : ($currency === 'EUR' ? 'euro_balance' : 'darham_balance'));
        $stmt = $conn->prepare("SELECT $balanceField as current_balance FROM main_account WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $main_account_id, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $balanceResult = $result->fetch_assoc();
        
        if (!$balanceResult || $balanceResult['current_balance'] < $amount) {
            throw new Exception(__('insufficient_main_account_balance'));
        }
        
        $newBalance = $balanceResult['current_balance'] - $amount;
        
        // Insert the withdrawal transaction
        $stmt = $conn->prepare("INSERT INTO sarafi_transactions (customer_id, amount, currency, type, notes, reference_number, tenant_id) VALUES (?, ?, ?, 'withdrawal', ?, ?, ?)");
        $stmt->bind_param("idsssi", $customer_id, $amount, $currency, $notes, $reference, $tenant_id);
        $stmt->execute();
        $transaction_id = $conn->insert_id;
        
        // Update customer wallet balance
        $stmt = $conn->prepare("UPDATE customer_wallets SET balance = balance - ? WHERE customer_id = ? AND currency = ? AND tenant_id = ?");
        $stmt->bind_param("disi", $amount, $customer_id, $currency, $tenant_id);
        $stmt->execute();

        // Update main account balance
        $stmt = $conn->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("dii", $newBalance, $main_account_id, $tenant_id);
        $stmt->execute();
        $transaction_of = 'withdrawal_sarafi';
        // Record main account transaction
        $stmt = $conn->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id) VALUES (?, 'debit', ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("idsssissi", $main_account_id, $amount, $currency, $notes, $transaction_of, $transaction_id, $newBalance, $reference, $tenant_id);
        $stmt->execute();
        $main_transaction_id = $conn->insert_id;

        // Create notification
        $notificationMessage = sprintf(
            __('new_withdrawal_notification'),
            $customer['name'],
            $currency,
            $amount,
            $reference
        );
        
        $stmt = $conn->prepare("INSERT INTO notifications (transaction_id, transaction_type, message, status, created_at, tenant_id) VALUES (?, ?, ?, 'Unread', NOW(), ?)");
        $stmt->bind_param("issi", $main_transaction_id, $transaction_of, $notificationMessage, $tenant_id);
        $stmt->execute();
        
        // Handle receipt upload if provided
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            $file_extension = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
            $new_filename = 'receipt_' . $transaction_id . '_' . time() . '.' . $file_extension;
            $upload_path = '../uploads/receipts/' . $new_filename;
            
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $upload_path)) {
                // Update transaction with receipt path
                $stmt = $conn->prepare("UPDATE sarafi_transactions SET receipt_path = ? WHERE id = ? AND tenant_id = ?");
                $stmt->bind_param("si", $new_filename, $transaction_id, $tenant_id);
                $stmt->execute();
            }
        }
        
        $conn->commit();
        $_SESSION['success_message'] = __('withdrawal_success');
        header('Location: ' . $redirect_url);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
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
        $conn->begin_transaction();
        
        // Get sender name
        $stmt = $conn->prepare("SELECT name FROM customers WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $data['sender_id'], $tenant_id);
        $stmt->execute();
        $sender = $stmt->get_result()->fetch_assoc();
        
        // Verify currencies match
        if ($data['send_currency'] !== $data['commission_currency']) {
            throw new Exception(__('commission_currency_mismatch'));
        }
        
        // Calculate net amount to deduct from main account (transfer amount - commission)
        $net_amount = $data['send_amount'] - $data['commission_amount'];
        
        // Get current main account balance
        $balanceField = $data['send_currency'] === 'USD' ? 'usd_balance' : ($data['send_currency'] === 'AFS' ? 'afs_balance' : ($data['send_currency'] === 'EUR' ? 'euro_balance' : 'darham_balance'));
        $stmt = $conn->prepare("SELECT $balanceField as current_balance FROM main_account WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("ii", $data['main_account_id'], $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $balanceResult = $result->fetch_assoc();
        
        if (!$balanceResult || $balanceResult['current_balance'] < $net_amount) {
            throw new Exception(__('insufficient_main_account_balance_hawala'));
        }
        
        $newBalance = $balanceResult['current_balance'] - $net_amount;
        
        // Update main account balance with net amount
        $stmt = $conn->prepare("UPDATE main_account SET $balanceField = ? WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param("dii", $newBalance, $data['main_account_id'], $tenant_id);
        $stmt->execute();
        
        // Process the hawala transfer
        $result = processHawalaTransfer($conn, $data);
        
        if ($result['success']) {
            // Record main account transaction for net hawala transfer amount
            $description = sprintf(__('hawala_transfer_description'), 
                $data['reference'],
                number_format($data['send_amount'], 2), $data['send_currency'],
                number_format($data['commission_amount'], 2), $data['commission_currency'],
                number_format($net_amount, 2), $data['send_currency']
            );
            $transaction_of = 'hawala_sarafi';
            $stmt = $conn->prepare("INSERT INTO main_account_transactions (main_account_id, type, amount, currency, description, transaction_of, reference_id, balance, receipt, tenant_id) 
            VALUES (?, 'debit', ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("idsssissi", $data['main_account_id'], $net_amount, $data['send_currency'], $data['notes'], $transaction_of, $result['sender_transaction_id'], $newBalance, $data['reference'], $tenant_id);
            $stmt->execute();
            $main_transaction_id = $conn->insert_id;

            // Create notification
            $notificationMessage = sprintf(
                __('new_hawala_transfer_notification'),
                $sender['name'],
                $data['send_currency'], $data['send_amount'],
                $data['commission_currency'], $data['commission_amount'],
                $data['send_currency'], $net_amount,
                $data['reference']
            );
            
            $stmt = $conn->prepare("INSERT INTO notifications (transaction_id, transaction_type, message, status, created_at, tenant_id) VALUES (?, ?, ?, 'Unread', NOW(), ?)");
            $stmt->bind_param("issi", $main_transaction_id, $transaction_of, $notificationMessage, $tenant_id);
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['success_message'] = $result['message'];
        } else {
            throw new Exception($result['message']);
        }
    } catch (Exception $e) {
        $conn->rollback();
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
    
    $result = processCurrencyExchange($conn, $data);
    
    if ($result['success']) {
        $_SESSION['success_message'] = $result['message'];
    } else {
        $_SESSION['error_message'] = $result['message'];
    }
    header('Location: ' . $redirect_url);
    exit();
}

// Fetch customers
$stmt = $conn->prepare("SELECT * FROM customers WHERE status = 'active' AND tenant_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
$customers = $result->fetch_all(MYSQLI_ASSOC);

// Calculate total balances by currency
$currency_totals = [];
$stmt = $conn->prepare("SELECT currency, SUM(balance) as total FROM customer_wallets WHERE tenant_id = ? GROUP BY currency");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $currency_totals[$row['currency']] = $row['total'];
}

?>

    <style>
        /* Select2 Z-index fix */
        .select2-container--open {
            z-index: 9999;
        }

        /* Ensure Select2 matches Bootstrap styling */
        .select2-container--bootstrap-5 .select2-selection {
            min-height: calc(1.5em + 0.75rem + 2px);
        }

        /* Fix Select2 in modals */
        .modal-body .select2-container {
            width: 100% !important;
        }

        /* Custom DataTables styling */
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #4099ff !important;
            color: #fff !important;
            border-color: #4099ff !important;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #e3f2fd !important;
            color: #4099ff !important;
        }
        
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 20px;
            padding-left: 15px;
            margin-left: 10px;
        }
        
        table.dataTable th {
            position: relative;
        }
        
        .dataTables_info {
            margin-top: 10px;
        }
        
        .table-responsive {
            min-height: 300px;
        }
    </style>

    <style>
/* Modern Dashboard Styling */
:root {
    --primary-color: #4099ff;
    --secondary-color: #2ed8b6;
    --danger-color: #ff5370;
    --warning-color: #ffb64d;
    --success-color: #2ed8b6;
    --info-color: #00bcd4;
}

/* Card Enhancements */
.card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.card-header {
    background: linear-gradient(45deg, var(--primary-color), #5dabff);
    color: white;
    border-radius: 10px 10px 0 0 !important;
    border-bottom: none;
    padding: 1rem 1.5rem;
}

/* Currency Total Cards */
.currency-card {
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.currency-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.currency-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(45deg, var(--primary-color), #5dabff);
    color: white;
    margin-right: 1rem;
}

/* Action Buttons */
.action-buttons .btn {
    border-radius: 50px;
    padding: 0.5rem 1.2rem;
    display: inline-flex;
    align-items: center;
    transition: all 0.3s ease;
    margin: 0.25rem;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.action-buttons .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.action-buttons .btn i {
    margin-right: 0.5rem;
    font-size: 1.1rem;
}

/* Table Enhancements */
.table {
    margin-bottom: 0;
}

.table thead th {
    background: #f8f9fa;
    border-top: none;
    border-bottom: 2px solid #e3e6f0;
    color: #434a54;
    font-weight: 600;
    padding: 1rem;
}

.table tbody td {
    padding: 1rem;
    vertical-align: middle;
    border-color: #e3e6f0;
}

/* Status Badges */
.badge {
    padding: 0.5em 1em;
    border-radius: 50px;
    font-weight: 500;
}

.badge-success {
    background-color: var(--success-color);
}

.badge-warning {
    background-color: var(--warning-color);
}

.badge-danger {
    background-color: var(--danger-color);
}

/* Toast Notifications */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
}

.toast {
    background: white;
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 10px;
    min-width: 300px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    justify-content: space-between;
    animation: slideIn 0.3s ease-out;
}

.toast.success {
    border-left: 4px solid var(--success-color);
}

.toast.error {
    border-left: 4px solid var(--danger-color);
}

.toast.warning {
    border-left: 4px solid var(--warning-color);
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Modal Enhancements */
.modal-content {
    border: none;
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
}

.modal-header {
    border-radius: 10px 10px 0 0;
    background: linear-gradient(45deg, var(--primary-color), #5dabff);
    color: white;
    border: none;
    padding: 1.5rem;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    border-top: 1px solid #e3e6f0;
    padding: 1rem 1.5rem;
}

/* Form Controls */
.form-control {
    border-radius: 8px;
    border: 1px solid #e3e6f0;
    padding: 0.75rem 1rem;
    transition: all 0.2s ease;
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
}

/* Select2 Enhancements */
.select2-container--bootstrap-5 .select2-selection {
    border-radius: 8px;
    border: 1px solid #e3e6f0;
}

.select2-container--bootstrap-5 .select2-selection:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
}
</style>


<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="css/modal-styles.css">


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
                    $stmt = $conn->prepare("
                        SELECT t.*, c.name as customer_name 
                        FROM sarafi_transactions t 
                        JOIN customers c ON t.customer_id = c.id 
                        WHERE t.tenant_id = ?
                        ORDER BY t.created_at DESC 
                        LIMIT 50
                    ");
                    $stmt->bind_param("i", $tenant_id);
                    $stmt->execute();
                    $transactions = $stmt->get_result();

                    while ($transaction = $transactions->fetch_assoc()):
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

                                <!-- Add Edit Button for deposit and withdrawal transactions -->
                                <?php if (in_array($transaction['type'], ['deposit', 'withdrawal'])): ?>
                                <button class="btn btn-sm btn-warning" 
                                        onclick="editTransaction(<?= $transaction['id'] ?>, '<?= $transaction['type'] ?>')" 
                                        data-toggle="tooltip" title="<?= __('edit') ?>">
                                    <i class="feather icon-edit"></i>
                                </button>
                                <?php endif; ?>

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
                    <?php endwhile; ?>
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
     <!-- Profile Modal -->
 <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="profileModalLabel">
                    <i class="feather icon-user mr-2"></i><?= __('user_profile') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    
                    <div class="position-relative d-inline-block">
                        <img src="<?= $imagePath ?>" 
                             class="rounded-circle profile-image" 
                             alt="User Profile Image">
                        <div class="profile-status online"></div>
                    </div>
                    <h5 class="mt-3 mb-1"><?= !empty($user['name']) ? htmlspecialchars($user['name']) : '<?= __("guest") ?>' ?></h5>
                    <p class="text-muted mb-0"><?= !empty($user['role']) ? htmlspecialchars($user['role']) : '<?= __("user") ?>' ?></p>
                </div>

                <div class="profile-info">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('email') ?></label>
                                <p class="mb-0"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('phone') ?></label>
                                <p class="mb-0"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('join_date') ?></label>
                                <p class="mb-0"><?= !empty($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('address') ?></label>
                                <p class="mb-0"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not Set' ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <h6 class="mb-3"><?= __('account_information') ?></h6>
                        <div class="activity-timeline">
                            <div class="timeline-item">
                                <i class="activity-icon fas fa-calendar-alt bg-primary"></i>
                                <div class="timeline-content">
                                    <p class="mb-0"><?= __('account_created') ?></p>
                                    <small class="text-muted"><?= !empty($user['created_at']) ? date('M d, Y H:i A', strtotime($user['created_at'])) : 'Not Available' ?></small>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __('close') ?></button>
                
            </div>
        </div>
    </div>
</div>

<style>
        .profile-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .profile-status {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #2ed8b6;
            border: 2px solid #fff;
        }

        .profile-status.online {
            background-color: #2ed8b6;
        }

        .info-item label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item p {
            font-weight: 500;
        }

        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 15px;
        }

        .activity-icon {
            position: absolute;
            left: -30px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #e3f2fd;
            color: #2196f3;
            text-align: center;
            line-height: 24px;
            font-size: 12px;
        }

        .modal-content {
            border: none;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .modal-header {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .modal-footer {
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        @media (max-width: 576px) {
            .profile-image {
                width: 100px;
                height: 100px;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
        }
        /* Updated Modal Styles */
        .modal-lg {
            max-width: 800px;
        }

        .floating-label {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .floating-label input,
        .floating-label textarea {
            height: auto;
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            width: 100%;
            font-size: 1rem;
        }

        .floating-label label {
            position: absolute;
            top: 50%;
            left: 0.75rem;
            transform: translateY(-50%);
            pointer-events: none;
            transition: all 0.2s ease;
            color: #6c757d;
            margin: 0;
            padding: 0 0.2rem;
            background-color: #fff;
            font-size: 1rem;
        }

        .floating-label textarea ~ label {
            top: 1rem;
            transform: translateY(0);
        }

        /* Active state - when input has value or is focused */
        .floating-label input:focus ~ label,
        .floating-label input:not(:placeholder-shown) ~ label,
        .floating-label textarea:focus ~ label,
        .floating-label textarea:not(:placeholder-shown) ~ label {
            top: 0;
            transform: translateY(-50%) scale(0.85);
            background-color: #fff;
            color: #4099ff;
            z-index: 1;
        }

        .floating-label input:focus,
        .floating-label textarea:focus {
            border-color: #4099ff;
            box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
            outline: none;
        }

        /* Ensure inputs have placeholder to trigger :not(:placeholder-shown) */
        .floating-label input,
        .floating-label textarea {
            placeholder: " ";
        }

        /* Rest of the styles remain the same */
        .profile-upload-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            transition: all 0.3s ease;
        }

        .upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            background: rgba(64, 153, 255, 0.9);
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-overlay:hover {
            transform: scale(1.1);
            background: rgba(64, 153, 255, 1);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .modal-lg {
                max-width: 95%;
                margin: 0.5rem auto;
            }

            .profile-upload-preview {
                width: 120px;
                height: 120px;
            }

            .modal-body {
                padding: 1rem !important;
            }

            .floating-label input,
            .floating-label textarea {
                padding: 0.6rem;
                font-size: 0.95rem;
            }

            .floating-label label {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 576px) {
            .profile-upload-preview {
                width: 100px;
                height: 100px;
            }

            .upload-overlay {
                width: 30px;
                height: 30px;
            }

            .modal-footer {
                flex-direction: column;
            }

            .modal-footer button {
                width: 100%;
                margin: 0.25rem 0;
            }
        }
</style>

                            <!-- Settings Modal -->
                            <div class="modal fade" id="settingsModal" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                    <form id="updateProfileForm" enctype="multipart/form-data">
                                        <div class="modal-content shadow-lg border-0">
                                            <div class="modal-header bg-primary text-white border-0">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-settings mr-2"></i><?= __('profile_settings') ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="row">
                                                    <!-- Left Column - Profile Picture -->
                                                    <div class="col-md-4 text-center mb-4">
                                                        <div class="position-relative d-inline-block">
                                                            <img src="<?= $imagePath ?>" alt="Profile Picture" 
                                                                 class="profile-upload-preview rounded-circle border shadow-sm"
                                                                 id="profilePreview">
                                                            <label for="profileImage" class="upload-overlay">
                                                                <i class="feather icon-camera"></i>
                                                            </label>
                                                            <input type="file" class="d-none" id="profileImage" name="image" 
                                                                   accept="image/*" onchange="previewImage(this)">
                                                        </div>
                                                        <small class="text-muted d-block mt-2"><?= __('click_to_change_profile_picture') ?></small>
                                                    </div>

                                                    <!-- Right Column - Form Fields -->
                                                    <div class="col-md-8">
                                                        <!-- Personal Info Section -->
                                                        <div class="settings-section active" id="personalInfo">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-user mr-2"></i>Personal Information
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="text" class="form-control" id="updateName" name="name" 
                                                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                                                                <label for="updateName"><?= __('full_name') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="email" class="form-control" id="updateEmail" name="email" 
                                                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                                                                <label for="updateEmail"><?= __('email_address') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="tel" class="form-control" id="updatePhone" name="phone" 
                                                                       value="<?= htmlspecialchars($user['phone']) ?>">
                                                                <label for="updatePhone"><?= __('phone_number') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <textarea class="form-control" id="updateAddress" name="address" 
                                                                          rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                                                                <label for="updateAddress"><?= __('address') ?></label>
                                                            </div>
                                                        </div>

                                                        <!-- Password Section -->
                                                        <div class="settings-section mt-4">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-lock mr-2"></i><?= __('change_password') ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="password" class="form-control" id="currentPassword" 
                                                                       name="current_password">
                                                                <label for="currentPassword"><?= __('current_password') ?></label>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="newPassword" 
                                                                               name="new_password">
                                                                        <label for="newPassword"><?= __('new_password') ?></label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="confirmPassword" 
                                                                               name="confirm_password">
                                                                        <label for="confirmPassword"><?= __('confirm_password') ?></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 bg-light">
                                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                                                </button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather icon-save mr-2"></i><?= __('save_changes') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>



<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                // Listen for form submission (using submit event)
                                document.getElementById('updateProfileForm').addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    
                                    const newPassword = document.getElementById('newPassword').value;
                                    const confirmPassword = document.getElementById('confirmPassword').value;
                                    const currentPassword = document.getElementById('currentPassword').value;

                                    // If any password field is filled, all password fields must be filled
                                    if (newPassword || confirmPassword || currentPassword) {
                                        if (!currentPassword) {
                                            alert('<?= __('please_enter_your_current_password') ?>');
                                            return;
                                        }
                                        if (!newPassword) {
                                            alert('<?= __('please_enter_a_new_password') ?>');
                                            return;
                                        }
                                        if (!confirmPassword) {
                                            alert('<?= __('please_confirm_your_new_password') ?>');
                                            return;
                                        }
                                        if (newPassword !== confirmPassword) {
                                            alert('<?= __('new_passwords_do_not_match') ?>');
                                            return;
                                        }
                                        if (newPassword.length < 6) {
                                            alert('<?= __('new_password_must_be_at_least_6_characters_long') ?>');
                                            return;
                                        }
                                    }
                                    
                                    const formData = new FormData(this);
                                    
                                    fetch('update_client_profile.php', {
                                        method: 'POST',
                                        body: formData
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert(data.message);
                                            // Clear password fields
                                            document.getElementById('currentPassword').value = '';
                                            document.getElementById('newPassword').value = '';
                                            document.getElementById('confirmPassword').value = '';
                                            location.reload();
                                        } else {
                                            alert(data.message || '<?= __('failed_to_update_profile') ?>');
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        alert('<?= __('an_error_occurred_while_updating_the_profile') ?>');
                                    });
                                });
                            });
                            </script>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>


    <!-- Include Modals -->
    <?php include 'includes/sarafi_modals.php'; ?>

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

                // Find customer ID by name (since it's not directly in the response)
                let customerId = '';
                $.ajax({
                    url: 'get_customer_id_by_name.php', // You'll need to create this endpoint
                    type: 'GET',
                    data: { name: customer.name },
                    async: false, // Synchronous to ensure ID is set before modal opens
                    success: function(customerResponse) {
                        customerId = customerResponse.customer_id || '';
                    },
                    error: function() {
                        console.error('Could not fetch customer ID');
                    }
                });

                // Find main account ID by name
                let mainAccountId = '';
                $.ajax({
                    url: 'get_main_account_id_by_name.php', // You'll need to create this endpoint
                    type: 'GET',
                    data: { name: mainAccount.name },
                    async: false, // Synchronous to ensure ID is set before modal opens
                    success: function(mainAccountResponse) {
                        mainAccountId = mainAccountResponse.main_account_id || '';
                    },
                    error: function() {
                        console.error('Could not fetch main account ID');
                    }
                });

                // Populate edit modal
                $('#editTransactionId').val(transaction.id);
                $('#editTransactionType').val(transaction.type);
                
                // Set customer and main account IDs
                $('#editCustomerId').val(customerId);
                $('#editCustomerName').val(customer.name);
                
                $('#editMainAccountId').val(mainAccountId);
                
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