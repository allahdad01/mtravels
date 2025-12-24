<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Include secure headers helper
require_once 'includes/set_secure_headers.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once '../includes/db.php';
include '../api/creditor/creditor_handler.php';
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../css/creditors/styles.css">
<!-- Add this right before the closing </body> tag -->
<!-- Toast Container -->
<div class="toast-container"></div>

<!-- Toast JavaScript -->
<script src="../js/creditor/toast.js"></script>
<script>
    // Show toasts if there are any messages
    <?php if (isset($success_message)): ?>
        toast.show('<?php echo addslashes($success_message); ?>', 'success');
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        toast.show('<?php echo addslashes($error_message); ?>', 'error');
    <?php endif; ?>
</script>

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css">

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <div class="container mt-4">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h2><?= __("creditors_management") ?></h2>
                                    
                                </div>
                                
                                <?php if (isset($success_message)): ?>
                                    <div class="alert alert-success"><?php echo h($success_message); ?></div>
                                <?php endif; ?>
                                
                                <?php if (isset($error_message)): ?>
                                    <div class="alert alert-danger"><?php echo h($error_message); ?></div>
                                <?php endif; ?>
                                
                                <!-- Total Credits by Currency Section -->
                                <?php if (!empty($currency_totals)): ?>
                                <div class="row">
                                    <?php foreach ($currency_totals as $currency => $total): ?>
                                    <div class="col-md-3 col-sm-6 mb-4">
                                        <div class="summary-card h-100">
                                            <div class="d-flex align-items-center">
                                                <div class="currency-icon me-3">
                                                    <i class="feather icon-credit-card" style="font-size: 2rem;"></i>
                                                </div>
                                                <div>
                                                    <h3 class="mb-1"><?php echo number_format($total, 2); ?></h3>
                                                    <p class="mb-0 text-white-50"><?php echo htmlspecialchars($currency); ?> Total</p>
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <div class="progress" style="height: 4px; background: rgba(255,255,255,0.2);">
                                                    <div class="progress-bar bg-white" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Status Toggle Tabs -->
                                <div class="card mb-4">
                                    <div class="card-body p-0">
                                        <ul class="nav nav-tabs nav-fill">
                                            <li class="nav-item">
                                                <a class="nav-link <?php echo h($status_filter) === 'active' ? 'active' : ''; ?>" href="creditors.php">
                                                    <i class="feather icon-user-check mr-2"></i><?= __("active_creditors") ?>
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link <?php echo h($status_filter) === 'inactive' ? 'active' : ''; ?>" href="creditors.php?status=inactive">
                                                    <i class="feather icon-user-minus mr-2"></i><?= __("inactive_creditors") ?>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <!-- Creditors Table -->
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <i class="feather icon-users mr-2"></i><?= __($status_filter . '_creditors') ?>
                                        </h5>
                                        <button type="button" class="btn btn-success d-flex align-items-center" data-toggle="modal" data-target="#addCreditorModal">
                                            <i class="feather icon-plus-circle mr-2"></i> <?= __("add_new_creditor") ?>
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover" id="creditorsTable" width="100%">
                                                <thead>
                                                    <tr>
                                                        <th><?= __("name") ?></th>
                                                        <th><?= __("email") ?></th>
                                                        <th><?= __("phone") ?></th>
                                                        <th><?= __("address") ?></th>
                                                        <th><?= __("balance") ?></th>
                                                        <th><?= __("currency") ?></th>
                                                        <th class="text-center no-sort"><?= __("actions") ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (count($creditors) > 0): ?>
                                                        <?php foreach ($creditors as $creditor): ?>
                                                            <tr>
                                                                <td>
                                                                    <div class="d-flex align-items-center">

                                                                        <div class="ml-3">
                                                                            <h6 class="mb-0"><?php echo htmlspecialchars($creditor['name']); ?></h6>
                                                                            <small class="text-muted">ID: <?php echo h($creditor['id']); ?></small>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($creditor['email'])): ?>
                                                                        <div class="d-flex align-items-center">
                                                                            <i class="feather icon-mail text-muted mr-2"></i>
                                                                            <?php echo htmlspecialchars($creditor['email']); ?>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($creditor['phone'])): ?>
                                                                        <div class="d-flex align-items-center">
                                                                            <i class="feather icon-phone text-muted mr-2"></i>
                                                                            <?php echo htmlspecialchars($creditor['phone']); ?>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($creditor['address'])): ?>
                                                                        <div class="d-flex align-items-center">
                                                                            <i class="feather icon-map-pin text-muted mr-2"></i>
                                                                            <?php echo htmlspecialchars($creditor['address']); ?>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <span class="font-weight-medium <?php echo $creditor['balance'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                                                            <?php echo number_format($creditor['balance'], 2); ?>
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <span class="">
                                                                        <?php echo htmlspecialchars($creditor['currency']); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <div class="d-flex justify-content-center">
                                                                        <div class="dropdown">
                                                                            <button class="btn btn-icon btn-primary dropdown-toggle" 
                                                                                    type="button" 
                                                                                    id="dropdownMenu_<?php echo h($creditor['id']); ?>" 
                                                                                    data-toggle="dropdown" 
                                                                                    aria-haspopup="true" 
                                                                                    aria-expanded="false"
                                                                                    title="<?= __("actions") ?>">
                                                                                <i class="feather icon-more-vertical"></i>
                                                                            </button>
                                                                            <div class="dropdown-menu dropdown-menu-right" 
                                                                                 aria-labelledby="dropdownMenu_<?php echo h($creditor['id']); ?>">
                                                                                <button type="button" class="dropdown-item" 
                                                                                        data-toggle="modal" 
                                                                                        data-target="#paymentModal_<?php echo h($creditor['id']); ?>">
                                                                                    <i class="feather icon-credit-card"></i> <?= __("process_payment") ?>
                                                                                </button>
                                                                                <button type="button" class="dropdown-item" 
                                                                                        data-toggle="modal" 
                                                                                        data-target="#transactionsModal_<?php echo h($creditor['id']); ?>">
                                                                                    <i class="feather icon-list"></i> <?= __("view_transactions") ?>
                                                                                </button>
                                                                                <a href="../api/creditor/print_creditor_statement.php?id=<?php echo h($creditor['id']); ?>" 
                                                                                   class="dropdown-item"
                                                                                   target="_blank">
                                                                                    <i class="feather icon-printer"></i> <?= __("print_statement") ?>
                                                                                </a>
                                                                                <div class="dropdown-divider"></div>
                                                                                <button type="button" class="dropdown-item" 
                                                                                        data-toggle="modal" 
                                                                                        data-target="#editCreditorModal_<?php echo h($creditor['id']); ?>">
                                                                                    <i class="feather icon-edit-2"></i> <?= __("edit_creditor") ?>
                                                                                </button>
                                                                                <button type="button" class="dropdown-item text-danger" 
                                                                                        data-toggle="modal" 
                                                                                        data-target="#deleteCreditorModal_<?php echo h($creditor['id']); ?>">
                                                                                    <i class="feather icon-trash-2"></i> <?= __("delete_creditor") ?>
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="7">
                                                                <div class="empty-state">
                                                                    <i class="feather icon-users"></i>
                                                                    <h5 class="mt-3"><?= __("no_creditors_found") ?></h5>
                                                                    <p class="text-muted">
                                                                        <?php if ($status_filter === 'active'): ?>
                                                                            <?= __("add_new_creditors_to_start_tracking_your_credits") ?>
                                                                        <?php else: ?>
                                                                            <?= __("deactivated_creditors_will_appear_here") ?>
                                                                        <?php endif; ?>
                                                                    </p>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            
        </div>
    </div>
    
    <!-- Add Creditor Modal -->
    <div class="modal fade" id="addCreditorModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="feather icon-user-plus mr-2"></i><?= __("add_new_creditor") ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label class="small text-muted mb-1"><?= __("name") ?> *</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="small text-muted mb-1"><?= __("email") ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-mail"></i></span>
                                    </div>
                                    <input type="email" class="form-control" name="email">
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="small text-muted mb-1"><?= __("phone") ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-phone"></i></span>
                                    </div>
                                    <input type="tel" class="form-control" name="phone">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="small text-muted mb-1"><?= __("address") ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="feather icon-map-pin"></i></span>
                                </div>
                                <textarea class="form-control" name="address" rows="2"></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="small text-muted mb-1"><?= __("initial_balance") ?> *</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-dollar-sign"></i></span>
                                    </div>
                                    <input type="number" class="form-control" name="balance" step="0.01" required>
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="small text-muted mb-1"><?= __("currency") ?> *</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-credit-card"></i></span>
                                    </div>
                                    <select class="form-control" name="currency" required>
                                        <option value="USD"><?= __("usd") ?></option>
                                        <option value="AFS"><?= __("afs") ?></option>
                                        <option value="EUR"><?= __("eur") ?></option>
                                        <option value="DARHAM"><?= __("darham") ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="small text-muted mb-1"><?= __("main_account") ?> *</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="feather icon-briefcase"></i></span>
                                </div>
                                <select class="form-control" name="main_account_id" id="mainAccountSelect" required>
                                    <?php foreach ($main_accounts as $account): ?>
                                        <option value="<?php echo h($account['id']); ?>"><?php echo htmlspecialchars($account['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="skipMainAccount" name="skip_main_account">
                            <label class="custom-control-label small" for="skipMainAccount">
                                <?= __("skip_adding_to_main_account_balance_and_transaction_record") ?>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-link" data-dismiss="modal">
                            <i class="feather icon-x mr-2"></i><?= __("cancel") ?>
                        </button>
                        <button type="submit" name="add_creditor" class="btn btn-success">
                            <i class="feather icon-check-circle mr-2"></i><?= __("add_creditor") ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    
    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    
    <!-- DataTables JS -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Initialize DataTable for creditors
        $('#creditorsTable').DataTable({
            responsive: true,
            language: {
                search: "<?= __('search') ?>:",
                lengthMenu: "<?= __('show') ?> _MENU_ <?= __('entries') ?>",
                info: "<?= __('showing') ?> _START_ <?= __('to') ?> _END_ <?= __('of') ?> _TOTAL_ <?= __('entries') ?>",
                infoEmpty: "<?= __('showing') ?> 0 <?= __('to') ?> 0 <?= __('of') ?> 0 <?= __('entries') ?>",
                infoFiltered: "(<?= __('filtered_from') ?> _MAX_ <?= __('total_entries') ?>)",
                paginate: {
                    first: "<?= __('first') ?>",
                    last: "<?= __('last') ?>",
                    next: "<?= __('next') ?>",
                    previous: "<?= __('previous') ?>"
                }
            },
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "<?= __('all') ?>"]],
            columnDefs: [
                { targets: 'no-sort', orderable: false }
            ],
            order: [[0, 'asc']]
        });

        // Initialize DataTables for transaction tables
        $('.transaction-table').each(function() {
            $(this).DataTable({
                responsive: true,
                language: {
                    search: "<?= __('search') ?>:",
                    lengthMenu: "<?= __('show') ?> _MENU_",
                    info: "<?= __('showing') ?> _START_ <?= __('to') ?> _END_ <?= __('of') ?> _TOTAL_",
                    infoEmpty: "<?= __('no_records') ?>",
                    paginate: {
                        next: "<?= __('next') ?>",
                        previous: "<?= __('previous') ?>"
                    }
                },
                pageLength: 5,
                lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "<?= __('all') ?>"]],
                columnDefs: [
                    { targets: 'no-sort', orderable: false }
                ],
                order: [[0, 'desc']]
            });
        });

        // Handle modal open events to fix DataTables layout issues
        $('body').on('shown.bs.modal', function(e) {
            $($.fn.dataTable.tables(true)).DataTable().columns.adjust().responsive.recalc();
        });
    });
    </script>


    <script src="../js/creditor/modal_init.js"></script>

