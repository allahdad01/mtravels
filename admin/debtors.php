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

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
include '../api/debtor/debtors_handler.php';

// Fetch debtors list
$status_filter = isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'inactive' : 'active';

try {
     // Get total count
     $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM debtors WHERE status = ? AND tenant_id = ? AND branch_id = ?");
     $countStmt->execute([$status_filter, $tenant_id, $branch_id]);
     $total_count = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
     
     // Pagination
     $items_per_page = 10;
     $current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
     $offset = ($current_page - 1) * $items_per_page;
     $total_pages = ceil($total_count / $items_per_page);
     
     // Fetch debtors with pagination
     $stmt = $pdo->prepare("SELECT * FROM debtors WHERE status = ? AND tenant_id = ? AND branch_id = ? ORDER BY name ASC LIMIT ? OFFSET ?");
     $stmt->execute([$status_filter, $tenant_id, $branch_id, $items_per_page, $offset]);
     $debtors = $stmt->fetchAll(PDO::FETCH_ASSOC);
     
     // Fetch total debts by currency
     $currencyStmt = $pdo->prepare("SELECT currency, SUM(balance) as total FROM debtors WHERE status = ? AND tenant_id = ? AND branch_id = ? GROUP BY currency");
     $currencyStmt->execute([$status_filter, $tenant_id, $branch_id]);
     $currency_results = $currencyStmt->fetchAll(PDO::FETCH_ASSOC);
     $currency_totals = [];
     foreach ($currency_results as $row) {
         $currency_totals[$row['currency']] = $row['total'];
     }
     
     // Fetch main accounts for the dropdown
     $mainAcctStmt = $pdo->prepare("SELECT id, name FROM main_account WHERE tenant_id = ? AND branch_id = ? ORDER BY name ASC");
     $mainAcctStmt->execute([$tenant_id, $branch_id]);
     $main_accounts = $mainAcctStmt->fetchAll(PDO::FETCH_ASSOC);
 } catch (PDOException $e) {
     error_log("Error fetching debtors: " . $e->getMessage());
     $debtors = [];
     $total_count = 0;
     $total_pages = 0;
     $main_accounts = [];
     $currency_totals = [];
 }
?>


    <?php include '../includes/header.php'; ?>
<!-- Custom CSS for Debtors Page -->

<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../css/debtors/styles.css">

