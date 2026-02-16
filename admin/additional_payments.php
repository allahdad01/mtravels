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
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle direct form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    // Include the update script directly
    include 'includes/update_additional_payment_base.php';
    exit(); // Stop execution after processing the update
}

// Database connection
require_once('../includes/db.php');

// Load input validation helper
require_once '../includes/InputValidator.php';

// Fetch main accounts for dropdown
$mainAccountsQuery = "SELECT * FROM main_account WHERE status = 'active' AND tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($mainAccountsQuery);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$mainAccounts = $stmt->fetchAll();

// Fetch suppliers for dropdown
 $suppliersQuery = "SELECT * FROM suppliers WHERE status = 'active' AND supplier_type = 'external' AND tenant_id = ? AND branch_id = ?";
 $stmt = $pdo->prepare($suppliersQuery);
 $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
 $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
 $stmt->execute();
 $suppliers = $stmt->fetchAll();

// Fetch clients for dropdown
 $clientsQuery = "SELECT * FROM clients WHERE status = 'active' AND tenant_id = ? AND branch_id = ?";
 $stmt = $pdo->prepare($clientsQuery);
 $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
 $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
 $stmt->execute();
 $clients = $stmt->fetchAll();

// Pagination settings
$items_per_page = 10;
$current_page = InputValidator::getInt($_GET['page'] ?? '', 1, 1, 9999);
$offset = ($current_page - 1) * $items_per_page;

// Search functionality - validate and sanitize search query
$search_query = InputValidator::getString($_GET['search'] ?? '', 100);
$search_condition = '';

if (!empty($search_query)) {
    $search_condition = " AND (
        ap.payment_type LIKE ? OR
        ap.description LIKE ? OR
        ma.name LIKE ? OR
        s.name LIKE ? OR
        c.name LIKE ?
    )";
}

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM additional_payments ap
              LEFT JOIN users u ON ap.created_by = u.id
              LEFT JOIN main_account ma ON ap.main_account_id = ma.id
              LEFT JOIN suppliers s ON ap.supplier_id = s.id
              LEFT JOIN clients c ON ap.client_id = c.id
              WHERE ap.tenant_id = ? AND ap.branch_id = ?" . $search_condition;
$countParams = [$tenant_id, $branch_id];
if (!empty($search_query)) {
    $search_param = '%' . $search_query . '%';
    $countParams = array_merge($countParams, array_fill(0, 5, $search_param));
}
$stmt = $pdo->prepare($countQuery);
$stmt->execute($countParams);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $items_per_page);

// Get all additional payments with pagination
 $paymentsQuery = "SELECT ap.*, u.name as created_by_name, ma.name as main_account_name,
                   s.name as supplier_name, s.id as supplier_id,
                   c.name as client_name, c.id as client_id
                   FROM additional_payments ap
                   LEFT JOIN users u ON ap.created_by = u.id
                   LEFT JOIN main_account ma ON ap.main_account_id = ma.id
                   LEFT JOIN suppliers s ON ap.supplier_id = s.id
                   LEFT JOIN clients c ON ap.client_id = c.id
                   WHERE ap.tenant_id = ? AND ap.branch_id = ?" . $search_condition . "
                   ORDER BY ap.created_at DESC
                   LIMIT ? OFFSET ?";
 $stmt = $pdo->prepare($paymentsQuery);
 $params = [$tenant_id, $branch_id];
 if (!empty($search_query)) {
     $search_param = '%' . $search_query . '%';
     $params = array_merge($params, array_fill(0, 5, $search_param));
 }
 $params[] = $items_per_page;
 $params[] = $offset;
 $stmt->execute($params);
 $payments = $stmt->fetchAll();
