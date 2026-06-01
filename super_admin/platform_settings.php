<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
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
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once '../includes/db.php';

// Fetch platform settings
$stmt = $pdo->prepare("SELECT `key`, `value`, `type`, `description` FROM platform_settings");
$stmt->execute();
$settings = $stmt->fetchAll();
$settings_map = array_column($settings, 'value', 'key');
?>

<?php include '../includes/header_super_admin.php'; ?>

<style>
.sa-wrap {
    max-width: 900px;
    margin: 0 auto;
}

.sa-content {
    padding: 8px 4px;
}

.sa-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    margin-bottom: 24px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}

.sa-card:last-child { margin-bottom: 0; }

.sa-card-hdr {
    padding: 18px 24px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 10px;
}

.sa-card-hdr h3 {
    font-size: .95rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #1f2937;
}

.sa-card-body {
    padding: 24px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 18px;
}

.form-group {
    position: relative;
}

.form-label {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
    font-size: .82rem;
}

.form-control {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: .88rem;
    transition: all .15s ease;
    background: #f9fafb;
    color: #1f2937;
    font-family: inherit;
}

.form-control:focus {
    outline: none;
    border-color: #4099ff;
    box-shadow: 0 0 0 3px rgba(64,153,255,.12);
    background: #fff;
}

.form-control.is-invalid {
    border-color: #ef4444;
    background-color: #fef2f2;
}

.form-control.is-valid {
    border-color: #10b981;
    background-color: #f0fdf4;
}

.invalid-feedback {
    color: #ef4444;
    font-size: .72rem;
    margin-top: 4px;
}

.image-upload-area {
    border: 2px dashed #d1d5db;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    background: #f9fafb;
    transition: all .2s;
    cursor: pointer;
}

.image-upload-area:hover {
    border-color: #4099ff;
    background: rgba(64,153,255,.04);
}

.image-upload-area.dragover {
    border-color: #4099ff;
    background: rgba(64,153,255,.08);
}

.upload-icon {
    font-size: 2rem;
    color: #9ca3af;
    margin-bottom: 6px;
}

.upload-text h5 {
    margin: 0 0 2px 0;
    font-weight: 600;
    font-size: .88rem;
    color: #1f2937;
}

.upload-text p {
    margin: 0;
    font-size: .75rem;
    color: #6b7280;
}

.preview-container {
    display: flex;
    gap: 12px;
    margin-top: 12px;
    flex-wrap: wrap;
}

.preview-item {
    background: #fff;
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    text-align: center;
    min-width: 130px;
}

.current-image {
    border-radius: 6px;
    border: 1px solid #e5e7eb;
    max-width: 160px;
    max-height: 90px;
    object-fit: contain;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background: #cbd5e1;
    transition: .3s;
    border-radius: 24px;
}

.slider:before {
    content: "";
    position: absolute;
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background: #fff;
    transition: .3s;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,.12);
}

