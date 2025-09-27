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
require_once('../includes/conn.php');

// Fetch main accounts for dropdown
$mainAccountsQuery = "SELECT * FROM main_account WHERE status = 'active' AND tenant_id = ?";
$stmt = $conn->prepare($mainAccountsQuery);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$mainAccountsResult = $stmt->get_result();
$mainAccounts = [];
if ($mainAccountsResult && $mainAccountsResult->num_rows > 0) {
    $mainAccounts = $mainAccountsResult->fetch_all(MYSQLI_ASSOC);
}

// Fetch suppliers for dropdown
$suppliersQuery = "SELECT * FROM suppliers WHERE status = 'active' AND supplier_type = 'external' AND tenant_id = ?";
$stmt = $conn->prepare($suppliersQuery);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$suppliersResult = $stmt->get_result();
$suppliers = [];
if ($suppliersResult && $suppliersResult->num_rows > 0) {
    $suppliers = $suppliersResult->fetch_all(MYSQLI_ASSOC);
}

// Fetch clients for dropdown
$clientsQuery = "SELECT * FROM clients WHERE status = 'active' AND tenant_id = ?";
$stmt = $conn->prepare($clientsQuery);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$clientsResult = $stmt->get_result();
$clients = [];
if ($clientsResult && $clientsResult->num_rows > 0) {
    $clients = $clientsResult->fetch_all(MYSQLI_ASSOC);
}

// Get all additional payments
$paymentsQuery = "SELECT ap.*, u.name as created_by_name, ma.name as main_account_name, 
                 s.name as supplier_name, s.id as supplier_id,
                 c.name as client_name, c.id as client_id
                 FROM additional_payments ap 
                 LEFT JOIN users u ON ap.created_by = u.id 
                 LEFT JOIN main_account ma ON ap.main_account_id = ma.id
                 LEFT JOIN suppliers s ON ap.supplier_id = s.id
                 LEFT JOIN clients c ON ap.client_id = c.id
                 WHERE ap.tenant_id = ?
                 ORDER BY ap.created_at DESC";
