<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;


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
        WHERE r.tenant_id = ? AND r.branch_id = ?
    ";
    $stmt = $pdo->prepare($countQuery);
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $totalRefunds = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRefunds / $recordsPerPage);

    // Paginated fetch
    $refundsQuery = "
        SELECT r.*, v.applicant_name, v.passport_number, v.country, v.currency as visa_currency,
               u.name as created_by, m.name as account_name
        FROM visa_refunds r
        LEFT JOIN visa_applications v ON r.visa_id = v.id
        LEFT JOIN users u ON r.processed_by = u.id
        LEFT JOIN main_account_transactions t ON r.transaction_id = t.id
        LEFT JOIN main_account m ON t.main_account_id = m.id
        WHERE r.tenant_id = ? AND r.branch_id = ?
        AND (v.id IS NULL OR v.branch_id = ?)
        AND (u.id IS NULL OR u.branch_id = ?)
        AND (t.id IS NULL OR t.branch_id = ?)
        AND (m.id IS NULL OR m.branch_id = ?)
        ORDER BY r.refund_date DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $pdo->prepare($refundsQuery);
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(5, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(6, $branch_id, PDO::PARAM_INT);
    $stmt->bindParam(7, $recordsPerPage, PDO::PARAM_INT);
    $stmt->bindParam(8, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                                                                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteRefund(<?= $refund['id'] ?>)">
                                                                            <i class="fas fa-trash"></i> Delete
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

    <?php include '../modals/visa_refund/transaction_modal.php'; ?>
    <?php include '../modals/visa_refund/edit_transaction_modal.php'; ?>




    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/ripple.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    <script src="../assets/js/menu-setting.min.js"></script>
    <script src="../js/visa_refund/transaction_manager.js"></script>
    <script src="../js/visa_refund/refund_management.js"></script>
    <script src="../js/visa_refund/button_protection.js"></script>
    <script src="../js/visa_refund/visa_delete.js"></script>
    
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


</body>
</html> 