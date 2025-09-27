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

// Check if umrah_refunds table exists
$tableCheckQuery = "SHOW TABLES LIKE 'umrah_refunds'";
$tableExists = $conn->query($tableCheckQuery)->num_rows > 0;

// Fetch refunds if table exists with pagination
$refunds = [];
$totalRefunds = 0;
$totalPages = 0;

if ($tableExists) {
    // First, count total refunds
    $countQuery = "
        SELECT COUNT(*) as total 
        FROM umrah_refunds r 
        LEFT JOIN umrah_bookings um ON r.booking_id = um.booking_id
        WHERE r.tenant_id = $tenant_id
    ";
    $countResult = $conn->query($countQuery);
    $totalRefunds = $countResult ? $countResult->fetch_assoc()['total'] : 0;
    $totalPages = ceil($totalRefunds / $recordsPerPage);

    // Then fetch paginated refunds
    $refundsQuery = "
        SELECT r.*, um.name, um.flight_date, um.return_date, um.room_type, um.duration, um.price, um.sold_price, um.paid, um.received_bank_payment, um.bank_receipt_number, um.due, um.profit,
               f.package_type, um.currency as booking_currency,
               u.name as processed_by_name, m.name as account_name,
               s.name as supplier_name, c.name as client_name
        FROM umrah_refunds r
        LEFT JOIN umrah_bookings um ON r.booking_id = um.booking_id
        LEFT JOIN families f ON um.family_id = f.family_id
        LEFT JOIN users u ON r.processed_by = u.id
        LEFT JOIN main_account m ON um.paid_to = m.id
        LEFT JOIN suppliers s ON um.supplier = s.id
        LEFT JOIN clients c ON um.sold_to = c.id
        WHERE r.tenant_id = $tenant_id
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($refundsQuery);
    $stmt->bind_param("ii", $recordsPerPage, $offset);
    $stmt->execute();
    $refundsResult = $stmt->get_result();
    
    if ($refundsResult) {
        $refunds = $refundsResult->fetch_all(MYSQLI_ASSOC);
    }
}

