<?php
include 'header.php';
$tenant_id = $_SESSION['tenant_id'];

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tenant_super_admin') {
    header('Location: ../login.php');
    exit();
}

$alertMessage = '';
$alertType = '';
if (isset($_SESSION['settings_message'])) {
    $alertMessage = $_SESSION['settings_message'];
    $alertType = $_SESSION['settings_type'] ?? 'success';
    unset($_SESSION['settings_message'], $_SESSION['settings_type']);
}

require_once('../includes/db.php');
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root{
    --surface:#f4f7fe;--card-bg:#ffffff;--border:#e8edf5;
    --text-main:#1a2340;--text-sub:#6b7a99;
    --green:#22c55e;--red:#ef4444;
    /* Agency Settings: Teal â†’ Blue */
    --c1:#0891b2;--c2:#1d4ed8;
    --radius:14px;--shadow:0 2px 12px rgba(8,145,178,.08);
}
*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}
.pcoded-content{padding:20px!important}
.page-header{display:none!important}

/* Header */
.dash-header{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 8px 32px rgba(64,153,255,.28);position:relative;overflow:hidden}
.dash-header::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='30' cy='30' r='20' fill='%23ffffff' fill-opacity='0.05'/%3E%3C/svg%3E") repeat}
.dash-header h4{font-size:22px;font-weight:800;color:#fff;margin:0 0 4px;letter-spacing:-.4px;position:relative}
.dash-header p{color:rgba(255,255,255,.8);margin:0;font-size:13px;position:relative}
.back-btn{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.2);color:#fff;border:1.5px solid rgba(255,255,255,.3);border-radius:10px;padding:9px 16px;font-family:inherit;font-size:12px;font-weight:700;text-decoration:none;transition:all .2s;position:relative}
.back-btn:hover{background:rgba(255,255,255,.3);color:#fff;text-decoration:none}

/* Alert */
.dash-alert{border-radius:12px;padding:13px 16px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600}
.dash-alert.success{background:rgba(34,197,94,.1);border:1.5px solid rgba(34,197,94,.25);color:#166534}
.dash-alert.danger {background:rgba(239,68,68,.08);border:1.5px solid rgba(239,68,68,.2);color:#991b1b}
.dash-alert.warning{background:rgba(245,158,11,.08);border:1.5px solid rgba(245,158,11,.2);color:#92400e}
.dash-alert-close{margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;opacity:.7;padding:0;font-size:16px;line-height:1}

/* Card */
.dash-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}
.dash-card:last-child{margin-bottom:0}
.dash-card-head{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.dash-card-head h6{font-size:14px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}
.dash-card-head .ico{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0}
.ico-agency{background:linear-gradient(135deg,#4099ff,#2ed8b6)}
.ico-smtp  {background:linear-gradient(135deg,#6d28d9,#db2777)}
.ico-test  {background:linear-gradient(135deg,#059669,#0d9488)}
.dash-card-body{padding:24px}

/* Section heading */
.section-heading{display:flex;align-items:center;gap:8px;margin-bottom:16px;padding-bottom:10px;border-bottom:1.5px solid var(--border)}
.section-heading-icon{width:30px;height:30px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.sh-agency{background:rgba(64,153,255,.12);color:#4099ff}
.sh-smtp  {background:rgba(109,40,217,.12);color:#6d28d9}
.section-heading h6{font-size:13px;font-weight:700;margin:0;color:var(--text-main)}

/* Form */
.form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.form-grid-3{display:grid;grid-template-columns:2fr 1fr 1fr;gap:18px}
@media(max-width:768px){.form-grid-2,.form-grid-3{grid-template-columns:1fr}}
.form-group{margin-bottom:0}
.form-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:flex;align-items:center;gap:5px;margin-bottom:6px}
.form-label i{font-size:12px;color:#4099ff}
.form-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:10px 13px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s,box-shadow .2s}
.form-input:focus{border-color:#4099ff;background:#fff;box-shadow:0 0 0 3px rgba(64,153,255,.1)}
textarea.form-input{resize:vertical;min-height:88px}
select.form-input{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7a99' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:36px}

/* Logo upload */
.logo-upload-area{border:2px dashed var(--border);border-radius:12px;padding:20px;text-align:center;transition:border-color .2s;cursor:pointer;position:relative;overflow:hidden}
.logo-upload-area:hover{border-color:#4099ff}
.logo-upload-area input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.logo-upload-label{font-size:12px;color:var(--text-sub);margin-top:8px;pointer-events:none}
.logo-upload-label strong{color:#4099ff}
.logo-preview-wrap{display:flex;align-items:center;justify-content:center;margin-top:14px}
.logo-preview-wrap img{max-height:90px;max-width:220px;object-fit:contain;border-radius:10px;border:1.5px solid var(--border);padding:6px;background:var(--surface)}

/* Password toggle */
.input-pw-wrap{position:relative}
.input-pw-wrap .form-input{padding-right:42px}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-sub);padding:0;font-size:14px}
.pw-toggle:hover{color:#4099ff}

/* SMTP test row */
.test-row{display:grid;grid-template-columns:1fr auto;gap:12px;align-items:end}
@media(max-width:600px){.test-row{grid-template-columns:1fr}}
.test-btn{display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#059669,#0d9488);color:#fff;border:none;border-radius:10px;padding:10px 18px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;transition:opacity .2s}
.test-btn:hover{opacity:.9}
.test-btn:disabled{opacity:.6;cursor:not-allowed}

/* Divider */
.form-divider{border:none;border-top:1px solid var(--border);margin:22px 0}

/* Form actions */
.form-actions{display:flex;align-items:center;gap:10px;padding-top:16px;border-top:1px solid var(--border);margin-top:4px}
.save-btn{display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#0891b2,#1d4ed8);color:#fff;border:none;border-radius:10px;padding:10px 24px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:opacity .2s}
.save-btn:hover{opacity:.9}
.reset-btn-form{display:inline-flex;align-items:center;gap:7px;background:var(--surface);color:var(--text-sub);border:1.5px solid var(--border);border-radius:10px;padding:10px 18px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
.reset-btn-form:hover{border-color:var(--text-sub);color:var(--text-main)}

/* No settings state */
.empty-state{text-align:center;padding:50px 20px}
.empty-state i{font-size:48px;color:var(--border);display:block;margin-bottom:14px}
.empty-state p{color:var(--text-sub);font-size:14px}

/* Toast notification */
.notif-toast{position:fixed;top:20px;right:20px;z-index:9999;min-width:280px;border-radius:12px;padding:13px 16px;display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.12);animation:slideIn .3s ease}
@keyframes slideIn{from{transform:translateX(120%);opacity:0}to{transform:translateX(0);opacity:1}}
.notif-toast.success{background:#fff;border:1.5px solid rgba(34,197,94,.3);color:#166534}
.notif-toast.error  {background:#fff;border:1.5px solid rgba(239,68,68,.3);color:#991b1b}
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <!-- Header -->
    <div class="dash-header">
        <div>
            <h4><i class="feather icon-settings" style="margin-right:8px;"></i><?php echo __('agency_settings'); ?></h4>
            <p><?php echo __('manage_your_agency_settings'); ?></p>
        </div>
        <a href="dashboard.php" class="back-btn"><i class="feather icon-arrow-left"></i><?php echo __('back_to_dashboard'); ?></a>
    </div>

    <!-- Flash alert -->
    <?php if ($alertMessage): ?>
    <div class="dash-alert <?= $alertType ?>">
        <i class="feather icon-<?= $alertType==='success'?'check-circle':'alert-circle' ?>"></i>
        <?= htmlspecialchars($alertMessage) ?>
        <button class="dash-alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php endif; ?>

    <?php if (!empty($settings)): ?>

    <form action="updateSettings.php" method="POST" enctype="multipart/form-data" id="mainSettingsForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars($settings['id']) ?>">
        <input type="hidden" name="existing_logo" value="<?= htmlspecialchars($settings['logo']) ?>">

        <!-- Agency Information -->
        <div class="dash-card">
            <div class="dash-card-head">
                <h6><span class="ico ico-agency"><i class="feather icon-home"></i></span><?php echo __('agency_information'); ?></h6>
            </div>
            <div class="dash-card-body">

                <div class="form-grid-2" style="margin-bottom:18px;">
                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-hash"></i><?php echo __('agency_name') ?></label>
                        <input type="text" class="form-input" name="agency_name" value="<?= htmlspecialchars($settings['agency_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-edit-2"></i><?php echo __('agency_title') ?></label>
                        <input type="text" class="form-input" name="title" value="<?= htmlspecialchars($settings['title']) ?>" required>
                    </div>
                </div>

                <div class="form-grid-2" style="margin-bottom:18px;">
                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-phone"></i><?php echo __('phone') ?></label>
                        <input type="text" class="form-input" name="phone" value="<?= htmlspecialchars($settings['phone']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-mail"></i><?php echo __('email') ?></label>
                        <input type="email" class="form-input" name="email" value="<?= htmlspecialchars($settings['email']) ?>" required>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-map-pin"></i><?php echo __('address') ?></label>
                        <textarea class="form-input" name="address" rows="3" required><?= htmlspecialchars($settings['address']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-image"></i><?php echo __('logo') ?></label>
                        <div class="logo-upload-area" id="logoDropArea">
                            <input type="file" name="logo" accept="image/*" id="logoInput" onchange="previewImage(this)">
                            <i class="feather icon-upload-cloud" style="font-size:28px;color:#0891b2;display:block;margin-bottom:6px;"></i>
                            <div class="logo-upload-label"><?php echo __('choose_file') ?> â€” <strong>click to browse</strong></div>
                            <div style="font-size:10px;color:var(--text-sub);margin-top:4px;">PNG, JPG, SVG up to 2MB</div>
                        </div>
                        <div class="logo-preview-wrap" id="logoPreviewWrap">
                            <img src="<?= $settings['logo'] ? '../uploads/logo/'.htmlspecialchars($settings['logo']) : '../assets/images/default-logo.png' ?>" alt="Logo" id="logoPreview">
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- SMTP Configuration -->
        <div class="dash-card">
            <div class="dash-card-head">
                <h6><span class="ico ico-smtp"><i class="feather icon-mail"></i></span><?php echo __('smtp_configuration'); ?></h6>
            </div>
            <div class="dash-card-body">

                <div class="form-grid-3" style="margin-bottom:18px;">
                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-server"></i><?php echo __('smtp_host'); ?></label>
                        <input type="text" class="form-input" id="smtp_host" name="smtp_host" value="<?= htmlspecialchars($settings['smtp_host']??'') ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-hash"></i><?php echo __('smtp_port'); ?></label>
                        <input type="number" class="form-input" id="smtp_port" name="smtp_port" value="<?= htmlspecialchars($settings['smtp_port']??'') ?>" placeholder="587" min="1" max="65535">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-shield"></i><?php echo __('encryption'); ?></label>
                        <select class="form-input" id="smtp_encryption" name="smtp_encryption">
                            <option value=""><?php echo __('none'); ?></option>
                            <option value="tls" <?= ($settings['smtp_encryption']??'')==='tls'?'selected':'' ?>>TLS</option>
                            <option value="ssl" <?= ($settings['smtp_encryption']??'')==='ssl'?'selected':'' ?>>SSL</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid-2" style="margin-bottom:18px;">
                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-user"></i><?php echo __('smtp_username'); ?></label>
                        <input type="text" class="form-input" id="smtp_username" name="smtp_username" value="<?= htmlspecialchars($settings['smtp_username']??'') ?>" placeholder="your-email@gmail.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-lock"></i><?php echo __('smtp_password'); ?></label>
                        <div class="input-pw-wrap">
                            <input type="password" class="form-input" id="smtp_password" name="smtp_password" value="<?= htmlspecialchars($settings['smtp_password']??'') ?>" placeholder="<?php echo __('your_smtp_password'); ?>">
                            <button type="button" class="pw-toggle" onclick="togglePw('smtp_password',this)"><i class="feather icon-eye"></i></button>
                        </div>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-send"></i><?php echo __('from_email'); ?></label>
                        <input type="email" class="form-input" id="smtp_from_email" name="smtp_from_email" value="<?= htmlspecialchars($settings['smtp_from_email']??'') ?>" placeholder="noreply@yourdomain.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-tag"></i><?php echo __('from_name'); ?></label>
                        <input type="text" class="form-input" id="smtp_from_name" name="smtp_from_name" value="<?= htmlspecialchars($settings['smtp_from_name']??'') ?>" placeholder="<?php echo __('your_agency_name'); ?>">
                    </div>
                </div>

                <div class="form-row" style="margin-top: 15px;">
                    <div class="form-group col-md-12">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" 
                                   class="custom-control-input" 
                                   id="smtp_enabled" 
                                   name="smtp_enabled" 
                                   value="1"
                                   <?= (!empty($settings['smtp_enabled']) ? 'checked' : '') ?>>
                            <label class="custom-control-label" for="smtp_enabled">
                                <i class="feather icon-mail"></i><?php echo __('smtp_enabled'); ?>
                                <br>
                                <small style="color: #999;"><?php echo __('enable_to_send_emails'); ?></small>
                            </label>
                        </div>
                    </div>
                </div>

                <hr class="form-divider">

                <!-- Test email -->
                <div class="section-heading">
                    <span class="section-heading-icon sh-smtp"><i class="feather icon-send"></i></span>
                    <h6><?php echo __('test_email_address'); ?></h6>
                </div>
                <div class="test-row">
                    <div class="form-group">
                        <label class="form-label"><i class="feather icon-mail"></i><?php echo __('test_email_address'); ?></label>
                        <input type="email" class="form-input" id="test_email" placeholder="test@example.com">
                    </div>
                    <button type="button" class="test-btn" id="testEmailBtn">
                    <i class="feather icon-paper-plane"></i> <?php echo __('send_test_email'); ?>
                    </button>
                </div>

            </div>
        </div>

        <!-- Actions -->
        <div class="form-actions">
            <button type="submit" class="save-btn"><i class="feather icon-save"></i><?php echo __('update_settings'); ?></button>
            <button type="reset" class="reset-btn-form"><i class="feather icon-refresh-ccw"></i><?php echo __('reset'); ?></button>
        </div>

    </form>

    <?php else: ?>
    <div class="dash-card">
        <div class="dash-card-body">
            <div class="empty-state">
                <i class="feather icon-alert-circle"></i>
                <p><?php echo __('no_settings_found_in_the_database'); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>

<?php include 'footer.php'; ?>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { document.getElementById('logoPreview').src = e.target.result; };
        reader.readAsDataURL(input.files[0]);
        // Update upload label
        const area = document.getElementById('logoDropArea');
        const label = area.querySelector('.logo-upload-label');
        if (label) label.textContent = input.files[0].name;
    }
}

function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (inp.type === 'password') { inp.type = 'text'; icon.className = 'feather icon-eye-off'; }
    else { inp.type = 'password'; icon.className = 'feather icon-eye'; }
}

function showToast(message, type = 'success') {
    document.querySelectorAll('.notif-toast').forEach(n => n.remove());
    const t = document.createElement('div');
    t.className = `notif-toast ${type}`;
    t.innerHTML = `<i class="feather icon-${type==='success'?'check-circle':'alert-circle'}"></i><span>${message}</span>`;
    document.body.appendChild(t);
    setTimeout(() => { t.style.transition='opacity .4s'; t.style.opacity='0'; setTimeout(()=>t.remove(),400); }, 4500);
}

document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide flash alert
    const alert = document.querySelector('.dash-alert');
    if (alert) setTimeout(() => { alert.style.transition='opacity .4s'; alert.style.opacity='0'; setTimeout(()=>alert.remove(),400); }, 5000);

    // Test email
    const testBtn = document.getElementById('testEmailBtn');
    if (testBtn) {
        testBtn.addEventListener('click', function() {
            const testEmail = document.getElementById('test_email').value.trim();
            const host      = document.getElementById('smtp_host').value.trim();
            const user      = document.getElementById('smtp_username').value.trim();
            const pass      = document.getElementById('smtp_password').value.trim();

            if (!testEmail) { showToast('Please enter a test email address.', 'error'); return; }
            if (!host || !user || !pass) { showToast('Please configure SMTP settings first.', 'error'); return; }

            testBtn.disabled = true;
            testBtn.innerHTML = '<i class="feather icon-loader"></i>Sending...';

            fetch('send_test_email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    test_email: testEmail,
                    smtp_host: host,
                    smtp_port: document.getElementById('smtp_port').value,
                    smtp_encryption: document.getElementById('smtp_encryption').value,
                    smtp_username: user,
                    smtp_password: pass,
                    smtp_from_email: document.getElementById('smtp_from_email').value,
                    smtp_from_name: document.getElementById('smtp_from_name').value
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) showToast('Test email sent to ' + testEmail);
                else showToast('Failed: ' + (data.message || 'Unknown error'), 'error');
            })
            .catch(err => showToast('Error: ' + err.message, 'error'))
            .finally(() => {
                testBtn.disabled = false;
                testBtn.innerHTML = '<i class="feather icon-send"></i><?php echo __('send_test_email'); ?>';
            });
        });
    }
});
</script>