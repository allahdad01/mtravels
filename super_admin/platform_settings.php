<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to platform_settings.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once '../includes/conn.php';

// Fetch platform settings
$stmt = $conn->prepare("SELECT `key`, `value`, `type`, `description` FROM platform_settings");
$stmt->execute();
$settings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$settings_map = array_column($settings, 'value', 'key');
?>

<?php include '../includes/header_super_admin.php'; ?>

<style>
.settings-card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    overflow: hidden;
}

.settings-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

.settings-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    margin: -1.25rem -1.25rem 2rem -1.25rem;
    position: relative;
}

.settings-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>') repeat;
    opacity: 0.3;
}

.settings-header h4 {
    margin: 0;
    font-size: 1.75rem;
    font-weight: 600;
    position: relative;
    z-index: 1;
}

.settings-header p {
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
    position: relative;
    z-index: 1;
}

.settings-section {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.settings-section:hover {
    border-color: #667eea;
    box-shadow: 0 2px 10px rgba(102, 126, 234, 0.1);
}

.section-title {
    color: #2c3e50;
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #667eea;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.section-icon {
    width: 24px;
    height: 24px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
}

.form-group {
    margin-bottom: 1.75rem;
    position: relative;
}

.form-group label {
    font-weight: 600;
    color: #34495e;
    margin-bottom: 0.5rem;
    display: block;
    font-size: 0.95rem;
}

.form-control, .form-control-file {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    background: white;
    outline: none;
}

.form-control.is-invalid {
    border-color: #e74c3c;
    background: #fdf2f2;
}

.invalid-feedback {
    color: #e74c3c;
    font-size: 0.875rem;
    margin-top: 0.25rem;
    display: block;
}

.image-upload-area {
    border: 2px dashed #cbd5e0;
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
    background: #f7fafc;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.image-upload-area:hover {
    border-color: #667eea;
    background: #f0f4ff;
}

.image-upload-area.dragover {
    border-color: #667eea;
    background: #e6f3ff;
    transform: scale(1.02);
}

.upload-icon {
    font-size: 3rem;
    color: #a0aec0;
    margin-bottom: 1rem;
}

.current-image {
    border-radius: 12px;
    border: 3px solid #e9ecef;
    transition: all 0.3s ease;
    max-width: 200px;
    max-height: 120px;
    object-fit: cover;
}

.current-image:hover {
    border-color: #667eea;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    border-radius: 8px;
    padding: 0.875rem 2rem;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    background: linear-gradient(135deg, #5a67d8, #6b46c1);
}

.btn-secondary {
    background: #6c757d;
    border: none;
    border-radius: 8px;
    padding: 0.875rem 2rem;
    font-weight: 600;
    color: white;
    transition: all 0.3s ease;
}

.alert {
    border: none;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.alert-success {
    background: linear-gradient(135deg, #48bb78, #38a169);
    color: white;
}

.alert-danger {
    background: linear-gradient(135deg, #f56565, #e53e3e);
    color: white;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    text-align: center;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.stat-card:hover {
    border-color: #667eea;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.1);
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #667eea;
    margin-bottom: 0.25rem;
}

.stat-label {
    color: #6c757d;
    font-size: 0.875rem;
    font-weight: 500;
}

.preview-container {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}

.preview-item {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    text-align: center;
    min-width: 150px;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
    margin-left: 1rem;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 34px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background: linear-gradient(135deg, #667eea, #764ba2);
}

input:checked + .slider:before {
    transform: translateX(26px);
}

@media (max-width: 768px) {
    .settings-section {
        padding: 1.5rem 1rem;
    }
    
    .settings-header {
        padding: 1.5rem 1rem;
        margin: -1.25rem -1.25rem 1.5rem -1.25rem;
    }
    
    .section-title {
        font-size: 1.1rem;
    }
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 5px solid #f3f3f3;
    border-top: 5px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
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
                                    <h5 class="m-b-10"><?= __('platform_settings') ?></h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="#!"><?= __('settings') ?></a></li>
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
                            <div class="col-xl-12">
                                <div class="card settings-card">
                                    <div class="card-body">
                                        <div class="settings-header">
                                            <h4><i class="feather icon-settings mr-2"></i>Platform Configuration</h4>
                                            <p>Manage your platform's core settings, branding, and functionality</p>
                                        </div>

                                        <?php if (isset($_GET['error'])): ?>
                                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                <i class="feather icon-alert-circle mr-2"></i>
                                                <?= htmlspecialchars(urldecode($_GET['error'])) ?>
                                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($_GET['success'])): ?>
                                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                                <i class="feather icon-check-circle mr-2"></i>
                                                <?= htmlspecialchars(urldecode($_GET['success'])) ?>
                                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                        <?php endif; ?>

                                        <form id="settingsForm" enctype="multipart/form-data" method="POST" action="update_settings.php">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            
                                            <!-- Platform Identity Section -->
                                            <div class="settings-section">
                                                <div class="section-title">
                                                    <div class="section-icon">🏢</div>
                                                    Platform Identity
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="platform_name">Platform Name</label>
                                                            <input type="text" class="form-control" id="platform_name" name="platform_name" 
                                                                   value="<?= htmlspecialchars($settings_map['platform_name'] ?? '') ?>" 
                                                                   placeholder="Enter your platform name" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="support_email">Support Email</label>
                                                            <input type="email" class="form-control" id="support_email" name="support_email" 
                                                                   value="<?= htmlspecialchars($settings_map['support_email'] ?? '') ?>" 
                                                                   placeholder="support@yourplatform.com" required>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="contact_email">Contact Email</label>
                                                            <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                                                   value="<?= htmlspecialchars($settings_map['contact_email'] ?? '') ?>" 
                                                                   placeholder="contact@yourplatform.com">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="website_url">Website URL</label>
                                                            <input type="url" class="form-control" id="website_url" name="website_url" 
                                                                   value="<?= htmlspecialchars($settings_map['website_url'] ?? '') ?>" 
                                                                   placeholder="https://yourplatform.com">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Visual Branding Section -->
                                            <div class="settings-section">
                                                <div class="section-title">
                                                    <div class="section-icon">🎨</div>
                                                    Visual Branding
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="platform_logo">Platform Logo</label>
                                                            <div class="image-upload-area" onclick="document.getElementById('platform_logo').click()">
                                                                <input type="file" class="d-none" id="platform_logo" name="platform_logo" accept="image/*">
                                                                <div class="upload-icon">📷</div>
                                                                <h5>Click to upload logo</h5>
                                                                <p class="text-muted mb-0">PNG, JPG, GIF up to 2MB</p>
                                                            </div>
                                                            
                                                            <?php if (!empty($settings_map['platform_logo']) && $settings_map['platform_logo'] !== 'None'): ?>
                                                                <div class="preview-container">
                                                                    <div class="preview-item">
                                                                        <small class="text-muted d-block mb-2">Current Logo</small>
                                                                        <img src="../uploads/logo/<?= htmlspecialchars($settings_map['platform_logo']) ?>" 
                                                                             alt="Current Platform Logo" 
                                                                             class="current-image">
                                                                        <br>
                                                                        <small class="text-muted"><?= htmlspecialchars($settings_map['platform_logo']) ?></small>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="platform_favicon">Platform Favicon</label>
                                                            <div class="image-upload-area" onclick="document.getElementById('platform_favicon').click()">
                                                                <input type="file" class="d-none" id="platform_favicon" name="platform_favicon" accept=".ico,.png">
                                                                <div class="upload-icon">🔗</div>
                                                                <h5>Click to upload favicon</h5>
                                                                <p class="text-muted mb-0">ICO or PNG 16x16, 32x32</p>
                                                            </div>
                                                            
                                                            <?php if (!empty($settings_map['platform_favicon']) && $settings_map['platform_favicon'] !== 'None'): ?>
                                                                <div class="preview-container">
                                                                    <div class="preview-item">
                                                                        <small class="text-muted d-block mb-2">Current Favicon</small>
                                                                        <img src="../uploads/logo/<?= htmlspecialchars($settings_map['platform_favicon']) ?>" 
                                                                             alt="Current Platform Favicon" 
                                                                             style="width: 32px; height: 32px;">
                                                                        <br>
                                                                        <small class="text-muted"><?= htmlspecialchars($settings_map['platform_favicon']) ?></small>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Contact Information Section -->
                                            <div class="settings-section">
                                                <div class="section-title">
                                                    <div class="section-icon">📞</div>
                                                    Contact Information
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="contact_phone">Contact Phone</label>
                                                            <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                                                                   value="<?= htmlspecialchars($settings_map['contact_phone'] ?? '') ?>" 
                                                                   placeholder="+1234567890">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="support_phone">Support Phone</label>
                                                            <input type="tel" class="form-control" id="support_phone" name="support_phone" 
                                                                   value="<?= htmlspecialchars($settings_map['support_phone'] ?? '') ?>" 
                                                                   placeholder="+1234567890">
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="contact_address">Contact Address</label>
                                                    <textarea class="form-control" id="contact_address" name="contact_address" rows="2" 
                                                              placeholder="Enter your business address"><?= htmlspecialchars($settings_map['contact_address'] ?? '') ?></textarea>
                                                </div>
                                            </div>

                                            <!-- Social Media Section -->
                                            <div class="settings-section">
                                                <div class="section-title">
                                                    <div class="section-icon">🌐</div>
                                                    Social Media Links
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="contact_facebook">Facebook URL</label>
                                                            <input type="url" class="form-control" id="contact_facebook" name="contact_facebook" 
                                                                   value="<?= htmlspecialchars($settings_map['contact_facebook'] ?? '') ?>" 
                                                                   placeholder="https://facebook.com/yourpage">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="contact_twitter">Twitter URL</label>
                                                            <input type="url" class="form-control" id="contact_twitter" name="contact_twitter" 
                                                                   value="<?= htmlspecialchars($settings_map['contact_twitter'] ?? '') ?>" 
                                                                   placeholder="https://twitter.com/yourhandle">
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="contact_linkedin">LinkedIn URL</label>
                                                            <input type="url" class="form-control" id="contact_linkedin" name="contact_linkedin" 
                                                                   value="<?= htmlspecialchars($settings_map['contact_linkedin'] ?? '') ?>" 
                                                                   placeholder="https://linkedin.com/company/yourcompany">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="contact_instagram">Instagram URL</label>
                                                            <input type="url" class="form-control" id="contact_instagram" name="contact_instagram" 
                                                                   value="<?= htmlspecialchars($settings_map['contact_instagram'] ?? '') ?>" 
                                                                   placeholder="https://instagram.com/yourhandle">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Platform Configuration Section -->
                                            <div class="settings-section">
                                                <div class="section-title">
                                                    <div class="section-icon">⚙️</div>
                                                    Platform Configuration
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label for="default_currency">Default Currency</label>
                                                            <input type="text" class="form-control" id="default_currency" name="default_currency" 
                                                                   value="<?= htmlspecialchars($settings_map['default_currency'] ?? '') ?>" 
                                                                   placeholder="USD" maxlength="3" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label for="max_users_per_tenant">Max Users Per Tenant</label>
                                                            <input type="number" class="form-control" id="max_users_per_tenant" name="max_users_per_tenant" 
                                                                   value="<?= htmlspecialchars($settings_map['max_users_per_tenant'] ?? '') ?>" 
                                                                   placeholder="100" min="1" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-group">
                                                            <label for="api_enabled">API Status</label>
                                                            <div class="d-flex align-items-center">
                                                                <span class="mr-2">Disabled</span>
                                                                <label class="toggle-switch">
                                                                    <input type="hidden" name="api_enabled" value="false">
                                                                    <input type="checkbox" id="api_enabled" name="api_enabled" value="true" 
                                                                           <?= ($settings_map['api_enabled'] ?? '') === 'true' ? 'checked' : '' ?>>
                                                                    <span class="slider"></span>
                                                                </label>
                                                                <span class="ml-2">Enabled</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="text-center">
                                                <button type="button" class="btn btn-secondary mr-3" onclick="resetForm()">
                                                    <i class="feather icon-refresh-cw mr-2"></i>Reset Changes
                                                </button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather icon-save mr-2"></i>Save All Settings
                                                </button>
                                            </div>
                                        </form>
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
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('settingsForm');
    const loadingOverlay = document.getElementById('loadingOverlay');
    
    // Auto-hide alerts
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s ease-out';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);

    // File upload handling
    setupFileUpload('platform_logo');
    setupFileUpload('platform_favicon');

    // Form validation
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (validateForm()) {
            loadingOverlay.style.display = 'flex';
            
            // Create FormData for file uploads
            const formData = new FormData(form);
            
            // Submit form via fetch for better UX
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                return response.json();
            })
            .then(data => {
                loadingOverlay.style.display = 'none';

                if (data.success) {
                    // Handle success
                    showNotification(data.message, 'success');

                    // Clear form changed flag
                    formChanged = false;

                    // Clear draft data since settings were successfully saved
                    localStorage.removeItem('platform_settings_draft');

                    // Reload page after short delay or redirect if specified
                    setTimeout(() => {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        } else {
                            window.location.reload();
                        }
                    }, 1500);
                } else {
                    // Handle error
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error saving settings. Please try again.', 'error');
                loadingOverlay.style.display = 'none';
            });
        }
    });

    // Form validation function
    function validateForm() {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        // Clear previous validation
        form.querySelectorAll('.is-invalid').forEach(field => field.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(feedback => feedback.remove());
        
        requiredFields.forEach(function(field) {
            if (!field.value.trim()) {
                showFieldError(field, 'This field is required.');
                isValid = false;
            }
        });

        // Email validation
        const emailField = form.querySelector('#support_email');
        if (emailField && emailField.value && !isValidEmail(emailField.value)) {
            showFieldError(emailField, 'Please enter a valid email address.');
            isValid = false;
        }

        // Currency validation
        const currencyField = form.querySelector('#default_currency');
        if (currencyField && currencyField.value && !/^[A-Z]{3}$/.test(currencyField.value)) {
            showFieldError(currencyField, 'Please enter a valid 3-letter currency code (e.g., USD, EUR).');
            isValid = false;
        }

        // Users validation
        const usersField = form.querySelector('#max_users_per_tenant');
        if (usersField && usersField.value && (!isValidNumber(usersField.value) || parseInt(usersField.value) < 1)) {
            showFieldError(usersField, 'Please enter a valid number greater than 0.');
            isValid = false;
        }

        if (!isValid) {
            const firstError = form.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
        }

        return isValid;
    }

    // File upload setup
    function setupFileUpload(inputId) {
        const input = document.getElementById(inputId);
        const uploadArea = input.closest('.image-upload-area');
        
        if (!input || !uploadArea) return;

        // Drag and drop
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', function() {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                input.files = files;
                handleFileSelect(input);
            }
        });

        // File selection
        input.addEventListener('change', function() {
            handleFileSelect(this);
        });
    }

    // File selection handler
    function handleFileSelect(input) {
        const file = input.files[0];
        if (!file) return;

        const inputId = input.id;
        let allowedTypes, maxSize, maxDimensions;

        if (inputId === 'platform_logo') {
            allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            maxSize = 2 * 1024 * 1024; // 2MB
            maxDimensions = { width: 1000, height: 500 };
        } else if (inputId === 'platform_favicon') {
            allowedTypes = ['image/x-icon', 'image/png'];
            maxSize = 100 * 1024; // 100KB
            maxDimensions = { width: 64, height: 64 };
        }

        // Validate file type
        if (!allowedTypes.includes(file.type)) {
            showNotification(`Please select a valid file type for ${inputId.replace('platform_', '')}.`, 'error');
            input.value = '';
            return;
        }

        // Validate file size
        if (file.size > maxSize) {
            showNotification(`File size must be less than ${Math.round(maxSize / 1024 / 1024)}MB.`, 'error');
            input.value = '';
            return;
        }

        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            // Remove existing preview
            const existingPreview = input.closest('.form-group').querySelector('.new-preview');
            if (existingPreview) {
                existingPreview.remove();
            }

            // Create new preview
            const preview = document.createElement('div');
            preview.className = 'preview-container new-preview';
            preview.innerHTML = `
                <div class="preview-item">
                    <small class="text-success d-block mb-2">New ${inputId.replace('platform_', '')} Preview</small>
                    <img src="${e.target.result}" alt="${inputId} Preview" 
                         class="current-image" 
                         style="${inputId === 'platform_favicon' ? 'width: 32px; height: 32px;' : ''}">
                    <br>
                    <small class="text-muted">${file.name}</small>
                    <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="removePreview(this, '${inputId}')">
                        <i class="feather icon-x"></i> Remove
                    </button>
                </div>
            `;

            input.closest('.form-group').appendChild(preview);
        };
        reader.readAsDataURL(file);
    }

    // Helper functions
    function showFieldError(field, message) {
        field.classList.add('is-invalid');
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.textContent = message;
        field.parentNode.appendChild(feedback);
    }

    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function isValidNumber(value) {
        return !isNaN(value) && !isNaN(parseFloat(value));
    }

    function showNotification(message, type) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const iconClass = type === 'success' ? 'icon-check-circle' : 'icon-alert-circle';
        
        const alert = document.createElement('div');
        alert.className = `alert ${alertClass} alert-dismissible fade show`;
        alert.style.position = 'fixed';
        alert.style.top = '20px';
        alert.style.right = '20px';
        alert.style.zIndex = '10000';
        alert.style.minWidth = '300px';
        alert.innerHTML = `
            <i class="feather ${iconClass} mr-2"></i>
            ${message}
            <button type="button" class="close" onclick="this.parentElement.remove()">
                <span>&times;</span>
            </button>
        `;
        
        document.body.appendChild(alert);
        
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }

    // Remove validation on input
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(function(input) {
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid');
            const feedback = this.parentNode.querySelector('.invalid-feedback');
            if (feedback) feedback.remove();
        });
    });

    // Currency field auto-uppercase
    const currencyField = document.getElementById('default_currency');
    if (currencyField) {
        currencyField.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }

    // Toggle switch functionality
    const apiToggle = document.getElementById('api_enabled');
    if (apiToggle) {
        apiToggle.addEventListener('change', function() {
            const hiddenInput = form.querySelector('input[name="api_enabled"][type="hidden"]');
            if (hiddenInput) {
                hiddenInput.value = this.checked ? 'true' : 'false';
            }
        });
    }
});

