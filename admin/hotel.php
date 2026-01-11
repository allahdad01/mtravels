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


<!-- Main Content -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- Enhanced Page Header -->
                        <div class="page-header card">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h5 class="mb-0"><i class="feather icon-list mr-2"></i><?= __('hotel_bookings') ?></h5>
                                    <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;"><?= __('manage_hotel_bookings_efficiently') ?></p>
                                </div>
                                <div class="col-md-6 text-end">
                                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="feather icon-arrow-left mr-1"></i><?= __('back_to_dashboard') ?>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Dashboard Stats -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="feather icon-users mb-3 text-primary" style="font-size: 32px;"></i>
                                        <h3 class="mb-1"><?= number_format($totalRecords) ?></h3>
                                        <p class="text-muted mb-0"><?= __('total_bookings') ?></p>
                                    </div>
                                </div>
                            </div>
                            <!-- Add more stat cards as needed -->
                        </div>

                        <!-- Toast Container -->
                        <div class="toast-container"></div>

                        <!-- Main Card -->
                        <div class="card shadow-sm fade-in">
                                <!-- Enhanced Card Header with Actions -->
                                <div class="card-header">
                                    <h5><i class="feather icon-list mr-2"></i><?= __('hotel_bookings') ?></h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <form class="input-group" method="get">
                                                <input type="text"
                                                       class="form-control form-control-lg"
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
                                        <div class="col-md-6 text-end">
                                            <div class="d-flex flex-row justify-content-end">
                                                <button class="btn btn-primary btn-lg" data-toggle="modal" data-target="#addBookingModal">
                                                    <i class="feather icon-plus mr-1"></i><?= __('new_booking') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Enhanced Table Container -->
                                <div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
                                    <table class="table table-hover mb-0" id="bookingsTable" style="min-width: 1200px;">
                                        <thead>
                                            <tr>
                                                <th><?= __('booking_id') ?></th>
                                                <th><?= __('guest') ?></th>
                                                <th><?= __('check_in_out') ?></th>
                                                <th><?= __('room_details') ?></th>
                                                <th><?= __('amount') ?></th>
                                                <th><?= __('status') ?></th>
                                                <th class="text-center"><?= __('actions') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="bookingsTableBody">
                                            <?php if (!empty($bookings)): ?>
                                                <?php foreach ($bookings as $booking): ?>
                                                    <tr class="align-middle">
    
                                                        <td data-label="<?= __('booking_id') ?>">
                                                            <span class="font-weight-bold text-primary">#<?= getValue($booking, 'order_id') ?></span>
                                                        </td>
                                                        <td data-label="<?= __('guest') ?>">
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
                                                        <td data-label="<?= __('check_in_out') ?>">
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
                                                        <td data-label="<?= __('room_details') ?>">
                                                            <div class="d-flex align-items-center">
                                                                <i class="feather icon-home text-info mr-2"></i>
                                                                <span><?= getValue($booking, 'accommodation_details') ?></span>
                                                            </div>
                                                        </td>
                                                        <td data-label="<?= __('amount') ?>">
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
                                                        <td data-label="<?= __('status') ?>">
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
                                                        <td data-label="<?= __('actions') ?>">
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
                                                    <td colspan="8" class="text-center py-5" data-label="">
                                                        
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

                                <!-- Enhanced Pagination -->
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
    /* Enhanced custom styles for better layout and design */
    .page-header.card {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        color: #ffffff;
        border: none;
        margin-bottom: 20px;
        padding: 20px !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-radius: 10px;
    }

    .page-header.card .row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-header.card h5 {
        color: #ffffff;
        margin: 0;
        font-weight: 600;
    }

    .page-header.card .text-end {
        text-align: right;
    }

    .page-header.card .btn {
        background: rgba(255,255,255,0.2);
        color: #ffffff;
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 25px;
        transition: all 0.3s ease;
    }

    .page-header.card .btn:hover {
        background: rgba(255,255,255,0.3);
        border-color: rgba(255,255,255,0.5);
        transform: translateY(-1px);
    }

    .card {
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        border: none;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px 10px 0 0;
        padding: 1rem 1.5rem;
        border: none;
    }

    .card-header h5 {
        margin: 0;
        font-weight: 600;
        display: flex;
        align-items: center;
    }

    .progress {
        border-radius: 15px;
        overflow: hidden;
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
    }

    .progress-bar {
        transition: width 0.6s ease;
    }

    .badge {
        font-size: 0.85em;
        padding: 0.5em 0.75em;
        border-radius: 20px;
        font-weight: 500;
    }

    .badge-success {
        background-color: #28a745;
    }

    .badge-warning {
        background-color: #ffc107;
        color: #212529;
    }

    .badge-info {
        background-color: #17a2b8;
    }

    .table-responsive {
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid #dee2e6;
        max-height: 60vh;
        overflow-x: auto;
        overflow-y: auto;
    }
    
    /* Enhanced table responsiveness */
    .table-responsive::-webkit-scrollbar {
        height: 8px;
    }
    
    /* Enhanced table header styling */
    .table thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #f8f9fa;
    }
    
    /* Better mobile table header visibility */
    @media (max-width: 576px) {
        .table thead th {
            position: relative;
            background-color: #e9ecef;
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }
    }
    
    .table-responsive::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .table-responsive::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }
    
    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
    
    /* Mobile responsive adjustments */
    @media (max-width: 768px) {
        .table-responsive {
            max-height: 50vh;
        }
        
        .table tbody td {
            padding: 0.75rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .table thead th {
            padding: 0.75rem 0.5rem;
            font-size: 0.75rem;
        }
        
        /* Enable horizontal scrolling on small screens */
        @media (max-width: 576px) {
            .table-responsive {
                max-height: 50vh;
                overflow-x: auto;
                overflow-y: auto;
            }
            
            .table {
                min-width: 1000px; /* Ensure table has minimum width for horizontal scrolling */
            }
            
            .table tbody td {
                padding: 0.5rem 0.25rem;
                font-size: 0.8rem;
                white-space: nowrap; /* Prevent text wrapping */
            }
            
            .table thead th {
                padding: 0.5rem 0.25rem;
                font-size: 0.75rem;
                white-space: nowrap; /* Prevent header text wrapping */
            }
            
            /* Keep table rows as blocks but allow horizontal scrolling */
            .table tbody tr {
                display: table-row;
            }
        }
        
        /* Extra small screens - more aggressive horizontal scrolling */
        @media (max-width: 425px) {
            .table-responsive {
                max-height: 45vh;
            }
            
            .table {
                min-width: 900px;
            }
            
            .table tbody td {
                padding: 0.4rem 0.2rem;
                font-size: 0.75rem;
            }
            
            .table thead th {
                padding: 0.4rem 0.2rem;
                font-size: 0.7rem;
            }
        }
        
        /* Mobile layout improvements for search and buttons */
        @media (max-width: 768px) {
            .card-body .row.mb-3 {
                flex-direction: column;
            }
            
            .card-body .row.mb-3 .col-md-6 {
                width: 100%;
                margin-bottom: 1rem;
            }
            
            .card-body .row.mb-3 .col-md-6:last-child {
                margin-bottom: 0;
                text-align: left;
            }
            
            .card-body .row.mb-3 .d-flex.flex-row.justify-content-end {
                justify-content: flex-start !important;
            }
            
            .form-control.form-control-lg {
                font-size: 1rem;
                padding: 0.75rem;
            }
            
            .btn.btn-primary.btn-lg,
            .btn.btn-outline-secondary {
                font-size: 0.875rem;
                padding: 0.5rem 1rem;
                width: 100%;
                margin-bottom: 0.5rem;
            }
            
            .btn-group .btn:not(:last-child) {
                margin-right: 0;
            }
        }
        
        /* Very small screens */
        @media (max-width: 425px) {
            .form-control.form-control-lg {
                font-size: 0.875rem;
                padding: 0.625rem;
            }
            
            .btn.btn-primary.btn-lg,
            .btn.btn-outline-secondary {
                font-size: 0.8rem;
                padding: 0.5rem 0.75rem;
            }
        }
    }

    .table {
        margin-bottom: 0;
    }

    .table thead th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        color: #495057;
        padding: 1rem;
    }

    .table tbody tr:hover {
        background-color: #f1f3f4;
    }

    .table tbody td {
        padding: 1rem;
        vertical-align: middle;
    }

    .form-control {
        border-radius: 8px;
        border: 1px solid #ced4da;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        padding: 0.75rem;
    }

    .form-control:focus {
        border-color: #4099ff;
        box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
    }

    .btn-primary {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        border: none;
        border-radius: 25px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
    }

    .btn-secondary {
        border-radius: 25px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .alert {
        border-radius: 10px;
        border: none;
        padding: 1rem 1.5rem;
    }

    .alert-info {
        background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
        color: #0c5460;
    }

    .alert-success {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
    }

    .alert-danger {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
    }

    #estimated_cost {
        color: #28a745;
        font-weight: bold;
    }

    .h2 {
        font-size: 2.5rem;
    }

    .h4 {
        font-size: 1.5rem;
    }

    .h5 {
        font-size: 1.25rem;
    }

    .h6 {
        font-size: 1rem;
    }

    /* Additional Hotel-specific styles */
    .stat-card {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border: none;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

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
    #floatingActionButton .btn {
  width: 56px;
  height: 56px;
  border-radius: 50%;

}
</style>

<script>
    // Enhanced JavaScript for better user experience
    document.addEventListener('DOMContentLoaded', function() {
        // Add hover effects to cards
        const cards = document.querySelectorAll('.card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 4px 16px rgba(0,0,0,0.15)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';
            });
        });
        
        // Enhanced search functionality
        const searchInput = document.getElementById('searchBookings');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                // Add subtle animation to search input
                this.style.borderColor = '#4099ff';
                this.style.boxShadow = '0 0 0 0.2rem rgba(64, 153, 255, 0.25)';
                
                setTimeout(() => {
                    this.style.borderColor = '#ced4da';
                    this.style.boxShadow = 'none';
                }, 1000);
            });
        }
        
        // Enhanced button animations
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(btn => {
            btn.addEventListener('click', function() {
                this.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
        });
    });
</script>

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
