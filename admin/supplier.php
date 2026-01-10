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

    .badge {
        font-size: 0.85em;
        padding: 0.5em 0.75em;
        border-radius: 20px;
        font-weight: 500;
    }

    .badge-success {
        background-color: #28a745;
    }

    .badge-warning {
        background-color: #ffc107;
        color: #212529;
    }

    .badge-info {
        background-color: #17a2b8;
    }

    .table-responsive {
        border-radius: 10px;

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

    /* Toast styles */
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
    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="page-header card">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-0"><i class="feather icon-users mr-2"></i><?php echo __('supplier'); ?></h5>
                                <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;"><?php echo __('manage_suppliers'); ?></p>
                            </div>
                            <div class="col-md-6 text-end">
                                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="feather icon-arrow-left mr-1"></i><?php echo __('back_to_dashboard'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="main-content">
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
                                    <div class="mb-3 d-flex justify-content-between align-items-center">
                                         <!-- Add Supplier Button -->
                                        <button class="btn btn-primary" data-toggle="modal" data-target="#addSupplierModal"><?= __('add_new_supplier') ?></button>
                                    </div>

                                    <!-- Search Bar -->
                                    <div class="card mb-3">
                                        <div class="card-body pb-3">
                                            <form class="form-inline" onsubmit="return false;">
                                                <div class="form-group mb-0 flex-grow-1">
                                                    <input 
                                                        type="text" 
                                                        id="searchSupplier"
                                                        name="search"
                                                        class="form-control w-100" 
                                                        placeholder="Search by supplier name or ID..." 
                                                    >
                                                </div>
                                                <button type="button" class="btn btn-info ml-2" onclick="SupplierManagement.handleSearch()">
                                                    <i class="feather icon-search"></i> <?= __('search') ?>
                                                </button>
                                                <button type="button" class="btn btn-secondary ml-2" onclick="document.getElementById('searchSupplier').value = ''; SupplierManagement.loadSuppliers();">
                                                    <i class="feather icon-x"></i> <?= __('clear') ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Supplier Tabs -->
                                    <ul class="nav nav-tabs mb-3" id="supplierTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <a class="nav-link active" id="activeSuppliers-tab" data-toggle="tab" href="#activeSuppliers" role="tab">
                                                <?= __('active_suppliers') ?> <span id="activeCount" class="ml-2">0</span>
                                            </a>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <a class="nav-link" id="inactiveSuppliers-tab" data-toggle="tab" href="#inactiveSuppliers" role="tab">
                                                <?= __('inactive_suppliers') ?> <span id="inactiveCount" class="ml-2">0</span>
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
                                                 <!-- Pagination for Active Suppliers -->
                                                 <div id="activePaginationContainer"></div>
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
                                                 <!-- Pagination for Inactive Suppliers -->
                                                 <div id="inactivePaginationContainer"></div>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                         </div>
                     </div>
                 </div>
                 </div> <!-- end of main-content -->
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