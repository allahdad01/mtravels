<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');
require_once('../includes/conn.php');

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Check if visa_refunds table exists
$tableCheckQuery = "SHOW TABLES LIKE 'visa_refunds'";
$tableExists = $conn->query($tableCheckQuery)->num_rows > 0;

// Fetch refunds if table exists with pagination
$refunds = [];
$totalRefunds = 0;
$totalPages = 0;

if ($tableExists) {
    // COUNT query
    $countQuery = "
        SELECT COUNT(*) as total 
        FROM visa_refunds r
        LEFT JOIN visa_applications v ON r.visa_id = v.id
        WHERE r.tenant_id = ?
    ";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $tenant_id);
    $stmt->execute();
    $countResult = $stmt->get_result();
    $totalRefunds = $countResult->fetch_assoc()['total'];
    $totalPages = ceil($totalRefunds / $recordsPerPage);
    $stmt->close();

    // Paginated fetch
    $refundsQuery = "
        SELECT r.*, v.applicant_name, v.passport_number, v.country, v.currency as visa_currency,
               u.name as created_by, m.name as account_name
        FROM visa_refunds r
        LEFT JOIN visa_applications v ON r.visa_id = v.id
        LEFT JOIN users u ON r.processed_by = u.id
        LEFT JOIN main_account_transactions t ON r.transaction_id = t.id
        LEFT JOIN main_account m ON t.main_account_id = m.id
        WHERE r.tenant_id = ?
        ORDER BY r.refund_date DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($refundsQuery);
    $stmt->bind_param("iii", $tenant_id, $recordsPerPage, $offset); // Correct 3 params
    $stmt->execute();
    $refundsResult = $stmt->get_result();
    $refunds = $refundsResult->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}


