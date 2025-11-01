<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database security module for input validation
require_once 'includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Include database connection
include '../includes/db.php';
include '../includes/conn.php';

// Initialize variables
$expense = null;
$transactions = [];
$errorMessage = '';

// Get expense ID from URL
if (isset($_GET['id'])) {
    $expenseId = DbSecurity::validateInput($_GET['id'], 'int');
    
    if (!$expenseId) {
        $errorMessage = "Invalid expense ID.";
    } else {
        // Get expense details
        $query = "SELECT e.*, ec.name as category_name, ma.name as account_name, mat.receipt as receipt_number
                  FROM expenses e
                  LEFT JOIN expense_categories ec ON e.category_id = ec.id
                  LEFT JOIN main_account ma ON e.main_account_id = ma.id
                  LEFT JOIN main_account_transactions mat ON e.id = mat.reference_id AND mat.transaction_of = 'expense'
                  WHERE e.id = ? AND e.tenant_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$expenseId, $tenant_id]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$expense) {
            $errorMessage = "Expense not found.";
        } else {
            // Get transactions related to this expense
            $transactionQuery = "SELECT 
                'Main Account' AS transaction_type,
                mat.id,
                mat.type,
                mat.amount,
                mat.currency,
                mat.description,
                mat.transaction_of,
                mat.receipt,
                mat.created_at AS transaction_date
                FROM main_account_transactions mat
                WHERE mat.reference_id = ? AND mat.transaction_of = 'expense' AND mat.tenant_id = ?";
            $stmt = $pdo->prepare($transactionQuery);
            $stmt->execute([$expenseId, $tenant_id]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} else {
    $errorMessage = "Expense ID is required.";
}

// Include the header
include '../includes/header_finance.php';
?>
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
<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10"><?= __('expense_details') ?></h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="expenses.php"><?= __('expenses') ?></a></li>
                            <li class="breadcrumb-item"><a href="javascript:"><?= __('expense_details') ?></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger"><?php echo h($errorMessage); ?></div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h5><?= __('expense_information') ?></h5>
                            <span class="text-muted">Details about expense #<?php echo h($expense['id']); ?></span>
                            <div class="card-header-right">
                                <a href="expense_management.php" class="btn btn-sm btn-secondary">
                                    <i class="feather icon-arrow-left"></i> <?= __('back_to_expenses') ?>
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-bordered table-striped">
                                        <tr>
                                            <th width="30%"><?= __('id') ?></th>
                                            <td><strong><?php echo h($expense['id']); ?></strong></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('category') ?></th>
                                            <td><?php echo h($expense['category_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('description') ?></th>
                                            <td><?php echo h($expense['description']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('amount') ?></th>
                                            <td>
                                                <span class="text-danger font-weight-bold">
                                                    <?php echo h($expense['currency']) . ' ' . h(number_format($expense['amount'], 2)); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php if (!empty($expense['receipt_number'])): ?>
                                        <tr>
                                            <th><?= __('receipt_number') ?></th>
                                            <td><?php echo h($expense['receipt_number']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($expense['receipt_file'])): ?>
                                        <tr>
                                            <th><?= __('receipt_file') ?></th>
                                            <td>
                                                <a href="#" onclick="showReceipt('<?php echo h($expense['receipt_file']); ?>')" class="btn btn-sm btn-info">
                                                    <i class="feather icon-eye"></i> <?= __('view_receipt') ?>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-bordered table-striped">
                                        <tr>
                                            <th width="30%"><?= __('date') ?></th>
                                            <td><?php echo h(date('Y-m-d', strtotime($expense['date']))); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('account') ?></th>
                                            <td><?php echo h($expense['account_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('created_at') ?></th>
                                            <td><?php echo h(date('Y-m-d H:i:s', strtotime($expense['created_at']))); ?></td>
                                        </tr>
                                        <?php if (isset($expense['allocation_id']) && $expense['allocation_id']): ?>
                                        <tr>
                                            <th><?= __('budget_allocation') ?></th>
                                            <td>
                                                <a href="budget_allocation_detail.php?id=<?php echo h($expense['allocation_id']); ?>">
                                                    <?= __('view_allocation') ?> #<?php echo h($expense['allocation_id']); ?>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transaction History -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="feather icon-activity mr-1"></i> <?= __('transaction_history') ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($transactions)): ?>
                                <div class="alert alert-info"><?= __('no_transactions_found_for_this_expense') ?></div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th><?= __('id') ?></th>
                                                <th><?= __('type') ?></th>
                                                <th><?= __('amount') ?></th>
                                                <th><?= __('description') ?></th>
                                                <th><?= __('transaction_date') ?></th>
                                               
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($transactions as $transaction): ?>
                                                <tr>
                                                    <td><?php echo h($transaction['id']); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo strtolower($transaction['type']) == 'debit' ? 'danger' : 'success'; ?>">
                                                            <?php echo h($transaction['type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo h($transaction['currency']) . ' ' . h(number_format($transaction['amount'], 2)); ?></td>
                                                    <td><?php echo h($transaction['description']); ?></td>
                                                    <td><?php echo h(date('Y-m-d H:i', strtotime($transaction['transaction_date']))); ?></td>
                                                    
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Actions Section -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="feather icon-tool mr-1"></i> <?= __('actions') ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">
                                    
                                    <a href="print_expense.php?id=<?php echo h($expenseId); ?>" class="btn btn-secondary ml-2" target="_blank">
                                        <i class="feather icon-printer"></i> <?= __('print_details') ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" role="dialog" aria-labelledby="receiptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="receiptModalLabel"><?= __('receipt') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <img id="receiptImage" src="" alt="Receipt" class="img-fluid">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                <a id="downloadReceipt" href="" class="btn btn-primary" download><?= __('download') ?></a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel"><?= __('confirm_delete') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><?= __('are_you_sure_you_want_to_delete_this_expense') ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                <a href="#" id="deleteLink" class="btn btn-danger"><?= __('delete') ?></a>
            </div>
        </div>
    </div>
</div>

<script>
    function showReceipt(receipt) {
        const baseUrl = '../uploads/expense_receipt/';
        const receiptUrl = baseUrl + receipt;

        document.getElementById('receiptImage').src = receiptUrl;
        document.getElementById('downloadReceipt').href = receiptUrl;

        $('#receiptModal').modal('show');
    }
    
    function confirmDelete(id) {
        document.getElementById('deleteLink').href = 'delete_expense.php?id=' + id;
        $('#deleteModal').modal('show');
    }
</script>


                            <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>


<?php
// Include footer
include '../includes/admin_footer.php';
?> 