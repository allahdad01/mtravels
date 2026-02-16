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
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../css/general/modal-styles.css">

<style>
/* Modern Design System */
:root {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #818cf8;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-300: #d1d5db;
    --gray-600: #4b5563;
    --gray-700: #374151;
    --gray-800: #1f2937;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    --radius: 12px;
    --radius-sm: 8px;
}

/* Reset and Base Styles */
.pcoded-main-container {
    background: var(--gray-50);
    min-height: 100vh;
}

/* Modern Page Header */
.page-header.card {
    background: white;
    border: none;
    margin-bottom: 24px;
    padding: 24px 32px !important;
    box-shadow: var(--shadow-sm);
    border-radius: var(--radius);
    border-left: 4px solid var(--primary);
}

.page-header h5 {
    color: var(--gray-800);
    margin: 0;
    font-weight: 700;
    font-size: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-header p {
    color: var(--gray-600);
    margin: 4px 0 0 0;
    font-size: 14px;
}

.page-header .btn {
    background: white;
    color: var(--gray-700);
    border: 1px solid var(--gray-300);
    border-radius: var(--radius-sm);
    padding: 8px 20px;
    font-weight: 500;
    transition: all 0.2s;
}

.page-header .btn:hover {
    background: var(--gray-50);
    border-color: var(--gray-400);
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
}

/* Action Bar */
.action-bar {
    display: flex;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
    align-items: center;
}

/* Search Card */
.search-card {
    background: white;
    border-radius: var(--radius);
    padding: 20px 24px;
    box-shadow: var(--shadow-sm);
    margin-bottom: 24px;
    border: 1px solid var(--gray-200);
}

.search-form {
    display: flex;
    gap: 12px;
    align-items: center;
}

.search-form .form-control {
    flex: 1;
    border: 1px solid var(--gray-300);
    border-radius: var(--radius-sm);
    padding: 10px 16px;
    font-size: 14px;
    transition: all 0.2s;
}

.search-form .form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    outline: none;
}

/* Modern Buttons */
.btn-primary {
    background: var(--primary);
    border: none;
    border-radius: var(--radius-sm);
    padding: 10px 24px;
    font-weight: 600;
    color: white;
    transition: all 0.2s;
    box-shadow: var(--shadow-sm);
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

.btn-info {
    background: var(--primary);
    border: none;
    border-radius: var(--radius-sm);
    padding: 10px 20px;
    color: white;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-info:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.btn-secondary {
    background: white;
    border: 1px solid var(--gray-300);
    border-radius: var(--radius-sm);
    padding: 10px 20px;
    color: var(--gray-700);
    font-weight: 500;
    transition: all 0.2s;
}

.btn-secondary:hover {
    background: var(--gray-50);
    border-color: var(--gray-400);
}

/* Modern Tabs */
.nav-tabs {
    border-bottom: 2px solid var(--gray-200);
    margin-bottom: 24px;
}

.nav-tabs .nav-link {
    border: none;
    color: var(--gray-600);
    padding: 12px 24px;
    font-weight: 500;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
}

.nav-tabs .nav-link:hover {
    color: var(--primary);
    border-bottom-color: transparent;
}

.nav-tabs .nav-link.active {
    color: var(--primary);
    background: transparent;
    border-bottom: 2px solid var(--primary);
}

.nav-tabs .nav-link span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--gray-100);
    color: var(--gray-700);
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    min-width: 24px;
}

.nav-tabs .nav-link.active span {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
}

/* Card Redesign */
.card {
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    background: white;
    overflow: hidden;
}

.card-header {
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
    padding: 16px 24px;
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
    color: var(--gray-800);
    font-size: 16px;
}

/* Table Redesign */
.table-responsive {
    border-radius: 0;
}

.table {
    margin-bottom: 0;
}

.table thead th {
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
    font-weight: 600;
    color: var(--gray-700);
    padding: 16px 20px;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table tbody td {
    padding: 16px 20px;
    vertical-align: middle;
    border-bottom: 1px solid var(--gray-100);
    color: var(--gray-700);
}

.table tbody tr {
    transition: background 0.2s;
}

.table tbody tr:hover {
    background: var(--gray-50);
}

.table tbody tr:last-child td {
    border-bottom: none;
}

/* Badges */
.badge {
    font-size: 12px;
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: 600;
    letter-spacing: 0.3px;
}

.badge-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.badge-warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.badge-info {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
}

/* Toast Notifications */
.toast-container {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 9999;
    max-width: 400px;
}

.toast {
    background: white;
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow-lg);
    margin-bottom: 12px;
    overflow: hidden;
    opacity: 0;
    transform: translateX(100px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid var(--gray-200);
    padding: 16px;
}

.toast-showing {
    opacity: 1;
    transform: translateX(0);
}

.toast-removing {
    opacity: 0;
    transform: translateX(100px);
}

.toast-success {
    border-left: 4px solid var(--success);
}

.toast-error {
    border-left: 4px solid var(--danger);
}

.toast-warning {
    border-left: 4px solid var(--warning);
}

.toast-info {
    border-left: 4px solid var(--primary);
}

.toast-title {
    font-weight: 600;
    margin-bottom: 4px;
    color: var(--gray-800);
    display: flex;
    align-items: center;
    gap: 8px;
}

.toast-message {
    color: var(--gray-600);
    font-size: 14px;
    line-height: 1.5;
}

/* Pagination */
#activePaginationContainer,
#inactivePaginationContainer {
    padding: 16px 24px;
    border-top: 1px solid var(--gray-200);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 48px 24px;
    color: var(--gray-500);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

/* Responsive */
@media (max-width: 768px) {
    .action-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-form {
        flex-direction: column;
    }
    
    .page-header.card {
        padding: 20px !important;
    }
    
    .table thead {
        display: none;
    }
    
    .table tbody tr {
        display: block;
        margin-bottom: 16px;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-sm);
    }
    
    .table tbody td {
        display: flex;
        justify-content: space-between;
        padding: 12px 16px;
        border: none;
        border-bottom: 1px solid var(--gray-100);
    }
    
    .table tbody td:before {
        content: attr(data-label);
        font-weight: 600;
        color: var(--gray-700);
    }
}
</style>