?>


    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="css/modal-styles.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap4.min.css">

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
                                        <h5 class="m-b-10"><?= __('visa_refunds') ?></h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item"><a href="visa.php"><?= __('visa_management') ?></a></li>
                                        <li class="breadcrumb-item"><a href="javascript:"><?= __('visa_refunds') ?></a></li>
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
                                <div class="col-sm-12">
                                    <div class="card">
                                        <div class="card-header bg-primary text-white">
                                            <h5><i class="feather icon-refresh-cw mr-2"></i><?= __('visa_refund_records') ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <?php if (!$tableExists || empty($refunds)): ?>
                                                <div class="alert alert-info">
                                                    <i class="feather icon-info mr-2"></i><?= __('no_visa_refunds_have_been_processed_yet') ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="table-responsive">
                                                    <table id="refundsTable" class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th><?= __('id') ?></th>
                                                                <th><?= __('visa_details') ?></th>
                                                                <th><?= __('refund_info') ?></th>
                                                                <th><?= __('amount') ?></th>
                                                                <th><?= __('date') ?></th>
                                                                <th><?= __('actions') ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($refunds as $index => $refund): ?>
                                                                <tr>
                                                                    <td><?= ($offset + $index + 1) ?></td>
                                                                    <td>
                                                                        <div class="d-flex flex-column">
                                                                            <span class="font-weight-bold">
                                                                                <?= !empty($refund['applicant_name']) ? htmlspecialchars($refund['applicant_name']) : 'N/A' ?>
                                                                            </span>
                                                                            <small class="text-muted">
                                                                                Passport: <?= !empty($refund['passport_number']) ? htmlspecialchars($refund['passport_number']) : 'N/A' ?>
                                                                            </small>
                                                                            <small class="text-muted">
                                                                                Country: <?= !empty($refund['country']) ? htmlspecialchars($refund['country']) : 'N/A' ?>
                                                                            </small>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <div class="d-flex flex-column">
                                                                            <span class="badge badge-<?= $refund['refund_type'] === 'full' ? 'danger' : 'warning' ?>">
                                                                                <?= ucfirst($refund['refund_type']) ?> <?= __('refund') ?>
                                                                            </span>
                                                                            <small class="text-muted mt-1">
                                                                                <?= htmlspecialchars($refund['reason']) ?>
                                                                            </small>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <span class="font-weight-bold text-danger">
                                                                            <?= htmlspecialchars($refund['currency']) ?> <?= number_format($refund['refund_amount'], 2) ?>
                                                                        </span>
                                                                        <?php if (!empty($refund['exchange_rate']) && $refund['exchange_rate'] != 1): ?>
                                                                        <br>
                                                                        <small class="text-muted">
                                                                            <?= __('exchange_rate') ?>: <?= number_format($refund['exchange_rate'], 4) ?>
                                                                        </small>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <?= date('M d, Y', strtotime($refund['refund_date'])) ?>
                                                                        <br>
                                                                        <small class="text-muted">
                                                                            <?= date('h:i A', strtotime($refund['refund_date'])) ?>
                                                                        </small>
                                                                        <br>
                                                                        <small class="text-muted">
                                                                            <?= __('created_by') ?>: <?= htmlspecialchars($refund['created_by']) ?>
                                                                        </small>
                                                                    </td>
                                                                   
                                                                    <td>
                                                                        
                                                                        <?php if ($refund['processed'] == 0): ?>
                                                                        <button type="button" class="btn btn-success btn-sm" onclick="processRefundTransaction(<?= $refund['id'] ?>)">
                                                                            <i class="fas fa-check"></i> Process
                                                                        </button>
                                                                        <?php endif; ?>
                                                                        <button type="button" class="btn btn-info btn-sm" onclick="printRefundAgreement(<?= $refund['id'] ?>)">
                                                                            <i class="fas fa-print"></i> Print Agreement
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                    
                                    <!-- Pagination Controls -->
                                    <nav aria-label="Visa Refunds pagination" class="mt-3">
                                        <ul class="pagination justify-content-center">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=1" aria-label="First">
                                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                                    </a>
                                                </li>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                                                        <span aria-hidden="true">&laquo;</span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>

                                            <?php 
                                            // Show page numbers
                                            $startPage = max(1, $page - 2);
                                            $endPage = min($totalPages, $page + 2);
                                            
                                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>

                                            <?php if ($page < $totalPages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                                                        <span aria-hidden="true">&raquo;</span>
                                                    </a>
                                                </li>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?= $totalPages ?>" aria-label="Last">
                                                        <span aria-hidden="true">&raquo;&raquo;</span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                        
                                        <!-- Pagination Info -->
                                        <div class="text-center mt-2 text-muted">
                                            <?= sprintf(__('showing_page_x_of_y'), $page, $totalPages) ?> 
                                            (<?= sprintf(__('total_x_refunds'), $totalRefunds) ?>)
                                        </div>
                                    </nav>
                                </div>
                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- [ Main Content ] end -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <!-- Refund Transaction Modal -->
    <div class="modal fade" id="refundTransactionModal" tabindex="-1" role="dialog">
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
                    <!-- Visa Info Card -->
                    <div class="card mb-4 border-primary">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2"><?= __('visa_refund_details') ?></h6>
                                    <p class="mb-1"><strong><?= __('visa_id') ?>:</strong> <span id="transactionVisaId"></span></p>
                                    <div id="refundInfoSection">
                                        <p class="mb-1"><strong><?= __('refund_type') ?>:</strong> <span id="refundType"></span></p>
                                        <p class="mb-1"><strong><?= __('reason') ?>:</strong> <span id="refundReason"></span></p>
                                        <p class="mb-1"><strong><?= __('applicant') ?>:</strong> <span id="refundApplicant"></span></p>
                                        <p class="mb-1"><strong><?= __('passport') ?>:</strong> <span id="refundPassport"></span></p>
                                    </div>
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
                                <form id="visaTransactionForm">
                                    <input type="hidden" id="refund_id" name="refund_id">
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
                                                <input type="hidden" id="originalAmount" name="original_amount">
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
                                        <div class="col-md-6">
                                            <div class="form-group" id="exchangeRateField" style="display: none;">
                                                <label for="transactionExchangeRate">
                                                    <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                                                </label>
                                                <input type="number" class="form-control" id="transactionExchangeRate"
                                                       name="exchange_rate" step="0.01" placeholder="Enter exchange rate">
                                            </div>
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
                                    
                                    <div class="form-group">
                                        <label for="mainAccountId">
                                            <i class="feather icon-briefcase mr-1"></i><?= __('main_account') ?>
                                        </label>
                                        <select class="form-control" id="mainAccountId" name="main_account_id" required>
                                            <option value=""><?= __('select_main_account') ?></option>
                                            <?php 
                                            $accountsQuery = "SELECT id, name 
                                                            FROM main_account 
                                                            WHERE status = 'active' AND tenant_id = ?";

                                            $stmt = $conn->prepare($accountsQuery);
                                            $stmt->bind_param("i", $tenant_id); // "i" because tenant_id is an integer
                                            $stmt->execute();
                                            $accountsResult = $stmt->get_result();

                                            if ($accountsResult) {
                                                while ($account = $accountsResult->fetch_assoc()) {
                                                    echo '<option value="' . $account['id'] . '">' . htmlspecialchars($account['name']) . '</option>';
                                                }
                                            }

                                            $stmt->close();
                                            ?>
                                        </select>
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
                        <i class="feather icon-edit-2 mr-2"></i><?= __('edit_transaction') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editTransactionForm">
                        <input type="hidden" id="editTransactionId" name="transaction_id">
                        <input type="hidden" id="editRefundId" name="visa_id">
                        <input type="hidden" id="editOriginalAmount" name="original_amount">
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
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="editPaymentAmount">
                                    <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                                </label>
                                <input type="number" step="0.01" class="form-control" id="editPaymentAmount" name="payment_amount" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="editPaymentCurrency">
                                    <i class="feather icon-dollar-sign mr-1"></i><?= __('currency') ?>
                                </label>
                                <select class="form-control" id="editPaymentCurrency" name="payment_currency" required>
                                    <option value=""><?= __('select_currency') ?></option>
                                    <option value="USD"><?= __('usd') ?></option>
                                    <option value="AFS"><?= __('afs') ?></option>
                                    <option value="EUR"><?= __('eur') ?></option>
                                    <option value="DARHAM"><?= __('darham') ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" id="editExchangeRateField" style="display: none;">
                            <label for="editTransactionExchangeRate">
                                <i class="feather icon-refresh-cw mr-1"></i><?= __('exchange_rate') ?>
                            </label>
                            <input type="number" class="form-control" id="editTransactionExchangeRate"
                                   name="exchange_rate" step="0.01" placeholder="Enter exchange rate">
                        </div>

                        <div class="form-group">
                            <label for="editPaymentDescription">
                                <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                            </label>
                            <textarea class="form-control" id="editPaymentDescription" name="payment_description" rows="2" required></textarea>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather icon-save mr-1"></i><?= __('save_changes') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/ripple.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    <script src="../assets/js/menu-setting.min.js"></script>
    <script src="js/visa_refund/transaction_manager.js"></script>
    <script src="js/visa_refund/refund_management.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

   
    <script>
$(document).ready(function() {
    $('#refundsTable').DataTable({
        responsive: true,
        pageLength: <?= $recordsPerPage ?>,
        lengthChange: true,
        searching: true,
        ordering: true,
        paging: false,  // Disable DataTables pagination
        columns: [
            { width: '5%' },   // ID
            { width: '20%' },  // Visa Details
            { width: '15%' },  // Refund Info
            { width: '10%' },  // Amount
            { width: '15%' },  // Date
            { width: '15%' }   // Actions
        ]
    });
});
</script>
</body>
</html> 