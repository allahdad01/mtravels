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



<?php  ?>
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

                            <!-- SMTP Configuration Section -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h5 class="mb-3"><i class="feather icon-mail mr-2"></i>SMTP Configuration</h5>
                                    <div class="card border-primary">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="smtp_host">SMTP Host</label>
                                                        <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                                                               value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>"
                                                               placeholder="smtp.gmail.com">
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label for="smtp_port">SMTP Port</label>
                                                        <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                                                               value="<?= htmlspecialchars($settings['smtp_port'] ?? '') ?>"
                                                               placeholder="587" min="1" max="65535">
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label for="smtp_encryption">Encryption</label>
                                                        <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                                                            <option value="">None</option>
                                                            <option value="tls" <?= ($settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                                            <option value="ssl" <?= ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="smtp_username">SMTP Username</label>
                                                        <input type="text" class="form-control" id="smtp_username" name="smtp_username"
                                                               value="<?= htmlspecialchars($settings['smtp_username'] ?? '') ?>"
                                                               placeholder="your-email@gmail.com">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="smtp_password">SMTP Password</label>
                                                        <input type="password" class="form-control" id="smtp_password" name="smtp_password"
                                                               value="<?= htmlspecialchars($settings['smtp_password'] ?? '') ?>"
                                                               placeholder="Your SMTP password">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="smtp_from_email">From Email</label>
                                                        <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email"
                                                               value="<?= htmlspecialchars($settings['smtp_from_email'] ?? '') ?>"
                                                               placeholder="noreply@yourdomain.com">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="smtp_from_name">From Name</label>
                                                        <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name"
                                                               value="<?= htmlspecialchars($settings['smtp_from_name'] ?? '') ?>"
                                                               placeholder="Your Agency Name">
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Test Email Section -->
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="test_email">Test Email Address</label>
                                                        <input type="email" class="form-control" id="test_email" name="test_email"
                                                               placeholder="test@example.com">
                                                    </div>
                                                </div>
                                                <div class="col-md-6 d-flex align-items-end">
                                                    <button type="button" class="btn btn-info" id="testEmailBtn">
                                                        <i class="feather icon-send mr-2"></i>Send Test Email
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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

    </body>
</html>