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

// Database connection
require_once('../includes/db.php');

// First, define pagination variables
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Initialize variables
$bookings = [];


// Fetch bookings data with all necessary fields
try {
    $stmt = $pdo->prepare("
        SELECT 
            hb.id,
            CONCAT(hb.title, ' ', hb.first_name, ' ', hb.last_name) as guest_name,
            hb.gender,
            hb.order_id,
            hb.check_in_date,
            hb.check_out_date,
            hb.accommodation_details,
            hb.issue_date,
            hb.supplier_id,
            hb.contact_no,
            hb.base_amount,
            hb.sold_amount,
            hb.profit,
            hb.currency,
            hb.remarks,
            hb.receipt,
            s.name as supplier_name,
            c.name as client_name,
            ma.name as paid_to_name,
            u.name as created_by
        FROM hotel_bookings hb
        LEFT JOIN suppliers s ON hb.supplier_id = s.id
        LEFT JOIN clients c ON hb.sold_to = c.id
        LEFT JOIN main_account ma ON hb.paid_to = ma.id
        LEFT JOIN users u ON hb.created_by = u.id
        WHERE hb.tenant_id = :tenant_id
        ORDER BY hb.id DESC
        LIMIT :offset, :itemsPerPage
    ");
    
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':itemsPerPage', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':tenant_id', (int)$tenant_id, PDO::PARAM_INT);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For debugging
    error_log("Bookings fetched successfully: " . count($bookings) . " records");
} catch (PDOException $e) {
    error_log("Error fetching bookings: " . $e->getMessage());
    $bookings = [];
}

// Get total number of records for pagination
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM hotel_bookings WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $itemsPerPage);
    
    
    // Calculate start and end record numbers
    $startRecord = $offset + 1;
    $endRecord = min($offset + $itemsPerPage, $totalRecords);

} catch (PDOException $e) {
    error_log("Error fetching pagination data: " . $e->getMessage());
    $totalRecords = 0;
    $totalPages = 1;
    $startRecord = 0;
    $endRecord = 0;
}

// Validate current page
if ($currentPage > $totalPages && $totalPages > 0) {
    $currentPage = $totalPages;
    // Recalculate offset
    $offset = ($currentPage - 1) * $itemsPerPage;
}

// Ensure all variables have default values
$totalPages = $totalPages ?? 1;
$startRecord = $startRecord ?? 0;
$endRecord = $endRecord ?? 0;
// Include utility functions
require_once('../includes/utils.php');

?>


    <!-- Custom Dashboard Styles -->
    <style>
        :root {
            --primary-color: #4099ff;
            --secondary-color: #6c757d;
            --success-color: #2ed8b6;
            --info-color: #00bcd4;
            --warning-color: #ffc107;
            --danger-color: #ff5370;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-radius: 0.5rem;
            --box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --transition: all 0.3s ease;
        }

        body {
            background-color: #f4f7fa;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0,0,0,.05);
            padding: 1.25rem;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }

        .btn {
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            line-height: 32px;
            text-align: center;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #fff;
        }

        .badge {
            padding: 0.5em 0.75em;
            font-weight: 500;
            border-radius: 30px;
        }

        .form-control {
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            border: 1px solid #e9ecef;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
        }

        .modal-content {
            border: none;
            border-radius: var(--border-radius);
        }

        .modal-header {
            border-top-left-radius: var(--border-radius);
            border-top-right-radius: var(--border-radius);
            padding: 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-bottom-left-radius: var(--border-radius);
            border-bottom-right-radius: var(--border-radius);
        }

        /* Enhanced Table Styles */
        .table-hover tbody tr:hover {
            background-color: rgba(64, 153, 255, 0.05);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        .slide-up {
            animation: slideUp 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Enhanced Form Elements */
        .form-group label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }

        .input-group-text {
            background-color: #f8f9fa;
            border-color: #e9ecef;
        }

        /* Status Indicators */
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }

        .status-active { background-color: var(--success-color); }
        .status-pending { background-color: var(--warning-color); }
        .status-inactive { background-color: var(--danger-color); }

        /* Enhanced Cards */
        .stat-card {
            background: linear-gradient(45deg, #4099ff, #73b4ff);
            color: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            margin-bottom: 0;
            opacity: 0.8;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .card-header {
                padding: 1rem;
            }

            .btn {
                padding: 0.375rem 0.75rem;
            }

            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 1rem;
            }
        }
    </style>

