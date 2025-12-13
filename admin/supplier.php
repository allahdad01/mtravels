<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];


// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');

?>


    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../css/general/modal-styles.css">
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
                                        <h5 class="m-b-10"><?= __('supplier') ?></h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item"><a href="javascript:"><?= __('supplier') ?></a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [ breadcrumb ] end -->
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <!-- Toast Container -->
<div class="toast-container"></div>

<style>
.toast-container {
   position: fixed;
   top: 20px;
   right: 20px;
   z-index: 9999;
   max-width: 350px;
}

.toast {
   position: relative;
   background-color: #fff;
   border-radius: 8px;
   box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
   margin-bottom: 10px;
   overflow: hidden;
   opacity: 0;
   transform: translateX(40px);
   transition: all 0.3s ease;
   border-left: 4px solid transparent;
   padding: 15px;
}

.toast-showing {
   opacity: 1;
   transform: translateX(0);
}

.toast-removing {
   opacity: 0;
   transform: translateY(-20px);
}

.toast-success {
   border-left-color: #10b981;
}

.toast-error {
   border-left-color: #ef4444;
}

.toast-warning {
   border-left-color: #f59e0b;
}

.toast-info {
   border-left-color: #3b82f6;
}

.toast-title {
   display: flex;
   align-items: center;
   font-weight: 600;
   margin-bottom: 5px;
}

.toast-message {
   word-break: break-word;
   line-height: 1.5;
   color: #64748b;
}
</style>
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="mb-3 text-right">
                                         <!-- Add Supplier Button -->
                                        <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#addSupplierModal"><?= __('add_new_supplier') ?></button>
                                    </div>
                                    <!-- Supplier Tabs -->
                                    <ul class="nav nav-tabs mb-3" id="supplierTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <a class="nav-link active" id="activeSuppliers-tab" data-toggle="tab" href="#activeSuppliers" role="tab">
                                                <?= __('active_suppliers') ?>
                                            </a>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <a class="nav-link" id="inactiveSuppliers-tab" data-toggle="tab" href="#inactiveSuppliers" role="tab">
                                                <?= __('inactive_suppliers') ?>
                                            </a>
                                        </li>
                                    </ul>
                                    <div class="tab-content" id="supplierTabContent">
                                        <div class="tab-pane fade show active" id="activeSuppliers" role="tabpanel">
                                            <div class="card">
                                                <!-- body -->
                                                <div class="table-responsive">
                                                    <!-- Active Suppliers Table -->
                                                    <table class="table table-hover" id="activeSuppliersTable" width="100%">
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
                                                            <!-- Active Supplier Rows will be populated dynamically -->
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="inactiveSuppliers" role="tabpanel">
                                            <div class="card">
                                                <!-- body -->
                                                <div class="table-responsive">
                                                    <!-- Inactive Suppliers Table -->
                                                    <table class="table table-hover" id="inactiveSuppliersTable" width="100%">
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
                                                            <!-- Inactive Supplier Rows will be populated dynamically -->
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

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>