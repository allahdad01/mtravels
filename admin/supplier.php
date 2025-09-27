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
    <script src="js/supplier/profile.js"></script>
                                                        
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