<?php include '../includes/header.php'; ?>

<link rel="stylesheet" href="css/modal-styles.css">

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

                        <!-- Main Card -->
                        <div class="card shadow-sm fade-in">
                                <!-- Card Header with Actions -->
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <i class="feather icon-list text-primary mr-2" style="font-size: 20px;"></i>
                                        <h5 class="mb-0"><?= __('hotel_bookings') ?></h5>
                                    </div>
                                    <div class="button-group d-flex align-items-center">
                                        <div class="search-box mr-3">
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="searchBookings" 
                                                       placeholder="<?= __('search_bookings') ?>">
                                                <div class="input-group-append">
                                                    <span class="input-group-text">
                                                        <i class="feather icon-search"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="hotel_refunds.php" class="btn btn-outline-warning btn-sm mr-2">
                                            <i class="feather icon-refresh-cw mr-1"></i><?= __('view_refunds') ?>
                                        </a>
                                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addBookingModal">
                                            <i class="feather icon-plus mr-1"></i><?= __('new_booking') ?>
                                        </button>
                                    </div>
                                </div>

                                <!-- Table Container -->
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="bookingsTable">
                                        <thead>
                                            <tr>
                                                <th class="border-0" width="40">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input" id="selectAll">
                                                        <label class="custom-control-label" for="selectAll"></label>
                                                    </div>
                                                </th>
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
                                                            <div class="custom-control custom-checkbox">
                                                                <input type="checkbox" class="custom-control-input" 
                                                                       id="booking<?= getValue($booking, 'id') ?>">
                                                                <label class="custom-control-label" 
                                                                       for="booking<?= getValue($booking, 'id') ?>"></label>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="font-weight-bold text-primary">#<?= getValue($booking, 'order_id') ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="avatar bg-primary text-white mr-2">
                                                                    <?= strtoupper(substr(getValue($booking, 'first_name'), 0, 1)) ?>
                                                                </div>
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
                                                                        <span class="badge badge-light mr-2">IN</span>
                                                                        <span><?= getValue($booking, 'check_in_date') ? date('M d, Y', strtotime($booking['check_in_date'])) : 'N/A' ?></span>
                                                                    </div>
                                                                    <div class="d-flex align-items-center">
                                                                        <span class="badge badge-light mr-2">OUT</span>
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
                                                                <button type="button" class="btn btn-icon btn-light mr-2" 
                                                                        onclick="manageTransactions(<?= $booking['id'] ?>)"
                                                                        title="<?= __('manage_transactions') ?>">
                                                                    <i class="fas fa-dollar-sign text-success"></i>
                                                                </button>
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
                                                <?= generatePagination($currentPage, $totalPages) ?>
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

    <!-- Add New Booking Modal -->
    <div class="modal fade" id="addBookingModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title d-flex align-items-center">
                        <i class="feather icon-plus-circle mr-2"></i><?= __('add_new_hotel_booking') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="addHotelBookingForm" class="needs-validation" novalidate>
                        <!-- Form Sections -->
                        <div class="form-sections">
                            <!-- Guest Information Section -->
                            <div class="form-section mb-4">
                                <h6 class="text-primary mb-3">
                                    <i class="feather icon-user mr-2"></i><?= __('guest_information') ?>
                                </h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('title') ?></label>
                                            <select class="form-control custom-select" name="title" required>
                                                <option value=""><?= __('select_title') ?></option>
                                                <option value="Mr"><?= __('mr') ?></option>
                                                <option value="Mrs"><?= __('mrs') ?></option>
                                                <option value="Ms"><?= __('ms') ?></option>
                                            </select>
                                            <div class="invalid-feedback">
                                                <?= __('please_select_title') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('first_name') ?></label>
                                            <input type="text" class="form-control" name="first_name" required>
                                            <div class="invalid-feedback">
                                                <?= __('please_enter_first_name') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('last_name') ?></label>
                                            <input type="text" class="form-control" name="last_name" required>
                                            <div class="invalid-feedback">
                                                <?= __('please_enter_last_name') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('gender') ?></label>
                                            <select class="form-control custom-select" name="gender" required>
                                                <option value=""><?= __('select_gender') ?></option>
                                                <option value="Male"><?= __('male') ?></option>
                                                <option value="Female"><?= __('female') ?></option>
                                            </select>
                                            <div class="invalid-feedback">
                                                <?= __('please_select_gender') ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Booking Details Section -->
                            <div class="form-section mb-4">
                                <h6 class="text-primary mb-3">
                                    <i class="feather icon-file-text mr-2"></i><?= __('booking_details') ?>
                                </h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('order_id') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">#</span>
                                                </div>
                                                <input type="text" class="form-control" name="order_id" required>
                                                <div class="invalid-feedback">
                                                    <?= __('please_enter_order_id') ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('issue_date') ?></label>
                                            <input type="date" class="form-control" name="issue_date" 
                                                   value="<?php echo date('Y-m-d'); ?>" required>
                                            <div class="invalid-feedback">
                                                <?= __('please_select_issue_date') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('contact_number') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">
                                                        <i class="feather icon-phone"></i>
                                                    </span>
                                                </div>
                                                <input type="text" class="form-control" name="contact_no" required>
                                                <div class="invalid-feedback">
                                                    <?= __('please_enter_contact_number') ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stay Details Section -->
                            <div class="form-section mb-4">
                                <h6 class="text-primary mb-3">
                                    <i class="feather icon-calendar mr-2"></i><?= __('stay_details') ?>
                                </h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('check_in_date') ?></label>
                                            <input type="date" class="form-control" name="check_in_date" required>
                                            <div class="invalid-feedback">
                                                <?= __('please_select_check_in_date') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('check_out_date') ?></label>
                                            <input type="date" class="form-control" name="check_out_date" required>
                                            <div class="invalid-feedback">
                                                <?= __('please_select_check_out_date') ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label"><?= __('accommodation_details') ?></label>
                                    <textarea class="form-control" name="accommodation_details" rows="3" required></textarea>
                                    <div class="invalid-feedback">
                                        <?= __('please_enter_accommodation_details') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Financial Details Section -->
                            <div class="form-section mb-4">
                                <h6 class="text-primary mb-3">
                                    <i class="feather icon-dollar-sign mr-2"></i><?= __('financial_details') ?>
                                </h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('base_amount') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">$</span>
                                                </div>
                                                <input type="number" class="form-control" name="base_amount" 
                                                       step="0.01" required onchange="calculateProfit()">
                                                <div class="invalid-feedback">
                                                    <?= __('please_enter_base_amount') ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('sold_amount') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">$</span>
                                                </div>
                                                <input type="number" class="form-control" name="sold_amount" 
                                                       step="0.01" required onchange="calculateProfit()">
                                                <div class="invalid-feedback">
                                                    <?= __('please_enter_sold_amount') ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('profit') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">$</span>
                                                </div>
                                                <input type="number" class="form-control bg-light" name="profit" 
                                                       step="0.01" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Details Section -->
                            <div class="form-section">
                                <h6 class="text-primary mb-3">
                                    <i class="feather icon-info mr-2"></i><?= __('additional_details') ?>
                                </h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('supplier') ?></label>
                                            <select class="form-control select2" name="supplier_id" id="supplier" required>
                                                <option value=""><?= __('select_supplier') ?></option>
                                            </select>
                                            <div class="invalid-feedback">
                                                <?= __('please_select_supplier') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('sold_to') ?></label>
                                            <select class="form-control select2" name="sold_to" id="soldTo" required>
                                                <option value=""><?= __('select_client') ?></option>
                                            </select>
                                            <div class="invalid-feedback">
                                                <?= __('please_select_client') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('paid_to') ?></label>
                                            <select class="form-control select2" name="paid_to" required>
                                                <option value=""><?= __('select_account') ?></option>
                                            </select>
                                            <div class="invalid-feedback">
                                                <?= __('please_select_account') ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label"><?= __('currency') ?></label>
                                            <input type="text" class="form-control" name="currency" id="currency" readonly required>
                                            <div class="invalid-feedback">
                                                <?= __('please_select_currency') ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group mb-0">
                                    <label class="form-label"><?= __('remarks') ?></label>
                                    <textarea class="form-control" name="remarks" rows="2" 
                                              placeholder="<?= __('enter_any_additional_notes') ?>"></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                    </button>
                    <button type="button" class="btn btn-primary" onclick="addHotelBookingForm()">
                        <i class="feather icon-check mr-2"></i><?= __('add_booking') ?>
                    </button>
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

