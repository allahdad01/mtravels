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
                                                                            <span class="badge-<?= $refund['refund_type'] === 'full' ? 'danger' : 'warning' ?> mb-2">
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