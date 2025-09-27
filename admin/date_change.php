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
include 'handlers/date_change_handler.php';
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
                                                                $clientQuery = $conn->query("SELECT client_type FROM clients WHERE tenant_id = $tenant_id AND name = '$soldTo'");
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
                                                                        AND reference_id = '$ticketId'");

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
                                                                    <button type="button" class="btn btn-sm btn-primary" onclick="manageTransactions(<?= $ticket['id'] ?>)" title="<?= __('manage_transactions') ?>">
                                                                        <i class="fa fa-credit-card"></i>
                                                                    </button>
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
<!-- Add a floating action button for launching the multi-ticket invoice modal -->
<div id="floatingActionButton" class="position-fixed" style="bottom: 80px; right: 30px; z-index: 1050;">
    <button type="button" class="btn btn-primary btn-lg shadow" id="launchMultiTicketInvoice" title="<?= __('generate_multi_ticket_invoice') ?>">
        <i class="feather icon-file-text"></i>
    </button>
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
                        <label for="clientForInvoice"><?= __('client') ?></label>
                        <input type="text" class="form-control" id="clientForInvoice" name="clientForInvoice" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="clientFilter"><?= __('filter_by_client') ?></label>
                        <select class="form-control" id="clientFilter" name="clientFilter">
                            <option value=""><?= __('all_clients') ?></option>
                            <?php
                            // Fetch clients from database
                            $clientQuery = "SELECT DISTINCT c.name FROM clients c 
                                          INNER JOIN date_change_tickets dct ON c.id = dct.sold_to 
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
                                    <th><?= __('charges') ?></th>
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


