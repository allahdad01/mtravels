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

// Debug CSRF token - remove in production
error_log("CSRF Token in session: " . $_SESSION['csrf_token']);

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
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Search functionality
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
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
        /* Modern Card Styling */
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            border: none;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: none;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
        }

        /* Form Styling */
        .form-control, .form-select {
            border-radius: 8px;
            padding: 12px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        /* Button Styling */
        .btn {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
        }

        .btn-danger {
            background: linear-gradient(45deg, #dc3545, #c82333);
            border: none;
        }

        .btn-success {
            background: linear-gradient(45deg, #28a745, #218838);
            border: none;
        }

        /* Table Styling */
        .table-responsive {
            border-radius: 15px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .table {
            margin-bottom: 0;
            min-width: 1000px; /* Ensures table doesn't shrink too much */
            white-space: nowrap;
        }

        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            padding: 15px;
        }

        .table td {
            padding: 12px 15px;
            vertical-align: middle;
        }

        .table tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }

        /* Modal Styling */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            border-radius: 15px 15px 0 0;
            border: none;
        }

        .modal-footer {
            border-top: 1px solid #e0e0e0;
            padding: 1.5rem;
        }

        /* Alert Styling */
        .alert {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .alert-success {
            background: linear-gradient(45deg, #28a745, #218838);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(45deg, #dc3545, #c82333);
            color: white;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
            align-items: center;
        }

        .action-buttons .btn {
            padding: 6px 10px;
            font-size: 14px;
            min-width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-buttons .btn i {
            margin: 0;
            font-size: 14px;
        }

        .action-buttons .btn-primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
        }

        .action-buttons .btn-danger {
            background: linear-gradient(45deg, #dc3545, #c82333);
        }

        .action-buttons .btn-success {
            background: linear-gradient(45deg, #28a745, #218838);
        }

        .action-buttons .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: row;
                gap: 3px;
            }
            
            .action-buttons .btn {
                padding: 4px 8px;
                min-width: 28px;
                height: 28px;
            }
        }

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
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../css/general/modal-styles.css">

    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="card">
                                         <div class="card-header">
                                             <h5><?= __('additional_payments') ?></h5>
                                             <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addPaymentModal">
                                                 <i class="feather icon-plus"></i> <?= __('add_new_payment') ?>
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
                                                                            data-receipt="<?= htmlspecialchars($payment['receipt']) ?>"
                                                                            data-description="<?= htmlspecialchars($payment['description']) ?>"
                                                                            data-sold-amount="<?= $payment['sold_amount'] ?>"
                                                                            onclick="this.dispatchEvent(new CustomEvent('click', {bubbles: true})); document.querySelector('.add-transaction[data-id=\'' + this.dataset.id + '\']')?.click();">
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
                                                                            data-receipt="<?= htmlspecialchars($payment['receipt']) ?>"
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
                                                                             data-receipt="<?= htmlspecialchars($payment['receipt']) ?>"
                                                                             data-description="<?= htmlspecialchars($payment['description']) ?>"
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
                                                                             data-receipt="<?= htmlspecialchars($payment['receipt']) ?>">
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