<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once('../includes/session_check.php');
require_once('../includes/language_helpers.php');
$lang = init_language();
if (isset($_GET['lang'])) { set_language($_GET['lang'], true); }

require_once('../includes/db.php');
$tenant_id = $_SESSION['tenant_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? AND tenant_id=?");
    $stmt->execute([$_SESSION['user_id'], $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) { session_destroy(); header('Location: ../login.php'); exit(); }
} catch (PDOException $e) { error_log($e->getMessage()); }

$profilePic = !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : 'default-avatar.jpg';
$imagePath  = "../assets/images/user/" . $profilePic;

try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id=?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log($e->getMessage()); }

// Stats
$activityCount = 0; $bookingCount = 0;
try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE user_id=? AND tenant_id=?");
    $s->execute([$_SESSION['user_id'],$tenant_id]); $activityCount = $s->fetchColumn();
    foreach(['ticket_bookings','hotel_bookings','umrah_bookings','visa_applications'] as $tbl) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM $tbl WHERE created_by=? AND tenant_id=?");
        $s->execute([$_SESSION['user_id'],$tenant_id]); $bookingCount += $s->fetchColumn();
    }
} catch (PDOException $e) {}

$createdDate = strtotime($user['created_at'] ?? $user['hire_date'] ?? date('Y-m-d'));
$daysActive  = max(1, floor((time() - $createdDate) / 86400));

