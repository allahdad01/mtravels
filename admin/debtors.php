<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

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
$tenant_id = $_SESSION['tenant_id'];
include 'handlers/debtors_handler.php';
?>


    <?php include '../includes/header.php'; ?>
<!-- Custom CSS for Debtors Page -->

<link rel="stylesheet" href="css/modal-styles.css">
<style>
/* Modern Dashboard Styling */
:root {
    --primary-color: #4099ff;
    --secondary-color: #2ed8b6;
    --danger-color: #ff5370;
    --warning-color: #ffb64d;
    --success-color: #2ed8b6;
    --dark-color: #222;
    --light-color: #f8f9fa;
    --border-radius: 8px;
    --box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    --transition: all 0.3s ease;
}

/* General Layout Improvements */
.pcoded-main-container {
    background-color: #f8f9fa;
    padding: 20px;
}

.page-wrapper {
    margin-top: 20px;
}

/* Card Enhancements */
.card {
    border: none;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    transition: var(--transition);
    margin-bottom: 24px;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.card-header {
    border-bottom: 1px solid rgba(0,0,0,0.05);
    padding: 1.25rem;
    background: white;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
}

/* Summary Cards */
.summary-card {
    padding: 1.5rem;
    border-radius: var(--border-radius);
    background: linear-gradient(45deg, var(--primary-color), #73b4ff);
    color: white;
    margin-bottom: 20px;
}

.summary-card h3 {
    font-size: 1.75rem;
    margin-bottom: 0.5rem;
}

/* Table Enhancements */
.table {
    margin-bottom: 0;
}

.table thead th {
    border-top: none;
    background-color: #f8f9fa;
    color: #495057;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
}

.table td {
    vertical-align: middle;
    padding: 1rem;
}

/* Button Styling */
.btn {
    border-radius: 50px;
    padding: 0.5rem 1.25rem;
    font-weight: 500;
    transition: var(--transition);
}

.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    margin: 0 2px;
}

.btn-primary {
    background: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-success {
    background: var(--success-color);
    border-color: var(--success-color);
}

/* Status Badge Styling */
.badge {
    padding: 0.5em 1em;
    border-radius: 50px;
    font-weight: 500;
}

.badge-light-primary {
    background-color: rgba(64, 153, 255, 0.1);
    color: var(--primary-color);
}

/* Search and Filter Styling */
.dataTables_wrapper .dataTables_filter input {
    border: 1px solid #dee2e6;
    border-radius: 50px;
    padding: 8px 16px;
    padding-left: 40px;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="%236c757d" class="bi bi-search" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>') no-repeat 16px center;
}

/* Avatar Styling */
.avatar {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1rem;
}

/* Modal Enhancements */
.modal-content {
    border: none;
    border-radius: var(--border-radius);
}

.modal-header {
    border-bottom: 1px solid rgba(0,0,0,0.05);
    padding: 1.5rem;
}

.modal-footer {
    border-top: 1px solid rgba(0,0,0,0.05);
    padding: 1.5rem;
}

/* Form Styling */
.form-control {
    border-radius: var(--border-radius);
    padding: 0.75rem 1rem;
    border: 1px solid #dee2e6;
    transition: var(--transition);
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
}

/* Navigation Tabs */
.nav-tabs {
    border-bottom: none;
    margin-bottom: 1.5rem;
}

.nav-tabs .nav-link {
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: var(--border-radius);
    color: #6c757d;
    transition: var(--transition);
}

.nav-tabs .nav-link.active {
    background-color: var(--primary-color);
    color: white;
}

/* Empty State Styling */
.empty-state {
    padding: 3rem;
    text-align: center;
}

.empty-state i {
    font-size: 3rem;
    color: #dee2e6;
    margin-bottom: 1rem;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .btn-icon {
        width: 28px;
        height: 28px;
    }
    
    .table td {
        padding: 0.75rem;
    }
    
    .card-header {
        padding: 1rem;
    }
}

/* Animation Effects */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.card {
    animation: fadeIn 0.3s ease-out;
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

/* Toast Notifications */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
}

.toast {
    background: white;
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 10px;
    min-width: 300px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    justify-content: space-between;
    animation: slideIn 0.3s ease-out;
    transition: all 0.3s ease;
}

.toast.success {
    border-left: 4px solid var(--success-color);
}

.toast.error {
    border-left: 4px solid var(--danger-color);
}

.toast.warning {
    border-left: 4px solid var(--warning-color);
}

.toast-content {
    display: flex;
    align-items: center;
}

.toast-icon {
    margin-right: 12px;
    font-size: 20px;
}

.toast.success .toast-icon {
    color: var(--success-color);
}

.toast.error .toast-icon {
    color: var(--danger-color);
}

.toast.warning .toast-icon {
    color: var(--warning-color);
}

.toast-message {
    color: var(--dark-color);
    font-size: 14px;
    margin: 0;
}

.toast-close {
    color: #6c757d;
    background: none;
    border: none;
    padding: 0;
    margin-left: 15px;
    cursor: pointer;
    font-size: 18px;
    opacity: 0.7;
    transition: opacity 0.3s ease;
}

.toast-close:hover {
    opacity: 1;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

/* ... existing styles ... */
</style>
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
<!-- Add this right before the closing </body> tag -->
<!-- Toast Container -->
<div class="toast-container"></div>
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="../assets/plugins/sweetalert2/sweetalert2.min.css">
 <!-- [ Main Content ] start -->
 <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= __('debtors_management') ?></h2>
        <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addDebtorModal">
            <i class="fas fa-plus"></i> <?= __('add_new_debtor') ?>
        </button>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo h($success_message); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo h($error_message); ?></div>
    <?php endif; ?>
    
    <!-- Total Debts by Currency Section -->
    <?php if (!empty($currency_totals)): ?>
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="feather icon-bar-chart-2 mr-2"></i><?= __('total_debts_summary') ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($currency_totals as $currency => $total): ?>
                <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                    <div class="card border-0 bg-light h-100">
                        <div class="card-body text-center">
                            <h3 class="text-primary mb-1"><?php echo number_format($total, 2); ?></h3>
                            <p class="mb-0 font-weight-bold"><?php echo htmlspecialchars($currency); ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Status Toggle Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo h($status_filter) === 'active' ? 'active' : ''; ?>" href="debtors.php">
                <i class="feather icon-user-check mr-1"></i><?= __('active_debtors') ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo h($status_filter) === 'inactive' ? 'active' : ''; ?>" href="debtors.php?status=inactive">
                <i class="feather icon-user-minus mr-1"></i><?= __('inactive_debtors') ?>
            </a>
        </li>
    </ul>
    
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

    
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent py-3">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0 text-primary">
                        <i class="feather icon-users mr-2"></i><?= __(ucfirst($status_filter) . '_debtors') ?>
                    </h5>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped" id="debtorsTable" width="100%">
                    <thead class="thead-light">
                        <tr>
                            <th>
                                <div class="d-flex align-items-center">
                                    <i class="feather icon-user mr-2 text-muted"></i><?= __('name') ?>
                                </div>
                            </th>
                            <th>
                                <div class="d-flex align-items-center">
                                    <i class="feather icon-mail mr-2 text-muted"></i><?= __('email') ?>
                                </div>
                            </th>
                            <th>
                                <div class="d-flex align-items-center">
                                    <i class="feather icon-phone mr-2 text-muted"></i><?= __('phone') ?>
                                </div>
                            </th>
                            <th>
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
                        <?php if (count($debtors) > 0): ?>
                            <?php foreach ($debtors as $debtor): ?>
                                <tr class="debtor-row">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-sm bg-light-primary rounded-circle text-primary mr-2">
                                                <?php echo strtoupper(substr($debtor['name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($debtor['name']); ?></h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($debtor['email'])): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($debtor['email']); ?>" class="text-body">
                                                <?php echo htmlspecialchars($debtor['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted"><?= __('not_provided') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($debtor['phone'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($debtor['phone']); ?>" class="text-body">
                                                <?php echo htmlspecialchars($debtor['phone']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted"><?= __('not_provided') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
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
                                                <span class="badge badge-success mr-2"><?= __('paid') ?></span>
                                            <?php elseif ($debtor['balance'] > 0): ?>
                                                <span class="badge badge-warning mr-2"><?= __('pending') ?></span>
                                            <?php endif; ?>
                                            <span class="font-weight-medium">
                                                <?php echo number_format($debtor['balance'], 2); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-primary">
                                            <?php echo htmlspecialchars($debtor['currency']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center">
                                            <?php if ($status_filter === 'active'): ?>
                                                <button type="button" class="btn btn-icon btn-primary btn-sm mr-1" data-toggle="modal" data-target="#paymentModal<?php echo h($debtor['id']); ?>" title="<?= __('process_payment') ?>">
                                                    <i class="feather icon-credit-card"></i>
                                                </button>
                                                <button type="button" class="btn btn-icon btn-info btn-sm mr-1" data-toggle="modal" data-target="#transactionsModal<?php echo h($debtor['id']); ?>" title="<?= __('view_transactions') ?>">
                                                    <i class="feather icon-list"></i>
                                                </button>
                                                <button type="button" class="btn btn-icon btn-warning btn-sm mr-1" data-toggle="modal" data-target="#editDebtorModal<?php echo h($debtor['id']); ?>" title="<?= __('edit_debtor') ?>">
                                                    <i class="feather icon-edit-2"></i>
                                                </button>
                                                <a href="print_debtor_statement.php?id=<?php echo h($debtor['id']); ?>" class="btn btn-icon btn-secondary btn-sm mr-1" target="_blank" title="<?= __('print_statement') ?>">
                                                    <i class="feather icon-printer"></i>
                                                </a>
                                                <a href="print_agreement.php?id=<?php echo h($debtor['id']); ?>" class="btn btn-icon btn-dark btn-sm mr-1" target="_blank" title="<?= __('print_agreement') ?>">
                                                    <i class="feather icon-printer"></i>
                                                </a>
                                                
                                                <?php if ($debtor['balance'] <= 0): ?>
                                                    <form method="POST" class="d-inline" name="debtor_status_form" onsubmit="return confirm('<?= __('confirm_deactivate_debtor') ?>');">
                                                        <input type="hidden" name="debtor_id" value="<?php echo h($debtor['id']); ?>">
                                                        <button type="submit" name="deactivate_debtor" class="btn btn-icon btn-danger btn-sm" title="<?= __('deactivate_debtor') ?>">
                                                            <i class="feather icon-user-x"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <!-- Actions for inactive debtors -->
                                                <button type="button" class="btn btn-icon btn-info btn-sm mr-1" data-toggle="modal" data-target="#transactionsModal<?php echo h($debtor['id']); ?>" title="<?= __('view_transactions') ?>">
                                                    <i class="feather icon-list"></i>
                                                </button>
                                                <form method="POST" class="d-inline" name="debtor_status_form" onsubmit="return confirm('<?= __('confirm_reactivate_debtor') ?>');">
                                                    <input type="hidden" name="debtor_id" value="<?php echo h($debtor['id']); ?>">
                                                    <button type="submit" name="reactivate_debtor" class="btn btn-icon btn-success btn-sm" title="<?= __('reactivate_debtor') ?>">
                                                        <i class="feather icon-user-check"></i>
                                                    </button>
                                                    <a href="print_debtor_statement.php?id=<?php echo h($debtor['id']); ?>" class="btn btn-icon btn-secondary btn-sm mr-1" target="_blank" title="<?= __('print_statement') ?>">
                                                    <i class="feather icon-printer"></i>
                                                </a>
                                                </form>
                                            <?php endif; ?>
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
            <div class="mt-4">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
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
                    <div class="modal-body">
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
                        
                        <!-- Receipt -->
                        <div class="form-group">
                            <label class="form-label"><?= __('receipt') ?></label>
                            <input type="text" class="form-control" name="receipt">
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
                                $transStmt = $conn->prepare("SELECT * FROM debtor_transactions WHERE debtor_id = ? ORDER BY payment_date DESC");
                                $transStmt->bind_param("i", $debtor['id']);
                                $transStmt->execute();
                                $transResult = $transStmt->get_result();
                                
                                if ($transResult->num_rows > 0) {
                                    while ($transaction = $transResult->fetch_assoc()) {
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
                    <div class="modal-body">
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

  <!-- [ Main Content ] end -->
  </div>
                    </div>
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



                            <!-- Edit Transaction Modal -->


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
<script src="js/debtors-translations.js"></script>
<script src="js/modern-ui.js"></script>
<script src="js/debtors-management.js"></script>

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

<!-- Direct Fix for Edit Transaction Button -->
<script>
// This script will ensure the edit transaction button works
document.addEventListener('DOMContentLoaded', function() {
    
    // Direct event handler for all edit transaction buttons
    const editButtons = document.querySelectorAll('.edit-transaction-btn');
    console.log('Found ' + editButtons.length + ' edit transaction buttons');
    
    editButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Edit button clicked directly');
            
            // Get data attributes
            const transactionId = this.getAttribute('data-transaction-id');
            const debtorId = this.getAttribute('data-debtor-id');
            const amount = this.getAttribute('data-amount');
            const currency = this.getAttribute('data-currency');
            const description = this.getAttribute('data-description');
            const paymentDate = this.getAttribute('data-payment-date');
            const createdAt = this.getAttribute('data-created-at');
            
            console.log('Transaction data:', {
                transactionId,
                debtorId,
                amount,
                currency,
                description,
                paymentDate,
                createdAt
            });
            
            // Close any open modals first
            $('.modal').modal('hide');
            
            // Wait a moment for any previous modal to close
            setTimeout(function() {
                // Set form values
                document.getElementById('edit_transaction_id').value = transactionId;
                document.getElementById('edit_debtor_id').value = debtorId;
                document.getElementById('edit_original_amount').value = amount;
                document.getElementById('edit_currency').value = currency;
                document.getElementById('edit_amount').value = amount;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_payment_date').value = paymentDate;
                
                // Handle created_at datetime
                if (createdAt) {
                    const createdAtObj = new Date(createdAt);
                    // Format time as HH:MM
                    const hours = createdAtObj.getHours().toString().padStart(2, '0');
                    const minutes = createdAtObj.getMinutes().toString().padStart(2, '0');
                    document.getElementById('edit_created_at_time').value = `${hours}:${minutes}`;
                    document.getElementById('edit_created_at_date').value = paymentDate;
                }
                
                // Show the modal
                $('#editTransactionModal').modal('show');
            }, 300);
        });
    });
    
    // Direct event handler for the save button
    const saveButton = document.getElementById('saveTransactionBtn');
    if (saveButton) {
        console.log('Save button found, attaching direct handler');
        saveButton.addEventListener('click', function(e) {
            console.log('Save button clicked directly');
            
            const form = document.getElementById('editTransactionForm');
            
            // Enhanced validation
            const amount = document.getElementById('edit_amount').value;
            const description = document.getElementById('edit_description').value;
            const paymentDate = document.getElementById('edit_payment_date').value;
            
            // Validate required fields with visual feedback
            let isValid = true;
            
            if (!amount || amount <= 0) {
                console.log('Amount validation failed');
                const field = document.getElementById('edit_amount');
                field.classList.add('is-invalid');
                isValid = false;
            }
            
            if (!description.trim()) {
                console.log('Description validation failed');
                const field = document.getElementById('edit_description');
                field.classList.add('is-invalid');
                isValid = false;
            }
            
            if (!paymentDate) {
                console.log('Payment date validation failed');
                const field = document.getElementById('edit_payment_date');
                field.classList.add('is-invalid');
                isValid = false;
            }
            
            if (!isValid) {
                console.log('Form validation failed');
                Swal.fire({
                    icon: 'warning',
                    title: 'Please complete all required fields',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
                return;
            }
            
            // Collect form data
            const formData = new FormData();
            formData.append('transaction_id', document.getElementById('edit_transaction_id').value);
            formData.append('debtor_id', document.getElementById('edit_debtor_id').value);
            formData.append('original_amount', document.getElementById('edit_original_amount').value);
            formData.append('amount', document.getElementById('edit_amount').value);
            formData.append('currency', document.getElementById('edit_currency').value);
            formData.append('description', document.getElementById('edit_description').value);
            formData.append('payment_date', document.getElementById('edit_payment_date').value);
            formData.append('created_at_time', document.getElementById('edit_created_at_time').value);
            formData.append('created_at_date', document.getElementById('edit_created_at_date').value);
            
            // Show loading indicator
            Swal.fire({
                title: 'Updating transaction...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Submit form data via fetch API
            fetch('update_debtor_transaction.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response received:', response);
                return response.json();
            })
            .then(data => {
                console.log('Data received:', data);
                Swal.close();
                if (data.success) {
                    // Close modal
                    $('#editTransactionModal').modal('hide');
                    
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: data.message || 'Transaction updated successfully',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                    
                    // Reload the page to refresh the transaction list
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    // Show error message
                    Swal.fire({
                        icon: 'error',
                        title: data.message || 'Failed to update transaction',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'An error occurred while updating the transaction',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            });
        });
    } else {
        console.error('Save button not found!');
    }
    
    // Direct event handler for delete transaction buttons
    const deleteButtons = document.querySelectorAll('.delete-transaction-btn');
    console.log('Found ' + deleteButtons.length + ' delete transaction buttons');
    
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Delete button clicked');
            
            // Get data attributes
            const transactionId = this.getAttribute('data-transaction-id');
            const debtorId = this.getAttribute('data-debtor-id');
            const amount = this.getAttribute('data-amount');
            const currency = this.getAttribute('data-currency');
            
            console.log('Transaction data for deletion:', {
                transactionId,
                debtorId,
                amount,
                currency
            });
            
            // Show toast notification instead of confirmation dialog
            Swal.fire({
                icon: 'info',
                title: 'Deleting transaction...',
                text: 'Transaction will be deleted and payment will be reversed.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            
            // Create form data for the delete request
            const formData = new FormData();
            formData.append('transaction_id', transactionId);
            formData.append('debtor_id', debtorId);
            formData.append('amount', amount);
            formData.append('currency', currency);
            formData.append('delete_transaction', 'true');
            
            // Submit form data via fetch API after a short delay
            setTimeout(() => {
                fetch('delete_debtor_transaction.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Response received:', response);
                    return response.json();
                })
                .then(data => {
                    console.log('Data received:', data);
                    if (data.success) {
                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: data.message || 'Transaction deleted successfully',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                        
                        // Reload the page to refresh the transaction list
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        // Show error message
                        Swal.fire({
                            icon: 'error',
                            title: data.message || 'Failed to delete transaction',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'An error occurred while deleting the transaction',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                });
            }, 2000);
        });
    });
});
</script>
<!-- Function to check currency and show/hide exchange rate field -->
<script>
function checkCurrency(selectElement, debtorCurrency, debtorId) {
    const selectedCurrency = selectElement.value;
    const exchangeRateDiv = document.getElementById('exchangeRateDiv' + debtorId);
    const selectedCurrencySpan = document.getElementById('selectedCurrency' + debtorId);
    const debtorCurrencySpan = document.getElementById('debtorCurrency' + debtorId);
    const exchangeRateInput = document.getElementById('exchangeRate' + debtorId);
    
    if (selectedCurrency !== debtorCurrency) {
        // Show exchange rate field
        exchangeRateDiv.style.display = 'block';
        selectedCurrencySpan.textContent = selectedCurrency;
        debtorCurrencySpan.textContent = debtorCurrency;
        exchangeRateInput.required = true;
    } else {
        // Hide exchange rate field
        exchangeRateDiv.style.display = 'none';
        exchangeRateInput.required = false;
        exchangeRateInput.value = '';
    }
}
</script>
</body>
</html>