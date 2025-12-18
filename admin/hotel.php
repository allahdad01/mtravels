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

// Database connection
require_once('../includes/db.php');

// Load hotel bookings using handler (similar to ticket listing)
include '../api/hotel/hotel_handler.php';

// Include utility functions
require_once('../includes/utils.php');

$paginationPattern = empty($search)
    ? '?page='
    : '?search=' . urlencode($search) . '&page=';

?>




<?php include '../includes/header.php'; ?>

<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../css/hotel/styles.css">


<!-- Main Content -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- Dashboard Stats -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="stat-card slide-up">
                                    <i class="feather icon-users mb-2" style="font-size: 24px;"></i>
                                    <h3><?= number_format($totalRecords) ?></h3>
                                    <p><?= __('total_bookings') ?></p>
                                </div>
                            </div>
                            <!-- Add more stat cards as needed -->
                        </div>

                        <!-- Toast Container -->
                        <div class="toast-container"></div>

                        <!-- Main Card -->
                        <div class="card shadow-sm fade-in">
                                <!-- Card Header with Actions -->
                                <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                                    <div class="d-flex align-items-center mb-3 mb-md-0">
                                        <i class="feather icon-list text-primary mr-2" style="font-size: 20px;"></i>
                                        <h5 class="mb-0"><?= __('hotel_bookings') ?></h5>
                                    </div>
                                    <div class="button-group d-flex flex-column flex-md-row align-items-stretch align-items-md-center w-100 w-md-auto">
                                        <div class="search-box mb-2 mb-md-0 mr-md-3 w-100 w-md-auto">
                                            <form class="input-group" method="get">
                                                <input type="text"
                                                       class="form-control"
                                                       id="searchBookings"
                                                       name="search"
                                                       value="<?= htmlspecialchars($search ?? '') ?>"
                                                       placeholder="<?= __('search_bookings') ?>">
                                                <div class="input-group-append">
                                                    <button class="btn btn-primary" type="submit">
                                                        <i class="feather icon-search mr-1"></i><?= __('search') ?>
                                                    </button>
                                                    <?php if (!empty($search)): ?>
                                                        <a href="hotel.php" class="btn btn-outline-secondary">
                                                            <i class="feather icon-x mr-1"></i><?= __('clear') ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </form>
                                        </div>
                                        <div class="d-flex flex-row justify-content-between justify-content-md-start w-100 w-md-auto">
                                            <a href="hotel_refunds.php" class="btn btn-outline-warning btn-sm mr-2 flex-fill flex-md-auto">
                                                <i class="feather icon-refresh-cw mr-1"></i><span class="d-none d-sm-inline"><?= __('view_refunds') ?></span><span class="d-inline d-sm-none">Refunds</span>
                                            </a>
                                            <button class="btn btn-primary btn-sm flex-fill flex-md-auto" data-toggle="modal" data-target="#addBookingModal">
                                                <i class="feather icon-plus mr-1"></i><span class="d-none d-sm-inline"><?= __('new_booking') ?></span><span class="d-inline d-sm-none">New</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Table Container -->
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="bookingsTable">
                                        <thead>
                                            <tr>
    
                                                <th class="border-0"><?= __('booking_id') ?></th>
                                                <th class="border-0"><?= __('guest') ?></th>
                                                <th class="border-0"><?= __('check_in_out') ?></th>
                                                <th class="border-0"><?= __('room_details') ?></th>
                                                <th class="border-0"><?= __('amount') ?></th>
                                                <th class="border-0"><?= __('status') ?></th>
                                                <th class="border-0 text-center" width="200"><?= __('actions') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="bookingsTableBody">
                                            <?php if (!empty($bookings)): ?>
                                                <?php foreach ($bookings as $booking): ?>
                                                    <tr class="align-middle">
    
                                                        <td>
                                                            <span class="font-weight-bold text-primary">#<?= getValue($booking, 'order_id') ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div>
                                                                    <span class="d-block font-weight-medium"><?= getValue($booking, 'guest_name') ?></span>
                                                                    <small class="text-muted">
                                                                        <i class="feather icon-phone mr-1"></i>
                                                                        <?= getValue($booking, 'contact_no') ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <i class="feather icon-calendar text-primary mr-2"></i>
                                                                <div>
                                                                    <div class="d-flex align-items-center mb-1">
                                                                        <span class="mr-2">IN</span>
                                                                        <span><?= getValue($booking, 'check_in_date') ? date('M d, Y', strtotime($booking['check_in_date'])) : 'N/A' ?></span>
                                                                    </div>
                                                                    <div class="d-flex align-items-center">
                                                                        <span class="mr-2">OUT</span>
                                                                        <span><?= getValue($booking, 'check_out_date') ? date('M d, Y', strtotime($booking['check_out_date'])) : 'N/A' ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <i class="feather icon-home text-info mr-2"></i>
                                                                <span><?= getValue($booking, 'accommodation_details') ?></span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <h6 class="mb-1 text-primary">
                                                                    <?= getValue($booking, 'currency') ?> <?= number_format(getValue($booking, 'sold_amount', 0), 2) ?>
                                                                </h6>
                                                                <small class="text-success">
                                                                    <i class="feather icon-trending-up mr-1"></i>
                                                                    Profit: <?= getValue($booking, 'currency') ?> <?= number_format(getValue($booking, 'profit', 0), 2) ?>
                                                                </small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <div class="mb-1">
                                                                    <span class="status-dot status-active"></span>
                                                                    <span>Sold to: <?= getValue($booking, 'client_name') ?></span>
                                                                </div>
                                                                <small class="text-muted">
                                                                    <i class="feather icon-user mr-1"></i>
                                                                    <?= __('created_by') ?>: <?= htmlspecialchars($booking['created_by']) ?>
                                                                </small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex justify-content-end">
                                                                <button type="button" class="btn btn-icon btn-light mr-2" 
                                                                        onclick="viewBooking(<?= $booking['id'] ?>)" 
                                                                        title="<?= __('view_details') ?>">
                                                                    <i class="feather icon-eye text-info"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-icon btn-light mr-2" 
                                                                        onclick="editBooking(<?= $booking['id'] ?>)"
                                                                        title="<?= __('edit_booking') ?>">
                                                                    <i class="feather icon-edit-2 text-warning"></i>
                                                                </button>
                                                                <?php
                                                                $isAgencyClient = false;
                                                                if (!empty($booking['sold_to'])) {
                                                                    try {
                                                                        $clientStmt = $pdo->prepare("SELECT client_type FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
                                                                        $clientStmt->execute([$booking['sold_to'], $tenant_id, $branch_id]);
                                                                        $clientRow = $clientStmt->fetch(PDO::FETCH_ASSOC);
                                                                        if ($clientRow) {
                                                                            $isAgencyClient = ($clientRow['client_type'] === 'agency');
                                                                        }
                                                                    } catch (PDOException $e) {
                                                                        error_log("Error checking client type: " . $e->getMessage());
                                                                    }
                                                                }
                                                                if ($isAgencyClient): ?>
                                                                <button type="button" class="btn btn-icon btn-light mr-2"
                                                                        onclick="manageTransactions(<?= $booking['id'] ?>)"
                                                                        title="<?= __('manage_transactions') ?>">
                                                                    <i class="fas fa-dollar-sign text-success"></i>
                                                                </button>
                                                                <?php endif; ?>
                                                                <div class="dropdown">
                                                                    <button type="button" class="btn btn-icon btn-light" 
                                                                            data-toggle="dropdown" aria-haspopup="true" 
                                                                            aria-expanded="false">
                                                                        <i class="feather icon-more-vertical"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu dropdown-menu-right">
                                                                        <a class="dropdown-item text-danger" href="#" 
                                                                           onclick="deleteBooking(<?= $booking['id'] ?>)">
                                                                            <i class="feather icon-trash-2 mr-2"></i>
                                                                            <?= __('delete_booking') ?>
                                                                        </a>
                                                                        <a class="dropdown-item" href="#" 
                                                                           onclick="openRefundModal(<?= $booking['id'] ?>, <?= $booking['sold_amount'] ?>, <?= $booking['profit'] ?>, '<?= $booking['currency'] ?>')">
                                                                            <i class="feather icon-refresh-ccw mr-2"></i>
                                                                            <?= __('process_refund') ?>
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-5">
                                                        
                                                        <h5 class="text-muted mb-2"><?= __('no_bookings_found') ?></h5>
                                                        <p class="text-muted mb-3"><?= __('start_by_adding_your_first_hotel_booking') ?></p>
                                                        <button class="btn btn-primary" data-toggle="modal" data-target="#addBookingModal">
                                                            <i class="feather icon-plus mr-1"></i><?= __('add_new_booking') ?>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if (!empty($bookings)): ?>
                                <div class="card-footer bg-white border-top">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <p class="text-muted mb-0">
                                                <?= generatePageInfo($currentPage, $itemsPerPage, $totalRecords) ?>
                                            </p>
                                        </div>
                                        <div class="col-auto">
                                            <nav>
                                                <?= generatePagination($currentPage, $totalPages, $paginationPattern) ?>
                                            </nav>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../modals/hotel/refund_modal.php'; ?>
<?php include '../modals/hotel/transaction_modal.php'; ?>
<?php include '../modals/hotel/edit_transaction_modal.php'; ?>
<?php include '../modals/hotel/add_hotel_modal.php'; ?>
<?php include '../modals/hotel/edit_hotel_modal.php'; ?>
<?php include '../modals/hotel/view_details_modal.php'; ?>
<?php include '../modals/hotel/multi_ticket.php'; ?>





<style>
    /* Transaction Modal Styles */
    .booking-summary {
        background: linear-gradient(to right, #f8f9fa, #ffffff);
    }

    .payment-section {
        padding: 0.5rem 0;
        transition: all 0.3s ease;
    }

    .payment-section:not(:last-child) {
        border-bottom: 1px solid #e9ecef;
    }

    /* Enhanced Table Styles */
    .table th {
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        color: #6c757d;
        background-color: #f8f9fa;
        border-top: none;
    }

    .table td {
        vertical-align: middle;
        padding: 1rem;
    }

    /* Animation for New Transactions */
    @keyframes highlightRow {
        from { background-color: rgba(64, 153, 255, 0.1); }
        to { background-color: transparent; }
    }

    .new-transaction {
        animation: highlightRow 2s ease-out;
    }

    /* Custom Scrollbar for Modal Body */
    .modal-body {
        max-height: 75vh;
        overflow-y: auto;
    }

    .modal-body::-webkit-scrollbar {
        width: 6px;
    }

    .modal-body::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .modal-body::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 3px;
    }

    .modal-body::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    /* Form Validation Styles */
    .was-validated .form-control:invalid:focus,
    .form-control.is-invalid:focus {
        border-color: var(--danger-color);
        box-shadow: 0 0 0 0.2rem rgba(255, 83, 112, 0.25);
    }

    .was-validated .form-control:valid:focus,
    .form-control.is-valid:focus {
        border-color: var(--success-color);
        box-shadow: 0 0 0 0.2rem rgba(46, 216, 182, 0.25);
    }

    /* Enhanced Button States */
    .btn {
        position: relative;
        overflow: hidden;
    }

    .btn::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 5px;
        height: 5px;
        background: rgba(255, 255, 255, 0.5);
        opacity: 0;
        border-radius: 100%;
        transform: scale(1, 1) translate(-50%);
        transform-origin: 50% 50%;
    }

    .btn:focus:not(:active)::after {
        animation: ripple 1s ease-out;
    }

    @keyframes ripple {
        0% {
            transform: scale(0, 0);
            opacity: 0.5;
        }
        100% {
            transform: scale(20, 20);
            opacity: 0;
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

    /* Refund Modal Styles */
    .btn-group-toggle .btn {
        transition: all 0.2s ease;
    }

    .btn-group-toggle .btn:not(:disabled):not(.disabled).active {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
    }

    .btn-outline-primary {
        color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .btn-outline-primary:hover {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
    }

    /* Loading State */
    .btn.loading {
        position: relative;
        color: transparent !important;
    }

    .btn.loading::after {
        content: '';
        position: absolute;
        width: 16px;
        height: 16px;
        top: 50%;
        left: 50%;
        margin-top: -8px;
        margin-left: -8px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

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

    /* Enhanced Form Styles */
    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
    }

    .input-group-text {
        background-color: #f8f9fa;
        border-color: #e9ecef;
    }

    /* Toast Close Button */
    .toast .close {
        background: none;
        border: none;
        font-size: 1.5rem;
        line-height: 1;
        color: #6c757d;
        opacity: 0.7;
        padding: 0;
        margin-left: auto;
    }

    .toast .close:hover {
        color: #000;
        opacity: 1;
    }

    /* Tooltip Enhancements */
    .tooltip {
        font-size: 0.75rem;
    }

    .tooltip-inner {
        padding: 0.5rem 1rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
</style>

<!-- Required Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<!-- Custom Scripts -->
<script src="../js/hotel/transactions.js"></script>
<script src="../js/hotel/bookings.js"></script>
<script src="../js/hotel/invoices.js"></script>
<script src="../js/hotel/refunds.js"></script>
<script src="../js/hotel/init.js"></script>
<script src="../js/hotel/toast.js"></script>
<script src="../js/hotel/extra.js"></script>
<script src="../js/hotel/refund_modal.js"></script>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>
