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
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');
require_once '../includes/conn.php';

// Get the user ID from the session
$user_id = $_SESSION["user_id"];
$tenant_id = $_SESSION['tenant_id'];
// Query to fetch ticket weights with related information
$weightsQuery = "
    SELECT 
        tw.*,
        t.passenger_name,
        t.pnr,
        t.phone,
        t.airline,
        t.origin,
        t.destination,
        t.departure_date,
        t.currency,
        s.name AS supplier_name,
        c.name AS sold_to_name
    FROM 
        ticket_weights tw
    LEFT JOIN 
        ticket_bookings t ON tw.ticket_id = t.id
    LEFT JOIN 
        suppliers s ON t.supplier = s.id
    LEFT JOIN 
        clients c ON t.sold_to = c.id
    WHERE
        tw.tenant_id = $tenant_id
    ORDER BY 
        tw.created_at DESC
";

$weightsResult = $conn->query($weightsQuery);

// Initialize the array to hold weight details
$weights = [];

if ($weightsResult && $weightsResult->num_rows > 0) {
    while ($row = $weightsResult->fetch_assoc()) {
        $weights[] = $row;
    }
}

?>


    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="css/ticket_styles.css">
    <link rel="stylesheet" href="css/ticket-components.css">
    <link rel="stylesheet" href="css/modal-styles.css">
    <link rel="stylesheet" href="css/ticket-form.css">
    <link rel="stylesheet" href="css/ticket_weight.css">

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <!-- [ Table ] start -->
                                <div class="col-sm-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <div class="row align-items-center">
                                                <div class="col">
                                                    <h5><?= __('ticket_weights_management') ?></h5>
                                                </div>
                                                <div class="col text-right">
                                                     <button type="button" class="btn btn-success" id="generateInvoiceBtn" style="display: none;">
                                                         <i class="feather icon-file-text mr-2"></i>Generate Invoice
                                                     </button>
                                                     <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addTransactionModal">
                                                         <i class="feather icon-plus mr-2"></i><?= __('add_weight') ?>
                                                     </button>
                                                 </div>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-hover mb-0" id="weightsTable">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center">
                                                                <input type="checkbox" id="selectAllWeights">
                                                            </th>
                                                            <th><?= __('passenger') ?></th>
                                                            <th><?= __('flight_details') ?></th>
                                                            <th><?= __('weight_details') ?></th>
                                                            <th><?= __('financial_details') ?></th>
                                                            <th><?= __('date_added') ?></th>
                                                            <th><?= __('payment_status') ?></th>
                                                            <th class="text-right no-sort"><?= __('actions') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="ticketTable">
                                                        <?php foreach ($weights as $weight): ?>
                                                        <tr>
                                                            <td class="text-center">
                                                                <input type="checkbox" class="weight-checkbox" value="<?= $weight['id'] ?>">
                                                            </td>
                                                            <td>
                                                                <div class="passenger-info">
                                                                    
                                                                    <div class="passenger-info__details">
                                                                        <div class="passenger-info__name">
                                                                         <?= htmlspecialchars($weight['passenger_name']) ?>
                                                                        </div>
                                                                        <div class="passenger-info__pnr">
                                                                            PNR: <?= htmlspecialchars($weight['pnr']) ?>
                                                                            <br>
                                                                            <?= __('phone') ?>: <?= htmlspecialchars($weight['phone']) ?>
                                                                            <br>
                                                                            <?= __('created_by') ?>: <?= htmlspecialchars($weight['created_by']) ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="flight-info">
                                                                    <div class="flight-info__segment">
                                                                        <div class="flight-info__city">
                                                                            <?= htmlspecialchars($weight['origin']) ?> - <?= htmlspecialchars($weight['destination']) ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="weight-info">
                                                                    <div class="weight-info__weight">
                                                                        <?= number_format($weight['weight'], 2) ?> kg
                                                                    </div>
                                                                    <?php if (!empty($weight['remarks'])): ?>
                                                                    <div class="weight-info__remarks">
                                                                        <?= htmlspecialchars($weight['remarks']) ?>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($weight['exchange_rate']) || !empty($weight['market_exchange_rate'])): ?>
                                                                    <div class="weight-info__exchange-rate">
                                                                        <?= __('rate') ?>: <?= number_format($weight['exchange_rate'], 2) ?>
                                                                    </div>
                                                                    <?php if (!empty($weight['market_exchange_rate'])): ?>
                                                                    <div class="weight-info__market-exchange-rate">
                                                                        <?= __('market') ?>: <?= number_format($weight['market_exchange_rate'], 2) ?>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="financial-info">
                                                                    <div class="financial-info__amount">
                                                                        <?= htmlspecialchars($weight['currency']) ?> <?= number_format($weight['sold_price'], 2) ?>
                                                                    </div>
                                                                    <div class="financial-info__base-price">
                                                                        <?= __('base') ?>: <?= htmlspecialchars($weight['currency']) ?> <?= number_format($weight['base_price'], 2) ?>
                                                                    </div>
                                                                    <div class="financial-info__profit">
                                                                        <?= __('profit') ?>: <?= htmlspecialchars($weight['currency']) ?> <?= number_format($weight['profit'], 2) ?>
                                                                    </div>
                                                                    
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <?= date('d M Y H:i', strtotime($weight['created_at'])) ?>
                                                                <br>
                                                                <?= __('created_by') ?>: <?= htmlspecialchars($weight['created_by']) ?>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                // Get client type from clients table
                                                                $soldTo = $weight['sold_to_name'];
                                                                $isAgencyClient = false;

                                                                // Check client type
                                                                $clientQuery = $conn->query("SELECT client_type FROM clients WHERE tenant_id = $tenant_id AND name = '$soldTo'");
                                                                if ($clientQuery && $clientQuery->num_rows > 0) {
                                                                    $clientRow = $clientQuery->fetch_assoc();
                                                                    $isAgencyClient = ($clientRow['client_type'] === 'agency');
                                                                }

                                                                if ($isAgencyClient) {
                                                                    $baseCurrency = $weight['currency']; // Base currency of the weight
                                                                    $soldAmount = floatval($weight['sold_price']);
                                                                    $totalPaidInBase = 0.0;

                                                                    $weightId = $weight['id'];

                                                                    // Fetch transactions
                                                                    $transactionQuery = $conn->query("SELECT * FROM main_account_transactions 
                                                                        WHERE transaction_of = 'weight' 
                                                                        AND reference_id = '$weightId' 
                                                                        AND tenant_id = $tenant_id");

                                                                    if ($transactionQuery && $transactionQuery->num_rows > 0) {
                                                                        while ($transaction = $transactionQuery->fetch_assoc()) {
                                                                            $amount = floatval($transaction['amount']);
                                                                            $transCurrency = $transaction['currency'];
                                                                            $transExchangeRate = isset($transaction['exchange_rate']) && $transaction['exchange_rate'] > 0 
                                                                                                ? floatval($transaction['exchange_rate']) 
                                                                                                : 1.0;

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

                                                                    // Payment status icon
                                                                    if ($totalPaidInBase <= 0) {
                                                                        echo '<i class="fas fa-circle text-danger" title="No payment received"></i>';
                                                                    } elseif ($totalPaidInBase < $soldAmount) {
                                                                        $percentage = round(($totalPaidInBase / $soldAmount) * 100);
                                                                        echo '<i class="fas fa-circle text-warning" style="color: #ffc107 !important;"
                                                                            title="Partial payment: ' . $baseCurrency . ' ' . number_format($totalPaidInBase, 2) . ' / ' . 
                                                                            $baseCurrency . ' ' . number_format($soldAmount, 2) . ' (' . $percentage . '%)"></i>';
                                                                    } elseif (abs($totalPaidInBase - $soldAmount) < 0.01) {
                                                                        echo '<i class="fas fa-circle text-success" title="Fully paid"></i>';
                                                                    } else {
                                                                        echo '<i class="fas fa-circle text-success"
                                                                            title="Fully paid (overpaid by ' . $baseCurrency . ' ' . number_format($totalPaidInBase - $soldAmount, 2) . ')"></i>';
                                                                    }
                                                                } else {
                                                                    echo '<i class="fas fa-minus text-muted" title="Not an agency client"></i>';
                                                                }
                                                                ?>
                                                            </td>

                                                            <td class="text-right">
                                                                <?php if ($isAgencyClient): ?>
                                                                <button class="btn btn-icon btn-sm btn-primary" onclick="manageTransactions(<?= $weight['id'] ?>)" title="<?= __('manage_transactions') ?>">
                                                                    <i class="feather icon-credit-card"></i>
                                                                </button>
                                                                <?php endif; ?>
                                                                <button class="btn btn-icon btn-sm btn-info" onclick="editWeight(<?= $weight['id'] ?>)" title="<?= __('edit_weight') ?>">
                                                                    <i class="feather icon-edit"></i>
                                                                </button>
                                                                <button class="btn btn-icon btn-sm btn-danger" onclick="deleteWeight(<?= $weight['id'] ?>)" title="<?= __('delete_weight') ?>">
                                                                    <i class="feather icon-trash-2"></i>
                                                                </button>
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

    <!-- Edit Weight Modal -->
    <div class="modal fade" id="editWeightModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="feather icon-edit-2 mr-2"></i><?= __('edit_weight') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form id="editWeightForm">
                    <div class="modal-body">
                        <input type="hidden" id="editWeightId" name="weight_id">
                        
                        <div class="form-group">
                            <label for="editWeight"><?= __('weight_kg') ?></label>
                            <input type="number" class="form-control" id="editWeight" name="weight" step="0.01" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editBasePrice"><?= __('base_price') ?></label>
                                    <input type="number" class="form-control" id="editBasePrice" name="base_price" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editSoldPrice"><?= __('sold_price') ?></label>
                                    <input type="number" class="form-control" id="editSoldPrice" name="sold_price" step="0.01" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="editProfit"><?= __('profit') ?></label>
                            <input type="number" class="form-control" id="editProfit" readonly>
                        </div>

                        <div class="form-group">
                            <label for="editRemarks"><?= __('remarks') ?></label>
                            <textarea class="form-control" id="editRemarks" name="remarks" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                        </button>
                        <button type="submit" class="btn btn-info">
                            <i class="feather icon-save mr-2"></i><?= __('save_changes') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Ticket weight Modal -->
    <div class="modal fade" id="addTransactionModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="feather icon-plus mr-2"></i><?= __('add_weight') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form id="addTransactionForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="searchPNR"><?= __('search_by_pnr') ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="searchPNR" placeholder="<?= __('enter_pnr') ?>">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button" id="searchPNRBtn">
                                                <i class="feather icon-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="searchPassenger"><?= __('search_by_passenger') ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="searchPassenger" placeholder="<?= __('enter_passenger_name') ?>">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button" id="searchPassengerBtn">
                                                <i class="feather icon-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive mt-3" id="searchResultsContainer" style="display: none;">
                            <table class="table table-hover" id="searchResultsTable">
                                <thead>
                                    <tr>
                                        <th><?= __('passenger') ?></th>
                                        <th><?= __('pnr') ?></th>
                                        <th><?= __('flight_details') ?></th>
                                        <th><?= __('date') ?></th>
                                        <th><?= __('action') ?></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div id="weightDetailsContainer" style="display: none;">
                            <hr>
                            <h6 class="mb-3"><?= __('weight_details') ?></h6>
                            
                            <input type="hidden" id="selectedTicketId" name="ticket_id">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="weight"><?= __('weight_kg') ?> <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="weight" name="weight" step="0.01" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="basePrice"><?= __('base_price') ?> <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="basePrice" name="base_price" step="0.01" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="soldPrice"><?= __('sold_price') ?> <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="soldPrice" name="sold_price" step="0.01" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="profit"><?= __('profit') ?></label>
                                        <input type="number" class="form-control" id="profit" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="remarks"><?= __('remarks') ?></label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                        </button>
                        <button type="submit" class="btn btn-primary" id="saveTransactionBtn" style="display: none;">
                            <i class="feather icon-save mr-2"></i><?= __('save_transaction') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
                <div class="modal-body" style="max-height: 80vh; overflow-y: auto; padding: 1.5rem;">
                    <!-- Weight Info Card -->
                    <div class="card mb-4 border-primary shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 text-primary">
                                <i class="feather icon-info mr-2"></i><?= __('weight_details') ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="media">
                                        <div class="media-body">
                                            <h6 class="mt-0 mb-1 text-muted"><?= __('passenger_information') ?></h6>
                                            <p class="mb-1"><strong class="text-dark"><?= __('passenger') ?>:</strong> <span id="trans-passenger-name" class="text-primary">Loading...</span></p>
                                            <p class="mb-1"><strong class="text-dark"><?= __('pnr') ?>:</strong> <span id="trans-pnr" class="text-primary">Loading...</span></p>
                                            <p class="mb-0"><strong class="text-dark"><?= __('weight') ?>:</strong> <span id="trans-weight" class="text-primary">Loading...</span></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card bg-light border-0">
                                                <div class="card-body p-3">
                                                    <h6 class="card-title text-muted mb-2">
                                                        <i class="feather icon-dollar-sign mr-1"></i><?= __('total_amount') ?>
                                                    </h6>
                                                    <h4 class="mb-0 text-primary" id="totalAmount">Loading...</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card bg-light border-0">
                                                <div class="card-body p-3">
                                                    <h6 class="card-title text-muted mb-2">
                                                        <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                                                    </h6>
                                                    <h5 class="mb-0 text-info" id="exchangeRateDisplay">Loading...</h5>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <h6 class="text-muted mb-2">
                                            <i class="feather icon-trending-up mr-1"></i><?= __('exchanged_amount') ?>
                                        </h6>
                                        <p class="mb-0 text-success font-weight-bold" id="exchangedAmount">Loading...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Status Cards -->
                    <div class="row mb-4" id="paymentStatusContainer" style="display: none;">
                        <div class="col-12">
                            <h6 class="mb-3 text-muted">
                                <i class="feather icon-bar-chart-2 mr-2"></i><?= __('payment_status') ?>
                            </h6>
                        </div>
                        <div class="col-md-3" id="usdSection" style="display: none;">
                            <div class="card border-success">
                                <div class="card-body text-center p-3">
                                    <h6 class="card-title text-success mb-2">USD</h6>
                                    <div class="mb-2">
                                        <small class="text-muted d-block"><?= __('paid') ?>:</small>
                                        <strong id="paidAmountUSD" class="text-success">USD 0.00</strong>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block"><?= __('remaining') ?>:</small>
                                        <strong id="remainingAmountUSD" class="text-danger">USD 0.00</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3" id="afsSection" style="display: none;">
                            <div class="card border-success">
                                <div class="card-body text-center p-3">
                                    <h6 class="card-title text-success mb-2">AFS</h6>
                                    <div class="mb-2">
                                        <small class="text-muted d-block"><?= __('paid') ?>:</small>
                                        <strong id="paidAmountAFS" class="text-success">AFS 0.00</strong>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block"><?= __('remaining') ?>:</small>
                                        <strong id="remainingAmountAFS" class="text-danger">AFS 0.00</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3" id="eurSection" style="display: none;">
                            <div class="card border-success">
                                <div class="card-body text-center p-3">
                                    <h6 class="card-title text-success mb-2">EUR</h6>
                                    <div class="mb-2">
                                        <small class="text-muted d-block"><?= __('paid') ?>:</small>
                                        <strong id="paidAmountEUR" class="text-success">EUR 0.00</strong>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block"><?= __('remaining') ?>:</small>
                                        <strong id="remainingAmountEUR" class="text-danger">EUR 0.00</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3" id="darhamSection" style="display: none;">
                            <div class="card border-success">
                                <div class="card-body text-center p-3">
                                    <h6 class="card-title text-success mb-2">DARHAM</h6>
                                    <div class="mb-2">
                                        <small class="text-muted d-block"><?= __('paid') ?>:</small>
                                        <strong id="paidAmountDARHAM" class="text-success">DARHAM 0.00</strong>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block"><?= __('remaining') ?>:</small>
                                        <strong id="remainingAmountDARHAM" class="text-danger">DARHAM 0.00</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add Transaction Form -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">
                                <i class="feather icon-plus-circle mr-2"></i><?= __('add_new_transaction') ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <form id="weightTransactionForm">
                                <input type="hidden" id="weightId" name="weight_id">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="transactionAmount" class="font-weight-bold">
                                                <i class="feather icon-dollar-sign mr-1"></i>
                                                <?= __('amount') ?> <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="feather icon-hash"></i></span>
                                                </div>
                                                <input type="number" class="form-control form-control-lg" id="transactionAmount" name="amount" step="0.01" placeholder="0.00" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="transactionCurrency" class="font-weight-bold">
                                                <i class="feather icon-globe mr-1"></i>
                                                <?= __('currency') ?> <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-control form-control-lg" id="transactionCurrency" name="currency" required>
                                                <option value=""><?= __('select_currency') ?></option>
                                                <option value="USD">USD - US Dollar</option>
                                                <option value="AFS">AFS - Afghan Afghani</option>
                                                <option value="EUR">EUR - Euro</option>
                                                <option value="DARHAM">DARHAM - UAE Darham</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Exchange Rate Section -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <div class="form-group" id="exchangeRateField" style="display: none;">
                                            <label for="transactionExchangeRate" class="font-weight-bold">
                                                <i class="feather icon-refresh-cw mr-1"></i>
                                                <?= __('exchange_rate') ?> <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="feather icon-trending-up"></i></span>
                                                </div>
                                                <input type="number" class="form-control form-control-lg" id="transactionExchangeRate"
                                                    name="exchange_rate" step="0.01" placeholder="Enter exchange rate" required>
                                            </div>
                                            <small class="form-text text-muted">
                                                <i class="feather icon-info mr-1"></i>
                                                <?= __('required_when_payment_currency_differs_from_weight_currency') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="transactionDate" class="font-weight-bold">
                                                <i class="feather icon-calendar mr-1"></i>
                                                <?= __('date') ?> <span class="text-danger">*</span>
                                            </label>
                                            <input type="date" class="form-control form-control-lg" id="transactionDate" name="transaction_date" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="transactionTime" class="font-weight-bold">
                                                <i class="feather icon-clock mr-1"></i>
                                                <?= __('time') ?> <span class="text-danger">*</span>
                                            </label>
                                            <input type="time" class="form-control form-control-lg" id="transactionTime" name="transaction_time" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="transactionRemarks" class="font-weight-bold">
                                        <i class="feather icon-message-square mr-1"></i>
                                        <?= __('remarks') ?>
                                    </label>
                                    <textarea class="form-control" id="transactionRemarks" name="remarks" rows="3" placeholder="Optional remarks..."></textarea>
                                </div>

                                <div class="text-right">
                                    <button type="submit" class="btn btn-primary btn-lg px-4">
                                        <i class="feather icon-save mr-2"></i>
                                        <?= __('save_transaction') ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Transactions Table -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="feather icon-list mr-2"></i>
                                <?= __('transaction_history') ?>
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="transactionsTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th class="border-0">
                                                <i class="feather icon-calendar mr-1"></i>
                                                <?= __('date') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-dollar-sign mr-1"></i>
                                                <?= __('remarks') ?>
                                                
                                            </th>
                                            
                                            <th class="border-0">
                                                <i class="feather icon-refresh-cw mr-1"></i>
                                                <?= __('amount') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-message-square mr-1"></i>
                                                <?= __('exchange_rate') ?>
                                            </th>
                                            <th class="text-center border-0">
                                                <i class="feather icon-settings mr-1"></i>
                                                <?= __('actions') ?>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="transactionsTableBody">
                                        <!-- Transactions will be loaded here dynamically -->
                                        <tr id="noTransactionsRow">
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <i class="feather icon-inbox display-4 mb-3"></i>
                                                <h5 class="mb-2"><?= __('no_transactions_found') ?></h5>
                                                <p class="mb-0"><?= __('add_first_transaction_above') ?></p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
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
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>
    
        <script src="js/weight/transaction_manager.js"></script>

        <script src="js/weight/weight_manager.js"></script>
    
        <script>
            // Button Protection System for Ticket Weights
            class TicketWeightsButtonProtection {
                constructor() {
                    this.init();
                }

                init() {
                    this.protectAddWeightButton();
                    this.protectSaveTransactionButton();
                    this.protectSaveChangesButton();
                    this.protectGenerateInvoiceButton();
                    this.protectGenerateCombinedInvoiceButton();
                    this.protectSearchButtons();
                    this.protectManageTransactionsButtons();
                    this.protectEditWeightButtons();
                    this.protectDeleteWeightButtons();
                    this.protectSelectTicketButtons();
                }

                protectButton(button, loadingText = 'Processing...', duration = 2000, disableButton = true) {
                    if (button && (!button.disabled || !disableButton)) {
                        const originalText = button.innerHTML;
                        if (disableButton) {
                            button.disabled = true;
                            button.innerHTML = `<i class="fas fa-spinner fa-spin mr-1"></i>${loadingText}`;
                        }

                        setTimeout(() => {
                            if (disableButton) {
                                button.disabled = false;
                                button.innerHTML = originalText;
                            }
                        }, duration);
                    }
                }

                protectAddWeightButton() {
                    const button = document.querySelector('[data-target="#addTransactionModal"]');
                    if (button) {
                        button.addEventListener('click', () => {
                            this.protectButton(button, 'Loading...', 1000, false);
                        });
                    }
                }

                protectSaveTransactionButton() {
                    const form = document.getElementById('addTransactionForm');
                    const button = document.getElementById('saveTransactionBtn');
                    if (form && button) {
                        form.addEventListener('submit', () => {
                            this.protectButton(button, 'Saving Transaction...', 3000);
                        });
                    }
                }

                protectSaveChangesButton() {
                    const form = document.getElementById('editWeightForm');
                    const button = document.querySelector('#editWeightForm button[type="submit"]');
                    if (form && button) {
                        form.addEventListener('submit', () => {
                            this.protectButton(button, 'Saving Changes...', 3000);
                        });
                    }
                }

                protectGenerateInvoiceButton() {
                    const button = document.getElementById('generateInvoiceBtn');
                    if (button) {
                        button.addEventListener('click', () => {
                            this.protectButton(button, 'Generating Invoice...', 5000);
                        });
                    }
                }

                protectGenerateCombinedInvoiceButton() {
                    const button = document.getElementById('generateCombinedWeightInvoice');
                    if (button) {
                        button.addEventListener('click', () => {
                            this.protectButton(button, 'Generating Invoice...', 5000);
                        });
                    }
                }

                protectSearchButtons() {
                    const searchPNRBtn = document.getElementById('searchPNRBtn');
                    const searchPassengerBtn = document.getElementById('searchPassengerBtn');

                    if (searchPNRBtn) {
                        searchPNRBtn.addEventListener('click', () => {
                            this.protectButton(searchPNRBtn, 'Searching...', 2000);
                        });
                    }

                    if (searchPassengerBtn) {
                        searchPassengerBtn.addEventListener('click', () => {
                            this.protectButton(searchPassengerBtn, 'Searching...', 2000);
                        });
                    }
                }

                protectManageTransactionsButtons() {
                    document.addEventListener('click', (e) => {
                        if (e.target.closest('[onclick*="manageTransactions"]')) {
                            const button = e.target.closest('button');
                            if (button) {
                                this.protectButton(button, 'Loading...', 1000);
                            }
                        }
                    });
                }

                protectEditWeightButtons() {
                    document.addEventListener('click', (e) => {
                        if (e.target.closest('[onclick*="editWeight"]')) {
                            const button = e.target.closest('button');
                            if (button) {
                                this.protectButton(button, 'Loading...', 1000);
                            }
                        }
                    });
                }

                protectDeleteWeightButtons() {
                    document.addEventListener('click', (e) => {
                        if (e.target.closest('[onclick*="deleteWeight"]')) {
                            const button = e.target.closest('button');
                            if (button) {
                                this.protectButton(button, 'Deleting...', 2000);
                            }
                        }
                    });
                }

                protectSelectTicketButtons() {
                    document.addEventListener('click', (e) => {
                        if (e.target.closest('.select-ticket')) {
                            const button = e.target.closest('button');
                            if (button) {
                                this.protectButton(button, 'Loading...', 1000);
                            }
                        }
                    });
                }
            }

            // Initialize button protection
            const ticketWeightsButtonProtection = new TicketWeightsButtonProtection();

            // Function to show toast
    function showToast(message, type = 'success') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type,
            title: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    }


        $(document).ready(function() {
            // Initialize DataTable
            $('#weightsTable').DataTable({
                responsive: true,
                autoWidth: false,
                language: {
                    search: <?= json_encode(__('search') . ':') ?>,
                    lengthMenu: <?= json_encode(__('show') . ' _MENU_ ' . __('entries')) ?>,
                    info: <?= json_encode(__('showing') . ' _START_ ' . __('to') . ' _END_ ' . __('of') . ' _TOTAL_ ' . __('entries')) ?>,
                    infoEmpty: <?= json_encode(__('showing') . ' 0 ' . __('to') . ' 0 ' . __('of') . ' 0 ' . __('entries')) ?>,
                    infoFiltered: <?= json_encode('(' . __('filtered_from') . ' _MAX_ ' . __('total_entries') . ')') ?>,
                    paginate: {
                        first: <?= json_encode(__('first')) ?>,
                        last: <?= json_encode(__('last')) ?>,
                        next: <?= json_encode(__('next')) ?>,
                        previous: <?= json_encode(__('previous')) ?>
                    }
                },
                columnDefs: [
                    { orderable: false, targets: 'no-sort' },
                    { orderable: false, targets: 0 } // Make checkbox column non-sortable
                ],
                order: [[5, 'desc']] // Sort by date added by default (adjusted for new checkbox column)
            });

            // Handle select all checkbox
            $('#selectAllWeights').on('change', function() {
                $('.weight-checkbox').prop('checked', $(this).prop('checked'));
                updateRowHighlighting();
                updateGenerateInvoiceButton();
            });

            // Handle individual checkbox changes
            $(document).on('change', '.weight-checkbox', function() {
                updateRowHighlighting();
                updateGenerateInvoiceButton();
            });

            // Function to update row highlighting
            function updateRowHighlighting() {
                $('.weight-checkbox').each(function() {
                    const row = $(this).closest('tr');
                    if ($(this).prop('checked')) {
                        row.addClass('selected');
                    } else {
                        row.removeClass('selected');
                    }
                });
            }

            // Function to update generate invoice button visibility
            function updateGenerateInvoiceButton() {
                const checkedBoxes = $('.weight-checkbox:checked');
                if (checkedBoxes.length > 0) {
                    $('#generateInvoiceBtn').show();
                } else {
                    $('#generateInvoiceBtn').hide();
                }
            }

            // Handle generate invoice button click
            $('#generateInvoiceBtn').on('click', function() {
                const selectedWeights = $('.weight-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();

                if (selectedWeights.length === 0) {
                    showToast('Please select at least one weight.', 'warning');
                    return;
                }

                // Show modal for invoice details
                showInvoiceModal(selectedWeights);
            });

            // Function to show invoice modal
            function showInvoiceModal(selectedWeights) {
                Swal.fire({
                    title: 'Generate Invoice',
                    html: `
                        <div class="form-group text-left">
                            <label for="invoiceCurrency">Currency:</label>
                            <select id="invoiceCurrency" class="form-control">
                                <option value="USD">USD</option>
                                <option value="AFS">AFS</option>
                                <option value="EUR">EUR</option>
                                <option value="DARHAM">DARHAM</option>
                            </select>
                        </div>
                        <div class="form-group text-left">
                            <label for="clientName">Client Name:</label>
                            <input type="text" id="clientName" class="form-control" placeholder="Enter client name">
                        </div>
                        <div class="form-group text-left">
                            <label for="invoiceComments">Comments:</label>
                            <textarea id="invoiceComments" class="form-control" rows="3" placeholder="Optional comments"></textarea>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Generate Invoice',
                    cancelButtonText: 'Cancel',
                    preConfirm: () => {
                        const currency = $('#invoiceCurrency').val();
                        const clientName = $('#clientName').val().trim();
                        const comments = $('#invoiceComments').val().trim();

                        if (!clientName) {
                            Swal.showValidationMessage('Please enter a client name');
                            return false;
                        }

                        return {
                            currency: currency,
                            clientName: clientName,
                            comments: comments
                        };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        generateInvoice(selectedWeights, result.value);
                    }
                });
            }

            // Function to generate invoice
            function generateInvoice(selectedWeights, invoiceData) {
                // Create form data
                const formData = new FormData();
                formData.append('invoiceData', JSON.stringify({
                    tickets: selectedWeights,
                    currency: invoiceData.currency,
                    clientName: invoiceData.clientName,
                    comment: invoiceData.comments
                }));

                // Create a temporary form to submit the data
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'generate_multi_ticket_weight_invoice.php';
                form.target = '_blank';

                // Add the invoice data as a hidden input
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'invoiceData';
                input.value = JSON.stringify({
                    tickets: selectedWeights,
                    currency: invoiceData.currency,
                    clientName: invoiceData.clientName,
                    comment: invoiceData.comments
                });
                form.appendChild(input);

                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);

                // Uncheck all checkboxes after generating invoice
                $('.weight-checkbox').prop('checked', false);
                $('#selectAllWeights').prop('checked', false);
                updateRowHighlighting();
                updateGenerateInvoiceButton();

                showToast('Invoice generated successfully!', 'success');
            }

            // Handle floating action button click
            $('#launchMultiWeightInvoice').on('click', function() {
                loadWeightsForInvoice();
                $('#multiWeightInvoiceModal').modal('show');
            });

            // Function to load weights for invoice selection
            function loadWeightsForInvoice() {
                $.ajax({
                    url: 'fetch_weights_for_invoice.php',
                    type: 'GET',
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                displayWeightsForInvoice(result.weights);
                            } else {
                                showToast(result.message || 'Failed to load weights', 'error');
                            }
                        } catch (e) {
                            showToast('Error loading weights', 'error');
                        }
                    },
                    error: function() {
                        showToast('Error loading weights', 'error');
                    }
                });
            }

            // Function to display weights in the modal
            function displayWeightsForInvoice(weights) {
                const tbody = $('#weightsForInvoiceBody');
                tbody.empty();
                let total = 0;

                weights.forEach(weight => {
                    const row = `
                        <tr>
                            <td>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input weight-invoice-checkbox"
                                           id="weight_${weight.id}" value="${weight.id}">
                                    <label class="custom-control-label" for="weight_${weight.id}"></label>
                                </div>
                            </td>
                            <td>${weight.sold_to_name || '-'}</td>
                            <td>${weight.passenger_name}</td>
                            <td>${weight.pnr}</td>
                            <td>${weight.weight} kg</td>
                            <td>${weight.currency} ${parseFloat(weight.sold_price).toFixed(2)}</td>
                        </tr>
                    `;
                    tbody.append(row);
                    total += parseFloat(weight.sold_price);
                });

                $('#weightInvoiceTotal').text(total.toFixed(2));

                // Handle select all in modal
                $('#selectAllWeightsModal').on('change', function() {
                    $('.weight-invoice-checkbox').prop('checked', $(this).prop('checked'));
                    updateModalTotal();
                });

                // Handle individual checkbox changes
                $(document).on('change', '.weight-invoice-checkbox', function() {
                    updateModalTotal();
                });
            }

            // Function to update modal total
            function updateModalTotal() {
                let total = 0;
                $('.weight-invoice-checkbox:checked').each(function() {
                    const weightId = $(this).val();
                    // Find the corresponding weight data and add to total
                    // This would need to be enhanced to get the actual amount
                });
                $('#weightInvoiceTotal').text(total.toFixed(2));
            }

            // Handle generate combined weight invoice
            $('#generateCombinedWeightInvoice').on('click', function() {
                const selectedWeights = $('.weight-invoice-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();

                if (selectedWeights.length === 0) {
                    showToast('Please select at least one weight.', 'warning');
                    return;
                }

                const clientName = $('#clientForWeightInvoice').val().trim();
                if (!clientName) {
                    showToast('Please enter a client name.', 'warning');
                    return;
                }

                const currency = $('#weightInvoiceCurrency').val();
                const comments = $('#weightInvoiceComment').val().trim();

                // Create form data
                const formData = new FormData();
                formData.append('invoiceData', JSON.stringify({
                    tickets: selectedWeights,
                    currency: currency,
                    clientName: clientName,
                    comment: comments
                }));

                // Create a temporary form to submit the data
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'generate_multi_ticket_weight_invoice.php';
                form.target = '_blank';

                // Add the invoice data as a hidden input
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'invoiceData';
                input.value = JSON.stringify({
                    tickets: selectedWeights,
                    currency: currency,
                    clientName: clientName,
                    comment: comments
                });
                form.appendChild(input);

                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);

                // Close modal and show success message
                $('#multiWeightInvoiceModal').modal('hide');
                showToast('Weight invoice generated successfully!', 'success');
            });

            // Handle client filter
            $('#clientFilterWeight').on('change', function() {
                const clientName = $(this).val();
                if (clientName) {
                    loadWeightsForInvoice(clientName);
                } else {
                    loadWeightsForInvoice();
                }
            });

            

            // Search by PNR
            $('#searchPNRBtn').on('click', function() {
                const pnr = $('#searchPNR').val().trim();
                if (pnr) {
                    searchTickets({ pnr: pnr });
                }
            });

            // Search by Passenger Name
            $('#searchPassengerBtn').on('click', function() {
                const passengerName = $('#searchPassenger').val().trim();
                if (passengerName) {
                    searchTickets({ passenger: passengerName });
                }
            });

            // Function to search tickets
            function searchTickets(params) {
                $.ajax({
                    url: 'ajax/search_tickets.php',
                    type: 'GET',
                    data: params,
                    success: function(response) {
                        try {
                            
                            // Determine if response is already an object or needs parsing
                            const result = typeof response === 'string' ? JSON.parse(response) : response;
                            
                            if (result.success) {
                                displaySearchResults(result.tickets);
                            } else {
                                alert(result.message || <?= json_encode(__('no_tickets_found')) ?>);
                            }
                        } catch (e) {
                            alert(<?= json_encode(__('error_processing_request')) ?>);
                        }
                    },
                    error: function() {
                        alert(<?= json_encode(__('error_searching_tickets')) ?>);
                    }
                });
            }

            // Function to display search results
            function displaySearchResults(tickets) {
                const tbody = $('#searchResultsTable tbody');
                tbody.empty();

                tickets.forEach(ticket => {
                    const row = `
                        <tr>
                            <td>${ticket.passenger_name}</td>
                            <td>${ticket.pnr}</td>
                            <td>
                                ${ticket.airline}<br>
                                <small>${ticket.origin} - ${ticket.destination}</small>
                            </td>
                            <td>${ticket.departure_date}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary select-ticket" data-ticket-id="${ticket.id}">
                                    <?= __('select') ?>
                                </button>
                            </td>
                        </tr>
                    `;
                    tbody.append(row);
                });
                $('#searchResultsContainer').show();
            }

            // Handle ticket selection
            $(document).on('click', '.select-ticket', function() {
                const ticketId = $(this).data('ticket-id');
                $('#selectedTicketId').val(ticketId);
                $('#weightDetailsContainer').show();
                $('#saveTransactionBtn').show();
            });

            // Calculate profit automatically
            $('#basePrice, #soldPrice').on('input', function() {
                const basePrice = parseFloat($('#basePrice').val()) || 0;
                const soldPrice = parseFloat($('#soldPrice').val()) || 0;
                const profit = soldPrice - basePrice;
                $('#profit').val(profit.toFixed(2));
            });

            // Handle form submission
            $('#addTransactionForm').on('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                $.ajax({
                    url: 'ajax/save_weight.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                showToast(<?= json_encode(__('weight_saved_successfully')) ?>, 'success');
                                location.reload();
                            } else {
                                showToast(result.message || <?= json_encode(__('failed_to_save_weight')) ?>, 'error');
                            }
                        } catch (e) {
                            showToast(<?= json_encode(__('error_processing_request')) ?>, 'error');
                        }
                    },
                    error: function() {
                        showToast(<?= json_encode(__('error_saving_weight')) ?>, 'error');
                    }
                });
            });

        });
    </script>


    <!-- Floating Action Button for Multi-Weight Invoice -->
    <div id="floatingActionButton" class="position-fixed" style="bottom: 80px; right: 20px; z-index: 1050;">
        <button type="button" class="btn btn-primary btn-lg shadow" id="launchMultiWeightInvoice" title="<?= __('generate_multi_weight_invoice') ?>">
            <i class="feather icon-file-text"></i>
        </button>
    </div>

    <!-- Multiple Weight Invoice Modal -->
    <div class="modal fade" id="multiWeightInvoiceModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="feather icon-file-text mr-2"></i><?= __('generate_combined_weight_invoice') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="feather icon-info mr-2"></i><?= __('select_multiple_weights_to_generate_a_combined_invoice') ?>
                    </div>

                    <form id="multiWeightInvoiceForm">
                        <div class="form-group">
                            <label for="clientFilterWeight"><?= __('filter_by_client') ?></label>
                            <select class="form-control" id="clientFilterWeight" name="clientFilter">
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
                            <label for="clientForWeightInvoice"><?= __('client') ?></label>
                            <input type="text" class="form-control" id="clientForWeightInvoice" name="clientForInvoice" required>
                        </div>

                        <div class="form-group">
                            <label for="weightInvoiceComment"><?= __('comments_notes') ?></label>
                            <textarea class="form-control" id="weightInvoiceComment" name="invoiceComment" rows="2"></textarea>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover table-bordered" id="weightSelectionTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th width="40">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="selectAllWeightsModal">
                                                <label class="custom-control-label" for="selectAllWeightsModal"></label>
                                            </div>
                                        </th>
                                        <th><?= __('client') ?></th>
                                        <th><?= __('passenger') ?></th>
                                        <th><?= __('pnr') ?></th>
                                        <th><?= __('weight') ?></th>
                                        <th><?= __('amount') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="weightsForInvoiceBody">
                                    <!-- Weights will be loaded here dynamically -->
                                </tbody>
                                <tfoot>
                                    <tr class="table-primary">
                                        <td colspan="5" class="text-right font-weight-bold"><?= __('total') ?>:</td>
                                        <td id="weightInvoiceTotal" class="font-weight-bold">0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="form-group mt-3">
                            <label for="weightInvoiceCurrency"><?= __('currency') ?></label>
                            <select class="form-control" id="weightInvoiceCurrency" name="invoiceCurrency" required>
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
                    <button type="button" class="btn btn-primary" id="generateCombinedWeightInvoice">
                        <i class="feather icon-file-text mr-2"></i><?= __('generate_invoice') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
