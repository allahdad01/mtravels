<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once('../includes/session_check.php');
require_once('../includes/language_helpers.php');
$lang = init_language();
if (isset($_GET['lang'])) { set_language($_GET['lang'], true); }

require_once('../includes/db.php');

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) { session_destroy(); header('Location: ../login.php'); exit(); }
} catch (PDOException $e) { error_log($e->getMessage()); }

$profilePic = !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : 'default-avatar.jpg';
$imagePath  = "../assets/images/user/" . $profilePic;

// Global stats (across all tenants)
$totalTenants = 0; $totalTickets = 0; $daysActive = 0;
try {
    $s = $pdo->query("SELECT COUNT(*) FROM tenants WHERE status='active'");
    $totalTenants = (int)$s->fetchColumn();

    $s = $pdo->prepare("SELECT COUNT(*) FROM support_tickets");
    $s->execute(); $totalTickets = (int)$s->fetchColumn();
} catch (PDOException $e) {}

$createdDate = strtotime($user['created_at'] ?? $user['hire_date'] ?? date('Y-m-d'));
$daysActive  = max(1, floor((time() - $createdDate) / 86400));

// Recent activity
$recentActivity = [];
try {
    $s = $pdo->prepare("SELECT action,table_name,created_at FROM activity_log WHERE user_id=? ORDER BY created_at DESC LIMIT 15");
    $s->execute([$_SESSION['user_id']]); $recentActivity = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Platform settings for favicon
$platformFavicon = 'logo.png';
try {
    $s = $pdo->query("SELECT `value` FROM platform_settings WHERE `key`='platform_logo'");
    $v = $s->fetchColumn();
    if ($v) $platformFavicon = $v;
} catch (PDOException $e) {}

function actIcon($action) {
    $a = strtolower($action);
    $map = [
        'create' => ['icon'=>'M12 4v16m8-8H4','color'=>'#059669'],
        'update' => ['icon'=>'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z','color'=>'#d97706'],
        'delete' => ['icon'=>'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16','color'=>'#dc2626'],
        'login'  => ['icon'=>'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1','color'=>'#6366f1'],
    ];
    $key = strtolower(explode(' ',$action)[0]);
    $d = $map[$key] ?? ['icon'=>'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z','color'=>'#6b7280'];
    return '<svg width="16" height="16" fill="none" stroke="'.$d['color'].'" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="'.$d['icon'].'"/></svg>';
}
?>

    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <link rel="icon" href="../uploads/logo/<?= htmlspecialchars($platformFavicon) ?>" type="image/x-icon">
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <title>Profile - <?= htmlspecialchars($user['name'] ?? 'Super Admin') ?></title>
    </head>
    <style>
        :root {
            --brand: #4099ff;
            --brand-dark: #2673cc;
            --brand-light: #e8f0fe;
            --surface: #fff;
            --surface-2: #f8fafc;
            --border: #e5e7eb;
            --text-primary: #111827;
            --text-muted: #6b7280;
            --text-xs: #9ca3af;
            --success: #059669;
            --warning: #d97706;
            --danger: #dc2626;
            --radius: 10px;
            --radius-lg: 16px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
            --shadow: 0 4px 12px rgba(0,0,0,.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,.10);
            --transition: all .2s cubic-bezier(.4,0,.2,1);
        }
        body { font-family: 'DM Sans', sans-serif; color: var(--text-primary); background: var(--surface-2); }

        #toast-container {
            position: fixed; top: 20px; right: 20px; z-index: 9999;
            display: flex; flex-direction: column; gap: 10px;
        }
        .toast-msg {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 18px; border-radius: var(--radius);
            background: var(--surface); box-shadow: var(--shadow-lg);
            border-left: 4px solid var(--brand);
            font-size: 14px; font-weight: 500; min-width: 280px;
            animation: slideIn .3s ease forwards;
        }
        .toast-msg.success { border-color: var(--success); }
        .toast-msg.error   { border-color: var(--danger); }
        .toast-msg .toast-icon { font-size: 18px; flex-shrink: 0; }
        .toast-msg .toast-close {
            margin-left: auto; cursor: pointer; color: var(--text-xs);
            font-size: 18px; line-height: 1; border: none; background: none; padding: 0;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to   { transform: translateX(0); opacity: 1; }
        }

        .profile-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 24px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 24px 48px;
        }
        @media (max-width: 991px) { .profile-layout { grid-template-columns: 1fr; } }

        .profile-sidebar { display: flex; flex-direction: column; gap: 16px; align-self: start; position: sticky; top: 80px; }
        .sidebar-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); overflow: hidden; }

        .sidebar-cover {
            height: 90px;
            background: linear-gradient(135deg, #4099ff 0%, #0891b2 50%, #2ed8b6 100%);
            position: relative;
        }
        .sidebar-cover::after {
            content: ''; position: absolute; inset: 0;
            background: repeating-linear-gradient(45deg, rgba(255,255,255,.04) 0px, rgba(255,255,255,.04) 1px, transparent 1px, transparent 8px);
        }
        .sidebar-avatar-wrap { padding: 0 24px; margin-top: -40px; position: relative; z-index: 1; }
        .sidebar-avatar { width: 80px; height: 80px; border-radius: 50%; border: 3px solid var(--surface); object-fit: cover; box-shadow: var(--shadow); display: block; }
        .sidebar-identity { padding: 12px 24px 20px; }
        .sidebar-name { font-family: 'DM Serif Display', serif; font-size: 20px; font-weight: 400; margin: 0 0 4px; color: var(--text-primary); letter-spacing: -.2px; }
        .sidebar-role-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 10px; border-radius: 20px; font-size: 12px;
            font-weight: 600; letter-spacing: .3px; text-transform: uppercase;
        }
        .sidebar-meta { padding: 0 24px 20px; }
        .sidebar-meta-row { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-top: 1px solid var(--border); font-size: 13.5px; }
        .sidebar-meta-row:first-child { border-top: none; }
        .sidebar-meta-icon { color: var(--text-muted); flex-shrink: 0; margin-top: 1px; }
        .sidebar-meta-label { color: var(--text-xs); font-size: 11px; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 1px; }
        .sidebar-meta-val { color: var(--text-primary); word-break: break-all; }

        .sidebar-stats { display: grid; grid-template-columns: repeat(3,1fr); border-top: 1px solid var(--border); }
        .sidebar-stat { text-align: center; padding: 16px 8px; border-right: 1px solid var(--border); }
        .sidebar-stat:last-child { border-right: none; }
        .sidebar-stat-num { font-size: 22px; font-weight: 700; color: var(--brand); font-variant-numeric: tabular-nums; line-height: 1; }
        .sidebar-stat-lbl { font-size: 11px; color: var(--text-muted); margin-top: 4px; }

        .profile-main { display: flex; flex-direction: column; gap: 20px; }
        .profile-tabs { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); }
        .tab-nav { display: flex; border-bottom: 1px solid var(--border); padding: 0 4px; overflow-x: auto; }
        .tab-btn {
            display: flex; align-items: center; gap: 7px; padding: 14px 18px;
            font-size: 13.5px; font-weight: 500; color: var(--text-muted);
            border: none; background: none; cursor: pointer; white-space: nowrap;
            border-bottom: 2px solid transparent; margin-bottom: -1px; transition: var(--transition);
        }
        .tab-btn:hover { color: var(--brand); }
        .tab-btn.active { color: var(--brand); border-bottom-color: var(--brand); }
        .tab-btn svg { flex-shrink: 0; }
        .tab-panel { display: none; padding: 28px; }
        .tab-panel.active { display: block; }

        .section-title { font-family: 'DM Serif Display', serif; font-size: 17px; font-weight: 400; color: var(--text-primary); margin: 0 0 20px; display: flex; align-items: center; gap: 8px; }
        .section-title svg { color: var(--brand); }
        .section-divider { border: none; border-top: 1px solid var(--border); margin: 28px 0; }

        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        @media (max-width: 575px) { .info-grid { grid-template-columns: 1fr; } }
        .info-item { padding: 14px 16px; background: var(--surface-2); border-radius: var(--radius); border: 1px solid var(--border); }
        .info-item-label { font-size: 11px; color: var(--text-xs); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
        .info-item-val { font-size: 14px; font-weight: 500; color: var(--text-primary); word-break: break-all; }
        .info-item-val.empty { color: var(--text-muted); font-style: italic; font-weight: 400; }

        .activity-feed { display: flex; flex-direction: column; gap: 0; }
        .activity-item { display: flex; gap: 14px; padding: 14px 0; border-bottom: 1px solid var(--border); align-items: flex-start; }
        .activity-item:last-child { border-bottom: none; }
        .activity-dot { width: 34px; height: 34px; border-radius: 50%; background: var(--surface-2); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px; }
        .activity-body { flex: 1; min-width: 0; }
        .activity-title { font-size: 13.5px; font-weight: 500; color: var(--text-primary); }
        .activity-title span { color: var(--brand); }
        .activity-time { font-size: 12px; color: var(--text-xs); margin-top: 2px; }

        .form-section { display: grid; grid-template-columns: 1fr 280px; gap: 32px; }
        @media (max-width: 767px) { .form-section { grid-template-columns: 1fr; } }
        .form-group { margin-bottom: 18px; }
        .form-label { display: block; font-size: 12.5px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 6px; }
        .form-control-custom { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: var(--radius); font-size: 14px; font-family: inherit; color: var(--text-primary); background: var(--surface); transition: var(--transition); outline: none; }
        .form-control-custom:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(64,153,255,.12); }
        textarea.form-control-custom { resize: vertical; min-height: 90px; }

        .password-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 575px) { .password-row { grid-template-columns: 1fr; } }

        .avatar-upload-card { background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 24px; text-align: center; }
        .avatar-upload-img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--surface); box-shadow: var(--shadow); margin: 0 auto 16px; display: block; }
        .avatar-upload-trigger { display: inline-flex; align-items: center; gap: 7px; padding: 8px 16px; border: 1.5px dashed var(--brand); border-radius: var(--radius); color: var(--brand); font-size: 13px; font-weight: 500; cursor: pointer; transition: var(--transition); background: var(--brand-light); }
        .avatar-upload-trigger:hover { background: #dbeafe; }
        .avatar-upload-hint { font-size: 11.5px; color: var(--text-xs); margin-top: 8px; }

        .btn-primary-custom { display: inline-flex; align-items: center; gap: 7px; padding: 10px 20px; background: var(--brand); color: #fff; border: none; border-radius: var(--radius); font-size: 13.5px; font-weight: 600; cursor: pointer; font-family: inherit; transition: var(--transition); }
        .btn-primary-custom:hover { background: var(--brand-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(64,153,255,.3); }
        .btn-primary-custom:active { transform: translateY(0); }
        .btn-outline-custom { display: inline-flex; align-items: center; gap: 7px; padding: 10px 20px; background: transparent; color: var(--brand); border: 1.5px solid var(--brand); border-radius: var(--radius); font-size: 13.5px; font-weight: 600; cursor: pointer; font-family: inherit; transition: var(--transition); }
        .btn-outline-custom:hover { background: var(--brand-light); }

        .btn-spinner { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); }
        .empty-state svg { color: #d1d5db; margin-bottom: 12px; }
        .empty-state p { font-size: 14px; }
    </style>

<body>

<div id="toast-container" aria-live="polite"></div>
<div class="loader-bg"><div class="loader-track"><div class="loader-fill"></div></div></div>

<?php include '../includes/header_super_admin.php'; ?>

<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">My Profile</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="#!">Profile</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="profile-layout">

            <!-- SIDEBAR -->
            <aside class="profile-sidebar">
                <div class="sidebar-card">
                    <div class="sidebar-cover"></div>
                    <div class="sidebar-avatar-wrap">
                        <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($user['name']) ?>" class="sidebar-avatar" id="sidebarAvatar">
                    </div>
                    <div class="sidebar-identity">
                        <h1 class="sidebar-name"><?= htmlspecialchars($user['name']) ?></h1>
                        <span class="sidebar-role-badge" style="background:#e8f0fe;color:#4099ff">Super Admin</span>
                    </div>
                    <div class="sidebar-stats">
                        <div class="sidebar-stat">
                            <div class="sidebar-stat-num"><?= number_format($totalTenants) ?></div>
                            <div class="sidebar-stat-lbl">Tenants</div>
                        </div>
                        <div class="sidebar-stat">
                            <div class="sidebar-stat-num"><?= number_format($daysActive) ?></div>
                            <div class="sidebar-stat-lbl">Days</div>
                        </div>
                        <div class="sidebar-stat">
                            <div class="sidebar-stat-num"><?= number_format($totalTickets) ?></div>
                            <div class="sidebar-stat-lbl">Tickets</div>
                        </div>
                    </div>
                </div>

                <div class="sidebar-card">
                    <div style="padding:16px 24px 0;font-size:11px;color:var(--text-xs);text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Contact Details</div>
                    <div class="sidebar-meta">
                        <div class="sidebar-meta-row">
                            <svg class="sidebar-meta-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <div>
                                <div class="sidebar-meta-label">Email</div>
                                <div class="sidebar-meta-val"><?= htmlspecialchars($user['email']) ?></div>
                            </div>
                        </div>
                        <div class="sidebar-meta-row">
                            <svg class="sidebar-meta-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <div>
                                <div class="sidebar-meta-label">Phone</div>
                                <div class="sidebar-meta-val"><?= htmlspecialchars($user['phone'] ?? '') ?: '<em style="color:var(--text-xs)">Not provided</em>' ?></div>
                            </div>
                        </div>
                        <div class="sidebar-meta-row">
                            <svg class="sidebar-meta-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <div>
                                <div class="sidebar-meta-label">Address</div>
                                <div class="sidebar-meta-val"><?= htmlspecialchars($user['address'] ?? '') ?: '<em style="color:var(--text-xs)">Not provided</em>' ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- MAIN CONTENT -->
            <main class="profile-main">
                <div class="profile-tabs">
                    <nav class="tab-nav" role="tablist" aria-label="Profile sections">
                        <button class="tab-btn active" role="tab" aria-selected="true" aria-controls="panel-overview" data-tab="overview">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Overview
                        </button>
                        <button class="tab-btn" role="tab" aria-selected="false" aria-controls="panel-activity" data-tab="activity">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            Activity
                        </button>
                        <button class="tab-btn" role="tab" aria-selected="false" aria-controls="panel-settings" data-tab="settings">
                            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Settings
                        </button>
                    </nav>

                    <!-- Overview panel -->
                    <div class="tab-panel active" id="panel-overview" role="tabpanel">
                        <h2 class="section-title">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Personal Information
                        </h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-item-label">Full Name</div>
                                <div class="info-item-val"><?= htmlspecialchars($user['name']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-item-label">Email Address</div>
                                <div class="info-item-val"><?= htmlspecialchars($user['email']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-item-label">Phone Number</div>
                                <?php if (!empty($user['phone'])): ?>
                                    <div class="info-item-val"><?= htmlspecialchars($user['phone']) ?></div>
                                <?php else: ?>
                                    <div class="info-item-val empty">Not provided</div>
                                <?php endif; ?>
                            </div>
                            <div class="info-item">
                                <div class="info-item-label">Role</div>
                                <div class="info-item-val">
                                    <span class="sidebar-role-badge" style="background:#e8f0fe;color:#4099ff">Super Admin</span>
                                </div>
                            </div>
                            <div class="info-item" style="grid-column:1/-1">
                                <div class="info-item-label">Address</div>
                                <?php if (!empty($user['address'])): ?>
                                    <div class="info-item-val"><?= htmlspecialchars($user['address']) ?></div>
                                <?php else: ?>
                                    <div class="info-item-val empty">Not provided</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Activity panel -->
                    <div class="tab-panel" id="panel-activity" role="tabpanel">
                        <h2 class="section-title">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Recent Activity
                        </h2>
                        <?php if (count($recentActivity) > 0): ?>
                        <div class="activity-feed">
                            <?php foreach ($recentActivity as $act): ?>
                            <div class="activity-item">
                                <div class="activity-dot"><?= actIcon($act['action']) ?></div>
                                <div class="activity-body">
                                    <div class="activity-title">
                                        <?= ucfirst(htmlspecialchars($act['action'])) ?> on <span><?= ucwords(str_replace('_',' ',htmlspecialchars($act['table_name'] ?? 'system'))) ?></span>
                                    </div>
                                    <div class="activity-time"><?= date('M d, Y · H:i', strtotime($act['created_at'])) ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            <p>No activity recorded yet</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Settings panel -->
                    <div class="tab-panel" id="panel-settings" role="tabpanel">
                        <div class="form-section">
                            <div>
                                <h2 class="section-title">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    Edit Profile
                                </h2>
                                <form id="profileUpdateForm" enctype="multipart/form-data" novalidate>
                                    <div class="form-group">
                                        <label class="form-label" for="fieldName">Full Name</label>
                                        <input type="text" id="fieldName" name="name" class="form-control-custom" value="<?= htmlspecialchars($user['name']) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="fieldEmail">Email Address</label>
                                        <input type="email" id="fieldEmail" name="email" class="form-control-custom" value="<?= htmlspecialchars($user['email']) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="fieldPhone">Phone Number</label>
                                        <input type="tel" id="fieldPhone" name="phone" class="form-control-custom" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="fieldAddress">Address</label>
                                        <textarea id="fieldAddress" name="address" class="form-control-custom"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                                    </div>
                                    <input type="file" id="profileImageInput" name="profile_image" accept="image/*" class="d-none" onchange="previewAvatar(this)">
                                    <button type="submit" class="btn-primary-custom" id="saveProfileBtn">
                                        <div class="btn-spinner" id="saveSpinner"></div>
                                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        Save Changes
                                    </button>
                                </form>

                                <hr class="section-divider">

                                <h2 class="section-title">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    Change Password
                                </h2>
                                <div class="form-group">
                                    <label class="form-label" for="currentPassword">Current Password</label>
                                    <input type="password" id="currentPassword" class="form-control-custom" autocomplete="current-password">
                                </div>
                                <div class="password-row">
                                    <div class="form-group">
                                        <label class="form-label" for="newPassword">New Password</label>
                                        <input type="password" id="newPassword" class="form-control-custom" autocomplete="new-password">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="confirmPassword">Confirm Password</label>
                                        <input type="password" id="confirmPassword" class="form-control-custom" autocomplete="new-password">
                                    </div>
                                </div>
                                <div id="passwordStrength" style="margin-top:-10px;margin-bottom:14px;font-size:12px;"></div>
                                <button type="button" class="btn-outline-custom" id="changePasswordBtn">
                                    <div class="btn-spinner" id="pwSpinner" style="border-top-color:var(--brand);border-color:rgba(64,153,255,.2);"></div>
                                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Update Password
                                </button>
                            </div>

                            <div>
                                <h2 class="section-title">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    Photo
                                </h2>
                                <div class="avatar-upload-card">
                                    <img src="<?= $imagePath ?>" alt="Profile" id="avatarPreview" class="avatar-upload-img">
                                    <div class="avatar-upload-trigger" onclick="document.getElementById('profileImageInput').click()">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                        Upload Photo
                                    </div>
                                    <p class="avatar-upload-hint">JPG, PNG or GIF · Max 5 MB</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

        </div>
    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
function showToast(message, type = 'info') {
    const icons = { success: '✓', error: '✕', info: 'ℹ' };
    const tc = document.getElementById('toast-container');
    const t = document.createElement('div');
    t.className = `toast-msg ${type}`;
    t.innerHTML = `<span class="toast-icon">${icons[type] ?? icons.info}</span><span>${message}</span><button class="toast-close" aria-label="Close">&times;</button>`;
    t.querySelector('.toast-close').onclick = () => t.remove();
    tc.appendChild(t);
    setTimeout(() => t.remove(), 5000);
}

document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => { b.classList.remove('active'); b.setAttribute('aria-selected','false'); });
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        btn.setAttribute('aria-selected','true');
        document.getElementById('panel-' + btn.dataset.tab).classList.add('active');
    });
});