<style>
        .profile-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .profile-status {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #2ed8b6;
            border: 2px solid #fff;
        }

        .profile-status.online {
            background-color: #2ed8b6;
        }

        .info-item label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item p {
            font-weight: 500;
        }

        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 15px;
        }

        .activity-icon {
            position: absolute;
            left: -30px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #e3f2fd;
            color: #2196f3;
            text-align: center;
            line-height: 24px;
            font-size: 12px;
        }

        .modal-content {
            border: none;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .modal-header {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .modal-footer {
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        @media (max-width: 576px) {
            .profile-image {
                width: 100px;
                height: 100px;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
        }
        /* Updated Modal Styles */
        .modal-lg {
            max-width: 800px;
        }

        .floating-label {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .floating-label input,
        .floating-label textarea {
            height: auto;
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            width: 100%;
            font-size: 1rem;
        }

        .floating-label label {
            position: absolute;
            top: 50%;
            left: 0.75rem;
            transform: translateY(-50%);
            pointer-events: none;
            transition: all 0.2s ease;
            color: #6c757d;
            margin: 0;
            padding: 0 0.2rem;
            background-color: #fff;
            font-size: 1rem;
        }

        .floating-label textarea ~ label {
            top: 1rem;
            transform: translateY(0);
        }

        /* Active state - when input has value or is focused */
        .floating-label input:focus ~ label,
        .floating-label input:not(:placeholder-shown) ~ label,
        .floating-label textarea:focus ~ label,
        .floating-label textarea:not(:placeholder-shown) ~ label {
            top: 0;
            transform: translateY(-50%) scale(0.85);
            background-color: #fff;
            color: #4099ff;
            z-index: 1;
        }

        .floating-label input:focus,
        .floating-label textarea:focus {
            border-color: #4099ff;
            box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
            outline: none;
        }

        /* Ensure inputs have placeholder to trigger :not(:placeholder-shown) */
        .floating-label input,
        .floating-label textarea {
            placeholder: " ";
        }

        /* Rest of the styles remain the same */
        .profile-upload-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            transition: all 0.3s ease;
        }

        .upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            background: rgba(64, 153, 255, 0.9);
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-overlay:hover {
            transform: scale(1.1);
            background: rgba(64, 153, 255, 1);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .modal-lg {
                max-width: 95%;
                margin: 0.5rem auto;
            }

            .profile-upload-preview {
                width: 120px;
                height: 120px;
            }

            .modal-body {
                padding: 1rem !important;
            }

            .floating-label input,
            .floating-label textarea {
                padding: 0.6rem;
                font-size: 0.95rem;
            }

            .floating-label label {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 576px) {
            .profile-upload-preview {
                width: 100px;
                height: 100px;
            }

            .upload-overlay {
                width: 30px;
                height: 30px;
            }

            .modal-footer {
                flex-direction: column;
            }

            .modal-footer button {
                width: 100%;
                margin: 0.25rem 0;
            }
        }
</style>

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






<!-- Edit Booking Modal -->
<div class="modal fade" id="editBookingModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-edit-2 mr-2"></i><?= __('edit_booking') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editBookingForm">
                    <input type="hidden" id="edit_booking_id" name="booking_id">
                    
                    <!-- Personal Information -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?= __('title') ?></label>
                                <select class="form-control" id="title" name="title" required>
                                    <option value="Mr"><?= __('mr') ?></option>
                                    <option value="Mrs"><?= __('mrs') ?></option>
                                    <option value="Ms"><?= __('ms') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?= __('first_name') ?></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?= __('last_name') ?></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label><?= __('gender') ?></label>
                                <select class="form-control" id="gender" name="gender" required>
                                    <option value="Male"><?= __('male') ?></option>
                                    <option value="Female"><?= __('female') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Booking Details -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('order_id') ?></label>
                                <input type="text" class="form-control" id="order_id" name="order_id" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('issue_date') ?></label>
                                <input type="date" class="form-control" id="issue_date" name="issue_date" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('contact_number') ?></label>
                                <input type="text" class="form-control" id="contact_no" name="contact_no" required>
                            </div>
                        </div>
                    </div>

                    <!-- Stay Details -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('check_in_date') ?></label>
                                <input type="date" class="form-control" id="check_in_date" name="check_in_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('check_out_date') ?></label>
                                <input type="date" class="form-control" id="check_out_date" name="check_out_date" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?= __('accommodation_details') ?></label>
                        <textarea class="form-control" id="accommodation_details" name="accommodation_details" rows="3" required></textarea>
                    </div>

                    <!-- Financial Details -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('base_amount') ?></label>
                                <input type="number" class="form-control" id="base_amount" name="base_amount" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('sold_amount') ?></label>
                                <input type="number" class="form-control" id="sold_amount" name="sold_amount" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('profit') ?></label>
                                <input type="number" class="form-control" id="profit" name="profit" step="0.01" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Details -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('supplier') ?></label>
                                <select class="form-control" id="supplier_id" name="supplier_id" required>
                                    <!-- Will be populated dynamically -->
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('sold_to') ?></label>
                                <select class="form-control" id="sold_to" name="sold_to" required>
                                    <!-- Will be populated dynamically -->
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('paid_to') ?></label>
                                <select class="form-control" id="paid_to" name="paid_to" required>
                                    <!-- Will be populated dynamically -->
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('currency') ?></label>
                                <select class="form-control" id="edit_currency" name="currency" required>
                                    <option value="USD"><?= __('usd') ?></option>
                                    <option value="AFS"><?= __('afs') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                            <label><?= __('remarks') ?></label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                <button type="button" class="btn btn-primary" onclick="submitEditForm()"><?= __('save_changes') ?></button>
            </div>
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
                <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                    <!-- Hotel Info Card -->
                    <div class="card mb-4 border-primary">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2"><?= __('hotel_booking_details') ?></h6>
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
</style>

<script>
// Initialize form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Add animation to new transactions
function animateNewTransaction(row) {
    row.classList.add('new-transaction');
    setTimeout(() => {
        row.classList.remove('new-transaction');
    }, 2000);
}

// Format currency
function formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

// Update payment sections visibility
function updatePaymentSections(currency) {
    const usdSection = document.getElementById('usdSection');
    const afsSection = document.getElementById('afsSection');
    
    if (currency === 'USD') {
        usdSection.style.display = 'block';
        afsSection.style.display = 'none';
    } else if (currency === 'AFS') {
        usdSection.style.display = 'none';
        afsSection.style.display = 'block';
    } else {
        usdSection.style.display = 'none';
        afsSection.style.display = 'none';
    }
}

// Initialize tooltips
$(function () {
    $('[data-toggle="tooltip"]').tooltip();
});
</script>

<!-- Edit Transaction Modal -->
<div class="modal fade" id="editTransactionModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-edit mr-2"></i><?= __('edit_transaction') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="editTransactionForm">
                <div class="modal-body">
                    <input type="hidden" id="editTransactionId" name="transaction_id">
                    <input type="hidden" id="editBookingId" name="booking_id">
                    <input type="hidden" id="originalAmount" name="original_amount">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editPaymentDate">
                                    <i class="feather icon-calendar mr-1"></i><?= __('payment_date') ?>
                                </label>
                                <input type="date" class="form-control" id="editPaymentDate" name="payment_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editPaymentTime">
                                    <i class="feather icon-clock mr-1"></i><?= __('payment_time') ?>
                                </label>
                                <input type="time" class="form-control" id="editPaymentTime" name="payment_time" step="1" required>
                            </div>
                        </div>
                    </div>
                    
                   
                    
                    <div class="form-group">
                        <label for="editPaymentAmount">
                            <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                        </label>
                        <input type="number" class="form-control" id="editPaymentAmount" name="payment_amount" step="0.01" min="0.01" required>
                    </div>

                    <div class="form-group">
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check mr-1"></i><?= __('save_changes') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- View Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('booking_details') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="bookingDetails">
                    <!-- Details will be populated dynamically -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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
                                    <th><?= __('guest_name') ?></th>
                                    <th><?= __('order_id') ?></th>
                                    <th><?= __('check_in_date') ?></th>
                                    <th><?= __('check_out_date') ?></th>
                                    <th><?= __('accommodation_details') ?></th>
                                    <th><?= __('amount') ?></th>
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
<div id="floatingActionButton" class="position-fixed" style="bottom: 80px; right: 30px; z-index: 1050;">
    <button type="button" class="btn btn-primary btn-lg shadow" id="launchMultiTicketInvoice" title="<?= __('generate_multi_ticket_invoice') ?>">
        <i class="feather icon-file-text"></i>
    </button>
</div>
<style>
    #floatingActionButton {
        right: 30px;
    }
    
    /* RTL support - position on left side instead */
    html[dir="rtl"] #floatingActionButton {
        right: auto;
        left: 30px;
    }
</style>
<!-- Add script for multiple ticket invoice functionality -->

<!-- Refund Modal -->
<div class="modal fade" id="refundModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title d-flex align-items-center">
                    <i class="feather icon-refresh-ccw mr-2"></i><?= __('process_refund') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="refundForm" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" id="refund_booking_id" name="booking_id">
                    <input type="hidden" id="refund_original_amount" name="original_amount">
                    <input type="hidden" id="refund_original_profit" name="original_profit">
                    <input type="hidden" id="refund_currency" name="currency">
                    
                    <!-- Booking Summary Card -->
                    <div class="card bg-light border-0 mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted"><?= __('original_amount') ?></span>
                                <strong id="displayOriginalAmount" class="text-primary">-</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted"><?= __('original_profit') ?></span>
                                <strong id="displayOriginalProfit" class="text-success">-</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Refund Type -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="feather icon-tag mr-1"></i><?= __('refund_type') ?>
                        </label>
                        <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                            <label class="btn btn-outline-primary active">
                                <input type="radio" name="refund_type" value="full" checked 
                                       onchange="toggleRefundAmount()"> <?= __('full_refund') ?>
                            </label>
                            <label class="btn btn-outline-primary">
                                <input type="radio" name="refund_type" value="partial" 
                                       onchange="toggleRefundAmount()"> <?= __('partial_refund') ?>
                            </label>
                        </div>
                    </div>

                    <!-- Refund Amount (Hidden by default) -->
                    <div class="form-group" id="refundAmountGroup" style="display: none;">
                        <label class="form-label">
                            <i class="feather icon-dollar-sign mr-1"></i><?= __('refund_amount') ?>
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" id="refundCurrencySymbol">$</span>
                            </div>
                            <input type="number" class="form-control" id="refund_amount" name="refund_amount" 
                                   step="0.01" min="0.01">
                            <div class="invalid-feedback">
                                <?= __('please_enter_valid_refund_amount') ?>
                            </div>
                        </div>
                        <small class="form-text text-muted">
                            <?= __('maximum_refund_amount') ?>: <span id="maxRefundAmount">-</span>
                        </small>
                    </div>

                    <!-- Reason -->
                    <div class="form-group mb-0">
                        <label class="form-label">
                            <i class="feather icon-file-text mr-1"></i><?= __('reason_for_refund') ?>
                        </label>
                        <textarea class="form-control" id="refund_reason" name="reason" 
                                  rows="3" required placeholder="<?= __('enter_refund_reason') ?>"></textarea>
                        <div class="invalid-feedback">
                            <?= __('please_enter_refund_reason') ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-check mr-1"></i><?= __('process_refund') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Refund Modal Scripts
document.addEventListener('DOMContentLoaded', function() {
    const refundForm = document.getElementById('refundForm');
    const refundTypeInputs = refundForm.querySelectorAll('input[name="refund_type"]');
    const refundAmountGroup = document.getElementById('refundAmountGroup');
    const refundAmount = document.getElementById('refund_amount');
    const maxRefundAmount = document.getElementById('maxRefundAmount');
    const currentRate = document.getElementById('currentRate');

    // Toggle refund amount field
    function toggleRefundAmount() {
        const selectedType = refundForm.querySelector('input[name="refund_type"]:checked').value;
        refundAmountGroup.style.display = selectedType === 'partial' ? 'block' : 'none';
        
        if (selectedType === 'partial') {
            refundAmount.setAttribute('required', '');
        } else {
            refundAmount.removeAttribute('required');
        }
    }

    // Initialize refund modal
    function initRefundModal(amount, profit, currency) {
        // Update display values
        document.getElementById('displayOriginalAmount').textContent = formatCurrency(amount, currency);
        document.getElementById('displayOriginalProfit').textContent = formatCurrency(profit, currency);
        maxRefundAmount.textContent = formatCurrency(amount, currency);
        
        // Set currency symbol
        const currencySymbol = currency === 'USD' ? '$' : 'AFS';
        document.getElementById('refundCurrencySymbol').textContent = currencySymbol;
        
        // Set max refund amount
        refundAmount.max = amount;
        
        // Reset form
        refundForm.reset();
        refundForm.classList.remove('was-validated');
        
    }

    // Validate refund amount
    refundAmount.addEventListener('input', function() {
        const max = parseFloat(this.max);
        const value = parseFloat(this.value);
        
        if (value > max) {
            this.value = max;
        }
    });

    // Form validation
    refundForm.addEventListener('submit', function(event) {
        if (!this.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        this.classList.add('was-validated');
    });

    // Expose functions
    window.toggleRefundAmount = toggleRefundAmount;
    window.initRefundModal = initRefundModal;
});
</script>

<style>
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

    /* Toast Notifications */
    .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        min-width: 250px;
        padding: 1rem;
        background-color: white;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        border-radius: var(--border-radius);
        z-index: 1050;
        opacity: 0;
        transition: all 0.3s ease;
    }

    .toast.show {
        opacity: 1;
    }

    .toast.success {
        border-left: 4px solid var(--success-color);
    }

    .toast.error {
        border-left: 4px solid var(--danger-color);
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
<script src="../js/profile-management.js"></script>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>
