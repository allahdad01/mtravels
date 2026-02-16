<?php
include 'header.php';
$tenant_id = $_SESSION['tenant_id'];


if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'tenant_super_admin') {
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

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        <div class="main-content">
                            <div class="page-header card">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5 class="mb-0"><i class="feather icon-settings mr-2"></i><?php echo __('agency_settings'); ?></h5>
                                        <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;"><?php echo __('manage_your_agency_settings'); ?></p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                            <i class="feather icon-arrow-left mr-1"></i><?php echo __('back_to_dashboard'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>

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
                                    <?php if ($settings): ?>
                                    <div class="card">
                                        <div class="card-header">
                                            <h5><i class="feather icon-home mr-2"></i><?php echo __('agency_information'); ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <form action="updateSettings.php" method="POST" enctype="multipart/form-data" class="settings-form">
                                                
                                                <input type="hidden" name="id" value="<?= htmlspecialchars($settings['id']); ?>">
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="agency_name"><i class="feather icon-hash mr-2"></i><?php echo __('agency_name') ?></label>
                                                            <input type="text" class="form-control" id="agency_name" name="agency_name" 
                                                                   value="<?= htmlspecialchars($settings['agency_name']); ?>" required>
                                                        </div>

                                                        <div class="form-group">
                                                            <label for="title"><i class="feather icon-edit-2 mr-2"></i><?php echo __('agency_title') ?></label>
                                                            <input type="text" class="form-control" id="title" name="title" 
                                                                   value="<?= htmlspecialchars($settings['title']); ?>" required>
                                                        </div>

                                                        <div class="form-group">
                                                            <label for="phone"><i class="feather icon-phone mr-2"></i><?php echo __('phone') ?></label>
                                                            <input type="text" class="form-control" id="phone" name="phone" 
                                                                   value="<?= htmlspecialchars($settings['phone']); ?>" required>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="email"><i class="feather icon-mail mr-2"></i><?php echo __('email') ?></label>
                                                            <input type="email" class="form-control" id="email" name="email" 
                                                                   value="<?= htmlspecialchars($settings['email']); ?>" required>
                                                        </div>

                                                        <div class="form-group">
                                                            <label for="address"><i class="feather icon-map-pin mr-2"></i><?php echo __('address') ?></label>
                                                            <textarea class="form-control" id="address" name="address" 
                                                                      rows="3" required><?= htmlspecialchars($settings['address']); ?></textarea>
                                                        </div>

                                                        <div class="form-group">
                                                            <label for="logo"><i class="feather icon-image mr-2"></i><?php echo __('logo') ?></label>
                                                            <div class="custom-file">
                                                                <input type="file" class="custom-file-input" id="logo" name="logo"
                                                                       accept="image/*" onchange="previewImage(this);">
                                                                <label class="custom-file-label" for="logo"><?php echo __('choose_file') ?></label>
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
                                            </form>
                                        </div>
                                    </div>

                                    <!-- SMTP Configuration Section -->
                                    <div class="card mt-3">
                                        <div class="card-header">
                                            <h5><i class="feather icon-mail mr-2"></i><?php echo __('smtp_configuration'); ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <form action="updateSettings.php" method="POST" enctype="multipart/form-data" class="settings-form">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars($settings['id']); ?>">
                                                <input type="hidden" name="existing_logo" value="<?= htmlspecialchars($settings['logo']); ?>">
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="smtp_host"><i class="feather icon-server mr-2"></i><?php echo __('smtp_host'); ?></label>
                                                            <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                                                                   value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>"
                                                                   placeholder="smtp.gmail.com">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="form-group">
                                                            <label for="smtp_port"><i class="feather icon-hash mr-2"></i><?php echo __('smtp_port'); ?></label>
                                                            <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                                                                   value="<?= htmlspecialchars($settings['smtp_port'] ?? '') ?>"
                                                                   placeholder="587" min="1" max="65535">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="form-group">
                                                            <label for="smtp_encryption"><i class="feather icon-shield mr-2"></i><?php echo __('encryption'); ?></label>
                                                            <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                                                                <option value=""><?php echo __('none'); ?></option>
                                                                <option value="tls" <?= ($settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>><?php echo __('tls'); ?></option>
                                                                <option value="ssl" <?= ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>><?php echo __('ssl'); ?></option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="smtp_username"><i class="feather icon-user mr-2"></i><?php echo __('smtp_username'); ?></label>
                                                            <input type="text" class="form-control" id="smtp_username" name="smtp_username"
                                                                   value="<?= htmlspecialchars($settings['smtp_username'] ?? '') ?>"
                                                                   placeholder="your-email@gmail.com">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="smtp_password"><i class="feather icon-lock mr-2"></i><?php echo __('smtp_password'); ?></label>
                                                            <input type="password" class="form-control" id="smtp_password" name="smtp_password"
                                                                   value="<?= htmlspecialchars($settings['smtp_password'] ?? '') ?>"
                                                                   placeholder="<?php echo __('your_smtp_password'); ?>">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="smtp_from_email"><i class="feather icon-send mr-2"></i><?php echo __('from_email'); ?></label>
                                                            <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email"
                                                                   value="<?= htmlspecialchars($settings['smtp_from_email'] ?? '') ?>"
                                                                   placeholder="noreply@yourdomain.com">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="smtp_from_name"><i class="feather icon-tag mr-2"></i><?php echo __('from_name'); ?></label>
                                                            <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name"
                                                                   value="<?= htmlspecialchars($settings['smtp_from_name'] ?? '') ?>"
                                                                   placeholder="<?php echo __('your_agency_name'); ?>">
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Test Email Section -->
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="test_email"><i class="feather icon-mail mr-2"></i><?php echo __('test_email_address'); ?></label>
                                                            <input type="email" class="form-control" id="test_email" name="test_email"
                                                                   placeholder="test@example.com">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6 d-flex align-items-end">
                                                        <button type="button" class="btn btn-info" id="testEmailBtn">
                                                            <i class="feather icon-send mr-2"></i><?php echo __('send_test_email'); ?>
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="row mt-4">
                                                    <div class="col-12">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="feather icon-save mr-2"></i><?php echo __('update_settings'); ?>
                                                        </button>
                                                        <button type="reset" class="btn btn-secondary ml-2">
                                                            <i class="feather icon-refresh-ccw mr-2"></i><?php echo __('reset'); ?>
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-danger" role="alert">
                                        <i class="feather icon-alert-circle mr-2"></i>
                                        <?php echo __('no_settings_found_in_the_database'); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <!-- [ Main Content ] end -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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

.card-body {
    padding: 1.5rem;
}

.settings-form {
    max-width: 100%;
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
    border-radius: 8px;
}

.custom-file-label {
    
    text-overflow: ellipsis;
    white-space: nowrap;
    border-radius: 8px;
}

.form-group label {
    font-weight: 500;
    color: #333;
    margin-bottom: 0.5rem;
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
    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    color: #ffffff;
}

.btn-secondary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
}

.btn-info {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-info:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);
}

