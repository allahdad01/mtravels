<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();



// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
include 'handlers/refund_ticket_handler.php';

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
                            <!-- [ Statistics ] start -->
                            <div class="col-md-12 col-xl-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="text-muted mb-3"><?= __('total_refunds') ?></h6>
                                        <div class="row d-flex align-items-center">
                                            <div class="col-9">
                                                <h3 class="f-w-300 d-flex align-items-center m-b-0">
                                                    <i class="feather icon-refresh-ccw text-c-blue f-30 m-r-10"></i>
                                                    <?= count($tickets) ?>
                                                </h3>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- [ Statistics ] end -->

                            <!-- [ Search and Actions Section ] start -->
                            <div class="card col-sm-12">
                                <div class="card-header">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-6 text-left">
                                                <h4 class="mb-0">Refunded Tickets</h4>
                                            </div>
                                            <div class="col-md-6 text-right">
                                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addRefundTicketModal">
                                                    <i class="feather icon-plus mr-2"></i><?= __('add_refund_ticket') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                                <!-- [ Ticket Table ] start -->
                                <div class="card" style="width: 100%;">
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
                                                    <tr>
                                                        <td class="text-center"><?= $index + 1 ?></td>
                                                        <td class="text-center">
                                                            <div class="d-flex justify-content-center">
                                                                <div class="dropdown">
                                                                    <button class="btn btn-icon btn-outline-primary dropdown-toggle" type="button" data-toggle="dropdown">
                                                                        <i class="feather icon-more-horizontal"></i>
                                                                    </button>
                                                                <div class="dropdown-menu dropdown-menu-right">
                                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="manageTransactions(<?= $ticket['id'] ?>)">
                                                                        <i class="fas fa-dollar-sign mr-2"></i><?= __('manage_payments') ?>
                                                                    </a>
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

$clientQuery = $conn->query("SELECT client_type FROM clients WHERE name = '$soldTo'");
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
        AND reference_id = '$ticketId'");

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

