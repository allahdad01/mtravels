<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';


// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();



// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
include '../api/ticket_date_change/date_change_handler.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.css">
<link rel="stylesheet" href="../assets/plugins/sweetalert2/sweetalert2.min.css">
<script src="../assets/plugins/sweetalert2/sweetalert2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.js"></script>
<link rel="stylesheet" href="css/ticket_styles.css">
<link rel="stylesheet" href="css/ticket-components.css">
<link rel="stylesheet" href="css/modal-styles.css">
<link rel="stylesheet" href="css/ticket-form.css">
<link rel="stylesheet" href="css/date-change/datechange-css.css">
        <?php 
include '../includes/header.php';
?>
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

/* Transaction Modal Enhancements */
#transactionsModal .modal-xl {
    max-width: 95% !important;
}

#transactionsModal .card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

#transactionsModal .card-header {
    border-radius: 8px 8px 0 0 !important;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

#transactionsModal .form-control:focus {
    border-color: #4099ff;
    box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
}

#transactionsModal .btn-group .btn {
    margin-left: 5px;
}

#transactionsModal .table th {
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

#transactionsModal .table td {
    vertical-align: middle;
}

#transactionsModal .border-bottom {
    border-color: #e9ecef !important;
}

/* Loading animation */
.fa-spin {
    animation: fa-spin 1s infinite linear;
}

