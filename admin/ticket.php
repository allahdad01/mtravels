<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];

// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

include 'handlers/ticket_handler.php';
?>

<?php 
include '../includes/header.php';
?>
<link rel="stylesheet" href="css/ticket_styles.css">
<link rel="stylesheet" href="css/ticket-components.css">
<link rel="stylesheet" href="css/modal-styles.css">
<link rel="stylesheet" href="css/ticket-form.css">
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
                                                            $clientQuery = $conn->query("SELECT client_type FROM clients WHERE name = '$soldTo' AND tenant_id = $tenant_id");
                                                            if ($clientQuery && $clientQuery->num_rows > 0) {
                                                                $clientRow = $clientQuery->fetch_assoc();
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
                                                                        <button class="dropdown-item" onclick="manageTransactions(<?= $ticket['ticket']['id'] ?>)">
                                                                            <i class="fas fa-dollar-sign text-success mr-2"></i> <?= __('manage_transactions') ?>
                                                                        </button>
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
$clientQuery = $conn->query("SELECT client_type FROM clients WHERE name = '$soldTo'");
if ($clientQuery && $clientQuery->num_rows > 0) {
    $clientRow = $clientQuery->fetch_assoc();
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
    $transactionQuery = $conn->query("SELECT * FROM main_account_transactions WHERE
        transaction_of = 'ticket_sale'
        AND reference_id = '$ticketId'");

    if ($transactionQuery && $transactionQuery->num_rows > 0) {
        while ($transaction = $transactionQuery->fetch_assoc()) {
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
                                                                        <?php if ($ticket['ticket']['trip_type'] === 'round_trip'): ?>
                                                                            <br>
                                                                            <i class="feather icon-plane text-muted mr-1"></i>
                                                                            <?= htmlspecialchars($ticket['ticket']['return_date']) ?>
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
<?php include '../includes/admin_footer.php'; ?>
        <!-- Transaction Modal -->
        <div class="modal fade" id="transactionsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="feather icon-credit-card mr-2"></i><?= __('manage_transactions') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                    <!-- Hotel Info Card -->
                    <div class="card mb-4 border-primary">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2"><?= __('ticket_booking_details') ?></h6>
                                    <p class="mb-1"><strong><?= __('name') ?>:</strong> <span id="trans-guest-name"></span></p>
                                    <p class="mb-1"><strong><?= __('pnr') ?>:</strong> <span id="trans-order-id"></span></p>
                                </div>
                                <div class="col-md-6">
                                <div class="alert alert-info mb-0">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span><?= __('total_amount') ?>:</span>
                                        <strong id="totalAmount"></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span><?= __('exchange_rate') ?>:</span>
                                        <strong id="exchangeRateDisplay"></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><?= __('exchanged_amount') ?>:</span>
                                        <strong id="exchangedAmount"></strong>
                                    </div>
                                    <div id="usdSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('paid_amount_usd') ?>:</span>
                                            <strong id="paidAmountUSD" class="text-success">USD 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('remaining_amount_usd') ?>:</span>
                                            <strong id="remainingAmountUSD" class="text-danger">USD 0.00</strong>
                                        </div>
                                    </div>
                                    <div id="afsSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('paid_amount_afs') ?>:</span>
                                            <strong id="paidAmountAFS" class="text-success">AFS 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('remaining_amount_afs') ?>:</span>
                                            <strong id="remainingAmountAFS" class="text-danger">AFS 0.00</strong>
                                        </div>
                                    </div>
                                    <div id="eurSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('paid_amount_eur') ?>:</span>
                                            <strong id="paidAmountEUR" class="text-success">EUR 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('remaining_amount_eur') ?>:</span>
                                            <strong id="remainingAmountEUR" class="text-danger">EUR 0.00</strong>
                                        </div>
                                    </div>
                                    <div id="aedSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('paid_amount_aed') ?>:</span>
                                            <strong id="paidAmountAED" class="text-success">AED 0.00</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span><?= __('remaining_amount_aed') ?>:</span>
                                            <strong id="remainingAmountAED" class="text-danger">AED 0.00</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transactions Table -->
                    <div class="card mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><?= __('transaction_history') ?></h6>
                            <button type="button" class="btn btn-sm btn-primary" data-toggle="collapse" data-target="#addTransactionForm">
                                <i class="feather icon-plus"></i> <?= __('new_transaction') ?>
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th><?= __('date') ?></th>
                                            <th><?= __('description') ?></th>
                                            <th><?= __('payment') ?></th>
                                            <th><?= __('amount') ?></th>
                                            <th><?= __('exchange_rate') ?></th>
                                            <th class="text-center"><?= __('actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="transactionTableBody">
                                        <!-- Transactions will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Add Transaction Form -->
                    <div id="addTransactionForm" class="collapse">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><?= __('add_new_transaction') ?></h6>
                            </div>
                            <div class="card-body">
                                <form id="hotelTransactionForm">
                                    <input type="hidden" id="booking_id" name="booking_id">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="paymentDate">
                                                    <i class="feather icon-calendar mr-1"></i><?= __('payment_date') ?>
                                                </label>
                                                <input type="date" class="form-control" id="paymentDate" name="payment_date" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="paymentTime">
                                                    <i class="feather icon-clock mr-1"></i><?= __('payment_time') ?>
                                                </label>
                                                <input type="time" class="form-control" id="paymentTime" name="payment_time" step="1" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="paymentAmount">
                                                    <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                                                </label>
                                                <input type="number" class="form-control" id="paymentAmount" 
                                                    name="payment_amount" step="0.01" min="0.01" required 
                                                    placeholder="Enter amount">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="paymentCurrency">
                                                    <i class="feather icon-dollar-sign mr-1"></i><?= __('currency') ?>
                                                </label>
                                                <select class="form-control" id="paymentCurrency" name="payment_currency" required>
                                                    <option value=""><?= __('select_currency') ?></option>
                                                    <option value="USD"><?= __('usd') ?></option>
                                                    <option value="AFS"><?= __('afs') ?></option>
                                                    <option value="EUR"><?= __('eur') ?></option>
                                                    <option value="DARHAM"><?= __('darham') ?></option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
   
                                    <div class="form-group" id="exchangeRateField" style="display: none;">
                                        <label for="transactionExchangeRate">
                                            <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                                        </label>
                                        <input type="number" class="form-control" id="transactionExchangeRate"
                                            name="exchange_rate" step="0.01" placeholder="Enter exchange rate">
                                    </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="paymentDescription">
                                            <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                        </label>
                                        <textarea class="form-control" id="paymentDescription" 
                                                name="payment_description" rows="2" required
                                                placeholder="Enter payment description"></textarea>
                                    </div>

                                    <div class="text-right mt-3">
                                        <button type="button" class="btn btn-secondary" data-toggle="collapse" 
                                                data-target="#addTransactionForm">
                                            <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="feather icon-check mr-1"></i><?= __('add_transaction') ?>
                                        </button>
                                    </div>
                                </form>
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

        <!-- Multiple Ticket Invoice Modal -->
        <div class="modal fade" id="multiTicketInvoiceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-file-text mr-2"></i><?= __('generate_combined_invoice') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <i class="feather icon-info mr-2"></i><?= __('select_multiple_tickets_to_generate_a_combined_invoice') ?>
                </div>
                
                <form id="multiTicketInvoiceForm">
                <div class="form-group">
                        <label for="clientFilter"><?= __('filter_by_client') ?></label>
                        <select class="form-control" id="clientFilter" name="clientFilter">
                            <option value=""><?= __('all_clients') ?></option>
                            <?php
                            // Fetch clients from database
                            $clientQuery = "SELECT DISTINCT c.name FROM clients c 
                                          INNER JOIN ticket_bookings t ON c.id = t.sold_to 
                                          WHERE t.tenant_id = $tenant_id
                                          ORDER BY c.name ASC";
                            $clientResult = $conn->query($clientQuery);
                            
                            if ($clientResult && $clientResult->num_rows > 0) {
                                while ($client = $clientResult->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($client['name']) . '">' . 
                                         htmlspecialchars($client['name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="clientForInvoice"><?= __('client') ?></label>
                        
                        <input type="text" class="form-control" id="clientForInvoice" name="clientForInvoice" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="invoiceComment"><?= __('comments_notes') ?></label>
                        <textarea class="form-control" id="invoiceComment" name="invoiceComment" rows="2"></textarea>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="ticketSelectionTable">
                            <thead class="thead-light">
                                <tr>
                                    <th width="40">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="selectAllTickets">
                                            <label class="custom-control-label" for="selectAllTickets"></label>
                                        </div>
                                    </th>
                                    <th><?= __('client') ?></th>
                                    <th><?= __('passenger') ?></th>
                                    <th><?= __('pnr') ?></th>
                                    <th><?= __('sector') ?></th>
                                    <th><?= __('flight') ?></th>
                                    <th><?= __('date') ?></th>
                                    <th><?= __('amount') ?></th>
                                </tr>
                            </thead>
                            <tbody id="ticketsForInvoiceBody">
                                <!-- Tickets will be loaded here dynamically -->
                            </tbody>
                            <tfoot>
                                <tr class="table-primary">
                                    <td colspan="7" class="text-right font-weight-bold"><?= __('total') ?>:</td>
                                    <td id="invoiceTotal" class="font-weight-bold">0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="form-group mt-3">
                        <label for="invoiceCurrency"><?= __('currency') ?></label>
                        <select class="form-control" id="invoiceCurrency" name="invoiceCurrency" required>
                            <option value=""><?= __('select_currency') ?></option>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                            <option value="EUR"><?= __('eur') ?></option>
                            <option value="DARHAM"><?= __('darham') ?></option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                <button type="button" class="btn btn-primary" id="generateCombinedInvoice">
                    <i class="feather icon-file-text mr-2"></i><?= __('generate_invoice') ?>
                </button>
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

    <!-- Ticket details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title">
                        <i class="feather icon-clipboard mr-2"></i><?= __('ticket_details') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <!-- Top Summary Card -->
                    <div class="bg-light p-4 border-bottom">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <div class="small text-muted mb-1"><?= __('sold_price') ?></div>
                                <h4 class="mb-0 text-primary" id="sold-price">-</h4>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="small text-muted mb-1"><?= __('base_price') ?></div>
                                <h4 class="mb-0 text-info" id="base-price">-</h4>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="small text-muted mb-1"><?= __('profit') ?></div>
                                <h4 class="mb-0 text-success" id="profit">-</h4>
                            </div>
                            
                        </div>
                    </div>

                    <!-- Tabs Navigation -->
                    <ul class="nav nav-pills nav-fill p-3" id="detailsTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="details-summary-tab" data-toggle="tab" href="#details-summary" role="tab">
                                <i class="feather icon-info mr-2"></i><?= __('summary') ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="details-description-tab" data-toggle="tab" href="#details-description" role="tab">
                                <i class="feather icon-file-text mr-2"></i><?= __('description') ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="details-refund-tab" data-toggle="tab" href="#details-refund" role="tab">
                                <i class="feather icon-refresh-ccw mr-2"></i><?= __('refund') ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="details-date-change-tab" data-toggle="tab" href="#details-date-change" role="tab">
                                <i class="feather icon-calendar mr-2"></i><?= __('date_change') ?>
                            </a>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content p-4">
                        <!-- Summary Tab -->
                        <div class="tab-pane fade show active" id="details-summary" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card border-0 shadow-sm mb-3">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-3 text-muted"><?= __('client_information') ?></h6>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="text-muted"><?= __('passenger_name') ?></span>
                                                <strong id="passenger-name">-</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="text-muted"><?= __('pnr') ?></span>
                                                <strong id="pnr">-</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="text-muted"><?= __('supplier') ?></span>
                                                <strong id="supplier-name">-</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="text-muted"><?= __('sold_to') ?></span>
                                                <strong id="sold-to">-</strong>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted"><?= __('paid_to') ?></span>
                                                <strong id="paid-to">-</strong>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted"><?= __('created_by') ?></span>
                                                <strong id="created-by">-</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-0 shadow-sm mb-3">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-3 text-muted"><?= __('additional_details') ?></h6>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="text-muted"><?= __('currency') ?></span>
                                                <strong id="currency">-</strong>
                                            </div>
                                            
                                            
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="text-muted"><?= __('phone') ?></span>
                                                <strong id="phone">-</strong>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted"><?= __('gender') ?></span>
                                                <strong id="gender">-</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Description Tab -->
                        <div class="tab-pane fade" id="details-description" role="tabpanel">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <p id="description" class="mb-0">-</p>
                                </div>
                            </div>
                        </div>

                        <!-- Refund Tab -->
                        <div class="tab-pane fade" id="details-refund" role="tabpanel">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted"><?= __('supplier_penalty') ?></span>
                                        <strong id="refund-supplier-penalty">-</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted"><?= __('service_penalty') ?></span>
                                        <strong id="refund-service-penalty">-</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted"><?= __('refund_to_passenger') ?></span>
                                        <strong id="refund-to-passenger">-</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted"><?= __('status') ?></span>
                                        <span id="refund-status" class="badge badge-pill badge-info">-</span>
                                    </div>
                                    <div class="mt-3">
                                        <h6 class="text-muted mb-2"><?= __('remarks') ?></h6>
                                        <p id="refund-remarks" class="mb-0">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Date Change Tab -->
                        <div class="tab-pane fade" id="details-date-change" role="tabpanel">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted"><?= __('new_departure_date') ?></span>
                                        <strong id="date-change-departure-date">-</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted"><?= __('currency') ?></span>
                                        <strong id="date-change-currency">-</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted"><?= __('supplier_penalty') ?></span>
                                        <strong id="date-change-supplier-penalty">-</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted"><?= __('service_penalty') ?></span>
                                        <strong id="date-change-service-penalty">-</strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted"><?= __('status') ?></span>
                                        <span id="date-change-status" class="badge badge-pill badge-info">-</span>
                                    </div>
                                    <div class="mt-3">
                                        <h6 class="text-muted mb-2"><?= __('remarks') ?></h6>
                                        <p id="date-change-remarks" class="mb-0">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-2"></i><?= __('close') ?>
                    </button>
                    <button type="button" class="btn btn-danger" id="refundBtn">
                        <i class="feather icon-refresh-ccw mr-2"></i><?= __('refund') ?>
                    </button>
                    <button type="button" class="btn btn-warning" id="dateChangeBtn">
                        <i class="feather icon-calendar mr-2"></i><?= __('date_change') ?>
                    </button>
                    <button type="button" class="btn btn-info" id="addWeightBtn" data-ticket-id="">
                        <i class="feather icon-package mr-2"></i><?= __('add_weight') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Weight Modal -->
    <div class="modal fade" id="addWeightModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-info text-white border-0">
                    <h5 class="modal-title">
                        <i class="feather icon-package mr-2"></i><?= __('add_weight') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="addWeightForm">
                    <div class="modal-body">
                        <!-- Passenger Information (Read-only) -->
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body bg-light">
                                <h6 class="card-subtitle mb-3 text-muted"><?= __('passenger_information') ?></h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="small text-muted"><?= __('passenger_name') ?></label>
                                            <p class="mb-0" id="weight-passenger-name">-</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="small text-muted"><?= __('pnr') ?></label>
                                            <p class="mb-0" id="weight-pnr">-</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Weight Details -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted"><?= __('weight_details') ?></h6>
                                <input type="hidden" name="ticket_id" id="weight-ticket-id">
                                
                                <div class="form-group">
                                    <label for="weight"><?= __('weight_kg') ?> <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="weight" name="weight" required step="0.01">
                                </div>

                                <div class="form-group">
                                    <label for="base-weight-price"><?= __('base_price') ?> <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="base-weight-price" name="base_price" required step="0.01">
                                </div>

                                <div class="form-group">
                                    <label for="sold-weight-price"><?= __('sold_price') ?> <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="sold-weight-price" name="sold_price" required step="0.01">
                                </div>

                                <div class="form-group">
                                    <label for="weight-profit"><?= __('profit') ?></label>
                                    <input type="number" class="form-control" id="weight-profit" readonly>
                                </div>

                                <div class="form-group mb-0">
                                    <label for="weight-remarks"><?= __('remarks') ?></label>
                                    <textarea class="form-control" id="weight-remarks" name="remarks" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                        </button>
                        <button type="submit" class="btn btn-info">
                            <i class="feather icon-save mr-2"></i><?= __('save') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Book Ticket Modal -->
    <div class="modal fade" id="bookTicketModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="feather icon-plus-circle mr-2"></i><?= __('book_a_ticket') ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form id="bookTicketForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <!-- Client and Trip Information -->
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><?= __('booking_details') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label for="supplier">
                                            <i class="feather icon-user mr-1"></i><?= __('supplier') ?>
                                        </label>
                                        <select class="form-control selectpicker" id="supplier" name="supplier" required 
                                                data-live-search="true" data-style="btn-light">
                                            <option value=""><?= __('select_supplier') ?></option>
                                            <?php foreach ($suppliers as $supplier): ?>
                                                <option value="<?= $supplier['id'] ?>" data-tokens="<?= $supplier['name'] ?>"><?= $supplier['name'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group col-md-4">
                                        <label for="soldTo">
                                            <i class="feather icon-users mr-1"></i><?= __('sold_to') ?>
                                        </label>
                                        <select class="form-control selectpicker" id="soldTo" name="soldTo" required 
                                                data-live-search="true" data-style="btn-light">
                                            <option value=""><?= __('select_client') ?></option>
                                            <?php 
                                            if ($conn->connect_error) {
                                                echo "<option value=''>Database connection failed</option>";
                                            } else {
                                                $result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM clients where status = 'active' AND tenant_id = $tenant_id");
                                                while ($row = $result->fetch_assoc()) {
                                                    echo "<option value='{$row['id']}' data-tokens='{$row['name']}'>{$row['name']}</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group col-md-4">
                                        <label for="tripType">
                                            <i class="feather icon-repeat mr-1"></i><?= __('trip_type') ?>
                                        </label>
                                        <select class="form-control selectpicker" id="tripType" name="tripType" required 
                                                data-style="btn-light">
                                            <option value="one_way"><?= __('one_way') ?></option>
                                            <option value="round_trip"><?= __('round_trip') ?></option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="col-md-12">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="adultCount">
                                                        <i class="feather icon-user mr-1"></i><?= __('adults') ?> (12+ <?= __('years') ?>)
                                                    </label>
                                                    <select class="form-control select2 passenger-count" id="adultCount" name="adultCount" required>
                                                        <?php for($i = 1; $i <= 9; $i++): ?>
                                                            <option value="<?= $i ?>"><?= $i ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="childCount">
                                                        <i class="feather icon-user mr-1"></i><?= __('children') ?> (2-11 <?= __('years') ?>)
                                                    </label>
                                                    <select class="form-control select2 passenger-count" id="childCount" name="childCount">
                                                        <?php for($i = 0; $i <= 9; $i++): ?>
                                                            <option value="<?= $i ?>"><?= $i ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="infantCount">
                                                        <i class="feather icon-user mr-1"></i><?= __('infants') ?> (0-2 <?= __('years') ?>)
                                                    </label>
                                                    <select class="form-control select2 passenger-count" id="infantCount" name="infantCount">
                                                        <?php for($i = 0; $i <= 9; $i++): ?>
                                                            <option value="<?= $i ?>"><?= $i ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Passenger Information Section -->
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><?= __('passenger_information') ?></h6>
                            </div>
                            <div class="card-body" id="passengersContainer">
                                <!-- Passenger details will be dynamically added here -->
                            </div>
                        </div>

                        <!-- Flight Details -->
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><?= __('flight_details') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label for="pnr">
                                            <i class="feather icon-hash mr-1"></i><?= __('pnr') ?>
                                        </label>
                                        <input type="text" class="form-control" id="pnr" name="pnr" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="origin">
                                            <i class="feather icon-map-pin mr-1"></i><?= __('from') ?>
                                        </label>
                                        <input type="text" class="form-control" id="origin" name="origin" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="destination">
                                            <i class="feather icon-map-pin mr-1"></i><?= __('to') ?>
                                        </label>
                                        <input type="text" class="form-control" id="destination" name="destination" required>
                                    </div>
                                    <div id="returnJourneyFields" class="form-group col-md-3" style="display: none;">
                                        <label for="returnDestination">
                                            <i class="feather icon-map-pin mr-1"></i><?= __('return_to') ?>
                                        </label>
                                        <input type="text" class="form-control" id="returnDestination" name="returnDestination">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label for="airline">
                                            <i class="feather icon-plane mr-1"></i><?= __('airline') ?>
                                        </label>
                                        <select class="form-control select2" id="airline" name="airline" required>
                                            <!-- Airlines will be populated by JavaScript -->
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label for="issueDate">
                                            <i class="feather icon-calendar mr-1"></i><?= __('issue_date') ?>
                                        </label>
                                        <input type="date" class="form-control" id="issueDate" name="issueDate" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="departureDate">
                                            <i class="feather icon-calendar mr-1"></i><?= __('departure_date') ?>
                                        </label>
                                        <input type="date" class="form-control" id="departureDate" name="departureDate" required>
                                    </div>
                                    <div id="returnDateField" class="form-group col-md-3" style="display: none;">
                                        <label for="returnDate">
                                            <i class="feather icon-calendar mr-1"></i><?= __('return_date') ?>
                                        </label>
                                        <input type="date" class="form-control" id="returnDate" name="returnDate">
                                    </div>
                                    
                                </div>
                            </div>
                        </div>

                        <!-- Payment Information -->
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><?= __('payment_information') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label for="curr">
                                            <i class="feather icon-dollar-sign mr-1"></i><?= __('currency') ?>
                                        </label>
                                        <input class="form-control" id="curr" name="curr" required readonly>
                                    </div>
                                    <div class="form-group col-md-8">
                                        <label for="paidTo">
                                            <i class="feather icon-credit-card mr-1"></i><?= __('paid_to') ?>
                                        </label>
                                        <select class="form-control select2" id="paidTo" name="paidTo" required>
                                            <option value=""><?= __('select_main_account') ?></option>
                                            <?php
                                            if ($conn->connect_error) {
                                                echo "<option value=''>Database connection failed</option>";
                                            } else {
                                                $result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM main_account where status = 'active' AND tenant_id = $tenant_id");
                                                while ($row = $result->fetch_assoc()) {
                                                    echo "<option value='{$row['id']}'>{$row['name']}</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            
                                <!-- Payment Totals Section -->
                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label for="base">
                                            <i class="feather icon-dollar-sign mr-1"></i><?= __('base') ?>
                                        </label>
                                        <input type="number" class="form-control" id="base" name="base" step="any" readonly>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="sold">
                                            <i class="feather icon-dollar-sign mr-1"></i><?= __('sold') ?>
                                        </label>
                                        <input type="number" class="form-control" id="sold" name="sold" step="any" readonly>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="discount">
                                            <i class="feather icon-minus-circle mr-1"></i><?= __('discount') ?>
                                        </label>
                                        <input type="number" class="form-control" id="discount" name="discount" value="0" step="any" readonly>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="pro">
                                            <i class="feather icon-plus-circle mr-1"></i><?= __('profit') ?>
                                        </label>
                                        <input type="number" class="form-control" id="pro" name="pro" readonly>
                                    </div>
                                </div>
                            
                                <div class="form-row">
                                    <div class="form-group col-md-12">
                                        <label for="description">
                                            <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                        </label>
                                        <input type="text" class="form-control" id="description" name="description">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="feather icon-x mr-2"></i><?= __('close') ?>
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-check mr-2"></i><?= __('book') ?>
                        </button>
                    </div>
                </form>
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
                        <h5 class="mt-3 mb-1"><?= !empty($user['name']) ? htmlspecialchars($user['name']) : 'Guest' ?></h5>
                        <p class="text-muted mb-0"><?= !empty($user['role']) ? htmlspecialchars($user['role']) : 'User' ?></p>
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


        <!-- Date Change Modal -->
        <div class="modal fade" id="dateChangeModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= __('date_change') ?></h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <form id="dateChangeForm">
                        <div class="modal-body">
                            <input type="hidden" id="dateChangeTicketId" name="ticketId">
                            <input type="hidden" name="status" value="Date Changed">

                            <div class="form-group">
                                <label for="dateChangeSold"><?= __('sold_price') ?></label>
                                <input type="number" step="any" class="form-control" id="dateChangeSold" name="sold" required>
                            </div>

                            <div class="form-group">
                                <label for="dateChangeBase"><?= __('base_price') ?></label>
                                <input type="number" step="any" class="form-control" id="dateChangeBase" name="base" required>
                            </div>
                            <div class="form-group">
                                <label for="supplierPenalty"><?= __('supplier_penalty') ?></label>
                                <input type="number" step="any" class="form-control" id="supplierPenalty" name="supplier_penalty" required>
                                <small class="form-text text-muted">
                                    <?= __('penalty_charged_by_the_supplier_deducted_from_the_base_price') ?>
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="servicePenalty"><?= __('our_service_penalty') ?></label>
                                <input type="number" step="any" class="form-control" id="servicePenalty" name="service_penalty" required>
                                <small class="form-text text-muted">
                                    <?= __('penalty_charged_by_us_independent_of_supplier_penalties') ?>
                                </small>
                            </div>


                            <div class="form-group">
                                <label for="dateChangeDepartureDate"><?= __('new_departure_date') ?></label>
                                <input type="date" class="form-control" id="dateChangeDepartureDate" name="departureDate" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="dateChangeDescription"><?= __('description') ?></label>
                                <textarea class="form-control" id="dateChangeDescription" name="description" rows="3" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                            <button type="submit" class="btn btn-primary"><?= __('submit') ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <!-- Refund Modal -->
    <div class="modal fade" id="refundModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('refund') ?></h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form id="refundForm">
                    <div class="modal-body">
                        <input type="hidden" id="refundTicketId" name="ticketId">
                        <input type="hidden" name="status" value="Refunded">

                        <div class="form-group">
                            <label for="refundSold"><?= __('sold_price') ?></label>
                            <input type="number" step="any" class="form-control" id="refundSold" name="sold" required>
                        </div>

                        <div class="form-group">
                            <label for="refundBase"><?= __('base_price') ?></label>
                            <input type="number" step="any" class="form-control" id="refundBase" name="base" required>
                        </div>

                        <div class="form-group">
                            <label><?= __('calculation_method') ?></label>
                            <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                                <label class="btn btn-outline-primary active">
                                    <input type="radio" name="calculationMethod" id="calcFromBase" value="base" checked> Calculate from Base
                                </label>
                                <label class="btn btn-outline-primary">
                                    <input type="radio" name="calculationMethod" id="calcFromSold" value="sold"> Calculate from Sold
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="supplierPenalty"><?= __('supplier_penalty') ?></label>
                            <input type="number" step="any" class="form-control" id="supplierRefundPenalty" name="supplier_penalty" required>
                            <small class="form-text text-muted">
                                <?= __('penalty_charged_by_the_supplier_deducted_from_the_base_price') ?>
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="servicePenalty"><?= __('our_service_penalty') ?></label>
                            <input type="number" step="any" class="form-control" id="serviceRefundPenalty" name="service_penalty" required>
                            <small class="form-text text-muted">
                                <?= __('penalty_charged_by_us_independent_of_supplier_penalties') ?>
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="refundAmount"><?= __('refund_amount') ?></label>
                            <input type="number" step="any" class="form-control" id="refundAmount" name="refund" readonly>
                            <small class="form-text text-muted">
                                <?= __('the_amount_the_passenger_will_be_refunded_calculated_automatically') ?>
                            </small>
                        </div>

                        

                        <div class="form-group">
                            <label for="refundDescription"><?= __('description') ?></label>
                            <textarea class="form-control" id="refundDescription" name="description" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                        <button type="submit" class="btn btn-primary"><?= __('submit') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit ticket modal -->
    <div class="modal fade" id="editTicketModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div id="editLoader" style="display: none; text-align: center;">
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...
            </div>

            <form id="editTicketForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= __('edit_ticket') ?></h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editTicketId" name="id">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="supplier"><?= __('supplier') ?></label>
                            <select class="form-control" id="editSupplier" name="supplier" required readonly>
                                <option value=""><?= __('select_supplier') ?></option>
                                <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>"><?= $supplier['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="editSoldTo"><?= __('sold_to') ?></label>
                            <select class="form-control" id="editSoldTo" name="soldTo" required readonly>
                                <option value=""><?= __('select_client') ?></option>
                                <?php 
                                // Fetch main accounts from the database
                                if ($conn->connect_error) {
                                    echo "<option value=''>Database connection failed</option>";
                                } else {
                                    $result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM clients where status = 'active' AND tenant_id = $tenant_id");
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<option value='{$row['id']}'>
                                                {$row['name']}
                                            </option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="editTripType"><?= __('trip_type') ?></label>
                            <select class="form-control" id="editTripType" name="tripType" required>
                                <option value="one_way"><?= __('one_way') ?></option>
                                <option value="round_trip"><?= __('round_trip') ?></option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="editTitle"><?= __('title') ?></label>
                            <select class="form-control" id="editTitle" name="title" required>
                                <option value="Mr"><?= __('mr') ?></option>
                                <option value="Mrs"><?= __('mrs') ?></option>
                                <option value="Child"><?= __('child') ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="editGender"><?= __('gender') ?></label>
                            <select class="form-control" id="editGender" name="gender" required>
                                <option value="Male"><?= __('male') ?></option>
                                <option value="Female"><?= __('female') ?></option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="editPassengerName"><?= __('passenger_name') ?></label>
                            <input type="text" class="form-control" id="editPassengerName" name="passengerName" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="editPhone"><?= __('phone') ?></label>
                            <input type="text" class="form-control" id="editPhone" name="phone" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="editPnr"><?= __('pnr') ?></label>
                            <input type="text" class="form-control" id="editPnr" name="pnr" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="editOrigin"><?= __('from') ?></label>
                            <input type="text" class="form-control" id="editOrigin" name="origin" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="editDestination"><?= __('to') ?></label>
                            <input type="text" class="form-control" id="editDestination" name="destination" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="editAirline"><?= __('airline') ?></label>
                            <select class="form-control" id="editAirline" name="airline" required>
                                <!-- Airline options go here -->
                                
                            </select>
                        </div>
                        <div id="editReturnJourneyFields" style="display: none;">
                            <div class="form-group col-md-3">
                                <label for="editReturnDestination"><?= __('return_to') ?></label>
                                <input type="text" class="form-control" id="editReturnDestination" name="returnDestination">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="editIssueDate"><?= __('issue_date') ?></label>
                            <input type="date" class="form-control" id="editIssueDate" name="issueDate" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="editDepartureDate"><?= __('departure_date') ?></label>
                            <input type="date" class="form-control" id="editDepartureDate" name="departureDate" required>
                        </div>
                        <div id="editReturnDateField" class="form-group col-md-3" style="display: none;">
                            <label for="editReturnDate"><?= __('return_date') ?></label>
                            <input type="date" class="form-control" id="editReturnDate" name="returnDate">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="editBase"><?= __('base') ?></label>
                            <input type="number" class="form-control" id="editBase" name="base" step="any" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="editSold"><?= __('sold') ?></label>
                            <input type="number" class="form-control" id="editSold" name="sold" step="any" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="editDiscount"><?= __('discount') ?></label>
                            <input type="number" class="form-control" id="editDiscount" name="discount" step="any" value="0">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="editPro"><?= __('profit') ?></label>
                            <input type="number" class="form-control" id="editPro" name="pro" step="any" required readonly>
                        </div>
                        
                        <div class="form-group col-md-3">
                            <label for="editCurr"><?= __('currency') ?></label>
                            <input class="form-control" id="editCurr" name="curr" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="editPaidTo"><?= __('paid_to') ?></label>
                            <select class="form-control" id="editPaidTo" name="paidTo" required readonly>
                                <option value=""><?= __('select_main_account') ?></option>
                                <?php 
                                // Fetch main accounts from the database
                                if ($conn->connect_error) {
                                    echo "<option value=''>Database connection failed</option>";
                                } else {
                                    $result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM main_account where status = 'active' AND tenant_id = $tenant_id");
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<option value='{$row['id']}'>
                                                {$row['name']}
                                            </option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-12">
                            <label for="editDescription"><?= __('description') ?></label>
                            <input type="text" class="form-control" id="editDescription" name="description">
                        </div>
                    </div>

                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                    <button type="submit" class="btn btn-primary"><?= __('save_changes') ?></button>
                </div>
            </form>
        </div>
    </div>
    


                                    <!-- Required Js -->
                                    <script src="../assets/js/vendor-all.min.js"></script>
                                    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
                                    <script src="../assets/js/pcoded.min.js"></script>
                                    <!-- Add Bootstrap-select JavaScript -->
                                    <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>
                                    <script src="js/ticket/profit-calc.js"></script>
                                    <script src="js/ticket/ticket-details.js"></script>
                                    <script src="js/ticket/ticket-form.js"></script>
                                    <script src="js/ticket/supplier-currency.js"></script>
                                    <script src="js/ticket/delete-ticket.js"></script>
                                    <script src="js/ticket/weight-management.js"></script>
                                    <script src="js/ticket/refund-calc.js"></script>
                                    <script src="js/ticket/generate-invoice.js"></script>
                                    <script src="js/ticket/search.js"></script>
                                    <script src="js/ticket/transaction-manager.js"></script>
                                    <script src="js/ticket/trip-type.js"></script>
                                    <script src="js/ticket/payment-calculation.js"></script>
                                    <script src="js/ticket/passenger-count.js"></script>
                                    <script src="js/ticket/supplier-currency-select.js"></script>
                                    <script src="js/ticket/edit-ticket.js"></script>
                                    <script src="js/ticket/passenger-management.js"></script>
                                    <script src="js/ticket/data/airlines.js"></script>
                                    <script src="js/ticket/airline-select.js"></script>
                                    <script src="js/ticket/multi-ticket-invoice.js"></script>

    <script>
    $(document).ready(function() {
        // Function to calculate totals
        function calculateTotals() {
            let totalBase = 0;
            let totalSold = 0;
            let totalDiscount = 0; // Initialize total discount to 0
            let totalProfit = 0;

            // Sum up all passenger amounts
            $('.passenger-info').each(function() {
                const base = parseFloat($(this).find('.base-amount').val()) || 0;
                const sold = parseFloat($(this).find('.sold-amount').val()) || 0;
                const passengerDiscount = parseFloat($(this).find('.discount-amount').val()) || 0;
                const passengerProfit = sold - base - passengerDiscount;

                // Update individual passenger profit
                $(this).find('.profit-amount').val(passengerProfit.toFixed(2));

                // Add to totals
                totalBase += base;
                totalSold += sold;
                totalDiscount += passengerDiscount; // Sum up passenger discounts
            });

            // Calculate total profit
            totalProfit = totalSold - totalBase - totalDiscount;

            // Update total fields
            $('#base').val(totalBase.toFixed(2));
            $('#sold').val(totalSold.toFixed(2));
            $('#discount').val(totalDiscount.toFixed(2)); // Update total discount field
            $('#pro').val(totalProfit.toFixed(2));
        }

        // Function to create passenger form fields
        function createPassengerFields(type, index, count) {
            let titles = type === 'infant' ? ['Infant'] : 
                       type === 'child' ? ['Child'] : 
                       ['Mr', 'Mrs', 'Ms'];
            
            let html = `
                <div class="passenger-info ${type}-passenger" data-passenger="${index}">
                    <h6 class="border-bottom pb-2 mb-3">${type.charAt(0).toUpperCase() + type.slice(1)} Passenger ${count}</h6>
                    <div class="form-row mb-3">
                        <div class="form-group col-md-2 mb-0">
                            <label for="title_${index}"><?= __('title') ?></label>
                            <select class="form-control" id="title_${index}" name="passengers[${index}][title]" required>
                                ${titles.map(title => `<option value="${title}">${title}</option>`).join('')}
                            </select>
                        </div>
                        <div class="form-group col-md-2 mb-0">
                            <label for="gender_${index}"><?= __('gender') ?></label>
                            <select class="form-control" id="gender_${index}" name="passengers[${index}][gender]" required>
                                <option value="Male"><?= __('male') ?></option>
                                <option value="Female"><?= __('female') ?></option>
                            </select>
                        </div>
                        <div class="form-group col-md-5 mb-0">
                            <label for="passengerName_${index}"><?= __('passenger_name') ?></label>
                            <input type="text" class="form-control" id="passengerName_${index}" name="passengers[${index}][name]" required>
                        </div>
                        <div class="form-group col-md-3 mb-0">
                            <label for="phone_${index}"><?= __('phone') ?></label>
                            <input type="text" class="form-control" id="phone_${index}" name="passengers[${index}][phone]" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-3 mb-0">
                            <label for="base_${index}">
                                <i class="feather icon-dollar-sign mr-1"></i><?= __('base_amount') ?>
                            </label>
                            <input type="number" class="form-control base-amount" id="base_${index}" name="passengers[${index}][base]" step="any" required>
                        </div>
                        <div class="form-group col-md-3 mb-0">
                            <label for="sold_${index}">
                                <i class="feather icon-dollar-sign mr-1"></i><?= __('sold_amount') ?>
                            </label>
                            <input type="number" class="form-control sold-amount" id="sold_${index}" name="passengers[${index}][sold]" step="any" required>
                        </div>
                        <div class="form-group col-md-3 mb-0">
                            <label for="discount_${index}">
                                <i class="feather icon-minus-circle mr-1"></i><?= __('discount') ?>
                            </label>
                            <input type="number" class="form-control discount-amount" id="discount_${index}" name="passengers[${index}][discount]" value="0" step="any">
                        </div>
                        <div class="form-group col-md-3 mb-0">
                            <label for="profit_${index}">
                                <i class="feather icon-plus-circle mr-1"></i><?= __('profit') ?>
                            </label>
                            <input type="number" class="form-control profit-amount" id="profit_${index}" name="passengers[${index}][profit]" step="any" readonly>
                        </div>
                    </div>
                </div>
            `;
            return html;
        }

        // Function to update passenger fields
        function updatePassengerFields() {
            let adultCount = parseInt($('#adultCount').val()) || 0;
            let childCount = parseInt($('#childCount').val()) || 0;
            let infantCount = parseInt($('#infantCount').val()) || 0;
            
            let container = $('#passengersContainer');
            container.empty();
            
            let index = 1;
            
            // Add adult passengers
            for(let i = 0; i < adultCount; i++) {
                container.append(createPassengerFields('adult', index, i + 1));
                if (i < adultCount - 1) {
                    container.append('<hr>');
                }
                index++;
            }
            
            // Add child passengers
            if (childCount > 0 && adultCount > 0) {
                container.append('<hr>');
            }
            for(let i = 0; i < childCount; i++) {
                container.append(createPassengerFields('child', index, i + 1));
                if (i < childCount - 1) {
                    container.append('<hr>');
                }
                index++;
            }
            
            // Add infant passengers
            if (infantCount > 0 && (adultCount > 0 || childCount > 0)) {
                container.append('<hr>');
            }
            for(let i = 0; i < infantCount; i++) {
                container.append(createPassengerFields('infant', index, i + 1));
                if (i < infantCount - 1) {
                    container.append('<hr>');
                }
                index++;
            }

            // Add event listeners for calculation
            $('.base-amount, .sold-amount, .discount-amount').on('input', calculateTotals);
        }

        // Event listeners
        $('.passenger-count').change(updatePassengerFields);
        
        // Initial setup
        updatePassengerFields();
    });
    </script>


<script>
// Toast notification system
const toastConfig = {
    duration: 4000,      // Display duration in ms
    animationDuration: 300,  // Animation duration in ms
    maxToasts: 3        // Maximum number of toasts to show at once
};

// Collection to track active toasts
let activeToasts = [];

/**
 * Show a toast notification
 * @param {string} message - The message to display
 * @param {string} type - Type of toast (success, error, warning, info)
 * @param {object} options - Optional configuration overrides
 */
function showToast(message, type = 'success', options = {}) {
    const config = { ...toastConfig, ...options };
    
    // Create the toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    // Set icon based on type
    let icon = 'check-circle';
    switch(type) {
        case 'error':
            icon = 'alert-circle';
            break;
        case 'warning':
            icon = 'alert-triangle';
            break;
        case 'info':
            icon = 'info';
            break;
    }
    
    // Set toast content
    toast.innerHTML = `
        <div class="toast-title">
            <i class="feather icon-${icon} mr-2"></i>
            ${type.charAt(0).toUpperCase() + type.slice(1)}
        </div>
        <div class="toast-message">${message}</div>
    `;
    
    // Manage toast collection
    if (activeToasts.length >= toastConfig.maxToasts) {
        const oldestToast = activeToasts.shift();
        if (oldestToast && oldestToast.parentNode) {
            oldestToast.classList.add('toast-removing');
            setTimeout(() => oldestToast.remove(), config.animationDuration);
        }
    }
    
    // Add toast to container
    const container = document.querySelector('.toast-container');
    container.appendChild(toast);
    activeToasts.push(toast);
    
    // Trigger animation
    requestAnimationFrame(() => toast.classList.add('toast-showing'));
    
    // Auto dismiss
    setTimeout(() => {
        toast.classList.add('toast-removing');
        setTimeout(() => {
            toast.remove();
            activeToasts = activeToasts.filter(t => t !== toast);
        }, config.animationDuration);
    }, config.duration);
    
    return toast;
}

// Convert all alerts to toasts
document.addEventListener('DOMContentLoaded', function() {
    // Success alerts
    document.querySelectorAll('.alert-success').forEach(alert => {
        const message = alert.textContent.trim();
        showToast(message, 'success');
        alert.remove();
    });
    
    // Error alerts
    document.querySelectorAll('.alert-danger').forEach(alert => {
        const message = alert.textContent.trim();
        showToast(message, 'error');
        alert.remove();
    });
    
    // Warning alerts
    document.querySelectorAll('.alert-warning').forEach(alert => {
        const message = alert.textContent.trim();
        showToast(message, 'warning');
        alert.remove();
    });
});

// Replace all existing alert() calls with toast notifications
window.oldAlert = window.alert;
window.alert = function(message) {
    showToast(message, 'info');
};
</script>

</body>
</html>