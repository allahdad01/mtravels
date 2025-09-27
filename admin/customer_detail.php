<?php
require_once '../includes/conn.php';
require_once '../includes/db.php';
require_once 'security.php';
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}


// Build redirect URL with current query parameters
$redirect_url = $_SERVER['PHP_SELF'] . '?id=' . $_GET['id'];

// Debug POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('POST Data: ' . print_r($_POST, true));
    error_log('FILES Data: ' . print_r($_FILES, true));
}

// Handle deposit submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_deposit'])) {
    error_log('Processing deposit...');
    
    try {
        $customer_id = $_POST['customer_id'];
        $amount = $_POST['amount'];
        $currency = $_POST['currency'];
        $notes = $_POST['notes'] ?? '';
        $reference = $_POST['reference'];
        
        error_log("Deposit data - Customer: $customer_id, Amount: $amount, Currency: $currency, Reference: $reference");
        
        $conn->begin_transaction();
        
        // Insert the deposit transaction
        $stmt = $conn->prepare("INSERT INTO sarafi_transactions (customer_id, amount, currency, type, notes, reference_number, status, tenant_id) VALUES (?, ?, ?, 'deposit', ?, ?, 'completed', ?)");
        $stmt->bind_param("idsssii", $customer_id, $amount, $currency, $notes, $reference, $tenant_id);
        
        if (!$stmt->execute()) {
            throw new Exception(__("error_inserting_transaction") . ": " . $stmt->error);
        }
        
        $transaction_id = $conn->insert_id;
        error_log("Transaction created with ID: $transaction_id");
        
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
        
        if (!$stmt->execute()) {
            throw new Exception(__("error_updating_wallet") . ": " . $stmt->error);
        }
        
        error_log("Wallet updated successfully");
        
        // Handle receipt upload if provided
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            $file_extension = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
            $new_filename = 'receipt_' . $transaction_id . '_' . time() . '.' . $file_extension;
            $upload_path = '../uploads/receipts/' . $new_filename;
            
            if (!is_dir('../uploads/receipts')) {
                mkdir('../uploads/receipts', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $upload_path)) {
                // Update transaction with receipt path
                $stmt = $conn->prepare("UPDATE sarafi_transactions SET receipt_path = ? WHERE id = ? AND tenant_id = ?");
                $stmt->bind_param("si", $new_filename, $transaction_id, $tenant_id);
                if (!$stmt->execute()) {
                    throw new Exception(__("error_updating_receipt_path") . ": " . $stmt->error);
                }
                error_log("Receipt uploaded successfully: $new_filename");
            } else {
                error_log("Failed to move uploaded file to: $upload_path");
            }
        }
        
        $conn->commit();
        $_SESSION['success_message'] = "Deposit processed successfully!";
        error_log("Deposit completed successfully");
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Deposit error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error processing deposit: " . $e->getMessage();
    }
    
    header('Location: ' . $redirect_url);
    exit();
}

// Handle withdrawal submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_withdrawal'])) {
    $customer_id = $_POST['customer_id'];
    $amount = $_POST['amount'];
    $currency = $_POST['currency'];
    $notes = $_POST['notes'];
    $reference = $_POST['reference'];
    
    try {
        $conn->begin_transaction();
        
        // Check if customer has sufficient balance
        $stmt = $conn->prepare("SELECT balance FROM customer_wallets WHERE customer_id = ? AND currency = ? AND tenant_id = ?");
        $stmt->bind_param("isi", $customer_id, $currency, $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $wallet = $result->fetch_assoc();
        
        if (!$wallet || $wallet['balance'] < $amount) {
            throw new Exception(__("insufficient_balance"));
        }
        
        // Insert the withdrawal transaction
        $stmt = $conn->prepare("INSERT INTO sarafi_transactions (customer_id, amount, currency, type, notes, reference_number, tenant_id) VALUES (?, ?, ?, 'withdrawal', ?, ?, ?)");
        $stmt->bind_param("idsssi", $customer_id, $amount, $currency, $notes, $reference, $tenant_id);
        $stmt->execute();
        $transaction_id = $conn->insert_id;
        
        // Update customer wallet balance
        $stmt = $conn->prepare("UPDATE customer_wallets SET balance = balance - ? WHERE customer_id = ? AND currency = ? AND tenant_id = ?");
        $stmt->bind_param("disi", $amount, $customer_id, $currency, $tenant_id);
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
        $_SESSION['success_message'] = __("withdrawal_processed_successfully");
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = __("error_processing_withdrawal") . ": " . $e->getMessage();
    }
    
    header('Location: ' . $redirect_url);
    exit();
}