<script src="../js/creditor/currency_check.js"></script>

<?php foreach ($creditors as $creditor): ?>
    <!-- Transactions Modal -->
    <div class="modal fade" id="transactionsModal_<?php echo h($creditor['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("transactions") ?> - <?php echo htmlspecialchars($creditor['name']); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped transaction-table">
                            <thead>
                                <tr>
                                    <th><?= __("date") ?></th>
                                    <th><?= __("amount") ?></th>
                                    <th><?= __("type") ?></th>
                                    <th><?= __("description") ?></th>
                                    <th><?= __("receipt") ?></th>
                                    <th class="no-sort"><?= __("actions") ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Fetch transactions for this creditor
                                $transStmt = $pdo->prepare("SELECT * FROM creditor_transactions WHERE creditor_id = ? AND tenant_id = ? AND branch_id = ? ORDER BY payment_date DESC");
                                $transStmt->bindParam(1, $creditor['id'], PDO::PARAM_INT);
                                $transStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                $transStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                $transStmt->execute();
                                $transResult = $transStmt->fetchAll();
                                
                                if (count($transResult) > 0) {
                                    foreach ($transResult as $transaction) {
                                        echo '<tr>';
                                        // Ensure we display the exact date and time as stored in the database
                                        $dateTime = new DateTime($transaction['created_at']);
                                        echo '<td>' . $dateTime->format('Y-m-d H:i:s') . '</td>';
                                        echo '<td>' . number_format($transaction['amount'], 2) . ' ' . $transaction['currency'] . '</td>';
                                        echo '<td>' . ($transaction['transaction_type'] == 'debit' ? '<span class="badge-success">' . __("payment") . '</span>' : '<span class="badge-danger">' . __("credit") . '</span>') . '</td>';
                                        echo '<td>' . htmlspecialchars($transaction['description']) . '</td>';
                                        echo '<td>' . htmlspecialchars($transaction['reference_number']) . '</td>';
                                        echo '<td>';
                                        echo '<button class="btn btn-info btn-sm mr-1" title="Print Receipt" onclick="printReceipt(\'' . $transaction['id'] . '\')"><i class="feather icon-printer"></i></button>';
                                        // Add edit button
                                        echo '<button type="button" class="btn btn-primary btn-sm mr-1" data-toggle="modal" data-target="#editTransactionModal_' . $transaction['id'] . '"><i class="feather icon-edit"></i> ' . __("edit") . '</button>';
                                        echo '<form method="POST" onsubmit="return confirm(\'' . __("are_you_sure_you_want_to_delete_this_transaction_this_will_reverse_the_payment") . '\');">';
                                        echo '<input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf_token']) . '">';
                                        echo '<input type="hidden" name="transaction_id" value="' . $transaction['id'] . '">';
                                        echo '<input type="hidden" name="creditor_id" value="' . $creditor['id'] . '">';
                                        echo '<input type="hidden" name="amount" value="' . $transaction['amount'] . '">';
                                        echo '<input type="hidden" name="currency" value="' . $transaction['currency'] . '">';
                                        echo '<button type="submit" name="delete_transaction" class="btn btn-danger btn-sm"><i class="feather icon-trash"></i> ' . __("delete") . '</button>';
                                        echo '</form>';
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="6" class="text-center">' . __("no_transactions_found") . '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("close") ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal_<?php echo h($creditor['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("process_payment") ?> - <?php echo htmlspecialchars($creditor['name']); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    
                    <div class="modal-body">
                        <input type="hidden" name="creditor_id" value="<?php echo h($creditor['id']); ?>">
                        <input type="hidden" name="creditor_currency" value="<?php echo h($creditor['currency']); ?>">
                        <div class="form-group">
                            <label><?= __("amount") ?> *</label>
                            <input type="number" class="form-control" name="amount" step="0.000001" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("payment_currency") ?> *</label>
                            <select class="form-control" name="currency" required onchange="checkCreditorCurrency(this, '<?php echo h($creditor['currency']); ?>', '<?php echo h($creditor['id']); ?>')">
                                <option value="USD" <?php echo h($creditor['currency']) == 'USD' ? 'selected' : ''; ?>>USD</option>
                                <option value="AFS" <?php echo h($creditor['currency']) == 'AFS' ? 'selected' : ''; ?>>AFS</option>
                                <option value="EUR" <?php echo h($creditor['currency']) == 'EUR' ? 'selected' : ''; ?>>EUR</option>
                                <option value="DARHAM" <?php echo h($creditor['currency']) == 'DARHAM' ? 'selected' : ''; ?>>DARHAM</option>
                            </select>
                        </div>
                        <!-- Exchange Rate Field - Initially Hidden -->
                        <div class="form-group" id="exchangeRateDiv_<?php echo h($creditor['id']); ?>" style="display: none;">
                            <label>Exchange Rate (1 <span id="selectedCreditorCurrency_<?php echo h($creditor['id']); ?>"><?php echo h($creditor['currency']); ?></span> = ? <span id="creditorCurrency_<?php echo h($creditor['id']); ?>"><?php echo h($creditor['currency']); ?></span>)</label>
                            <input type="number" class="form-control" name="exchange_rate" id="exchangeRate_<?php echo h($creditor['id']); ?>" step="0.000001" placeholder="Enter exchange rate">
                            <small class="form-text text-muted">Enter the exchange rate to convert from payment currency to creditor's currency</small>
                        </div>
                        <div class="form-group">
                            <label><?= __("payment_date") ?> *</label>
                            <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("description") ?></label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label><?= __("paid_from") ?> *</label>
                            <select class="form-control" name="paid_to" required>
                                <?php foreach ($main_accounts as $account): ?>
                                    <option value="<?php echo h($account['id']); ?>"><?php echo htmlspecialchars($account['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("cancel") ?></button>
                        <button type="submit" name="pay" class="btn btn-primary"><?= __("process_payment") ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Creditor Modal -->
    <div class="modal fade" id="editCreditorModal_<?php echo h($creditor['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("edit_creditor") ?> - <?php echo htmlspecialchars($creditor['name']); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    
                    <div class="modal-body">
                        <input type="hidden" name="creditor_id" value="<?php echo h($creditor['id']); ?>">
                        <div class="form-group">
                            <label><?= __("name") ?> *</label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($creditor['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("email") ?></label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($creditor['email']); ?>">
                        </div>
                        <div class="form-group">
                            <label><?= __("phone") ?></label>
                            <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($creditor['phone']); ?>">
                        </div>
                        <div class="form-group">
                            <label><?= __("address") ?></label>
                            <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($creditor['address']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label><?= __("balance") ?> *</label>
                            <input type="number" class="form-control" name="balance" step="0.01" value="<?php echo h($creditor['balance']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("currency") ?> *</label>
                            <select class="form-control" name="currency" required>
                                <option value="USD" <?php echo h($creditor['currency']) == 'USD' ? 'selected' : ''; ?>>USD</option>
                                <option value="AFS" <?php echo h($creditor['currency']) == 'AFS' ? 'selected' : ''; ?>>AFS</option>
                                <option value="EUR" <?php echo h($creditor['currency']) == 'EUR' ? 'selected' : ''; ?>>EUR</option>
                                <option value="DARHAM" <?php echo h($creditor['currency']) == 'DARHAM' ? 'selected' : ''; ?>>DARHAM</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("cancel") ?></button>
                        <button type="submit" name="edit_creditor" class="btn btn-primary"><?= __("save_changes") ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Add Delete Creditor Modal for each creditor -->
<?php foreach ($creditors as $creditor): ?>
    <div class="modal fade" id="deleteCreditorModal_<?php echo h($creditor['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><?= __("delete_creditor") ?> - <?php echo htmlspecialchars($creditor['name']); ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" onsubmit="return confirm('<?= __("are_you_sure_you_want_to_delete_this_creditor_this_action_cannot_be_undone") ?>');">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    
                    <div class="modal-body">
                        <input type="hidden" name="creditor_id" value="<?php echo h($creditor['id']); ?>">
                        <input type="hidden" name="creditor_balance" value="<?php echo h($creditor['balance']); ?>">
                        <input type="hidden" name="creditor_currency" value="<?php echo h($creditor['currency']); ?>">
                        <p><?= __("are_you_sure_you_want_to_delete_this_creditor") ?> <strong><?php echo htmlspecialchars($creditor['name']); ?></strong>?</p>
                        <p><?= __("current_balance") ?>: <strong><?php echo number_format($creditor['balance'], 2) . ' ' . h($creditor['currency']); ?></strong></p>
                        <?php if ($creditor['balance'] > 0): ?>
                            <div class="alert alert-warning">
                                <i class="feather icon-alert-triangle mr-2"></i>
                                <?= __("warning") ?>: <?= __("this_creditor_has_a_non_zero_balance_deleting_will_affect_main_account_balances_if_transactions_exist") ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("cancel") ?></button>
                        <button type="submit" name="delete_creditor" class="btn btn-danger"><?= __("delete_creditor") ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php
// Validate edit_creditor
$edit_creditor = isset($_POST['edit_creditor']) ? DbSecurity::validateInput($_POST['edit_creditor'], 'string', ['maxlength' => 255]) : null;

// Add Edit Transaction Modals for each transaction
foreach ($creditors as $creditor): 
    // Fetch transactions for this creditor
    $transStmt = $pdo->prepare("SELECT * FROM creditor_transactions WHERE creditor_id = ? AND tenant_id = ? AND branch_id = ? ORDER BY payment_date DESC");
    $transStmt->bindParam(1, $creditor['id'], PDO::PARAM_INT);
    $transStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $transStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $transStmt->execute();
    $transResult = $transStmt->fetchAll();
    
    foreach ($transResult as $transaction):
?>
    <!-- Edit Transaction Modal -->
    <div class="modal fade" id="editTransactionModal_<?php echo $transaction['id']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("edit_transaction") ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editTransactionForm_<?php echo $transaction['id']; ?>" class="edit-transaction-form">
                        <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                        <input type="hidden" name="creditor_id" value="<?php echo $creditor['id']; ?>">
                        <input type="hidden" name="original_amount" value="<?php echo $transaction['amount']; ?>">
                        <input type="hidden" name="original_currency" value="<?php echo $transaction['currency']; ?>">
                        
                        <div class="form-group">
                            <label><?= __("amount") ?> *</label>
                            <input type="number" class="form-control" name="payment_amount" value="<?php echo $transaction['amount']; ?>" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("payment_date_and_time") ?> *</label>
                            <div class="row">
                                <div class="col-md-7">
                                    <?php 
                                    // Ensure we get the proper date
                                    $datetime = new DateTime($transaction['created_at']);
                                    $formattedDate = $datetime->format('d/m/Y');
                                    ?>
                                    <input type="text" class="form-control" name="payment_date" 
                                           placeholder="DD/MM/YYYY" value="<?php echo $formattedDate; ?>" required>
                                    <small class="form-text text-muted"><?= __("format") ?>: DD/MM/YYYY</small>
                                </div>
                                <div class="col-md-5">
                                    <?php 
                                    // Get the time part
                                    $formattedTime = $datetime->format('H:i:s');
                                    ?>
                                    <input type="text" class="form-control" name="payment_time" 
                                           placeholder="HH:MM:SS" value="<?php echo $formattedTime; ?>" required>
                                    <small class="form-text text-muted"><?= __("format") ?>: HH:MM:SS</small>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><?= __("reference_number") ?></label>
                            <input type="text" class="form-control" name="reference_number" value="<?php echo htmlspecialchars($transaction['reference_number']); ?>">
                        </div>
                        <div class="form-group">
                            <label><?= __("description") ?></label>
                            <textarea class="form-control" name="payment_description" rows="3"><?php echo htmlspecialchars($transaction['description']); ?></textarea>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="feather icon-alert-triangle mr-2"></i>
                            <?= __("warning") ?>: <?= __("editing_a_transaction_will_recalculate_balances") ?>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("cancel") ?></button>
                    <button type="button" class="btn btn-primary" onclick="updateCreditorTransaction(<?php echo $transaction['id']; ?>)"><?= __("save_changes") ?></button>
                </div>
            </div>
        </div>
    </div>
<?php
    endforeach;
endforeach;
?>



<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

<script src="../js/creditor/transaction_update.js"></script>
<script src="../js/creditor/print_receipt.js"></script>
</body>
</html> 