// Global functions
function resetForm() {
    if (confirm('Are you sure you want to reset all changes? This will revert to the last saved values.')) {
        location.reload();
    }
}

function removePreview(button, inputId) {
    const input = document.getElementById(inputId);
    const preview = button.closest('.new-preview');
    
    if (input) input.value = '';
    if (preview) preview.remove();
}

// Real-time form changes detection
let formChanged = false;
document.addEventListener('change', function() {
    formChanged = true;
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// Auto-save draft functionality (optional)
function saveDraft() {
    const formData = new FormData(document.getElementById('settingsForm'));
    const draftData = {};

    for (let [key, value] of formData.entries()) {
        if (key !== 'platform_logo' && key !== 'platform_favicon' && key !== 'csrf_token') { // Don't save files or CSRF token in draft
            draftData[key] = value;
        }
    }

    localStorage.setItem('platform_settings_draft', JSON.stringify(draftData));
}

function loadDraft() {
    const draft = localStorage.getItem('platform_settings_draft');
    if (draft) {
        const draftData = JSON.parse(draft);
        Object.keys(draftData).forEach(key => {
            const input = document.querySelector(`[name="${key}"]`);
            if (input && input.type !== 'file') {
                input.value = draftData[key];
            }
        });
    }
}

// Auto-save every 30 seconds
setInterval(saveDraft, 30000);

// Load draft on page load
document.addEventListener('DOMContentLoaded', function() {
    const draft = localStorage.getItem('platform_settings_draft');
    if (draft) {
        const draftData = JSON.parse(draft);
        let hasChanges = false;

        // Check if any form field values differ from current values
        Object.keys(draftData).forEach(key => {
            const input = document.querySelector(`[name="${key}"]`);
            if (input && input.type !== 'file' && input.value !== draftData[key]) {
                hasChanges = true;
            }
        });

        if (hasChanges) {
            if (confirm('You have unsaved changes. Would you like to restore them?')) {
                loadDraft();
            } else {
                localStorage.removeItem('platform_settings_draft');
            }
        } else {
            // No actual changes, just clear the draft
            localStorage.removeItem('platform_settings_draft');
        }
    }
});
</script>
</body>
</html>