.alert {
    border-radius: 10px;
    border: none;
    padding: 1rem 1.5rem;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
}

.alert-warning {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
    color: #856404;
}

.alert-info {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    color: #0c5460;
}

.alert-dismissible .close {
    padding: 0.75rem 1.25rem;
}

.alert .feather {
    width: 20px;
    height: 20px;
    stroke-width: 2.5;
}

.custom-file {
    margin-bottom: 0.5rem;
}

.custom-file-input {
    border-radius: 8px;
}

.img-thumbnail {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.mt-3 {
    margin-top: 1rem !important;
}

.mt-4 {
    margin-top: 1.5rem !important;
}

.mb-0 {
    margin-bottom: 0 !important;
}

.ml-2 {
    margin-left: 0.5rem !important;
}

.d-flex {
    display: flex !important;
}

.align-items-end {
    align-items: flex-end !important;
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

// Test Email functionality
document.addEventListener('DOMContentLoaded', function() {
    const testEmailBtn = document.getElementById('testEmailBtn');
    if (testEmailBtn) {
        testEmailBtn.addEventListener('click', function() {
            const testEmail = document.getElementById('test_email').value;
            const smtpHost = document.getElementById('smtp_host').value;
            const smtpUsername = document.getElementById('smtp_username').value;
            const smtpPassword = document.getElementById('smtp_password').value;

            if (!testEmail) {
                alert('Please enter a test email address.');
                return;
            }

            if (!smtpHost || !smtpUsername || !smtpPassword) {
                alert('Please configure SMTP settings first.');
                return;
            }

            // Show loading
            testEmailBtn.disabled = true;
            testEmailBtn.innerHTML = '<i class="feather icon-loader mr-2"></i>Sending...';

            // Send test email
            fetch('send_test_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    test_email: testEmail,
                    smtp_host: smtpHost,
                    smtp_port: document.getElementById('smtp_port').value,
                    smtp_encryption: document.getElementById('smtp_encryption').value,
                    smtp_username: smtpUsername,
                    smtp_password: smtpPassword,
                    smtp_from_email: document.getElementById('smtp_from_email').value,
                    smtp_from_name: document.getElementById('smtp_from_name').value
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Show success message
                    showNotification('Test email sent successfully to ' + testEmail, 'success');
                } else {
                    // Show error message
                    showNotification('Failed to send test email: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error sending test email. Please check your configuration and try again.', 'error');
            })
            .finally(() => {
                // Reset button
                testEmailBtn.disabled = false;
                testEmailBtn.innerHTML = '<i class="feather icon-send mr-2"></i>Send Test Email';
            });
        });
    }

    // Notification function
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.notification-toast');
        existingNotifications.forEach(notification => notification.remove());

        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show notification-toast`;
        notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);';
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="feather icon-${type === 'success' ? 'check-circle' : type === 'error' ? 'alert-circle' : 'info'} mr-2"></i>
                <strong>${message}</strong>
            </div>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        `;

        // Add to page
        document.body.appendChild(notification);

        // Auto-hide after 5 seconds
        setTimeout(function() {
            $(notification).fadeOut('slow', function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Auto-hide alert after 5 seconds
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

