<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];


if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
// Get any messages from the session
$alertMessage = '';
$alertType = '';

if (isset($_SESSION['settings_message'])) {
    $alertMessage = $_SESSION['settings_message'];
    $alertType = $_SESSION['settings_type'] ?? 'success';
    // Clear the message after displaying
    unset($_SESSION['settings_message']);
    unset($_SESSION['settings_type']);
}



// Database connection
require_once('../includes/db.php');

?>



<?php include '../includes/header.php'; ?>
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
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="row">
                            <div class="col-sm-12">
                               
                                    <!-- body -->
    <div class="container-fluid">
        <!-- Alert Section -->
        <?php if ($alertMessage): ?>
        <div class="alert alert-<?= $alertType ?> alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="feather icon-check-circle mr-2"></i>
                <strong><?= htmlspecialchars($alertMessage) ?></strong>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="feather icon-settings mr-2"></i><?= __('agency_settings') ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if ($settings): ?>
                        <form action="updateSettings.php" method="POST" enctype="multipart/form-data" class="settings-form">
    <!-- CSRF Protection -->
    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
    
                            <input type="hidden" name="id" value="<?= htmlspecialchars($settings['id']); ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="agency_name"><?= __('agency_name') ?></label>
                                        <input type="text" class="form-control" id="agency_name" name="agency_name" 
                                               value="<?= htmlspecialchars($settings['agency_name']); ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="title"><?= __('agency_title') ?></label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?= htmlspecialchars($settings['title']); ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="phone"><?= __('phone') ?></label>
                                        <input type="text" class="form-control" id="phone" name="phone" 
                                               value="<?= htmlspecialchars($settings['phone']); ?>" required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email"><?=__('email')?></label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?= htmlspecialchars($settings['email']); ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="address"><?= __('address') ?></label>
                                        <textarea class="form-control" id="address" name="address" 
                                                  rows="3" required><?= htmlspecialchars($settings['address']); ?></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label for="logo"><?= __('logo') ?></label>
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="logo" name="logo" 
                                                   accept="image/*" onchange="previewImage(this);">
                                            <label class="custom-file-label" for="logo"><?= __('choose_file') ?></label>
                                        </div>
                                        <div class="logo-preview mt-3">
                                            <?php if ($settings['logo']): ?>
                                                <img src="../uploads/logo/<?= htmlspecialchars($settings['logo']); ?>" 
                                                     alt="Logo" id="logoPreview" class="img-thumbnail">
                                            <?php else: ?>
                                                <img src="../assets/images/default-logo.png" 
                                                     alt="Default Logo" id="logoPreview" class="img-thumbnail">
                                            <?php endif; ?>
                                        </div>
                                        <input type="hidden" name="existing_logo" 
                                               value="<?= htmlspecialchars($settings['logo']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="feather icon-save mr-2"></i><?= __('update_settings') ?>
                                    </button>
                                    <button type="reset" class="btn btn-light ml-2">
                                        <i class="feather icon-refresh-ccw mr-2"></i><?= __('reset') ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="feather icon-alert-circle mr-2"></i>
                            <?= __('no_settings_found_in_the_database') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
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
    

<style>
.settings-form {
    max-width: 1200px;
    margin: 0 auto;
}

.logo-preview {
    text-align: center;
    margin-top: 15px;
}

.logo-preview img {
    max-height: 150px;
    max-width: 300px;
    object-fit: contain;
    border: 1px solid #ddd;
    padding: 5px;
    border-radius: 4px;
}

.custom-file-label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.form-group label {
    font-weight: 500;
    color: #333;
}

.card {
    box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
    transition: all 0.3s cubic-bezier(.25,.8,.25,1);
}

.card:hover {
    box-shadow: 0 14px 28px rgba(0,0,0,0.25), 0 10px 10px rgba(0,0,0,0.22);
}

.alert {
    margin-bottom: 20px;
    border: none;
    border-radius: 4px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

.alert-warning {
    background-color: #fff3cd;
    color: #856404;
    border-left: 4px solid #ffc107;
}

.alert.fade {
    transition: opacity 0.3s linear;
}

.alert-dismissible .close {
    padding: 0.75rem 1.25rem;
    transition: opacity 0.15s linear;
}

.alert .feather {
    width: 20px;
    height: 20px;
    stroke-width: 2.5;
}
</style>

<script>
function editProfile() {
    // Redirect to profile edit page or show edit modal
    window.location.href = 'edit_profile.php';
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            document.getElementById('logoPreview').src = e.target.result;
        }
        
        reader.readAsDataURL(input.files[0]);
        
        // Update file input label
        var fileName = input.files[0].name;
        var label = input.nextElementSibling;
        label.innerHTML = fileName;
    }
}

// Add file input name to label
document.querySelector('.custom-file-input').addEventListener('change', function(e) {
    var fileName = e.target.files[0].name;
    var nextSibling = e.target.nextElementSibling;
    nextSibling.innerHTML = fileName;
});

// Auto-hide alert after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alert = document.querySelector('.alert');
    if (alert) {
        setTimeout(function() {
            $(alert).fadeOut('slow', function() {
                $(this).remove();
            });
        }, 5000);
    }
});
</script>

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>

                        
<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

    </body>
</html>