?>


    <style>
        /* Modern Refunds Page Styles */
        .refunds-container {
            background-color: #f8f9fe;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .refunds-header {
            background: linear-gradient(135deg, #4099ff, #73b4ff);
            color: white;
            padding: 1.5rem;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .refunds-header h5 {
            margin: 0;
            display: flex;
            align-items: center;
            font-weight: 600;
        }

        .refunds-header .btn-group .btn {
            background-color: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }

        .refunds-header .btn-group .btn:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .refunds-table {
            background-color: white;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
        }

        .refunds-table thead {
            background-color: #f1f3f9;
            color: #4099ff;
        }

        .refunds-table tbody tr {
            transition: background-color 0.3s ease;
        }

        .refunds-table tbody tr:hover {
            background-color: rgba(64, 153, 255, 0.05);
        }

        .refund-status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .refund-status-full {
            background-color: rgba(255, 87, 34, 0.1);
            color: #FF5722;
        }

        .refund-status-partial {
            background-color: rgba(255, 152, 0, 0.1);
            color: #FF9800;
        }

        .refund-actions .dropdown-menu {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .refund-actions .dropdown-item {
            transition: all 0.3s ease;
        }

        .refund-actions .dropdown-item:hover {
            background-color: #f1f3f9;
            transform: translateX(5px);
        }

        @media (max-width: 768px) {
            .refunds-header {
                flex-direction: column;
                text-align: center;
            }

            .refunds-header .btn-group {
                margin-top: 1rem;
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
                                        <h5 class="m-b-10"><?= __('umrah_refunds') ?></h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item"><a href="umrah.php"><?= __('umrah_management') ?></a></li>
                                        <li class="breadcrumb-item"><a href="javascript:"><?= __('umrah_refunds') ?></a></li>
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
                                    <div class="card refunds-container">
                                        
                                        <!-- Refunds Page Header -->
                                        <div class="refunds-header">
                                            <!-- Page Title -->
                                            <h5>
                                                <i class="feather icon-refresh-cw mr-2"></i>
                                                <?= __('umrah_refund_records') ?>
                                            </h5>
                                        </div>

                                        <!-- Refunds Table Container -->
                                        <div class="card-body p-0 refunds-table">
                                            <!-- No Refunds Message -->
                                            <?php if (!$tableExists || empty($refunds)): ?>
                                                <div class="alert alert-info m-3">
                                                    <i class="feather icon-info mr-2"></i>
                                                    <?= __('no_umrah_refunds_have_been_processed_yet') ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="table-responsive">
                                                    <table class="table table-hover mb-0" id="umrahRefundsTable">
                                                        <thead>
                                                            <tr>
                                                                <th class="text-center">#</th>
                                                                <th><?= __('pilgrim_details') ?></th>
                                                                <th><?= __('refund_info') ?></th>
                                                                <th><?= __('financial_details') ?></th>
                                                                <th><?= __('processed_on') ?></th>
                                                                <th class="text-center"><?= __('actions') ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($refunds as $index => $refund): ?>
                                                                <tr>
                                                                    <td class="text-center font-weight-bold"><?= ($offset + $index + 1) ?></td>
                                                                    <td>
                                                                        <div class="d-flex flex-column">
                                                                            <span class="font-weight-bold">
                                                                                <?= htmlspecialchars($refund['name']) ?>
                                                                            </span>
                                                                            <small class="text-muted">
                                                                                <?= __('flight_date') ?>: <?= date('M d, Y', strtotime($refund['flight_date'])) ?>
                                                                            </small>
                                                                            <small class="text-muted">
                                                                                <?= __('package') ?>: <?= htmlspecialchars($refund['package_type']) ?>
                                                                            </small>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <span class="refund-status-badge 
                                                                            <?= $refund['refund_type'] === 'full' ? 'refund-status-full' : 'refund-status-partial' ?>">
                                                                            <?= ucfirst($refund['refund_type']) ?> <?= __('refund') ?>
                                                                        </span>
                                                                        <div class="mt-1">
                                                                            <small class="text-muted d-block">
                                                                                <?= __('reason') ?>: <?= htmlspecialchars($refund['reason']) ?>
                                                                            </small>
                                                                            <small class="text-muted">
                                                                                <?= __('client') ?>: <?= htmlspecialchars($refund['client_name']) ?>
                                                                            </small>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <div class="d-flex flex-column">
                                                                            <span class="font-weight-bold text-danger">
                                                                                <?= htmlspecialchars($refund['currency']) ?> 
                                                                                <?= number_format($refund['refund_amount'], 2) ?>
                                                                            </span>
                                                                            <?php if (!empty($refund['exchange_rate']) && $refund['exchange_rate'] != 1): ?>
                                                                                <small class="text-muted">
                                                                                    <?= __('exchange_rate') ?>: 
                                                                                    <?= number_format($refund['exchange_rate'], 4) ?>
                                                                                </small>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <?= date('M d, Y', strtotime($refund['created_at'])) ?>
                                                                        <br>
                                                                        <small class="text-muted">
                                                                            <?= date('h:i A', strtotime($refund['created_at'])) ?>
                                                                        </small>
                                                                        <?php if ($refund['processed_by_name']): ?>
                                                                            <br>
                                                                            <small class="text-muted">
                                                                                <?= __('by') ?>: <?= htmlspecialchars($refund['processed_by_name']) ?>
                                                                            </small>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td class="text-center refund-actions">
                                                                        <div class="dropdown">
                                                                            <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                                                <i class="feather icon-more-horizontal"></i>
                                                                            </button>
                                                                            <div class="dropdown-menu dropdown-menu-right">
                                                                                <a class="dropdown-item" href="umrah.php?id=<?= $refund['booking_id'] ?>">
                                                                                    <i class="feather icon-file-text text-info mr-2"></i><?= __('view_booking') ?>
                                                                                </a>
                                                                                <?php if (!empty($refund['transaction_id'])): ?>
                                                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="viewTransaction(<?= $refund['transaction_id'] ?>)">
                                                                                        <i class="feather icon-credit-card text-success mr-2"></i><?= __('view_transaction') ?>
                                                                                    </a>
                                                                                <?php endif; ?>
                                                                                <?php if ($refund['processed'] != 1): ?>
                                                                                    <a class="dropdown-item" href="javascript:void(0)" 
                                                                                       onclick="processRefundTransaction(<?= $refund['id'] ?>)">
                                                                                        <i class="feather icon-check-circle text-primary mr-2"></i><?= __('process_payment') ?>
                                                                                    </a>
                                                                                <?php endif; ?>
                                                                                <a class="dropdown-item text-danger" href="javascript:void(0)" 
                                                                                   onclick="deleteRefund(<?= $refund['id'] ?>)">
                                                                                    <i class="feather icon-trash-2 mr-2"></i><?= __('delete_refund') ?>
                                                                                </a>
                                                                                <a class="dropdown-item" href="javascript:void(0)" onclick="printRefundAgreement(<?= $refund['id'] ?>)">
                                                                                    <i class="fas fa-print text-info mr-2"></i><?= __('print_agreement') ?>
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                    
                                                    <!-- Pagination Controls -->
                                                    <nav aria-label="Umrah Refunds pagination" class="mt-3">
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

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Add SweetAlert2 for better alerts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <!-- Add DataTables for advanced table interactions -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>

    <!-- Add Animate.css for smooth animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="js/umrah_refund/transaction_manager.js"></script>
    <script src="js/umrah_refund/umrah_management.js"></script>


    <script>
    $(document).ready(function() {
        $('#umrahRefundsTable').DataTable({
            responsive: true,
            pageLength: <?= $recordsPerPage ?>,
            lengthChange: true,
            searching: true,
            ordering: true,
            paging: false,  // Disable DataTables pagination
            columns: [
                { width: '5%' },   // ID
                { width: '20%' },  // Pilgrim Details
                { width: '15%' },  // Refund Info
                { width: '15%' },  // Financial Details
                { width: '15%' },  // Processed On
                { width: '10%' }   // Actions
            ]
        });
    });
    </script>

    <!-- Transaction Modal -->
    <div class="modal fade select-modal" id="refundTransactionModal" tabindex="-1" role="dialog">
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
                    <!-- Umrah Info Card -->
                    <div class="card mb-4 border-primary">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2"><?= __('umrah_refund_details') ?></h6>
                                    <p class="mb-1"><strong><?= __('booking_id') ?>:</strong> <span id="transactionBookingId"></span></p>
                                    <div id="refundInfoSection">
                                        <p class="mb-1"><strong><?= __('refund_type') ?>:</strong> <span id="refundType"></span></p>
                                        <p class="mb-1"><strong><?= __('reason') ?>:</strong> <span id="refundReason"></span></p>
                                        <p class="mb-1"><strong><?= __('pilgrim') ?>:</strong> <span id="refundGuest"></span></p>
                                        <p class="mb-1"><strong><?= __('umrah_package') ?>:</strong> <span id="refundUmrah"></span></p>
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
                                            <th><?= __('account') ?></th>
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
                                <form id="hotelTransactionForm" novalidate>
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
                                                <select class="form-control" id="paymentCurrency" name="payment_currency" required aria-describedby="currencyHelp">
                                                    <option value=""><?= __('select_currency') ?></option>
                                                    <option value="USD"><?= __('usd') ?></option>
                                                    <option value="AFS"><?= __('afs') ?></option>
                                                    <option value="EUR"><?= __('euro') ?></option>
                                                    <option value="DARHAM"><?= __('darham') ?></option>

                                                </select>
                                                <small id="currencyHelp" class="form-text text-muted"><?= __('select_the_transaction_currency') ?></small>
                                                <div class="validation-feedback"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group" id="exchangeRateField" style="display: none;">
                                                <label for="exchangeRate">
                                                    <i class="feather icon-trending-up mr-1"></i><?= __('exchange_rate') ?>
                                                </label>
                                                <input type="number" class="form-control" id="exchangeRate" name="exchange_rate" step="0.00001" min="0.00001" placeholder="Enter exchange rate">
                                                <small class="form-text text-muted"><?= __('exchange_rate_required_for_currency_conversion') ?></small>
                                                <div class="validation-feedback"></div>
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
                                        <select class="form-control" id="mainAccountId" name="main_account_id" required aria-describedby="accountHelp">
                                            <option value=""><?= __('select_main_account') ?></option>
                                            <?php 
                                            $accountsQuery = "SELECT id, name FROM main_account WHERE status = 'active' AND tenant_id = $tenant_id";
                                            $accountsResult = $conn->query($accountsQuery);
                                            if ($accountsResult) {
                                                while ($account = $accountsResult->fetch_assoc()) {
                                                    echo '<option value="' . $account['id'] . '">' . htmlspecialchars($account['name']) . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                        <small id="accountHelp" class="form-text text-muted"><?= __('select_the_account_for_transaction') ?></small>
                                        <div class="validation-feedback"></div>
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
    <div class="modal fade select-modal" id="editTransactionModal" tabindex="-1" role="dialog">
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
                    <form id="editTransactionForm" novalidate>
                        <input type="hidden" id="editTransactionId" name="transaction_id">
                        <input type="hidden" id="editRefundId" name="booking_id">
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
                            <select class="form-control" id="editPaymentCurrency" name="payment_currency" required disabled>
                                <option value=""><?= __('select_currency') ?></option>
                                <option value="USD"><?= __('usd') ?></option>
                                <option value="AFS"><?= __('afs') ?></option>
                                <option value="EUR"><?= __('eur') ?></option>
                                <option value="DARHAM"><?= __('darham') ?></option>
                            </select>
                        </div>

                        <div class="form-group" id="editExchangeRateField" style="display: none;">
                            <label for="editExchangeRate">
                                <i class="feather icon-trending-up mr-1"></i><?= __('exchange_rate') ?>
                            </label>
                            <input type="number" class="form-control" id="editExchangeRate" name="exchange_rate" step="0.00001" min="0.00001" placeholder="Enter exchange rate">
                            <small class="form-text text-muted"><?= __('exchange_rate_required_for_currency_conversion') ?></small>
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

    <script>
   
    </script>

</body>
</html>