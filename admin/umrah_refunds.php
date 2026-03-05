<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance', 'sales', 'umrah'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - Role: " . ($_SESSION['role'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');

// Check if user is admin or finance
$canEdit = in_array($_SESSION['role'], ['admin', 'finance']);

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Check if umrah_refunds table exists
$tableCheckQuery = "SHOW TABLES LIKE 'umrah_refunds'";
$tableCheckStmt = $pdo->query($tableCheckQuery);
$tableExists = $tableCheckStmt->rowCount() > 0;

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
        WHERE r.tenant_id = ? AND r.branch_id = ?
        AND um.tenant_id = ? AND um.branch_id = ?
    ";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $countStmt->bindParam(2, $branch_id, PDO::PARAM_INT);
    $countStmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $countStmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $countStmt->execute();
    $totalRefunds = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRefunds / $recordsPerPage);

    // Then fetch paginated refunds
    $refundsQuery = "
        SELECT r.*, um.name, um.flight_date, um.return_date, um.room_type, um.duration, um.price, um.sold_price, um.paid, um.received_bank_payment, um.bank_receipt_number, um.due, um.profit,
               f.package_type, um.currency as booking_currency,
               u.name as processed_by_name, m.name as account_name,
               c.name as client_name
        FROM umrah_refunds r
        LEFT JOIN umrah_bookings um ON r.booking_id = um.booking_id
        LEFT JOIN families f ON um.family_id = f.family_id
        LEFT JOIN users u ON r.processed_by = u.id
        LEFT JOIN main_account m ON um.paid_to = m.id
        LEFT JOIN clients c ON um.sold_to = c.id
        WHERE r.tenant_id = ? AND r.branch_id = ?
        AND um.tenant_id = ? AND um.branch_id = ?
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $pdo->prepare($refundsQuery);
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(5, $recordsPerPage, PDO::PARAM_INT);
    $stmt->bindParam(6, $offset, PDO::PARAM_INT);
    $stmt->execute();

    $refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<?php include '../includes/header.php'; ?>
<script src="../assets/plugins/jquery/js/jquery.min.js"></script>
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../css/umrah/umrah-enhanced.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Status badges - specific to refunds */
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

/* Fix SweetAlert2 z-index to appear above Bootstrap modals */
.swal2-container {
    z-index: 1200 !important;
}

/* Ensure SweetAlert2 inputs are focusable and interactive */
.swal2-container input,
.swal2-container textarea,
.swal2-container select {
    pointer-events: auto !important;
    z-index: 1201 !important;
}

.swal2-container .form-group {
    margin-bottom: 1rem;
}

.swal2-container label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #495057;
}
</style>
<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- Enhanced Page Header -->
                <div class="enhanced-page-header">
                    <div class="container-fluid">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="page-title-wrapper">
                                    <i class="fas fa-undo page-icon"></i>
                                    <div>
                                        <h2 class="page-title"><?= __('umrah_refunds') ?></h2>
                                        <p class="page-subtitle"><?= __('manage_umrah_refund_records') ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 text-right">
                                <button class="btn btn-gradient-primary" onclick="location.reload()">
                                    <i class="fas fa-sync mr-2"></i><?= __('refresh') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                    <!-- Refunds Table -->
                    <div class="container-fluid px-4">
                        <!-- No Refunds Message -->
                        <?php if (!$tableExists || empty($refunds)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <h3 class="text-muted mt-3"><?= __('no_umrah_refunds_have_been_processed_yet') ?></h3>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive card">
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
                                                                                <?php if (!empty($refund['transaction_id']) && $canEdit): ?>
                                                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="viewTransaction(<?= $refund['transaction_id'] ?>)">
                                                                                        <i class="feather icon-credit-card text-success mr-2"></i><?= __('view_transaction') ?>
                                                                                    </a>
                                                                                <?php endif; ?>
                                                                                <?php if ($refund['processed'] != 1 && $canEdit): ?>
                                                                                    <a class="dropdown-item" href="javascript:void(0)" 
                                                                                       onclick="processRefundTransaction(<?= $refund['id'] ?>)">
                                                                                        <i class="feather icon-check-circle text-primary mr-2"></i><?= __('process_payment') ?>
                                                                                    </a>
                                                                                <?php endif; ?>
                                                                                 <?php if ($canEdit): ?>
                                                                                 <a class="dropdown-item text-danger" href="javascript:void(0)" 
                                                                                    onclick="deleteRefund(<?= $refund['id'] ?>)">
                                                                                     <i class="feather icon-trash-2 mr-2"></i><?= __('delete_refund') ?>
                                                                                 </a>
                                                                                 <?php endif; ?>
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
                                                    </div>

                                                    <!-- Pagination Controls -->
                                                    <nav aria-label="Umrah Refunds pagination" class="p-3">
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
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


<?php include '../modals/umrah_refund/transaction_modal.php'; ?>
<?php include '../modals/umrah_refund/edit_transaction_modal.php'; ?>

<!-- Required Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

<script src="../js/umrah_refund/transaction_manager.js"></script>
<script src="../js/umrah_refund/umrah_management.js"></script>

<!-- Custom Scripts -->
<script>
    // Set CSRF token
    window.csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

    // Toast notification function
    function showToast(type, message) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });

        Toast.fire({
            icon: type,
            title: message
        });
    }
</script>
<?php include '../includes/admin_footer.php'; ?>


</body>
</html>