$stmt = $conn->prepare($paymentsQuery);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$paymentsResult = $stmt->get_result();
$payments = $paymentsResult->fetch_all(MYSQLI_ASSOC);
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
    </style>
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
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="css/modal-styles.css">

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
                                        <div class="card-body">
                                            <?php if (isset($_SESSION['success'])): ?>
                                                <div class="alert alert-success"><?php echo h($_SESSION['success']); unset($_SESSION['success']); ?></div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($_SESSION['error'])): ?>
                                                <div class="alert alert-danger"><?php echo h($_SESSION['error']); unset($_SESSION['error']); ?></div>
                                            <?php endif; ?>

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
                                                                    <div class="action-buttons">
                                                                        
                                                                    <button class="btn btn-sm btn-success add-transaction" 
                                                                                data-id="<?= $payment['id'] ?>"
                                                                                data-payment-type="<?= htmlspecialchars($payment['payment_type']) ?>"
                                                                                data-currency="<?= htmlspecialchars($payment['currency']) ?>"
                                                                                data-main-account="<?= $payment['main_account_id'] ?>"
                                                                                data-supplier="<?= $payment['supplier_id'] ?>"
                                                                                data-client="<?= $payment['client_id'] ?>"
                                                                                data-receipt="<?= htmlspecialchars($payment['receipt']) ?>"
                                                                                data-description="<?= htmlspecialchars($payment['description']) ?>"
                                                                                data-sold-amount="<?= $payment['sold_amount'] ?>"
                                                                                title="Add Transaction">
                                                                            <i class="feather icon-plus"></i>
                                                                        </button>
                                                                        <button class="btn btn-sm btn-primary edit-payment" 
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
                                                                                title="Edit Payment">
                                                                            <i class="feather icon-edit"></i>
                                                                        </button>
                                                                        <button class="btn btn-sm btn-danger delete-payment" 
                                                                                data-id="<?= $payment['id'] ?>"
                                                                                title="Delete Payment">
                                                                            <i class="feather icon-trash"></i>
                                                                        </button>
                                                                    </div>
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
                                                                   $transactionQuery = $conn->query("SELECT * FROM main_account_transactions WHERE
                                                                       transaction_of = 'additional_payment'
                                                                       AND reference_id = '$paymentId'");

                                                                   // Initialize default exchange rates (same as JavaScript)
                                                                   $usdToAfsRate = 70;
                                                                   $usdToEurRate = 0.9;
                                                                   $usdToDarhamRate = 3.61;

                                                                   // First pass: extract exchange rates from transactions
                                                                   if ($transactionQuery && $transactionQuery->num_rows > 0) {
                                                                       $transactions = [];
                                                                       mysqli_data_seek($transactionQuery, 0); // Reset pointer
                                                                       while ($transaction = $transactionQuery->fetch_assoc()) {
                                                                           $transactions[] = $transaction;

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

    <!-- Add Payment Modal -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1" role="dialog" aria-labelledby="addPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addPaymentModalLabel"><?= __('add_new_payment') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="addPaymentForm" method="POST" action="includes/add_additional_payment.php">
                        <!-- CSRF Protection -->
                        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                        
                        <div class="form-group">
                            <label for="payment_type"><?= __('payment_type') ?></label>
                            <input type="text" class="form-control" id="payment_type" name="payment_type" required>
                        </div>
                        <div class="form-group">
                            <label for="main_account_id"><?= __('main_account') ?></label>
                            <select class="form-control" id="main_account_id" name="main_account_id" required>
                                <option value=""><?= __('select_main_account') ?></option>
                                <?php foreach ($mainAccounts as $account): ?>
                                    <option value="<?= $account['id'] ?>">
                                        <?= htmlspecialchars($account['name']) ?> 
                                      
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="description"><?= __('description') ?></label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="base_amount"><?= __('base_amount') ?></label>
                            <input type="number" class="form-control" id="base_amount" name="base_amount" required step="0.01" onchange="calculateProfit()">
                        </div>
                        <div class="form-group">
                            <label for="sold_amount"><?= __('sold_amount') ?></label>
                            <input type="number" class="form-control" id="sold_amount" name="sold_amount" required step="0.01" onchange="calculateProfit()">
                        </div>
                        <div class="form-group">
                            <label for="profit"><?= __('profit') ?></label>
                            <input type="number" class="form-control" id="profit" name="profit" required step="0.01" readonly>
                        </div>
                        <div class="form-group">
                            <label for="currency"><?= __('currency') ?></label>
                            <select class="form-control" id="currency" name="currency" required>
                                <option value="USD"><?= __('usd') ?></option>
                                <option value="AFS"><?= __('afs') ?></option>
                                <option value="EUR"><?= __('eur') ?></option>
                                <option value="DARHAM"><?= __('darham') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="is_from_supplier" name="is_from_supplier">
                                <label class="custom-control-label" for="is_from_supplier"><?= __('bought_from_supplier') ?></label>
                            </div>
                        </div>
                        <div class="form-group supplier-group" style="display: none;">
                            <label for="supplier_id"><?= __('supplier') ?></label>
                            <select class="form-control" id="supplier_id" name="supplier_id">
                                <option value=""><?= __('select_supplier') ?></option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier['id'] ?>">
                                        <?= htmlspecialchars($supplier['name']) ?> 
                                       
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="is_for_client" name="is_for_client">
                                <label class="custom-control-label" for="is_for_client"><?= __('sold_to_client') ?></label>
                            </div>
                        </div>
                        <div class="form-group client-group" style="display: none;">
                            <label for="client_id"><?= __('client') ?></label>
                            <select class="form-control" id="client_id" name="client_id">
                                <option value=""><?= __('select_client') ?></option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= $client['id'] ?>">
                                        <?= htmlspecialchars($client['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="button" class="btn btn-primary" id="savePayment"><?= __('save_payment') ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Payment Modal -->
    <div class="modal fade" id="editPaymentModal" tabindex="-1" role="dialog" aria-labelledby="editPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPaymentModalLabel"><?= __('edit_payment') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editPaymentForm" method="POST" action="additional_payments.php">
                        <!-- CSRF Protection -->
                        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                        <input type="hidden" id="edit_id" name="id">
                        <input type="hidden" name="action" value="edit">
                        
                        <!-- Form fields -->
                        <div class="form-group">
                            <label for="edit_payment_type"><?= __('payment_type') ?></label>
                            <input type="text" class="form-control" id="edit_payment_type" name="payment_type" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_main_account_id"><?= __('main_account') ?></label>
                            <select class="form-control" id="edit_main_account_id" name="main_account_id" required>
                                <option value=""><?= __('select_main_account') ?></option>
                                <?php foreach ($mainAccounts as $account): ?>
                                    <option value="<?= $account['id'] ?>">
                                        <?= htmlspecialchars($account['name']) ?> 
                                       
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_description"><?= __('description') ?></label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="edit_base_amount"><?= __('base_amount') ?></label>
                            <input type="number" class="form-control" id="edit_base_amount" name="base_amount" required step="0.01" onchange="calculateEditProfit()">
                        </div>
                        <div class="form-group">
                            <label for="edit_sold_amount"><?= __('sold_amount') ?></label>
                            <input type="number" class="form-control" id="edit_sold_amount" name="sold_amount" required step="0.01" onchange="calculateEditProfit()">
                        </div>
                        <div class="form-group">
                            <label for="edit_profit"><?= __('profit') ?></label>
                            <input type="number" class="form-control" id="edit_profit" name="profit" required step="0.01" readonly>
                        </div>
                        <div class="form-group">
                            <label for="edit_currency"><?= __('currency') ?></label>
                            <select class="form-control" id="edit_currency" name="currency" required>
                                <option value="USD"><?= __('usd') ?></option>
                                <option value="AFS"><?= __('afs') ?></option>
                                <option value="EUR"><?= __('eur') ?></option>
                                <option value="DARHAM"><?= __('darham') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="edit_is_from_supplier" name="is_from_supplier">
                                <label class="custom-control-label" for="edit_is_from_supplier"><?= __('bought_from_supplier') ?></label>
                            </div>
                        </div>
                        <div class="form-group supplier-group" style="display: none;">
                            <label for="edit_supplier_id"><?= __('supplier') ?></label>
                            <select class="form-control" id="edit_supplier_id" name="supplier_id">
                                <option value=""><?= __('select_supplier') ?></option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier['id'] ?>">
                                        <?= htmlspecialchars($supplier['name']) ?> 
                                      
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="edit_is_for_client" name="is_for_client">
                                <label class="custom-control-label" for="edit_is_for_client"><?= __('sold_to_client') ?></label>
                            </div>
                        </div>
                        <div class="form-group client-group" style="display: none;">
                            <label for="edit_client_id"><?= __('client') ?></label>
                            <select class="form-control" id="edit_client_id" name="client_id">
                                <option value=""><?= __('select_client') ?></option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= $client['id'] ?>">
                                        <?= htmlspecialchars($client['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="button" class="btn btn-primary" id="updatePayment"><?= __('update_payment') ?></button>
                </div>
            </div>
        </div>
    </div>

<!-- Transaction Modal -->
<div class="modal fade" id="addTransactionModal" tabindex="-1" role="dialog" aria-labelledby="addTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addTransactionModalLabel">
                    <i class="feather icon-credit-card mr-2"></i><?= __('manage_transactions') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Payment Info Summary Card -->
                <div class="card mb-4 border-primary">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2"><?= __('payment_details') ?></h6>
                                <p class="mb-1"><strong><?= __('payment_type') ?>:</strong> <span id="trans-payment-type"></span></p>
                                <p class="mb-1"><strong><?= __('description') ?>:</strong> <span id="trans-description"></span></p>
                                <p class="mb-1"><strong><?= __('account') ?>:</strong> <span id="trans-account"></span></p>
                            </div>
                            <div class="col-md-6">
                                                                    <div class="alert alert-info mb-0">
                                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                                            <span><?= __('total_amount') ?>:</span>
                                                                            <strong id="totalAmount"></strong>
                                                                        </div>
                                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                                            <span>Exchange Rate:</span>
                                                                            <strong id="exchangeRateDisplay"></strong>
                                                                        </div>
                                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                                            <span>Exchanged Amount:</span>
                                                                            <strong id="exchangedAmount"></strong>
                                                                        </div>
                                                                        <div id="usdSection" style="display: none;">
                                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                <span>Paid Amount USD:</span>
                                                                                <strong id="paidAmountUSD" class="text-success">USD 0.00</strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                <span>Remaining Amount USD:</span>
                                                                                <strong id="remainingAmountUSD" class="text-danger">USD 0.00</strong>
                                                                            </div>
                                                                        </div>
                                                                        <div id="afsSection" style="display: none;">
                                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                <span>Paid Amount AFS:</span>
                                                                                <strong id="paidAmountAFS" class="text-success">AFS 0.00</strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                <span>Remaining Amount AFS:</span>
                                                                                <strong id="remainingAmountAFS" class="text-danger">AFS 0.00</strong>
                                                                            </div>
                                                                        </div>
                                                                        <div id="eurSection" style="display: none;">
                                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                <span>Paid Amount EUR:</span>
                                                                                <strong id="paidAmountEUR" class="text-success">EUR 0.00</strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                <span>Remaining Amount EUR:</span>
                                                                                <strong id="remainingAmountEUR" class="text-danger">EUR 0.00</strong>
                                                                            </div>
                                                                        </div>
                                                                        <div id="aedSection" style="display: none;">
                                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                <span>Paid Amount AED:</span>
                                                                                <strong id="paidAmountAED" class="text-success">AED 0.00</strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                <span>Remaining Amount AED:</span>
                                                                                <strong id="remainingAmountAED" class="text-danger">AED 0.00</strong>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                        </div>
                    </div>
                </div>

                <!-- Add Transaction Form Card -->
                <div class="card mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= __('add_new_transaction') ?></h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="collapse" data-target="#transactionFormContainer">
                            <i class="feather icon-plus"></i> <?= __('show_hide_form') ?>
                        </button>
                    </div>
                    <div id="transactionFormContainer" class="collapse show">
                        <div class="card-body">
                            <form id="transactionForm">
                                <input type="hidden" id="transaction_payment_id" name="payment_id">
                                <input type="hidden" id="transaction_payment_type" name="payment_type">
                                <input type="hidden" id="original_payment_currency" name="original_payment_currency">
                                <input type="hidden" id="transaction_main_account_id" name="main_account_id">
                                <input type="hidden" id="transaction_id" name="transaction_id">
                    
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="payment_date">
                                                <i class="feather icon-calendar mr-1"></i><?= __('payment_date') ?>
                                            </label>
                                            <input type="date" class="form-control" id="payment_date" name="payment_date" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="payment_time">
                                                <i class="feather icon-clock mr-1"></i><?= __('time') ?>
                                            </label>
                                            <input type="time" class="form-control" id="payment_time" name="payment_time" step="1" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="payment_amount">
                                                <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                                            </label>
                                            <input type="number" class="form-control" id="payment_amount" name="payment_amount" required step="0.01">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="transaction_currency">
                                                <i class="feather icon-globe mr-1"></i><?= __('currency') ?>
                                            </label>
                                            <select class="form-control" id="transaction_currency" name="currency" required>
                                                <option value=""><?= __('select_currency') ?></option>
                                                <option value="USD"><?= __('usd') ?></option>
                                                <option value="AFS"><?= __('afs') ?></option>
                                                <option value="EUR"><?= __('eur') ?></option>
                                                <option value="DARHAM"><?= __('darham') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group" id="exchange_rate_group" style="display: none;">
                                    <label for="exchange_rate">
                                            <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                                    </label>
                                    <input type="number" class="form-control" id="exchange_rate" name="exchange_rate" step="0.0001" min="0.0001">
                                    <small class="form-text text-muted"><?= __('enter_the_exchange_rate_from_transaction_currency_to_payment_currency') ?></small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="payment_description">
                                        <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                    </label>
                                    <textarea class="form-control" id="payment_description" name="payment_description" rows="3"></textarea>
                                </div>
                                
                                <div class="text-right mt-3">
                                    <button type="button" class="btn btn-primary" id="AddTransaction">
                                        <i class="feather icon-check mr-1"></i><?= __('add_transaction') ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Existing Transactions Table Card -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><?= __('transaction_history') ?></h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="transactionsTable">
                                    <thead class="thead-light">
                                    <tr>
                                        <th><?= __('date') ?></th>
                                        <th><?= __('description') ?></th>
                                        <th><?= __('type') ?></th>
                                        <th><?= __('amount') ?></th>
                                        <th><?= __('exchange_rate') ?></th>
                                        <th class="text-center"><?= __('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="transactionsTableBody">
                                    <!-- Transactions will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('close') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Transaction Modal -->
<div class="modal fade" id="editTransactionModal" tabindex="-1" role="dialog" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editTransactionModalLabel">
                    <i class="feather icon-edit mr-2"></i><?= __('edit_transaction') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editTransactionForm">
                    <input type="hidden" id="edit_transaction_id" name="transaction_id">
                    <input type="hidden" id="edit_transaction_payment_id" name="payment_id">
                    <input type="hidden" id="edit_original_payment_currency" name="original_payment_currency">
                    
                    <div class="row">
                        <div class="col-md-6">
                    <div class="form-group">
                                <label for="edit_payment_date">
                                    <i class="feather icon-calendar mr-1"></i><?= __('date') ?>
                                </label>
                                <input type="date" class="form-control" id="edit_payment_date" name="payment_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_payment_time">
                                    <i class="feather icon-clock mr-1"></i><?= __('time') ?>
                                </label>
                                <input type="time" class="form-control" id="edit_payment_time" name="payment_time" step="1" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_payment_amount">
                                    <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                                </label>
                        <input type="number" class="form-control" id="edit_payment_amount" name="payment_amount" required step="0.01">
                    </div>
                        </div>
                        <div class="col-md-6">
                    <div class="form-group">
                                <label for="edit_transaction_currency">
                                    <i class="feather icon-globe mr-1"></i><?= __('currency') ?>
                                </label>
                        <select class="form-control" id="edit_transaction_currency" name="currency" required>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                            <option value="EUR"><?= __('eur') ?></option>
                            <option value="DARHAM"><?= __('darham') ?></option>
                        </select>
                    </div>
                        </div>
                    </div>
                    
                    <div class="form-group" id="edit_exchange_rate_group" style="display: none;">
                        <label for="edit_exchange_rate">
                            <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                        </label>
                        <input type="number" class="form-control" id="edit_exchange_rate" name="exchange_rate" step="0.0001" min="0.0001">
                        <small class="form-text text-muted"><?= __('enter_the_exchange_rate_from_transaction_currency_to_payment_currency') ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_payment_description">
                            <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                        </label>
                        <textarea class="form-control" id="edit_payment_description" name="payment_description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_receipt">
                            <i class="feather icon-file mr-1"></i><?= __('receipt_number') ?>
                        </label>
                        <input type="text" class="form-control" id="edit_receipt" name="receipt">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('close') ?>
                </button>
                <button type="button" class="btn btn-primary" id="updateTransaction">
                    <i class="feather icon-save mr-1"></i><?= __('update_transaction') ?>
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
            // Transaction Manager for Additional Payments
            const transactionManager = {
                formatDate: function(dateString) {
                    const date = new Date(dateString);
                    return date.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                },
    
                loadTransactionHistory: function(paymentId) {
                    const soldAmount = parseFloat($('#totalAmount').text().split(' ')[1]);
    
                    $.ajax({
                        url: 'get_transactions.php',
                        type: 'GET',
                        data: { payment_id: paymentId },
                        dataType: 'json',
                        success: function(response) {
                            try {
                                const transactions = response;
    
                                // Check if transactions is an array
                                if (!Array.isArray(transactions)) {
                                    console.error('Invalid transactions response:', transactions);
                                    $('#transactionsTableBody').html('<tr><td colspan="6" class="text-center">Error loading transactions</td></tr>');
                                    return;
                                }
    
                                const tbody = $('#transactionsTableBody');
                                tbody.empty();
    
                                let hasUSDTransactions = false;
                                let hasAFSTransactions = false;
                                let hasEURTransactions = false;
                                let hasDARHAMTransactions = false;
    
                                // Store exchange rates for calculations (will be updated from transactions)
                                let usdToAfsRate = 70; // Default AFS rate from user's data
                                let usdToEurRate = 0.9; // Default EUR rate from user's data
                                let usdToDarhamRate = 3.61; // Default DARHAM rate from user's data
    
                                // Collect exchange rates from transactions for display
                                let exchangeRatesDisplay = [];
    
                                transactions.forEach(transaction => {
                                    // Check which currencies have transactions
                                    switch (transaction.currency) {
                                        case 'USD':
                                            hasUSDTransactions = true;
                                            break;
                                        case 'AFS':
                                            hasAFSTransactions = true;
                                            break;
                                        case 'EUR':
                                            hasEURTransactions = true;
                                            break;
                                        case 'DARHAM':
                                            hasDARHAMTransactions = true;
                                            break;
                                    }

                                    // Ensure description is a string
                                    const description = String(transaction.description || '');

                                    // Use exchange_rate field directly from transaction
                                    let transactionExchangeRate = transaction.exchange_rate ? parseFloat(transaction.exchange_rate) : null;
                                    let exchangeRateDisplay = transactionExchangeRate ? transactionExchangeRate.toString() : 'N/A';

                                    if (transactionExchangeRate) {
                                        // Update exchange rates for calculations if this transaction has a rate
                                        if (transaction.currency === 'AFS') {
                                            usdToAfsRate = transactionExchangeRate;
                                            console.log('Updated AFS rate:', usdToAfsRate);
                                            // Add to display list if not already present
                                            if (!exchangeRatesDisplay.find(rate => rate.currency === 'AFS' && rate.value === transactionExchangeRate)) {
                                                exchangeRatesDisplay.push({ currency: 'AFS', value: transactionExchangeRate });
                                            }
                                        } else if (transaction.currency === 'EUR') {
                                            usdToEurRate = transactionExchangeRate;
                                            console.log('Updated EUR rate:', usdToEurRate);
                                            // Add to display list if not already present
                                            if (!exchangeRatesDisplay.find(rate => rate.currency === 'EUR' && rate.value === transactionExchangeRate)) {
                                                exchangeRatesDisplay.push({ currency: 'EUR', value: transactionExchangeRate });
                                            }
                                        } else if (transaction.currency === 'DARHAM') {
                                            usdToDarhamRate = transactionExchangeRate;
                                            console.log('Updated DARHAM rate:', usdToDarhamRate);
                                            // Add to display list if not already present
                                            if (!exchangeRatesDisplay.find(rate => rate.currency === 'DARHAM' && rate.value === transactionExchangeRate)) {
                                                exchangeRatesDisplay.push({ currency: 'DARHAM', value: transactionExchangeRate });
                                            }
                                        } else if (transaction.currency === 'USD') {
                                            usdToUsdRate = transactionExchangeRate;
                                            console.log('Updated USD rate:', usdToUsdRate);
                                            // Add to display list if not already present
                                            if (!exchangeRatesDisplay.find(rate => rate.currency === 'USD' && rate.value === transactionExchangeRate)) {
                                                exchangeRatesDisplay.push({ currency: 'USD', value: transactionExchangeRate });
                                            }
                                        }

                                    }
    
                                    const row = `
                                        <tr>
                                            <td>${transactionManager.formatDate(transaction.created_at)}</td>
                                            <td>${description}</td>
                                            <td>${transaction.type === 'credit' ? 'Received' : 'Paid'}</td>
                                            <td>${parseFloat(transaction.amount).toFixed(2)} ${transaction.currency}</td>
                                            <td>${exchangeRateDisplay}</td>
                                            <td class="text-center">
                                                <button class="btn btn-primary btn-sm mr-1" title="Edit Transaction"
                                                        onclick="transactionManager.editTransaction(${transaction.id}, '${description.replace(/'/g, "\\'")}', ${transaction.amount}, '${transaction.created_at}', '${transaction.currency}', ${transaction.exchange_rate || 'null'})">
                                                    <i class="feather icon-edit"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" title="Delete Transaction"
                                                        onclick="transactionManager.deleteTransaction(${transaction.id}, ${transaction.amount})">
                                                    <i class="feather icon-trash-2"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    `;
                                    tbody.append(row);
                                });
    
                                // Update exchange rate display in info card
                                if (exchangeRatesDisplay.length > 0) {
                                    const displayText = exchangeRatesDisplay
                                        .map(rate => `${rate.currency}: ${rate.value}`)
                                        .join(', ');
                                    $('#exchangeRateDisplay').text(displayText);
                                } else {
                                    $('#exchangeRateDisplay').text('No exchange rates found');
                                }
    
                                // Get the total amount and its currency
                                const totalAmountText = $('#totalAmount').text();
                                const totalCurrency = totalAmountText.split(' ')[0];
                                const totalAmount = parseFloat(totalAmountText.split(' ')[1]) || 0;
    
                                // Calculate and display exchanged amounts for each currency
                                let exchangedAmounts = [];
                                let exchangedAmountMap = {}; // Store exchanged amounts for later use

                                exchangeRatesDisplay.forEach(rate => {
                                    let convertedAmount = totalAmount;
                                    if (totalCurrency === 'USD') {
                                        if (rate.currency === 'AFS') {
                                            convertedAmount = totalAmount * rate.value;
                                        } else if (rate.currency === 'EUR') {
                                            convertedAmount = totalAmount * rate.value;
                                        } else if (rate.currency === 'DARHAM') {
                                            convertedAmount = totalAmount * rate.value;
                                        }
                                    } else if (totalCurrency === 'AFS') {
                                        if (rate.currency === 'USD') {
                                            convertedAmount = totalAmount / rate.value;
                                        } else if (rate.currency === 'EUR') {
                                            convertedAmount = (totalAmount / usdToAfsRate) * rate.value;
                                        } else if (rate.currency === 'DARHAM') {
                                            convertedAmount = (totalAmount / usdToAfsRate) * rate.value;
                                        } else if (rate.currency === 'USD') {
                                            convertedAmount = (totalAmount / usdToAfsRate) * rate.value;
                                        }
                                    } else if (totalCurrency === 'EUR') {
                                        if (rate.currency === 'USD') {
                                            convertedAmount = totalAmount / rate.value;
                                        } else if (rate.currency === 'AFS') {
                                            convertedAmount = (totalAmount / usdToEurRate) * rate.value;
                                        } else if (rate.currency === 'DARHAM') {
                                            convertedAmount = (totalAmount / usdToEurRate) * rate.value;
                                        }
                                    } else if (totalCurrency === 'DARHAM') {
                                        if (rate.currency === 'USD') {
                                            convertedAmount = totalAmount / rate.value;
                                        } else if (rate.currency === 'AFS') {
                                            convertedAmount = (totalAmount / usdToDarhamRate) * rate.value;
                                        } else if (rate.currency === 'EUR') {
                                            convertedAmount = (totalAmount / usdToDarhamRate) * rate.value;
                                        }
                                    }
                                    exchangedAmounts.push(`${rate.currency} ${convertedAmount.toFixed(2)}`);
                                    exchangedAmountMap[rate.currency] = convertedAmount;
                                });

                                if (exchangedAmounts.length > 0) {
                                    $('#exchangedAmount').text(exchangedAmounts.join(', '));
                                } else {
                                    $('#exchangedAmount').text('No conversions available');
                                }
    
                                // Exchange rates are now calculated from transaction data above
                                // usdToAfsRate, usdToEurRate, usdToDarhamRate are set based on actual transaction exchange rates
    
                                // Show/hide currency sections based on transaction existence
                                $('#usdSection').toggle(hasUSDTransactions);
                                $('#afsSection').toggle(hasAFSTransactions);
                                $('#eurSection').toggle(hasEURTransactions);
                                $('#aedSection').toggle(hasDARHAMTransactions);
    
                                // Calculate totals and remaining amounts using transaction-specific exchange rates
                                let totalPaidInBaseCurrency = 0;
                                console.log('Total currency:', totalCurrency, 'Total amount:', totalAmount);
                                console.log('Exchange rates - AFS:', usdToAfsRate, 'EUR:', usdToEurRate, 'DARHAM:', usdToDarhamRate);

                                // Sum up all payments converted to the payment's base currency
                                transactions.forEach(transaction => {
                                    const amount = parseFloat(transaction.amount);
                                    let transactionExchangeRate = transaction.exchange_rate ? parseFloat(transaction.exchange_rate) : null;
                                    console.log('Processing transaction:', transaction.currency, amount, 'rate:', transactionExchangeRate);

                                    // Convert transaction amount to base currency
                                    let convertedAmount = amount;
                                    if (transaction.currency !== totalCurrency) {
                                        // Use transaction-specific exchange rate if available, otherwise use default rates
                                        let exchangeRateToUse = transactionExchangeRate;

                                        if (!exchangeRateToUse) {
                                            // Use default exchange rates when transaction doesn't have a rate
                                            if (totalCurrency === 'USD') {
                                                if (transaction.currency === 'AFS') exchangeRateToUse = usdToAfsRate;
                                                else if (transaction.currency === 'EUR') exchangeRateToUse = usdToEurRate;
                                                else if (transaction.currency === 'DARHAM') exchangeRateToUse = usdToDarhamRate;
                                            } else if (totalCurrency === 'AFS') {
                                                if (transaction.currency === 'USD') exchangeRateToUse = 1 / usdToAfsRate;
                                                else if (transaction.currency === 'EUR') exchangeRateToUse = usdToEurRate / usdToAfsRate;
                                                else if (transaction.currency === 'DARHAM') exchangeRateToUse = usdToDarhamRate / usdToAfsRate;
                                            } else if (totalCurrency === 'EUR') {
                                                if (transaction.currency === 'USD') exchangeRateToUse = 1 / usdToEurRate;
                                                else if (transaction.currency === 'AFS') exchangeRateToUse = usdToAfsRate / usdToEurRate;
                                                else if (transaction.currency === 'DARHAM') exchangeRateToUse = usdToDarhamRate / usdToEurRate;
                                            }
                                        }

                                        if (exchangeRateToUse) {
                                            if (totalCurrency === 'USD') {
                                                if (transaction.currency === 'AFS') {
                                                    convertedAmount = amount / exchangeRateToUse;
                                                    console.log('USD base - AFS conversion:', amount, '/', exchangeRateToUse, '=', convertedAmount);
                                                } else if (transaction.currency === 'EUR') {
                                                    convertedAmount = amount / exchangeRateToUse;
                                                    console.log('USD base - EUR conversion:', amount, '/', exchangeRateToUse, '=', convertedAmount);
                                                } else if (transaction.currency === 'DARHAM') {
                                                    convertedAmount = amount / exchangeRateToUse;
                                                    console.log('USD base - DARHAM conversion:', amount, '/', exchangeRateToUse, '=', convertedAmount);
                                                }
                                            } else if (totalCurrency === 'AFS') {
                                                if (transaction.currency === 'USD') {
                                                    convertedAmount = amount * exchangeRateToUse;
                                                    console.log('AFS base - USD conversion:', amount, '*', exchangeRateToUse, '=', convertedAmount);
                                                } else if (transaction.currency === 'EUR') {
                                                    convertedAmount = amount * exchangeRateToUse;
                                                    console.log('AFS base - EUR conversion:', amount, '*', exchangeRateToUse, '=', convertedAmount);
                                                } else if (transaction.currency === 'DARHAM') {
                                                    convertedAmount = amount * exchangeRateToUse;
                                                    console.log('AFS base - DARHAM conversion:', amount, '*', exchangeRateToUse, '=', convertedAmount);
                                                }
                                            } else if (totalCurrency === 'EUR') {
                                                if (transaction.currency === 'USD') {
                                                    convertedAmount = amount * exchangeRateToUse;
                                                    console.log('EUR base - USD conversion:', amount, '*', exchangeRateToUse, '=', convertedAmount);
                                                } else if (transaction.currency === 'AFS') {
                                                    convertedAmount = amount * exchangeRateToUse;
                                                    console.log('EUR base - AFS conversion:', amount, '*', exchangeRateToUse, '=', convertedAmount);
                                                } else if (transaction.currency === 'DARHAM') {
                                                    convertedAmount = amount * exchangeRateToUse;
                                                    console.log('EUR base - DARHAM conversion:', amount, '*', exchangeRateToUse, '=', convertedAmount);
                                                }
                                            }
                                        } else {
                                            console.log('No exchange rate available for', transaction.currency, '->', totalCurrency);
                                        }
                                    } else {
                                        console.log('No conversion needed for', transaction.currency, 'amount:', amount);
                                    }

                                    totalPaidInBaseCurrency += convertedAmount;
                                    console.log('Running total paid in base currency:', totalPaidInBaseCurrency);
                                });

                                const remainingAmount = Math.max(0, totalAmount - totalPaidInBaseCurrency);
                                console.log('Final calculation - Total:', totalAmount, 'Paid:', totalPaidInBaseCurrency, 'Remaining:', remainingAmount);
    
                                // Display amounts in each currency section
                                if (hasUSDTransactions) {
                                    const usdPaid = transactions
                                        .filter(t => t.currency === 'USD')
                                        .reduce((sum, t) => sum + parseFloat(t.amount), 0);
                                    $('#paidAmountUSD').text(`USD ${usdPaid.toFixed(2)}`);

                                    let usdRemaining = remainingAmount;
                                    if (totalCurrency === 'AFS') {
                                        usdRemaining = remainingAmount / usdToAfsRate;
                                    } else if (totalCurrency === 'EUR') {
                                        usdRemaining = remainingAmount / usdToEurRate;
                                    } else if (totalCurrency === 'DARHAM') {
                                        usdRemaining = remainingAmount / usdToDarhamRate;
                                    }
                                    $('#remainingAmountUSD').text(`USD ${usdRemaining.toFixed(2)}`);
                                }

                                if (hasAFSTransactions) {
                                    const afsPaid = transactions
                                        .filter(t => t.currency === 'AFS')
                                        .reduce((sum, t) => sum + parseFloat(t.amount), 0);
                                    $('#paidAmountAFS').text(`AFS ${afsPaid.toFixed(2)}`);

                                    let afsRemaining = remainingAmount;
                                    if (totalCurrency === 'USD') {
                                        afsRemaining = remainingAmount * usdToAfsRate;
                                    } else if (totalCurrency === 'EUR') {
                                        afsRemaining = (remainingAmount / usdToEurRate) * usdToAfsRate;
                                    } else if (totalCurrency === 'DARHAM') {
                                        afsRemaining = (remainingAmount / usdToDarhamRate) * usdToAfsRate;
                                    }
                                    $('#remainingAmountAFS').text(`AFS ${afsRemaining.toFixed(2)}`);
                                }

                                if (hasEURTransactions) {
                                    const eurPaid = transactions
                                        .filter(t => t.currency === 'EUR')
                                        .reduce((sum, t) => sum + parseFloat(t.amount), 0);
                                    $('#paidAmountEUR').text(`EUR ${eurPaid.toFixed(2)}`);

                                    let eurRemaining = remainingAmount;
                                    if (totalCurrency === 'USD') {
                                        eurRemaining = remainingAmount * usdToEurRate;
                                    } else if (totalCurrency === 'AFS') {
                                        eurRemaining = (remainingAmount / usdToAfsRate) * usdToEurRate;
                                    } else if (totalCurrency === 'DARHAM') {
                                        eurRemaining = (remainingAmount / usdToDarhamRate) * usdToEurRate;
                                    }
                                    $('#remainingAmountEUR').text(`EUR ${eurRemaining.toFixed(2)}`);
                                }

                                if (hasDARHAMTransactions) {
                                    const darhamPaid = transactions
                                        .filter(t => t.currency === 'DARHAM')
                                        .reduce((sum, t) => sum + parseFloat(t.amount), 0);
                                    $('#paidAmountAED').text(`AED ${darhamPaid.toFixed(2)}`);

                                    let darhamRemaining = remainingAmount;
                                    if (totalCurrency === 'USD') {
                                        darhamRemaining = remainingAmount * usdToDarhamRate;
                                    } else if (totalCurrency === 'AFS') {
                                        darhamRemaining = (remainingAmount / usdToAfsRate) * usdToDarhamRate;
                                    } else if (totalCurrency === 'EUR') {
                                        darhamRemaining = (remainingAmount / usdToEurRate) * usdToDarhamRate;
                                    }
                                    $('#remainingAmountAED').text(`AED ${darhamRemaining.toFixed(2)}`);
                                }
    
                            } catch (e) {
                                console.error('Error parsing transactions:', e);
                                $('#transactionsTableBody').html(
                                    '<tr><td colspan="6" class="text-center">error_loading_transactions</td></tr>'
                                );
                                $('#exchangeRateDisplay').text('Error loading exchange rates');
                                $('#exchangedAmount').text('Error calculating amounts');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error loading transactions:', error);
                            $('#transactionsTableBody').html(
                                '<tr><td colspan="6" class="text-center">error_loading_transactions</td></tr>'
                            );
                            $('#exchangeRateDisplay').text('Error loading exchange rates');
                            $('#exchangedAmount').text('Error calculating amounts');
                        }
                    });
                },
    
                editTransaction: function(id, description, amount, created_at, currency, exchange_rate) {
                    // Populate edit modal with transaction data
                    $('#edit_transaction_id').val(id);
                    $('#edit_payment_amount').val(amount);
                    $('#edit_transaction_currency').val(currency);
                    $('#edit_payment_description').val(description);
                    $('#edit_receipt').val(''); // You may need to fetch this separately
    
                    // Parse and set date/time
                    const txDate = new Date(created_at);
                    const formattedDate = txDate.toISOString().split('T')[0];
                    const hours = String(txDate.getHours()).padStart(2, '0');
                    const minutes = String(txDate.getMinutes()).padStart(2, '0');
                    const seconds = String(txDate.getSeconds()).padStart(2, '0');
                    const formattedTime = `${hours}:${minutes}:${seconds}`;
    
                    $('#edit_payment_date').val(formattedDate);
                    $('#edit_payment_time').val(formattedTime);
    
                    // Handle exchange rate - use the direct field from database
                    if (exchange_rate && exchange_rate !== 'null') {
                        $('#edit_exchange_rate').val(exchange_rate);
                        $('#edit_exchange_rate_group').show();
                    } else {
                        $('#edit_exchange_rate').val('');
                        $('#edit_exchange_rate_group').hide();
                    }
    
                    // Show modal
                    $('#editTransactionModal').modal('show');
                },
    
                deleteTransaction: function(id, amount) {
                    if (confirm('Are you sure you want to delete this transaction?')) {
                        const paymentId = $('#transaction_payment_id').val();
                        $.ajax({
                            url: 'delete_additional_payment_transaction.php',
                            type: 'POST',
                            data: {
                                transaction_id: id,
                                payment_id: paymentId
                            },
                            success: function(response) {
                                try {
                                    const result = typeof response === 'object' ? response : JSON.parse(response);
                                    if (result.success) {
                                        alert('Transaction deleted successfully');
                                        // Reload transactions
                                        transactionManager.loadTransactionHistory(paymentId);
                                    } else {
                                        alert('Error: ' + (result.message || 'Unknown error occurred'));
                                    }
                                } catch (e) {
                                    console.error('Error parsing response:', e);
                                    alert('Error: Invalid response from server');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Error deleting transaction:', error);
                                alert('Error deleting transaction');
                            }
                        });
                    }
                }
            };
    
            $(document).ready(function() {
                $('#supplier_id').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownParent: $('#supplier_id').closest('.modal-body'),
                    placeholder: '<?= __("select_supplier") ?>',
                    allowClear: true
                });
                $('#client_id').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    dropdownParent: $('#client_id').closest('.modal-body'),
                    placeholder: '<?= __("select_client") ?>',
                    allowClear: true
                });
            });
        </script>
<script>
        $(document).ready(function() {
            // Set today's date as default for payment date
            $('#payment_date').val(new Date().toISOString().split('T')[0]);
            
            // Prevent default form submission for edit form
            $('#editPaymentForm').submit(function(e) {
                e.preventDefault();
                // The form will be submitted via AJAX by the updatePayment click handler
            });
            
            // Handle supplier checkbox in add form
            $('#is_from_supplier').change(function() {
                if($(this).is(':checked')) {
                    $('.supplier-group').show();
                    $('#supplier_id').prop('required', true);
                } else {
                    $('.supplier-group').hide();
                    $('#supplier_id').prop('required', false);
                }
            });

            // Handle client checkbox in add form
            $('#is_for_client').change(function() {
                if($(this).is(':checked')) {
                    $('.client-group').show();
                    $('#client_id').prop('required', true);
                } else {
                    $('.client-group').hide();
                    $('#client_id').prop('required', false);
                }
            });

            // Handle supplier checkbox in edit form
            $('#edit_is_from_supplier').change(function() {
                if($(this).is(':checked')) {
                    $('.supplier-group').show();
                    $('#edit_supplier_id').prop('required', true);
                } else {
                    $('.supplier-group').hide();
                    $('#edit_supplier_id').prop('required', false);
                }
            });

            // Handle client checkbox in edit form
            $('#edit_is_for_client').change(function() {
                if($(this).is(':checked')) {
                    $('.client-group').show();
                    $('#edit_client_id').prop('required', true);
                } else {
                    $('.client-group').hide();
                    $('#edit_client_id').prop('required', false);
                }
            });

            // When editing payment, check if it has supplier and client
            $('.edit-payment').click(function() {
                const id = $(this).data('id');
                const paymentType = $(this).data('payment-type');
                const description = $(this).data('description');
                const baseAmount = $(this).data('base-amount');
                const profit = $(this).data('profit');
                const soldAmount = $(this).data('sold-amount');
                const currency = $(this).data('currency');
                const mainAccount = $(this).data('main-account');
                const supplier = $(this).data('supplier');
                const client = $(this).data('client');
                const receipt = $(this).data('receipt');

                // Set form values
                $('#edit_id').val(id);
                $('#edit_payment_type').val(paymentType);
                $('#edit_description').val(description);
                $('#edit_base_amount').val(baseAmount);
                $('#edit_sold_amount').val(soldAmount);
                $('#edit_profit').val(profit);
                $('#edit_currency').val(currency);
                $('#edit_main_account_id').val(mainAccount);
                $('#edit_supplier_id').val(supplier);
                $('#edit_client_id').val(client);

                // Handle supplier checkbox and fields
                if (supplier) {
                    $('#edit_is_from_supplier').prop('checked', true);
                    $('.supplier-group').show();
                    $('#edit_supplier_id').prop('required', true);
                } else {
                    $('#edit_is_from_supplier').prop('checked', false);
                    $('.supplier-group').hide();
                    $('#edit_supplier_id').prop('required', false);
                }

                // Handle client checkbox and fields
                if (client) {
                    $('#edit_is_for_client').prop('checked', true);
                    $('.client-group').show();
                    $('#edit_client_id').prop('required', true);
                } else {
                    $('#edit_is_for_client').prop('checked', false);
                    $('.client-group').hide();
                    $('#edit_client_id').prop('required', false);
                }

                // Store the original base amount for comparison
                $('#updatePayment').data('original-base-amount', baseAmount);

                $('#editPaymentModal').modal('show');
            });

            // Save Payment button click handler - SINGLE HANDLER
            $('#savePayment').off('click').on('click', function(e) {
                e.preventDefault(); // Prevent any default behavior
                var form = $('#addPaymentForm');
                var formData = new FormData(form[0]);
                
                // Add checkbox values
                formData.append('is_from_supplier', $('#is_from_supplier').is(':checked') ? 1 : 0);
                formData.append('is_for_client', $('#is_for_client').is(':checked') ? 1 : 0);
                
                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        try {
                            var result = typeof response === 'object' ? response : JSON.parse(response);
                            if (result.success) {
                                $('#addPaymentModal').modal('hide');
                                location.reload();
                            } else {
                                alert('<?= __('error') ?>: ' + (result.message || '<?= __('unknown_error_occurred') ?>'));
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            alert('<?= __('error') ?>: <?= __('invalid_response_from_server') ?>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error saving payment:', error);
                        try {
                            var errorResponse = JSON.parse(xhr.responseText);
                            alert('<?= __('error') ?>: ' + (errorResponse.message || '<?= __('failed_to_save_payment') ?>'));
                        } catch (e) {
                            alert('<?= __('error') ?>: <?= __('failed_to_save_payment') ?>. <?= __('please_try_again') ?>');
                        }
                    }
                });
            });

            // Update Payment button click handler
            $('#updatePayment').click(function(e) {
                e.preventDefault();
                
                // Disable the button to prevent multiple submissions
                $('#updatePayment').prop('disabled', true);
                
                // Collect form data
                var formData = {
                    id: $('#edit_id').val(),
                    action: 'edit',
                    payment_type: $('#edit_payment_type').val(),
                    description: $('#edit_description').val(),
                    base_amount: $('#edit_base_amount').val(),
                    profit: $('#edit_profit').val(),
                    sold_amount: $('#edit_sold_amount').val(),
                    currency: $('#edit_currency').val(),
                    main_account_id: $('#edit_main_account_id').val(),
                    is_from_supplier: $('#edit_is_from_supplier').is(':checked') ? 1 : 0,
                    supplier_id: $('#edit_is_from_supplier').is(':checked') ? $('#edit_supplier_id').val() : '',
                    is_for_client: $('#edit_is_for_client').is(':checked') ? 1 : 0,
                    client_id: $('#edit_is_for_client').is(':checked') ? $('#edit_client_id').val() : '',
                    csrf_token: $('input[name="csrf_token"]').val()
                };
                
                // Debug: Log the form data being sent
                console.log('Form data being sent:', formData);
                
                // Use the current page URL
                var ajaxUrl = 'additional_payments.php';
                console.log('AJAX URL:', ajaxUrl);
                
                // Submit via AJAX
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: formData,
                    beforeSend: function(xhr) {
                        console.log('Before send - Request URL:', this.url);
                    },
                    success: function(response) {
                        console.log('Raw AJAX response:', response);
                        
                        try {
                            var result = typeof response === 'object' ? response : JSON.parse(response);
                            console.log('Parsed response:', result);
                            
                            if (result.success) {
                                console.log('Update successful, closing modal and reloading page');
                                // Show success message
                                alert('<?= __('payment_updated_successfully') ?>');
                                // Close modal and reload page
                                $('#editPaymentModal').modal('hide');
                                location.reload();
                            } else {
                                // Reset button state
                                $('#updatePayment').prop('disabled', false);
                                console.error('Update failed:', result.message);
                                alert('<?= __('error') ?>: ' + (result.message || '<?= __('unknown_error_occurred') ?>'));
                            }
                        } catch (e) {
                            // Reset button state
                            $('#updatePayment').prop('disabled', false);
                            console.error('Error parsing response:', e);
                            console.error('Raw response was:', response);
                            alert('<?= __('error') ?>: <?= __('invalid_response_from_server') ?>');
                        }
                    },
                    error: function(xhr, status, error) {
                        // Reset button state
                        $('#updatePayment').prop('disabled', false);
                        console.error('AJAX error:', {xhr: xhr, status: status, error: error});
                        console.error('Response status:', xhr.status);
                        console.error('Response text:', xhr.responseText);
                        console.error('Request URL:', this.url);
                        
                        // If we get a 404 error, try direct form submission as a fallback
                        if (xhr.status === 404) {
                            console.log('404 error detected, trying direct form submission as fallback');
                            
                            // Create a temporary form for direct submission
                            var tempForm = $('<form>', {
                                'action': 'additional_payments.php',
                                'method': 'post',
                                'style': 'display: none;'
                            });
                            
                            // Add all the form data as hidden fields
                            $.each(formData, function(key, value) {
                                $('<input>').attr({
                                    type: 'hidden',
                                    name: key,
                                    value: value
                                }).appendTo(tempForm);
                            });
                            
                            // Add the form to the body and submit it
                            tempForm.appendTo('body').submit();
                            return;
                        }
                        
                        try {
                            var errorResponse = JSON.parse(xhr.responseText);
                            console.error('Parsed error response:', errorResponse);
                            alert('<?= __('error') ?>: ' + (errorResponse.message || '<?= __('failed_to_update_payment') ?>'));
                        } catch (e) {
                            console.error('Could not parse error response:', e);
                            alert('<?= __('error') ?>: <?= __('failed_to_update_payment') ?>. <?= __('please_try_again') ?>');
                        }
                    }
                });
            });

            // Load transactions when modal is shown
            $('#addTransactionModal').on('show.bs.modal', function() {
                var paymentId = $('#transaction_payment_id').val();
                transactionManager.loadTransactionHistory(paymentId);
            });

            // Currency dropdown change event
            $('#transaction_currency').change(function() {
                var selectedCurrency = $(this).val();
                var originalCurrency = $('#original_payment_currency').val();
                
                // Show/hide exchange rate field if currencies are different
                if (selectedCurrency !== originalCurrency) {
                    $('#exchange_rate_group').show();
                    $('#exchange_rate').prop('required', true);
                } else {
                    $('#exchange_rate_group').hide();
                    $('#exchange_rate').prop('required', false);
                }
            });
            
            // Save transaction
            $('#AddTransaction').click(function() {
                var selectedCurrency = $('#transaction_currency').val();
                var originalCurrency = $('#original_payment_currency').val();
                var description = $('#payment_description').val();
                var exchangeRate = $('#exchange_rate').val();
                
                // Exchange rate is stored in separate field, no need to modify description
                if (selectedCurrency !== originalCurrency) {
                    if (!exchangeRate) {
                        alert('<?= __('please_enter_an_exchange_rate') ?>');
                        return;
                    }
                }
                
                var formData = {
                    payment_id: $('#transaction_payment_id').val(),
                    payment_type: $('#transaction_payment_type').val(),
                    currency: selectedCurrency,
                    original_currency: originalCurrency,
                    exchange_rate: exchangeRate,
                    main_account_id: $('#transaction_main_account_id').val(),
                    payment_amount: $('#payment_amount').val(),
                    payment_date: $('#payment_date').val(),
                    payment_time: $('#payment_time').val(),
                    payment_description: $('#payment_description').val(),
                };

                var url = 'add_additional_payment_transaction.php';
                var transactionId = $('#transaction_id').val();
                if (transactionId) {
                    url = 'update_additional_payment_transaction.php';
                    formData.transaction_id = transactionId;
                }

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        try {
                            // Handle both string and parsed JSON responses
                            var result = typeof response === 'object' ? response : JSON.parse(response);
                            if (result.success) {
                                alert('<?= __('transaction_saved_successfully') ?>');
                                // Reset form fields
                                $('#payment_amount').val('');
                                $('#payment_description').val('');
                                $('#exchange_rate').val('');
                                // Reload transactions
                                transactionManager.loadTransactionHistory($('#transaction_payment_id').val());
                            } else {
                                alert('<?= __('error') ?>: ' + (result.message || '<?= __('unknown_error_occurred') ?>'));
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                                alert('<?= __('error') ?>: <?= __('invalid_response_from_server') ?>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error saving transaction:', error);
                        try {
                            var errorResponse = JSON.parse(xhr.responseText);
                            alert('<?= __('error') ?>: ' + (errorResponse.message || '<?= __('failed_to_save_transaction') ?>'));
                        } catch (e) {
                            alert('<?= __('error') ?>: <?= __('failed_to_save_transaction') ?>. <?= __('please_try_again') ?>');
                        }
                    }
                });
            });


            // Add transaction button click handler
            $('.add-transaction').click(function() {
                var id = $(this).data('id');
                var paymentType = $(this).data('payment-type');
                var currency = $(this).data('currency');
                var mainAccount = $(this).data('main-account');
                var client = $(this).data('client');
                var supplier = $(this).data('supplier');
                var description = $(this).data('description');
                var amount = $(this).data('sold-amount');
                
                // Set transaction form data
                $('#transaction_payment_id').val(id);
                $('#transaction_payment_type').val(paymentType);
                $('#original_payment_currency').val(currency);
                $('#transaction_currency').val(currency); // Set default currency same as payment currency
                $('#transaction_main_account_id').val(mainAccount);
                
                // Set display information
                $('#trans-payment-type').text(paymentType);
                $('#trans-description').text(description);
                $('#totalAmount').text(`${currency} ${parseFloat(amount).toFixed(2)}`);
                $('#remainingAmount').text(`${currency} ${parseFloat(amount).toFixed(2)}`);
                
                // Get account name
                var accountName = $("#main_account_id option[value='" + mainAccount + "']").text();
                $('#trans-account').text(accountName);
                
                // Reset exchange rate field
                $('#exchange_rate').val('');
                $('#exchange_rate_group').hide();
                
                // Set today's date and current time
                var now = new Date();
                var today = now.toISOString().split('T')[0];
                $('#payment_date').val(today);
                
                // Format time as HH:MM:SS
                var hours = String(now.getHours()).padStart(2, '0');
                var minutes = String(now.getMinutes()).padStart(2, '0');
                var seconds = String(now.getSeconds()).padStart(2, '0');
                $('#payment_time').val(`${hours}:${minutes}:${seconds}`);
                
                $('#addTransactionModal').modal('show');
            });

            // Edit transaction
            $(document).on('click', '.edit-transaction', function() {
                var id = $(this).data('id');
                var amount = $(this).data('amount');
                var currency = $(this).data('currency');
                var date = $(this).data('date');
                var description = $(this).data('description');
                var receipt = $(this).data('receipt');
                var paymentId = $('#transaction_payment_id').val();
                var originalCurrency = $('#original_payment_currency').val();

                $('#edit_transaction_id').val(id);
                $('#edit_transaction_payment_id').val(paymentId);
                $('#edit_original_payment_currency').val(originalCurrency);
                $('#edit_payment_amount').val(amount);
                $('#edit_transaction_currency').val(currency);
                
                // Parse the datetime string
                var txDate = new Date(date);
                var formattedDate = txDate.toISOString().split('T')[0];
                
                // Format time as HH:MM:SS
                var hours = String(txDate.getHours()).padStart(2, '0');
                var minutes = String(txDate.getMinutes()).padStart(2, '0');
                var seconds = String(txDate.getSeconds()).padStart(2, '0');
                var formattedTime = `${hours}:${minutes}:${seconds}`;
                
                $('#edit_payment_date').val(formattedDate);
                $('#edit_payment_time').val(formattedTime);
                $('#edit_payment_description').val(description);
                $('#edit_receipt').val(receipt);
                
                // Show/hide exchange rate field based on currency
                if (currency !== originalCurrency) {
                    $('#edit_exchange_rate_group').show();
                    $('#edit_exchange_rate').prop('required', true);
                    
                    // Exchange rate comes directly from the database field
                    // No need to extract from description
                } else {
                    $('#edit_exchange_rate_group').hide();
                    $('#edit_exchange_rate').prop('required', false);
                }
                
                // Add event listener for currency change
                $('#edit_transaction_currency').off('change').on('change', function() {
                    var selectedCurrency = $(this).val();
                    if (selectedCurrency !== originalCurrency) {
                        $('#edit_exchange_rate_group').show();
                        $('#edit_exchange_rate').prop('required', true);
                    } else {
                        $('#edit_exchange_rate_group').hide();
                        $('#edit_exchange_rate').prop('required', false);
                    }
                });
                
                $('#editTransactionModal').modal('show');
            });

            // Update transaction
            $('#updateTransaction').click(function() {
                var selectedCurrency = $('#edit_transaction_currency').val();
                var originalCurrency = $('#edit_original_payment_currency').val();
                var description = $('#edit_payment_description').val();
                var exchangeRate = $('#edit_exchange_rate').val();
                
                // Exchange rate is stored in separate field, no need to modify description
                if (selectedCurrency !== originalCurrency) {
                    if (!exchangeRate) {
                            alert('<?= __('please_enter_an_exchange_rate') ?>');
                        return;
                    }
                }
                
                var formData = {
                    transaction_id: $('#edit_transaction_id').val(),
                    payment_id: $('#edit_transaction_payment_id').val(),
                    payment_amount: $('#edit_payment_amount').val(),
                    currency: selectedCurrency,
                    original_currency: originalCurrency,
                    exchange_rate: exchangeRate,
                    payment_date: $('#edit_payment_date').val(),
                    payment_time: $('#edit_payment_time').val(),
                    payment_description: $('#edit_payment_description').val(),
                    receipt: $('#edit_receipt').val()
                };

                $.ajax({
                    url: 'update_additional_payment_transaction.php',
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        try {
                            var result = typeof response === 'object' ? response : JSON.parse(response);
                            if (result.success) {
                                alert('<?= __('transaction_updated_successfully') ?>');
                                $('#editTransactionModal').modal('hide');
                                transactionManager.loadTransactionHistory($('#transaction_payment_id').val());
                            } else {
                                alert('Error: ' + (result.message || 'Unknown error occurred'));
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            alert('<?= __('error') ?>: <?= __('invalid_response_from_server') ?>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error updating transaction:', error);
                        try {
                            var errorResponse = JSON.parse(xhr.responseText);
                            alert('<?= __('error') ?>: ' + (errorResponse.message || '<?= __('failed_to_update_transaction') ?>'));
                        } catch (e) {
                            alert('<?= __('error') ?>: <?= __('failed_to_update_transaction') ?>. <?= __('please_try_again') ?>');
                        }
                    }
                });
            });

            // Delete transaction
            $(document).on('click', '.delete-transaction', function() {
                if (confirm('<?= __('are_you_sure_you_want_to_delete_this_transaction') ?>')) {
                    var id = $(this).data('id');
                    var paymentId = $('#transaction_payment_id').val();
                    
                    $.ajax({
                        url: 'delete_additional_payment_transaction.php',
                        type: 'POST',
                        data: { 
                            transaction_id: id,
                            payment_id: paymentId
                        },
                        success: function(response) {
                            try {
                                var result = typeof response === 'object' ? response : JSON.parse(response);
                                if (result.success) {
                                    alert('<?= __('transaction_deleted_successfully') ?>');
                                    transactionManager.loadTransactionHistory(paymentId);
                                } else {
                                    alert('<?= __('error') ?>: ' + (result.message || '<?= __('unknown_error_occurred') ?>'));
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e);
                                alert('<?= __('error') ?>: <?= __('invalid_response_from_server') ?>');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error deleting transaction:', error);
                            try {
                                var errorResponse = JSON.parse(xhr.responseText);
                                    alert('<?= __('error') ?>: ' + (errorResponse.message || '<?= __('failed_to_delete_transaction') ?>'));
                            } catch (e) {
                                alert('<?= __('error') ?>: <?= __('failed_to_delete_transaction') ?>. <?= __('please_try_again') ?>');
                            }
                        }
                    });
                }
            });
        });
</script> 


<script>
    function calculateProfit() {
        const baseAmount = parseFloat(document.getElementById('base_amount').value) || 0;
        const soldAmount = parseFloat(document.getElementById('sold_amount').value) || 0;
        const profit = soldAmount - baseAmount;
        document.getElementById('profit').value = profit.toFixed(2);
    }

    function calculateEditProfit() {
        const baseAmount = parseFloat(document.getElementById('edit_base_amount').value) || 0;
        const soldAmount = parseFloat(document.getElementById('edit_sold_amount').value) || 0;
        const profit = soldAmount - baseAmount;
        document.getElementById('edit_profit').value = profit.toFixed(2);
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Calculate profit when the page loads if values exist
        calculateProfit();
        calculateEditProfit();

        // Add input event listeners for real-time calculation
        document.getElementById('base_amount').addEventListener('input', calculateProfit);
        document.getElementById('sold_amount').addEventListener('input', calculateProfit);
        document.getElementById('edit_base_amount').addEventListener('input', calculateEditProfit);
        document.getElementById('edit_sold_amount').addEventListener('input', calculateEditProfit);

    


        // Save Payment button click handler
        $('#savePayment').click(function() {
            var form = $('#addPaymentForm');
            var formData = new FormData(form[0]);
            
            // Add checkbox values
            formData.append('is_from_supplier', $('#is_from_supplier').is(':checked') ? 1 : 0);
            formData.append('is_for_client', $('#is_for_client').is(':checked') ? 1 : 0);
            
            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        var result = typeof response === 'object' ? response : JSON.parse(response);
                        if (result.success) {
                            $('#addPaymentModal').modal('hide');
                            location.reload();
                        } else {
                            alert('<?= __('error') ?>: ' + (result.message || '<?= __('unknown_error_occurred') ?>'));
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        alert('<?= __('error') ?>: <?= __('invalid_response_from_server') ?>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error saving payment:', error);
                    try {
                        var errorResponse = JSON.parse(xhr.responseText);
                        alert('<?= __('error') ?>: ' + (errorResponse.message || '<?= __('failed_to_save_payment') ?>'));
                    } catch (e) {
                        alert('<?= __('error') ?>: <?= __('failed_to_save_payment') ?>. <?= __('please_try_again') ?>');
                    }
                }
            });
        });

        // Delete Payment button click handler
        $('.delete-payment').click(function() {
            var id = $(this).data('id');
            if (confirm('<?= __('are_you_sure_you_want_to_delete_this_payment') ?>')) {
                $.ajax({
                    url: 'includes/delete_additional_payment.php',
                    type: 'POST',
                    data: {
                        action: 'delete',
                        id: id
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.message || '<?= __('cannot_delete_payment') ?>. <?= __('please_delete_any_associated_transactions_first') ?>');
                        }
                    },
                    error: function(xhr) {
                        try {
                            var errorResponse = JSON.parse(xhr.responseText);
                                alert(errorResponse.message || '<?= __('error_deleting_payment') ?>. <?= __('please_check_if_it_has_transactions') ?>');
                        } catch (e) {
                            alert('<?= __('error_deleting_payment') ?>. <?= __('please_check_if_it_has_transactions') ?>');
                        }
                    }
                });
            }
        });

        // Add Transaction button click handler
        $('.add-transaction').click(function() {
            var id = $(this).data('id');
            var paymentType = $(this).data('payment-type');
            var currency = $(this).data('currency');
            var mainAccount = $(this).data('main-account');
            var client = $(this).data('client');
            var isSupplier = $(this).data('is-supplier');
            
            $('#transaction_payment_id').val(id);
            $('#transaction_payment_type').val(paymentType);
            $('#original_payment_currency').val(currency);
            $('#transaction_currency').val(currency); // Set default currency same as payment currency
            $('#transaction_main_account_id').val(mainAccount);
            $('#transaction_client_id').val(client);
            $('#transaction_is_from_supplier').val(isSupplier);
            
            // Reset exchange rate field
            $('#exchange_rate').val('');
            $('#exchange_rate_group').hide();
            
            $('#addTransactionModal').modal('show');
        });

        // Handle client checkbox in add form
        $('#is_from_supplier').change(function() {
            if($(this).is(':checked')) {
                $('.client-group').show();
                $('#client_id').prop('required', true);
            } else {
                $('.client-group').hide();
                $('#client_id').prop('required', false);
            }
        });

        // Handle client checkbox in edit form
        $('#edit_is_from_supplier').change(function() {
            if($(this).is(':checked')) {
                $('.client-group').show();
                $('#edit_client_id').prop('required', true);
            } else {
                $('.client-group').hide();
                $('#edit_client_id').prop('required', false);
            }
        });

        // When editing payment, check if it has client
        $('.edit-payment').click(function() {
            const isSupplier = $(this).data('is-supplier');
            const clientId = $(this).data('client');
            
            if(isSupplier) {
                $('#edit_is_from_supplier').prop('checked', true);
                $('.client-group').show();
                $('#edit_client_id').prop('required', true);
                $('#edit_client_id').val(clientId);
            } else {
                $('#edit_is_from_supplier').prop('checked', false);
                $('.client-group').hide();
                $('#edit_client_id').prop('required', false);
            }
        });
    });
    </script>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html> 