@keyframes fa-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive improvements */
@media (max-width: 768px) {
    #transactionsModal .modal-xl {
        max-width: 100% !important;
        margin: 10px;
    }

    #transactionsModal .btn-group {
        flex-direction: column;
        width: 100%;
    }

    #transactionsModal .btn-group .btn {
        margin-left: 0;
        margin-top: 5px;
    }
}
</style>
    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <div class="col-sm-12">
                                    <!-- Search and Actions Section -->
                                    <div class="card-header mb-3">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col-md-8">
                                                    <h3><?= __('date_change_management') ?></h3>
                                                </div>
                                                <div class="col-md-4 text-right">
                                                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDateChangeModal">
                                                        <i class="feather icon-plus mr-2"></i><?= __('add_date_change') ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- [ Table ] start -->
                                    <div class="card">                              
                                        <div class="card-body p-0">        
                                            <div class="table-responsive">
                                                <table class="table table-hover" id="dateChangeTable">
                                                    <thead>
                                                        <tr>
                                                            <th><?= __('passenger') ?></th>
                                                            <th><?= __('flight_details') ?></th>
                                                            <th><?= __('date_change') ?></th>
                                                            <th><?= __('financial_details') ?></th>
                                                            <th><?= __('payment') ?></th>
                                                            <th><?= __('penalties') ?></th>
                                                            <th class="text-right no-sort"><?= __('actions') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="ticketTable">
                                                        <?php foreach ($tickets as $ticket): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="passenger-info">
                                                                    
                                                                    <div class="passenger-info__details">
                                                                        <div class="passenger-info__name">
                                                                            <?= htmlspecialchars($ticket['title']) ?> <?= htmlspecialchars($ticket['passenger_name']) ?>
                                                                        </div>
                                                                        <div class="passenger-info__pnr">
                                                                            PNR: <?= htmlspecialchars($ticket['pnr']) ?>
                                                                            <br>
                                                                            <?= __('phone') ?>: <?= htmlspecialchars($ticket['phone']) ?>
                                                                            <br>
                                                                            <?= __('created_by') ?>: <?= htmlspecialchars($ticket['created_by']) ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="flight-info">
                                                                    <div class="flight-info__segment">
                                                                        <div class="flight-info__city">
                                                                            <?= htmlspecialchars($ticket['origin']) ?> - <?= htmlspecialchars($ticket['destination']) ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="date-change-info">
                                                                    <div class="date-change-info__old">
                                                                        <?= __('old_date') ?>: <?= htmlspecialchars($ticket['old_departure_date']) ?>
                                                                    </div>
                                                                    <div class="date-change-info__new">
                                                                        <?= __('new_date') ?>: <?= htmlspecialchars($ticket['departure_date']) ?>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="financial-info">
                                                                    <div class="financial-info__amount">
                                                                        <?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['sold'], 2) ?>
                                                                    </div>
                                                                    <div class="financial-info__penalties">
                                                                        <?= __('base') ?>: <?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['base'], 2) ?>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                            <?php
                                                                // Get client type from clients table
                                                                $soldTo = $ticket['sold_to_name'];
                                                                $isAgencyClient = false; // Default to not agency client

                                                                // Fix: We need to query the clients table using the client name from sold_to
                                                                $clientQuery = $conn->query("SELECT client_type FROM clients WHERE tenant_id = $tenant_id AND branch_id = $branch_id AND name = '$soldTo'");
                                                                if ($clientQuery && $clientQuery->num_rows > 0) {
                                                                    $clientRow = $clientQuery->fetch_assoc();
                                                                    // Only show payment status for agency clients
                                                                    $isAgencyClient = ($clientRow['client_type'] === 'agency');
                                                                }

                                                                // Only show payment status for agency clients
                                                                if ($isAgencyClient) {
                                                                    // Calculate payment status using transaction-specific exchange rates
                                                                    $baseCurrency = $ticket['currency'];
                                                                    $soldAmount = floatval($ticket['supplier_penalty'] + $ticket['service_penalty']);
                                                                    $totalPaidInBase = 0.0;

                                                                    // Get ticket ID
                                                                    $ticketId = $ticket['id'];

                                                                    // Query transactions from main_account_transactions table
                                                                    $transactionQuery = $conn->query("SELECT * FROM main_account_transactions WHERE
                                                                        transaction_of = 'date_change'
                                                                        AND reference_id = '$ticketId'
                                                                        AND tenant_id = $tenant_id
                                                                        AND branch_id = $branch_id");

                                                                    // Define base exchange rates (can be fetched from DB if dynamic)
                                                                    $exchangeRates = [
                                                                        'USD' => 70,      // 1 USD = 70 AFS
                                                                        'AFS' => 1,       // Base unit
                                                                        'EUR' => 80,      // 1 EUR = 80 AFS
                                                                        'DARHAM' => 18.49 // 1 DARHAM = 18.49 AFS
                                                                    ];

                                                                    $totalPaidInBase = 0.0;

                                                                    if ($transactionQuery && $transactionQuery->num_rows > 0) {
                                                                        while ($transaction = $transactionQuery->fetch_assoc()) {
                                                                            $amount = floatval($transaction['amount']);
                                                                            $transCurrency = $transaction['currency'];
                                                                            
                                                                            // Use transaction-specific exchange rate if available, otherwise fallback to default
                                                                            $transExchangeRate = isset($transaction['exchange_rate']) && $transaction['exchange_rate'] > 0 
                                                                                                ? floatval($transaction['exchange_rate']) 
                                                                                                : (isset($exchangeRates[$transCurrency]) ? $exchangeRates[$transCurrency] : 1.0);

                                                                        // Conversion logic
                                                                        if ($transCurrency === $baseCurrency) {
                                                                            $convertedAmount = $amount;
                                                                        } else {
                                                                            if ($baseCurrency === 'AFS') {
                                                                                $convertedAmount = $amount * $transExchangeRate;
                                                                            } else {
                                                                                $convertedAmount = $amount / $transExchangeRate;
                                                                            }
                                                                        }

                                                                            $totalPaidInBase += $convertedAmount;
                                                                        }
                                                                    }


                                                                    // Status icon based on payment status
                                                                    if ($totalPaidInBase <= 0) {
                                                                        // No transactions
                                                                        echo '<i class="fas fa-circle text-danger" title="No payment received"></i>';
                                                                    } elseif ($totalPaidInBase < $soldAmount) {
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
                                                                } else {
                                                                    // Not an agency client - show neutral icon
                                                                    echo '<i class="fas fa-minus text-muted" title="Not an agency client"></i>';
                                                                }
                                                                ?>
                                                                </td>
                                                            <td>
                                                                <div class="financial-info">
                                                                    <div class="financial-info__amount">
                                                                        <?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['supplier_penalty'] + $ticket['service_penalty'], 2) ?>
                                                                    </div>
                                                                    <div class="financial-info__penalties">
                                                                        <?= __('supplier_penalty') ?>: <?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['supplier_penalty'], 2) ?>
                                                                    </div>
                                                                    <div class="financial-info__penalties">
                                                                        <?= __('service_penalty') ?>: <?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['service_penalty'], 2) ?>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            
                                                            <td class="text-center">
                                                                <div class="btn-group" role="group">
                                                                    <?php if ($isAgencyClient): ?>
                                                                    <button type="button" class="btn btn-sm btn-primary" onclick="manageTransactions(<?= $ticket['id'] ?>)" title="<?= __('manage_transactions') ?>">
                                                                        <i class="fa fa-credit-card"></i>
                                                                    </button>
                                                                    <?php endif; ?>
                                                                    <button type="button" class="btn btn-sm btn-warning" onclick="printAgreement(<?= $ticket['id'] ?>)" title="<?= __('print_agreement') ?>">
                                                                        <i class="feather icon-printer"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteTicket(<?= $ticket['id'] ?>)" title="<?= __('delete_ticket') ?>">
                                                                        <i class="feather icon-trash-2"></i>
                                                                    </button>
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
                                <!-- [ Table ] end -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../modals/ticket_date_change/multi_ticket.php'; ?>
    <?php include '../modals/ticket_date_change/transaction_modal.php'; ?>
    <?php include '../modals/ticket_date_change/edit_transaction.php'; ?>
    <?php include '../modals/ticket_date_change/add_date_change.php'; ?>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>
    


    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>
    <script src="../js/ticket_date_change/dataTable.js"></script>
    <script src="../js/ticket_date_change/addDateChange.js"></script>
    <script src="../js/ticket_date_change/deleteDateChange.js"></script>
    <script src="../js/ticket_date_change/transaction-manager.js"></script>
    <script src="../js/ticket_date_change/multiTicket.js"></script>

</body>
</html>