// Recent activity
$recentActivity = [];
try {
    $s = $pdo->prepare("SELECT action,table_name,created_at FROM activity_log WHERE user_id=? AND tenant_id=? ORDER BY created_at DESC LIMIT 15");
    $s->execute([$_SESSION['user_id'],$tenant_id]); $recentActivity = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

function actStyle($action) {
    $a = strtolower($action);
    if (in_array($a,['create','insert'])) return ['cls'=>'as-create','icon'=>'plus-circle'];
    if (in_array($a,['update','edit']))   return ['cls'=>'as-update','icon'=>'edit-2'];
    if (in_array($a,['delete','remove'])) return ['cls'=>'as-delete','icon'=>'trash-2'];
    if ($a==='login')                     return ['cls'=>'as-login', 'icon'=>'log-in'];
    if ($a==='logout')                    return ['cls'=>'as-logout','icon'=>'log-out'];
    return ['cls'=>'as-other','icon'=>'activity'];
}
?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <title><?= __('user_profile') ?> — <?= htmlspecialchars($settings['agency_name']??'') ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=0,minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="icon" href="../uploads/logo/<?= htmlspecialchars($settings['logo']??'') ?>" type="image/x-icon">
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="../assets/plugins/animation/css/animate.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/mobile-responsive.css">
</head>
<body>

<div class="loader-bg"><div class="loader-track"><div class="loader-fill"></div></div></div>
<?php include 'header.php'; ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap');

:root{
    --surface:#f4f7fe;--card-bg:#ffffff;--border:#e8edf5;
    --text-main:#1a2340;--text-sub:#6b7a99;
    --green:#22c55e;--red:#ef4444;--amber:#f59e0b;
    /* Profile: Blue → Teal (same as owner dashboard) */
    --c1:#4099ff;--c2:#2ed8b6;
    --radius:14px;--shadow:0 2px 12px rgba(64,153,255,.08);
}
*,*::before,*::after{box-sizing:border-box}
body,.pcoded-main-container{font-family:'Plus Jakarta Sans',sans-serif!important;background:var(--surface)!important;color:var(--text-main)!important}
.pcoded-content{padding:20px!important}
.page-header{display:none!important}

/* Hero banner */
.profile-hero{background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);border-radius:var(--radius);padding:32px 28px;margin-bottom:20px;display:flex;align-items:center;gap:24px;box-shadow:0 8px 32px rgba(64,153,255,.28);position:relative;overflow:hidden}
.profile-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='30' cy='30' r='20' fill='%23ffffff' fill-opacity='0.05'/%3E%3C/svg%3E") repeat}
.hero-avatar-ring{width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.25);padding:3px;flex-shrink:0;position:relative;cursor:pointer}
.hero-avatar-ring img{width:100%;height:100%;border-radius:50%;object-fit:cover;border:3px solid var(--card-bg)}
.hero-avatar-ring .cam-overlay{position:absolute;inset:0;border-radius:50%;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s;font-size:18px;color:#fff}
.hero-avatar-ring:hover .cam-overlay{opacity:1}
.hero-info{position:relative;flex:1}
.hero-name{font-size:20px;font-weight:800;color:#fff;margin:0 0 3px;letter-spacing:-.3px}
.hero-role{font-size:13px;color:rgba(255,255,255,.8);margin:0 0 8px;text-transform:capitalize}
.hero-meta{display:flex;gap:12px;flex-wrap:wrap}
.hero-meta-tag{display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,.2);border-radius:20px;padding:4px 11px;font-size:11px;font-weight:600;color:#fff}

/* Stat row */
.stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px}
.stat-tile{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);padding:18px;text-align:center}
.stat-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;margin:0 auto 10px}
.si-act  {background:rgba(64,153,255,.12);color:#4099ff}
.si-days {background:rgba(46,216,182,.12);color:#0d9488}
.si-book {background:rgba(124,58,237,.12);color:#7c3aed}
.stat-val{font-family:'JetBrains Mono',monospace;font-size:22px;font-weight:800;color:var(--text-main);margin-bottom:3px}
.stat-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub)}

/* Card */
.dash-card{background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden;margin-bottom:20px}

/* Tab bar */
.profile-tabs{display:flex;gap:4px;padding:14px 20px 0;border-bottom:1px solid var(--border);background:var(--card-bg)}
.profile-tab{background:none;border:none;border-bottom:3px solid transparent;padding:8px 16px 12px;font-family:inherit;font-size:13px;font-weight:700;color:var(--text-sub);cursor:pointer;display:flex;align-items:center;gap:6px;margin-bottom:-1px;transition:all .2s;white-space:nowrap}
.profile-tab.active{color:#4099ff;border-bottom-color:#4099ff}
.profile-tab:hover{color:#4099ff}
.tab-pane-custom{display:none;padding:24px}
.tab-pane-custom.active{display:block}

/* Overview layout */
.overview-grid{display:grid;grid-template-columns:2fr 1fr;gap:20px}
@media(max-width:800px){.overview-grid{grid-template-columns:1fr}}
.info-section-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:14px;display:flex;align-items:center;gap:6px}
.info-section-title i{color:#4099ff}
.info-table{width:100%;border-collapse:collapse}
.info-table tr{border-bottom:1px solid var(--border)}
.info-table tr:last-child{border-bottom:none}
.info-table td{padding:11px 0;font-size:13px;vertical-align:middle}
.info-table td:first-child{font-weight:700;color:var(--text-sub);width:38%;font-size:11px;text-transform:uppercase;letter-spacing:.4px}
.info-table td:last-child{color:var(--text-main);font-weight:600}
.role-badge{display:inline-flex;align-items:center;background:rgba(64,153,255,.1);color:#1d4ed8;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;text-transform:capitalize}

/* Avatar sidebar */
.avatar-sidebar{text-align:center}
.avatar-sidebar-img{width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid var(--border);margin-bottom:12px}
.avatar-sidebar-name{font-size:14px;font-weight:700;color:var(--text-main);margin-bottom:4px}
.avatar-note{font-size:11px;color:var(--text-sub)}

/* Activity feed */
.activity-feed{display:flex;flex-direction:column;gap:8px}
.activity-item{display:flex;align-items:flex-start;gap:12px;background:var(--surface);border-radius:10px;padding:12px 14px}
.act-dot{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.as-create{background:rgba(34,197,94,.12);color:#166534}
.as-update{background:rgba(64,153,255,.1);color:#1d4ed8}
.as-delete{background:rgba(239,68,68,.1);color:#991b1b}
.as-login {background:rgba(8,145,178,.1);color:#0e7490}
.as-logout{background:rgba(107,122,153,.1);color:var(--text-sub)}
.as-other {background:rgba(217,119,6,.1);color:#92400e}
.act-body strong{font-size:12px;font-weight:700;color:var(--text-main);display:block}
.act-body span{font-size:11px;color:var(--text-sub);font-family:'JetBrains Mono',monospace}
.empty-feed{text-align:center;padding:40px 20px}
.empty-feed i{font-size:40px;opacity:.2;display:block;margin-bottom:12px}
.empty-feed p{color:var(--text-sub);font-size:13px;margin:0}

/* Settings layout */
.settings-layout{display:grid;grid-template-columns:2fr 1fr;gap:20px}
@media(max-width:800px){.settings-layout{grid-template-columns:1fr}}
.form-section-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);margin-bottom:14px;display:flex;align-items:center;gap:6px;padding-bottom:10px;border-bottom:1px solid var(--border)}
.form-section-title i{color:#4099ff}
.form-group-custom{margin-bottom:14px}
.form-group-custom:last-child{margin-bottom:0}
.form-label-custom{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-sub);display:block;margin-bottom:5px}
.form-input{width:100%;border:1.5px solid var(--border);border-radius:10px;padding:10px 13px;font-family:inherit;font-size:13px;color:var(--text-main);background:var(--surface);outline:none;transition:border-color .2s,box-shadow .2s}
.form-input:focus{border-color:#4099ff;background:#fff;box-shadow:0 0 0 3px rgba(64,153,255,.1)}
textarea.form-input{resize:vertical;min-height:80px}
.input-pw-wrap{position:relative}
.input-pw-wrap .form-input{padding-right:42px}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-sub);padding:0;font-size:14px}
.pw-toggle:hover{color:#4099ff}

/* PWD strength */
.pwd-strength{height:4px;border-radius:2px;background:var(--border);margin-top:6px;overflow:hidden}
.pwd-strength-bar{height:100%;border-radius:2px;width:0;transition:width .3s,background .3s}
.pwd-hint{font-size:10px;color:var(--text-sub);margin-top:3px}
.match-msg{font-size:11px;font-weight:600;margin-top:4px}
.match-msg.ok{color:#166534}
.match-msg.err{color:#991b1b}
.match-ok{border-color:#22c55e!important;box-shadow:0 0 0 3px rgba(34,197,94,.1)!important}
.match-fail{border-color:#ef4444!important;box-shadow:0 0 0 3px rgba(239,68,68,.1)!important}

/* Buttons */
.save-btn{display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#4099ff,#2ed8b6);color:#fff;border:none;border-radius:10px;padding:10px 22px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:opacity .2s;margin-top:4px}
.save-btn:hover{opacity:.9}
.save-btn:disabled{opacity:.6;cursor:not-allowed}
.pwd-btn{display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#dc2626,#ef4444);color:#fff;border:none;border-radius:10px;padding:10px 22px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:opacity .2s;margin-top:4px}
.pwd-btn:hover{opacity:.9}
.pwd-btn:disabled{opacity:.6;cursor:not-allowed}

/* Upload area */
.upload-area{border:2px dashed var(--border);border-radius:12px;padding:20px;text-align:center;cursor:pointer;transition:all .2s}
.upload-area:hover{border-color:#4099ff;background:rgba(64,153,255,.04)}
.upload-preview{width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid var(--border);margin-bottom:10px}
.upload-label{font-size:12px;font-weight:600;color:var(--text-sub)}
.upload-hint{font-size:10px;color:var(--text-sub);margin-top:4px}

/* Toast */
.notif-toast{position:fixed;top:20px;right:20px;z-index:9999;min-width:270px;border-radius:12px;padding:12px 16px;display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.12);animation:slideIn .3s ease}
@keyframes slideIn{from{transform:translateX(120%);opacity:0}to{transform:translateX(0);opacity:1}}
.notif-toast.success{background:#fff;border:1.5px solid rgba(34,197,94,.3);color:#166534}
.notif-toast.error  {background:#fff;border:1.5px solid rgba(239,68,68,.3);color:#991b1b}
</style>

<div class="pcoded-main-container">
<div class="pcoded-content">

    <!-- Hero banner -->
    <div class="profile-hero">
        <div class="hero-avatar-ring" onclick="document.getElementById('profileImage').click()" title="Change photo">
            <img src="<?= $imagePath ?>" alt="Profile" id="heroAvatar">
            <div class="cam-overlay"><i class="feather icon-camera"></i></div>
        </div>
        <div class="hero-info">
            <div class="hero-name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="hero-role"><?= htmlspecialchars($user['role']) ?></div>
            <div class="hero-meta">
                <?php if (!empty($user['email'])): ?><span class="hero-meta-tag"><i class="feather icon-mail" style="font-size:11px;"></i><?= htmlspecialchars($user['email']) ?></span><?php endif; ?>
                <?php if (!empty($user['phone'])): ?><span class="hero-meta-tag"><i class="feather icon-phone" style="font-size:11px;"></i><?= htmlspecialchars($user['phone']) ?></span><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stat tiles -->
    <div class="stat-row">
        <div class="stat-tile">
            <div class="stat-icon si-act"><i class="feather icon-activity"></i></div>
            <div class="stat-val"><?= number_format($activityCount) ?></div>
            <div class="stat-lbl">Activities</div>
        </div>
        <div class="stat-tile">
            <div class="stat-icon si-days"><i class="feather icon-calendar"></i></div>
            <div class="stat-val"><?= number_format($daysActive) ?></div>
            <div class="stat-lbl">Days Active</div>
        </div>
        <div class="stat-tile">
            <div class="stat-icon si-book"><i class="feather icon-briefcase"></i></div>
            <div class="stat-val"><?= number_format($bookingCount) ?></div>
            <div class="stat-lbl">Bookings</div>
        </div>
    </div>

    <!-- Main card with tabs -->
    <div class="dash-card">
        <div class="profile-tabs">
            <button class="profile-tab active" onclick="switchTab('overview',this)"><i class="feather icon-user"></i>Overview</button>
            <button class="profile-tab" onclick="switchTab('activity',this)"><i class="feather icon-activity"></i>Activity</button>
            <button class="profile-tab" onclick="switchTab('settings',this)"><i class="feather icon-settings"></i>Settings</button>
        </div>

        <!-- Overview -->
        <div class="tab-pane-custom active" id="pane-overview">
            <div class="overview-grid">
                <div>
                    <div class="info-section-title"><i class="feather icon-user"></i>Personal Information</div>
                    <table class="info-table">
                        <tr><td>Full Name</td><td><?= htmlspecialchars($user['name']) ?></td></tr>
                        <tr><td>Email</td><td><?= htmlspecialchars($user['email']) ?></td></tr>
                        <tr><td>Phone</td><td><?= htmlspecialchars($user['phone']??'') ?: '<span style="color:var(--text-sub);">Not provided</span>' ?></td></tr>
                        <tr><td>Address</td><td><?= htmlspecialchars($user['address']??'') ?: '<span style="color:var(--text-sub);">Not provided</span>' ?></td></tr>
                        <tr><td>Role</td><td><span class="role-badge"><?= htmlspecialchars($user['role']??'User') ?></span></td></tr>
                        <tr><td>Join Date</td><td><?= date('M d, Y', strtotime($user['hire_date']??$user['created_at'])) ?></td></tr>
                    </table>
                </div>
                <div class="avatar-sidebar">
                    <div class="info-section-title" style="justify-content:center;"><i class="feather icon-image"></i>Profile Photo</div>
                    <img src="<?= $imagePath ?>" alt="Profile" class="avatar-sidebar-img">
                    <div class="avatar-sidebar-name"><?= htmlspecialchars($user['name']) ?></div>
                    <div class="avatar-note">Update in Settings tab</div>
                </div>
            </div>
        </div>

        <!-- Activity -->
        <div class="tab-pane-custom" id="pane-activity">
            <div class="info-section-title"><i class="feather icon-activity"></i>Recent Activity</div>
            <?php if (empty($recentActivity)): ?>
            <div class="empty-feed"><i class="feather icon-activity"></i><p>No recent activity recorded.</p></div>
            <?php else: ?>
            <div class="activity-feed">
                <?php foreach ($recentActivity as $act):
                    $s = actStyle($act['action']);
                ?>
                <div class="activity-item">
                    <div class="act-dot <?= $s['cls'] ?>"><i class="feather icon-<?= $s['icon'] ?>"></i></div>
                    <div class="act-body">
                        <strong><?= ucfirst(htmlspecialchars($act['action'])) ?> — <?= htmlspecialchars($act['table_name']??'system') ?></strong>
                        <span><?= date('M d, Y  H:i', strtotime($act['created_at'])) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Settings -->
        <div class="tab-pane-custom" id="pane-settings">
            <div class="settings-layout">
                <div>
                    <!-- Edit profile -->
                    <div class="form-section-title"><i class="feather icon-edit"></i>Edit Profile</div>
                    <form id="profileForm" enctype="multipart/form-data">
                        <div class="form-group-custom">
                            <label class="form-label-custom">Full Name</label>
                            <input type="text" class="form-input" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        <div class="form-group-custom">
                            <label class="form-label-custom">Email Address</label>
                            <input type="email" class="form-input" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="form-group-custom">
                            <label class="form-label-custom">Phone Number</label>
                            <input type="tel" class="form-input" name="phone" value="<?= htmlspecialchars($user['phone']??'') ?>">
                        </div>
                        <div class="form-group-custom">
                            <label class="form-label-custom">Address</label>
                            <textarea class="form-input" name="address" rows="3"><?= htmlspecialchars($user['address']??'') ?></textarea>
                        </div>
                        <input type="file" class="d-none" id="profileImage" name="profile_image" accept="image/*" onchange="previewProfileImage(this)">
                        <button type="submit" class="save-btn" id="saveProfileBtn"><i class="feather icon-save"></i>Save Changes</button>
                    </form>

                    <hr style="border-color:var(--border);margin:22px 0;">

                    <!-- Change password -->
                    <div class="form-section-title"><i class="feather icon-lock"></i>Change Password</div>
                    <div class="form-group-custom">
                        <label class="form-label-custom">Current Password</label>
                        <div class="input-pw-wrap">
                            <input type="password" class="form-input" id="currentPassword">
                            <button type="button" class="pw-toggle" onclick="togglePw('currentPassword',this)"><i class="feather icon-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group-custom">
                        <label class="form-label-custom">New Password</label>
                        <div class="input-pw-wrap">
                            <input type="password" class="form-input" id="newPassword">
                            <button type="button" class="pw-toggle" onclick="togglePw('newPassword',this)"><i class="feather icon-eye"></i></button>
                        </div>
                        <div class="pwd-strength"><div class="pwd-strength-bar" id="strengthBar"></div></div>
                        <div class="pwd-hint" id="strengthLabel">Enter a new password</div>
                    </div>
                    <div class="form-group-custom">
                        <label class="form-label-custom">Confirm New Password</label>
                        <div class="input-pw-wrap">
                            <input type="password" class="form-input" id="confirmPassword">
                            <button type="button" class="pw-toggle" onclick="togglePw('confirmPassword',this)"><i class="feather icon-eye"></i></button>
                        </div>
                        <div class="match-msg" id="matchMsg"></div>
                    </div>
                    <button type="button" class="pwd-btn" id="changePwdBtn" onclick="changePassword()"><i class="feather icon-lock"></i>Update Password</button>
                </div>

                <!-- Avatar upload -->
                <div>
                    <div class="form-section-title"><i class="feather icon-camera"></i>Profile Picture</div>
                    <div class="upload-area" onclick="document.getElementById('profileImage').click()">
                        <img src="<?= $imagePath ?>" alt="Preview" class="upload-preview" id="profilePreview">
                        <div class="upload-label"><i class="feather icon-upload-cloud" style="font-size:18px;color:#4099ff;display:block;margin-bottom:5px;"></i>Click to change</div>
                        <div class="upload-hint">JPG · PNG · GIF · up to 5MB</div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /dash-card -->

</div>
</div>

<?php include '../modals/umrah/profile_modal.php'; ?>
<?php include '../modals/umrah/settings_modal.php'; ?>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="../assets/js/mobile-menu.js"></script>

<script>
/* ── Tabs ── */
function switchTab(name, btn) {
    document.querySelectorAll('.profile-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane-custom').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('pane-'+name).classList.add('active');
}

/* ── Toast ── */
function toast(msg, type='success') {
    document.querySelectorAll('.notif-toast').forEach(n => n.remove());
    const t = document.createElement('div');
    t.className = `notif-toast ${type}`;
    t.innerHTML = `<i class="feather icon-${type==='success'?'check-circle':'alert-circle'}"></i><span>${msg}</span>`;
    document.body.appendChild(t);
    setTimeout(() => { t.style.transition='opacity .4s'; t.style.opacity=0; setTimeout(()=>t.remove(),400); }, 4000);
}

/* ── Password visibility ── */
function togglePw(id, btn) {
    const inp=document.getElementById(id), icon=btn.querySelector('i');
    inp.type = inp.type==='password' ? 'text' : 'password';
    icon.className = inp.type==='text' ? 'feather icon-eye-off' : 'feather icon-eye';
}

/* ── Strength meter ── */
document.getElementById('newPassword').addEventListener('input', function() {
    const v=this.value, bar=document.getElementById('strengthBar'), lbl=document.getElementById('strengthLabel');
    let sc=0;
    if(v.length>=12) sc++; if(/[A-Z]/.test(v)) sc++; if(/[a-z]/.test(v)) sc++;
    if(/[0-9]/.test(v)) sc++; if(/[^A-Za-z0-9]/.test(v)) sc++;
    const pct=(sc/5)*100;
    const cols=['#ef4444','#f97316','#f59e0b','#22c55e','#16a34a'];
    const labs=['Very weak','Weak','Fair','Strong','Very strong'];
    bar.style.width=pct+'%'; bar.style.background=cols[sc-1]||'#e5e7eb';
    lbl.textContent=v.length===0?'Enter a new password':(labs[sc-1]||'Very weak');
    lbl.style.color=cols[sc-1]||'var(--text-sub)';
    checkMatch();
});

document.getElementById('confirmPassword').addEventListener('input', checkMatch);
function checkMatch() {
    const np=document.getElementById('newPassword').value, cp=document.getElementById('confirmPassword').value;
    const el=document.getElementById('confirmPassword'), msg=document.getElementById('matchMsg');
    if(!cp){el.classList.remove('match-ok','match-fail');msg.textContent='';return;}
    if(np===cp){el.classList.add('match-ok');el.classList.remove('match-fail');msg.className='match-msg ok';msg.textContent='✓ Passwords match';}
    else{el.classList.add('match-fail');el.classList.remove('match-ok');msg.className='match-msg err';msg.textContent='✗ Passwords do not match';}
}

/* ── Image preview ── */
function previewProfileImage(input) {
    if (input.files && input.files[0]) {
        const r=new FileReader();
        r.onload = e => {
            document.getElementById('profilePreview').src = e.target.result;
            document.getElementById('heroAvatar').src = e.target.result;
        };
        r.readAsDataURL(input.files[0]);
    }
}

/* ── Save profile ── */
document.getElementById('profileForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn=document.getElementById('saveProfileBtn');
    btn.disabled=true; btn.innerHTML='<i class="feather icon-loader" style="animation:spin .7s linear infinite;"></i> Saving…';
    try {
        const r=await fetch('update_profile.php',{method:'POST',body:new FormData(this)});
        const d=await r.json();
        toast(d.success?'Profile updated successfully!':'Error: '+(d.message||'Unknown error'), d.success?'success':'error');
        if(d.success) setTimeout(()=>location.reload(),1500);
    } catch(err){ toast('Error updating profile','error'); }
    finally { btn.disabled=false; btn.innerHTML='<i class="feather icon-save"></i>Save Changes'; }
});

/* ── Change password ── */
async function changePassword() {
    const cp=document.getElementById('currentPassword').value;
    const np=document.getElementById('newPassword').value;
    const cf=document.getElementById('confirmPassword').value;
    if (!cp||!np||!cf){ toast('Please fill in all password fields','error'); return; }
    if (np!==cf){ toast('New passwords do not match','error'); return; }
    const btn=document.getElementById('changePwdBtn');
    btn.disabled=true; btn.innerHTML='<i class="feather icon-loader" style="animation:spin .7s linear infinite;"></i> Updating…';
    const fd=new FormData(); fd.append('current_password',cp); fd.append('new_password',np); fd.append('confirm_password',cf);
    try {
        const r=await fetch('api/change_password.php',{method:'POST',body:fd});
        const d=await r.json();
        toast(d.success?'Password changed successfully!':'Error: '+(d.message||'Unknown error'), d.success?'success':'error');
        if(d.success){ document.getElementById('currentPassword').value=''; document.getElementById('newPassword').value=''; document.getElementById('confirmPassword').value=''; }
    } catch(err){ toast('Error changing password','error'); }
    finally { btn.disabled=false; btn.innerHTML='<i class="feather icon-lock"></i>Update Password'; }
}

/* ── Spin keyframe ── */
const sty=document.createElement('style'); sty.textContent='@keyframes spin{to{transform:rotate(360deg)}}'; document.head.appendChild(sty);
</script>
</body>
</html>