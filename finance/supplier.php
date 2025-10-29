<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');

?>


    <?php include '../includes/header_finance.php'; ?>
    <link rel="stylesheet" href="css/modal-styles.css">
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

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="addSupplierForm">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light border-0">
                    <h5 class="modal-title">
                        <i class="feather icon-plus-circle text-primary mr-2"></i>
                        <?= __('add_new_supplier') ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="form-label"><?= __('name') ?></label>
                        <input type="text" class="form-control" id="supplierName" name="name" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label"><?= __('contact_person') ?></label>
                        <input type="text" class="form-control" id="contactPerson" name="contact_person">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label"><?= __('phone') ?></label>
                                <input type="text" class="form-control" id="supplierPhone" name="phone" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label"><?= __('email') ?></label>
                                <input type="email" class="form-control" id="supplierEmail" name="email">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label"><?= __('currency') ?></label>
                                <select class="form-control" id="currency" name="currency" required>
                                    <option value="AFS"><?= __('afs') ?></option>
                                    <option value="USD"><?= __('usd') ?></option>
                                    <option value="EUR"><?= __('eur') ?></option>
                                    <option value="DARHAM"><?= __('darham') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label"><?= __('balance') ?></label>
                                <input type="number" step="0.01" class="form-control" id="supplierBalance" 
                                       name="balance" value="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label"><?= __('supplier_type') ?></label>
                                <select class="form-control" id="supplierType" name="supplier_type" required>
                                    <option value="Internal"><?= __('internal') ?></option>
                                    <option value="External"><?= __('external') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label"><?= __('address') ?></label>
                        <textarea class="form-control" id="supplierAddress" name="address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save mr-2"></i><?= __('add_supplier') ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>




<!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    <script src="js/supplier/supplier_management.js"></script>

<script>
// Toast notification system
const toastConfig = {
    duration: 4000,      // Display duration in ms
    animationDuration: 300,  // Animation duration in ms
    maxToasts: 3        // Maximum number of toasts to show at once
};

// Collection to track active toasts
let activeToasts = [];

/**
 * Show a toast notification
 * @param {string} message - The message to display
 * @param {string} type - Type of toast (success, error, warning, info)
 * @param {object} options - Optional configuration overrides
 */
function showToast(message, type = 'success', options = {}) {
    const config = { ...toastConfig, ...options };

    // Create the toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    // Set icon based on type
    let icon = 'check-circle';
    switch(type) {
        case 'error':
            icon = 'alert-circle';
            break;
        case 'warning':
            icon = 'alert-triangle';
            break;
        case 'info':
            icon = 'info';
            break;
    }

    // Set toast content
    toast.innerHTML = `
        <div class="toast-title">
            <i class="feather icon-${icon} mr-2"></i>
            ${type.charAt(0).toUpperCase() + type.slice(1)}
        </div>
        <div class="toast-message">${message}</div>
    `;

    // Manage toast collection
    if (activeToasts.length >= toastConfig.maxToasts) {
        const oldestToast = activeToasts.shift();
        if (oldestToast && oldestToast.parentNode) {
            oldestToast.classList.add('toast-removing');
            setTimeout(() => oldestToast.remove(), config.animationDuration);
        }
    }

    // Add toast to container
    const container = document.querySelector('.toast-container');
    container.appendChild(toast);
    activeToasts.push(toast);

    // Trigger animation
    requestAnimationFrame(() => toast.classList.add('toast-showing'));

    // Auto dismiss
    setTimeout(() => {
        toast.classList.add('toast-removing');
        setTimeout(() => {
            toast.remove();
            activeToasts = activeToasts.filter(t => t !== toast);
        }, config.animationDuration);
    }, config.duration);

    return toast;
}

// Convert all alerts to toasts
document.addEventListener('DOMContentLoaded', function() {
    // Success alerts
    document.querySelectorAll('.alert-success').forEach(alert => {
        const message = alert.textContent.trim();
        showToast(message, 'success');
        alert.remove();
    });

    // Error alerts
    document.querySelectorAll('.alert-danger').forEach(alert => {
        const message = alert.textContent.trim();
        showToast(message, 'error');
        alert.remove();
    });

    // Warning alerts
    document.querySelectorAll('.alert-warning').forEach(alert => {
        const message = alert.textContent.trim();
        showToast(message, 'warning');
        alert.remove();
    });
});

// Replace all existing alert() calls with toast notifications
window.oldAlert = window.alert;
window.alert = function(message) {
    showToast(message, 'info');
};
</script>
                                                        


<!-- Edit Supplier tab -->
<div class="modal fade" id="editSupplierModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="editSupplierForm">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light border-0">
                    <h5 class="modal-title">
                        <i class="feather icon-edit text-primary mr-2"></i>
                        <?= __('edit_supplier') ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editSupplierId" name="id">
                    <div class="form-group mb-3">
                        <label class="form-label"><?= __('name') ?></label>
                        <input type="text" class="form-control" id="editSupplierName" name="name" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label"><?= __('contact_person') ?></label>
                        <input type="text" class="form-control" id="editContactPerson" name="contact_person">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label"><?= __('phone') ?></label>
                                <input type="text" class="form-control" id="editPhone" name="phone" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label"><?= __('email') ?></label>
                                <input type="email" class="form-control" id="editEmail" name="email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label"><?= __('supplier_type') ?></label>
                                <select class="form-control" id="editSupplierType" name="supplier_type" required>
                                    <option value="Internal"><?= __('internal') ?></option>
                                    <option value="External"><?= __('external') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label"><?= __('currency') ?></label>
                                <select class="form-control" id="editCurrency" name="currency" required>
                                    <option value="AFS"><?= __('afs') ?></option>
                                    <option value="USD"><?= __('usd') ?></option>
                                    <option value="EUR"><?= __('eur') ?></option>
                                    <option value="DARHAM"><?= __('darham') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label"><?= __('balance') ?></label>
                                <input type="number" step="0.000001" class="form-control" id="editBalance" 
                                       name="balance" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label"><?= __('address') ?></label>
                        <textarea class="form-control" id="editAddress" name="address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save mr-2"></i><?= __('save_changes') ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>


<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>