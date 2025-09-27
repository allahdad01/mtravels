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
                                                                <button class="btn btn-icon btn-sm btn-primary" onclick="manageTransactions(<?= $weight['id'] ?>)" title="<?= __('manage_transactions') ?>">
                                                                    <i class="feather icon-credit-card"></i>
                                                                </button>
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

    <!-- Add Transaction Modal -->
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
                                                <option value="USD">🇺🇸 USD - US Dollar</option>
                                                <option value="AFS">🇦🇫 AFS - Afghan Afghani</option>
                                                <option value="EUR">🇪🇺 EUR - Euro</option>
                                                <option value="DARHAM">🇦🇪 DARHAM - UAE Dirham</option>
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
                            <span class="badge badge-light" id="transactionCount">0</span>
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
                                                <?= __('amount') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-globe mr-1"></i>
                                                <?= __('currency') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-refresh-cw mr-1"></i>
                                                <?= __('exchange_rate') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-message-square mr-1"></i>
                                                <?= __('remarks') ?>
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
    <script src="js/toast-notification.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>

    <script>
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
         // Function to delete weight
    function deleteWeight(weightId) {
        Swal.fire({
            title: '<?= __('are_you_sure_you_want_to_delete_this_weight') ?>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: '<?= __('yes_delete_it') ?>',
            cancelButtonText: '<?= __('cancel') ?>'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax/delete_weight.php',
                    type: 'POST',
                    data: { id: weightId },
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                showToast('<?= __('weight_deleted_successfully') ?>', 'success');
                                location.reload();
                            } else {
                                showToast(result.message || '<?= __('failed_to_delete_weight') ?>', 'error');
                            }
                        } catch (e) {
                            console.error('Error:', e);
                            showToast('<?= __('error_processing_request') ?>', 'error');
                        }
                    },
                    error: function() {
                        showToast('<?= __('error_deleting_weight') ?>', 'error');
                    }
                });
            }
        });
    }

        $(document).ready(function() {
            // Initialize DataTable
            $('#weightsTable').DataTable({
                responsive: true,
                autoWidth: false,
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
                            console.error('Error:', e);
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

            // Calculate profit automatically in edit modal
            $('#editBasePrice, #editSoldPrice').on('input', function() {
                const basePrice = parseFloat($('#editBasePrice').val()) || 0;
                const soldPrice = parseFloat($('#editSoldPrice').val()) || 0;
                const profit = soldPrice - basePrice;
                $('#editProfit').val(profit.toFixed(2));
            });

            // Handle edit form submission
            $('#editWeightForm').on('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                $.ajax({
                    url: 'ajax/update_weight.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                showToast('<?= __('weight_updated_successfully') ?>', 'success');
                                location.reload();
                            } else {
                                showToast(result.message || '<?= __('failed_to_update_weight') ?>', 'error');
                            }
                        } catch (e) {
                            console.error('Error:', e);
                            showToast('<?= __('error_processing_request') ?>', 'error');
                        }
                    },
                    error: function() {
                        showToast('<?= __('error_updating_weight') ?>', 'error');
                    }
                });
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
                            console.log('Raw response:', response); // Log raw response
                            
                            // Determine if response is already an object or needs parsing
                            const result = typeof response === 'string' ? JSON.parse(response) : response;
                            
                            if (result.success) {
                                displaySearchResults(result.tickets);
                            } else {
                                alert(result.message || '<?= __('no_tickets_found') ?>');
                            }
                        } catch (e) {
                            console.error('Error:', e);
                            console.error('Response causing error:', response); // Log problematic response
                            alert('<?= __('error_processing_request') ?>');
                        }
                    },
                    error: function() {
                        alert('<?= __('error_searching_tickets') ?>');
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
                                showToast('<?= __('weight_saved_successfully') ?>', 'success');
                                location.reload();
                            } else {
                                showToast(result.message || '<?= __('failed_to_save_weight') ?>', 'error');
                            }
                        } catch (e) {
                            console.error('Error:', e);
                            showToast('<?= __('error_processing_request') ?>', 'error');
                        }
                    },
                    error: function() {
                        showToast('<?= __('error_saving_weight') ?>', 'error');
                    }
                });
            });

            // Reset form when modal is closed
            $('#addTransactionModal').on('hidden.bs.modal', function() {
                $('#addTransactionForm')[0].reset();
                $('#searchResultsContainer').hide();
                $('#weightDetailsContainer').hide();
                $('#saveTransactionBtn').hide();
                $('#selectedTicketId').val('');
            });

            // Function to manage transactions
            window.manageTransactions = function(weightId) {
                // Set the weight ID in the form
                $('#weightId').val(weightId);

                // Set today's date and current time as default for new transactions
                const now = new Date();
                const today = now.toISOString().split('T')[0];
                const currentTime = now.toTimeString().split(' ')[0].slice(0, 5); // Format: HH:mm
                $('#transactionDate').val(today);
                $('#transactionTime').val(currentTime);

                // Reset exchange rate field
                $('#exchangeRateField').hide();
                $('#transactionExchangeRate').attr('required', false);
                $('#transactionExchangeRate').val('');

                // Load weight details and transactions
                loadWeightDetails(weightId);
                loadTransactions(weightId);
                // Show the modal
                $('#transactionsModal').modal('show');
            };

            // Function to toggle exchange rate field based on currency selection
            function toggleExchangeRateField() {
                const selectedCurrency = $('#transactionCurrency').val();
                if (selectedCurrency && window.weightCurrency && selectedCurrency !== window.weightCurrency) {
                    $('#exchangeRateField').show();
                    $('#transactionExchangeRate').attr('required', true);
                } else {
                    $('#exchangeRateField').hide();
                    $('#transactionExchangeRate').attr('required', false);
                    $('#transactionExchangeRate').val(''); // Clear value when hidden
                }
            }

            // Add event listener for currency change
            $('#transactionCurrency').on('change', toggleExchangeRateField);

            // Function to load weight details
            function loadWeightDetails(weightId) {
                $.ajax({
                    url: 'ajax/get_weight.php',
                    type: 'GET',
                    data: { id: weightId },
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.success) {
                                const weight = result.weight;

                                // Store weight currency for exchange rate logic
                                window.weightCurrency = weight.currency;

                                // Update weight details in the modal
                                $('#trans-passenger-name').text(weight.passenger_name);
                                $('#trans-pnr').text(weight.pnr);
                                $('#trans-weight').text(weight.weight + ' kg');

                                // Update financial details
                                $('#totalAmount').text(weight.currency + ' ' + parseFloat(weight.sold_price).toFixed(2));
                                updatePaymentStatus(weight);
                            }
                        } catch (e) {
                            console.error('Error:', e);
                        }
                    }
                });
            }
           // Function to load transactions
