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
    error_log("Unauthorized access attempt to platform_settings.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
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
:root {
    --primary-color: #6366f1;
    --primary-dark: #4f46e5;
    --secondary-color: #10b981;
    --accent-color: #f59e0b;
    --danger-color: #ef4444;
    --success-color: #22c55e;
    --warning-color: #f59e0b;
    --info-color: #06b6d4;
    --light-bg: #f8fafc;
    --dark-bg: #1f2937;
    --text-primary: #111827;
    --text-secondary: #6b7280;
    --border-color: #e5e7eb;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --border-radius-sm: 0.375rem;
    --border-radius-md: 0.5rem;
    --border-radius-lg: 0.75rem;
    --border-radius-xl: 1rem;
    --transition-fast: 0.15s ease-in-out;
    --transition-normal: 0.3s ease-in-out;
}

* {
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background-color: var(--light-bg);
    color: var(--text-primary);
    line-height: 1.6;
}

.settings-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.settings-card {
    background: white;
    border-radius: var(--border-radius-xl);
    box-shadow: var(--shadow-lg);
    overflow: hidden;
    transition: var(--transition-normal);
    border: 1px solid var(--border-color);
}

.settings-card:hover {
    box-shadow: var(--shadow-xl);
    transform: translateY(-2px);
}

.settings-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
    color: white;
    padding: 3rem 2rem;
    position: relative;
    overflow: hidden;
}

.settings-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,0.1) 0%, transparent 50%);
}