function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('avatarPreview').src = e.target.result;
            document.getElementById('sidebarAvatar').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

document.getElementById('newPassword').addEventListener('input', function () {
    const v = this.value;
    const el = document.getElementById('passwordStrength');
    if (!v) { el.textContent = ''; return; }
    let score = 0;
    if (v.length >= 8) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const levels = [
        { label: 'Too short', color: '#dc2626' },
        { label: 'Weak', color: '#dc2626' },
        { label: 'Fair', color: '#d97706' },
        { label: 'Good', color: '#2563eb' },
        { label: 'Strong', color: '#059669' },
    ];
    el.innerHTML = `<span style="color:${levels[score].color};font-weight:600">${levels[score].label}</span>`;
});

document.getElementById('profileUpdateForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('saveProfileBtn');
    const spinner = document.getElementById('saveSpinner');
    btn.disabled = true;
    spinner.style.display = 'block';
    try {
        const res = await fetch('update_profile.php', { method: 'POST', body: new FormData(this) });
        const data = await res.json();
        if (data.success) { showToast('Profile updated successfully', 'success'); setTimeout(() => location.reload(true), 1200); }
        else { showToast(data.message || 'Update failed', 'error'); }
    } catch { showToast('Network error — please try again', 'error'); }
    finally { btn.disabled = false; spinner.style.display = 'none'; }
});

document.getElementById('changePasswordBtn').addEventListener('click', async function () {
    const cur = document.getElementById('currentPassword').value;
    const nw = document.getElementById('newPassword').value;
    const conf = document.getElementById('confirmPassword').value;
    if (!cur || !nw || !conf) { showToast('Please fill in all password fields', 'error'); return; }
    if (nw !== conf) { showToast('New passwords do not match', 'error'); return; }
    if (nw.length < 8) { showToast('Password must be at least 8 characters', 'error'); return; }
    const btn = this;
    const spinner = document.getElementById('pwSpinner');
    btn.disabled = true;
    spinner.style.display = 'block';
    const fd = new FormData();
    fd.append('current_password', cur);
    fd.append('new_password', nw);
    fd.append('confirm_password', conf);
    try {
        const res = await fetch('update_profile.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast('Password changed successfully', 'success');
            ['currentPassword','newPassword','confirmPassword'].forEach(id => document.getElementById(id).value = '');
            document.getElementById('passwordStrength').textContent = '';
        } else { showToast(data.message || 'Password change failed', 'error'); }
    } catch { showToast('Network error — please try again', 'error'); }
    finally { btn.disabled = false; spinner.style.display = 'none'; }
});
</script>
</body>
</html>