function loadTransactions(weightId) {
    $.ajax({
        url: 'ajax/get_weight_transactions.php',
        type: 'GET',
        data: { weight_id: weightId },
        success: function(response) {
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    displayTransactions(result.transactions);
                    updatePaymentStatus(result.weight, result.transactions);
                } else {
                    console.error('Failed to load transactions:', result.message);
                }
            } catch (e) {
                console.error('Error parsing transactions:', e);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading transactions:', error);
        }
    });
}
// Function to display transactions in the table
 function displayTransactions(transactions) {
     const tbody = $('#transactionsTableBody');
     tbody.empty();

     // Update transaction count
     $('#transactionCount').text(transactions.length);

     if (transactions.length === 0) {
         // Show no transactions message
         const noTransactionsRow = `
             <tr id="noTransactionsRow">
                 <td colspan="6" class="text-center py-5 text-muted">
                     <i class="feather icon-inbox display-4 mb-3"></i>
                     <h5 class="mb-2">${__('no_transactions_found')}</h5>
                     <p class="mb-0">${__('add_first_transaction_above')}</p>
                 </td>
             </tr>
         `;
         tbody.append(noTransactionsRow);
         return;
     }

     transactions.forEach(trans => {
         // Use exchange rate from the separate field
         let exchangeRateDisplay = trans.exchange_rate ? parseFloat(trans.exchange_rate).toFixed(2) : 'N/A';
         const description = trans.remarks || '';

         const row = `
             <tr>
                 <td>
                     <div class="d-flex align-items-center">
                         <i class="feather icon-calendar mr-2 text-muted"></i>
                         ${formatDate(trans.transaction_date)}
                     </div>
                 </td>
                 <td>
                     <strong class="text-primary">${parseFloat(trans.amount).toFixed(2)}</strong>
                     <small class="text-muted d-block">${trans.currency}</small>
                 </td>
                 <td>
                     <span class="badge badge-secondary">${trans.currency}</span>
                 </td>
                 <td>
                     <span class="text-info font-weight-bold">${exchangeRateDisplay}</span>
                 </td>
                 <td>
                     <div class="text-truncate" style="max-width: 200px;" title="${description}">
                         ${description || '<em class="text-muted">No remarks</em>'}
                     </div>
                 </td>
                 <td class="text-center">
                     <button class="btn btn-sm btn-outline-danger" onclick="deleteTransaction(${trans.id}, ${$('#weightId').val()}, ${trans.amount})" title="Delete Transaction">
                         <i class="feather icon-trash-2"></i>
                     </button>
                 </td>
             </tr>
         `;
         tbody.append(row);
     });
 }

 function updatePaymentStatus(weight, transactions = []) {
    const baseCurrency = weight.currency; // e.g., "AFS"
    const totalAmount = parseFloat(weight.sold_price) || 0;

    // --- STEP 1: Build rates map from transactions ---
    const ratesToBase = {};
    transactions.forEach(t => {
        const curr = t.currency;
        let rate = parseFloat(t.exchange_rate);
        if (!rate) rate = (curr === baseCurrency) ? 1 : null;
        if (rate) ratesToBase[curr] = rate;
    });
    ratesToBase[baseCurrency] = 1; // ensure base currency included

    // --- STEP 2: Sum paid amounts per currency ---
    const paidAmounts = {};
    transactions.forEach(t => {
        const curr = t.currency;
        const amount = parseFloat(t.amount) || 0;
        if (!paidAmounts[curr]) paidAmounts[curr] = 0;
        paidAmounts[curr] += amount;
    });

    // --- STEP 3: Conversion helper ---
    function convert(amount, from, to) {
        if (from === to) return amount;
        const rateFrom = ratesToBase[from];
        const rateTo = ratesToBase[to];
        if (!rateFrom || !rateTo) return 0;
        return (amount * rateFrom) / rateTo;
    }

    // --- STEP 4: Total paid in base currency ---
    let totalPaidInBase = 0;
    Object.keys(paidAmounts).forEach(curr => {
        totalPaidInBase += convert(paidAmounts[curr], curr, baseCurrency);
    });
    const remainingInBase = Math.max(0, totalAmount - totalPaidInBase);

    // --- STEP 5: Update info cards ---
    $('#totalAmount').text(`${baseCurrency} ${totalAmount.toFixed(2)}`);

    const exchangedAmounts = Object.keys(ratesToBase).map(curr => {
        const converted = convert(totalAmount, baseCurrency, curr).toFixed(2);
        return `${curr} ${converted}`;
    });
    $('#exchangedAmount').text(exchangedAmounts.join(', '));

    $('#exchangeRateDisplay').text(
        Object.keys(ratesToBase)
            .filter(c => c !== baseCurrency)
            .map(c => `${c}: ${ratesToBase[c]}`)
            .join(', ')
    );

    // --- STEP 6: Update all payment cards dynamically ---
    $('#paymentStatusContainer').show();
    const currencies = ['USD', 'AFS', 'EUR', 'DARHAM']; // your card IDs
    currencies.forEach(curr => {
        const paid = paidAmounts[curr] || 0;
        const remaining = convert(remainingInBase, baseCurrency, curr);

        const sectionId = `#${curr.toLowerCase()}Section`;
        if (paid > 0 || remaining > 0) $(sectionId).show();

        $(`#paidAmount${curr}`).text(`${curr} ${paid.toFixed(2)}`);
        $(`#remainingAmount${curr}`).text(`${curr} ${remaining.toFixed(2)}`);
    });
}