.settings-header-content {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.settings-header-icon {
    font-size: 2.5rem;
    opacity: 0.9;
}

.settings-header h1 {
    margin: 0;
    font-size: 2.25rem;
    font-weight: 700;
    letter-spacing: -0.025em;
}

.settings-header p {
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
    font-size: 1.125rem;
}

.settings-content {
    padding: 2rem;
}

.settings-section {
    background: white;
    border-radius: var(--border-radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    border: 1px solid var(--border-color);
    transition: var(--transition-normal);
    position: relative;
}

.settings-section:hover {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.section-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--primary-color);
}

.section-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    border-radius: var(--border-radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.section-title {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
}

.section-description {
    margin: 0;
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.form-group {
    position: relative;
}

.form-label {
    display: block;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border-color);
    border-radius: var(--border-radius-md);
    font-size: 0.875rem;
    transition: var(--transition-fast);
    background: white;
    color: var(--text-primary);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-control.is-invalid {
    border-color: var(--danger-color);
    background-color: #fef2f2;
}

.form-control.is-valid {
    border-color: var(--success-color);
    background-color: #f0fdf4;
}

.invalid-feedback {
    color: var(--danger-color);
    font-size: 0.75rem;
    margin-top: 0.25rem;
    display: block;
}

.valid-feedback {
    color: var(--success-color);
    font-size: 0.75rem;
    margin-top: 0.25rem;
    display: block;
}

.image-upload-area {
    border: 2px dashed var(--border-color);
    border-radius: var(--border-radius-lg);
    padding: 2rem;
    text-align: center;
    background: var(--light-bg);
    transition: var(--transition-normal);
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.image-upload-area:hover {
    border-color: var(--primary-color);
    background: rgba(99, 102, 241, 0.05);
}

.image-upload-area.dragover {
    border-color: var(--primary-color);
    background: rgba(99, 102, 241, 0.1);
    transform: scale(1.02);
}

.upload-icon {
    font-size: 3rem;
    color: var(--text-secondary);
    margin-bottom: 1rem;
    display: block;
}

.upload-text h5 {
    margin: 0 0 0.5rem 0;
    color: var(--text-primary);
    font-weight: 600;
}

.upload-text p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 0.875rem;
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
    border-radius: var(--border-radius-md);
    border: 1px solid var(--border-color);
    text-align: center;
    min-width: 150px;
    box-shadow: var(--shadow-sm);
}

.current-image {
    border-radius: var(--border-radius-md);
    border: 2px solid var(--border-color);
    transition: var(--transition-normal);
    max-width: 200px;
    max-height: 120px;
    object-fit: cover;
}

.current-image:hover {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: var(--border-radius-md);
    font-weight: 600;
    font-size: 0.875rem;
    text-decoration: none;
    cursor: pointer;
    transition: var(--transition-fast);
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: white;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-lg);
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
}

.btn-success {
    background: var(--success-color);
    color: white;
}

.btn-success:hover {
    background: #16a34a;
    transform: translateY(-1px);
}

.btn-danger {
    background: var(--danger-color);
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

.btn-info {
    background: var(--info-color);
    color: white;
}

.btn-info:hover {
    background: #0891b2;
    transform: translateY(-1px);
}

.alert {
    padding: 1rem 1.25rem;
    border-radius: var(--border-radius-lg);
    margin-bottom: 1.5rem;
    border: 1px solid transparent;
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.alert-success {
    background: rgba(34, 197, 94, 0.1);
    border-color: rgba(34, 197, 94, 0.2);
    color: #166534;
}

.alert-danger {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.2);
    color: #991b1b;
}

.alert-icon {
    flex-shrink: 0;
    font-size: 1.25rem;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 3rem;
    height: 1.5rem;
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
    background-color: #cbd5e1;
    transition: var(--transition-normal);
    border-radius: 1.5rem;
}

.slider:before {
    position: absolute;
    content: "";
    height: 1rem;
    width: 1rem;
    left: 0.25rem;
    bottom: 0.25rem;
    background-color: white;
    transition: var(--transition-normal);
    border-radius: 50%;
    box-shadow: var(--shadow-sm);
}

input:checked + .slider {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
}

input:checked + .slider:before {
    transform: translateX(1.5rem);
}

.action-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    backdrop-filter: blur(4px);
}

.loading-spinner {
    width: 3rem;
    height: 3rem;
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-top: 4px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .settings-container {
        padding: 1rem;
    }

    .settings-header {
        padding: 2rem 1rem;
    }

    .settings-header h1 {
        font-size: 1.875rem;
    }

    .settings-content {
        padding: 1rem;
    }

    .settings-section {
        padding: 1.5rem;
    }

    .form-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .action-buttons {
        flex-direction: column;
        align-items: stretch;
    }

    .btn {
        justify-content: center;
    }
}

@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
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
                                <div class="settings-container">
                                    <div class="settings-card">
                                        <div class="settings-header">
                                            <div class="settings-header-content">
                                                <div class="settings-header-icon">
                                                    <i class="feather icon-settings"></i>
                                                </div>
                                                <div>
                                                    <h1>Platform Configuration</h1>
                                                    <p>Manage your platform's core settings, branding, and functionality</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="settings-content">

                                            <?php if (isset($_GET['error'])): ?>
                                                <div class="alert alert-danger" role="alert">
                                                    <div class="alert-icon">
                                                        <i class="feather icon-alert-circle"></i>
                                                    </div>
                                                    <div>
                                                        <strong>Error:</strong> <?= htmlspecialchars(urldecode($_GET['error'])) ?>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-danger ms-auto" onclick="this.parentElement.remove()" aria-label="Close">
                                                        <i class="feather icon-x"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (isset($_GET['success'])): ?>
                                                <div class="alert alert-success" role="alert">
                                                    <div class="alert-icon">
                                                        <i class="feather icon-check-circle"></i>
                                                    </div>
                                                    <div>
                                                        <strong>Success:</strong> <?= htmlspecialchars(urldecode($_GET['success'])) ?>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-success ms-auto" onclick="this.parentElement.remove()" aria-label="Close">
                                                        <i class="feather icon-x"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>

                                        <form id="settingsForm" enctype="multipart/form-data" method="POST" action="update_settings.php">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            
                                           <!-- Platform Identity Section -->
                                           <div class="settings-section">
                                               <div class="section-header">
                                                   <div class="section-icon">
                                                       <i class="feather icon-building"></i>
                                                   </div>
                                                   <div>
                                                       <h2 class="section-title">Platform Identity</h2>
                                                       <p class="section-description">Configure your platform's basic information and contact details</p>
                                                   </div>
                                               </div>

                                               <div class="form-grid">
                                                   <div class="form-group">
                                                       <label class="form-label" for="platform_name">Platform Name</label>
                                                       <input type="text" class="form-control" id="platform_name" name="platform_name"
                                                              value="<?= htmlspecialchars($settings_map['platform_name'] ?? '') ?>"
                                                              placeholder="Enter your platform name" required>
                                                   </div>
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
                                                       <label class="form-label" for="website_url">Website URL</label>
                                                       <input type="url" class="form-control" id="website_url" name="website_url"
                                                              value="<?= htmlspecialchars($settings_map['website_url'] ?? '') ?>"
                                                              placeholder="https://yourplatform.com">
                                                   </div>
                                               </div>
                                           </div>

                                           <!-- Visual Branding Section -->
                                           <div class="settings-section">
                                               <div class="section-header">
                                                   <div class="section-icon">
                                                       <i class="feather icon-image"></i>
                                                   </div>
                                                   <div>
                                                       <h2 class="section-title">Visual Branding</h2>
                                                       <p class="section-description">Upload and manage your platform's visual assets</p>
                                                   </div>
                                               </div>

                                               <div class="form-grid">
                                                   <div class="form-group">
                                                       <label class="form-label" for="platform_logo">Platform Logo</label>
                                                       <div class="image-upload-area" onclick="document.getElementById('platform_logo').click()" role="button" tabindex="0" aria-label="Upload platform logo">
                                                           <input type="file" class="d-none" id="platform_logo" name="platform_logo" accept="image/*" aria-describedby="logo-help">
                                                           <div class="upload-icon" aria-hidden="true">
                                                               <i class="feather icon-camera"></i>
                                                           </div>
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
                                                                        alt="Current Platform Logo"
                                                                        class="current-image">
                                                                   <br>
                                                                   <small class="text-muted"><?= htmlspecialchars($settings_map['platform_logo']) ?></small>
                                                               </div>
                                                           </div>
                                                       <?php endif; ?>
                                                   </div>

                                                   <div class="form-group">
                                                       <label class="form-label" for="platform_favicon">Platform Favicon</label>
                                                       <div class="image-upload-area" onclick="document.getElementById('platform_favicon').click()" role="button" tabindex="0" aria-label="Upload platform favicon">
                                                           <input type="file" class="d-none" id="platform_favicon" name="platform_favicon" accept=".ico,.png" aria-describedby="favicon-help">
                                                           <div class="upload-icon" aria-hidden="true">
                                                               <i class="feather icon-link"></i>
                                                           </div>
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

                                           <!-- Contact Information Section -->
                                           <div class="settings-section">
                                               <div class="section-header">
                                                   <div class="section-icon">
                                                       <i class="feather icon-phone"></i>
                                                   </div>
                                                   <div>
                                                       <h2 class="section-title">Contact Information</h2>
                                                       <p class="section-description">Set up contact details for your platform</p>
                                                   </div>
                                               </div>

                                               <div class="form-grid">
                                                   <div class="form-group">
                                                       <label class="form-label" for="contact_phone">Contact Phone</label>
                                                       <input type="tel" class="form-control" id="contact_phone" name="contact_phone"
                                                              value="<?= htmlspecialchars($settings_map['contact_phone'] ?? '') ?>"
                                                              placeholder="+1234567890">
                                                   </div>
                                                   <div class="form-group">
                                                       <label class="form-label" for="support_phone">Support Phone</label>
                                                       <input type="tel" class="form-control" id="support_phone" name="support_phone"
                                                              value="<?= htmlspecialchars($settings_map['support_phone'] ?? '') ?>"
                                                              placeholder="+1234567890">
                                                   </div>
                                               </div>

                                               <div class="form-group">
                                                   <label class="form-label" for="contact_address">Contact Address</label>
                                                   <textarea class="form-control" id="contact_address" name="contact_address" rows="3"
                                                             placeholder="Enter your business address"><?= htmlspecialchars($settings_map['contact_address'] ?? '') ?></textarea>
                                               </div>
                                           </div>

                                           <!-- Social Media Section -->
                                           <div class="settings-section">
                                               <div class="section-header">
                                                   <div class="section-icon">
                                                       <i class="feather icon-globe"></i>
                                                   </div>
                                                   <div>
                                                       <h2 class="section-title">Social Media Links</h2>
                                                       <p class="section-description">Connect your social media profiles</p>
                                                   </div>
                                               </div>

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

                                           <!-- SMTP Configuration Section -->
                                           <div class="settings-section">
                                               <div class="section-header">
                                                   <div class="section-icon">
                                                       <i class="feather icon-mail"></i>
                                                   </div>
                                                   <div>
                                                       <h2 class="section-title">SMTP Configuration</h2>
                                                       <p class="section-description">Configure email settings for your platform</p>
                                                   </div>
                                               </div>

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
                                                   <div></div> <!-- Empty grid item for alignment -->
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
                                               </div>

                                               <!-- Test Email Section -->
                                               <div class="form-grid">
                                                   <div class="form-group">
                                                       <label class="form-label" for="test_email">Test Email Address</label>
                                                       <input type="email" class="form-control" id="test_email" name="test_email"
                                                              placeholder="test@example.com">
                                                   </div>
                                                   <div class="form-group">
                                                       <button type="button" class="btn btn-info" id="testEmailBtn">
                                                           <i class="feather icon-send"></i>
                                                           Send Test Email
                                                       </button>
                                                   </div>
                                               </div>
                                           </div>

                                           <!-- Platform Configuration Section -->
                                           <div class="settings-section">
                                               <div class="section-header">
                                                   <div class="section-icon">
                                                       <i class="feather icon-settings"></i>
                                                   </div>
                                                   <div>
                                                       <h2 class="section-title">Platform Configuration</h2>
                                                       <p class="section-description">Configure core platform settings and limits</p>
                                                   </div>
                                               </div>

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
                                                       <div class="d-flex align-items-center">
                                                           <span>Disabled</span>
                                                           <label class="toggle-switch">
                                                               <input type="hidden" name="api_enabled" value="false">
                                                               <input type="checkbox" id="api_enabled" name="api_enabled" value="true"
                                                                      <?= ($settings_map['api_enabled'] ?? '') === 'true' ? 'checked' : '' ?>>
                                                               <span class="slider"></span>
                                                           </label>
                                                           <span>Enabled</span>
                                                       </div>
                                                   </div>
                                               </div>
                                           </div>

                                           <div class="action-buttons">
                                               <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                                   <i class="feather icon-refresh-cw"></i>
                                                   Reset Changes
                                               </button>
                                               <button type="submit" class="btn btn-primary">
                                                   <i class="feather icon-save"></i>
                                                   Save All Settings
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