<!-- Main Content -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- Page Header -->
                <div class="page-header card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5>
                                <i class="feather icon-users"></i>
                                <?php echo __('supplier'); ?>
                            </h5>
                            <p><?php echo __('manage_suppliers'); ?></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="feather icon-arrow-left"></i> <?php echo __('back_to_dashboard'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="main-content">
                            <!-- Toast Container -->
                            <div class="toast-container"></div>

                            <!-- Action Bar -->
                            <div class="action-bar">
                                <button class="btn btn-primary" data-toggle="modal" data-target="#addSupplierModal">
                                    <i class="feather icon-plus"></i> <?= __('add_new_supplier') ?>
                                </button>
                            </div>

                            <!-- Search Card -->
                            <div class="search-card">
                                <form class="search-form" onsubmit="return false;">
                                    <input 
                                        type="text" 
                                        id="searchSupplier"
                                        name="search"
                                        class="form-control" 
                                        placeholder="Search by supplier name or ID..."
                                    >
                                    <button type="button" class="btn btn-info" onclick="SupplierManagement.handleSearch()">
                                        <i class="feather icon-search"></i> <?= __('search') ?>
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('searchSupplier').value = ''; SupplierManagement.loadSuppliers();">
                                        <i class="feather icon-x"></i> <?= __('clear') ?>
                                    </button>
                                </form>
                            </div>

                            <!-- Tabs -->
                            <ul class="nav nav-tabs" id="supplierTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link active" id="activeSuppliers-tab" data-toggle="tab" href="#activeSuppliers" role="tab">
                                        <?= __('active_suppliers') ?> <span id="activeCount">0</span>
                                    </a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link" id="inactiveSuppliers-tab" data-toggle="tab" href="#inactiveSuppliers" role="tab">
                                        <?= __('inactive_suppliers') ?> <span id="inactiveCount">0</span>
                                    </a>
                                </li>
                            </ul>

                            <!-- Tab Content -->
                            <div class="tab-content" id="supplierTabContent">
                                <!-- Active Suppliers -->
                                <div class="tab-pane fade show active" id="activeSuppliers" role="tabpanel">
                                    <div class="card">
                                        <div class="table-responsive">
                                            <table class="table" id="activeSuppliersTable">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th><?= __('supplier_info') ?></th>
                                                        <th><?= __('supplier_type') ?></th>
                                                        <th><?= __('balance') ?></th>
                                                        <th><?= __('currency') ?></th>
                                                        <th><?= __('address') ?></th>
                                                        <th><?= __('actions') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="activeSupplierTableBody">
                                                    <!-- Populated dynamically -->
                                                </tbody>
                                            </table>
                                        </div>
                                        <div id="activePaginationContainer"></div>
                                    </div>
                                </div>

                                <!-- Inactive Suppliers -->
                                <div class="tab-pane fade" id="inactiveSuppliers" role="tabpanel">
                                    <div class="card">
                                        <div class="table-responsive">
                                            <table class="table" id="inactiveSuppliersTable">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th><?= __('supplier_info') ?></th>
                                                        <th><?= __('supplier_type') ?></th>
                                                        <th><?= __('balance') ?></th>
                                                        <th><?= __('currency') ?></th>
                                                        <th><?= __('address') ?></th>
                                                        <th><?= __('actions') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="inactiveSupplierTableBody">
                                                    <!-- Populated dynamically -->
                                                </tbody>
                                            </table>
                                        </div>
                                        <div id="inactivePaginationContainer"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../modals/supplier/add_supplier.php'; ?>
<?php include '../modals/supplier/edit_supplier.php'; ?>

<!-- Required Js -->

    <script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="../js/supplier/supplier_management.js"></script>
<script src="../js/supplier/toast.js"></script>

<?php include '../includes/admin_footer.php'; ?>
</body>
</html>