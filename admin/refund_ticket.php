<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();



// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
include '../api/ticket_refund/refund_ticket_handler.php';

// Generate cache-busting version
$version = '?v=' . time();
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="css/ticket_styles.css">
<link rel="stylesheet" href="css/ticket-components.css">
<link rel="stylesheet" href="css/modal-styles.css">
<link rel="stylesheet" href="css/ticket-form.css">

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
<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="page-header-title">
                                    <h5 class="m-b-10"><?= __('refund_tickets') ?></h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="javascript:"><?= __('refund_tickets') ?></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->

                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="row">
                                <!-- [ Ticket Table ] start -->
                                <div class="card" style="width: 100%;">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h4 class="mb-0">Refunded Tickets</h4>
                                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRefundTicketModal">
                                            <i class="feather icon-plus mr-2"></i><?= __('add_refund_ticket') ?>
                                        </button>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="table-responsive">
                                            <table id="refundTicketTable" class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center">#</th>
                                                        
                                                        <th class="text-center"><?= __('actions') ?></th>
                                                        <th><?= __('passenger_details') ?></th>
                                                        <th><?= __('flight_info') ?></th>
                                                        <th><?= __('financial_details') ?></th>
                                                        <th><?= __('payment') ?></th>
                                                        <th><?= __('penalties') ?></th>
                                                        <th><?= __('refund_amount') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="ticketTable">
                                                    <?php foreach ($tickets as $index => $ticket): ?>
                                                        <?php
                                                        $soldTo = $ticket['sold_to_name'];
                                                        $isAgencyClient = false;

                                                        $clientQuery = $conn->query("SELECT client_type FROM clients WHERE name = '$soldTo' AND tenant_id = $tenant_id AND branch_id = $branch_id");
                                                        if ($clientQuery && $clientQuery->num_rows > 0) {
                                                            $clientRow = $clientQuery->fetch_assoc();
                                                            $isAgencyClient = ($clientRow['client_type'] === 'agency');
                                                        }
                                                        ?>
                                                    <tr>
                                                        <td class="text-center"><?= $index + 1 ?></td>
                                                        <td class="text-center">
                                                            <div class="d-flex justify-content-center">
                                                                <div class="dropdown">
                                                                    <button class="btn btn-icon btn-outline-primary dropdown-toggle" type="button" data-toggle="dropdown">
                                                                        <i class="feather icon-more-horizontal"></i>
                                                                    </button>
                                                                <div class="dropdown-menu dropdown-menu-right">
                                                                    <?php if ($isAgencyClient): ?>
                                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="manageTransactions(<?= $ticket['id'] ?>)">
                                                                        <i class="fas fa-dollar-sign mr-2"></i><?= __('manage_payments') ?>
                                                                    </a>
                                                                    <?php endif; ?>
                                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="printRefundAgreement(<?= $ticket['id'] ?>)">
                                                                        <i class="feather icon-file mr-2"></i><?= __('print_refund_agreement') ?>
                                                                    </a>
                                                                    <div class="dropdown-divider"></div>
                                                                    <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteTicket(<?= $ticket['id'] ?>)">
                                                                        <i class="feather icon-trash-2 mr-2"></i><?= __('delete') ?>
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </td>
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
                                                                    <div class="flight-info__airline">
                                                                        <?= htmlspecialchars($ticket['airline']) ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="financial-info">
                                                                <div class="financial-info__amount">
                                                                    <?= __('base') ?>: <?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['base'], 2) ?>
                                                                </div>
                                                                <div class="financial-info__penalties">
                                                                    <?= __('sold') ?>: <?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['sold'], 2) ?>
                                                                </div>
                                                                
                                                            </div>
                                                        </td>
                                                        <td>
                                                        <?php
                                                        $soldTo = $ticket['sold_to_name'];
                                                        $isAgencyClient = false;

                                                        $clientQuery = $conn->query("SELECT client_type FROM clients WHERE name = '$soldTo' AND tenant_id = $tenant_id AND branch_id = $branch_id");
                                                        if ($clientQuery && $clientQuery->num_rows > 0) {
                                                            $clientRow = $clientQuery->fetch_assoc();
                                                            $isAgencyClient = ($clientRow['client_type'] === 'agency');
                                                        }

                                                        if ($isAgencyClient) {
                                                            // Calculate payment status using transaction-specific exchange rates
                                                            $baseCurrency = $ticket['currency'];
                                                            $soldAmount = floatval($ticket['refund_to_passenger']);
                                                            $totalPaidInBase = 0.0;

                                                            $ticketId = $ticket['id'];

                                                            // Query transactions from main_account_transactions table
                                                            $transactionQuery = $conn->query("SELECT * FROM main_account_transactions WHERE
                                                                transaction_of = 'ticket_refund'
                                                                AND reference_id = '$ticketId' AND tenant_id = $tenant_id AND branch_id = $branch_id");

                                                            if ($transactionQuery && $transactionQuery->num_rows > 0) {
                                                                while ($transaction = $transactionQuery->fetch_assoc()) {
                                                                    $amount = floatval($transaction['amount']);
                                                                    $transCurrency = $transaction['currency'];
                                                                    $transExchangeRate = isset($transaction['exchange_rate']) && $transaction['exchange_rate'] > 0
                                                                        ? floatval($transaction['exchange_rate']) : 1.0;

                                                                    $convertedAmount = 0.0;

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
                                                                } // End of while loop
                                                            }

                                                            // Status icon based on payment status
                                                            if ($totalPaidInBase <= 0) {
                                                                echo '<i class="fas fa-circle text-danger" title="No payment received"></i>';
                                                            } elseif ($totalPaidInBase < $soldAmount) {
                                                                $percentage = round(($totalPaidInBase / $soldAmount) * 100);
                                                                echo '<i class="fas fa-circle text-warning" style="color: #ffc107 !important;"
                                                                    title="Partial payment: ' . $baseCurrency . ' ' . number_format($totalPaidInBase, 2) . ' / ' . $baseCurrency . ' ' .
                                                                    number_format($soldAmount, 2) . ' (' . $percentage . '%)"></i>';
                                                            } elseif (abs($totalPaidInBase - $soldAmount) < 0.01) {
                                                                echo '<i class="fas fa-circle text-success" title="Fully paid"></i>';
                                                            } else {
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
                                                                <div class="financial-info__penalties">
                                                                   <?= __('supplier_penalty') ?>: <?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['supplier_penalty'], 2) ?>
                                                                </div>
                                                                <div class="financial-info__penalties">
                                                                    <?= __('service_penalty') ?>: <?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['service_penalty'], 2) ?>
                                                                </div>
                                                        </td>
                                                        <td>
                                                            <div class="financial-info">
                                                                <div class="financial-info__amount">
                                                                    <?= htmlspecialchars($ticket['currency']) ?> <?= number_format($ticket['refund_to_passenger'], 2) ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <!-- [ Ticket Table ] end -->
                            </div>
                        </div>
                        <!-- [ Main Content ] end -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../modals/ticket_refund/refund_ticket_modal.php'; ?>
<?php include '../modals/ticket_refund/transaction_modal.php'; ?>
<?php include '../modals/ticket_refund/edit_transaction_modal.php'; ?>
<?php include '../modals/ticket_refund/multi_ticket.php'; ?>






                                  <!-- Required Js -->
                                    <script src="../assets/js/vendor-all.min.js"></script>
                                    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
                                    <script src="../assets/js/pcoded.min.js"></script>

                                    <!-- DataTables JS -->
                                    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
                                    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
                                    <script src="https://cdn.datatables.net/responsive/2.2.7/js/dataTables.responsive.min.js"></script>
                                    <script src="https://cdn.datatables.net/responsive/2.2.7/js/responsive.bootstrap4.min.js"></script>
                                

<style>
    /* Enhanced Card Styles */
    .card {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }

    .card:hover {
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        transform: translateY(-5px);
    }

    /* Responsive Table Improvements */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    /* Ticket Table Styles */
    #refundTicketTable thead {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
    }

    #refundTicketTable th {
        font-weight: 600;
        text-transform: uppercase;
        color: #6c757d;
        padding: 12px 15px;
    }

    #refundTicketTable td {
        vertical-align: middle;
        padding: 12px 15px;
    }

    /* Status Indicator Styles */
    .status-indicator {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin-right: 8px;
    }

    .status-indicator.status-paid {
        background-color: #28a745;
    }

    .status-indicator.status-partial {
        background-color: #ffc107;
    }

    .status-indicator.status-unpaid {
        background-color: #dc3545;
    }

    /* Avatar Styles */
    .avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: white;
    }

    .avatar.bg-light-primary {
        background-color: rgba(59, 125, 221, 0.2);
        color: #3b7ddd;
    }

    /* Dropdown Menu Styles */
    .dropdown-menu {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border: none;
    }

    .dropdown-item {
        transition: background-color 0.2s ease;
    }

    .dropdown-item:hover {
        background-color: #f8f9fa;
    }

    /* Floating Action Button */
    #floatingActionButton .btn {
        border-radius: 50%;
        width: 56px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    #floatingActionButton .btn:hover {
        transform: scale(1.1);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .card-body {
            padding: 15px;
        }

        .table-responsive {
            font-size: 0.9rem;
        }
    }

    #floatingActionButton {
        right: 30px;
    }

    /* RTL support - position on left side instead */
    html[dir="rtl"] #floatingActionButton {
        right: auto;
        left: 30px;
    }