?>

    <style>
    /* Enhanced custom styles for better layout and design */
    .page-header.card {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        color: #ffffff;
        border: none;
        margin-bottom: 20px;
        padding: 20px !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-radius: 10px;
    }

    .page-header.card .row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-header.card h5 {
        color: #ffffff;
        margin: 0;
        font-weight: 600;
    }

    .page-header.card .text-end {
        text-align: right;
    }

    .page-header.card .btn {
        background: rgba(255,255,255,0.2);
        color: #ffffff;
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 25px;
        transition: all 0.3s ease;
    }

    .page-header.card .btn:hover {
        background: rgba(255,255,255,0.3);
        border-color: rgba(255,255,255,0.5);
        transform: translateY(-1px);
    }

    .card {
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        border: none;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px 10px 0 0;
        padding: 1rem 1.5rem;
        border: none;
    }

    .card-header h5 {
        margin: 0;
        font-weight: 600;
        display: flex;
        align-items: center;
    }

    .progress {
        border-radius: 15px;
        overflow: hidden;
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
    }

    .progress-bar {
        transition: width 0.6s ease;
    }

    .badge {
        font-size: 0.85em;
        padding: 0.5em 0.75em;
        border-radius: 20px;
        font-weight: 500;
    }

    .badge-success {
        background-color: #28a745;
    }

    .badge-warning {
        background-color: #ffc107;
        color: #212529;
    }

    .badge-info {
        background-color: #17a2b8;
    }

    .table-responsive {
        border-radius: 10px;
        overflow: hidden;
    }

    .table {
        margin-bottom: 0;
    }

    .table thead th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        color: #495057;
        padding: 1rem;
    }

    .table tbody tr:hover {
        background-color: #f1f3f4;
    }

    .table tbody td {
        padding: 1rem;
        vertical-align: middle;
    }

    .form-control {
        border-radius: 8px;
        border: 1px solid #ced4da;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        padding: 0.75rem;
    }

    .form-control:focus {
        border-color: #4099ff;
        box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
    }

    .btn-primary {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        border: none;
        border-radius: 25px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
    }

    .btn-secondary {
        border-radius: 25px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .alert {
        border-radius: 10px;
        border: none;
        padding: 1rem 1.5rem;
    }

    .alert-info {
        background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
        color: #0c5460;
    }

    .alert-success {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
    }

    .alert-danger {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
    }

    #estimated_cost {
        color: #28a745;
        font-weight: bold;
    }

    .h2 {
        font-size: 2.5rem;
    }

    .h4 {
        font-size: 1.5rem;
    }

    .h5 {
        font-size: 1.25rem;
    }

    .h6 {
        font-size: 1rem;
    }
    </style>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../css/general/modal-styles.css">

    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="main-content">
                                <div class="page-header card">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h5><i class="feather icon-plus-circle mr-2"></i><?= __('additional_payments') ?></h5>
                                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;"><?php echo __('manage_additional_payments'); ?></p>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                                <i class="feather icon-arrow-left mr-1"></i><?php echo __('back_to_dashboard'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                <div class="col-md-12">
                                    <div class="card">
                                         <div class="card-header">
                                             <h5><?php echo __('payment_list'); ?></h5>
                                             <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addPaymentModal">
                                                 <i class="feather icon-plus mr-1"></i> <?= __('add_new_payment') ?>
                                             </button>
                                         </div>

                                         <!-- Search Bar -->
                                         <div class="card-body border-bottom pb-3">
                                             <form method="GET" class="form-inline">
                                                 <div class="form-group mb-0 flex-grow-1">
                                                     <input 
                                                         type="text" 
                                                         name="search" 
                                                         class="form-control w-100" 
                                                         placeholder="Search by payment type, description, account name..." 
                                                         value="<?= htmlspecialchars($search_query) ?>"
                                                     >
                                                 </div>
                                                 <button type="submit" class="btn btn-info ml-2">
                                                     <i class="feather icon-search"></i> Search
                                                 </button>
                                                 <?php if (!empty($search_query)): ?>
                                                     <a href="additional_payments.php" class="btn btn-secondary ml-2">
                                                         <i class="feather icon-x"></i> Clear
                                                     </a>
                                                 <?php endif; ?>
                                             </form>
                                         </div>

                                         <div class="card-body">
                                            <?php if (isset($_SESSION['success'])): ?>
                                                <div class="alert alert-success"><?php echo h($_SESSION['success']); unset($_SESSION['success']); ?></div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($_SESSION['error'])): ?>
                                                <div class="alert alert-danger"><?php echo h($_SESSION['error']); unset($_SESSION['error']); ?></div>
                                            <?php endif; ?>

                                            <!-- Pagination Info -->
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <small class="text-muted">
                                                        Showing <?= $offset + 1 ?> to <?= min($offset + $items_per_page, $total_records) ?> of <?= $total_records ?> entries
                                                    </small>
                                                </div>
                                            </div>

                                            <!-- Payment Table -->
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th><?= __('actions') ?></th>
                                                            <th><?= __('payment_type') ?></th>
                                                            <th><?= __('description') ?></th>
                                                            <th><?= __('financial_details') ?></th>   
                                                            <th><?= __('accounts') ?></th>
                                                            <th><?= __('created_by') ?></th> 
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($payments as $payment): ?>
                                                            <tr>
                                                             <td>
                                                                 <div class="dropdown">
                                                                     <button class="btn btn-icon btn-outline-primary dropdown-toggle" type="button" data-toggle="dropdown">
                                                                         <i class="feather icon-more-horizontal"></i>
                                                                     </button>
                                                                     <div class="dropdown-menu dropdown-menu-right">
                                                                         <a class="dropdown-item" href="javascript:void(0)" 
                                                                            data-id="<?= $payment['id'] ?>"
                                                                            data-payment-type="<?= htmlspecialchars($payment['payment_type']) ?>"
                                                                            data-currency="<?= htmlspecialchars($payment['currency']) ?>"
                                                                            data-main-account="<?= $payment['main_account_id'] ?>"
                                                                            data-supplier="<?= $payment['supplier_id'] ?>"
                                                                            data-client="<?= $payment['client_id'] ?>"
                                                                            data-receipt="<?= htmlspecialchars($payment['receipt'] ?? '') ?>"
                                                                            data-description="<?= htmlspecialchars($payment['description'] ?? '') ?>"
                                                                            data-sold-amount="<?= $payment['sold_amount'] ?>"
                                                                            onclick="document.querySelector('.add-transaction[data-id=\'' + this.dataset.id + '\']')?.click();">
                                                                             <i class="feather icon-plus mr-2"></i><?= __('add_transaction') ?>
                                                                         </a>
                                                                         <a class="dropdown-item" href="javascript:void(0)" 
                                                                            data-id="<?= $payment['id'] ?>"
                                                                            data-payment-type="<?= htmlspecialchars($payment['payment_type']) ?>"
                                                                            data-description="<?= htmlspecialchars($payment['description']) ?>"
                                                                            data-base-amount="<?= $payment['base_amount'] ?>"
                                                                            data-profit="<?= $payment['profit'] ?>"
                                                                            data-sold-amount="<?= $payment['sold_amount'] ?>"
                                                                            data-currency="<?= htmlspecialchars($payment['currency']) ?>"
                                                                            data-main-account="<?= $payment['main_account_id'] ?>"
                                                                            data-supplier="<?= $payment['supplier_id'] ?>"
                                                                            data-client="<?= $payment['client_id'] ?>"
                                                                            data-receipt="<?= htmlspecialchars($payment['receipt'] ?? '') ?>"
                                                                            onclick="document.querySelector('.edit-payment[data-id=\'' + this.dataset.id + '\']')?.dispatchEvent(new Event('click', {bubbles: true}));">
                                                                             <i class="feather icon-edit mr-2"></i><?= __('edit') ?>
                                                                         </a>
                                                                         <div class="dropdown-divider"></div>
                                                                         <a class="dropdown-item text-danger" href="javascript:void(0)" 
                                                                            data-id="<?= $payment['id'] ?>"
                                                                            onclick="document.querySelector('.delete-payment[data-id=\'' + this.dataset.id + '\']')?.dispatchEvent(new Event('click', {bubbles: true}));">
                                                                             <i class="feather icon-trash mr-2"></i><?= __('delete') ?>
                                                                         </a>
                                                                     </div>
                                                                 </div>
                                                                 <!-- Hidden buttons to maintain existing functionality -->
                                                                 <button style="display:none;" class="btn btn-sm btn-success add-transaction" 
                                                                             data-id="<?= $payment['id'] ?>"
                                                                             data-payment-type="<?= htmlspecialchars($payment['payment_type']) ?>"
                                                                             data-currency="<?= htmlspecialchars($payment['currency']) ?>"
                                                                             data-main-account="<?= $payment['main_account_id'] ?>"
                                                                             data-supplier="<?= $payment['supplier_id'] ?>"
                                                                             data-client="<?= $payment['client_id'] ?>"
                                                                             data-receipt="<?= htmlspecialchars($payment['receipt'] ?? '') ?>"
                                                                             data-description="<?= htmlspecialchars($payment['description'] ?? '') ?>"
                                                                             data-sold-amount="<?= $payment['sold_amount'] ?>">
                                                                             <i class="feather icon-plus"></i>
                                                                 </button>
                                                                 <button style="display:none;" class="btn btn-sm btn-primary edit-payment" 
                                                                             data-id="<?= $payment['id'] ?>"
                                                                             data-payment-type="<?= htmlspecialchars($payment['payment_type']) ?>"
                                                                             data-description="<?= htmlspecialchars($payment['description']) ?>"
                                                                             data-base-amount="<?= $payment['base_amount'] ?>"
                                                                             data-profit="<?= $payment['profit'] ?>"
                                                                             data-sold-amount="<?= $payment['sold_amount'] ?>"
                                                                             data-currency="<?= htmlspecialchars($payment['currency']) ?>"
                                                                             data-main-account="<?= $payment['main_account_id'] ?>"
                                                                             data-supplier="<?= $payment['supplier_id'] ?>"
                                                                             data-client="<?= $payment['client_id'] ?>"
                                                                             data-receipt="<?= htmlspecialchars($payment['receipt'] ?? '') ?>">
                                                                             <i class="feather icon-edit"></i>
                                                                             </button>
                                                                 <button style="display:none;" class="btn btn-sm btn-danger delete-payment" 
                                                                             data-id="<?= $payment['id'] ?>">
                                                                     <i class="feather icon-trash"></i>
                                                                 </button>
                                                                 </td>
                                                                <td><?= htmlspecialchars($payment['payment_type']) ?></br>

                                                               <?php
                                                                   // Calculate payment status using the same logic as JavaScript transaction manager
                                                                   $baseCurrency = $payment['currency'];
                                                                   $soldAmount = floatval($payment['sold_amount']);
                                                                   $totalPaidInBase = 0.0;

                                                                   // Get payment ID
                                                                   $paymentId = $payment['id'];

                                                                   // Query transactions from main_account_transactions table
                                                                   $transactionStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE
                                                                       transaction_of = 'additional_payment'
                                                                       AND reference_id = ? AND tenant_id = ? AND branch_id = ?");
                                                                   $transactionStmt->bindParam(1, $paymentId, PDO::PARAM_INT);
                                                                   $transactionStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                                                   $transactionStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                                                   $transactionStmt->execute();
                                                                   $transactions = $transactionStmt->fetchAll();

                                                                   // Initialize default exchange rates (same as JavaScript)
                                                                   $usdToAfsRate = 70;
                                                                   $usdToEurRate = 0.9;
                                                                   $usdToDarhamRate = 3.61;

                                                                   // First pass: extract exchange rates from transactions
                                                                   if ($transactions && count($transactions) > 0) {
                                                                       foreach ($transactions as $transaction) {
                                                                           // Update exchange rates if transaction has a rate
                                                                           $transExchangeRate = isset($transaction['exchange_rate']) && $transaction['exchange_rate'] > 0 ? floatval($transaction['exchange_rate']) : null;
                                                                           if ($transExchangeRate) {
                                                                               if ($transaction['currency'] === 'AFS') {
                                                                                   $usdToAfsRate = $transExchangeRate;
                                                                               } elseif ($transaction['currency'] === 'EUR') {
                                                                                   $usdToEurRate = $transExchangeRate;
                                                                               } elseif ($transaction['currency'] === 'DARHAM') {
                                                                                   $usdToDarhamRate = $transExchangeRate;
                                                                               }
                                                                           }
                                                                       }

                                                                       // Second pass: calculate total paid using extracted rates
                                                                       foreach ($transactions as $transaction) {
                                                                           $amount = floatval($transaction['amount']);
                                                                           $transCurrency = $transaction['currency'];

                                                                           // Use transaction-specific rate if available, otherwise use default
                                                                           $transExchangeRate = isset($transaction['exchange_rate']) && $transaction['exchange_rate'] > 0 ? floatval($transaction['exchange_rate']) : null;
                                                                           $exchangeRateToUse = $transExchangeRate;

                                                                           if (!$exchangeRateToUse) {
                                                                               // Use default exchange rates when transaction doesn't have a rate
                                                                               if ($baseCurrency === 'USD') {
                                                                                   if ($transCurrency === 'AFS') $exchangeRateToUse = $usdToAfsRate;
                                                                                   elseif ($transCurrency === 'EUR') $exchangeRateToUse = $usdToEurRate;
                                                                                   elseif ($transCurrency === 'DARHAM') $exchangeRateToUse = $usdToDarhamRate;
                                                                               } elseif ($baseCurrency === 'AFS') {
                                                                                   if ($transCurrency === 'USD') $exchangeRateToUse = 1 / $usdToAfsRate;
                                                                                   elseif ($transCurrency === 'EUR') $exchangeRateToUse = $usdToEurRate / $usdToAfsRate;
                                                                                   elseif ($transCurrency === 'DARHAM') $exchangeRateToUse = $usdToDarhamRate / $usdToAfsRate;
                                                                               } elseif ($baseCurrency === 'EUR') {
                                                                                   if ($transCurrency === 'USD') $exchangeRateToUse = 1 / $usdToEurRate;
                                                                                   elseif ($transCurrency === 'AFS') $exchangeRateToUse = $usdToAfsRate / $usdToEurRate;
                                                                                   elseif ($transCurrency === 'DARHAM') $exchangeRateToUse = $usdToDarhamRate / $usdToEurRate;
                                                                               } elseif ($baseCurrency === 'DARHAM') {
                                                                                   if ($transCurrency === 'USD') $exchangeRateToUse = 1 / $usdToDarhamRate;
                                                                                   elseif ($transCurrency === 'AFS') $exchangeRateToUse = $usdToAfsRate / $usdToDarhamRate;
                                                                                   elseif ($transCurrency === 'EUR') $exchangeRateToUse = $usdToEurRate / $usdToDarhamRate;
                                                                               }
                                                                           }

                                                                           $convertedAmount = $amount;
                                                                           if ($transCurrency !== $baseCurrency && $exchangeRateToUse) {
                                                                               if ($baseCurrency === 'USD') {
                                                                                   if ($transCurrency === 'AFS') {
                                                                                       $convertedAmount = $amount / $exchangeRateToUse;
                                                                                   } elseif ($transCurrency === 'EUR') {
                                                                                       $convertedAmount = $amount / $exchangeRateToUse;
                                                                                   } elseif ($transCurrency === 'DARHAM') {
                                                                                       $convertedAmount = $amount / $exchangeRateToUse;
                                                                                   }
                                                                               } elseif ($baseCurrency === 'AFS') {
                                                                                   if ($transCurrency === 'USD') {
                                                                                       $convertedAmount = $amount * $exchangeRateToUse;
                                                                                   } elseif ($transCurrency === 'EUR') {
                                                                                       $convertedAmount = $amount * $exchangeRateToUse;
                                                                                   } elseif ($transCurrency === 'DARHAM') {
                                                                                       $convertedAmount = $amount * $exchangeRateToUse;
                                                                                   }
                                                                               } elseif ($baseCurrency === 'EUR') {
                                                                                   if ($transCurrency === 'USD') {
                                                                                       $convertedAmount = $amount * $exchangeRateToUse;
                                                                                   } elseif ($transCurrency === 'AFS') {
                                                                                       $convertedAmount = $amount * $exchangeRateToUse;
                                                                                   } elseif ($transCurrency === 'DARHAM') {
                                                                                       $convertedAmount = $amount * $exchangeRateToUse;
                                                                                   }
                                                                               } elseif ($baseCurrency === 'DARHAM') {
                                                                                   if ($transCurrency === 'USD') {
                                                                                       $convertedAmount = $amount * $exchangeRateToUse;
                                                                                   } elseif ($transCurrency === 'AFS') {
                                                                                       $convertedAmount = $amount * $exchangeRateToUse;
                                                                                   } elseif ($transCurrency === 'EUR') {
                                                                                       $convertedAmount = $amount * $exchangeRateToUse;
                                                                                   }
                                                                               }
                                                                           }

                                                                           $totalPaidInBase += $convertedAmount;
                                                                       }
                                                                   }

                                                                   // Status icon based on payment status (same logic as JavaScript)
                                                                   if ($totalPaidInBase <= 0) {
                                                                       // No transactions
                                                                       echo '<i class="fas fa-circle text-danger" title="No payment received"></i>';
                                                                   } elseif ($totalPaidInBase < ($soldAmount - 0.01)) {
                                                                       // Partial payment
                                                                       $percentage = round(($totalPaidInBase / $soldAmount) * 100);
                                                                       echo '<i class="fas fa-circle text-warning" style="color: #ffc107 !important;"
                                                                           title="Partial payment: ' . $baseCurrency . ' ' . number_format($totalPaidInBase, 2) . ' / ' . $baseCurrency . ' ' .
                                                                           number_format($soldAmount, 2) . ' (' . $percentage . '%)"></i>';
                                                                   } elseif (abs($totalPaidInBase - $soldAmount) < 0.01) {
                                                                       // Fully paid (with a small tolerance for floating-point comparison)
                                                                       echo '<i class="fas fa-circle text-success" title="Fully paid"></i>';
                                                                   } else {
                                                                       // Overpaid
                                                                       echo '<i class="fas fa-circle text-success"
                                                                           title="Fully paid (overpaid by ' . $baseCurrency . ' ' .
                                                                           number_format($totalPaidInBase - $soldAmount, 2) . ')"></i>';
                                                                   }
                                                               ?>
                                                                </td>

                                                                
                                                                <td style="max-width: 300px; word-wrap: break-word; white-space: normal;"><?= htmlspecialchars($payment['description']) ?></td>
                                                                <td> Base: <?= number_format($payment['base_amount'], 2) ?> <?= htmlspecialchars($payment['currency']) ?></br>
                                                                Sold: <?= number_format($payment['sold_amount'], 2) ?> <?= htmlspecialchars($payment['currency']) ?></br>
                                                                Profit: <?= number_format($payment['profit'], 2) ?> <?= htmlspecialchars($payment['currency']) ?></td>
                                                                
                                                                
                                                                <td> Main Account: <?= htmlspecialchars($payment['main_account_name']) ?></br>
                                                                Supplier: <?= htmlspecialchars($payment['supplier_name'] ?? 'N/A') ?></br>
                                                                Client: <?= htmlspecialchars($payment['client_name'] ?? 'N/A') ?></td>
                                                                <td>Created By: <?= htmlspecialchars($payment['created_by_name']) ?></br>
                                                                Created At: <?= date('Y-m-d H:i:s', strtotime($payment['created_at'])) ?></td>
                                                                
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                                </div>
                                                <!-- Pagination Controls -->
                                                <div class="row mt-4 p-3">
                                                 <div class="col-md-12">
                                                     <nav aria-label="Page navigation">
                                                         <ul class="pagination justify-content-center">
                                                             <?php
                                                             // Helper function to build pagination links with search parameter
                                                             $search_param = !empty($search_query) ? '&search=' . urlencode($search_query) : '';
                                                             ?>
                                                             <?php if ($current_page > 1): ?>
                                                                 <li class="page-item">
                                                                     <a class="page-link" href="?page=1<?= $search_param ?>">First</a>
                                                                 </li>
                                                                 <li class="page-item">
                                                                     <a class="page-link" href="?page=<?= $current_page - 1 ?><?= $search_param ?>">Previous</a>
                                                                 </li>
                                                             <?php else: ?>
                                                                 <li class="page-item disabled">
                                                                     <span class="page-link">First</span>
                                                                 </li>
                                                                 <li class="page-item disabled">
                                                                     <span class="page-link">Previous</span>
                                                                 </li>
                                                             <?php endif; ?>

                                                             <?php
                                                             // Show page numbers
                                                             $start_page = max(1, $current_page - 2);
                                                             $end_page = min($total_pages, $current_page + 2);

                                                             if ($start_page > 1):
                                                             ?>
                                                                 <li class="page-item disabled"><span class="page-link">...</span></li>
                                                             <?php endif; ?>

                                                             <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                                 <?php if ($i == $current_page): ?>
                                                                     <li class="page-item active"><span class="page-link"><?= $i ?></span></li>
                                                                 <?php else: ?>
                                                                     <li class="page-item"><a class="page-link" href="?page=<?= $i ?><?= $search_param ?>"><?= $i ?></a></li>
                                                                 <?php endif; ?>
                                                             <?php endfor; ?>

                                                             <?php if ($end_page < $total_pages): ?>
                                                                 <li class="page-item disabled"><span class="page-link">...</span></li>
                                                             <?php endif; ?>

                                                             <?php if ($current_page < $total_pages): ?>
                                                                 <li class="page-item">
                                                                     <a class="page-link" href="?page=<?= $current_page + 1 ?><?= $search_param ?>">Next</a>
                                                                 </li>
                                                                 <li class="page-item">
                                                                     <a class="page-link" href="?page=<?= $total_pages ?><?= $search_param ?>">Last</a>
                                                                 </li>
                                                             <?php else: ?>
                                                                 <li class="page-item disabled">
                                                                     <span class="page-link">Next</span>
                                                                 </li>
                                                                 <li class="page-item disabled">
                                                                     <span class="page-link">Last</span>
                                                                 </li>
                                                             <?php endif; ?>
                                                         </ul>
                                                     </nav>
                                                 </div>
                                                </div>
                                                </div>
                                                </div>
                                                </div>
                                                </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Modal Files -->
    <?php include '../modals/additional_payment/add_payment_modal.php'; ?>
    <?php include '../modals/additional_payment/edit_payment_modal.php'; ?>
    <?php include '../modals/additional_payment/add_transaction_modal.php'; ?>
    <?php include '../modals/additional_payment/edit_transaction_modal.php'; ?>

    <!-- Required Js -->
    
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- Additional Payment Scripts -->
    <script src="../js/additional_payments/transactions.js"></script>
    <script src="../js/additional_payments/main.js"></script>
    <script src="../js/additional_payments/button-protection.js"></script>


<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>