// Handle transaction form submission
$('#weightTransactionForm').on('submit', function(e) {
    e.preventDefault();
    
    // Create FormData object
    const formData = new FormData(this);

    // Remove the transaction_time field since we'll combine it with date
    formData.delete('transaction_time');

    // Combine date and time
    const date = $('#transactionDate').val();
    const time = $('#transactionTime').val();
    if (date && time) {
        formData.set('transaction_date', `${date} ${time}`);
    }

    // Add exchange rate if field is visible
    if ($('#exchangeRateField').is(':visible')) {
        const exchangeRate = $('#transactionExchangeRate').val();
        if (exchangeRate) {
            formData.set('exchange_rate', exchangeRate);
        }
    }
    
    $.ajax({
        url: 'ajax/save_weight_transaction.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            try {
                const result = JSON.parse(response);
                if (result.success) {
                    // Show success message
                    showToast('<?= __('transaction_saved_successfully') ?>', 'success');
                    
                    // Reload transactions
                    loadTransactions($('#weightId').val());
                    
                    // Reset form
                    $('#weightTransactionForm')[0].reset();
                    
                    // Set today's date and current time again
                    const now = new Date();
                    $('#transactionDate').val(now.toISOString().split('T')[0]);
                    $('#transactionTime').val(now.toTimeString().split(' ')[0].slice(0, 5));
                } else {
                    showToast(result.message || '<?= __('failed_to_save_transaction') ?>', 'error');
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                console.log('Raw response:', response);
                showToast('<?= __('error_processing_request') ?>', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.log('Status:', status);
            console.log('Response:', xhr.responseText);
            alert('<?= __('error_saving_transaction') ?>'); 
        }
    });
});
            // Function to format date
            function formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }
        });

        // Function to edit weight
        function editWeight(weightId) {
            $.ajax({
                url: 'ajax/get_weight.php',
                type: 'GET',
                data: { id: weightId },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            const weight = result.weight;
                            
                            // Populate the edit form
                            $('#editWeightId').val(weight.id);
                            $('#editWeight').val(weight.weight);
                            $('#editBasePrice').val(weight.base_price);
                            $('#editSoldPrice').val(weight.sold_price);
                            $('#editMarketExchangeRate').val(weight.market_exchange_rate);
                            $('#editExchangeRate').val(weight.exchange_rate);
                            $('#editProfit').val(weight.profit);
                            $('#editRemarks').val(weight.remarks);
                            
                            // Show the modal
                            $('#editWeightModal').modal('show');
                        } else {
                            alert(result.message || '<?= __('failed_to_load_weight_details') ?>');
                        }
                    } catch (e) {
                        alert('<?= __('error_loading_weight_details') ?>');
                    }
                },
                error: function() {
                    showToast('<?= __('error_loading_weight_details') ?>', 'error');
                }
            });
        }
        function deleteTransaction(transactionId, reference_id, amount) {
    Swal.fire({
        title: '<?= __('are_you_sure_you_want_to_delete_this_transaction') ?>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: '<?= __('yes_delete_it') ?>',
        cancelButtonText: '<?= __('cancel') ?>'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/delete_weight_transaction.php',
                type: 'POST',
                dataType: 'json',  // Let jQuery parse JSON automatically
                data: { 
                    transaction_id: transactionId,
                    weight_id: reference_id,
                    amount: amount
                },
                success: function(result) {
                    // jQuery has already parsed JSON
                    if (result && result.success) {
                        showToast(result.message || '<?= __('transaction_deleted_successfully') ?>', 'success');
                        // Reload transactions
                    location.reload();
                    } else {
                        showToast(result?.message || '<?= __('failed_to_delete_transaction') ?>', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error, xhr.responseText);
                    showToast('<?= __('error_processing_request') ?>', 'error');
                }
            });
        }
    });
}

    </script>

    <style>
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }

        .bg-light-primary {
            background-color: rgba(62, 100, 255, 0.15);
            color: #3e64ff;
        }

        .table td {
            vertical-align: middle;
        }

        /* Checkbox styling */
        .weight-checkbox, #selectAllWeights {
            transform: scale(1.2);
            cursor: pointer;
        }

        /* Selected row highlighting */
        .table tbody tr.selected {
            background-color: rgba(40, 167, 69, 0.1);
        }

        /* Generate invoice button styling */
        #generateInvoiceBtn {
            margin-right: 10px;
            transition: all 0.3s ease;
        }

        #generateInvoiceBtn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* Transaction Modal Improvements */
        #transactionsModal .modal-xl {
            max-width: 95%;
        }

        #transactionsModal .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        #transactionsModal .card-header {
            border-radius: 10px 10px 0 0 !important;
            border: none;
            padding: 1rem 1.5rem;
        }

        #transactionsModal .form-control-lg {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        #transactionsModal .form-control-lg:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        #transactionsModal .input-group-text {
            background-color: #f8f9fa;
            border: 2px solid #e9ecef;
            color: #6c757d;
        }

        #transactionsModal .btn-lg {
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        #transactionsModal .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        }

        #transactionsModal .table th {
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #495057;
        }

        #transactionsModal .badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
        }

        /* Responsive improvements */
        @media (max-width: 768px) {
            #transactionsModal .modal-xl {
                max-width: 98%;
                margin: 0.5rem;
            }

            #transactionsModal .row {
                margin-bottom: 1rem;
            }

            #transactionsModal .card-body {
                padding: 1rem;
            }

            #transactionsModal .btn-lg {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }

        /* Animation for modal content */
        #transactionsModal .card {
            animation: fadeInUp 0.3s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Custom scrollbar for modal */
        #transactionsModal .modal-body::-webkit-scrollbar {
            width: 6px;
        }

        #transactionsModal .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        #transactionsModal .modal-body::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }

        #transactionsModal .modal-body::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>

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