</style>

<!-- SweetAlert2 CSS and JS -->
<link rel="stylesheet" href="../assets/plugins/sweetalert2/sweetalert2.min.css">
<script src="../assets/plugins/sweetalert2/sweetalert2.min.js"></script>

<!-- Initialize translations for JavaScript -->
<script>
window.translations = {
    search: "<?= __('search') ?>",
    show: "<?= __('show') ?>",
    entries: "<?= __('entries') ?>",
    showing: "<?= __('showing') ?>",
    to: "<?= __('to') ?>",
    of: "<?= __('of') ?>",
    filtered_from: "<?= __('filtered_from') ?>",
    total_entries: "<?= __('total_entries') ?>",
    first: "<?= __('first') ?>",
    last: "<?= __('last') ?>",
    next: "<?= __('next') ?>",
    previous: "<?= __('previous') ?>",
    ticket_id_is_missing: "<?= __('ticket_id_is_missing') ?>",
    error: "<?= __('error') ?>",
    failed_to_generate_agreement: "<?= __('failed_to_generate_agreement') ?>",
    error_generating_agreement: "<?= __('error_generating_agreement') ?>",
    are_you_sure_you_want_to_delete_this_ticket: "<?= __('are_you_sure_you_want_to_delete_this_ticket') ?>"
};
</script>

<!-- Custom JS -->
<script src="../js/ticket_refund/multi_ticket.js<?= $version ?>"></script>
<script src="../js/ticket_refund/search.js<?= $version ?>"></script>
<script src="../js/ticket_refund/transaction_manager.js<?= $version ?>"></script>
<script src="../js/ticket_refund/datatable.js<?= $version ?>"></script>
<script src="../js/ticket_refund/document_actions.js<?= $version ?>"></script>
<script src="../js/ticket_refund/table_search.js<?= $version ?>"></script>
<script src="../js/ticket_refund/select.js<?= $version ?>"></script>
<script src="../js/ticket_refund/main.js<?= $version ?>"></script>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>