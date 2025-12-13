<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Database connection
require_once('../includes/db.php');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

include '../api/ticket/ticket_handler.php';
?>

<?php 
include '../includes/header.php';
?>
<link rel="stylesheet" href="../css/ticket/ticket_styles.css">
<link rel="stylesheet" href="../css/ticket/ticket-components.css">
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../css/ticket/ticket-form.css">
<!-- Add Bootstrap-select CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css">
   
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
                                        <h5 class="m-b-10"><?= __('ticket') ?></h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item"><a href="javascript:"><?= __('ticket') ?></a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [ breadcrumb ] end -->
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                             <!-- Toast Container -->
                            <div class="toast-container"></div>

                            <style>
                                .toast-container {
                                    position: fixed;
                                    top: 20px;
                                    right: 20px;
                                    z-index: 9999;
                                    max-width: 350px;
                                }

                                .toast {
                                    position: relative;
                                    background-color: #fff;
                                    border-radius: 8px;
                                    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
                                    margin-bottom: 10px;
                                    overflow: hidden;
                                    opacity: 0;
                                    transform: translateX(40px);
                                    transition: all 0.3s ease;
                                    border-left: 4px solid transparent;
                                    padding: 15px;
                                }

                                .toast-showing {
                                    opacity: 1;
                                    transform: translateX(0);
                                }

                                .toast-removing {
                                    opacity: 0;
                                    transform: translateY(-20px);
                                }

                                .toast-success {
                                    border-left-color: #10b981;
                                }

                                .toast-error {
                                    border-left-color: #ef4444;
                                }

                                .toast-warning {
                                    border-left-color: #f59e0b;
                                }

                                .toast-info {
                                    border-left-color: #3b82f6;
                                }

                                .toast-title {
                                    display: flex;
                                    align-items: center;
                                    font-weight: 600;
                                    margin-bottom: 5px;
                                }

                                .toast-message {
                                    word-break: break-word;
                                    line-height: 1.5;
                                    color: #64748b;
                                }
                            </style>
                            <div class="row">
                                <div class="col-sm-12">
                                    <!-- Search and Actions Section -->
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <div class="row align-items-center">
                                                <div class="col-md-8">
                                                    <div class="search-box">
                                                        <div class="input-group">
                                                            <input type="text" id="pnrFilter" class="form-control" placeholder="<?= __('search_by_pnr_passenger_name_or_airline') ?>" value="<?= htmlspecialchars($search) ?>">
                                                            <div class="input-group-append">
                                                                <button class="btn btn-primary" type="button" id="searchBtn">
                                                                    <i class="feather icon-search"></i> <?= __('search') ?>
                                                                </button>
                                                                <?php if (!empty($search)): ?>
                                                                <a href="ticket.php" class="btn btn-secondary">
                                                                    <i class="feather icon-x"></i> <?= __('clear') ?>
                                                                </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 text-right">
                                                    <button class="btn btn-primary btn-lg shadow-md" data-toggle="modal" data-target="#bookTicketModal">
                                                        <i class="feather icon-plus-circle mr-2"></i><?= __('book_ticket') ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tickets Table Section -->
                                    <div class="card">
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center" width="50">#</th>
                                                            <th width="100"><?= __('action') ?></th>
                                                            <th width="60" class="text-center"><?= __('payment') ?></th>
                                                            <th><?= __('passenger_info') ?></th>
                                                            <th><?= __('flight_details') ?></th>
                                                            <th><?= __('booking_info') ?></th>
                                                            <th class="text-right"><?= __('amount') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="ticketTable">
                                                        <?php 
                                                        $counter = 1;
                                                        foreach ($tickets as $ticket): 
                                                            $isAgencyClient = false;
                                                            $soldTo = $ticket['ticket']['sold_to'];
                                                            $clientStmt = $pdo->prepare("SELECT client_type FROM clients WHERE name = ? AND tenant_id = ? AND branch_id = ?");
                                                            $clientStmt->bindParam(1, $soldTo, PDO::PARAM_STR);
                                                            $clientStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                                            $clientStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                                            $clientStmt->execute();
                                                            $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC);
                                                            if ($clientRow) {
                                                                $isAgencyClient = ($clientRow['client_type'] === 'agency');
                                                            }
                                                        ?>
                                                        <tr>
                                                            <td class="text-center"><?= $counter++ ?></td>
                                                            <td>
                                                                <div class="dropdown">
                                                                    <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" id="actionDropdown<?= $ticket['ticket']['id'] ?>" data-toggle="dropdown">
                                                                        <i class="feather icon-more-vertical"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu dropdown-menu-right">
                                                                        <button class="dropdown-item view-details" data-ticket='<?= htmlspecialchars(json_encode($ticket)) ?>'>
                                                                            <i class="feather icon-eye text-primary mr-2"></i> <?= __('view_details') ?>
                                                                        </button>
                                                                        <button class="dropdown-item" onclick="editTicket(<?= $ticket['ticket']['id'] ?>)">
                                                                            <i class="feather icon-edit-2 text-warning mr-2"></i> <?= __('edit') ?>
                                                                        </button>
                                                                        <?php if ($isAgencyClient): ?>
                                                                        <button class="dropdown-item" onclick="manageTransactions(<?= $ticket['ticket']['id'] ?>)">
                                                                            <i class="fas fa-dollar-sign text-success mr-2"></i> <?= __('manage_transactions') ?>
                                                                        </button>
                                                                        <?php endif; ?>
                                                                        <div class="dropdown-divider"></div>
                                                                        <button class="dropdown-item text-danger" onclick="deleteTicket(<?= $ticket['ticket']['id'] ?>)">
                                                                            <i class="feather icon-trash-2 mr-2"></i> <?= __('delete') ?>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="text-center">
                                                            <?php
                                                            // Get client type from clients table
                                                            $soldTo = $ticket['ticket']['sold_to'];
                                                            $isAgencyClient = false;

                                                            // Check if client is an agency
                                                            $clientStmt = $pdo->prepare("SELECT client_type FROM clients WHERE name = ? AND tenant_id = ? AND branch_id = ?");
                                                            $clientStmt->bindParam(1, $soldTo, PDO::PARAM_STR);
                                                            $clientStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                                            $clientStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                                            $clientStmt->execute();
                                                            $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC);
                                                            if ($clientRow) {
                                                                $isAgencyClient = ($clientRow['client_type'] === 'agency');
                                                            }

                                                            if ($isAgencyClient) {
                                                                // Calculate payment status using transaction-specific exchange rates
                                                                $baseCurrency = $ticket['ticket']['currency'];
                                                                $soldAmount = floatval($ticket['ticket']['sold']);
                                                                $totalPaidInBase = 0.0;

                                                                // Get ticket ID
                                                                $ticketId = $ticket['ticket']['id'];

                                                                // Query transactions from main_account_transactions table
                                                                $transactionStmt = $pdo->prepare("SELECT * FROM main_account_transactions WHERE
                                                                    transaction_of = 'ticket_sale'
                                                                    AND reference_id = ? AND tenant_id = ? AND branch_id = ?");
                                                                $transactionStmt->bindParam(1, $ticketId, PDO::PARAM_INT);
                                                                $transactionStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                                                $transactionStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                                                $transactionStmt->execute();
                                                                $transactions = $transactionStmt->fetchAll(PDO::FETCH_ASSOC);

                                                                if ($transactions && count($transactions) > 0) {
                                                                    foreach ($transactions as $transaction) {
                                                                        $amount = floatval($transaction['amount']);
                                                                        $transCurrency = $transaction['currency'];
                                                                        $transExchangeRate = isset($transaction['exchange_rate']) && $transaction['exchange_rate'] > 0 ? floatval($transaction['exchange_rate']) : 1.0;

                                                                        $convertedAmount = 0.0;

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
                                                                <div class="passenger-info">
                                                                    
                                                                    <div class="passenger-info__details">
                                                                        <div class="passenger-info__name">
                                                                            <?= htmlspecialchars($ticket['ticket']['title']) ?> <?= htmlspecialchars($ticket['ticket']['passenger_name']) ?>
                                                                        </div>
                                                                        <div class="passenger-info__pnr">
                                                                            PNR: <?= htmlspecialchars($ticket['ticket']['pnr']) ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td> 
                                                            <td>
                                                                <div class="flight-info">
                                                                    <div class="flight-info__segment">
                                                                        <div class="flight-info__city">
                                                                            <?= htmlspecialchars($ticket['ticket']['origin']) ?> - <?= htmlspecialchars($ticket['ticket']['destination']) ?>
                                                                        </div>
                                                                        <?php if ($ticket['ticket']['trip_type'] === 'round_trip'): ?>
                                                                            <div class="flight-info__city mt-2">
                                                                                <?= htmlspecialchars($ticket['ticket']['destination']) ?> - <?= htmlspecialchars($ticket['ticket']['return_destination']) ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        <div class="flight-info__airline">
                                                                            <?= htmlspecialchars($ticket['ticket']['airline']) ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="booking-info">
                                                                    <div class="booking-info__date">
                                                                        <i class="feather icon-calendar text-muted mr-1"></i>
                                                                        <?= htmlspecialchars($ticket['ticket']['issue_date']) ?>
                                                                    </div>
                                                                    <div class="booking-info__flight-date">
                                                                        <i class="feather icon-plane text-muted mr-1"></i>
                                                                        <?= htmlspecialchars($ticket['ticket']['departure_date']) ?>
                                                                        <?php if (!empty($ticket['ticket']['departure_time'])): ?> at <?= htmlspecialchars($ticket['ticket']['departure_time']) ?><?php endif; ?>
                                                                        <?php if ($ticket['ticket']['trip_type'] === 'round_trip'): ?>
                                                                            <br>
                                                                            <i class="feather icon-plane text-muted mr-1"></i>
                                                                            <?= htmlspecialchars($ticket['ticket']['return_date']) ?>
                                                                            <?php if (!empty($ticket['ticket']['return_departure_time'])): ?> at <?= htmlspecialchars($ticket['ticket']['return_departure_time']) ?><?php endif; ?>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="text-right">
                                                                <div class="ticket-amount">
                                                                    <div class="ticket-amount__value">
                                                                        <?= htmlspecialchars($ticket['ticket']['currency']) ?> <?= number_format($ticket['ticket']['sold'], 2) ?>
                                                                    </div>
                                                                    <?php if ($ticket['refund_data']): ?>
                                                                        <div class="ticket-amount__refund text-danger">
                                                                            <small>
                                                                                <?= __('refunded') ?>: <?= htmlspecialchars($ticket['refund_data']['currency']) ?> <?= number_format($ticket['refund_data']['refund_to_passenger'], 2) ?>
                                                                            </small>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <?php if ($ticket['date_change_data']): ?>
                                                                        <div class="ticket-amount__date-change text-warning">
                                                                            <small>
                                                                                <?= __('date_change') ?>: <?= htmlspecialchars($ticket['date_change_data']['currency']) ?> <?= number_format($ticket['date_change_data']['supplier_penalty'] + $ticket['date_change_data']['service_penalty'], 2) ?>
                                                                            </small>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <?php if ($ticket['ticket']['weight_count'] > 0): ?>
                                                                        <div class="ticket-amount__weight text-info">
                                                                            <small>
                                                                                <?= __('weight') ?>: <?= htmlspecialchars($ticket['ticket']['weight_count']) ?> items, <?= number_format($ticket['ticket']['total_weight'], 2) ?> kg
                                                                            </small>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <!-- Pagination -->
                                            <div class="card-footer bg-white">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="text-muted">
                                                        <?= __('showing') ?> <?= min(($page - 1) * $results_per_page + 1, $totalTickets) ?> <?= __('to') ?> <?= min($page * $results_per_page, $totalTickets) ?> <?= __('of') ?> <?= $totalTickets ?> <?= __('tickets') ?>
                                                    </div>
                                                    <nav aria-label="Page navigation">
                                                        <ul class="pagination mb-0">
                                                            <?php if ($page > 1): ?>
                                                                <li class="page-item">
                                                                    <a class="page-link" href="?page=1<?= !empty($search) ? '&search='.urlencode($search) : '' ?>">
                                                                        <i class="feather icon-chevrons-left"></i>
                                                                    </a>
                                                                </li>
                                                                <li class="page-item">
                                                                    <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>">
                                                                        <i class="feather icon-chevron-left"></i>
                                                                    </a>
                                                                </li>
                                                            <?php endif; ?>
                                                            
                                                            <?php
                                                            $start_page = max(1, $page - 2);
                                                            $end_page = min($total_pages, $page + 2);
                                                            
                                                            if ($start_page > 1) {
                                                                echo '<li class="page-item"><a class="page-link" href="?page=1' . (!empty($search) ? '&search='.urlencode($search) : '') . '">1</a></li>';
                                                                if ($start_page > 2) {
                                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                                }
                                                            }
                                                            
                                                            for ($i = $start_page; $i <= $end_page; $i++) {
                                                                echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                                                    <a class="page-link" href="?page=' . $i . (!empty($search) ? '&search='.urlencode($search) : '') . '">' . $i . '</a>
                                                                    </li>';
                                                            }
                                                            
                                                            if ($end_page < $total_pages) {
                                                                if ($end_page < $total_pages - 1) {
                                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                                }
                                                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . (!empty($search) ? '&search='.urlencode($search) : '') . '">' . $total_pages . '</a></li>';
                                                            }
                                                            ?>
                                                            
                                                            <?php if ($page < $total_pages): ?>
                                                                <li class="page-item">
                                                                    <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>">
                                                                        <i class="feather icon-chevron-right"></i>
                                                                    </a>
                                                                </li>
                                                                <li class="page-item">
                                                                    <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?>">
                                                                        <i class="feather icon-chevrons-right"></i>
                                                                    </a>
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
        <!-- Add a floating action button for launching the multi-ticket invoice modal -->
        <div id="floatingActionButton" class="position-fixed" style="bottom: 80px; z-index: 1050;">
    <button type="button" class="btn btn-primary btn-lg shadow" id="launchMultiTicketInvoice" title="<?= __('generate_multi_ticket_invoice') ?>">
        <i class="feather icon-file-text"></i>
    </button>
</div>
<?php include '../modals/ticket/multi_ticket_modal.php'; ?> 
    <?php include '../modals/ticket/book_ticket_modal.php'; ?>
    <?php include '../modals/ticket/ticket_details.php'; ?>
    <?php include '../modals/ticket/ticket_refund_modal.php'; ?>
    <?php include '../modals/ticket/ticket_date_change_modal.php'; ?>
    <?php include '../modals/ticket/ticket_weight_modal.php'; ?>
    <?php include '../modals/ticket/transaction_modal.php'; ?>

    <?php include '../modals/ticket/edit_ticket_modal.php'; ?>
    



       
<?php include '../includes/admin_footer.php'; ?>



                                    <!-- Required Js -->
                                    <script src="../assets/js/vendor-all.min.js"></script>
                                    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
                                    <script src="../assets/js/pcoded.min.js"></script>
                                    <!-- Add Bootstrap-select JavaScript -->
                                    <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>
                                    <script src="../js/ticket/profit-calc.js"></script>
                                    <script src="../js/ticket//ticket-details.js"></script>
                                    <script src="../js/ticket//ticket-form.js"></script>
                                    <script src="../js/ticket//supplier-currency.js"></script>
                                    <script src="../js/ticket//delete-ticket.js"></script>
                                    <script src="../js/ticket//weight-management.js"></script>
                                    <script src="../js/ticket/refund-calc.js"></script>
                                    <script src="../js/ticket/generate-invoice.js"></script>
                                    <script src="../js/ticket/search.js"></script>
                                    <script src="../js/ticket/transaction-manager.js"></script>
                                    <script src="../js/ticket/trip-type.js"></script>
                                    <script src="../js/ticket/payment-calculation.js"></script>
                                    <script src="../js/ticket/passenger-count.js"></script>
                                    <script src="../js/ticket/supplier-currency-select.js"></script>
                                    <script src="../js/ticket/edit-ticket.js"></script>
                                    <script src="../js/ticket/passenger-management.js"></script>
                                    <script src="../js/ticket/data/airlines.js"></script>
                                    <script src="../js/ticket/airline-select.js"></script>
                                    <script src="../js/ticket/multi-ticket-invoice.js"></script>
                                    <script src="../js/ticket/pdf-upload-handler.js"></script>
                                    <script src="../js/ticket/passenger_info.js"></script>
                                    <script src="../js/ticket/toast.js"></script>
                                    <script src="../js/ticket/pdf-ticket-extract.js"></script>


</body>
</html>