// Validate customer ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = __("invalid_customer_id");
    header('Location: customers.php');
    exit();
}

$customer_id = intval($_GET['id']);

// Fetch customer details
$stmt = $conn->prepare("
    SELECT c.*, 
           GROUP_CONCAT(DISTINCT CONCAT(w.currency, ':', w.balance) SEPARATOR ',') as wallet_balances
    FROM customers c
    LEFT JOIN customer_wallets w ON c.id = w.customer_id
    WHERE c.id = ? AND c.tenant_id = ?
    GROUP BY c.id
");
$stmt->bind_param("ii", $customer_id, $tenant_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

if (!$customer) {
    $_SESSION['error_message'] = __("customer_not_found");
    header('Location: customers.php');
    exit();
}

// Parse wallet balances
$wallets = [];
if ($customer['wallet_balances']) {
    foreach (explode(',', $customer['wallet_balances']) as $wallet) {
        list($currency, $balance) = explode(':', $wallet);
        $wallets[$currency] = $balance;
    }
}

// Fetch recent transactions
$stmt = $conn->prepare("
    SELECT t.*,
           CASE 
               WHEN t.type = 'hawala_send' THEN (
                   SELECT CONCAT('Code: ', h.secret_code)
                   FROM hawala_transfers h 
                   WHERE h.sender_transaction_id = t.id
               )
               WHEN t.type = 'exchange' THEN (
                   SELECT CONCAT(e.to_currency, ' ', e.to_amount)
                   FROM exchange_transactions e 
                   WHERE e.transaction_id = t.id
               )
               ELSE NULL
           END as additional_info
    FROM sarafi_transactions t
    WHERE t.customer_id = ? AND t.tenant_id = ?
    ORDER BY t.created_at DESC
    LIMIT 100
");
$stmt->bind_param("ii", $customer_id, $tenant_id);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch main account data for these transactions
$transaction_ids = array_column($transactions, 'id');
$main_account_data = [];

if (!empty($transaction_ids)) {
    $placeholders = str_repeat('?,', count($transaction_ids) - 1) . '?';
    $stmt = $conn->prepare("
        SELECT mat.reference_id, ma.name 
        FROM main_account_transactions mat 
        JOIN main_account ma ON mat.main_account_id = ma.id 
        WHERE mat.reference_id IN ($placeholders) AND mat.tenant_id = ?
    ");

    $bind_types = str_repeat('i', count($transaction_ids)) . 'i';

    // Merge transaction_ids with tenant_id
    $params = array_merge($transaction_ids, [$tenant_id]);

    // Convert into references for bind_param
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }

    // Call bind_param with dynamic arguments
    array_unshift($refs, $bind_types);
    call_user_func_array([$stmt, 'bind_param'], $refs);

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $main_account_data[$row['reference_id']] = $row['name'];
    }
}


// Calculate transaction statistics
$stats = [
    'total_deposits' => 0,
    'total_withdrawals' => 0,
    'total_hawala_sent' => 0,
    'total_hawala_received' => 0,
    'total_exchanges' => 0
];

foreach ($transactions as $transaction) {
    switch ($transaction['type']) {
        case 'deposit':
            $stats['total_deposits']++;
            break;
        case 'withdrawal':
            $stats['total_withdrawals']++;
            break;
        case 'hawala_send':
            $stats['total_hawala_sent']++;
            break;
        case 'hawala_receive':
            $stats['total_hawala_received']++;
            break;
        case 'exchange':
            $stats['total_exchanges']++;
            break;
    }
}

// Initialize messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear session messages
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

?>


    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #4099ff 0%, #73b4ff 100%);
            --success-gradient: linear-gradient(135deg, #2ed8b6 0%, #59e0c5 100%);
            --warning-gradient: linear-gradient(135deg, #FFB64D 0%, #ffcb80 100%);
            --danger-gradient: linear-gradient(135deg, #FF5370 0%, #ff869a 100%);
            --info-gradient: linear-gradient(135deg, #3ec9dc 0%, #7ce0f0 100%);
            --card-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        .card {
            border: none;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .card-header {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 1.25rem;
            display: flex;
            align-items: center;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .card-header i {
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }

        .customer-info {
            padding: 1.5rem;
        }

        .customer-info p {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            color: #555;
        }

        .customer-info i {
            width: 24px;
            margin-right: 0.75rem;
            color: #4099ff;
        }

        .wallet-balance {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .wallet-balance:hover {
            background: #fff;
            box-shadow: var(--card-shadow);
        }

        .stats-item {
            padding: 1.25rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stats-item.deposits {
            background: var(--success-gradient);
        }

        .stats-item.withdrawals {
            background: var(--warning-gradient);
        }

        .stats-item.hawala {
            background: var(--info-gradient);
        }

        .stats-item.exchanges {
            background: var(--primary-gradient);
        }

        .stats-item i {
            font-size: 2rem;
            opacity: 0.8;
        }

        .stats-item .stats-info {
            text-align: right;
        }

        .stats-item h6 {
            margin: 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .stats-item h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .transaction-table {
            margin-top: 1rem;
        }

        .transaction-table th {
            background: #f8f9fa;
            border-top: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            color: #555;
            padding: 1rem;
        }

        .transaction-table td {
            padding: 1rem;
            vertical-align: middle;
            color: #444;
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
        }

        .badge-success {
            background: var(--success-gradient);
            border: none;
        }

        .badge-warning {
            background: var(--warning-gradient);
            border: none;
            color: white;
        }

        .badge-info {
            background: var(--info-gradient);
            border: none;
            color: white;
        }

        .btn {
            border-radius: 50px;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn i {
            font-size: 1rem;
        }

        .btn-group-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-group-actions .btn {
            padding: 0.4rem 1rem;
            font-size: 0.875rem;
        }

        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 1.5rem;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert i {
            font-size: 1.25rem;
        }

        .alert-success {
            background: var(--success-gradient);
            color: white;
        }

        .alert-danger {
            background: var(--danger-gradient);
            color: white;
        }

        @media (max-width: 768px) {
            .btn-group-actions {
                flex-direction: column;
            }
            
            .btn-group-actions .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            .stats-item {
                padding: 1rem;
            }

            .stats-item i {
                font-size: 1.5rem;
            }

            .stats-item h4 {
                font-size: 1.25rem;
            }
        }

        /* Modal Enhancements */
        .modal-content {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .modal-header {
            background: var(--primary-gradient);
            color: white;
            border-bottom: none;
            border-radius: 10px 10px 0 0;
            padding: 1.25rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid #eee;
            padding: 1rem;
        }

        /* Transaction Details Enhancements */
        .transaction-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }

        .transaction-details table {
            margin-bottom: 0;
        }

        .transaction-details th {
            font-weight: 600;
            padding: 0.5rem 1rem;
            color: #555;
            width: 40%;
        }

        .transaction-details td {
            padding: 0.5rem 1rem;
            color: #333;
        }

        .receipt-image {
            max-width: 100%;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
        }

        /* Loading Spinner */
        .spinner-border {
            width: 1.5rem;
            height: 1.5rem;
            border-width: 0.2em;
        }

        .btn-loading {
            position: relative;
            pointer-events: none;
            opacity: 0.8;
        }

        .btn-loading .spinner-border {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 1rem;
            height: 1rem;
        }
    </style>

    <?php if (in_array($_SESSION['lang'] ?? 'en', ['fa', 'ps'])): ?>
    <style>
        .card-header {
            flex-direction: row-reverse !important;
        }
        .card-header .title-section {
            margin-right: 0;
            margin-left: auto;
        }
        .card-header .button-section {
            margin-left: 0;
            margin-right: auto;
        }
        .feather {
            margin-left: 8px;
            margin-right: 0;
        }
    </style>
    <?php endif; ?>

<?php include '../includes/header.php'; ?>
<style>
/* Apply gradient background to card headers matching the sidebar */
.card-header {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
    color: #ffffff !important;
    border-bottom: none !important;
}

.card-header h5 {
    color: #ffffff !important;
    margin-bottom: 0 !important;
}

.card-header .card-header-right {
    color: #ffffff !important;
}

.card-header .card-header-right .btn {
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
}

.card-header .card-header-right .btn:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
}
</style>
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
                                <!-- Customer Info Card -->
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <i class="feather icon-user"></i>
                                            <h5><?= __("customer_information") ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="customer-info">
                                                <p>
                                                    <i class="feather icon-user"></i>
                                                    <span><?= htmlspecialchars($customer['name']) ?></span>
                                                </p>
                                                <p>
                                                    <i class="feather icon-phone"></i>
                                                    <span><?= htmlspecialchars($customer['phone']) ?></span>
                                                </p>
                                                <?php if ($customer['email']): ?>
                                                <p>
                                                    <i class="feather icon-mail"></i>
                                                    <span><?= htmlspecialchars($customer['email']) ?></span>
                                                </p>
                                                <?php endif; ?>
                                                <?php if ($customer['address']): ?>
                                                <p>
                                                    <i class="feather icon-map-pin"></i>
                                                    <span><?= htmlspecialchars($customer['address']) ?></span>
                                                </p>
                                                <?php endif; ?>
                                                <p>
                                                    <i class="feather icon-clock"></i>
                                                    <span><?= __("created") ?>: <?= date('Y-m-d', strtotime($customer['created_at'])) ?></span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Wallet Balances Card -->
                                    <div class="card">
                                        <div class="card-header">
                                            <i class="feather icon-credit-card"></i>
                                            <h5><?= __("wallet_balances") ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <div id="customerBalance">
                                                <?php foreach ($wallets as $currency => $balance): ?>
                                                <div class="wallet-balance">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="font-weight-bold"><?= htmlspecialchars($currency) ?></span>
                                                        <span class="h5 mb-0 <?= $balance >= 0 ? 'text-success' : 'text-danger' ?>">
                                                            <?= number_format($balance, 2) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Transaction Stats Card -->
                                    <div class="card">
                                        <div class="card-header">
                                            <i class="feather icon-activity"></i>
                                            <h5><?= __("transaction_statistics") ?></h5>
                                        </div>
                                        <div class="card-body p-3">
                                            <div class="stats-item deposits">
                                                <i class="feather icon-plus-circle"></i>
                                                <div class="stats-info">
                                                    <h6><?= __("deposits") ?></h6>
                                                    <h4><?= $stats['total_deposits'] ?></h4>
                                                </div>
                                            </div>
                                            <div class="stats-item withdrawals">
                                                <i class="feather icon-minus-circle"></i>
                                                <div class="stats-info">
                                                    <h6><?= __("withdrawals") ?></h6>
                                                    <h4><?= $stats['total_withdrawals'] ?></h4>
                                                </div>
                                            </div>
                                            <div class="stats-item hawala">
                                                <i class="feather icon-repeat"></i>
                                                <div class="stats-info">
                                                    <h6><?= __("hawala_transfers") ?></h6>
                                                    <h4><?= $stats['total_hawala_sent'] + $stats['total_hawala_received'] ?></h4>
                                                </div>
                                            </div>
                                            <div class="stats-item exchanges">
                                                <i class="feather icon-refresh-cw"></i>
                                                <div class="stats-info">
                                                    <h6><?= __("exchanges") ?></h6>
                                                    <h4><?= $stats['total_exchanges'] ?></h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Transactions Card -->
                                <div class="col-md-8">
                                    <div class="card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <i class="feather icon-list"></i>
                                                <h5><?= __("transaction_history") ?></h5>
                                            </div>
                                            <div class="btn-group-actions">
                                                <button class="btn btn-success" data-toggle="modal" data-target="#depositModal">
                                                    <i class="feather icon-plus"></i>
                                                    <?= __("new_deposit") ?>
                                                </button>
                                                <button class="btn btn-warning" data-toggle="modal" data-target="#withdrawalModal">
                                                    <i class="feather icon-minus"></i>
                                                    <?= __("new_withdrawal") ?>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <!-- Success/Error Messages -->
                                            <?php if (isset($success_message)): ?>
                                                <div class="alert alert-success mx-3 mt-3">
                                                    <i class="feather icon-check-circle"></i>
                                                    <span><?= htmlspecialchars($success_message) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($error_message)): ?>
                                                <div class="alert alert-danger mx-3 mt-3">
                                                    <i class="feather icon-alert-circle"></i>
                                                    <span><?= htmlspecialchars($error_message) ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <div class="table-responsive">
                                                <table class="table transaction-table">
                                                    <thead>
                                                        <tr>
                                                            <th><?= __("date") ?></th>
                                                            <th><?= __("type") ?></th>
                                                            <th><?= __("amount") ?></th>
                                                            <th><?= __("paid_to") ?></th>
                                                            <th><?= __("currency") ?></th>
                                                            <th><?= __("status") ?></th>
                                                            <th><?= __("notes") ?></th>
                                                            <th><?= __("actions") ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($transactions as $transaction): 
                                                            $type_class = '';
                                                            $type_icon = '';
                                                            switch ($transaction['type']) {
                                                                case 'deposit':
                                                                    $type_class = 'text-success';
                                                                    $type_icon = 'plus-circle';
                                                                    break;
                                                                case 'withdrawal':
                                                                    $type_class = 'text-warning';
                                                                    $type_icon = 'minus-circle';
                                                                    break;
                                                                case 'hawala_send':
                                                                case 'hawala_receive':
                                                                    $type_class = 'text-info';
                                                                    $type_icon = 'repeat';
                                                                    break;
                                                                case 'exchange':
                                                                    $type_class = 'text-primary';
                                                                    $type_icon = 'refresh-cw';
                                                                    break;
                                                            }
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <i class="feather icon-calendar mr-2 text-muted"></i>
                                                                    <?= date('Y-m-d H:i', strtotime($transaction['created_at'])) ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="d-flex align-items-center <?= $type_class ?>">
                                                                    <i class="feather icon-<?= $type_icon ?> mr-2"></i>
                                                                    <div>
                                                                        <?= __($transaction['type']) ?>
                                                                        <?php if ($transaction['additional_info']): ?>
                                                                        <small class="d-block text-muted">
                                                                            <?= htmlspecialchars($transaction['additional_info']) ?>
                                                                        </small>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="font-weight-bold">
                                                                    <?= number_format($transaction['amount'], 2) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                if (!empty($main_account_data[$transaction['id']])) {
                                                                    echo '<div class="d-flex align-items-center">';
                                                                    echo '<i class="feather icon-user mr-2"></i>';
                                                                    echo htmlspecialchars($main_account_data[$transaction['id']]);
                                                                    echo '</div>';
                                                                } else {
                                                                    // Check if paid_to field exists before accessing it
                                                                    $paidTo = isset($transaction['paid_to']) ? $transaction['paid_to'] : '-';
                                                                    echo htmlspecialchars($paidTo);
                                                                }
                                                                ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge badge-light">
                                                                    <?= htmlspecialchars($transaction['currency']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge badge-<?= $transaction['status'] === 'completed' ? 'success' : ($transaction['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                                    <?= __($transaction['status']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if ($transaction['notes']): ?>
                                                                <small class="text-muted">
                                                                    <?= htmlspecialchars($transaction['notes']) ?>
                                                                </small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group-actions">
                                                                    <?php if ($transaction['receipt_path']): ?>
                                                                    <a href="../uploads/receipts/<?= htmlspecialchars($transaction['receipt_path']) ?>" 
                                                                       class="btn btn-info btn-sm" target="_blank" 
                                                                       data-toggle="tooltip" title="<?= __('view_receipt') ?>">
                                                                        <i class="feather icon-file"></i>
                                                                    </a>
                                                                    <?php endif; ?>
                                                                    
                                                                    <button class="btn btn-info btn-sm view-transaction" 
                                                                            data-id="<?= $transaction['id'] ?>"
                                                                            data-toggle="tooltip" title="<?= __('view_details') ?>">
                                                                        <i class="feather icon-eye"></i>
                                                                    </button>

                                                                    <?php if (in_array($transaction['type'], ['deposit', 'withdrawal', 'hawala_send'])): ?>
                                                                    <button class="btn btn-danger btn-sm delete-transaction" 
                                                                            data-id="<?= $transaction['id'] ?>"
                                                                            data-type="<?= $transaction['type'] ?>"
                                                                            data-amount="<?= $transaction['amount'] ?>"
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
                            <!-- [ Main Content ] end -->
                        </div>
                    </div>
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
                    <i class="feather icon-user mr-2"></i><?= __("user_profile") ?>
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
                    <h5 class="mt-3 mb-1"><?= !empty($user['name']) ? htmlspecialchars($user['name']) : __("guest") ?></h5>
                    <p class="text-muted mb-0"><?= !empty($user['role']) ? htmlspecialchars($user['role']) : __("user") ?></p>
                </div>

                <div class="profile-info">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __("email") ?></label>
                                <p class="mb-0"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : __("not_set") ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __("phone") ?></label>
                                <p class="mb-0"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : __("not_set") ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __("join_date") ?></label>
                                <p class="mb-0"><?= !empty($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : __("not_set") ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __("address") ?></label>
                                <p class="mb-0"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : __("not_set") ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <h6 class="mb-3"><?= __("account_information") ?></h6>
                        <div class="activity-timeline">
                            <div class="timeline-item">
                                <i class="activity-icon fas fa-calendar-alt bg-primary"></i>
                                <div class="timeline-content">
                                    <p class="mb-0"><?= __("account_created") ?></p>
                                    <small class="text-muted"><?= !empty($user['created_at']) ? date('M d, Y H:i A', strtotime($user['created_at'])) : __("not_available") ?></small>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __("close") ?></button>
                
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
                                                    <i class="feather icon-settings mr-2"></i>Profile Settings
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
                                                        <small class="text-muted d-block mt-2">Click to change profile picture</small>
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
                                                                <label for="updateName">Full Name</label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="email" class="form-control" id="updateEmail" name="email" 
                                                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                                                                <label for="updateEmail">Email Address</label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="tel" class="form-control" id="updatePhone" name="phone" 
                                                                       value="<?= htmlspecialchars($user['phone']) ?>">
                                                                <label for="updatePhone">Phone Number</label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <textarea class="form-control" id="updateAddress" name="address" 
                                                                          rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                                                                <label for="updateAddress">Address</label>
                                                            </div>
                                                        </div>

                                                        <!-- Password Section -->
                                                        <div class="settings-section mt-4">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-lock mr-2"></i>Change Password
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="password" class="form-control" id="currentPassword" 
                                                                       name="current_password">
                                                                <label for="currentPassword">Current Password</label>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="newPassword" 
                                                                               name="new_password">
                                                                        <label for="newPassword">New Password</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="confirmPassword" 
                                                                               name="confirm_password">
                                                                        <label for="confirmPassword">Confirm Password</label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 bg-light">
                                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                                    <i class="feather icon-x mr-2"></i>Cancel
                                                </button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather icon-save mr-2"></i>Save Changes
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>


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
                                            alert('Please enter your current password');
                                            return;
                                        }
                                        if (!newPassword) {
                                            alert('Please enter a new password');
                                            return;
                                        }
                                        if (!confirmPassword) {
                                            alert('Please confirm your new password');
                                            return;
                                        }
                                        if (newPassword !== confirmPassword) {
                                            alert('New passwords do not match');
                                            return;
                                        }
                                        if (newPassword.length < 6) {
                                            alert('New password must be at least 6 characters long');
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
                                            alert(data.message || 'Failed to update profile');
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        alert('An error occurred while updating the profile');
                                    });
                                });
                            });
                            </script>

    <script>
    // Function to preview image
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profilePreview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Function to cancel Hawala transfer
    function cancelHawala(hawalaId) {
        if (confirm('Are you sure you want to cancel this Hawala transfer?')) {
            fetch('ajax/cancel_hawala.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ hawala_id: hawalaId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while cancelling the transfer');
            });
        }
    }

    // Auto-select customer in modals
    document.addEventListener('DOMContentLoaded', function() {
        const customerId = <?= $customer_id ?>;
        
        // Function to set customer in modal
        function setCustomerInModal(modalId) {
            const modal = document.querySelector(modalId);
            if (modal) {
                const customerSelect = modal.querySelector('select[name="customer_id"], select[name="sender_id"]');
                if (customerSelect) {
                    customerSelect.value = customerId;
                    customerSelect.setAttribute('readonly', true);
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = customerSelect.name;
                    hiddenInput.value = customerId;
                    customerSelect.parentNode.appendChild(hiddenInput);
                }
            }
        }

        // Set customer ID in all transaction modals
        ['#depositModal', '#withdrawalModal', '#hawalaModal', '#exchangeModal'].forEach(modalId => {
            $(modalId).on('show.bs.modal', function() {
                setCustomerInModal(modalId);
            });
        });

        // Load initial balances
        loadCustomerBalance(customerId);
    });

    // Function to load customer balance
    function loadCustomerBalance(customerId) {
        const balanceContainer = document.getElementById('customerBalance');
        if (!balanceContainer) {
            console.warn('Customer balance container not found');
            return;
        }
        
        if (!customerId) {
            balanceContainer.innerHTML = '';
            return;
        }
        
        fetch('ajax/get_customer_balance.php?customer_id=' + customerId)
            .then(response => response.json())
            .then(data => {
                let balanceHtml = '';
                for (let currency in data) {
                    balanceHtml += `<div class="alert alert-info mb-2">
                        ${currency}: ${parseFloat(data[currency]).toFixed(2)}
                    </div>`;
                }
                balanceContainer.innerHTML = balanceHtml;
            })
            .catch(error => {
                console.error('Error:', error);
                balanceContainer.innerHTML = 
                    '<div class="alert alert-danger"><?= __("error_loading_balance") ?></div>';
            });
    }

    // Handle view transaction click
    $(document).on('click', '.view-transaction', function(e) {
        e.preventDefault();
        const transactionId = $(this).data('id');
        viewTransaction(transactionId);
    });

    // Function to view transaction details
    function viewTransaction(transactionId) {
        const modal = $('#transactionDetailsModal');
        const content = document.getElementById('transactionDetailsContent');
        
        content.innerHTML = `
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only"><?= __("loading") ?>...</span>
                </div>
            </div>
        `;
        
        modal.modal('show');
        
        fetch(`view_sarafi_transaction.php?id=${transactionId}`)
            .then(response => response.json())
            .then(response => {
                if (response.success) {
                    const data = response.data;
                    let details = `
                        <div class="row">
                            <div class="col-md-6">
                                <div class="transaction-details">
                                    <h6 class="text-primary mb-3">
                                        <i class="feather icon-file-text mr-2"></i>
                                        <?= __("transaction_details") ?>
                                    </h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <th><?= __("type") ?>:</th>
                                            <td>
                                                <span class="badge badge-${getTypeClass(data.transaction.type)}">
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
                                            <td>${data.transaction.reference_number || '-'}</td>
                                        </tr>
                                        <tr>
                                            <th><?= __("status") ?>:</th>
                                            <td>
                                                <span class="badge badge-${getStatusClass(data.transaction.status)}">
                                                    ${capitalizeFirstLetter(data.transaction.status)}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?= __("date") ?>:</th>
                                            <td>${formatDate(data.transaction.created_at)}</td>
                                        </tr>
                                        ${data.transaction.notes ? `
                                        <tr>
                                            <th><?= __("notes") ?>:</th>
                                            <td>${data.transaction.notes}</td>
                                        </tr>
                                        ` : ''}
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="transaction-details">
                                    <h6 class="text-primary mb-3">
                                        <i class="feather icon-user mr-2"></i>
                                        <?= __("customer_details") ?>
                                    </h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <th><?= __("name") ?>:</th>
                                            <td>${data.customer.name}</td>
                                        </tr>
                                        <tr>
                                            <th><?= __("phone") ?>:</th>
                                            <td>${data.customer.phone}</td>
                                        </tr>
                                        <tr>
                                            <th><?= __("wallet_balance") ?>:</th>
                                            <td>
                                                <span class="badge badge-${parseFloat(data.customer.wallet_balance) >= 0 ? 'success' : 'danger'}">
                                                    ${data.transaction.currency} ${parseFloat(data.customer.wallet_balance).toFixed(2)}
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>`;
                    content.innerHTML = details;
                } else {
                    content.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="feather icon-alert-circle mr-2"></i>
                            ${response.message || '<?= __("error_loading_transaction_details") ?>'}
                        </div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="feather icon-alert-circle mr-2"></i>
                        <?= __("error_loading_transaction_details") ?>
                    </div>`;
            });
    }

    // Function to delete deposit
    function deleteDeposit(transactionId, amount) {
        if (confirm('<?= __("are_you_sure_you_want_to_delete_this_deposit_transaction") ?>')) {
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
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the deposit');
            });
        }
    }

    // Function to delete withdrawal
    function deleteWithdrawal(transactionId, amount) {
        if (confirm('<?= __("are_you_sure_you_want_to_delete_this_withdrawal_transaction") ?>')) {
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
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the withdrawal');
            });
        }
    }

    // Function to delete hawala transfer
    function deleteHawala(transactionId, amount) {
        if (confirm('<?= __("are_you_sure_you_want_to_delete_this_hawala_transfer") ?>')) {
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
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the hawala transfer');
            });
        }
    }

    // Handle delete exchange transaction
    $(document).on('click', '.delete-exchange', function(e) {
        e.preventDefault();
        const transactionId = $(this).data('id');
        
        if (confirm('<?= __("are_you_sure_you_want_to_delete_this_exchange_transaction_this_action_cannot_be_undone") ?>')) {
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
                        alert(response.message || '<?= __("failed_to_delete_exchange_transaction") ?>');
                    }
                },
                error: function() {
                    alert('<?= __("error_occurred_while_deleting_exchange_transaction") ?>');
                }
            });
        }
    });

    // Handle delete transaction click
    $(document).on('click', '.delete-transaction', function(e) {
        e.preventDefault();
        const transactionId = $(this).data('id');
        const type = $(this).data('type');
        const amount = $(this).data('amount');
        
        switch(type) {
            case 'deposit':
                deleteDeposit(transactionId, amount);
                break;
            case 'withdrawal':
                deleteWithdrawal(transactionId, amount);
                break;
            case 'hawala_send':
                deleteHawala(transactionId, amount);
                break;
        }
    });

    // Handle print transaction
    $(document).on('click', '#printTransaction', function(e) {
        e.preventDefault();
        const content = document.getElementById('transactionDetailsContent').innerHTML;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Transaction Details</title>
                    <link rel="stylesheet" href="../assets/css/style.css">
                    <style>
                        body { padding: 20px; }
                        .modal-footer, .close, .btn-group-actions { display: none !important; }
                        @media print {
                            body { padding: 0; }
                            .transaction-details { page-break-inside: avoid; }
                        }
                    </style>
                </head>
                <body>
                    <div class="transaction-details">
                        ${content}
                    </div>
                    <script>
                        window.onload = function() {
                            window.print();
                            setTimeout(function() { window.close(); }, 500);
                        };
                    <\/script>
                </body>
            </html>
        `);
        printWindow.document.close();
    });

    // Helper function to capitalize first letter
    function capitalizeFirstLetter(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    // Helper function to format date
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Toast notification function
    function showToast(type, message) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="feather icon-${type === 'success' ? 'check-circle' : 'alert-circle'}"></i>
                <span>${message}</span>
            </div>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }, 100);
    }

    // Helper functions for transaction types and status
    function getTypeClass(type) {
        switch (type) {
            case 'deposit': return 'success';
            case 'withdrawal': return 'warning';
            case 'hawala_send':
            case 'hawala_receive': return 'info';
            case 'exchange': return 'primary';
            default: return 'secondary';
        }
    }

    function getStatusClass(status) {
        switch (status) {
            case 'completed': return 'success';
            case 'pending': return 'warning';
            case 'cancelled': return 'danger';
            default: return 'secondary';
        }
    }
    </script>

    <!-- Transaction Details Modal -->
    <div class="modal fade" id="transactionDetailsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="feather icon-file-text mr-2"></i>
                        <?= __("transaction_details") ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="transactionDetailsContent">
                        <div class="text-center p-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only"><?= __("loading") ?>...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-2"></i><?= __("close") ?>
                    </button>
                    <button type="button" class="btn btn-primary" id="printTransaction">
                        <i class="feather icon-printer mr-2"></i><?= __("print") ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Modals -->
    <?php 
    // Make customer data available to modals
    $customers = [$customer];
    include 'includes/sarafi_modals.php'; 
    ?>
</body>
</html> 