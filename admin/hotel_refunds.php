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
$branch_id = $_SESSION['branch_id'];
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
            WHERE r.tenant_id = ? AND r.branch_id = ?
        ";
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute([$tenant_id, $branch_id]);
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
            WHERE r.tenant_id = ? AND r.branch_id = ?
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $pdo->prepare($refundsQuery);
        $stmt->bindValue(1, (int)$tenant_id, PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$branch_id, PDO::PARAM_INT);
        $stmt->bindValue(3, (int)$recordsPerPage, PDO::PARAM_INT);
        $stmt->bindValue(4, (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

?>


<?php
    // Include the header
include '../includes/header.php';
?>
    <link rel="stylesheet" href="../css/general/modal-styles.css">
    <link rel="stylesheet" href="../css/hotel/styles.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap4.min.css">

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

    .badge-danger {
        background-color: #dc3545;
    }

    .badge-warning {
        background-color: #ffc107;
        color: #212529;
    }

    .badge-info {
        background-color: #17a2b8;
    }

    .badge-success {
        background-color: #28a745;
    }

    .table-responsive {
        border-radius: 10px;
        overflow: hidden;
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

    .btn-outline-info, .btn-outline-success, .btn-outline-primary, .btn-outline-danger {
        border-radius: 25px;
        padding: 0.5rem 1rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-outline-info:hover, .btn-outline-success:hover, .btn-outline-primary:hover, .btn-outline-danger:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
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

    .pagination .page-link {
        border-radius: 8px;
        margin: 0 2px;
        border: 1px solid #dee2e6;
        color: #495057;
    }

    .pagination .page-item.active .page-link {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        border-color: #4099ff;
    }

    .pagination .page-link:hover {
        background-color: #e9ecef;
        border-color: #adb5bd;
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
                            <div class="main-content">
                                <div class="page-header card">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h5 class="mb-0"><i class="feather icon-refresh-cw mr-2"></i><?= __('hotel_refunds') ?></h5>
                                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;"><?= __('manage_hotel_refund_records') ?></p>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <a href="hotel.php" class="btn btn-outline-secondary btn-sm">
                                                <i class="feather icon-arrow-left mr-1"></i><?= __('back_to_hotel_management') ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Refund Summary Card -->
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="feather icon-bar-chart-2 mr-2"></i><?= __('refund_summary') ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="text-center mb-4">
                                                    <div class="h2 font-weight-bold text-danger">
                                                        <i class="feather icon-refresh-cw mr-2"></i><?= count($refunds) ?>
                                                        <span class="text-muted h4">/ <?= $totalRefunds ?></span>
                                                    </div>
                                                    <p class="text-muted mb-0"><?= __('refunds_processed') ?></p>
                                                </div>

                                                <div class="progress mb-4" style="height: 30px; border-radius: 15px;">
                                                    <div class="progress-bar <?= count($refunds) >= $totalRefunds ? 'bg-success' : 'bg-info' ?>"
                                                         role="progressbar"
                                                         style="width: <?= $totalRefunds > 0 ? min(100, (count($refunds) / $totalRefunds) * 100) : 0 ?>%; border-radius: 15px;">
                                                        <span class="font-weight-bold"><?= $totalRefunds > 0 ? round((count($refunds) / $totalRefunds) * 100) : 0 ?>%</span>
                                                    </div>
                                                </div>

                                                <hr class="my-4">

                                                <div class="row text-center">
                                                    <div class="col-6">
                                                        <div class="h4 mb-1 font-weight-bold text-info"><?= __('this_page') ?></div>
                                                        <small class="text-muted"><i class="feather icon-file-text mr-1"></i><?= count($refunds) ?> <?= __('records') ?></small>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="h4 mb-1 font-weight-bold text-success"><?= __('total') ?></div>
                                                        <small class="text-muted"><i class="feather icon-database mr-1"></i><?= $totalRefunds ?> <?= __('records') ?></small>
                                                    </div>
                                                </div>

                                                <hr class="my-4">

                                                <div class="text-center">
                                                    <span class="badge badge-danger badge-pill px-3 py-2 h6">
                                                        <i class="feather icon-refresh-cw mr-1"></i><?= __('hotel_refunds') ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Refund Records -->
                                    <div class="col-md-8">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="feather icon-refresh-cw mr-2"></i><?= __('hotel_refund_records') ?></h5>
                                            </div>
                                            <div class="card-body">
                                            <?php if (!$tableExists || empty($refunds)): ?>
                                                <div class="alert alert-info border-0 shadow-sm">
                                                    <div class="d-flex align-items-center">
                                                        <i class="feather icon-info mr-3 text-info" style="font-size: 24px;"></i>
                                                        <div>
                                                            <h6 class="mb-1"><?= __('no_hotel_refunds_have_been_processed_yet') ?></h6>
                                                            <small class="text-muted"><?= __('when_refunds_are_processed_they_will_appear_here') ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="table-responsive">
                                                    <table id="refundsTable" class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th class="text-center">#</th>
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
                                                                    <td class="text-center font-weight-bold text-muted"><?= ($offset + $index + 1) ?></td>
                                                                    <td>
                                                                        <div class="d-flex flex-column">
                                                                            <span class="font-weight-bold text-dark h6 mb-2">
                                                                                <i class="feather icon-user mr-2 text-primary"></i><?= htmlspecialchars($refund['title'] . ' ' . $refund['first_name'] . ' ' . $refund['last_name']) ?>
                                                                            </span>
                                                                            <div class="mt-2">
                                                                                <small class="text-muted d-block">
                                                                                    <i class="feather icon-calendar mr-1"></i>
                                                                                    <?= __('check_in') ?>: <strong><?= date('M d, Y', strtotime($refund['check_in_date'])) ?></strong>
                                                                                </small>
                                                                                <small class="text-muted d-block">
                                                                                    <i class="feather icon-calendar mr-1"></i>
                                                                                    <?= __('check_out') ?>: <strong><?= date('M d, Y', strtotime($refund['check_out_date'])) ?></strong>
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
                                                                            <span class="badge <?= $refund['refund_type'] === 'full' ? 'badge-danger' : 'badge-warning' ?> badge-pill px-3 py-2 mb-2 font-weight-bold">
                                                                                <i class="feather icon-refresh-cw mr-1"></i><?= ucfirst($refund['refund_type']) ?> <?= __('refund') ?>
                                                                            </span>
                                                                            <small class="text-muted d-block mb-2">
                                                                                <i class="feather icon-info mr-1"></i>
                                                                                <strong><?= __('reason') ?>:</strong> <?= htmlspecialchars($refund['reason']) ?>
                                                                            </small>
                                                                            <div class="mt-2">
                                                                                <small class="text-muted d-block">
                                                                                    <i class="feather icon-user mr-1"></i>
                                                                                    <strong><?= __('client') ?>:</strong> <?= htmlspecialchars($refund['client_name']) ?>
                                                                                </small>
                                                                                <small class="text-muted d-block">
                                                                                    <i class="feather icon-briefcase mr-1"></i>
                                                                                    <strong><?= __('supplier') ?>:</strong> <?= htmlspecialchars($refund['supplier_name']) ?>
                                                                                </small>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <div class="d-flex flex-column">
                                                                            <span class="font-weight-bold text-danger h5 mb-1">
                                                                                <i class="feather icon-dollar-sign mr-1"></i><?= htmlspecialchars($refund['currency']) ?> <?= number_format($refund['refund_amount'], 2) ?>
                                                                            </span>
                                                                            <?php if (!empty($refund['exchange_rate']) && $refund['exchange_rate'] != 1): ?>
                                                                                <small class="text-muted mt-1">
                                                                                    <i class="feather icon-repeat mr-1"></i>
                                                                                    <strong><?= __('exchange_rate') ?>:</strong> <?= number_format($refund['exchange_rate'], 4) ?>
                                                                                </small>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <div class="d-flex flex-column">
                                                                            <span class="font-weight-bold text-dark h6 mb-1">
                                                                                <i class="feather icon-calendar mr-1"></i><?= date('M d, Y', strtotime($refund['created_at'])) ?>
                                                                            </span>
                                                                            <small class="text-muted mb-2">
                                                                                <i class="feather icon-clock mr-1"></i><?= date('h:i A', strtotime($refund['created_at'])) ?>
                                                                            </small>
                                                                            <?php if ($refund['processed_by_name']): ?>
                                                                                <small class="text-muted mt-1">
                                                                                    <i class="feather icon-user-check mr-1"></i>
                                                                                    <strong><?= __('processed_by') ?>:</strong> <?= htmlspecialchars($refund['processed_by_name']) ?>
                                                                                </small>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <div class="btn-group-vertical" role="group">
                                                                            <a class="btn btn-outline-info btn-sm mb-1" href="hotel.php?id=<?= $refund['booking_id'] ?>">
                                                                                <i class="feather icon-file-text mr-1"></i><?= __('view_booking') ?>
                                                                            </a>
                                                                            <?php if (!empty($refund['transaction_id'])): ?>
                                                                                <a class="btn btn-outline-success btn-sm mb-1" href="javascript:void(0)" onclick="viewTransaction(<?= $refund['transaction_id'] ?>)">
                                                                                    <i class="feather icon-credit-card mr-1"></i><?= __('view_transaction') ?>
                                                                                </a>
                                                                            <?php endif; ?>
                                                                            <?php if ($refund['processed'] != 1): ?>
                                                                                <a class="btn btn-outline-primary btn-sm mb-1" href="javascript:void(0)" onclick="processRefundTransaction(<?= $refund['id'] ?>)">
                                                                                    <i class="feather icon-check-circle mr-1"></i><?= __('process_payment') ?>
                                                                                </a>
                                                                            <?php endif; ?>
                                                                            <a class="btn btn-outline-primary btn-sm mb-1" href="javascript:void(0)" onclick="printRefundAgreement(<?= $refund['id'] ?>)">
                                                                                <i class="feather icon-printer mr-1"></i><?= __('print_agreement') ?>
                                                                            </a>
                                                                            <a class="btn btn-outline-danger btn-sm" href="javascript:void(0)" onclick="deleteRefund(<?= $refund['id'] ?>)">
                                                                                <i class="feather icon-trash-2 mr-1"></i><?= __('delete_refund') ?>
                                                                            </a>
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
    <?php include '../modals/hotel_refund/transaction_modal.php'; ?>
    <?php include '../modals/hotel_refund/edit_transaction_modal.php'; ?>


    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="../js/hotel_refund/transaction_manager.js"></script>
    <script src="../js/hotel_refund/hotel_management.js"></script>
    <script src="../js/hotel_refund/button_protection.js"></script>
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




<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>