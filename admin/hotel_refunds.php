<?php
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
$tenant_id = $_SESSION['tenant_id'];
// Database connection
require_once('../includes/db.php');
    // Pagination setup
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $recordsPerPage = 10;
    $offset = ($page - 1) * $recordsPerPage;


    // Fetch refunds if table exists with pagination
    // Verify table existence safely
    $tableExists = false;
    try {
        $pdo->query("SELECT 1 FROM hotel_refunds LIMIT 1");
        $tableExists = true;
    } catch (PDOException $e) {
        $tableExists = false;
    }
    $refunds = [];
    $totalRefunds = 0;
    $totalPages = 0;

    if ($tableExists) {
        // First, count total refunds
        $countQuery = "
            SELECT COUNT(*) as total 
            FROM hotel_refunds r
            LEFT JOIN hotel_bookings h ON r.booking_id = h.id
            WHERE r.tenant_id = ?
        ";
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute([$tenant_id]);
        $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
        $totalRefunds = $countRow ? (int)$countRow['total'] : 0;
        $totalPages = ceil($totalRefunds / $recordsPerPage);

        // Then fetch paginated refunds
        $refundsQuery = "
            SELECT r.*, h.title, h.first_name, h.last_name, h.check_in_date, h.check_out_date, 
                   h.accommodation_details, h.currency as booking_currency,
                   u.name as processed_by_name, m.name as account_name,
                   s.name as supplier_name, c.name as client_name
            FROM hotel_refunds r
            LEFT JOIN hotel_bookings h ON r.booking_id = h.id
            LEFT JOIN users u ON r.processed_by = u.id
            LEFT JOIN main_account m ON h.paid_to = m.id
            LEFT JOIN suppliers s ON h.supplier_id = s.id
            LEFT JOIN clients c ON h.sold_to = c.id
            WHERE r.tenant_id = ?
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $pdo->prepare($refundsQuery);
        $stmt->bindValue(1, (int)$tenant_id, PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$recordsPerPage, PDO::PARAM_INT);
        $stmt->bindValue(3, (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

?>


    <style>
        /* Enhanced Card Styles */
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .card-header {
            background: linear-gradient(135deg, #4099ff, #3a7bd5);
            border-bottom: none;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }

        .card-header h5 {
            margin: 0;
            color: #fff;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Table Enhancements */
        .table {
            margin: 0;
        }

        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            color: #495057;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 1rem;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-top: 1px solid #f1f1f1;
        }

        .table-hover tbody tr {
            transition: all 0.3s ease;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(64, 153, 255, 0.05);
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }

        /* Badge Enhancements */
        .badge {
            padding: 0.5em 0.75em;
            border-radius: 8px;
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .badge-danger {
            background-color: rgba(255, 83, 112, 0.15);
            color: #FF5370;
        }

        .badge-warning {
            background-color: rgba(255, 182, 77, 0.15);
            color: #FFB64D;
        }

        /* Button Enhancements */
        .btn {
            border-radius: 8px;
            padding: 0.6rem 1.2rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4099ff, #3a7bd5);
            border: none;
            box-shadow: 0 4px 15px rgba(64, 153, 255, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #3a7bd5, #4099ff);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(64, 153, 255, 0.3);
        }

        /* Dropdown Enhancements */
        .dropdown-menu {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 0.5rem;
        }

        .dropdown-item {
            border-radius: 8px;
            padding: 0.6rem 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dropdown-item:hover {
            background-color: rgba(64, 153, 255, 0.05);
            color: #4099ff;
        }

        .dropdown-item i {
            font-size: 1.1em;
            opacity: 0.8;
        }

        /* Modal Enhancements */
        .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, #4099ff, #3a7bd5);
            border: none;
            padding: 1.25rem 1.5rem;
            color: white;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid #f1f1f1;
            padding: 1.25rem 1.5rem;
            background-color: #f8f9fa;
        }

        /* Form Control Enhancements */
        .form-control {
            border-radius: 8px;
            border: 1px solid #e9ecef;
            padding: 0.75rem 1.2rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #4099ff;
            box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.15);
        }

        /* Alert Enhancements */
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            background-color: #f8f9fa;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .alert-info {
            background-color: rgba(64, 153, 255, 0.1);
            color: #4099ff;
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* Breadcrumb Enhancements */
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .breadcrumb-item a {
            color: #4099ff;
            transition: all 0.3s ease;
        }

        .breadcrumb-item a:hover {
            color: #3a7bd5;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: "→";
            color: #6c757d;
        }

        /* Loading Animation */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #4099ff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .card-body {
                padding: 1rem;
            }
            
            .table th, .table td {
                padding: 0.75rem;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
            
            .btn {
                padding: 0.5rem 1rem;
            }
        }
    </style>

<?php 
    // Include the header
include '../includes/header.php';
?>
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
                                        <h5 class="m-b-10 d-flex align-items-center">
                                            <i class="feather icon-refresh-cw mr-2"></i>
                                            <?= __('hotel_refunds') ?>
                                        </h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item">
                                            <a href="dashboard.php">
                                                <i class="feather icon-home"></i>
                                            </a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <a href="hotel.php"><?= __('hotel_management') ?></a>
                                        </li>
                                        <li class="breadcrumb-item">
                                            <a href="javascript:"><?= __('hotel_refunds') ?></a>
                                        </li>
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
                                        <div class="card-header">
                                            <h5>
                                                <i class="feather icon-refresh-cw mr-2"></i>
                                                <?= __('hotel_refund_records') ?>
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <?php if (!$tableExists || empty($refunds)): ?>
                                                <div class="alert alert-info">
                                                    <i class="feather icon-info mr-2"></i>
                                                    <?= __('no_hotel_refunds_have_been_processed_yet') ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="table-responsive">
                                                    <table id="refundsTable" class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>#</th>
                                                                <th><?= __('booking_details') ?></th>
                                                                <th><?= __('refund_info') ?></th>
                                                                <th><?= __('amount') ?></th>
                                                                <th><?= __('date') ?></th>
                                                                <th class="text-center"><?= __('actions') ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($refunds as $index => $refund): ?>
                                                                <tr>
                                                                    <td><?= ($offset + $index + 1) ?></td>
                                                                    <td>
                                                                        <div class="d-flex flex-column">
                                                                            <span class="font-weight-bold text-dark">
                                                                                <?= htmlspecialchars($refund['title'] . ' ' . $refund['first_name'] . ' ' . $refund['last_name']) ?>
                                                                            </span>
                                                                            <div class="mt-2">
                                                                                <small class="text-muted d-block">
                                                                                    <i class="feather icon-calendar mr-1"></i>
                                                                                    <?= __('check_in') ?>: <?= date('M d, Y', strtotime($refund['check_in_date'])) ?>
                                                                                </small>
                                                                                <small class="text-muted d-block">
                                                                                    <i class="feather icon-calendar mr-1"></i>
                                                                                    <?= __('check_out') ?>: <?= date('M d, Y', strtotime($refund['check_out_date'])) ?>
                                                                                </small>
                                                                            </div>
                                                                            <small class="text-muted d-block mt-2">
                                                                                <i class="feather icon-home mr-1"></i>
                                                                                <?= htmlspecialchars($refund['accommodation_details']) ?>
                                                                            </small>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <div class="d-flex flex-column">
                                                                            <span class="badge badge-<?= $refund['refund_type'] === 'full' ? 'danger' : 'warning' ?> mb-2">
                                                                                <?= ucfirst($refund['refund_type']) ?> <?= __('refund') ?>
                                                                            </span>
                                                                            <small class="text-muted d-block">
                                                                                <i class="feather icon-info mr-1"></i>
                                                                                <?= htmlspecialchars($refund['reason']) ?>
                                                                            </small>
                                                                            <div class="mt-2">
                                                                                <small class="text-muted d-block">
                                                                                    <i class="feather icon-user mr-1"></i>
                                                                                    <?= __('client') ?>: <?= htmlspecialchars($refund['client_name']) ?>
                                                                                </small>
                                                                                <small class="text-muted d-block">
                                                                                    <i class="feather icon-briefcase mr-1"></i>
                                                                                    <?= __('supplier') ?>: <?= htmlspecialchars($refund['supplier_name']) ?>
                                                                                </small>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <div class="d-flex flex-column">
                                                                            <span class="font-weight-bold text-danger">
                                                                                <?= htmlspecialchars($refund['currency']) ?> <?= number_format($refund['refund_amount'], 2) ?>
                                                                            </span>
                                                                            <?php if (!empty($refund['exchange_rate']) && $refund['exchange_rate'] != 1): ?>
                                                                                <small class="text-muted mt-1">
                                                                                    <i class="feather icon-repeat mr-1"></i>
                                                                                    <?= __('exchange_rate') ?>: <?= number_format($refund['exchange_rate'], 4) ?>
                                                                                </small>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <div class="d-flex flex-column">
                                                                            <span class="font-weight-medium">
                                                                                <?= date('M d, Y', strtotime($refund['created_at'])) ?>
                                                                            </span>
                                                                            <small class="text-muted">
                                                                                <?= date('h:i A', strtotime($refund['created_at'])) ?>
                                                                            </small>
                                                                            <?php if ($refund['processed_by_name']): ?>
                                                                                <small class="text-muted mt-1">
                                                                                    <i class="feather icon-user mr-1"></i>
                                                                                    <?= htmlspecialchars($refund['processed_by_name']) ?>
                                                                                </small>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <div class="dropdown">
                                                                            <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                                                <i class="feather icon-more-horizontal"></i>
                                                                            </button>
                                                                            <div class="dropdown-menu dropdown-menu-right">
                                                                                <a class="dropdown-item" href="hotel.php?id=<?= $refund['booking_id'] ?>">
                                                                                    <i class="feather icon-file-text text-info"></i>
                                                                                    <?= __('view_booking') ?>
                                                                                </a>
                                                                                <?php if (!empty($refund['transaction_id'])): ?>
                                                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="viewTransaction(<?= $refund['transaction_id'] ?>)">
                                                                                        <i class="feather icon-credit-card text-success"></i>
                                                                                        <?= __('view_transaction') ?>
                                                                                    </a>
                                                                                <?php endif; ?>
                                                                                <?php if ($refund['processed'] != 1): ?>
                                                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="processRefundTransaction(<?= $refund['id'] ?>)">
                                                                                        <i class="feather icon-check-circle text-primary"></i>
                                                                                        <?= __('process_payment') ?>
                                                                                    </a>
                                                                                <?php endif; ?>
                                                                                <a class="dropdown-item" href="javascript:void(0)" onclick="printRefundAgreement(<?= $refund['id'] ?>)">
                                                                                    <i class="feather icon-printer text-primary"></i>
                                                                                    <?= __('print_agreement') ?>
                                                                                </a>
                                                                                <div class="dropdown-divider"></div>
                                                                                <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteRefund(<?= $refund['id'] ?>)">
                                                                                    <i class="feather icon-trash-2"></i>
                                                                                    <?= __('delete_refund') ?>
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                    
                                    <!-- Pagination Controls -->
                                    <nav aria-label="Refunds pagination" class="mt-3">
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

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="js/hotel_refund/transaction_manager.js"></script>
    <script src="js/hotel_refund/hotel_management.js"></script>
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

<!-- Hotel Refund Transaction Modal -->
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
                <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                    <!-- Hotel Refund Info Card -->
                    <div class="card mb-4 border-primary">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2"><?= __('hotel_refund_details') ?></h6>
                                    <p class="mb-1"><strong><?= __('guest_name') ?>:</strong> <span id="trans-guest-name"></span></p>
                                    <p class="mb-1"><strong><?= __('booking_id') ?>:</strong> <span id="trans-order-id"></span></p>
                                    <p class="mb-1"><strong><?= __('refund_type') ?>:</strong> <span id="refundType"></span></p>
                                    <p class="mb-1"><strong><?= __('hotel_name') ?>:</strong> <span id="refundHotel"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2"><?= __('financial_summary') ?></h6>
                                    <p class="mb-1"><strong><?= __('total_amount') ?>:</strong> <span id="totalAmount"></span></p>
                                    <p class="mb-1"><strong><?= __('exchange_rate') ?>:</strong> <span id="exchangeRateDisplay"></span></p>
                                    <p class="mb-1"><strong><?= __('exchanged_amount') ?>:</strong> <span id="exchangedAmount"></span></p>
                                    <div id="usdSection" style="display: none;">
                                        <p class="mb-1"><strong><?= __('paid_amount_usd') ?>:</strong> <span id="paidAmountUSD" class="text-success">USD 0.00</span></p>
                                        <p class="mb-1"><strong><?= __('remaining_amount_usd') ?>:</strong> <span id="remainingAmountUSD" class="text-danger">USD 0.00</span></p>
                                    </div>
                                    <div id="afsSection" style="display: none;">
                                        <p class="mb-1"><strong><?= __('paid_amount_afs') ?>:</strong> <span id="paidAmountAFS" class="text-success">AFS 0.00</span></p>
                                        <p class="mb-1"><strong><?= __('remaining_amount_afs') ?>:</strong> <span id="remainingAmountAFS" class="text-danger">AFS 0.00</span></p>
                                    </div>
                                    <div id="eurSection" style="display: none;">
                                        <p class="mb-1"><strong><?= __('paid_amount_eur') ?>:</strong> <span id="paidAmountEUR" class="text-success">EUR 0.00</span></p>
                                        <p class="mb-1"><strong><?= __('remaining_amount_eur') ?>:</strong> <span id="remainingAmountEUR" class="text-danger">EUR 0.00</span></p>
                                    </div>
                                    <div id="aedSection" style="display: none;">
                                        <p class="mb-1"><strong><?= __('paid_amount_aed') ?>:</strong> <span id="paidAmountAED" class="text-success">AED 0.00</span></p>
                                        <p class="mb-1"><strong><?= __('remaining_amount_aed') ?>:</strong> <span id="remainingAmountAED" class="text-danger">AED 0.00</span></p>
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
                                               name="exchange_rate" step="0.01" placeholder="<?= __('enter_exchange_rate') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="mainAccountId">
                                            <i class="feather icon-briefcase mr-1"></i><?= __('main_account') ?>
                                        </label>
                                        <select class="form-control" id="mainAccountId" name="main_account_id" required>
                                            <option value=""><?= __('select_main_account') ?></option>
                                            <?php 
                                            $accountsQuery = "SELECT id, name FROM main_account WHERE status = 'active' and tenant_id = $tenant_id";
                                            $accountsResult = $conn->query($accountsQuery);
                                            if ($accountsResult) {
                                                while ($account = $accountsResult->fetch_assoc()) {
                                                    echo '<option value="' . $account['id'] . '">' . htmlspecialchars($account['name']) . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
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
                <div class="modal-header">
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
                        <input type="hidden" id="editRefundId" name="booking_id">
                        <input type="hidden" id="editOriginalAmount" name="original_amount">
                        <input type="hidden" id="originalAmount" name="original_amount">
                        
                        <div class="form-row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editPaymentDate">
                                        <i class="feather icon-calendar mr-1"></i><?= __('date') ?>
                                    </label>
                                    <input type="date" class="form-control" id="editPaymentDate" name="payment_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editPaymentTime">
                                        <i class="feather icon-clock mr-1"></i><?= __('time') ?>
                                    </label>
                                    <input type="text" class="form-control" id="editPaymentTime" name="payment_time" 
                                        placeholder="HH:MM:SS" pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]" 
                                        title="Format: HH:MM:SS" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="editPaymentAmount">
                                <i class="feather icon-dollar-sign mr-1"></i><?= __('amount') ?>
                            </label>
                            <input type="number" step="0.01" class="form-control" id="editPaymentAmount" name="payment_amount" required>
                        </div>

                        <div class="form-group">
                            <label for="editPaymentCurrency">
                                <i class="feather icon-dollar-sign mr-1"></i><?= __('currency') ?>
                            </label>
                            <select class="form-control" id="editPaymentCurrency" name="payment_currency" disabled readonly>
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
                                   name="exchange_rate" step="0.01" placeholder="<?= __('enter_exchange_rate') ?>">
                        </div>

                        <div class="form-group">
                            <label for="editPaymentDescription">
                                <i class="feather icon-file-text mr-1"></i><?= __('description') ?>
                            </label>
                            <textarea class="form-control" id="editPaymentDescription" name="payment_description" rows="2" required></textarea>
                        </div>
                        
                        <div class="modal-footer px-0 pb-0">
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

</body>
</html>