<!-- Add Refund Ticket Modal -->
<div class="modal fade" id="addRefundTicketModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-plus mr-2"></i><?= __('add_refund_ticket') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Search Section -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="form-row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="searchPNR"><?= __('search_by_pnr') ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="searchPNR" placeholder="<?= __('enter_pnr') ?>">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button" onclick="searchTickets('pnr')">
                                                <i class="feather icon-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="searchName"><?= __('search_by_name') ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="searchName" placeholder="<?= __('enter_passenger_name') ?>">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button" onclick="searchTickets('passenger')">
                                                <i class="feather icon-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="searchResults" class="mt-3" style="display: none;">
                            <h6><?= __('search_results') ?></h6>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th><?= __('passenger') ?></th>
                                            <th><?= __('pnr') ?></th>
                                            <th><?= __('flight_details') ?></th>
                                            <th><?= __('date') ?></th>
                                            <th><?= __('action') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="searchResultsBody">
                                        <!-- Search results will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Refund Form -->
                <form id="refundTicketForm" style="display: none;">
                    <input type="hidden" id="ticketId" name="ticketId">
                    <input type="hidden" id="status" name="status" value="pending">
                    <input type="hidden" id="exchangeRate" name="exchange_rate" value="1">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="base"><?= __('base') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" id="baseCurrency"></span>
                                    </div>
                                    <input type="number" class="form-control" id="base" name="base" 
                                           step="0.01" min="0" required readonly>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="sold"><?= __('sold') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" id="soldCurrency"></span>
                                    </div>
                                    <input type="number" class="form-control" id="sold" name="sold" 
                                           step="0.01" min="0" required readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="supplier_penalty"><?= __('supplier_penalty') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" id="penaltyCurrency"></span>
                                    </div>
                                    <input type="number" class="form-control" id="supplier_penalty" name="supplier_penalty" 
                                           step="0.01" min="0" required value="0">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="service_penalty"><?= __('service_penalty') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" id="penaltyCurrency2"></span>
                                    </div>
                                    <input type="number" class="form-control" id="service_penalty" name="service_penalty" 
                                           step="0.01" min="0" required value="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="totalPenalty"><?= __('total_penalty') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" id="totalPenaltyCurrency"></span>
                            </div>
                            <input type="text" class="form-control" id="totalPenalty" name="total_penalty" readonly>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="calculationMethod"><?= __('calculation_method') ?></label>
                        <select class="form-control" id="calculationMethod" name="calculationMethod" onchange="calculateRefund()">
                            <option value="sold"><?= __('calculate_from_sold') ?></option>
                            <option value="base"><?= __('calculate_from_base') ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="refundPassengerAmount"><?= __('refund_amount') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" id="refundCurrency">USD</span>
                            </div>
                            <input type="text" class="form-control" id="refundPassengerAmount" name="refund_amount" readonly required>
                        </div>
                        <small class="form-text text-muted" id="refundCalculationInfo">
                            <?= __('automatically_calculated_based_on_selected_method') ?>
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="description"><?= __('description') ?></label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-primary" onclick="saveRefundTicket()">
                    <i class="feather icon-save mr-2"></i><?= __('save') ?>
                </button>
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
  
                                  <!-- Required Js -->
                                    <script src="../assets/js/vendor-all.min.js"></script>
                                    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
                                    <script src="../assets/js/pcoded.min.js"></script>

                                    <!-- DataTables JS -->
                                    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
                                    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
                                    <script src="https://cdn.datatables.net/responsive/2.2.7/js/dataTables.responsive.min.js"></script>
                                    <script src="https://cdn.datatables.net/responsive/2.2.7/js/responsive.bootstrap4.min.js"></script>
                                
                        
                           
                                                                

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
            <div class="modal-body">
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
                                                <i class="feather icon-clock mr-1"></i><?= __('time') ?>
                                            </label>
                                            <input type="text" class="form-control" id="paymentTime" name="payment_time" 
                                                placeholder="HH:MM:SS" pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]" 
                                                title="Format: HH:MM:SS" required>
                                            <small class="form-text text-muted"><?= __('format_hours_minutes_seconds_24_hour') ?></small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="paymentAmount">
                                                <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                                            </label>
                                            <input type="number" class="form-control" id="paymentAmount" 
                                                   name="payment_amount" step="0.01" min="0.01" required 
                                                   placeholder="<?= __('enter_amount') ?>">
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

                                <div class="form-group">
                                    <label for="paymentDescription">
                                        <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                                    </label>
                                    <textarea class="form-control" id="paymentDescription" 
                                              name="payment_description" rows="2" required
                                              placeholder="<?= __('enter_payment_description') ?>"></textarea>
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
            <div class="modal-body">
                <form id="editTransactionForm">
                    <input type="hidden" id="editTransactionId" name="transaction_id">
                    <input type="hidden" id="editTicketId" name="ticket_id">
                    <input type="hidden" id="originalAmount" name="original_amount">
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="editPaymentDate">
                                <i class="feather icon-calendar mr-1"></i><?= __('date') ?>
                            </label>
                            <input type="date" class="form-control" id="editPaymentDate" name="payment_date" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="editPaymentTime">
                                <i class="feather icon-clock mr-1"></i><?= __('time') ?>
                            </label>
                            <input type="text" class="form-control" id="editPaymentTime" name="payment_time" 
                                placeholder="HH:MM:SS" pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]" 
                                title="Format: HH:MM:SS" required>
                            <small class="form-text text-muted"><?= __('format_hours_minutes_seconds_24_hour') ?></small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="editPaymentAmount">
                            <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                        </label>
                        <input type="number" step="0.01" class="form-control" id="editPaymentAmount" name="payment_amount" required>
                    </div>

                    <div class="form-group" id="editExchangeRateField" style="display: none;">
                        <label for="editExchangeRate">
                            <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                        </label>
                        <input type="number" class="form-control" id="editExchangeRate"
                            name="exchange_rate" step="0.01" placeholder="Enter exchange rate">
                    </div>

                    <div class="form-group">
                        <label for="editPaymentDescription">
                            <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                        </label>
                        <textarea class="form-control" id="editPaymentDescription" name="payment_description" rows="2" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                </button>
                <button type="button" id="updateTransactionBtn" class="btn btn-primary">
                    <i class="feather icon-save mr-1"></i><?= __('update_transaction') ?>
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
                    <!-- Client Filter Section -->
                    <div class="form-group">
                        <label for="clientFilter"><?= __('filter_by_client') ?></label>
                        <select class="form-control" id="clientFilter" name="clientFilter">
                            <option value=""><?= __('all_clients') ?></option>
                            <?php
                            // Fetch clients for the filter dropdown
                            $clientsQuery = "SELECT id, name FROM clients ORDER BY name";
                            $clientsResult = $conn->query($clientsQuery);
                            while ($client = $clientsResult->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($client['id']) . "'>" . 
                                     htmlspecialchars($client['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="clientForInvoice"><?= __('invoice_client') ?></label>
                        <select class="form-control" id="clientForInvoice" name="clientForInvoice" required>
                            <option value=""><?= __('select_client') ?></option>
                            <?php
                            // Reset the clients result pointer
                            $clientsResult->data_seek(0);
                            while ($client = $clientsResult->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($client['id']) . "'>" . 
                                     htmlspecialchars($client['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="invoiceComment"><?= __('comments_notes') ?></label>
                        <textarea class="form-control" id="invoiceComment" name="invoiceComment" rows="2"></textarea>
                    </div>

                    <!-- Include Charges Toggle -->
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="includeCharges" name="includeCharges">
                            <label class="custom-control-label" for="includeCharges"><?= __('include_charges_in_invoice') ?></label>
                        </div>
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
                                    <th><?= __('passenger') ?></th>
                                    <th><?= __('pnr') ?></th>
                                    <th><?= __('sector') ?></th>
                                    <th><?= __('flight') ?></th>
                                    <th><?= __('date') ?></th>
                                    <th><?= __('charges') ?></th>
                                    <th><?= __('refund_amount') ?></th>
                                </tr>
                            </thead>
                            <tbody id="ticketsForInvoiceBody">
                                <!-- Tickets will be loaded here dynamically -->
                            </tbody>
                            <tfoot>
                                <tr class="table-primary">
                                    <td colspan="6" class="text-right font-weight-bold"><?= __('total') ?>:</td>
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
<div id="floatingActionButton" class="position-fixed" style="bottom: 80px; z-index: 1050;">    <button type="button" class="btn btn-primary btn-lg shadow" id="launchMultiTicketInvoice" title="<?= __('generate_multi_ticket_invoice') ?>">
        <i class="feather icon-file-text"></i>
    </button>
</div>

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
<script src="js/refund_ticket/multi_ticket.js<?= $version ?>"></script>
<script src="js/refund_ticket/profile.js<?= $version ?>"></script>
<script src="js/refund_ticket/search.js<?= $version ?>"></script>
<script src="js/refund_ticket/transaction_manager.js<?= $version ?>"></script>
<script src="js/refund_ticket/datatable.js<?= $version ?>"></script>
<script src="js/refund_ticket/document_actions.js<?= $version ?>"></script>
<script src="js/refund_ticket/table_search.js<?= $version ?>"></script>
<script src="js/refund_ticket/select.js<?= $version ?>"></script>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>
<script>
function printRefundAgreement(ticketId) {
    if (!ticketId) {
        alert('<?= __('ticket_id_is_missing') ?>');
        return;
    }

    // Open the printable agreement page in a new window
    window.open('print_ticket_refund_agreement.php?id=' + ticketId, '_blank');
}

function deleteTicket(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Make the fetch request
            fetch('delete_ticket_rf.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: data.message || 'Ticket deleted successfully'
                    }).then(() => {
                        location.reload(); // Or call a table reload function instead of full reload
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Error deleting ticket'
                    });
                }
            })
            .catch(error => {
                console.error('Error deleting Ticket:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An unexpected error occurred'
                });
            });
        }
    });
}



                                        </script>
                                         <script>
                                    $(document).on('click', '.generate-invoice', function () {
                                    const ticketId = $(this).data('ticket-id');
                                    if (!ticketId) {
                                        alert('<?= __('ticket_id_is_missing') ?>');
                                        return;
                                    }
                                    window.location.href = `rt_generateInvoice.php?ticketId=${ticketId}`;
                                });

                                </script>
                                <script>
// Search functionality
$(document).ready(function() {
    $("#ticketSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("table tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});
</script>
</body>
</html>