input:checked + .slider {
    background: linear-gradient(135deg, #4099ff, #2ed8b6);
}

input:checked + .slider:before {
    transform: translateX(20px);
}

.alert {
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 16px;
    border: 1px solid transparent;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: .85rem;
}

.alert-success {
    background: rgba(16,185,129,.08);
    border-color: rgba(16,185,129,.18);
    color: #065f46;
}

.alert-danger {
    background: rgba(239,68,68,.08);
    border-color: rgba(239,68,68,.18);
    color: #991b1b;
}

.alert-icon {
    flex-shrink: 0;
    font-size: 1rem;
}

.action-buttons {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 28px;
    flex-wrap: wrap;
}

.sa-btn {
    font-size: .85rem;
    font-weight: 600;
    padding: 10px 22px;
    border-radius: 8px;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    transition: all .15s;
    font-family: inherit;
}

.sa-btn-ghost {
    background: #f3f4f6;
    color: #6b7280;
    border: 1px solid #d1d5db;
}

.sa-btn-ghost:hover {
    background: #e5e7eb;
    color: #374151;
}

.sa-btn-primary {
    background: linear-gradient(135deg, #4099ff, #2ed8b6);
    color: #fff;
}

.sa-btn-primary:hover {
    opacity: .88;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(64,153,255,.25);
}

.loading-overlay {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    backdrop-filter: blur(3px);
}

.loading-spinner {
    width: 2.8rem;
    height: 2.8rem;
    border: 3px solid rgba(255,255,255,.3);
    border-top: 3px solid #fff;
    border-radius: 50%;
    animation: spin .8s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .sa-card-body { padding: 16px; }
    .form-grid { grid-template-columns: 1fr; }
    .action-buttons { flex-direction: column; align-items: stretch; }
    .sa-btn { justify-content: center; }
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

                <div class="sa-wrap">
                    <div class="sa-content">

                        <!-- Alerts -->
                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger" role="alert">
                                <div class="alert-icon"><i class="feather icon-alert-circle"></i></div>
                                <div><strong>Error:</strong> <?= htmlspecialchars(urldecode($_GET['error'])) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success" role="alert">
                                <div class="alert-icon"><i class="feather icon-check-circle"></i></div>
                                <div><strong>Success:</strong> <?= htmlspecialchars(urldecode($_GET['success'])) ?></div>
                            </div>
                        <?php endif; ?>

                        <form id="settingsForm" enctype="multipart/form-data" method="POST" action="update_settings.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                            <!-- Platform Identity Card (name + branding) -->
                            <div class="sa-card">
                                <div class="sa-card-hdr">
                                    <h3><i class="feather icon-building" style="margin-right:8px"></i>Platform Identity</h3>
                                </div>
                                <div class="sa-card-body">
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label" for="platform_name">Platform Name</label>
                                            <input type="text" class="form-control" id="platform_name" name="platform_name"
                                                   value="<?= htmlspecialchars($settings_map['platform_name'] ?? '') ?>"
                                                   placeholder="Enter your platform name" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="website_url">Website URL</label>
                                            <input type="url" class="form-control" id="website_url" name="website_url"
                                                   value="<?= htmlspecialchars($settings_map['website_url'] ?? '') ?>"
                                                   placeholder="https://yourplatform.com">
                                        </div>
                                    </div>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:16px">
                                        <div class="form-group">
                                            <label class="form-label" for="platform_logo">Platform Logo</label>
                                            <div class="image-upload-area" onclick="document.getElementById('platform_logo').click()" role="button" tabindex="0" aria-label="Upload platform logo">
                                                <input type="file" class="d-none" id="platform_logo" name="platform_logo" accept="image/*" aria-describedby="logo-help">
                                                <div class="upload-icon"><i class="feather icon-camera"></i></div>
                                                <div class="upload-text">
                                                    <h5>Click to upload logo</h5>
                                                    <p id="logo-help">PNG, JPG, GIF up to 2MB</p>
                                                </div>
                                            </div>
                                            <?php if (!empty($settings_map['platform_logo']) && $settings_map['platform_logo'] !== 'None'): ?>
                                                <div class="preview-container">
                                                    <div class="preview-item">
                                                        <small class="text-muted d-block mb-2">Current Logo</small>
                                                        <img src="../uploads/logo/<?= htmlspecialchars($settings_map['platform_logo']) ?>"
                                                             alt="Current Platform Logo" class="current-image">
                                                        <br><small class="text-muted"><?= htmlspecialchars($settings_map['platform_logo']) ?></small>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="platform_favicon">Platform Favicon</label>
                                            <div class="image-upload-area" onclick="document.getElementById('platform_favicon').click()" role="button" tabindex="0" aria-label="Upload platform favicon">
                                                <input type="file" class="d-none" id="platform_favicon" name="platform_favicon" accept=".ico,.png" aria-describedby="favicon-help">
                                                <div class="upload-icon"><i class="feather icon-link"></i></div>
                                                <div class="upload-text">
                                                    <h5>Click to upload favicon</h5>
                                                    <p id="favicon-help">ICO or PNG 16x16, 32x32</p>
                                                </div>
                                            </div>
                                            <?php if (!empty($settings_map['platform_favicon']) && $settings_map['platform_favicon'] !== 'None'): ?>
                                                <div class="preview-container">
                                                    <div class="preview-item">
                                                        <small class="text-muted d-block mb-2">Current Favicon</small>
                                                        <img src="../uploads/logo/<?= htmlspecialchars($settings_map['platform_favicon']) ?>"
                                                             alt="Current Platform Favicon" style="width: 32px; height: 32px;">
                                                        <br><small class="text-muted"><?= htmlspecialchars($settings_map['platform_favicon']) ?></small>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Company Information Card (contact + social) -->
                            <div class="sa-card">
                                <div class="sa-card-hdr">
                                    <h3><i class="feather icon-phone" style="margin-right:8px"></i>Company Information</h3>
                                </div>
                                <div class="sa-card-body">
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label" for="support_email">Support Email</label>
                                            <input type="email" class="form-control" id="support_email" name="support_email"
                                                   value="<?= htmlspecialchars($settings_map['support_email'] ?? '') ?>"
                                                   placeholder="support@yourplatform.com" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="contact_email">Contact Email</label>
                                            <input type="email" class="form-control" id="contact_email" name="contact_email"
                                                   value="<?= htmlspecialchars($settings_map['contact_email'] ?? '') ?>"
                                                   placeholder="contact@yourplatform.com">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="support_phone">Support Phone</label>
                                            <input type="tel" class="form-control" id="support_phone" name="support_phone"
                                                   value="<?= htmlspecialchars($settings_map['support_phone'] ?? '') ?>"
                                                   placeholder="+1234567890">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="contact_phone">Contact Phone</label>
                                            <input type="tel" class="form-control" id="contact_phone" name="contact_phone"
                                                   value="<?= htmlspecialchars($settings_map['contact_phone'] ?? '') ?>"
                                                   placeholder="+1234567890">
                                        </div>
                                    </div>
                                    <div class="form-group" style="margin-top:12px">
                                        <label class="form-label" for="contact_address">Contact Address</label>
                                        <textarea class="form-control" id="contact_address" name="contact_address" rows="2"
                                                  placeholder="Enter your business address"><?= htmlspecialchars($settings_map['contact_address'] ?? '') ?></textarea>
                                    </div>
                                    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
                                        <div class="form-grid">
                                            <div class="form-group">
                                                <label class="form-label" for="contact_facebook">Facebook URL</label>
                                                <input type="url" class="form-control" id="contact_facebook" name="contact_facebook"
                                                       value="<?= htmlspecialchars($settings_map['contact_facebook'] ?? '') ?>"
                                                       placeholder="https://facebook.com/yourpage">
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label" for="contact_twitter">Twitter URL</label>
                                                <input type="url" class="form-control" id="contact_twitter" name="contact_twitter"
                                                       value="<?= htmlspecialchars($settings_map['contact_twitter'] ?? '') ?>"
                                                       placeholder="https://twitter.com/yourhandle">
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label" for="contact_linkedin">LinkedIn URL</label>
                                                <input type="url" class="form-control" id="contact_linkedin" name="contact_linkedin"
                                                       value="<?= htmlspecialchars($settings_map['contact_linkedin'] ?? '') ?>"
                                                       placeholder="https://linkedin.com/company/yourcompany">
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label" for="contact_instagram">Instagram URL</label>
                                                <input type="url" class="form-control" id="contact_instagram" name="contact_instagram"
                                                       value="<?= htmlspecialchars($settings_map['contact_instagram'] ?? '') ?>"
                                                       placeholder="https://instagram.com/yourhandle">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Platform Configuration Card -->
                            <div class="sa-card">
                                <div class="sa-card-hdr">
                                    <h3><i class="feather icon-settings" style="margin-right:8px"></i>Platform Configuration</h3>
                                </div>
                                <div class="sa-card-body">
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label" for="default_currency">Default Currency</label>
                                            <input type="text" class="form-control" id="default_currency" name="default_currency"
                                                   value="<?= htmlspecialchars($settings_map['default_currency'] ?? '') ?>"
                                                   placeholder="USD" maxlength="3" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="max_users_per_tenant">Max Users Per Tenant</label>
                                            <input type="number" class="form-control" id="max_users_per_tenant" name="max_users_per_tenant"
                                                   value="<?= htmlspecialchars($settings_map['max_users_per_tenant'] ?? '') ?>"
                                                   placeholder="100" min="1" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="api_enabled">API Status</label>
                                            <div style="display:flex;align-items:center;gap:10px;margin-top:8px">
                                                <span style="font-size:0.85rem;color:var(--muted)">Disabled</span>
                                                <label class="toggle-switch">
                                                    <input type="hidden" name="api_enabled" value="false">
                                                    <input type="checkbox" id="api_enabled" name="api_enabled" value="true"
                                                           <?= ($settings_map['api_enabled'] ?? '') === 'true' ? 'checked' : '' ?>>
                                                    <span class="slider"></span>
                                                </label>
                                                <span style="font-size:0.85rem;color:var(--muted)">Enabled</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SMTP Configuration Card -->
                            <div class="sa-card">
                                <div class="sa-card-hdr">
                                    <h3><i class="feather icon-mail" style="margin-right:8px"></i>SMTP Configuration</h3>
                                </div>
                                <div class="sa-card-body">
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label" for="smtp_host">SMTP Host</label>
                                            <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                                                   value="<?= htmlspecialchars($settings_map['smtp_host'] ?? '') ?>"
                                                   placeholder="smtp.gmail.com">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="smtp_port">SMTP Port</label>
                                            <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                                                   value="<?= htmlspecialchars($settings_map['smtp_port'] ?? '') ?>"
                                                   placeholder="587" min="1" max="65535">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="smtp_encryption">Encryption</label>
                                            <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                                                <option value="">None</option>
                                                <option value="tls" <?= ($settings_map['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                                <option value="ssl" <?= ($settings_map['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="smtp_username">SMTP Username</label>
                                            <input type="text" class="form-control" id="smtp_username" name="smtp_username"
                                                   value="<?= htmlspecialchars($settings_map['smtp_username'] ?? '') ?>"
                                                   placeholder="your-email@gmail.com">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="smtp_password">SMTP Password</label>
                                            <input type="password" class="form-control" id="smtp_password" name="smtp_password"
                                                   value="<?= htmlspecialchars($settings_map['smtp_password'] ?? '') ?>"
                                                   placeholder="Your SMTP password">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="smtp_from_email">From Email</label>
                                            <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email"
                                                   value="<?= htmlspecialchars($settings_map['smtp_from_email'] ?? '') ?>"
                                                   placeholder="noreply@yourdomain.com">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="smtp_from_name">From Name</label>
                                            <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name"
                                                   value="<?= htmlspecialchars($settings_map['smtp_from_name'] ?? '') ?>"
                                                   placeholder="Your Platform Name">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">&nbsp;</label>
                                            <div class="form-check">
                                                <input type="checkbox" 
                                                       class="form-check-input" 
                                                       id="smtp_enabled" 
                                                       name="smtp_enabled" 
                                                       value="1"
                                                       <?= !empty($settings_map['smtp_enabled']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="smtp_enabled">
                                                    <strong>Enable SMTP Email</strong>
                                                    <br>
                                                    <small class="text-muted">Check to enable email sending via configured SMTP server</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-grid" style="margin-top:16px">
                                        <div class="form-group">
                                            <label class="form-label" for="test_email">Test Email Address</label>
                                            <input type="email" class="form-control" id="test_email" name="test_email"
                                                   placeholder="test@example.com">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="sa-btn sa-btn-primary" id="testEmailBtn" style="width:100%;justify-content:center">
                                                <i class="feather icon-send"></i> Send Test Email
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="action-buttons">
                                <button type="button" class="sa-btn sa-btn-ghost" onclick="resetForm()">
                                    <i class="feather icon-refresh-cw"></i> Reset Changes
                                </button>
                                <button type="submit" class="sa-btn sa-btn-primary">
                                    <i class="feather icon-save"></i> Save All Settings
                                </button>
                            </div>
                        </form>

                    </div><!-- /sa-content -->
                </div><!-- /sa-wrap -->
            </div><!-- /.pcoded-inner-content -->
        </div><!-- /.pcoded-content -->
    </div><!-- /.pcoded-wrapper -->
</div><!-- /.pcoded-main-container -->


<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('settingsForm');
    const loadingOverlay = document.getElementById('loadingOverlay');

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s ease-out';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);

    // Initialize file upload handlers
    setupFileUpload('platform_logo');
    setupFileUpload('platform_favicon');

    // Form submission handler
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (!validateForm()) return;

        loadingOverlay.style.display = 'flex';

        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                showNotification(data.message, 'success');
                formChanged = false;
                localStorage.removeItem('platform_settings_draft');

                setTimeout(() => {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        window.location.reload();
                    }
                }, 1500);
            } else {
                showNotification(data.message || 'An error occurred while saving settings.', 'error');
            }
        } catch (error) {
            console.error('Submission error:', error);
            showNotification('Error saving settings. Please try again.', 'error');
        } finally {
            loadingOverlay.style.display = 'none';
        }
    });

    // Form validation
    function validateForm() {
        let isValid = true;

        // Clear previous validation states
        form.querySelectorAll('.is-invalid').forEach(field => field.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(feedback => feedback.remove());

        // Required fields validation
        form.querySelectorAll('[required]').forEach(field => {
            if (!field.value.trim()) {
                showFieldError(field, 'This field is required.');
                isValid = false;
            }
        });

        // Email validation
        const supportEmail = form.querySelector('#support_email');
        if (supportEmail?.value && !isValidEmail(supportEmail.value)) {
            showFieldError(supportEmail, 'Please enter a valid email address.');
            isValid = false;
        }

        // Currency validation
        const currencyField = form.querySelector('#default_currency');
        if (currencyField?.value && !/^[A-Z]{3}$/.test(currencyField.value)) {
            showFieldError(currencyField, 'Please enter a valid 3-letter currency code (e.g., USD, EUR).');
            isValid = false;
        }

        // Users validation
        const usersField = form.querySelector('#max_users_per_tenant');
        if (usersField?.value && (!isValidNumber(usersField.value) || parseInt(usersField.value) < 1)) {
            showFieldError(usersField, 'Please enter a valid number greater than 0.');
            isValid = false;
        }

        // Scroll to first error
        if (!isValid) {
            const firstError = form.querySelector('.is-invalid');
            firstError?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError?.focus();
        }

        return isValid;
    }

    // File upload setup
    function setupFileUpload(inputId) {
        const input = document.getElementById(inputId);
        const uploadArea = input?.closest('.image-upload-area');

        if (!input || !uploadArea) return;

        // Drag and drop handlers
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                input.files = files;
                handleFileSelect(input);
            }
        });

        // Keyboard support
        uploadArea.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                input.click();
            }
        });

        // File selection
        input.addEventListener('change', () => handleFileSelect(input));
    }

    // File selection handler
    function handleFileSelect(input) {
        const file = input.files[0];
        if (!file) return;

        const inputId = input.id;
        const config = getFileConfig(inputId);

        // Validate file type
        if (!config.allowedTypes.includes(file.type)) {
            showNotification(`Please select a valid file type for ${inputId.replace('platform_', '')}.`, 'error');
            input.value = '';
            return;
        }

        // Validate file size
        if (file.size > config.maxSize) {
            const sizeMB = Math.round(config.maxSize / 1024 / 1024);
            showNotification(`File size must be less than ${sizeMB}MB.`, 'error');
            input.value = '';
            return;
        }

        // Show preview
        const reader = new FileReader();
        reader.onload = (e) => {
            // Remove existing preview
            const existingPreview = input.closest('.form-group').querySelector('.new-preview');
            existingPreview?.remove();

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

    // Get file configuration
    function getFileConfig(inputId) {
        const configs = {
            platform_logo: {
                allowedTypes: ['image/jpeg', 'image/png', 'image/gif'],
                maxSize: 2 * 1024 * 1024 // 2MB
            },
            platform_favicon: {
                allowedTypes: ['image/x-icon', 'image/png'],
                maxSize: 100 * 1024 // 100KB
            }
        };
        return configs[inputId] || {};
    }

    // Utility functions
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
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.role = 'alert';
        alert.innerHTML = `
            <div class="alert-icon">
                <i class="feather icon-${type === 'success' ? 'check-circle' : 'alert-circle'}"></i>
            </div>
            <div>${message}</div>
            <button type="button" class="btn btn-sm btn-${type} ms-auto" onclick="this.parentElement.remove()" aria-label="Close">
                <i class="feather icon-x"></i>
            </button>
        `;

        alert.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            min-width: 300px;
            box-shadow: var(--shadow-lg);
        `;

        document.body.appendChild(alert);

        setTimeout(() => alert.remove(), 5000);
    }

    // Clear validation on input
    form.querySelectorAll('input, select, textarea').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid');
            this.parentNode.querySelector('.invalid-feedback')?.remove();
        });
    });

    // Currency field auto-uppercase
    document.getElementById('default_currency')?.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });

    // Toggle switch functionality
    document.getElementById('api_enabled')?.addEventListener('change', function() {
        const hiddenInput = form.querySelector('input[name="api_enabled"][type="hidden"]');
        if (hiddenInput) {
            hiddenInput.value = this.checked ? 'true' : 'false';
        }
    });

    // Test Email functionality
    document.getElementById('testEmailBtn')?.addEventListener('click', async () => {
        const testEmail = document.getElementById('test_email').value;
        const smtpHost = document.getElementById('smtp_host').value;
        const smtpUsername = document.getElementById('smtp_username').value;
        const smtpPassword = document.getElementById('smtp_password').value;

        if (!testEmail) {
            showNotification('Please enter a test email address.', 'error');
            return;
        }

        if (!smtpHost || !smtpUsername || !smtpPassword) {
            showNotification('Please configure SMTP settings first.', 'error');
            return;
        }

        const testEmailBtn = document.getElementById('testEmailBtn');
        testEmailBtn.disabled = true;
        testEmailBtn.innerHTML = '<i class="feather icon-loader"></i> Sending...';

        try {
            const response = await fetch('send_test_email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
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
            });

            const data = await response.json();

            if (data.success) {
                showNotification('Test email sent successfully!', 'success');
            } else {
                showNotification(`Failed to send test email: ${data.message}`, 'error');
            }
        } catch (error) {
            console.error('Test email error:', error);
            showNotification('Error sending test email. Please try again.', 'error');
        } finally {
            testEmailBtn.disabled = false;
            testEmailBtn.innerHTML = '<i class="feather icon-send"></i> Send Test Email';
        }
    });
});

// Global functions
function resetForm() {
    if (confirm('Are you sure you want to reset all changes? This will revert to the last saved values.')) {
        window.location.reload();
    }
}

function removePreview(button, inputId) {
    const input = document.getElementById(inputId);
    const preview = button.closest('.new-preview');

    input.value = '';
    preview.remove();
}

// Form change tracking
let formChanged = false;

document.addEventListener('change', () => {
    formChanged = true;
});

document.addEventListener('input', () => {
    formChanged = true;
});

window.addEventListener('beforeunload', (e) => {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// Draft functionality
const DRAFT_KEY = 'platform_settings_draft';

function saveDraft() {
    const form = document.getElementById('settingsForm');
    const formData = new FormData(form);
    const draftData = {};

    for (const [key, value] of formData.entries()) {
        if (!['platform_logo', 'platform_favicon', 'csrf_token'].includes(key)) {
            draftData[key] = value;
        }
    }

    localStorage.setItem(DRAFT_KEY, JSON.stringify(draftData));
}

function loadDraft() {
    const draft = localStorage.getItem(DRAFT_KEY);
    if (!draft) return;

    const draftData = JSON.parse(draft);
    Object.keys(draftData).forEach(key => {
        const input = document.querySelector(`[name="${key}"]`);
        if (input && input.type !== 'file') {
            input.value = draftData[key];
        }
    });
}

function checkForDraft() {
    const draft = localStorage.getItem(DRAFT_KEY);
    if (!draft) return;

    const draftData = JSON.parse(draft);
    let hasChanges = false;

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
            localStorage.removeItem(DRAFT_KEY);
        }
    } else {
        localStorage.removeItem(DRAFT_KEY);
    }
}

// Initialize draft functionality
document.addEventListener('DOMContentLoaded', () => {
    checkForDraft();
    setInterval(saveDraft, 30000); // Auto-save every 30 seconds
});
</script>
</body>
</html>