<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>
    
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
                                                                <i class="feather icon-user mr-2"></i><?= __('personal_information') ?>
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

                                <!-- Add Date Change Modal -->
    <div class="modal fade" id="addDateChangeModal" tabindex="-1" role="dialog" aria-labelledby="addDateChangeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addDateChangeModalLabel">
                        <i class="feather icon-plus mr-2"></i><?= __('add_date_change') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="addDateChangeForm" class="needs-validation" novalidate>
                    <div class="modal-body">
                        <!-- Alert Container for Messages -->
                        <div id="modalAlertContainer"></div>
                        
                        <!-- Search Section -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="searchPNR"><?= __('search_by_pnr') ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="searchPNR" 
                                               placeholder="<?= __('enter_pnr') ?>"
                                               pattern="[A-Z0-9]{6}"
                                               title="<?= __('pnr_format_hint') ?>">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button" id="searchPNRBtn">
                                                <i class="feather icon-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="invalid-feedback">
                                        <?= __('please_enter_valid_pnr') ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="searchPassenger"><?= __('search_by_passenger') ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="searchPassenger" 
                                               placeholder="<?= __('enter_passenger_name') ?>"
                                               minlength="3">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button" id="searchPassengerBtn">
                                                <i class="feather icon-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="invalid-feedback">
                                        <?= __('name_min_length') ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Search Results Section -->
                        <div id="searchResultsContainer" class="mt-4" style="display: none;">
                            <div class="table-responsive">
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
                        </div>

                        <!-- Date Change Details Section -->
                        <div id="dateChangeDetailsContainer" class="mt-4" style="display: none;">
                            <input type="hidden" id="selectedTicketId" name="ticketId">
                            <input type="hidden" name="status" value="Date Changed">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="departureDate"><?= __('new_departure_date') ?></label>
                                        <input type="date" class="form-control" id="departureDate" 
                                               name="departureDate" required
                                               min="<?= date('Y-m-d') ?>">
                                        <div class="invalid-feedback">
                                            <?= __('please_select_future_date') ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="exchange_rate"><?= __('exchange_rate') ?></label>
                                        <input type="number" step="0.0001" class="form-control" 
                                               id="exchange_rate" name="exchange_rate" required
                                               min="0.0001">
                                        <div class="invalid-feedback">
                                            <?= __('please_enter_valid_exchange_rate') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="supplier_penalty"><?= __('supplier_penalty') ?></label>
                                        <input type="number" step="0.01" class="form-control" 
                                               id="supplier_penalty" name="supplier_penalty" required
                                               min="0">
                                        <div class="invalid-feedback">
                                            <?= __('please_enter_valid_amount') ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="service_penalty"><?= __('service_penalty') ?></label>
                                        <input type="number" step="0.01" class="form-control" 
                                               id="service_penalty" name="service_penalty" required
                                               min="0">
                                        <div class="invalid-feedback">
                                            <?= __('please_enter_valid_amount') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="base"><?= __('base_price') ?></label>
                                        <input type="number" step="0.01" class="form-control" 
                                               id="base" name="base" required
                                               min="0">
                                        <div class="invalid-feedback">
                                            <?= __('please_enter_valid_amount') ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="sold"><?= __('sold_price') ?></label>
                                        <input type="number" step="0.01" class="form-control" 
                                               id="sold" name="sold" required
                                               min="0">
                                        <div class="invalid-feedback">
                                            <?= __('please_enter_valid_amount') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="description"><?= __('description') ?></label>
                                <textarea class="form-control" id="description" 
                                          name="description" rows="3" required
                                          minlength="10"></textarea>
                                <div class="invalid-feedback">
                                    <?= __('description_min_length') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                        </button>
                        <button type="submit" class="btn btn-primary" id="saveDateChangeBtn" style="display: none;">
                            <i class="feather icon-save mr-2"></i><?= __('save_date_change') ?>
                        </button>
                    </div>
                </form>
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
    <script src="js/date-change/dataTable.js"></script>
    <script src="js/date-change/profile.js"></script>
    <script src="js/date-change/addDateChange.js"></script>
    <script src="js/date-change/deleteDateChange.js"></script>
    <script src="js/date-change/transaction-manager.js"></script>
    <script src="js/date-change/multiTicket.js"></script>



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
                <div class="modal-body" style="max-height: 80vh; overflow-y: auto;">
                    <!-- Ticket Info Card -->
                    <div class="card mb-4 border-primary shadow-sm">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 text-primary">
                                <i class="feather icon-info mr-2"></i><?= __('ticket_information') ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-3">
                                        <i class="feather icon-user mr-2"></i><?= __('passenger_details') ?>
                                    </h6>
                                    <div class="pl-3">
                                        <p class="mb-2">
                                            <strong class="text-dark"><?= __('passenger') ?>:</strong>
                                            <span id="trans-passenger-name" class="text-primary">Loading...</span>
                                        </p>
                                        <p class="mb-2">
                                            <strong class="text-dark"><?= __('pnr') ?>:</strong>
                                            <span id="trans-pnr" class="text-primary">Loading...</span>
                                        </p>
                                        <p class="mb-0">
                                            <strong class="text-dark"><?= __('new_departure') ?>:</strong>
                                            <span id="trans-departure-date" class="text-primary">Loading...</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-3">
                                        <i class="feather icon-dollar-sign mr-2"></i><?= __('payment_summary') ?>
                                    </h6>
                                    <div class="bg-light p-3 rounded">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted d-block"><?= __('total_amount') ?></small>
                                                <strong id="totalAmount" class="text-dark h6">0.00</strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block"><?= __('exchange_rate') ?></small>
                                                <strong id="exchangeRateDisplay" class="text-dark h6">0.00</strong>
                                            </div>
                                        </div>
                                        <hr class="my-2">
                                        <div class="row">
                                            <div class="col-12">
                                                <small class="text-muted d-block"><?= __('exchanged_amount') ?></small>
                                                <strong id="exchangedAmount" class="text-success h6">0.00</strong>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Currency-specific sections -->
                                    <div id="usdSection" style="display: none;" class="mt-2">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-success d-block">
                                                    <i class="fas fa-check-circle mr-1"></i><?= __('paid_usd') ?>
                                                </small>
                                                <strong id="paidAmountUSD" class="text-success">USD 0.00</strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-danger d-block">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i><?= __('remaining_usd') ?>
                                                </small>
                                                <strong id="remainingAmountUSD" class="text-danger">USD 0.00</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="afsSection" style="display: none;" class="mt-2">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-success d-block">
                                                    <i class="fas fa-check-circle mr-1"></i><?= __('paid_afs') ?>
                                                </small>
                                                <strong id="paidAmountAFS" class="text-success">AFS 0.00</strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-danger d-block">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i><?= __('remaining_afs') ?>
                                                </small>
                                                <strong id="remainingAmountAFS" class="text-danger">AFS 0.00</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="eurSection" style="display: none;" class="mt-2">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-success d-block">
                                                    <i class="fas fa-check-circle mr-1"></i><?= __('paid_eur') ?>
                                                </small>
                                                <strong id="paidAmountEUR" class="text-success">EUR 0.00</strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-danger d-block">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i><?= __('remaining_eur') ?>
                                                </small>
                                                <strong id="remainingAmountEUR" class="text-danger">EUR 0.00</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="aedSection" style="display: none;" class="mt-2">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-success d-block">
                                                    <i class="fas fa-check-circle mr-1"></i><?= __('paid_aed') ?>
                                                </small>
                                                <strong id="paidAmountAED" class="text-success">AED 0.00</strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-danger d-block">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i><?= __('remaining_aed') ?>
                                                </small>
                                                <strong id="remainingAmountAED" class="text-danger">AED 0.00</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add Transaction Form -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0 text-white">
                                <i class="feather icon-plus-circle mr-2"></i><?= __('add_new_transaction') ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <form id="dateChangeTransactionForm">
                                <input type="hidden" id="booking_id" name="booking_id">

                                <!-- Transaction Details Section -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="text-primary mb-3 border-bottom pb-2">
                                            <i class="feather icon-calendar mr-2"></i><?= __('transaction_details') ?>
                                        </h6>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="paymentDate" class="font-weight-semibold text-dark">
                                                <i class="feather icon-calendar mr-1"></i><?= __('date') ?>
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="date" class="form-control border" id="paymentDate" name="payment_date" required>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="paymentTime" class="font-weight-semibold text-dark">
                                                <i class="feather icon-clock mr-1"></i><?= __('time') ?>
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control border" id="paymentTime" name="payment_time"
                                                placeholder="14:30:00" pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]"
                                                title="Format: HH:MM:SS (24-hour format)" required>
                                            <small class="form-text text-muted">
                                                <i class="feather icon-help-circle mr-1"></i><?= __('format_hours_minutes_seconds_24_hour') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Payment Information Section -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="text-primary mb-3 border-bottom pb-2">
                                            <i class="feather icon-credit-card mr-2"></i><?= __('payment_information') ?>
                                        </h6>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="paymentAmount" class="font-weight-semibold text-dark">
                                                <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="number" step="0.01" min="0" class="form-control border"
                                                id="paymentAmount" name="payment_amount"
                                                placeholder="0.00" required>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="paymentCurrency" class="font-weight-semibold text-dark">
                                                <i class="feather icon-globe mr-1"></i><?= __('currency') ?>
                                                <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-control border" id="paymentCurrency" name="payment_currency" required>
                                                <option value=""><?= __('select_currency') ?></option>
                                                <option value="USD">USD - <?= __('us_dollar') ?></option>
                                                <option value="AFS">AFS - <?= __('afghan_afghani') ?></option>
                                                <option value="EUR">EUR - <?= __('euro') ?></option>
                                                <option value="DARHAM">AED - <?= __('uae_dirham') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Exchange Rate Section -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="form-group" id="exchangeRateField" style="display: none;">
                                            <label for="transactionExchangeRate" class="font-weight-semibold text-dark">
                                                <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input type="number" class="form-control border" id="transactionExchangeRate"
                                                name="exchange_rate" step="0.01" placeholder="Enter exchange rate" required>
                                            <small class="form-text text-muted">
                                                <i class="feather icon-info mr-1"></i>
                                                <?= __('required_when_payment_currency_differs_from_ticket_currency') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Description Section -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="text-primary mb-3 border-bottom pb-2">
                                            <i class="feather icon-edit-3 mr-2"></i><?= __('additional_information') ?>
                                        </h6>
                                    </div>

                                    <div class="col-12">
                                        <div class="form-group">
                                            <label for="paymentDescription" class="font-weight-semibold text-dark">
                                                <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                            </label>
                                            <textarea class="form-control border" id="paymentDescription" name="payment_description"
                                                    rows="3" placeholder="<?= __('enter_transaction_description') ?>"></textarea>
                                            <small class="form-text text-muted"><?= __('optional_field') ?></small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="row">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                            <small class="text-muted">
                                                <i class="feather icon-info mr-1"></i>
                                                <span class="text-danger">*</span> <?= __('required_fields') ?>
                                            </small>

                                            <div class="btn-group">
                                                <button type="button" class="btn btn-outline-secondary" onclick="transactionManager.resetForm()">
                                                    <i class="feather icon-refresh-cw mr-1"></i><?= __('reset') ?>
                                                </button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather icon-save mr-1"></i><?= __('save_transaction') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>



                    <!-- Transaction History -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-primary">
                                <i class="feather icon-list mr-2"></i><?= __('transaction_history') ?>
                            </h6>
                            <small class="text-muted" id="transactionCount">0 <?= __('transactions') ?></small>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th class="border-0">
                                                <i class="feather icon-calendar mr-1"></i><?= __('date_time') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-tag mr-1"></i><?= __('type') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                                            </th>
                                            <th class="border-0">
                                                <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                                            </th>
                                            <th class="text-center border-0">
                                                <i class="feather icon-settings mr-1"></i><?= __('actions') ?>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="transactionTableBody">
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="feather icon-loader fa-spin fa-2x mb-3 d-block"></i>
                                                    <p class="mb-0"><?= __('loading_transactions') ?>...</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-light text-center" id="transactionFooter" style="display: none;">
                            <small class="text-muted">
                                <i class="feather icon-info mr-1"></i>
                                <?= __('showing_all_transactions_for_this_ticket') ?>
                            </small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <div class="d-flex justify-content-between w-100">
                        <small class="text-muted align-self-center">
                            <i class="feather icon-info mr-1"></i>
                            <?= __('manage_all_transactions_for_this_date_change') ?>
                        </small>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="feather icon-x mr-1"></i><?= __('close') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Transaction Modal -->
    <div class="modal fade" id="editTransactionModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="feather icon-edit mr-2"></i><?= __('edit_transaction') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editTransactionForm">
                    <div class="modal-body">
                        <input type="hidden" id="editTransactionId" name="transaction_id">
                        <input type="hidden" id="editBookingId" name="booking_id">
                        <input type="hidden" id="originalAmount" name="original_amount">
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="editPaymentDate"><?= __('date') ?></label>
                                <input type="date" class="form-control" id="editPaymentDate" name="payment_date" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="editPaymentTime"><?= __('time') ?></label>
                                <input type="text" class="form-control" id="editPaymentTime" name="payment_time" 
                                       placeholder="HH:MM:SS" pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]" 
                                       title="Format: HH:MM:SS" required>
                                <small class="form-text text-muted"><?= __('format_hours_minutes_seconds_24_hour') ?></small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="editPaymentAmount"><?= __('amount') ?></label>
                                <input type="number" step="0.01" class="form-control" id="editPaymentAmount" name="payment_amount" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="editPaymentCurrency"><?= __('currency') ?></label>
                                <select class="form-control" id="editPaymentCurrency" name="payment_currency" required disabled>
                                    <option value=""><?= __('select_currency') ?></option>
                                    <option value="USD">USD - <?= __('us_dollar') ?></option>
                                    <option value="AFS">AFS - <?= __('afghan_afghani') ?></option>
                                    <option value="EUR">EUR - <?= __('euro') ?></option>
                                    <option value="DARHAM">AED - <?= __('uae_dirham') ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" id="editExchangeRateField" style="display: none;">
                            <label for="editTransactionExchangeRate"><?= __('exchange_rate') ?></label>
                            <input type="number" class="form-control" id="editTransactionExchangeRate"
                                name="exchange_rate" step="0.01" placeholder="Enter exchange rate" required>
                            <small class="form-text text-muted">
                                <i class="feather icon-info mr-1"></i>
                                <?= __('required_when_payment_currency_differs_from_ticket_currency') ?>
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="editPaymentDescription"><?= __('description') ?></label>
                            <textarea class="form-control" id="editPaymentDescription" name="payment_description" rows="2" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="feather icon-save mr-1"></i><?= __('update_transaction') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