<!-- Add this right before the closing </body> tag -->
<!-- Toast Container -->
<div class="toast-container"></div>
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="../assets/plugins/sweetalert2/sweetalert2.min.css">
 <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="container-fluid">
                                <!-- Page Header -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center py-3 py-md-4">
                                            <div class="mb-3 mb-sm-0">
                                                <h2 class="h3 h-md-2 mb-1"><?= __('debtors_management') ?></h2>
                                                <p class="text-muted small mb-0">Manage your debtors and track payments</p>
                                            </div>
                                            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addDebtorModal">
                                                <i class="feather icon-plus mr-1"></i>
                                                <span class="d-none d-sm-inline"><?= __('add_new_debtor') ?></span>
                                                <span class="d-sm-none">Add</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
    
                                <?php if (isset($success_message)): ?>
                                    <div class="alert alert-success"><?php echo h($success_message); ?></div>
                                <?php endif; ?>
                                
                                <?php if (isset($error_message)): ?>
                                    <div class="alert alert-danger"><?php echo h($error_message); ?></div>
                                <?php endif; ?>
                                
                                <!-- Total Debts by Currency Section -->
                                <?php if (!empty($currency_totals)): ?>
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="card shadow-sm border-0">
                                            <div class="card-header bg-white border-bottom">
                                                <h5 class="mb-0 text-primary">
                                                    <i class="feather icon-bar-chart-2 mr-2"></i><?= __('total_debts_summary') ?>
                                                </h5>
                                            </div>
                                            <div class="card-body p-3 p-md-4">
                                                <div class="row">
                                                    <?php foreach ($currency_totals as $currency => $total): ?>
                                                    <div class="col-lg-3 col-md-4 col-sm-6 mb-3 mb-md-0">
                                                        <div class="text-center p-3 bg-light rounded h-100">
                                                            <h3 class="h4 text-primary mb-1"><?php echo number_format($total, 2); ?></h3>
                                                            <p class="mb-0 font-weight-bold small"><?php echo htmlspecialchars($currency); ?></p>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Status Toggle Tabs -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="card shadow-sm border-0">
                                            <div class="card-body p-2 p-md-3">
                                                <ul class="nav nav-tabs card-header-tabs flex-column flex-sm-row" id="debtorTabs" role="tablist">
                                                    <li class="nav-item">
                                                        <a class="nav-link <?php echo h($status_filter) === 'active' ? 'active' : ''; ?>" href="debtors.php">
                                                            <i class="feather icon-user-check mr-1 d-none d-sm-inline"></i>
                                                            <span class="small"><?= __('active_debtors') ?></span>
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link <?php echo h($status_filter) === 'inactive' ? 'active' : ''; ?>" href="debtors.php?status=inactive">
                                                            <i class="feather icon-user-minus mr-1 d-none d-sm-inline"></i>
                                                            <span class="small"><?= __('inactive_debtors') ?></span>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                        
                                <div class="card shadow-sm border-0">
                                    <div class="card-header bg-white border-bottom">
                                        <h5 class="mb-0 text-primary">
                                            <i class="feather icon-users mr-2"></i><?= __(ucfirst($status_filter ?? 'active') . '_debtors') ?>
                                        </h5>
                                    </div>
                                    <div class="card-body p-3 p-md-4">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-striped" id="debtorsTable" width="100%">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th>
                                                            <div class="d-flex align-items-center">
                                                                <i class="feather icon-user mr-2 text-muted"></i><?= __('name') ?>
                                                            </div>
                                                        </th>
                                                        <th class="d-none d-md-table-cell">
                                                            <div class="d-flex align-items-center">
                                                                <i class="feather icon-mail mr-2 text-muted"></i><?= __('email') ?>
                                                            </div>
                                                        </th>
                                                        <th class="d-none d-lg-table-cell">
                                                            <div class="d-flex align-items-center">
                                                                <i class="feather icon-phone mr-2 text-muted"></i><?= __('phone') ?>
                                                            </div>
                                                        </th>
                                                        <th class="d-none d-xl-table-cell">
                                                            <div class="d-flex align-items-center">
                                                                <i class="feather icon-map-pin mr-2 text-muted"></i><?= __('address') ?>
                                                            </div>
                                                        </th>
                                                        <th>
                                                            <div class="d-flex align-items-center">
                                                                <i class="feather icon-credit-card mr-2 text-muted"></i><?= __('balance') ?>
                                                            </div>
                                                        </th>
                                                        <th>
                                                            <div class="d-flex align-items-center">
                                                                <i class="feather icon-dollar-sign mr-2 text-muted"></i><?= __('currency') ?>
                                                            </div>
                                                        </th>
                                                        <th class="text-center"><?= __('actions') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($debtors) && count($debtors) > 0): ?>
                                                        <?php foreach ($debtors as $debtor): ?>
                                                            <tr class="debtor-row">
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <div>
                                                                            <h6 class="mb-0"><?php echo htmlspecialchars($debtor['name']); ?></h6>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td class="d-none d-md-table-cell">
                                                                    <?php if (!empty($debtor['email'])): ?>
                                                                        <a href="mailto:<?php echo htmlspecialchars($debtor['email']); ?>" class="text-body">
                                                                            <?php echo htmlspecialchars($debtor['email']); ?>
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <span class="text-muted"><?= __('not_provided') ?></span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="d-none d-lg-table-cell">
                                                                    <?php if (!empty($debtor['phone'])): ?>
                                                                        <a href="tel:<?php echo htmlspecialchars($debtor['phone']); ?>" class="text-body">
                                                                            <?php echo htmlspecialchars($debtor['phone']); ?>
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <span class="text-muted"><?= __('not_provided') ?></span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="d-none d-xl-table-cell">
                                                                    <?php if (!empty($debtor['address'])): ?>
                                                                        <span class="text-truncate d-inline-block" style="max-width: 150px;" title="<?php echo htmlspecialchars($debtor['address']); ?>">
                                                                            <?php echo htmlspecialchars($debtor['address']); ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="text-muted"><?= __('not_provided') ?></span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <?php if ($debtor['balance'] <= 0): ?>
                                                                            <span class="mr-2"><?= __('paid') ?></span>
                                                                        <?php elseif ($debtor['balance'] > 0): ?>
                                                                            <span class="mr-2"><?= __('pending') ?></span>
                                                                        <?php endif; ?>
                                                                        <span class="font-weight-medium">
                                                                            <?php echo number_format($debtor['balance'], 2); ?>
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <span class="">
                                                                        <?php echo htmlspecialchars($debtor['currency']); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <div class="d-flex justify-content-center">
                                                                        <div class="dropdown">
                                                                            <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="debtorDropdown<?php echo h($debtor['id']); ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                                <i class="feather icon-more-vertical"></i> <?= __('actions') ?>
                                                                            </button>
                                                                            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="debtorDropdown<?php echo h($debtor['id']); ?>">
                                                                                <?php if ($status_filter === 'active'): ?>
                                                                                    <button type="button" class="dropdown-item" data-toggle="modal" data-target="#paymentModal<?php echo h($debtor['id']); ?>">
                                                                                        <i class="feather icon-credit-card mr-2"></i><?= __('process_payment') ?>
                                                                                    </button>
                                                                                    <button type="button" class="dropdown-item" data-toggle="modal" data-target="#transactionsModal<?php echo h($debtor['id']); ?>">
                                                                                        <i class="feather icon-list mr-2"></i><?= __('view_transactions') ?>
                                                                                    </button>
                                                                                    <button type="button" class="dropdown-item" data-toggle="modal" data-target="#editDebtorModal<?php echo h($debtor['id']); ?>">
                                                                                        <i class="feather icon-edit-2 mr-2"></i><?= __('edit_debtor') ?>
                                                                                    </button>
                                                                                    <a href="../api/debtor/print_debtor_statement.php?id=<?php echo h($debtor['id']); ?>" class="dropdown-item" target="_blank">
                                                                                        <i class="feather icon-printer mr-2"></i><?= __('print_statement') ?>
                                                                                    </a>
                                                                                    <a href="../api/debtor/print_agreement.php?id=<?php echo h($debtor['id']); ?>" class="dropdown-item" target="_blank">
                                                                                        <i class="feather icon-file-text mr-2"></i><?= __('print_agreement') ?>
                                                                                    </a>
                                                                                    <div class="dropdown-divider"></div>
                                                                                    <?php if ($debtor['balance'] <= 0): ?>
                                                                                        <form method="POST" class="d-inline deactivate-form" onsubmit="return confirm('<?= __('confirm_deactivate_debtor') ?>');">
                                                                                            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                                                                            <input type="hidden" name="deactivate_debtor" value="1">
                                                                                            <input type="hidden" name="debtor_id" value="<?php echo h($debtor['id']); ?>">
                                                                                            <button type="submit" class="dropdown-item text-warning">
                                                                                                <i class="feather icon-user-x mr-2"></i><?= __('deactivate_debtor') ?>
                                                                                            </button>
                                                                                        </form>
                                                                                    <?php endif; ?>
                                                                                    <button type="button" class="dropdown-item text-danger delete-debtor-btn" data-debtor-id="<?php echo h($debtor['id']); ?>" data-debtor-name="<?php echo htmlspecialchars($debtor['name']); ?>">
                                                                                        <i class="feather icon-trash-2 mr-2"></i><?= __('delete_debtor') ?>
                                                                                    </button>
                                                                                <?php else: ?>
                                                                                    <button type="button" class="dropdown-item" data-toggle="modal" data-target="#transactionsModal<?php echo h($debtor['id']); ?>">
                                                                                        <i class="feather icon-list mr-2"></i><?= __('view_transactions') ?>
                                                                                    </button>
                                                                                    <a href="print_debtor_statement.php?id=<?php echo h($debtor['id']); ?>" class="dropdown-item" target="_blank">
                                                                                        <i class="feather icon-printer mr-2"></i><?= __('print_statement') ?>
                                                                                    </a>
                                                                                    <div class="dropdown-divider"></div>
                                                                                    <form method="POST" class="d-inline reactivate-form" onsubmit="return confirm('<?= __('confirm_reactivate_debtor') ?>');">
                                                                                        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                                                                        <input type="hidden" name="reactivate_debtor" value="1">
                                                                                        <input type="hidden" name="debtor_id" value="<?php echo h($debtor['id']); ?>">
                                                                                        <button type="submit" class="dropdown-item text-success">
                                                                                            <i class="feather icon-user-check mr-2"></i><?= __('reactivate_debtor') ?>
                                                                                        </button>
                                                                                    </form>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="7" class="text-center py-5">
                                                                <div class="empty-state">
                                                                    <i class="feather icon-users text-muted" style="font-size: 48px;"></i>
                                                                    <h5 class="mt-3"><?= __('no_debtors_found', ['status' => h($status_filter)]) ?></h5>
                                                                    <p class="text-muted">
                                                                        <?php if ($status_filter === 'active'): ?>
                                                                            <?= __('add_debtors_to_start') ?>
                                                                        <?php else: ?>
                                                                            <?= __('deactivated_debtors_appear_here') ?>
                                                                        <?php endif; ?>
                                                                    </p>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Pagination -->
                                        <div class="mt-3 mt-md-4">
                                            <nav aria-label="Page navigation">
                                                <ul class="pagination pagination-sm justify-content-center flex-wrap">
                                                    <?php
                                                    // Previous button
                                                    if ($current_page > 1): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?page=<?= $current_page - 1 ?><?= $status_filter === 'inactive' ? '&status=inactive' : '' ?>" aria-label="Previous">
                                                                <span aria-hidden="true">&laquo;</span>
                                                            </a>
                                                        </li>
                                                    <?php else: ?>
                                                        <li class="page-item disabled">
                                                            <a class="page-link" href="#" aria-label="Previous">
                                                                <span aria-hidden="true">&laquo;</span>
                                                            </a>
                                                        </li>
                                                    <?php endif;
                                                    
                                                    // Page numbers
                                                    $start_page = max(1, $current_page - 2);
                                                    $end_page = min($total_pages, $current_page + 2);
                                                    
                                                    if ($start_page > 1): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?page=1<?= $status_filter === 'inactive' ? '&status=inactive' : '' ?>">1</a>
                                                        </li>
                                                        <?php if ($start_page > 2): ?>
                                                            <li class="page-item disabled">
                                                                <a class="page-link" href="#">...</a>
                                                            </li>
                                                        <?php endif;
                                                    endif;
                                                    
                                                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                        <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                                            <a class="page-link" href="?page=<?= $i ?><?= $status_filter === 'inactive' ? '&status=inactive' : '' ?>"><?= $i ?></a>
                                                        </li>
                                                    <?php endfor;
                                                    
                                                    if ($end_page < $total_pages): 
                                                        if ($end_page < $total_pages - 1): ?>
                                                            <li class="page-item disabled">
                                                                <a class="page-link" href="#">...</a>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?page=<?= $total_pages ?><?= $status_filter === 'inactive' ? '&status=inactive' : '' ?>"><?= $total_pages ?></a>
                                                        </li>
                                                    <?php endif;
                                                    
                                                    // Next button
                                                    if ($current_page < $total_pages): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?page=<?= $current_page + 1 ?><?= $status_filter === 'inactive' ? '&status=inactive' : '' ?>" aria-label="Next">
                                                                <span aria-hidden="true">&raquo;</span>
                                                            </a>
                                                        </li>
                                                    <?php else: ?>
                                                        <li class="page-item disabled">
                                                            <a class="page-link" href="#" aria-label="Next">
                                                                <span aria-hidden="true">&raquo;</span>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </nav>
                                            <div class="text-center mt-2">
                                                <small class="text-muted">
                                                    <?= __('showing') ?> <?= count($debtors) ?> <?= __('of') ?> <?= $total_count ?> <?= __('debtors') ?> |
                                                    <?= __('page') ?> <?= $current_page ?> <?= __('of') ?> <?= $total_pages ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Add Debtor Modal -->
                                <div class="modal fade" id="addDebtorModal" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            
                                            <!-- Header styled like creditor -->
                                            <div class="modal-header bg-success text-white">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-user-plus mr-2"></i><?= __("add_new_debtor") ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            
                                            <form method="POST">
                                                <!-- CSRF Protection (if needed like creditor) -->
                                                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                                
                                                <div class="modal-body">
                                                    <div class="form-row">
                                                        <div class="form-group col-md-12">
                                                            <label for="debtor_name" class="small text-muted mb-1"><?= __("name") ?> *</label>
                                                            <div class="input-group">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="feather icon-user"></i></span>
                                                                </div>
                                                                <input type="text" class="form-control" id="debtor_name" name="name" placeholder="<?= __("enter_name") ?>" required>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-6">
                                                            <label for="debtor_email" class="small text-muted mb-1"><?= __("email") ?></label>
                                                            <div class="input-group">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="feather icon-mail"></i></span>
                                                                </div>
                                                                <input type="email" class="form-control" id="debtor_email" name="email" placeholder="<?= __("enter_email") ?>">
                                                            </div>
                                                        </div>
                                                        <div class="form-group col-md-6">
                                                            <label for="debtor_phone" class="small text-muted mb-1"><?= __("phone") ?></label>
                                                            <div class="input-group">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="feather icon-phone"></i></span>
                                                                </div>
                                                                <input type="tel" class="form-control" id="debtor_phone" name="phone" placeholder="<?= __("enter_phone") ?>">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="debtor_address" class="small text-muted mb-1"><?= __("address") ?></label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i class="feather icon-map-pin"></i></span>
                                                            </div>
                                                            <textarea class="form-control" id="debtor_address" name="address" rows="2" placeholder="<?= __("enter_address") ?>"></textarea>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-6">
                                                            <label for="debtor_balance" class="small text-muted mb-1"><?= __("initial_balance") ?> *</label>
                                                            <div class="input-group">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="feather icon-dollar-sign"></i></span>
                                                                </div>
                                                                <input type="number" class="form-control" id="debtor_balance" name="balance" step="0.01" placeholder="0.00" required>
                                                            </div>
                                                        </div>
                                                        <div class="form-group col-md-6">
                                                            <label for="debtor_currency" class="small text-muted mb-1"><?= __("currency") ?> *</label>
                                                            <div class="input-group">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="feather icon-credit-card"></i></span>
                                                                </div>
                                                                <select class="form-control" id="debtor_currency" name="currency" required>
                                                                    <option value=""><?= __("select_currency") ?></option>
                                                                    <option value="USD"><?= __("usd") ?></option>
                                                                    <option value="AFS"><?= __("afs") ?></option>
                                                                    <option value="EUR"><?= __("eur") ?></option>
                                                                    <option value="DARHAM"><?= __("darham") ?></option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="debtor_main_account" class="small text-muted mb-1"><?= __("main_account") ?> *</label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i class="feather icon-briefcase"></i></span>
                                                            </div>
                                                            <select class="form-control" id="debtor_main_account" name="main_account_id" required>
                                                                <option value=""><?= __("select_main_account") ?></option>
                                                                <?php foreach ($main_accounts as $account): ?>
                                                                    <option value="<?php echo h($account['id']); ?>"><?php echo h($account['name']); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <small class="form-text text-muted"><?= __('main_account_debit_notice') ?></small>
                                                    </div>

                                                    <div class="custom-control custom-switch">
                                                        <input type="checkbox" class="custom-control-input" id="skipDeduction" name="skip_deduction">
                                                        <label class="custom-control-label small" for="skipDeduction">
                                                            <?= __('skip_deduction_from_main_account') ?>
                                                        </label>
                                                        <small class="form-text text-muted"><?= __('skip_deduction_notice') ?></small>
                                                    </div>

                                                    <div class="form-group mt-3">
                                                        <label for="debtor_agreement" class="small text-muted mb-1"><?= __('agreement_terms') ?></label>
                                                        <textarea class="form-control" id="debtor_agreement" name="agreement_terms" rows="3" placeholder="<?= __('enter_agreement_terms_placeholder') ?>"></textarea>
                                                    </div>
                                                </div>

                                                <!-- Footer styled like creditor -->
                                                <div class="modal-footer bg-light">
                                                    <button type="button" class="btn btn-link" data-dismiss="modal">
                                                        <i class="feather icon-x mr-2"></i><?= __("cancel") ?>
                                                    </button>
                                                    <button type="submit" name="add_debtor" class="btn btn-success">
                                                        <i class="feather icon-check-circle mr-2"></i><?= __("add_debtor") ?>
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <?php foreach ($debtors as $debtor): ?>
                                    <!-- Payment Modal -->
                                    <div class="modal fade" id="paymentModal<?php echo h($debtor['id']); ?>" tabindex="-1" role="dialog" aria-labelledby="paymentModalLabel<?php echo h($debtor['id']); ?>" aria-hidden="true">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="paymentModalLabel<?php echo h($debtor['id']); ?>"><?= __('process_payment') ?></h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <form method="POST">
                                                     <!-- CSRF Protection -->
                                                     <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                                     
                                                     <div class="modal-body">
                                                         <input type="hidden" name="pay" value="1">
                                                         <input type="hidden" name="debtor_id" value="<?php echo h($debtor['id']); ?>">
                                                         <input type="hidden" name="debtor_currency" value="<?php echo h($debtor['currency']); ?>">
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('debtor_name') ?></label>
                                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($debtor['name']); ?>" readonly>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('current_balance') ?></label>
                                                            <input type="text" class="form-control" value="<?php echo number_format($debtor['balance'], 2) . ' ' . $debtor['currency']; ?>" readonly>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('payment_amount') ?></label>
                                                            <input type="number" class="form-control" name="amount" step="0.00001" required>
                                                        </div>

                                                        <!-- Payment Currency -->
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('payment_currency') ?></label>
                                                            <select class="form-control" name="currency" required onchange="checkCurrency(this, '<?php echo h($debtor['currency']); ?>', '<?php echo h($debtor['id']); ?>')">
                                                                <option value="USD" <?php echo h($debtor['currency']) == 'USD' ? 'selected' : ''; ?>><?= __('usd') ?></option>
                                                                <option value="AFS" <?php echo h($debtor['currency']) == 'AFS' ? 'selected' : ''; ?>><?= __('afs') ?></option>
                                                                <option value="EUR" <?php echo h($debtor['currency']) == 'EUR' ? 'selected' : ''; ?>><?= __('eur') ?></option>
                                                                <option value="DARHAM" <?php echo h($debtor['currency']) == 'DARHAM' ? 'selected' : ''; ?>><?= __('darham') ?></option>
                                                            </select>
                                                        </div>
                                                        
                                                        <!-- Exchange Rate Field - Initially Hidden -->
                                                        <div class="form-group" id="exchangeRateDiv<?php echo h($debtor['id']); ?>" style="display: none;">
                                                            <label class="form-label"><?= __('exchange_rate') ?> (1 <span id="selectedCurrency<?php echo h($debtor['id']); ?>"><?php echo h($debtor['currency']); ?></span> = ? <span id="debtorCurrency<?php echo h($debtor['id']); ?>"><?php echo h($debtor['currency']); ?></span>)</label>
                                                            <input type="number" class="form-control" name="exchange_rate" id="exchangeRate<?php echo h($debtor['id']); ?>" step="0.000001" placeholder="<?= __('enter_exchange_rate') ?>">
                                                            <small class="form-text text-muted"><?= __('enter_the_exchange_rate_to_convert_from_payment_currency_to_debtor_s_currency') ?></small>
                                                        </div>

                                                        <!-- Payment Date -->
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('payment_date') ?></label>
                                                            <input type="date" class="form-control" name="payment_date" required>
                                                        </div>
                                                        
                                                        <!-- Description -->
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('description') ?></label>
                                                            <input type="text" class="form-control" name="description">
                                                        </div>
                                                        
                                                        <!-- Paid To -->
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('paid_to') ?></label>   
                                                            <select class="form-control" name="paid_to" required>
                                                                <option value=""><?= __('select_main_account') ?></option>
                                                                <?php foreach ($main_accounts as $account): ?>
                                                                    <option value="<?php echo h($account['id']); ?>"><?php echo h($account['name']); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                                                        <button type="submit" name="pay" class="btn btn-primary"><?= __('process_payment') ?></button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Transactions Modal -->
                                    <div class="modal fade" id="transactionsModal<?php echo h($debtor['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                        <div class="modal-dialog modal-lg" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><?= __('transactions') ?> - <?php echo htmlspecialchars($debtor['name']); ?></h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="table-responsive">
                                                        <table class="table table-bordered table-striped">
                                                            <thead>
                                                                <tr>
                                                                    <th><?= __('date') ?></th>
                                                                    <th><?= __('amount') ?></th>
                                                                    <th><?= __('type') ?></th>
                                                                    <th><?= __('description') ?></th>
                                                                    <th><?= __('receipt') ?></th>
                                                                    <th><?= __('actions') ?></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                // Fetch transactions for this debtor
                                                                $transStmt = $pdo->prepare("SELECT * FROM debtor_transactions WHERE debtor_id = ? AND tenant_id = ? AND branch_id = ? ORDER BY payment_date DESC");
                                                                $transStmt->bindParam(1, $debtor['id'], PDO::PARAM_INT);
                                                                $transStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                                                $transStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                                                $transStmt->execute();
                                                                $transResult = $transStmt->fetchAll();

                                                                if (count($transResult) > 0) {
                                                                    foreach ($transResult as $transaction) {
                                                                        echo '<tr>';
                                                                        echo '<td>' . date('M d, Y H:i:s', strtotime($transaction['created_at'])) . '</td>';
                                                                        echo '<td>' . number_format($transaction['amount'], 2) . ' ' . $transaction['currency'] . '</td>';
                                                                        echo '<td>' . ($transaction['transaction_type'] == 'credit' ? '<span class="badge badge-success">Payment</span>' : '<span class="badge badge-danger">Debt</span>') . '</td>';
                                                                        echo '<td>' . htmlspecialchars($transaction['description']) . '</td>';
                                                                        echo '<td>' . htmlspecialchars($transaction['reference_number']) . '</td>';
                                                                        echo '<td>';
                                                                        echo '<div class="btn-group" role="group">';
                                                                        // Edit button
                                                                        echo '<button type="button" class="btn btn-warning btn-sm mr-1 edit-transaction-btn" 
                                                                            data-transaction-id="' . $transaction['id'] . '"
                                                                            data-debtor-id="' . $debtor['id'] . '"
                                                                            data-amount="' . $transaction['amount'] . '"
                                                                            data-currency="' . $transaction['currency'] . '"
                                                                            data-description="' . htmlspecialchars($transaction['description'], ENT_QUOTES) . '"
                                                                            data-payment-date="' . date('Y-m-d', strtotime($transaction['payment_date'])) . '"
                                                                            data-created-at="' . date('Y-m-d\TH:i', strtotime($transaction['created_at'])) . '">
                                                                            <i class="feather icon-edit-2"></i> ' . __('edit') . '
                                                                        </button>';
                                                                        echo '<button class="btn btn-info btn-sm mr-1" title="Print Receipt"
                                                                        onclick="printDebtorReceipt('.$transaction['id'].')">
                                                                    <i class="feather icon-printer"></i>
                                                                </button>';
                                                                        // Delete button with toast notification
                                                                        echo '<button type="button" class="btn btn-danger btn-sm delete-transaction-btn" 
                                                                            data-transaction-id="' . $transaction['id'] . '"
                                                                            data-debtor-id="' . $debtor['id'] . '"
                                                                            data-amount="' . $transaction['amount'] . '"
                                                                            data-currency="' . $transaction['currency'] . '">
                                                                            <i class="feather icon-trash"></i> ' . __('delete') . '
                                                                        </button>';
                                                                        echo '</div>';
                                                                        echo '</td>';
                                                                        echo '</tr>';
                                                                    }
                                                                } else {
                                                                    echo '<tr><td colspan="6" class="text-center">' . __('no_transactions_found') . '</td></tr>';
                                                                }
                                                                ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Edit Debtor Modal -->
                                    <div class="modal fade" id="editDebtorModal<?php echo h($debtor['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><?= __('edit_debtor') ?> - <?php echo htmlspecialchars($debtor['name']); ?></h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <form method="POST">
                                                     <!-- CSRF Protection -->
                                                     <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                                     
                                                     <div class="modal-body">
                                                         <input type="hidden" name="edit_debtor" value="1">
                                                         <input type="hidden" name="debtor_id" value="<?php echo h($debtor['id']); ?>">
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('name') ?> *</label>
                                                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($debtor['name']); ?>" required>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('email') ?></label>
                                                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($debtor['email']); ?>">
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('phone') ?></label>
                                                            <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($debtor['phone']); ?>">
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('address') ?></label>
                                                            <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($debtor['address']); ?></textarea>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('balance') ?> *</label>
                                                                <input type="number" class="form-control" name="balance" step="0.01" value="<?php echo h($debtor['balance']); ?>" required>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('currency') ?> *</label>
                                                            <select class="form-control" name="currency" required>
                                                                <option value="USD" <?php echo h($debtor['currency']) == 'USD' ? 'selected' : ''; ?>><?= __('usd') ?></option>
                                                                <option value="AFS" <?php echo h($debtor['currency']) == 'AFS' ? 'selected' : ''; ?>><?= __('afs') ?></option>
                                                                <option value="EUR" <?php echo h($debtor['currency']) == 'EUR' ? 'selected' : ''; ?>><?= __('eur') ?></option>
                                                                <option value="DARHAM" <?php echo h($debtor['currency']) == 'DARHAM' ? 'selected' : ''; ?>><?= __('darham') ?></option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('main_account') ?> *</label>
                                                            <select class="form-control" name="main_account_id" required>
                                                                <option value=""><?= __('select_main_account') ?></option>
                                                                <?php foreach ($main_accounts as $account): ?>
                                                                    <option value="<?php echo h($account['id']); ?>" <?php echo isset($debtor['main_account_id']) && $debtor['main_account_id'] == $account['id'] ? 'selected' : ''; ?>>
                                                                        <?php echo h($account['name']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('agreement_terms') ?></label>
                                                            <textarea class="form-control" name="agreement_terms" rows="4"><?php echo htmlspecialchars($debtor['agreement_terms'] ?? ''); ?></textarea>
                                                            <small class="text-muted"><?= __('these_terms_will_appear_on_the_printed_agreement') ?></small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                                                        <button type="submit" name="edit_debtor" class="btn btn-warning"><?= __('update_debtor') ?></button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <!-- [ Main Content ] end -->
                        </div>
                    </div>
                </div>
            
        </div>
    </div>



<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<!-- SweetAlert2 JS -->
<script src="../assets/plugins/sweetalert2/sweetalert2.min.js"></script>

<!-- DataTables JS -->
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>

<!-- Custom JS for Debtors Page -->

<script src="../js/debtor/debtors-interactions.js"></script>
<script src="../js/debtor/currency-check.js"></script>
<script src="../js/debtor/form-protection.js"></script>

<!-- Toast Container -->
<div class="toast-container"></div>

<!-- Edit Transaction Modal - Moved to root level -->
<div class="modal fade" id="editTransactionModal" tabindex="-1" role="dialog" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-gradient-warning text-white border-0">
                <h5 class="modal-title" id="editTransactionModalLabel">
                    <i class="feather icon-edit-2 mr-2"></i><?= __('edit_transaction') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editTransactionForm">
                    <input type="hidden" id="edit_transaction_id" name="transaction_id">
                    <input type="hidden" id="edit_debtor_id" name="debtor_id">
                    <input type="hidden" id="edit_original_amount" name="original_amount">
                    <input type="hidden" id="edit_currency" name="currency">
                    
                    <div class="form-group">
                        <div class="d-flex align-items-center mb-2">
                            
                            <label for="edit_amount" class="mb-0"><?= __('amount') ?></label>
                        </div>
                        <input type="number" class="form-control" id="edit_amount" name="amount" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <div class="d-flex align-items-center mb-2">
                            
                            <label for="edit_description" class="mb-0"><?= __('description') ?></label>
                        </div>
                        <input type="text" class="form-control" id="edit_description" name="description" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="d-flex align-items-center mb-2">
                                    
                                    <label for="edit_payment_date" class="mb-0"><?= __('payment_date') ?></label>
                                </div>
                                <input type="date" class="form-control" id="edit_payment_date" name="payment_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="d-flex align-items-center mb-2">
                                    
                                    <label for="edit_created_at_time" class="mb-0"><?= __('transaction_time') ?></label>
                                </div>
                                <input type="time" class="form-control" id="edit_created_at_time" name="created_at_time">
                                <small class="form-text text-muted mt-1">
                                    <i class="feather icon-info mr-1"></i><?= __('time_the_transaction_was_created') ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" id="edit_created_at_date" name="created_at_date">
                   
                </form>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-primary" id="saveTransactionBtn">
                    <i class="feather icon-save mr-1"></i><?= __('save_changes') ?>
                </button>
            </div>
        </div>
    </div>
</div>

</body>
</html>